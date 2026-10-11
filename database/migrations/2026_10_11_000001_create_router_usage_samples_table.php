<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A running trail of how much data each router has carried. Not used any more.
 *
 * This backed a bandwidth graph on the reports page: an agent-mode router summed bytes-in plus
 * bytes-out across its active hotspot sessions on every poll and sent that number along. On
 * weaker hardware with many customers, the extra per-session reads behind that sum were heavy
 * enough to slow the poll itself down, which risked the very thing that matters most — a paying
 * customer getting connected without delay — so the feature was pulled.
 *
 * The table is left in place, empty, rather than reverted: dropping it here would mean rolling
 * this migration back on a server where it may already share a batch with others applied the
 * same run, which is more risk than an unused table is worth. Safe to drop by hand later if ever
 * wanted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('router_usage_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('router_id')->constrained('tenant_routers')->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->unsignedBigInteger('bytes_total')->nullable();
            $table->unsignedBigInteger('bytes_delta')->default(0);
            $table->unsignedInteger('active_users')->default(0);

            $table->index(['router_id', 'recorded_at']);
            $table->index(['tenant_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('router_usage_samples');
    }
};
