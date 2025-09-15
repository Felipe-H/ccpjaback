<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('content_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained('content_items')->cascadeOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('storage_path');
            $table->string('original_name')->nullable();
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('variant', 24)->default('original');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->index(['content_item_id', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_assets');
    }
};
