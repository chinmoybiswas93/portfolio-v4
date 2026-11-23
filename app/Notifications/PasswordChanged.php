<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Security notice to the account email after the admin password changes (ADR-0010). Sent straight away, not queued.
 */
class PasswordChanged extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your admin password was changed')
            ->line('The password for your '.config('app.name').' admin account was changed on '.now('Europe/London')->format('j M Y \a\t H:i').' (UK time).')
            ->line('Every other signed-in session was signed out.')
            ->line('If this wasn’t you, reset the password on the server with `php artisan app:admin-reset` and review your two-factor settings straight away.');
    }
}
