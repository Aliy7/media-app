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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('uuid', 36)->unique();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('processing_step', 100)->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('error_message')->nullable();
            $table->json('outputs')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
