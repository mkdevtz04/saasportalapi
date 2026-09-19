<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The platform is free to use: there are no trials and no monthly subscriptions.
 * The only revenue is the fee taken from withdrawals.
 *
 * "trial" was also doubling as "has not finished onboarding yet", so that state
 * is renamed to "onboarding" instead of being dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Widen the enum first so the rename below is valid, then narrow it.
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['trial', 'onboarding', 'active', 'suspended'])->default('onboarding')->change();
        });

        DB::table('tenants')->where('status', 'trial')->update(['status' => 'onboarding']);

        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['onboarding', 'active', 'suspended'])->default('onboarding')->change();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['trial_ends_at', 'monthly_fee_tzs', 'next_billing_at', 'last_billed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable();
            $table->unsignedInteger('monthly_fee_tzs')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('last_billed_at')->nullable();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['trial', 'onboarding', 'active', 'suspended'])->default('trial')->change();
        });

        DB::table('tenants')->where('status', 'onboarding')->update(['status' => 'trial']);

        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['trial', 'active', 'suspended'])->default('trial')->change();
        });
    }
};
