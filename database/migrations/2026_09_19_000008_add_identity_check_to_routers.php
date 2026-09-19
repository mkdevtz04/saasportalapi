<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_routers', function (Blueprint $table) {
            // Does the router still carry the name the platform gave it? RADIUS logins only work
            // on routers whose name starts with nas-<tenant id>-, so a renamed router rejects everyone.
            // Null means not known yet.
            $table->boolean('identity_ok')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_routers', function (Blueprint $table) {
            $table->dropColumn('identity_ok');
        });
    }
};
