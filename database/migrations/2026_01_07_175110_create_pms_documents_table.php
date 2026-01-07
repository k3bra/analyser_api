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
        Schema::create('pms_documents', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('storage_disk')->default('local');
            $table->string('storage_path');
            $table->string('extracted_text_path')->nullable();
            $table->string('status')->default('uploaded');
            $table->timestamp('text_extracted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pms_documents');
    }
};
