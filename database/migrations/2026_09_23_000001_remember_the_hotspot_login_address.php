<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The address a customer's device posts its WiFi code to, for example http://192.168.88.1/login.
 *
 * It only ever reaches the platform as a query parameter on a portal link, and a returning
 * customer often arrives without one: from history, from a bookmark, or through a captive-portal
 * window that dropped the query string. Until now that left them looking at their code with no
 * way to use it. The address belongs to the router and does not change, so the first time a real
 * one arrives it is kept here and used whenever the link does not carry one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_routers', function (Blueprint $table) {
            $table->string('hotspot_login_url', 255)->nullable()->after('public_ip');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_routers', function (Blueprint $table) {
            $table->dropColumn('hotspot_login_url');
        });
    }
};
