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
        Schema::create('pms_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pms_document_id')->constrained()->cascadeOnDelete();
            $table->string('prompt_version');
            $table->string('prompt_hash');
            $table->string('model');
            $table->string('status')->default('queued');
            $table->unsignedInteger('progress')->default(0);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->json('chunk_results')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pms_analyses');
    }
};
