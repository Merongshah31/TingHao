<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_email_drafts', function (Blueprint $table): void {
            $table->string('delivery_status')->nullable()->after('sent_at');
            $table->string('delivery_provider')->nullable()->after('delivery_status');
            $table->json('delivery_metadata')->nullable()->after('delivery_provider');
            $table->timestamp('last_delivery_attempt_at')->nullable()->after('delivery_metadata');

            $table->index(['delivery_status', 'last_delivery_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::table('supplier_email_drafts', function (Blueprint $table): void {
            $table->dropIndex(['delivery_status', 'last_delivery_attempt_at']);
            $table->dropColumn([
                'delivery_status',
                'delivery_provider',
                'delivery_metadata',
                'last_delivery_attempt_at',
            ]);
        });
    }
};
