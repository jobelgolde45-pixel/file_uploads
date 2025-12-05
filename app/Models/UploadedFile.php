<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class UploadedFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_name',
        'file_name',
        'file_path',
        'file_type',
        'file_extension',
        'file_size',
        'mime_type',
        'disk',
        'metadata',
        'url'
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
    ];

    /**
     * Get full URL for the file
     */
    public function getFullUrlAttribute(): string
    {
        // FIXED: Use URL::asset() instead of Storage::url()
        return URL::asset('storage/' . $this->file_path);
    }

    /**
     * Check if file is an image
     */
    public function isImage(): bool
    {
        return $this->file_type === 'image';
    }

    /**
     * Check if file is a document
     */
    public function isDocument(): bool
    {
        return $this->file_type === 'document';
    }

    /**
     * Delete file from storage
     */
    public function deleteFromStorage(): bool
    {
        try {
            Storage::disk($this->disk)->delete($this->file_path);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete file: ' . $e->getMessage());
            return false;
        }
    }
}