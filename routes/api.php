<?php

use App\Http\Controllers\FileUploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('files')->group(function () {
    // GET: List all files
    Route::get('/', [FileUploadController::class, 'index']);
    
    // GET: Get single file details
    Route::get('/{id}', [FileUploadController::class, 'show']);
    
    // POST: Upload new file
    Route::post('/upload', [FileUploadController::class, 'store']);
    
    // PUT: Update/replace existing file
    Route::post('/{id}', [FileUploadController::class, 'update']);
    
    // DELETE: Delete file
    Route::delete('/{id}', [FileUploadController::class, 'destroy']);
    
    // POST: Resize existing image
    Route::post('/{id}/resize', [FileUploadController::class, 'resize']);
});


Route::apiResource('uploads', FileUploadController::class)->only([
    'index', 'show', 'store', 'update', 'destroy'
]);
Route::post('uploads/{id}/resize', [FileUploadController::class, 'resize']);