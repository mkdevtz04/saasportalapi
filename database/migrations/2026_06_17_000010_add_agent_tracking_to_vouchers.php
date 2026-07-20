<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Who sold this voucher (agent POS) — null if not yet sold or sold directly by owner
            $table->foreignId('agent_id')
                  ->nullable()
                  ->after('used_by_phone')
                  ->constrained('tenant_users')
                  ->nullOnDelete();

            // When the agent dispensed the voucher (money moves at this point)
            $table->timestamp('sold_at')->nullable()->after('agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
            $table->dropColumn('sold_at');
        });
    }
};
