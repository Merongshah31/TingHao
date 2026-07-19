<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_order_items', 'accepted_quantity')) {
                $table->decimal('accepted_quantity', 10, 2)->default(0)->after('received_quantity');
            }

            if (! Schema::hasColumn('purchase_order_items', 'damaged_quantity')) {
                $table->decimal('damaged_quantity', 10, 2)->default(0)->after('accepted_quantity');
            }

            if (! Schema::hasColumn('purchase_order_items', 'returned_quantity')) {
                $table->decimal('returned_quantity', 10, 2)->default(0)->after('damaged_quantity');
            }

            if (! Schema::hasColumn('purchase_order_items', 'shortage_quantity')) {
                $table->decimal('shortage_quantity', 10, 2)->default(0)->after('returned_quantity');
            }

            if (! Schema::hasColumn('purchase_order_items', 'receiving_notes')) {
                $table->text('receiving_notes')->nullable()->after('quality_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $columns = collect([
                'accepted_quantity',
                'damaged_quantity',
                'returned_quantity',
                'shortage_quantity',
                'receiving_notes',
            ])->filter(fn (string $column): bool => Schema::hasColumn('purchase_order_items', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
