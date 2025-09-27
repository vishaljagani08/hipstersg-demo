<?php

namespace App\Http\Controllers;

use App\Imports\ProductsImport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Jobs\ProcessProductsImport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    // Blade UI
    public function ui()
    {
        return view('imports.ui');
    }

    // Synchronous import handler invoked by the form
    // NOTE: For very large CSVs you may want to dispatch a job instead (see previous examples)
    public function handle(Request $r)
    {
        $r->validate(['file' => 'required|file|mimes:csv,txt']);

        $path = $r->file('file')->store('imports');

        $import = new ProductsImport();
        // Use the stored path for import
        Excel::import($import, Storage::path($path));

        // get stats from import class (it must expose getStats())
        $stats = $import->getStats();

        return response()->json([
            'total' => $stats['total'] ?? 0,
            'imported' => $stats['imported'] ?? 0,
            'updated' => $stats['updated'] ?? 0,
            'invalid' => $stats['invalid'] ?? 0,
            'duplicates' => $stats['duplicates'] ?? 0,
            'invalid_file' => $import->getInvalidFile() ?? null
        ]);
    }
}
