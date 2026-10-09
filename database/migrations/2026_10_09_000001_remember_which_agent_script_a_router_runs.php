<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which version of the agent script a router is actually running.
 *
 * The script is installed on the router once, at setup, and never updates itself — on purpose,
 * because the platform's reply to a poll is data the router reads, never code it runs. So a fix
 * to the script reaches a router only when its owner re-runs the setup command, and until now
 * there was no way to tell who had and who had not.
 *
 * Each script version now names itself in its poll. A router that polls without naming one is
 * running a script from before this existed, which is the case worth spotting: those are the
 * ones whose customers' deadlines are kept only by the platform's own sweep.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_routers', function (Blueprint $table) {
            $table->unsignedSmallInteger('agent_version')->nullable()->after('routeros_version');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_routers', function (Blueprint $table) {
            $table->dropColumn('agent_version');
        });
    }
};
