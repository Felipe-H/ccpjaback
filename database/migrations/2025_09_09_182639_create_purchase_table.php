<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_batches', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable();
            $table->unsignedBigInteger('event_id')->nullable(); // sem FK por enquanto
            $table->string('payment_status')->default('paid');  // paid | pending
            $table->text('notes')->nullable();
            $table->decimal('total_estimated', 10, 2)->nullable();
            $table->decimal('total_real', 10, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // FKs opcionais
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // Itens do lote
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id');
            $table->unsignedBigInteger('item_id');
            $table->decimal('qty', 12, 3)->default(0); // permite fracionado se quiser
            $table->decimal('unit_price_estimated', 10, 2)->nullable();
            $table->decimal('unit_price_real', 10, 2)->default(0);
            $table->decimal('subtotal_estimated', 12, 2)->nullable();
            $table->decimal('subtotal_real', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('purchase_id')->references('id')->on('purchase_batches')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('inventory_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchase_batches');
    }
};
