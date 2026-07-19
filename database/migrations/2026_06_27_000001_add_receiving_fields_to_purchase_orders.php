<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->timestamp('confirmed_at')->nullable()->after('sent_at');
            $table->timestamp('received_at')->nullable()->after('confirmed_at');
            $table->timestamp('closed_at')->nullable()->after('received_at');
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->decimal('received_quantity', 10, 2)->default(0)->after('line_total');
            $table->string('quality_status', 40)->nullable()->after('received_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->dropColumn(['received_quantity', 'quality_status']);
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn(['confirmed_at', 'received_at', 'closed_at']);
        });
    }
};
