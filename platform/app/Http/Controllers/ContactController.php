<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Services\ContactFormChallenge;
use App\Services\ContactMessageNotification;
use App\Services\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContactController extends Controller
{
    public function create(Request $request, SiteContent $content, ContactFormChallenge $challenge): Response
    {
        $previousChallenge = $request->old('contact_challenge');
        $contactChallenge = $challenge->read($request, $previousChallenge, 0) !== null
            ? $previousChallenge
            : $challenge->issue($request);

        return response()->view('site.contact', [
            'site' => $content->getPublished('settings'),
            'contactChallenge' => $contactChallenge,
            'contactMinimumSeconds' => ContactFormChallenge::MINIMUM_SECONDS,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(StoreContactMessageRequest $request, SiteContent $content, ContactMessageNotification $notifications): RedirectResponse
    {
        if (($submissionKey = $request->submissionKey()) !== null) {
            // Unique challenge keys also make retries safe across concurrent requests.
            $message = ContactMessage::firstOrCreate(
                ['submission_key' => $submissionKey],
                $request->safe()->only(['name', 'email', 'subject', 'message']),
            );
            if ($message->wasRecentlyCreated) {
                $notifications->send($message);
            }
        }

        $site = $content->getPublished('settings');

        return to_route('contact.create')->with('contact_status', $site['contact']['success_message'] ?? 'Thank you. Your message has been received.');
    }
}
