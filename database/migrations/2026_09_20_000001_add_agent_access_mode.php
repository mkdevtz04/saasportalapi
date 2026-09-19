<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent mode: no RADIUS and no inbound access to the router. The router calls the platform every
 * few seconds and creates the customer's hotspot user itself when the platform asks it to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('router_commands', function (Blueprint $table) {
            // The login a command is about, so the portal can ask "is this customer's access ready yet".
            $table->string('reference', 64)->nullable()->after('type')->index();
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Set once the platform has told the router to remove the customer's access at expiry.
            $table->timestamp('access_ended_at')->nullable()->after('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('access_ended_at');
        });

        Schema::table('router_commands', function (Blueprint $table) {
            $table->dropColumn('reference');
        });
    }
};
