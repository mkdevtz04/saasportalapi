<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            // amount     = gross, what leaves the tenant wallet
            // fee_amount = platform fee kept from the payout
            // net_amount = what is actually sent to the tenant's mobile money
            $table->unsignedBigInteger('fee_amount')->default(0)->after('amount');
            $table->unsignedBigInteger('net_amount')->nullable()->after('fee_amount');
        });

        // Requests made before withdrawal fees existed were paid out in full.
        DB::table('withdrawal_requests')->update(['net_amount' => DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropColumn(['fee_amount', 'net_amount']);
        });
    }
};
