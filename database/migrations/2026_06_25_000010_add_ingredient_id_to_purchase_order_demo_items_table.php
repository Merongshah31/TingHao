<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_demo_items', function (Blueprint $table): void {
            $table->foreignId('ingredient_id')
                ->nullable()
                ->after('purchase_order_demo_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_demo_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ingredient_id');
        });
    }
};
