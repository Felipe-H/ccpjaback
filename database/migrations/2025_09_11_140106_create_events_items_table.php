<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('event_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity_required')->default(0);
            $table->unsignedInteger('quantity_used')->default(0);
            $table->boolean('is_from_stock')->default(true);
            $table->boolean('is_ready')->default(false);
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'inventory_item_id']);
            $table->index(['needed_by', 'is_ready']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('event_items');
    }
};
