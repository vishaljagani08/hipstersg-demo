<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Upload;
use App\Models\Image;
use App\Models\Product;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels, Dispatchable};
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;


class GenerateImageVariants implements ShouldQueue
{
    use Queueable, InteractsWithQueue, Queueable, SerializesModels;

    protected $uploadId;
    public function __construct(int $uploadId)
    {
        $this->uploadId = $uploadId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $upload = Upload::find($this->uploadId);
        if (!$upload || $upload->status !== 'completed') return;

        $disk = Storage::disk('public');
        $origPath = Storage::path("app/public/{$upload->storage_path}");
        if (! file_exists($origPath)) return;

        // configure driver
        $manager = new ImageManager(new Driver());

        $img = $manager->make($origPath);
        $w = $img->width();
        $h = $img->height();
        $maxSide = max($w, $h);

        foreach ([256, 512, 1024] as $size) {
            $thumb = clone $img;

            if ($maxSide <= $size) {
                // original is smaller than target: copy original (no upscaling)
                $encoded = (string)$thumb->encode('jpg', 85);
            } else {
                if ($w >= $h) {
                    $thumb->resize($size, null, fn($c) => $c->aspectRatio()->upsize());
                } else {
                    $thumb->resize(null, $size, fn($c) => $c->aspectRatio()->upsize());
                }
                $encoded = (string)$thumb->encode('jpg', 85);
            }

            $outPath = "uploads/{$upload->id}/v{$size}.jpg";
            $disk->put($outPath, $encoded);

            Image::create([
                'upload_id' => $upload->id,
                'variant' => "v{$size}",
                'path' => $outPath,
                'width' => $thumb->width(),
                'height' => $thumb->height()
            ]);
        }

        // update original image width/height
        $origImage = Image::where('upload_id', $upload->id)->where('variant', 'original')->first();
        if ($origImage) $origImage->update(['width' => $w, 'height' => $h]);
    }
}
