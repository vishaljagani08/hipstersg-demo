<?php

use App\Services\CsvImportService;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('upserts users and returns correct summary', function () {
    Storage::fake('local');

    $csvContent = <<<CSV
name,email
Alice,alice@example.com
Bob,bob@example.com
Alice,alice@example.com
,missing_email@example.com
CSV;

    $filePath = 'imports/test.csv';
    Storage::disk('local')->put($filePath, $csvContent);

    $service = new CsvImportService();
    $summary = $service->import(Storage::disk('local')->path($filePath));

    expect($summary['total'])->toBe(4)
        ->and($summary['imported'])->toBe(2)
        ->and($summary['duplicates'])->toBe(1)
        ->and($summary['invalid'])->toBe(1);

    expect(User::where('email', 'alice@example.com')->exists())->toBeTrue()
        ->and(User::where('email', 'bob@example.com')->exists())->toBeTrue();
});

it('should upsert CSV data and produce result summary', function () {
    // Updated CSV headers and rows to include required columns
    $csvData = <<<CSV
name,email
John Doe,john@example.com
Jane Doe,jane@example.com
John Doe,john@example.com
Invalid Row
CSV;

    $service = new \App\Services\CsvImportService();
    $result = $service->importFromCsvContent($csvData);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('total');
    expect($result)->toHaveKey('imported');
    expect($result)->toHaveKey('updated');
    expect($result)->toHaveKey('invalid');
    expect($result)->toHaveKey('duplicates');
});
