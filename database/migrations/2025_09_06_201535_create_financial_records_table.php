<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('financial_records', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['income','expense']);
            $table->string('category')->default('Outros');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->date('date')->nullable();
            $table->enum('payment_status', ['paid','pending','estimated'])->default('pending');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // opcional
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('financial_records');
    }
};
