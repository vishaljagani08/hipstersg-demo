<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Imports\ProductsImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Bus\Dispatcher;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessProductsImport implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $filePath;
    public $resultKey;

    public function __construct(string $filePath, string $resultKey) {
        $this->filePath = $filePath;
        $this->resultKey = $resultKey;
    }

    public function handle() {
        $import = new ProductsImport();
        Excel::import($import, $this->filePath);
        Cache::put($this->resultKey, [
            'summary' => $import->getStats(),
            'invalid_file' => $import->getInvalidFile()
        ], now()->addHours(4));
    }
}
