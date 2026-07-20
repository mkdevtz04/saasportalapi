<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove per-tenant payment gateway credentials — platform handles all payments
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['payment_gateway', 'palmpesa_api_key', 'palmpesa_user_id', 'payment_number']);
            // Mobile money number where the tenant receives payouts from the platform
            $table->string('withdrawal_number', 30)->nullable()->after('contact_phone');
        });

        // Per-tenant revenue wallet (credited when customers pay)
        Schema::create('tenant_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0); // TZS, accumulated revenue
            $table->unsignedBigInteger('total_earned')->default(0); // lifetime total, never decremented
            $table->timestamps();
        });

        // Tenant payout requests
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('amount'); // TZS requested
            $table->string('mobile_number', 30);  // where to send the money
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('tenant_wallets');

        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('withdrawal_number');
            $table->enum('payment_gateway', ['palmpesa', 'airtel', 'mpesa'])->default('palmpesa')->after('tenant_id');
            $table->string('payment_number')->nullable()->after('payment_gateway');
            $table->text('palmpesa_api_key')->nullable()->after('payment_number');
            $table->string('palmpesa_user_id')->nullable()->after('palmpesa_api_key');
        });
    }
};
