<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            // Payment
            $table->enum('payment_gateway', ['palmpesa', 'airtel', 'mpesa'])->default('palmpesa');
            $table->string('payment_number')->nullable(); // Merchant till/number customers pay to
            $table->text('palmpesa_api_key')->nullable();  // stored encrypted
            $table->string('palmpesa_user_id')->nullable();
            // Branding
            $table->string('brand_color', 7)->default('#0066cc'); // hex
            $table->string('tagline')->nullable();
            $table->string('custom_logo_path')->nullable();
            $table->string('contact_phone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
