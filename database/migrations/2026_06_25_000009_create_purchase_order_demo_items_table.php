<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_demo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_demo_id')->constrained()->cascadeOnDelete();
            $table->string('ingredient_name');
            $table->decimal('quantity', 10, 2);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->decimal('received_quantity', 10, 2)->default(0);
            $table->string('quality_status')->nullable();
            $table->timestamps();

            $table->index('purchase_order_demo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_demo_items');
    }
};
