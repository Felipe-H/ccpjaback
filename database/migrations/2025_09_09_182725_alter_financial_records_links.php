<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            // novos campos (todos opcionais para não quebrar nada)
            $table->decimal('amount_estimated', 10, 2)->nullable()->after('amount');
            $table->unsignedBigInteger('item_id')->nullable()->after('category');
            $table->unsignedBigInteger('event_id')->nullable()->after('item_id');      // sem FK (evento futuro)
            $table->unsignedBigInteger('purchase_id')->nullable()->after('event_id');  // FK para lote de compra
            $table->json('meta')->nullable()->after('purchase_id');

            // FKs: só as que já existem hoje
            $table->foreign('item_id')->references('id')->on('inventory_items')->nullOnDelete();
            $table->foreign('purchase_id')->references('id')->on('purchase_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropForeign(['purchase_id']);
            $table->dropColumn(['amount_estimated','item_id','event_id','purchase_id','meta']);
        });
    }
};
