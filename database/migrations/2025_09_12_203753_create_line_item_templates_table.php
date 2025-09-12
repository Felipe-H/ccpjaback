<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('line_item_templates', function (Blueprint $table) {
            $table->foreignId('line_id')->constrained('spiritual_lines')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->enum('purpose', ['trabalho','defumacao','oferta','cuidado','indumentaria','outro'])->default('trabalho');
            $table->decimal('suggested_qty', 12, 3)->default(1);
            $table->string('unit')->nullable();
            $table->boolean('required')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->primary(['line_id','item_id','purpose']);
            $table->index(['item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_item_templates');
    }
};
