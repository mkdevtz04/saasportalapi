<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_routers', function (Blueprint $table) {
            $table->string('provision_token', 64)->nullable()->unique()->after('nas_identifier');
            $table->enum('provision_status', ['pending', 'script_downloaded', 'completed', 'failed'])->default('pending')->after('provision_token');
            $table->timestamp('provisioned_at')->nullable()->after('provision_status');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_routers', function (Blueprint $table) {
            $table->dropColumn(['provision_token', 'provision_status', 'provisioned_at']);
        });
    }
};
