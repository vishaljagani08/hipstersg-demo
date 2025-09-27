<?php

use App\Http\Controllers\ImportController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});




// Import UI + handler
Route::get('/imports', [ImportController::class, 'ui'])->name('imports.ui');
Route::post('/imports/handle', [ImportController::class, 'handle'])->name('imports.handle');

// Upload UI + chunk endpoints
Route::get('/uploads', [UploadController::class, 'ui'])->name('uploads.ui');

// API endpoints used by Resumable.js + UI
Route::post('/uploads/initiate', [UploadController::class, 'initiate'])->name('uploads.initiate');
Route::post('/uploads/chunk', [UploadController::class, 'uploadChunk'])->name('uploads.chunk');
Route::get('/uploads/{upload}/status', [UploadController::class, 'status'])->name('uploads.status');
Route::post('/uploads/{upload}/complete', [UploadController::class, 'complete'])->name('uploads.complete');
