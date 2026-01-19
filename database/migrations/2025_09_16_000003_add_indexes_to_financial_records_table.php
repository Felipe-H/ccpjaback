<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->index('date');
            $table->index('type');
            $table->index('payment_status');
            $table->index('category');
            $table->index('item_id');
            $table->index('event_id');
            $table->index('purchase_id');
        });
    }

    public function down(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropIndex(['type']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['category']);
            $table->dropIndex(['item_id']);
            $table->dropIndex(['event_id']);
            $table->dropIndex(['purchase_id']);
        });
    }
};
