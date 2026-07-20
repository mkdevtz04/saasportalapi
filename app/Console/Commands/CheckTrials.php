<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckTrials extends Command
{
    protected $signature   = 'billing:check-trials';
    protected $description = 'Suspend tenants whose trial period has expired';

    public function handle(): int
    {
        $expired = Tenant::where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired trials found.');
            return self::SUCCESS;
        }

        foreach ($expired as $tenant) {
            $tenant->update(['status' => 'suspended']);
            Log::info('Trial expired — tenant suspended', ['tenant_id' => $tenant->id, 'name' => $tenant->name]);
            $this->line("Suspended: {$tenant->name} ({$tenant->subdomain})");
        }

        $this->info("Suspended {$expired->count()} expired trial(s).");
        return self::SUCCESS;
    }
}
