<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('content_item_line', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained('content_items')->cascadeOnDelete();
            $table->foreignId('line_id')->constrained('spiritual_lines')->cascadeOnDelete();
            $table->string('role', 32)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->smallInteger('weight')->default(0);
            $table->timestamps();

            $table->unique(['content_item_id', 'line_id']);
            $table->index(['line_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_item_line');
    }
};
