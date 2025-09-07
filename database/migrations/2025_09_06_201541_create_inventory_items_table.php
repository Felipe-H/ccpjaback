<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->string('category')->default('Outros');
            $table->enum('purchase_type', ['donation','member','purchase'])->default('purchase');
            $table->enum('status', ['available','low_stock','out_of_stock','to_buy'])->default('available');
            $table->text('description')->nullable();
            $table->date('date_added')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // opcional
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('inventory_items');
    }
};
