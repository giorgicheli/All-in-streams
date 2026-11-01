<?php

namespace App\Http\Controllers;

use App\Models\AgencyProfile;
use App\Models\AgencyTalent;
use App\Models\Invitation;
use App\Rules\StreamingChannelUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgencyPortalController extends Controller
{
    private function agency(Request $request)
    {
        abort_unless($request->user()?->role === 'agency', 403);
        return $request->user();
    }

    public function dashboard(Request $request)
    {
        $agency = $this->agency($request);
        $profile = $agency->agencyProfile;
        $counts = [
            'talent' => $agency->agencyTalent()->count(),
            'approved' => $agency->agencyTalent()->where('status', 'approved')->count(),
            'offers' => Invitation::where('agency_user_id', $agency->id)->where('status', 'pending')->where('selection_status', 'unselected')->count(),
            'briefs' => $agency->briefs()->count(),
        ];
        return view('agency.dashboard', compact('profile', 'counts'));
    }

    public function profile(Request $request)
    {
        $profile = $this->agency($request)->agencyProfile;
        return view('agency.profile', compact('profile'));
    }

    public function saveProfile(Request $request)
    {
        $agency = $this->agency($request);
        $this->normalizeLists($request, ['languages', 'audience_geos']);
        $data = $request->validate([
            'company' => ['required', 'string', 'max:180'],
            'services' => ['required', 'array', 'min:1', 'max:2'], 'services.*' => ['required', 'in:supply,request', 'distinct'],
            'country' => ['required', 'string', 'max:80'], 'website' => ['nullable', 'url:https', 'max:255'],
            'languages' => ['required', 'array', 'min:1', 'max:20'], 'languages.*' => ['required', 'string', 'max:80', 'distinct'],
            'audience_geos' => ['required', 'array', 'min:1', 'max:30'], 'audience_geos.*' => ['required', 'string', 'max:80', 'distinct'],
            'platforms' => ['required', 'array', 'min:1', 'max:4'], 'platforms.*' => ['required', 'in:Twitch,Kick,YouTube,TikTok', 'distinct'],
            'bio' => ['nullable', 'string', 'max:3000'],
        ]);
        DB::transaction(function () use ($agency, $data) {
            $agency = $agency->newQuery()->lockForUpdate()->findOrFail($agency->id);
            if (Invitation::where('agency_user_id', $agency->id)->where('selection_status', 'selected')->whereHas('brief', fn ($query) => $query->whereIn('status', ['contracts', 'live']))->exists()) {
                throw ValidationException::withMessages(['agency' => 'Your agency is selected for an active contract or live campaign. Complete that work before changing the agency profile.']);
            }
            $agency->update(['company' => $data['company']]);
            unset($data['company']);
            AgencyProfile::updateOrCreate(['user_id' => $agency->id], $data + ['status' => 'pending']);
        });
        return to_route('agency.profile')->with('status', 'Your agency profile was submitted for staff review. Roster supply is available after approval.');
    }

    public function roster(Request $request)
    {
        $agency = $this->agency($request);
        $talents = $agency->agencyTalent()->latest()->get();
        $editing = $request->filled('edit') ? $agency->agencyTalent()->findOrFail($request->integer('edit')) : null;
        return view('agency.roster', compact('talents', 'editing'));
    }

    public function storeTalent(Request $request)
    {
        $agency = $this->agency($request);
        $data = $this->talentData($request);
        DB::transaction(function () use ($agency, $data) {
            $agency = $agency->newQuery()->lockForUpdate()->findOrFail($agency->id);
            $agency->agencyTalent()->create($data + ['status' => 'pending']);
        });
        return to_route('agency.roster')->with('status', 'Talent added to your roster and submitted for staff review.');
    }

    public function updateTalent(Request $request, AgencyTalent $talent)
    {
        $agency = $this->agency($request);
        abort_unless($talent->agency_user_id === $agency->id, 403);
        $data = $this->talentData($request);
        DB::transaction(function () use ($agency, $talent, $data) {
            $agency->newQuery()->lockForUpdate()->findOrFail($agency->id);
            $talent = AgencyTalent::whereKey($talent->id)->lockForUpdate()->firstOrFail();
            $this->guardTalentCommitment($talent);
            $talent->update($data + ['status' => 'pending']);
        });
        return to_route('agency.roster')->with('status', 'Talent updated. Staff will review the changes before matching.');
    }

    public function deleteTalent(Request $request, AgencyTalent $talent)
    {
        $agency = $this->agency($request);
        abort_unless($talent->agency_user_id === $agency->id, 403);
        DB::transaction(function () use ($agency, $talent) {
            $agency->newQuery()->lockForUpdate()->findOrFail($agency->id);
            $talent = AgencyTalent::whereKey($talent->id)->lockForUpdate()->firstOrFail();
            $this->guardTalentCommitment($talent);
            $talent->delete();
        });
        return to_route('agency.roster')->with('status', 'Talent removed from your active roster. Campaign history was retained.');
    }

    public function opportunities(Request $request)
    {
        $agency = $this->agency($request);
        $offers = Invitation::where('agency_user_id', $agency->id)->latest()->get()->map(fn (Invitation $invitation) => $invitation->forRecipient($agency));
        $talents = $agency->agencyTalent()->where('status', 'approved')->orderBy('public_name')->get();
        return view('agency.opportunities', compact('offers', 'talents'));
    }

    private function talentData(Request $request): array
    {
        $this->normalizeLists($request, ['audience_geos']);
        $data = $request->validate([
            'public_name' => ['required', 'string', 'min:2', 'max:120'], 'country' => ['required', 'string', 'max:80'],
            'language' => ['required', 'string', 'max:80'], 'platform' => ['required', 'in:Twitch,Kick'],
            'twitch_url' => ['nullable', 'required_without:kick_url', 'string', 'max:255', new StreamingChannelUrl('Twitch')],
            'kick_url' => ['nullable', 'required_without:twitch_url', 'string', 'max:255', new StreamingChannelUrl('Kick')],
            'followers' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'average_viewers' => ['required', 'integer', 'min:0', 'max:100000000'],
            'audience_geos' => ['required', 'array', 'min:1', 'max:30'], 'audience_geos.*' => ['required', 'string', 'max:80', 'distinct'],
            'rate' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'availability' => ['required', 'in:Open,Opening soon,Unavailable'],
        ]);
        $data['twitch_url'] = StreamingChannelUrl::canonicalize($data['twitch_url'] ?? null, 'Twitch');
        $data['kick_url'] = StreamingChannelUrl::canonicalize($data['kick_url'] ?? null, 'Kick');
        if ($data['platform'] === 'Twitch' && ! $data['twitch_url']) $data['platform'] = 'Kick';
        if ($data['platform'] === 'Kick' && ! $data['kick_url']) $data['platform'] = 'Twitch';
        return $data;
    }

    private function guardTalentCommitment(AgencyTalent $talent): void
    {
        if ($talent->isCommitted()) throw ValidationException::withMessages(['talent' => 'This talent is part of a selected campaign in contracts or live delivery. Ask All In Streams before changing the roster.']);
    }

    private function normalizeLists(Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            if (is_string($request->input($field))) $request->merge([$field => array_values(array_filter(array_map('trim', explode(',', $request->input($field)))))]);
        }
    }
}
