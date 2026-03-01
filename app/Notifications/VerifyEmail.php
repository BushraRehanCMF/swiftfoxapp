<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmail extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
    }

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
        // Generate signed API URL parameters
        $apiUrl = URL::temporarySignedRoute(
            'auth.verify-email',
            now()->addHours(24),
            ['user' => $notifiable->id]
        );

        // Extract query parameters from API URL
        $parsedUrl = parse_url($apiUrl);
        parse_str($parsedUrl['query'] ?? '', $queryParams);

        // Build frontend URL with same signature parameters
        $frontendUrl = config('app.url') . '/verify-email?' . http_build_query([
            'user' => $notifiable->id,
            'expires' => $queryParams['expires'] ?? '',
            'signature' => $queryParams['signature'] ?? '',
        ]);

        return (new MailMessage)
            ->subject('Verify Your SwiftFox Email')
            ->greeting('Welcome to SwiftFox!')
            ->line('Please verify your email address to complete your registration.')
            ->action('Verify Email', $frontendUrl)
            ->line('This verification link expires in 24 hours.')
            ->line('If you did not create this account, no further action is required.');
    }
}
