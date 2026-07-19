<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_reasoning_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->string('step_type');
            $table->string('title');
            $table->text('summary');
            $table->json('evidence')->nullable();
            $table->decimal('confidence', 4, 2)->nullable();
            $table->string('risk_level')->nullable();
            $table->boolean('requires_human_approval')->default(false);
            $table->foreignId('related_tool_call_id')->nullable()->constrained('agent_tool_calls')->nullOnDelete();
            $table->timestamps();

            $table->index(['agent_run_id', 'step_order']);
            $table->index(['step_type', 'risk_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_reasoning_steps');
    }
};
