<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\File;

class CsvImportService
{
    protected string $storagePath;

    public function __construct(string $storagePath = null)
    {
        // allow passing a temp path for unit tests
        $this->storagePath = $storagePath ?? base_path('storage/app/imports');

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Import CSV file, upsert users, and return summary
     *
     * @param string $filePath Full path to CSV
     * @return array
     */
    public function import(string $filePath): array
    {
        $summary = [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'invalid' => 0,
            'duplicates' => 0,
        ];

        if (!File::exists($filePath)) {
            throw new \Exception("CSV file not found: $filePath");
        }

        $lines = array_filter(array_map('trim', explode("\n", file_get_contents($filePath))));
        if (empty($lines)) {
            return $summary;
        }

        $headers = str_getcsv(array_shift($lines));

        // Validate that required columns exist
        $required = ['name','email'];
        if (count(array_intersect($required, $headers)) < count($required)) {
            throw new \Exception('Missing required columns: ' . implode(',', $required));
        }

        $emailsSeen = [];

        // iterate rows
        foreach ($lines as $line) {
            $summary['total']++;
            $row = str_getcsv($line);
            if (count($row) !== count($headers)) {
                $summary['invalid']++;
                continue;
            }
            $data = array_combine($headers, $row);

            // check missing required fields
            if (empty($data['name']) || empty($data['email'])) {
                $summary['invalid']++;
                continue;
            }

            // If this email has already been processed, mark as duplicate and skip upsert
            if (in_array($data['email'], $emailsSeen)) {
                $summary['duplicates']++;
                continue;
            }

            // Upsert user record with a default password to satisfy NOT NULL constraint
            $user = \App\Models\User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => bcrypt('secret')
                ]
            );
            $summary['imported']++;

            // Mark email as processed
            $emailsSeen[] = $data['email'];
        }

        return $summary;
    }

    /**
     * Import CSV data from a string and return a summary.
     *
     * @param string $csvContent
     * @return array
     */
    public function importFromCsvContent(string $csvContent): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmpFile, $csvContent);
        $summary = $this->import($tmpFile);
        unlink($tmpFile);
        return $summary;
    }
}
