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
            ? $brief->invitations()->with(['creatorProfile', 'brief'])->where('status', 'interested')->whereHas('creatorProfile', fn ($query) => $query->where('status', 'approved'))->get()->map->forBusiness() : collect();
        $activities = $brief->activities()->where('audience', 'business')->latest()->get();
        $documents = $brief->documents()->visibleTo($request->user())->latest()->get();
        $stats = $statistics->campaign($brief, $request->user());
        return view('portal.campaign', compact('brief', 'creators', 'activities', 'documents', 'stats'));
    }

    public function proposals(Request $request)
    {
        $proposals = Invitation::query()->whereHas('brief', fn ($query) => $query->where('user_id', $request->user()->id)->whereIn('status', ['proposal', 'contracts', 'live', 'completed']))->where('status', 'interested')->whereHas('creatorProfile', fn ($query) => $query->where('status', 'approved'))->with(['creatorProfile', 'brief'])->latest()->get()->map->forBusiness();
        return view('portal.proposals', compact('proposals'));
    }

    public function requestCreator(Request $request, Invitation $invitation)
    {
        abort_unless($request->user()->role === 'business' && $invitation->brief->user_id === $request->user()->id && $invitation->status === 'interested' && $invitation->creatorProfile->status === 'approved' && in_array($invitation->brief->status, ['proposal', 'contracts'], true), 403);
        $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);
        Activity::create(['brief_id' => $invitation->brief_id, 'user_id' => $request->user()->id, 'title' => 'Creator requested by business', 'body' => $invitation->creatorProfile->code.($request->filled('notes') ? ': '.$request->string('notes') : ' requested for the campaign.'), 'audience' => 'admin']);
        return back()->with('status', 'Your account manager has received the creator request and will coordinate the next steps.');
    }

    public function documents(Request $request)
    {
        $documents = CampaignDocument::query()->visibleTo($request->user())->with('brief')->latest()->get();
        return view('portal.documents', compact('documents'));
    }

    public function download(Request $request, CampaignDocument $document)
    {
        abort_unless($document->visibleTo($request->user()), 403);
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);
        return Storage::disk('local')->download($document->file_path);
    }

    public function opportunities(Request $request, WorkspaceStatistics $statistics)
    {
        $profile = $request->user()->creatorProfile;
        $invitations = $profile ? $profile->invitations()->with([
            'brief' => fn ($query) => $query->select('id', 'title', 'brand', 'vertical', 'geos', 'language', 'platform', 'currency', 'deliverables', 'start_date', 'end_date', 'kpis', 'status', 'created_at', 'is_demo'),
        ])->latest()->get() : collect();
        $creatorStats = $statistics->creator($request->user());
        return view('portal.opportunities', compact('invitations', 'profile', 'creatorStats'));
    }

    public function respond(Request $request, Invitation $invitation, CampaignWorkflow $workflow)
    {
        $data = $request->validate(['status' => ['required', 'in:interested,changes_requested,declined'], 'notes' => ['nullable', 'string', 'max:3000']]);
        $workflow->respond($invitation, $request->user(), $data['status'], $data['notes'] ?? null);
        return back()->with('status', 'Your response has been sent to All In Streams. Your account manager will coordinate the next steps.');
    }
}
