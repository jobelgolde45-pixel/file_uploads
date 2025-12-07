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

    /* -------------------------------------------------
     *  FORMAT-NEGOTIATION HELPERS (JSON ↔ XML)
     * -------------------------------------------------*/
    private function format($data, $status = 200)
    {
        $acceptHeader = strtolower(request()->header('Accept', ''));
        $formatParam  = strtolower(request()->get('format', ''));

        if ($formatParam === 'xml' || str_contains($acceptHeader, 'application/xml') || str_contains($acceptHeader, 'text/xml')) {
            return $this->convertToXml($data, $status);
        }

        // default → JSON
        return response()->json($data, $status);
    }

    private function convertToXml($data, $status = 200)
    {
        try {
            if ($data instanceof \Illuminate\Database\Eloquent\Collection) {
                $data = $data->toArray();
            }
            if (is_array($data) && array_keys($data) === range(0, count($data) - 1)) {
                $data = ['item' => $data];
            }

            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><response></response>');
            $this->arrayToXml($data, $xml);

            return response($xml->asXML(), $status)->header('Content-Type', 'application/xml');
        } catch (\Exception $e) {
            Log::error('XML conversion failed: ' . $e->getMessage());
            return response()->json($data, $status);
        }
    }

    private function arrayToXml($data, &$xml)
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item';
            }
            $key = $this->sanitizeXmlKey($key);

            if (is_array($value)) {
                $sub = $xml->addChild($key);
                $this->arrayToXml($value, $sub);
            } else {
                $xml->addChild($key, htmlspecialchars((string) $value, ENT_XML1, 'UTF-8'));
            }
        }
    }

    private function sanitizeXmlKey($key)
    {
        $key = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        if (empty($key) || is_numeric(substr($key, 0, 1))) {
            $key = 'item_' . $key;
        }
        return $key;
    }

    /* -------------------------------------------------
     *  RESOURCE ENDPOINTS
     * -------------------------------------------------*/
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

        $payload = [
            'success'    => true,
            'data'       => $files->items(),
            'pagination' => [
                'current_page' => $files->currentPage(),
                'total_pages'  => $files->lastPage(),
                'total_items'  => $files->total(),
                'per_page'     => $files->perPage(),
            ],
        ];

        return $this->format($payload);
    }

    public function show($id)
    {
        $file = UploadedFile::find($id);

        if (!$file) {
            return $this->format(['success' => false, 'message' => 'File not found'], 404);
        }

        return $this->format(['success' => true, 'data' => $file]);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'file'   => 'required|file|max:' . $this->maxFileSize,
                'resize' => 'nullable|boolean',
                'width'  => 'nullable|integer|min:1|max:2000',
                'height' => 'nullable|integer|min:1|max:2000',
                'folder' => 'nullable|string|max:50',
            ]);

            $uploadedFile = $request->file('file');
            $folder       = $request->get('folder', 'uploads');
            $extension    = strtolower($uploadedFile->getClientOriginalExtension());
            $fileType     = $this->determineFileType($extension);

            if (!$fileType) {
                return $this->format(['success' => false, 'message' => 'File type not supported'], 400);
            }

            $fileName = $this->generateFileName($uploadedFile, $extension);
            $filePath = $folder . '/' . date('Y/m') . '/' . $fileName;

            if ($fileType === 'image' && $request->has('resize') && $request->resize) {
                $this->handleImageUpload($uploadedFile, $filePath, $request);
            } else {
                Storage::disk('public')->putFileAs(dirname($filePath), $uploadedFile, basename($filePath));
            }

            $metadata = $this->getFileMetadata($uploadedFile, $fileType);
            $fileUrl  = URL::asset('storage/' . $filePath);

            $fileRecord = UploadedFile::create([
                'original_name'   => $uploadedFile->getClientOriginalName(),
                'file_name'       => $fileName,
                'file_path'       => $filePath,
                'file_type'       => $fileType,
                'file_extension'  => $extension,
                'file_size'       => $uploadedFile->getSize(),
                'mime_type'       => $uploadedFile->getMimeType(),
                'disk'            => 'public',
                'metadata'        => $metadata,
                'url'             => $fileUrl,
            ]);

            $payload = [
                'success' => true,
                'message' => 'File uploaded successfully',
                'data'    => [
                    'id'          => $fileRecord->id,
                    'url'         => $fileRecord->url,
                    'full_url'    => $fileRecord->full_url,
                    'file_type'   => $fileType,
                    'size'        => $this->formatFileSize($fileRecord->file_size),
                    'created_at'  => $fileRecord->created_at->toISOString(),
                ],
            ];

            return $this->format($payload, 201);
        } catch (\Exception $e) {
            Log::error('File upload failed: ' . $e->getMessage());
            return $this->format(['success' => false, 'message' => 'File upload failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $fileRecord = UploadedFile::find($id);
            if (!$fileRecord) {
                return $this->format(['success' => false, 'message' => 'File not found'], 404);
            }

            $request->validate([
                'file'   => 'required|file|max:' . $this->maxFileSize,
                'resize' => 'nullable|boolean',
                'width'  => 'nullable|integer|min:1|max:2000',
                'height' => 'nullable|integer|min:1|max:2000',
            ]);

            $uploadedFile = $request->file('file');
            $extension      = strtolower($uploadedFile->getClientOriginalExtension());

            $fileRecord->deleteFromStorage();

            $fileName = $this->generateFileName($uploadedFile, $extension);
            $filePath = dirname($fileRecord->file_path) . '/' . $fileName;

            if ($fileRecord->file_type === 'image' && $request->has('resize') && $request->resize) {
                $this->handleImageUpload($uploadedFile, $filePath, $request);
            } else {
                Storage::disk('public')->putFileAs(dirname($filePath), $uploadedFile, basename($filePath));
            }

            $metadata = $this->getFileMetadata($uploadedFile, $fileRecord->file_type);
            $fileUrl  = URL::asset('storage/' . $filePath);

            $fileRecord->update([
                'original_name'  => $uploadedFile->getClientOriginalName(),
                'file_name'      => $fileName,
                'file_path'      => $filePath,
                'file_extension' => $extension,
                'file_size'      => $uploadedFile->getSize(),
                'mime_type'      => $uploadedFile->getMimeType(),
                'metadata'       => $metadata,
                'url'            => $fileUrl,
            ]);

            $payload = [
                'success' => true,
                'message' => 'File updated successfully',
                'data'    => [
                    'id'         => $fileRecord->id,
                    'url'        => $fileRecord->url,
                    'full_url'   => $fileRecord->full_url,
                    'file_type'  => $fileRecord->file_type,
                    'size'       => $this->formatFileSize($fileRecord->file_size),
                    'updated_at' => $fileRecord->updated_at->toISOString(),
                ],
            ];

            return $this->format($payload);
        } catch (\Exception $e) {
            Log::error('File update failed: ' . $e->getMessage());
            return $this->format(['success' => false, 'message' => 'File update failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $fileRecord = UploadedFile::find($id);
            if (!$fileRecord) {
                return $this->format(['success' => false, 'message' => 'File not found'], 404);
            }

            $fileRecord->deleteFromStorage();
            $fileRecord->delete();

            return $this->format(['success' => true, 'message' => 'File deleted successfully']);
        } catch (\Exception $e) {
            Log::error('File deletion failed: ' . $e->getMessage());
            return $this->format(['success' => false, 'message' => 'File deletion failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function resize(Request $request, $id)
    {
        try {
            $fileRecord = UploadedFile::find($id);
            if (!$fileRecord) {
                return $this->format(['success' => false, 'message' => 'File not found'], 404);
            }
            if (!$fileRecord->isImage()) {
                return $this->format(['success' => false, 'message' => 'File is not an image'], 400);
            }

            $request->validate([
                'width'                 => 'required|integer|min:1|max:2000',
                'height'                => 'required|integer|min:1|max:2000',
                'maintain_aspect_ratio' => 'nullable|boolean',
            ]);

            $filePath = Storage::disk('public')->path($fileRecord->file_path);
            $this->resizeImage($filePath, $request->width, $request->height, $request->get('maintain_aspect_ratio', true));

            $fileRecord->update([
                'file_size' => filesize($filePath),
                'metadata'  => array_merge($fileRecord->metadata ?? [], ['resized_at' => now()->toISOString()]),
            ]);

            $payload = [
                'success' => true,
                'message' => 'Image resized successfully',
                'data'    => [
                    'id'  => $fileRecord->id,
                    'url' => $fileRecord->url,
                ],
            ];

            return $this->format($payload);
        } catch (\Exception $e) {
            Log::error('Image resize failed: ' . $e->getMessage());
            return $this->format(['success' => false, 'message' => 'Image resize failed', 'error' => $e->getMessage()], 500);
        }
    }

    /* -------------------------------------------------
     *  HELPERS
     * -------------------------------------------------*/
    private function determineFileType($extension)
    {
        if (in_array($extension, $this->allowedImageTypes)) {
            return 'image';
        }
        if (in_array($extension, $this->allowedDocumentTypes)) {
            return 'document';
        }
        return null;
    }

    private function generateFileName($file, $extension)
    {
        $timestamp    = now()->timestamp;
        $randomString = Str::random(8);
        $cleanName    = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        return "{$cleanName}_{$timestamp}_{$randomString}.{$extension}";
    }

    private function handleImageUpload($file, $filePath, $request)
    {
        if ($request->has('width') && $request->has('height')) {
            $tempPath = $file->getRealPath();
            $this->resizeImage($tempPath, $request->width, $request->height, true);
            Storage::disk('public')->put($filePath, file_get_contents($tempPath));
        } else {
            Storage::disk('public')->putFileAs(dirname($filePath), $file, basename($filePath));
        }
    }

    private function resizeImage($imagePath, $width, $height, $maintainAspectRatio = true)
    {
        $info = getimagesize($imagePath);
        $mime = $info['mime'];

        switch ($mime) {
            case 'image/jpeg': $image = imagecreatefromjpeg($imagePath); break;
            case 'image/png':  $image = imagecreatefrompng($imagePath);  break;
            case 'image/gif':  $image = imagecreatefromgif($imagePath);  break;
            default: return;
        }

        $originalWidth  = imagesx($image);
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
            case 'image/jpeg': imagejpeg($resizedImage, $imagePath, 90); break;
            case 'image/png':  imagepng($resizedImage, $imagePath);      break;
            case 'image/gif':  imagegif($resizedImage, $imagePath);       break;
        }

        imagedestroy($image);
        imagedestroy($resizedImage);
    }

    private function getFileMetadata($file, $fileType)
    {
        $metadata = [
            'original_extension' => strtolower($file->getClientOriginalExtension()),
            'uploaded_at'        => now()->toISOString(),
        ];
        if ($fileType === 'image') {
            $imageSize = getimagesize($file->getRealPath());
            if ($imageSize !== false) {
                $metadata['width']  = $imageSize[0];
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