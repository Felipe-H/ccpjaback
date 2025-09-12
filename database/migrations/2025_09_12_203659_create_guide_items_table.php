<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('guide_items', function (Blueprint $table) {
            $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->enum('purpose', ['trabalho','defumacao','oferta','cuidado','indumentaria','outro'])->default('trabalho');
            $table->decimal('default_qty', 12, 3)->default(1);
            $table->string('unit')->nullable();     // se divergir da unidade base do item
            $table->boolean('required')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->primary(['guide_id','item_id','purpose']);
            $table->index(['item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_items');
    }
};
