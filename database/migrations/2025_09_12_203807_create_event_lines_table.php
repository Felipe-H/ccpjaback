<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_lines', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('line_id')->constrained('spiritual_lines');
            $table->enum('role', ['principal','cantada','presente'])->default('principal');
            $table->timestamps();

            $table->primary(['event_id','line_id']); // pivot sem id
            $table->index(['line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_lines');
    }
};
