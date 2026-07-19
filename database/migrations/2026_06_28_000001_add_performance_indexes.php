<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table): void {
            $table->index(['quantity', 'minimum_stock'], 'ingredients_stock_level_index');
            $table->index(['category_id', 'name'], 'ingredients_category_name_index');
            $table->index(['supplier_id', 'name'], 'ingredients_supplier_name_index');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index('created_at', 'stock_movements_created_at_index');
            $table->index(['type', 'created_at'], 'stock_movements_type_created_at_index');
        });

        Schema::table('restock_requests', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'restock_requests_status_created_at_index');
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'purchase_orders_status_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex('purchase_orders_status_created_at_index');
        });

        Schema::table('restock_requests', function (Blueprint $table): void {
            $table->dropIndex('restock_requests_status_created_at_index');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('stock_movements_type_created_at_index');
            $table->dropIndex('stock_movements_created_at_index');
        });

        Schema::table('ingredients', function (Blueprint $table): void {
            $table->dropIndex('ingredients_supplier_name_index');
            $table->dropIndex('ingredients_category_name_index');
            $table->dropIndex('ingredients_stock_level_index');
        });
    }
};
