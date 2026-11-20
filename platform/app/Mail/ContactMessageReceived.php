<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;

class ContactMessageReceived extends Mailable
{
    public function __construct(public readonly ContactMessage $contactMessage, private readonly array $sender) {}

    public function build(): self
    {
        return $this->from($this->sender['address'], $this->sender['name'])
            ->replyTo($this->contactMessage->email, $this->contactMessage->name)
            ->subject('New website enquiry - All In Streams')
            ->text('emails.contact-message', [
                'inboxUrl' => rtrim((string) config('app.url'), '/').'/admin/contact-messages',
            ]);
    }
}
