<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('color', 16)->nullable();
            $table->string('description', 400)->nullable();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('content_item_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained('content_items')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['content_item_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_item_tag');
        Schema::dropIfExists('tags');
    }
};
