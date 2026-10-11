<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A running trail of how much data each router has carried, so a tenant can see usage over
 * time instead of only a live snapshot.
 *
 * An agent-mode router already reports in every few seconds; this adds one more number to that
 * same reply, the bytes-in plus bytes-out summed across its currently active hotspot sessions,
 * which costs it nothing extra to read. Not every poll is kept, or this would be a row every ten
 * seconds forever: one is kept every few minutes, and bytes_delta is how much moved since the row
 * before it, worked out here rather than trusted from the router, because a reboot resets the
 * router's own counter to zero and a plain subtraction would read that as negative use.
 *
 * RADIUS-mode routers already have real session accounting in radacct and are read from there
 * instead. API-mode routers, the legacy way of connecting, are not sampled yet.
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
