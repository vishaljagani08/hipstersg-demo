<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class GenerateMockData extends Command
{
    protected $signature = 'mock:generate {rows=10000} {images=300}';
    protected $description = 'Generate mock CSV and images';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $rows = (int)$this->argument('rows');
        $images = (int)$this->argument('images');

        $path = 'app/mocks/products_' . $rows . '.csv';
        Storage::disk('public')->makeDirectory("app/mocks/images");

        $csvPath = Storage::disk('public')->path($path);
        $this->info("Writing $rows rows to $csvPath");
        $directory = dirname($csvPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true); // Create directory with read/write/execute permissions for owner, read/execute for others
        }
        $f = fopen($csvPath, 'w');
        fputcsv($f, ['sku', 'name', 'description', 'price', 'upload_id']); // upload_id empty for now

        for ($i = 1; $i <= $rows; $i++) {
            $sku = 'SKU-' . str_pad($i, 6, '0', STR_PAD_LEFT);
            fputcsv($f, [$sku, "Product $i", "Desc for $i", rand(100, 10000) / 100, '']);
        }
        fclose($f);

        // copy a sample image multiple times
        $sample = public_path('sample.jpg'); // put a sample.jpg in public/
        if (!file_exists($sample)) {
            // create a simple sample image
            $img = imagecreatetruecolor(800, 600);
            $bg = imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255));
            imagefill($img, 0, 0, $bg);
            imagejpeg($img, $sample, 85);
            imagedestroy($img);
        }

        $dir = Storage::disk('public')->path('app/mocks/images');
        @mkdir($dir, 0755, true);
        for ($i = 1; $i <= $images; $i++) {
            copy($sample, $dir . '/img_' . str_pad($i, 4, '0', STR_PAD_LEFT) . '.jpg');
        }
        $this->info("Created $images images in $dir");
        $this->info("Done.");
    }
}
