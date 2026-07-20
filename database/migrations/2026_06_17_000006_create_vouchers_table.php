<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('tenant_packages')->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('batch_ref')->nullable()->index(); // groups vouchers from same print run
            $table->timestamp('used_at')->nullable();
            $table->string('used_by_phone', 20)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
