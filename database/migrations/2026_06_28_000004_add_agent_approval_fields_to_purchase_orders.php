<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_orders', 'agent_run_id')) {
                $table->foreignId('agent_run_id')->nullable()->after('supplier_id')->constrained('agent_runs')->nullOnDelete();
            }

            if (! Schema::hasColumn('purchase_orders', 'requested_by')) {
                $table->foreignId('requested_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('purchase_orders', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('purchase_orders', 'agent_reasoning')) {
                $table->text('agent_reasoning')->nullable()->after('notes');
            }
        });

        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_run_id')->nullable()->constrained('agent_runs')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type')->default('purchase_order');
            $table->string('status')->default('pending');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['requested_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');

        Schema::table('purchase_orders', function (Blueprint $table): void {
            foreach (['agent_run_id', 'requested_by', 'approved_by'] as $column) {
                if (Schema::hasColumn('purchase_orders', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            if (Schema::hasColumn('purchase_orders', 'agent_reasoning')) {
                $table->dropColumn('agent_reasoning');
            }
        });
    }
};
