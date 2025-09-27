<?php

it('assigns upload id and validates checksum', function () {
    $tmpPath = sys_get_temp_dir() . '/uploads_test';
    $service = new \App\Services\UploadService($tmpPath);

    $content = 'hello world';
    $checksum = hash('sha256', $content);

    $uploadId = $service->initiateUpload('file.txt', strlen($content), $checksum);
    $service->storeChunk($uploadId, 0, $content);

    expect($service->validateChecksum($uploadId, $checksum))->toBeTrue();
});

