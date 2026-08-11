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
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug', 100)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('schema'); // Dynamic Form single source of truth
            $table->enum('status', ['draft', 'published', 'archived'])->default('published');
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();

            // Performant MySQL Indexes
            $table->index(['status', 'created_at'], 'idx_status_created');
            $table->index('slug', 'idx_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
