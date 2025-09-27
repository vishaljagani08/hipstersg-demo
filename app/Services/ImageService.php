<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageService
{
    protected array $sizes = [256, 512, 1024];

    /**
     * Generate resized image variants (in-memory).
     *
     * @param string $filePath Absolute path to original image
     * @return array<int, \Intervention\Image\Image>
     */
    public function generateVariants(string $filePath): array
    {
        $variants = [];

        foreach ($this->sizes as $size) {
            // $img = Image::make($filePath)
            $imageManager = new ImageManager(new Driver());

            $img = $imageManager->read($filePath)
                ->resize($size, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

            $variants[$size] = $img;
        }

        return $variants;
    }
}
