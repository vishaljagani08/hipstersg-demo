<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use App\Models\Product;
use App\Models\Upload;
use App\Services\ImageAttachService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ProductsImport implements OnEachRow, WithHeadingRow, WithChunkReading
{
    public $stats = ['total' => 0, 'imported' => 0, 'updated' => 0, 'invalid' => 0, 'duplicates' => 0];
    protected $seen = [];
    protected $invalidFile;
    protected $invalidHandle;

    public function __construct($invalidFile = null)
    {
        $this->invalidFile = $invalidFile ?? Storage::disk('public')->path('app/imports/invalid_' . time() . '.csv');
        @mkdir(dirname($this->invalidFile), 0755, true);
        $this->invalidHandle = fopen($this->invalidFile, 'w+');
    }

    public function onRow(Row $row)
    {
        $this->stats['total']++;
        $data = $row->toArray();

        // required columns: sku, name
        if (empty($data['sku']) || empty($data['name'])) {
            $this->stats['invalid']++;
            fputcsv($this->invalidHandle, $data);
            return;
        }

        $sku = trim($data['sku']);
        if (isset($this->seen[$sku])) {
            $this->stats['duplicates']++;
            return;
        }
        $this->seen[$sku] = true;

        // upsert by sku (updateOrCreate to check recentlyCreated)
        $product = Product::updateOrCreate(
            ['sku' => $sku],
            [
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'price' => $data['price'] ?? null,
                'sku' => $sku,
            ]
        );

        if ($product->wasRecentlyCreated) $this->stats['imported']++;
        else $this->stats['updated']++;

        // If CSV contains upload_id to link image
        if (!empty($data['upload_id'])) {
            $upload = Upload::find($data['upload_id']);
            if ($upload && $upload->status === 'completed') {
                try {
                    ImageAttachService::attachUploadAsPrimary($product, $upload->id);
                } catch (\Exception $e) {
                    Log::warning("Attach failed for product {$product->id} upload {$upload->id}: " . $e->getMessage());
                }
            }
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function __destruct()
    {
        @fclose($this->invalidHandle);
    }
    public function getStats()
    {
        return $this->stats;
    }
    public function getInvalidFile()
    {

        return Storage::url('app/imports/'.basename($this->invalidFile));
    }
}
