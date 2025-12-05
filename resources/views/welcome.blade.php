<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }} - File Manager</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Alpine.js for interactivity -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-hover: #2563eb;
            --secondary-color: #6b7280;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --background-color: #f9fafb;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }
        
        .file-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 3rem;
            text-align: center;
            transition: border-color 0.3s ease;
        }
        
        .file-upload-area:hover, .file-upload-area.dragover {
            border-color: var(--primary-color);
            background-color: #eff6ff;
        }
        
        .file-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            margin-right: 1rem;
        }
        
        .file-icon.image {
            background-color: #dbeafe;
            color: #1d4ed8;
        }
        
        .file-icon.document {
            background-color: #f3e8ff;
            color: #7c3aed;
        }
        
        .progress-bar {
            height: 6px;
            background-color: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background-color: var(--primary-color);
            transition: width 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .file-upload-area {
                padding: 2rem 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .action-buttons button {
                width: 100%;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-instrument-sans">
    <div x-data="fileManager()" x-init="fetchFiles()" class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">File Manager</h1>
                        <p class="text-gray-600">Upload, manage, and resize your files</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500">
                            Max file size: 10MB
                        </span>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Main Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column: File List -->
                <div class="lg:col-span-2">
                    <!-- Search and Filters -->
                    <div class="card p-6 mb-6">
                        <div class="flex flex-col sm:flex-row gap-4">
                            <div class="flex-1">
                                <input 
                                    type="text" 
                                    placeholder="Search files..." 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                            </div>
                            <div class="flex gap-2">
                                <select class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Types</option>
                                    <option value="image">Images</option>
                                    <option value="document">Documents</option>
                                </select>
                                <button class="btn-secondary whitespace-nowrap">Filter</button>
                            </div>
                        </div>
                    </div>

                    <!-- Files List -->
                    <div class="space-y-4">
                        <template x-for="file in files" :key="file.id">
                            <div class="card p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div :class="`file-icon ${file.file_type}`">
                                            <template x-if="file.file_type === 'image'">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </template>
                                            <template x-if="file.file_type === 'document'">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </template>
                                        </div>
                                        <div>
                                            <h3 class="font-medium text-gray-900" x-text="file.original_name"></h3>
                                            <p class="text-sm text-gray-500">
                                                <span x-text="file.file_extension.toUpperCase()"></span> • 
                                                <span x-text="formatFileSize(file.file_size)"></span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button @click="viewFile(file.id)" class="text-blue-600 hover:text-blue-800">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <button @click="editFile(file.id)" class="text-gray-600 hover:text-gray-800">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button @click="deleteFile(file.id)" class="text-red-600 hover:text-red-800">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-6 flex justify-center">
                        <nav class="flex items-center space-x-2">
                            <button class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Previous
                            </button>
                            <button class="px-3 py-2 border border-blue-500 bg-blue-50 rounded-md text-sm font-medium text-blue-600">
                                1
                            </button>
                            <button class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Next
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Right Column: Forms -->
                <div class="space-y-6">
                    <!-- Upload Form (POST) -->
                    <div class="card p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Upload File</h2>
                        <form @submit.prevent="uploadFile" id="uploadForm" enctype="multipart/form-data">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Select File
                                    </label>
                                    <div class="file-upload-area" 
                                         @dragover.prevent="dragOver = true" 
                                         @dragleave="dragOver = false"
                                         @drop.prevent="handleDrop">
                                        <div class="space-y-3">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                            </svg>
                                            <div>
                                                <p class="text-sm text-gray-600">
                                                    <span class="font-medium text-blue-600 cursor-pointer" onclick="document.getElementById('fileInput').click()">
                                                        Click to upload
                                                    </span>
                                                    or drag and drop
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    Images (JPG, PNG, GIF, WEBP, SVG) • Documents (PDF, DOC, XLS, etc.)
                                                </p>
                                            </div>
                                            <input type="file" id="fileInput" name="file" @change="handleFileSelect" class="hidden">
                                        </div>
                                    </div>
                                    <div x-show="selectedFile" class="mt-4">
                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <span x-text="selectedFile.name" class="text-sm"></span>
                                            </div>
                                            <button type="button" @click="selectedFile = null" class="text-gray-400 hover:text-gray-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="resize" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Resize on upload</span>
                                    </label>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Width (px)</label>
                                            <input type="number" name="width" min="1" max="2000" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Height (px)</label>
                                            <input type="number" name="height" min="1" max="2000" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Folder (optional)</label>
                                        <input type="text" name="folder" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>

                                <button type="submit" class="btn-primary w-full">
                                    Upload File
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Update Form (PUT) -->
                    <div class="card p-6" x-show="currentFile">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Update File</h2>
                        <form @submit.prevent="updateFile" id="updateForm" enctype="multipart/form-data">
                            <input type="hidden" name="id" x-model="currentFile.id">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Replace File
                                    </label>
                                    <input type="file" name="file" @change="handleUpdateFileSelect" 
                                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                </div>
                                
                                <div class="space-y-4">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="resize" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Resize on update</span>
                                    </label>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Width (px)</label>
                                            <input type="number" name="width" min="1" max="2000" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Height (px)</label>
                                            <input type="number" name="height" min="1" max="2000" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                </div>

                                <div class="action-buttons flex gap-2">
                                    <button type="submit" class="btn-primary flex-1">
                                        Update File
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Resize Form (POST resize) -->
                    <div class="card p-6" x-show="currentFile && currentFile.file_type === 'image'">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Resize Image</h2>
                        <form @submit.prevent="resizeImage" id="resizeForm">
                            <input type="hidden" name="id" x-model="currentFile.id">
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Width (px)</label>
                                        <input type="number" name="width" min="1" max="2000" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Height (px)</label>
                                        <input type="number" name="height" min="1" max="2000" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="maintain_aspect_ratio" checked 
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">Maintain aspect ratio</span>
                                </label>

                                <button type="submit" class="btn-primary w-full">
                                    Resize Image
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>

        <!-- File Details Modal -->
        <div x-show="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div class="card max-w-2xl w-full">
                <div class="p-6">
                    <div class="flex justify-between items-start mb-6">
                        <h2 class="text-xl font-semibold text-gray-900">File Details</h2>
                        <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <div x-show="modalFile" class="space-y-4">
                        <div class="flex items-center space-x-4">
                            <div :class="`file-icon ${modalFile.file_type}`">
                                <template x-if="modalFile.file_type === 'image'">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </template>
                                <template x-if="modalFile.file_type === 'document'">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </template>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900" x-text="modalFile.original_name"></h3>
                                <p class="text-sm text-gray-500" x-text="modalFile.file_extension.toUpperCase()"></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <p class="text-sm text-gray-500">Size</p>
                                <p class="font-medium" x-text="formatFileSize(modalFile.file_size)"></p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm text-gray-500">Type</p>
                                <p class="font-medium" x-text="modalFile.file_type.charAt(0).toUpperCase() + modalFile.file_type.slice(1)"></p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm text-gray-500">Uploaded</p>
                                <p class="font-medium" x-text="new Date(modalFile.created_at).toLocaleDateString()"></p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm text-gray-500">MIME Type</p>
                                <p class="font-medium" x-text="modalFile.mime_type"></p>
                            </div>
                        </div>
                        
                        <template x-if="modalFile.metadata && modalFile.metadata.width">
                            <div class="space-y-2">
                                <p class="text-sm text-gray-500">Dimensions</p>
                                <p class="font-medium" x-text="`${modalFile.metadata.width} × ${modalFile.metadata.height} px`"></p>
                            </div>
                        </template>
                        
                        <div class="pt-4 border-t">
                            <a :href="modalFile.url" target="_blank" class="btn-primary inline-flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Download File
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function fileManager() {
            return {
                files: [],
                selectedFile: null,
                currentFile: null,
                modalFile: null,
                showModal: false,
                dragOver: false,
                
                async fetchFiles() {
                    try {
                        const response = await fetch('/api/uploads');
                        const data = await response.json();
                        this.files = data.data;
                    } catch (error) {
                        console.error('Error fetching files:', error);
                    }
                },
                
                async uploadFile() {
                    const form = document.getElementById('uploadForm');
                    const formData = new FormData(form);
                    
                    if (this.selectedFile) {
                        formData.append('file', this.selectedFile);
                    }
                    
                    try {
                        const response = await fetch('/api/uploads', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert('File uploaded successfully!');
                            this.fetchFiles();
                            form.reset();
                            this.selectedFile = null;
                        } else {
                            alert('Upload failed: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Upload error:', error);
                        alert('Upload failed!');
                    }
                },
                
                async updateFile() {
                    const form = document.getElementById('updateForm');
                    const formData = new FormData(form);
                    
                    try {
                        const response = await fetch(`/api/uploads/${this.currentFile.id}`, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-HTTP-Method-Override': 'PUT'
                            }
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert('File updated successfully!');
                            this.fetchFiles();
                            form.reset();
                        } else {
                            alert('Update failed: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Update error:', error);
                        alert('Update failed!');
                    }
                },
                
                async deleteFile(id) {
                    if (!confirm('Are you sure you want to delete this file?')) return;
                    
                    try {
                        const response = await fetch(`/api/uploads/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            }
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert('File deleted successfully!');
                            this.fetchFiles();
                        } else {
                            alert('Delete failed: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Delete error:', error);
                        alert('Delete failed!');
                    }
                },
                
                async resizeImage() {
                    const form = document.getElementById('resizeForm');
                    const formData = new FormData(form);
                    
                    try {
                        const response = await fetch(`/api/uploads/${this.currentFile.id}/resize`, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            }
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert('Image resized successfully!');
                            this.fetchFiles();
                        } else {
                            alert('Resize failed: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Resize error:', error);
                        alert('Resize failed!');
                    }
                },
                
                async viewFile(id) {
                    try {
                        const response = await fetch(`/api/uploads/${id}`);
                        const result = await response.json();
                        
                        if (result.success) {
                            this.modalFile = result.data;
                            this.showModal = true;
                        }
                    } catch (error) {
                        console.error('Error fetching file:', error);
                    }
                },
                
                editFile(id) {
                    this.currentFile = this.files.find(f => f.id === id);
                    // Scroll to update form
                    document.querySelector('#updateForm').scrollIntoView({ behavior: 'smooth' });
                },
                
                handleFileSelect(event) {
                    this.selectedFile = event.target.files[0];
                },
                
                handleUpdateFileSelect(event) {
                    // Handle update file selection if needed
                },
                
                handleDrop(event) {
                    this.dragOver = false;
                    const files = event.dataTransfer.files;
                    if (files.length > 0) {
                        this.selectedFile = files[0];
                    }
                },
                
                formatFileSize(bytes) {
                    const units = ['B', 'KB', 'MB', 'GB'];
                    let index = 0;
                    
                    while (bytes >= 1024 && index < units.length - 1) {
                        bytes /= 1024;
                        index++;
                    }
                    
                    return Math.round(bytes * 100) / 100 + ' ' + units[index];
                }
            }
        }
    </script>
</body>
</html>