<?php

namespace App\Http\Controllers;

use App\Models\DeliverySubmission;
use App\Models\Invitation;
use App\Models\PaymentMilestone;
use App\Services\ProviderDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProviderDeliveryController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        abort_unless(in_array($actor->role, ['creator', 'agency'], true), 403);
        $submissions = DeliverySubmission::query()->ownedBy($actor)->with('invitation.brief')->latest()->get()->map(fn ($submission) => $submission->forRecipient($actor));
        $opportunities = Invitation::query()->where('selection_status', 'selected')->where('status', 'interested')->whereHas('brief', fn ($query) => $query->where('status', 'live'))
            ->where(function ($query) use ($actor) {
                if ($actor->role === 'agency') { $query->where('agency_user_id', $actor->id); }
                else { $query->whereHas('creatorProfile', fn ($profile) => $profile->where('user_id', $actor->id)); }
            })->with('brief')->get()->filter(fn ($invitation) => $invitation->accepted_offer_version === $invitation->offer_version)->map(fn ($invitation) => $invitation->forRecipient($actor));

        return view('portal.provider-delivery', compact('submissions', 'opportunities'));
    }

    public function store(Request $request, Invitation $invitation, ProviderDelivery $delivery)
    {
        $request->validate(['evidence' => ['nullable', 'file']]);
        $delivery->submit($invitation, $request->user(), $request->all(), $request->file('evidence'));

        return redirect()->route('provider.deliveries')->with('status', 'Your delivery evidence has been sent to All In Streams for review.');
    }

    public function payments(Request $request)
    {
        $actor = $request->user();
        abort_unless(in_array($actor->role, ['creator', 'agency'], true), 403);
        $milestones = PaymentMilestone::query()->ownedBy($actor)->with('invitation.brief')->latest()->get()->map(fn ($milestone) => $milestone->forRecipient($actor));

        return view('portal.provider-payments', compact('milestones'));
    }

    public function download(Request $request, DeliverySubmission $delivery)
    {
        abort_unless($request->user()->role === 'admin' || $delivery->invitation->ownedBy($request->user()), 403);
        abort_unless($delivery->file_path && str_starts_with($delivery->file_path, 'provider-deliveries/') && Storage::disk('local')->exists($delivery->file_path), 404);

        return Storage::disk('local')->download($delivery->file_path);
    }
}
