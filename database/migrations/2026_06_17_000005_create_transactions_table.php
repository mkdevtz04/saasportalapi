<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('router_id')->nullable()->constrained('tenant_routers')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('tenant_packages')->nullOnDelete();
            $table->string('phone', 20);
            $table->unsignedInteger('amount'); // TZS
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('palmpesa_order_id')->nullable()->index();
            $table->string('palmpesa_txn_id')->nullable();
            $table->string('voucher_code')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            // Customer device (from MikroTik redirect params)
            $table->string('customer_mac', 17)->nullable();
            $table->string('customer_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
