<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Unguessable id handed to the browser. The integer id must never be
            // used to look up a transaction from a public endpoint.
            $table->string('public_id', 26)->nullable()->unique()->after('id');

            // portal  = paid through the gateway, money sits in the platform account.
            // voucher = redeemed voucher, cash the tenant already collected offline.
            $table->string('channel', 20)->default('portal')->after('status');
        });

        DB::table('transactions')->whereNull('public_id')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('transactions')->where('id', $row->id)->update(['public_id' => (string) Str::ulid()]);
            }
        });

        // Voucher redemptions never went through the gateway, so they have no order id.
        DB::table('transactions')
            ->whereNull('palmpesa_order_id')
            ->where('status', 'completed')
            ->update(['channel' => 'voucher']);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'channel']);
        });
    }
};
