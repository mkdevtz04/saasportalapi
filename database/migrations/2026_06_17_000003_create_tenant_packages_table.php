<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('price'); // TZS, whole numbers only
            $table->unsignedInteger('duration_hours');
            $table->unsignedSmallInteger('speed_down_mbps');
            $table->unsignedSmallInteger('speed_up_mbps');
            $table->unsignedInteger('data_cap_mb')->nullable(); // null = unlimited
            $table->string('mikrotik_profile'); // must match a profile name on the router
            $table->enum('validity_type', ['strict', 'first_login'])->default('strict');
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_packages');
    }
};
