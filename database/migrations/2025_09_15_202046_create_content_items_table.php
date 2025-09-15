<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('status', 16)->default('draft');
            $table->string('visibility', 16)->default('members');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('source_url')->nullable();
            $table->string('cover_url')->nullable();
            $table->unsignedInteger('duration_sec')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
    }
};
