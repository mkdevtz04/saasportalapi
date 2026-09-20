<?php

namespace App\Jobs;

use App\Contracts\SmsGateway;
use App\Models\WithdrawalRequest;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Texts the platform admin when an ISP asks to withdraw.
 *
 * The money only moves when a person approves it, and nobody watches the admin panel all day,
 * so the request has to reach them. The ISP's money is already held either way: the wallet was
 * debited when the request was made, so a text that fails delays a payout, it never loses one.
 */
class NotifyAdminWithdrawalJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $withdrawalId)
    {
    }

    /** @return int[] */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(SmsGateway $sms): void
    {
        $phone = trim((string) config('platform.alert_phone', ''));

        // No number configured is a choice, not a fault: the admin panel is then the only notice.
        if ($phone === '') {
            return;
        }

        if (! Phone::isMobile($phone)) {
            Log::warning('PLATFORM_ALERT_PHONE is not a mobile number, so no withdrawal alert was sent.');

            return;
        }

        // Runs on the queue with no tenant in context, so the tenant scope has to be lifted.
        $withdrawal = WithdrawalRequest::withoutGlobalScopes()->with('tenant')->find($this->withdrawalId);

        // Already dealt with by the time the queue got here: nothing left to tell anyone.
        if (! $withdrawal || ! $withdrawal->isPending()) {
            return;
        }

        $message = sprintf(
            'TrinetPay: %s asked to withdraw TZS %s (net TZS %s) to %s. Approve it in the admin panel.',
            $withdrawal->tenant?->name ?? 'An ISP',
            number_format($withdrawal->amount),
            number_format($withdrawal->net_amount),
            Audit::maskPhone($withdrawal->mobile_number) ?? 'an unknown number'
        );

        if (! $sms->send(Phone::international($phone), $message)) {
            throw new RuntimeException('The SMS provider did not accept the withdrawal alert.');
        }
    }
}
