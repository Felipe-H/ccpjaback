<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');              // ex.: Pai Joaquim
            $table->string('nickname')->nullable();
            $table->foreignId('line_id')->constrained('spiritual_lines');
            $table->string('photo_url')->nullable();
            $table->string('salutation')->nullable(); // saudação
            $table->date('started_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['user_id','name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guides');
    }
};
