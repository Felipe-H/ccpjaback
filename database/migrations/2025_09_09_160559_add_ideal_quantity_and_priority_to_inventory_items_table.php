<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            // quantidade ideal
            $table->unsignedInteger('ideal_quantity')
                ->default(0)
                ->after('quantity');

            // prioridade dos itens baixa / média / alta
            $table->enum('priority', ['low', 'medium', 'high'])
                ->default('medium')
                ->after('ideal_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['ideal_quantity', 'priority']);
        });
    }
};
