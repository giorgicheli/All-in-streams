New contact message

Name: {!! $contactMessage->name !!}
Email: {!! $contactMessage->email !!}
Subject: {!! $contactMessage->subject !!}
Received: {{ $contactMessage->created_at->toIso8601String() }}

{!! $contactMessage->message !!}

Admin inbox: {!! $inboxUrl !!}

This message is stored privately in the admin inbox. Reply to this email to contact the sender.
