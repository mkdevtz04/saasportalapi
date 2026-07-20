<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('monthly_fee_tzs')->nullable()->after('trial_ends_at');
            $table->timestamp('next_billing_at')->nullable()->after('monthly_fee_tzs');
            $table->timestamp('last_billed_at')->nullable()->after('next_billing_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['monthly_fee_tzs', 'next_billing_at', 'last_billed_at']);
        });
    }
};
