<?php

namespace App\Notifications;

use App\Services\SmtpConfiguration;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use RuntimeException;

class PartnerResetPassword extends Notification
{
    public function __construct(
        #[\SensitiveParameter] private readonly string $token,
        private readonly array $sender,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $url = parse_url($baseUrl);
        if (!is_array($url) || ($url['scheme'] ?? null) !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
            throw new RuntimeException('The canonical HTTPS site URL is unavailable.');
        }
        $resetUrl = $baseUrl.'/reset-password/'.rawurlencode($this->token).'?'.http_build_query([
            'email' => $notifiable->getEmailForPasswordReset(),
        ], '', '&', PHP_QUERY_RFC3986);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->mailer(SmtpConfiguration::MAILER)
            ->from($this->sender['address'], $this->sender['name'])
            ->subject('Reset your All In Streams password')
            ->greeting('Reset your password')
            ->line('We received a request to reset the password for your All In Streams account.')
            ->action('Reset password', $resetUrl)
            ->line('This link expires in '.$minutes.' minutes and can be used once.')
            ->line('If you did not request this email, you can ignore it.');
    }
}
