<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expiry_loss_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_at_risk', 12, 2)->nullable();
            $table->string('unit')->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('potential_loss', 12, 2)->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('days_until_expiry')->nullable();
            $table->string('recommendation_title')->nullable();
            $table->text('recommendation_body')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ingredient_id', 'expiry_date', 'status'], 'expiry_loss_ingredient_date_status_index');
            $table->index(['expiry_date', 'status'], 'expiry_loss_expiry_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expiry_loss_recommendations');
    }
};
