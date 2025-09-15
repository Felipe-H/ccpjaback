<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trail_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trail_id')->constrained('trails')->cascadeOnDelete();
            $table->foreignId('content_item_id')->constrained('content_items')->cascadeOnDelete();
            $table->unsignedInteger('order')->default(1);
            $table->boolean('required')->default(false);
            $table->string('note', 400)->nullable();
            $table->timestamps();

            $table->unique(['trail_id', 'content_item_id']);
            $table->index(['trail_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trail_items');
    }
};
