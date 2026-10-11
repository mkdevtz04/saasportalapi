<?php

namespace App\Jobs;

use App\Contracts\SmsGateway;
use App\Models\WithdrawalRequest;
use App\Support\Phone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Texts the tenant back on the number they just asked to be paid to, so a withdrawal
 * request does not simply vanish into a dashboard they may not reopen for a day.
 *
 * The admin panel is still where the request is actually decided; this is only the
 * acknowledgement. The money is already held either way, so a text that fails delays a
 * confirmation, it never loses a payout — the same reasoning NotifyAdminWithdrawalJob
 * runs on, for the other side of the same request.
 */
class NotifyTenantWithdrawalRequestedJob implements ShouldQueue
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
        // Runs on the queue with no tenant in context, so the tenant scope has to be lifted.
        $withdrawal = WithdrawalRequest::withoutGlobalScopes()->find($this->withdrawalId);

        // Already dealt with by the time the queue got here: an acknowledgement would be stale.
        if (! $withdrawal || ! $withdrawal->isPending()) {
            return;
        }

        if (! Phone::isMobile((string) $withdrawal->mobile_number)) {
            Log::warning('A withdrawal request has no mobile number to confirm by SMS.', [
                'withdrawal_id' => $withdrawal->id,
            ]);

            return;
        }

        $message = sprintf(
            'TrinetPay: your withdrawal request for TZS %s has been received. '
                . 'We will act on it within 3 hours.',
            number_format($withdrawal->net_amount)
        );

        if (! $sms->send(Phone::international($withdrawal->mobile_number), $message)) {
            throw new RuntimeException('The SMS provider did not accept the withdrawal confirmation.');
        }
    }
}
