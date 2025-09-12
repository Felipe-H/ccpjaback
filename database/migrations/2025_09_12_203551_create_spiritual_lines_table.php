<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('spiritual_lines', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // ex.: Pretos-Velhos
            $table->string('slug')->unique();
            $table->enum('type', ['orixa','linha','falange']);
            $table->foreignId('parent_id')->nullable()
                ->constrained('spiritual_lines')->nullOnDelete(); // hierarquia
            $table->text('description')->nullable();
            $table->string('icon')->nullable();     // p/ UI
            $table->string('color_hex', 16)->nullable();
            $table->json('meta')->nullable();       // saudacao, elementos, etc.
            $table->enum('status', ['ativo','arquivado'])->default('ativo');
            $table->integer('sort_order')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spiritual_lines');
    }
};
