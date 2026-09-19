<?php

namespace App\Notifications;

use App\Models\TenantRouter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an ISP owner that a router stopped reporting, or that it is back.
 */
class RouterStatusNotification extends Notification
{
    public function __construct(private TenantRouter $router, private bool $online)
    {
    }

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->online) {
            return (new MailMessage())
                ->subject("Router back online: {$this->router->name}")
                ->greeting('Good news')
                ->line("Your router \"{$this->router->name}\" is reporting again. Customers can connect.");
        }

        return (new MailMessage())
            ->subject("Router offline: {$this->router->name}")
            ->greeting('Your router stopped reporting')
            ->line("Your router \"{$this->router->name}\" has not checked in with TrinetPay for several minutes.")
            ->line('Customers cannot buy or connect until it is back. Check the power and the internet connection at the site.')
            ->line('We will email you again when it returns.');
    }
}
