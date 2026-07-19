<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_email_drafts', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_email_drafts', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('supplier_email_drafts', 'qwen_model')) {
                $table->string('qwen_model')->nullable()->after('sent_at');
            }

            if (! Schema::hasColumn('supplier_email_drafts', 'qwen_metadata')) {
                $table->json('qwen_metadata')->nullable()->after('qwen_model');
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_email_drafts', function (Blueprint $table): void {
            if (Schema::hasColumn('supplier_email_drafts', 'qwen_metadata')) {
                $table->dropColumn('qwen_metadata');
            }

            if (Schema::hasColumn('supplier_email_drafts', 'qwen_model')) {
                $table->dropColumn('qwen_model');
            }

            if (Schema::hasColumn('supplier_email_drafts', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
        });
    }
};
