<?php

use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;

it('generates in-memory image variants', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('test.jpg', 2000, 1500);
    $path = $file->store('uploads', 'public');

    $absolutePath = Storage::disk('public')->path($path);

    $service = new ImageService();
    $variants = $service->generateVariants($absolutePath);

    foreach ([256, 512, 1024] as $size) {
        expect(array_key_exists($size, $variants))->toBeTrue();

        // $img = Image::make($variants[$size]);
        $img = $variants[$size];
        expect($img->width() === $size || $img->height() === $size)->toBeTrue();
    }
});

it('generates image variants preserving aspect ratio', function () {
    // Create a valid 1x1 PNG image
    $dummyImagePath = __DIR__ . '/dummy.png';
    $im = imagecreatetruecolor(1, 1);
    ob_start();
    imagepng($im);
    $imageData = ob_get_clean();
    file_put_contents($dummyImagePath, $imageData);
    imagedestroy($im);

    $service = new ImageService();
    $variants = $service->generateVariants($dummyImagePath);

    expect($variants)->toBeArray();
    expect($variants)->toHaveKey('256');
    expect($variants)->toHaveKey('512');
    expect($variants)->toHaveKey('1024');

    // Clean up the dummy image
    unlink($dummyImagePath);
});
