<?php

namespace App\Services;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactMessageNotification
{
    public function __construct(private readonly SmtpConfiguration $smtp) {}

    /** Delivery is best effort; an SMTP outage must never discard an enquiry. */
    public function send(ContactMessage $message): void
    {
        try {
            if (! $this->smtp->isReady()) {
                return;
            }

            $recipient = MailSetting::query()->find(1)?->contact_recipient;
            $recipients = filled($recipient)
                ? [$recipient]
                : User::query()->where('role', 'admin')->where('is_demo', false)->pluck('email')->all();
            $recipients = array_values(array_unique(array_filter($recipients, fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))));
            if (! $recipients) {
                return;
            }

            $sender = $this->smtp->apply();
            Mail::mailer(SmtpConfiguration::MAILER)->to($recipients)->send(new ContactMessageReceived($message, $sender));
        } catch (Throwable) {
            // Never log transport exception messages, credentials, or contact contents.
            Log::warning('Contact notification email could not be sent. The message remains in the admin inbox.', ['contact_message_id' => $message->id]);
        }
    }
}
