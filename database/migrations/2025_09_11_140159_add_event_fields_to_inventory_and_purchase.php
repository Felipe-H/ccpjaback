<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Itens de estoque: classificação + evento de origem (opcional)
        Schema::table('inventory_items', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_items', 'scope')) {
                $table->enum('scope', ['common','event'])->default('common');
            }
            if (!Schema::hasColumn('inventory_items', 'event_id')) {
                $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            }
            // índices úteis para filtros
            $table->index(['scope']);
            $table->index(['event_id']);
        });

        // Lotes de compra associados a evento (opcional)
        if (Schema::hasTable('purchase_batches')) {
            Schema::table('purchase_batches', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_batches', 'event_id')) {
                    $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
                }
                $table->index(['event_id']);
            });
        }
    }

    public function down(): void {
        if (Schema::hasTable('purchase_batches')) {
            Schema::table('purchase_batches', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_batches', 'event_id')) {
                    $table->dropConstrainedForeignId('event_id');
                }
            });
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'event_id')) {
                $table->dropConstrainedForeignId('event_id');
            }
            if (Schema::hasColumn('inventory_items', 'scope')) {
                $table->dropColumn('scope');
            }
        });
    }
};
