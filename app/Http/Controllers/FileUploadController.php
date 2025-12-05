<?php

namespace App\Http\Controllers;

use App\Models\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    private $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    private $allowedDocumentTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf'];
    private $maxFileSize = 10240;

    /**
     * GET: List all uploaded files
     */
    public function index(Request $request)
    {
        $query = UploadedFile::query();

        if ($request->has('file_type')) {
            $query->where('file_type', $request->get('file_type'));
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $files = $query->orderBy('created_at', 'desc')
                      ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $files->items(),
            'pagination' => [
                'current_page' => $files->currentPage(),
                'total_pages' => $files->lastPage(),
                'total_items' => $files->total(),
                'per_page' => $files->perPage()
            ]
        ]);
    }

    /**
     * GET: Get single file details
     */
    public function show($id)
    {
        $file = UploadedFile::find($id);

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $file
        ]);
    }

    /**
     * POST: Upload file
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:' . $this->maxFileSize,
                'resize' => 'nullable|boolean',
                'width' => 'nullable|integer|min:1|max:2000',
                'height' => 'nullable|integer|min:1|max:2000',
                'folder' => 'nullable|string|max:50'
            ]);

            $uploadedFile = $request->file('file');
            $folder = $request->get('folder', 'uploads');

            $extension = strtolower($uploadedFile->getClientOriginalExtension());
            $fileType = $this->determineFileType($extension);

            if (!$fileType) {
                return response()->json([
                    'success' => false,
                    'message' => 'File type not supported'
                ], 400);
            }

            $fileName = $this->generateFileName($uploadedFile, $extension);
            $filePath = $folder . '/' . date('Y/m') . '/' . $fileName;

            if ($fileType === 'image' && $request->has('resize') && $request->resize) {
                $this->handleImageUpload($uploadedFile, $filePath, $request);
            } else {
                Storage::disk('public')->putFileAs(
                    dirname($filePath),
                    $uploadedFile,
                    basename($filePath)
                );
            }

            $metadata = $this->getFileMetadata($uploadedFile, $fileType);

            $fileUrl = URL::asset('storage/' . $filePath);

            $fileRecord = UploadedFile::create([
                'original_name' => $uploadedFile->getClientOriginalName(),
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_type' => $fileType,
                'file_extension' => $extension,
                'file_size' => $uploadedFile->getSize(),
                'mime_type' => $uploadedFile->getMimeType(),
                'disk' => 'public',
                'metadata' => $metadata,
                'url' => $fileUrl
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'id' => $fileRecord->id,
                    'url' => $fileRecord->url,
                    'full_url' => $fileRecord->full_url,
                    'file_type' => $fileType,
                    'size' => $this->formatFileSize($fileRecord->file_size),
                    'created_at' => $fileRecord->created_at->toISOString()
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('File upload failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'File upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT: Update file (re-upload)
     */
    public function update(Request $request, $id)
    {
        try {
            $fileRecord = UploadedFile::find($id);

            if (!$fileRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $request->validate([
                'file' => 'required|file|max:' . $this->maxFileSize,
                'resize' => 'nullable|boolean',
                'width' => 'nullable|integer|min:1|max:2000',
                'height' => 'nullable|integer|min:1|max:2000'
            ]);

            $uploadedFile = $request->file('file');
            $extension = strtolower($uploadedFile->getClientOriginalExtension());

            $fileRecord->deleteFromStorage();

            $fileName = $this->generateFileName($uploadedFile, $extension);
            $filePath = dirname($fileRecord->file_path) . '/' . $fileName;

            if ($fileRecord->file_type === 'image' && $request->has('resize') && $request->resize) {
                $this->handleImageUpload($uploadedFile, $filePath, $request);
            } else {
                Storage::disk('public')->putFileAs(
                    dirname($filePath),
                    $uploadedFile,
                    basename($filePath)
                );
            }

            $metadata = $this->getFileMetadata($uploadedFile, $fileRecord->file_type);

            $fileUrl = URL::asset('storage/' . $filePath);

            $fileRecord->update([
                'original_name' => $uploadedFile->getClientOriginalName(),
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_extension' => $extension,
                'file_size' => $uploadedFile->getSize(),
                'mime_type' => $uploadedFile->getMimeType(),
                'metadata' => $metadata,
                'url' => $fileUrl
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File updated successfully',
                'data' => [
                    'id' => $fileRecord->id,
                    'url' => $fileRecord->url,
                    'full_url' => $fileRecord->full_url,
                    'file_type' => $fileRecord->file_type,
                    'size' => $this->formatFileSize($fileRecord->file_size),
                    'updated_at' => $fileRecord->updated_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('File update failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'File update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE: Delete file
     */
    public function destroy($id)
    {
        try {
            $fileRecord = UploadedFile::find($id);

            if (!$fileRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $fileRecord->deleteFromStorage();
            $fileRecord->delete();

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('File deletion failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'File deletion failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST: Resize existing image
     */
    public function resize(Request $request, $id)
    {
        try {
            $fileRecord = UploadedFile::find($id);

            if (!$fileRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            if (!$fileRecord->isImage()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File is not an image'
                ], 400);
            }

            $request->validate([
                'width' => 'required|integer|min:1|max:2000',
                'height' => 'required|integer|min:1|max:2000',
                'maintain_aspect_ratio' => 'nullable|boolean'
            ]);

            $filePath = Storage::disk('public')->path($fileRecord->file_path);
            $this->resizeImage($filePath, $request->width, $request->height, $request->get('maintain_aspect_ratio', true));

            $fileRecord->update([
                'file_size' => filesize($filePath),
                'metadata' => array_merge($fileRecord->metadata ?? [], [
                    'resized_at' => now()->toISOString()
                ])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image resized successfully',
                'data' => [
                    'id' => $fileRecord->id,
                    'url' => $fileRecord->url
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Image resize failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Image resize failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function determineFileType($extension)
    {
        if (in_array($extension, $this->allowedImageTypes)) {
            return 'image';
        } elseif (in_array($extension, $this->allowedDocumentTypes)) {
            return 'document';
        } else {
            return null;
        }
    }

    private function generateFileName($file, $extension)
    {
        $timestamp = now()->timestamp;
        $randomString = Str::random(8);
        $cleanName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        
        return "{$cleanName}_{$timestamp}_{$randomString}.{$extension}";
    }

    private function handleImageUpload($file, $filePath, $request)
    {
        if ($request->has('width') && $request->has('height')) {
            $tempPath = $file->getRealPath();
            $this->resizeImage($tempPath, $request->width, $request->height, true);
            Storage::disk('public')->put($filePath, file_get_contents($tempPath));
        } else {
            Storage::disk('public')->putFileAs(
                dirname($filePath),
                $file,
                basename($filePath)
            );
        }
    }

    private function resizeImage($imagePath, $width, $height, $maintainAspectRatio = true)
    {
        $info = getimagesize($imagePath);
        $mime = $info['mime'];
        
        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($imagePath);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($imagePath);
                break;
            default:
                return;
        }

        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        if ($maintainAspectRatio) {
            $ratio = $originalWidth / $originalHeight;
            if ($width / $height > $ratio) {
                $width = $height * $ratio;
            } else {
                $height = $width / $ratio;
            }
        }

        $resizedImage = imagecreatetruecolor($width, $height);
        
        if ($mime == 'image/png') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
        }
        
        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight);

        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($resizedImage, $imagePath, 90);
                break;
            case 'image/png':
                imagepng($resizedImage, $imagePath);
                break;
            case 'image/gif':
                imagegif($resizedImage, $imagePath);
                break;
        }

        imagedestroy($image);
        imagedestroy($resizedImage);
    }

    private function getFileMetadata($file, $fileType)
    {
        $metadata = [
            'original_extension' => strtolower($file->getClientOriginalExtension()),
            'uploaded_at' => now()->toISOString()
        ];

        if ($fileType === 'image') {
            $imageSize = getimagesize($file->getRealPath());
            if ($imageSize !== false) {
                $metadata['width'] = $imageSize[0];
                $metadata['height'] = $imageSize[1];
            }
        }

        return $metadata;
    }

    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        
        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }
        
        return round($bytes, 2) . ' ' . $units[$index];
    }
}