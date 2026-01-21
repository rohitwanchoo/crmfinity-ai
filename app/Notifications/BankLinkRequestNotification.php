<?php

namespace App\Notifications;

use App\Models\BankLinkRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BankLinkRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected BankLinkRequest $linkRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct(BankLinkRequest $linkRequest)
    {
        $this->linkRequest = $linkRequest;
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
        $appName = config('app.name');
        $expiresAt = $this->linkRequest->expires_at->format('F j, Y');

        return (new MailMessage)
            ->subject("Action Required: Connect Your Bank Account - {$appName}")
            ->greeting("Hello {$this->linkRequest->merchant_name},")
            ->line("We need you to securely connect your business bank account to complete the funding application for **{$this->linkRequest->business_name}**.")
            ->line('This process is quick, secure, and uses bank-level encryption.')
            ->action('Connect Bank Account', $this->linkRequest->link_url)
            ->line("**This link expires on {$expiresAt}.**")
            ->line('**What to expect:**')
            ->line('1. Click the button above to open the secure connection page')
            ->line('2. Select your bank from the list')
            ->line('3. Log in with your bank credentials (we never see your password)')
            ->line('4. Select the accounts you want to connect')
            ->line('Your credentials are encrypted and we only receive read-only access to verify your business finances.')
            ->line('If you have any questions or did not request this, please contact us immediately.')
            ->salutation("Best regards,\nThe {$appName} Team");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'link_request_id' => $this->linkRequest->id,
            'application_id' => $this->linkRequest->application_id,
            'business_name' => $this->linkRequest->business_name,
        ];
    }
}
