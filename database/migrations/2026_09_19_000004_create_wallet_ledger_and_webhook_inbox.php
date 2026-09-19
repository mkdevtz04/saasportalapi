<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only record of every movement of tenant money. The wallet balance is
        // a cached total of these rows, and the two can be checked against each other.
        Schema::create('wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 30);                    // payment, withdrawal, withdrawal_refund, opening_balance, adjustment
            $table->bigInteger('amount');                  // signed: credits positive, debits negative
            $table->unsignedBigInteger('balance_after');
            $table->string('reference', 100);              // TXN-12, WDR-7 ... makes every movement idempotent
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'type', 'reference']);
            $table->index(['tenant_id', 'created_at']);
        });

        // Wallets that already hold money get one opening entry so the ledger adds up from day one.
        DB::table('tenant_wallets')->where('balance', '>', 0)->orderBy('id')->each(function ($wallet) {
            DB::table('wallet_entries')->insert([
                'tenant_id'     => $wallet->tenant_id,
                'type'          => 'opening_balance',
                'amount'        => $wallet->balance,
                'balance_after' => $wallet->balance,
                'reference'     => 'opening',
                'meta'          => json_encode(['note' => 'Balance before the ledger existed']),
                'created_at'    => now(),
            ]);
        });

        // Every gateway callback is stored exactly as received before anything acts on it.
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30)->default('palmpesa');
            $table->string('order_id')->nullable()->index();
            $table->json('payload');
            $table->string('ip', 45)->nullable();
            $table->string('result', 40)->nullable();      // settled, pending, already_processed, not_found ...
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Did the customer actually get online access? Separate from "did they pay".
            $table->string('provision_status', 20)->default('pending')->after('status');
            $table->string('provision_error', 255)->nullable()->after('provision_status');
        });

        // Everything already completed was provisioned the old way.
        DB::table('transactions')->where('status', 'completed')->update(['provision_status' => 'done']);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['provision_status', 'provision_error']);
        });
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('wallet_entries');
    }
};
