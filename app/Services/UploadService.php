<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class UploadService
{
    protected $uploadsPath;

    public function __construct()
    {
        $this->uploadsPath = $uploadsPath ?? base_path('storage/app/public/app\mocks');

        if (!is_dir($this->uploadsPath)) {
            mkdir($this->uploadsPath, 0755, true);
        }
    }

    /**
     * Initiate a new upload and return unique upload ID
     */
    public function initiateUpload(string $filename, int $size, ?string $checksum = null): string
    {
        // Use normal numeric ID or increment (for simplicity, timestamp + random)
        $uploadId = time() . rand(1000, 9999);

        $uploadDir = $this->getUploadPath($uploadId);
        if (!File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }

        // Store initial metadata
        $meta = [
            'filename' => $filename,
            'size' => $size,
            'checksum' => $checksum,
            'chunks' => [],
        ];

        File::put($uploadDir . '/meta.json', json_encode($meta));

        return $uploadId;
    }

    /**
     * Store a single chunk
     */
    public function storeChunk(string $uploadId, int $chunkIndex, string $content)
    {
        $uploadDir = $this->getUploadPath($uploadId);
        if (!File::exists($uploadDir)) {
            throw new \Exception("Upload ID not found: {$uploadId}");
        }

        $chunkPath = $uploadDir . "/chunk_{$chunkIndex}.part";
        File::put($chunkPath, $content);

        // update meta
        $meta = json_decode(File::get($uploadDir . '/meta.json'), true);
        $meta['chunks'][$chunkIndex] = $chunkPath;
        File::put($uploadDir . '/meta.json', json_encode($meta));
    }

    /**
     * Validate checksum of all chunks combined
     */
    public function validateChecksum(string $uploadId, string $expectedChecksum): bool
    {
        $uploadDir = $this->getUploadPath($uploadId);
        $meta = json_decode(File::get($uploadDir . '/meta.json'), true);

        $combined = '';
        ksort($meta['chunks']); // ensure order
        foreach ($meta['chunks'] as $path) {
            $combined .= File::get($path);
        }

        $actualChecksum = hash('sha256', $combined);

        return $actualChecksum === $expectedChecksum;
    }

    /**
     * Complete upload: merge chunks into final file
     */
    public function completeUpload(string $uploadId): string
    {
        $uploadDir = $this->getUploadPath($uploadId);
        $meta = json_decode(File::get($uploadDir . '/meta.json'), true);

        $finalPath = $uploadDir . '/' . $meta['filename'];
        $fp = fopen($finalPath, 'wb');

        ksort($meta['chunks']);
        foreach ($meta['chunks'] as $chunkPath) {
            fwrite($fp, File::get($chunkPath));
        }
        fclose($fp);

        return $finalPath;
    }

    protected function getUploadPath(string $uploadId): string
    {
        return $this->uploadsPath . '/' . $uploadId;
    }
}
