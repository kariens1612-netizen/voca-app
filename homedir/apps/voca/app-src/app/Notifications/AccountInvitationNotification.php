<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountInvitationNotification extends Notification
{
    public function __construct(public string $url, public string $organisationName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Your invitation to '.$this->organisationName)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('You have been given access to '.$this->organisationName.'.')
            ->line('Set a password using the button below. If you already use Voca, you can sign in with your existing password. Changing it changes it for all your Voca organisations.')
            ->action('Set your password', $this->url)
            ->line('This link expires in '.config('auth.passwords.users.expire').' minutes. You can request another from the sign-in page.');
    }
}
