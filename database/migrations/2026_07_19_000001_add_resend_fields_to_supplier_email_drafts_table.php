<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_email_drafts', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_email_drafts', 'provider')) {
                $table->string('provider')->nullable()->after('sent_at');
            }

            if (! Schema::hasColumn('supplier_email_drafts', 'provider_message_id')) {
                $table->string('provider_message_id')->nullable()->after('provider');
            }

            if (! Schema::hasColumn('supplier_email_drafts', 'sent_by')) {
                $table->foreignId('sent_by')->nullable()->after('sent_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('supplier_email_drafts', 'send_error_category')) {
                $table->string('send_error_category')->nullable()->after('delivery_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_email_drafts', function (Blueprint $table): void {
            if (Schema::hasColumn('supplier_email_drafts', 'sent_by')) {
                $table->dropConstrainedForeignId('sent_by');
            }

            foreach (['provider', 'provider_message_id', 'send_error_category'] as $column) {
                if (Schema::hasColumn('supplier_email_drafts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
