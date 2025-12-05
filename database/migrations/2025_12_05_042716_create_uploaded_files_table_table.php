<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('uploaded_files', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type'); // image, document, video, etc.
            $table->string('file_extension', 10);
            $table->bigInteger('file_size');
            $table->string('mime_type');
            $table->string('disk')->default('public');
            $table->json('metadata')->nullable(); // for image dimensions, etc.
            $table->string('url');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('file_type');
            $table->index('mime_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploaded_files');
    }
};
