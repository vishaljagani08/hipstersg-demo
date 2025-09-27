<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateImageVariants;
use App\Models\Image;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    // Return the Blade UI
    public function ui()
    {
        return view('uploads.ui');
    }

    // Create Upload DB row and return numeric id
    public function initiate(Request $r)
    {
        $data = $r->validate([
            'original_name' => 'required|string',
            'size' => 'nullable|integer',
            'checksum' => 'nullable|string',
            'meta' => 'nullable|array'
        ]);

        $upload = Upload::create([
            'original_name' => $data['original_name'],
            'size' => $data['size'] ?? null,
            'checksum' => $data['checksum'] ?? null,
            'status' => 'pending',
            'meta' => $data['meta'] ?? null,
        ]);

        return response()->json([
            'upload_id' => $upload->id,
            'message' => 'initiated'
        ]);
    }

    // Receive single chunk (called many times). Expects 'file' file field and 'upload_id' in query or body.
    public function uploadChunk(Request $r)
    {
        // Accept both Resumable.js param names and generic ones
        $uploadId = $r->input('upload_id') ?? $r->query('upload_id');
        if (!$uploadId) {
            // Try resumableIdentifier fallback (if you used that)
            $uploadId = $r->input('resumableIdentifier') ?? null;
        }

        if (!$uploadId) {
            return response()->json(['error' => 'missing upload_id'], 422);
        }

        $upload = Upload::find($uploadId);
        if (!$upload) return response()->json(['error'=>'upload not found'], 404);

        // Resumable.js uses resumableChunkNumber (1-based). Support both chunk_index and resumableChunkNumber.
        $chunkIndex = null;
        if ($r->has('chunk_index')) $chunkIndex = (int)$r->input('chunk_index');
        elseif ($r->has('resumableChunkNumber')) $chunkIndex = (int)$r->input('resumableChunkNumber') - 1; // convert to 0-based
        else $chunkIndex = (int)$r->input('chunk', 0);

        if (! $r->hasFile('file')) {
            return response()->json(['error'=>'no chunk file found'], 422);
        }

        $file = $r->file('file');

        $tmpDir = Storage::path("app/tmp/uploads/{$upload->id}");
        @mkdir($tmpDir, 0755, true);

        $tmpPath = "{$tmpDir}/chunk_{$chunkIndex}.part.tmp";
        $finalPath = "{$tmpDir}/chunk_{$chunkIndex}.part";

        // Move uploaded chunk to .tmp first
        $file->move(dirname($tmpPath), basename($tmpPath));

        // optional per-chunk checksum (client may send chunk_checksum)
        if ($r->filled('chunk_checksum')) {
            if (hash_file('sha256', $tmpPath) !== $r->input('chunk_checksum')) {
                @unlink($tmpPath);
                return response()->json(['error'=>'chunk checksum mismatch'], 422);
            }
        }

        // Atomic rename (overwrite if exists)
        @rename($tmpPath, $finalPath);

        return response()->json(['ok' => true, 'index' => $chunkIndex]);
    }

    // Return present chunk indices (for resume)
    public function status(Upload $upload)
    {
        $dir = Storage::path("app/tmp/uploads/{$upload->id}");
        if (!is_dir($dir)) return response()->json(['chunks' => []]);

        $parts = glob("{$dir}/chunk_*.part");
        $present = [];
        foreach ($parts as $p) {
            if (preg_match('/chunk_(\d+)\.part$/', $p, $m)) $present[] = (int)$m[1];
        }
        sort($present);
        return response()->json(['chunks' => $present]);
    }

    // Assemble parts -> final file, verify final checksum, move to public disk, create Image row, dispatch variant job
    public function complete(Request $r, Upload $upload)
    {
        $dir = Storage::path("app/tmp/uploads/{$upload->id}");
        if (!is_dir($dir)) return response()->json(['error'=>'no_parts'], 422);

        // distributed lock; requires cache driver that supports locks (redis, memcached). Falls back if not available.
        $lock = Cache::lock("upload:{$upload->id}:assemble", 60);

        if (!$lock->get()) {
            return response()->json(['error' => 'assembly_locked'], 423);
        }

        try {
            $parts = glob("{$dir}/chunk_*.part");
            if (empty($parts)) {
                return response()->json(['error' => 'no_parts'], 422);
            }

            // ensure proper order
            natsort($parts);

            $assembled = "{$dir}/assembled.bin";
            $out = fopen($assembled, 'wb');

            foreach ($parts as $p) {
                $in = fopen($p, 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
            fclose($out);

            // final checksum check (if provided at initiation)
            $expected = $upload->checksum;
            $actual = hash_file('sha256', $assembled);
            if ($expected && $expected !== $actual) {
                $upload->update(['status' => 'failed']);
                return response()->json(['error' => 'checksum_mismatch', 'expected' => $expected, 'actual' => $actual], 422);
            }

            // move to public storage
            $ext = pathinfo($upload->original_name, PATHINFO_EXTENSION) ?: 'bin';
            $publicDir = "uploads/{$upload->id}";
            $publicName = "original.{$ext}";
            $publicPath = "{$publicDir}/{$publicName}";

            // ensure folder exists on disk
            Storage::disk('public')->putFileAs($publicDir, new \Illuminate\Http\File($assembled), $publicName);

            $upload->update([
                'status' => 'completed',
                'storage_path' => $publicPath
            ]);

            // create image record for original
            $img = Image::create([
                'upload_id' => $upload->id,
                'variant' => 'original',
                'path' => $publicPath
            ]);

            // dispatch variants generation job (queued)
            GenerateImageVariants::dispatch($upload->id);

            return response()->json(['ok' => true, 'upload_id' => $upload->id, 'path' => $publicPath]);

        } finally {
            $lock->release();
        }
    }
}
