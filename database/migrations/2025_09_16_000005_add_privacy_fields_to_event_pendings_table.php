<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_pendings', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('assignee_id');
            $table->index(['is_private', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::table('event_pendings', function (Blueprint $table) {
            $table->dropIndex(['is_private', 'created_by']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('is_private');
        });
    }
};
