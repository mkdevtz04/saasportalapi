<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two indexes for queries that run on every tenant, every minute.
 *
 * The expiry sweep asks for completed sales whose time is up and whose access has not been ended
 * yet. It asks across every tenant at once, sixty times an hour, and until now it had no index to
 * use: it read the whole transactions table each time. On a platform with a handful of ISPs that
 * is invisible; on one with a few hundred and a year of sales it becomes the heaviest thing the
 * database does all day.
 *
 * access_ended_at leads the first index because it is the selective half. Once a sale's access
 * has ended the column is set for good, so the rows left NULL are only those still running or
 * just finished — a small and roughly constant slice, however large the table grows.
 *
 * The second index is for the question asked about a customer who tops up: has this device got
 * time left on another sale? Looking that up by MAC had no index either, and it is asked once per
 * expiring customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['access_ended_at', 'expires_at'], 'transactions_expiry_sweep_index');
            $table->index(['tenant_id', 'customer_mac'], 'transactions_tenant_device_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_expiry_sweep_index');
            $table->dropIndex('transactions_tenant_device_index');
        });
    }
};
