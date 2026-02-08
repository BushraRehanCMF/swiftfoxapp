<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $token
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->buildResetUrl($notifiable);

        return (new MailMessage)
            ->subject('You\'ve been invited to join SwiftFox')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You\'ve been invited to join your team on SwiftFox.')
            ->line('SwiftFox is a powerful WhatsApp business messaging platform that helps teams manage customer conversations efficiently.')
            ->action('Set Your Password', $url)
            ->line('Click the button above to set your password and access your account.')
            ->line('This link will expire in 60 minutes.')
            ->salutation('Welcome aboard!');
    }

    /**
     * Build the password reset URL.
     */
    protected function buildResetUrl(object $notifiable): string
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));

        return $frontendUrl . '/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'team_invitation',
        ];
    }
}
