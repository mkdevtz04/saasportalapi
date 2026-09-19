<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who did what, to whom, from where. Append-only: rows are never edited or deleted.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 10);              // admin, tenant, system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_label', 120)->nullable(); // email, so the trail survives a deleted user
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('action', 60)->index();
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
        });

        Schema::table('tenant_routers', function (Blueprint $table) {
            // Set when the owner was told the router went offline, cleared when it returns.
            // It stops the same outage from sending an alert every few minutes.
            $table->timestamp('offline_alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_routers', function (Blueprint $table) {
            $table->dropColumn('offline_alerted_at');
        });

        Schema::dropIfExists('audit_logs');
    }
};
