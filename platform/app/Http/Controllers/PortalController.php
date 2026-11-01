<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Brief;
use App\Models\CampaignDocument;
use App\Models\Invitation;
use App\Services\CampaignWorkflow;
use App\Services\WorkspaceStatistics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortalController extends Controller
{
    public function dashboard(Request $request, WorkspaceStatistics $statistics)
    {
        $briefs = $request->user()->briefs()->latest()->get();
        $stats = $statistics->business($request->user());
        return view('portal.dashboard', compact('briefs', 'stats'));
    }

    public function campaigns(Request $request)
    {
        $briefs = $request->user()->briefs()->latest()->paginate(12);
        return view('portal.campaigns', compact('briefs'));
    }

    public function campaign(Request $request, Brief $brief, WorkspaceStatistics $statistics)
    {
        abort_unless($brief->user_id === $request->user()->id, 403);
        $creators = in_array($brief->status, ['proposal', 'contracts', 'live', 'completed'], true)
            ? $brief->invitations()->with(['creatorProfile', 'agency.agencyProfile', 'brief'])->eligibleForBusiness()->get()->filter(fn (Invitation $invitation) => $invitation->recipientApproved() || ($brief->status === 'completed' && $invitation->selection_status === 'selected'))->map->forBusiness() : collect();
        $activities = $brief->activities()->where('audience', 'business')->latest()->get();
        $documents = $brief->documents()->visibleTo($request->user())->latest()->get();
        $stats = $statistics->campaign($brief, $request->user());
        return view('portal.campaign', compact('brief', 'creators', 'activities', 'documents', 'stats'));
    }

    public function proposals(Request $request)
    {
        $proposals = Invitation::query()->whereHas('brief', fn ($query) => $query->where('user_id', $request->user()->id)->whereIn('status', ['proposal', 'contracts', 'live', 'completed']))->eligibleForBusiness()->with(['creatorProfile', 'agency.agencyProfile', 'brief'])->latest()->get()->filter(fn (Invitation $invitation) => $invitation->recipientApproved() || ($invitation->brief->status === 'completed' && $invitation->selection_status === 'selected'))->map->forBusiness();
        return view('portal.proposals', compact('proposals'));
    }

    public function requestCreator(Request $request, Invitation $invitation)
    {
        abort_unless(in_array($request->user()->role, ['business', 'agency'], true) && $invitation->brief->user_id === $request->user()->id && $invitation->status === 'interested' && $invitation->selection_status !== 'not_selected' && $invitation->recipientApproved() && in_array($invitation->brief->status, ['proposal', 'contracts'], true), 403);
        $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);
        Activity::create(['brief_id' => $invitation->brief_id, 'user_id' => $request->user()->id, 'title' => 'Provider requested by client', 'body' => $invitation->recipient_code.($request->filled('notes') ? ': '.$request->string('notes') : ' requested for the campaign.'), 'audience' => 'admin']);
        return back()->with('status', 'Your account manager has received the request. Staff will confirm the final provider and next steps.');
    }

    public function documents(Request $request)
    {
        $actor = $request->user();
        $documents = CampaignDocument::query()->visibleTo($actor)->with('brief.invitations')->latest()->get()->map(function (CampaignDocument $document) use ($actor) {
            $clientSide = $actor->role === 'admin' || ($document->audience === 'business' && $document->brief->user_id === $actor->id);
            $offer = $clientSide ? null : $document->brief->invitations->first(fn (Invitation $invitation) => $invitation->ownedBy($actor));
            return ['id' => $document->id, 'title' => $clientSide ? $document->title : 'Your service agreement', 'context' => $clientSide ? $document->brief->title : ($offer?->code ?? 'Private partnership'), 'date' => $document->created_at->format('d M Y'), 'signature_status' => $document->signature_status];
        });
        return view('portal.documents', compact('documents'));
    }

    public function download(Request $request, CampaignDocument $document)
    {
        abort_unless($document->visibleTo($request->user()), 403);
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);
        return Storage::disk('local')->download($document->file_path, 'agreement-'.$document->id.'.'.pathinfo($document->file_path, PATHINFO_EXTENSION));
    }

    public function briefAttachment(Request $request, Brief $brief)
    {
        abort_unless($request->user()->role === 'admin' || $brief->user_id === $request->user()->id, 403);
        abort_unless($brief->attachment_path && Storage::disk('local')->exists($brief->attachment_path), 404);
        return Storage::disk('local')->download($brief->attachment_path, 'campaign-brief-'.$brief->id.'.pdf');
    }

    public function opportunities(Request $request, WorkspaceStatistics $statistics)
    {
        $profile = $request->user()->creatorProfile;
        $invitations = $profile ? $profile->invitations()->latest()->get()->map(fn (Invitation $invitation) => $invitation->forRecipient($request->user())) : collect();
        $creatorStats = $statistics->creator($request->user());
        return view('portal.opportunities', compact('invitations', 'profile', 'creatorStats'));
    }

    public function respond(Request $request, Invitation $invitation, CampaignWorkflow $workflow)
    {
        abort_unless($invitation->ownedBy($request->user()), 403);
        $data = $request->validate([
            'status' => ['required', 'in:interested,changes_requested,declined'],
            'notes' => ['required_if:status,changes_requested', 'nullable', 'string', 'max:3000'],
            'counter_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'counter_cpa_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'offer_version' => ['required', 'integer', 'min:1'],
            'proposed_talent_ids' => ['nullable', 'array', 'max:100'],
            'proposed_talent_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $workflow->respond($invitation, $request->user(), $data['status'], $data['notes'] ?? null, $data['counter_amount'] ?? null, $data['counter_cpa_rate'] ?? null, $data['offer_version'], $data['proposed_talent_ids'] ?? []);
        return back()->with('status', 'Your response has been sent to All In Streams. Your account manager will coordinate the next steps.');
    }
}
