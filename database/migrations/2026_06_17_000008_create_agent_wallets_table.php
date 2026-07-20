<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_user_id')->unique()->constrained('tenant_users')->cascadeOnDelete();
            $table->unsignedInteger('balance')->default(0); // TZS
            $table->timestamps();
        });

        Schema::create('agent_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('agent_wallets')->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->unsignedInteger('amount'); // TZS
            $table->unsignedInteger('balance_after'); // snapshot for audit trail
            $table->string('reference')->nullable(); // transaction ID or manual top-up ref
            $table->string('description')->nullable();
            $table->timestamp('created_at');

            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_wallet_transactions');
        Schema::dropIfExists('agent_wallets');
    }
};
