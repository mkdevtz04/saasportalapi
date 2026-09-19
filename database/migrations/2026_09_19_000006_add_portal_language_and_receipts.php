<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Language the customer used on the portal, so the SMS receipt matches it.
            $table->string('locale', 5)->default('en')->after('channel');
            $table->timestamp('receipt_sent_at')->nullable()->after('provision_error');
        });

        Schema::table('tenant_settings', function (Blueprint $table) {
            // Portal language for customers who have not chosen one: sw (Swahili) or en (English).
            $table->string('default_language', 5)->default('sw')->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('default_language');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['locale', 'receipt_sent_at']);
        });
    }
};
