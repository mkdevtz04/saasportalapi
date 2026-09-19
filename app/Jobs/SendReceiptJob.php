<?php

namespace App\Jobs;

use App\Contracts\SmsGateway;
use App\Models\Transaction;
use App\Support\Phone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Lang;
use RuntimeException;

/**
 * Texts the customer their WiFi code after a portal payment, in the language they used
 * on the portal. It is a convenience: if it fails the customer still has the code on screen.
 */
class SendReceiptJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $transactionId)
    {
    }

    /** @return int[] */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(SmsGateway $sms): void
    {
        $transaction = Transaction::withoutGlobalScopes()
            ->with(['tenant', 'package'])
            ->find($this->transactionId);

        if (! $transaction
            || $transaction->status !== 'completed'
            || $transaction->channel !== Transaction::CHANNEL_PORTAL
            || $transaction->receipt_sent_at !== null
            || ! $transaction->voucher_code
            || ! Phone::isMobile((string) $transaction->phone)) {
            return;
        }

        $expires = $transaction->expires_at?->copy()->timezone('Africa/Dar_es_Salaam');

        $message = Lang::get('sms.receipt', [
            'business' => $transaction->tenant?->name ?? 'WiFi',
            'amount'   => number_format($transaction->amount),
            'package'  => $transaction->package?->name ?? 'WiFi',
            'token'    => $transaction->voucher_code,
            'expires'  => $expires ? $expires->format('d M H:i') : '-',
            'ref'      => $transaction->reference(),
        ], in_array($transaction->locale, ['sw', 'en'], true) ? $transaction->locale : 'en');

        if (! $sms->send(Phone::international($transaction->phone), $message)) {
            throw new RuntimeException('The SMS provider did not accept the receipt.');
        }

        $transaction->update(['receipt_sent_at' => now()]);
    }
}
