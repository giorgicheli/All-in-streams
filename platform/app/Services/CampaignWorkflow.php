<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\AgencyProfile;
use App\Models\AgencyTalent;
use App\Models\Brief;
use App\Models\CreatorProfile;
use App\Models\DeliverySubmission;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CampaignWorkflow
{
    public function transition(Brief $brief, string $status, User $actor): Brief
    {
        $this->admin($actor);
        return DB::transaction(function () use ($brief, $status, $actor) {
            $brief = Brief::query()->lockForUpdate()->findOrFail($brief->id);
            $allowed = ['submitted' => ['review', 'matching', 'changes_requested', 'declined'], 'review' => ['matching', 'changes_requested', 'declined'], 'changes_requested' => ['review', 'matching', 'declined'], 'matching' => ['proposal', 'changes_requested', 'declined'], 'proposal' => ['contracts', 'matching', 'declined'], 'contracts' => ['live', 'proposal'], 'live' => ['completed']];
            $this->check(in_array($status, $allowed[$brief->status] ?? [], true), 'status', 'This campaign cannot move from '.$brief->status.' to '.$status.'.');
            $interested = $brief->invitations()->where('status', 'interested')->where('selection_status', '!=', 'not_selected')->get();
            if ($status === 'proposal') {
                $this->check($interested->isNotEmpty(), 'status', 'At least one provider must accept before publishing a proposal.');
                foreach ($interested as $invitation) {
                    $this->lockProvider($invitation);
                    $this->acceptedProvider($invitation);
                }
            }
            $selected = $brief->invitations()->where('selection_status', 'selected')->get();
            if (in_array($status, ['contracts', 'live', 'completed'], true)) {
                $this->check($selected->count() === 1, 'status', 'Approve exactly one creator or agency before proceeding.');
                $invitation = $selected->sole();
                $this->lockProvider($invitation);
                $this->acceptedProvider($invitation);
                $this->withinBudget($brief, $invitation->compensation);
            }
            if ($status === 'live') {
                $documents = $brief->documents()->where('signature_status', 'signed')->whereNotNull('file_path')->get()->filter(fn ($document) => Storage::disk('local')->exists($document->file_path));
                $businessSigned = $documents->contains('audience', 'business');
                $providerSigned = $documents->contains(fn ($document) => $invitation->agency_user_id
                    ? $document->audience === 'agency' && $document->agency_user_id === $invitation->agency_user_id
                    : $document->audience === 'creator' && $document->creator_profile_id === $invitation->creator_profile_id);
                $this->check($brief->payment_ready && $businessSigned && $providerSigned, 'status', 'Launch requires payment readiness and separate signed agreements for the business and the selected provider. Uploading a document does not mark it signed.');
            }
            if ($status === 'completed') {
                $this->check(DeliverySubmission::where('invitation_id', $invitation->id)->where('status', 'approved')->exists(), 'status', 'Review and approve the selected provider\'s delivery evidence before completing the campaign.');
            }
            $brief->update(['status' => $status]);
            $messages = [
                'review' => ['Your brief is being reviewed', 'Your account manager is reviewing the campaign requirements.'],
                'matching' => ['Creator matching has started', 'Your brief is approved. Our team is finding creators and agencies for your audience and markets.'],
                'proposal' => ['Your creator proposal is ready', 'Review accepted provider candidates in your private shortlist. All In Streams makes the final provider selection.'],
                'contracts' => ['Agreements are being prepared', 'One provider is approved. Our team is coordinating separate agreements and payment readiness before launch.'],
                'live' => ['Your campaign is live', 'Agreements and payment readiness are confirmed. Campaign delivery is underway.'],
                'completed' => ['Campaign completed', 'Delivery evidence is approved. Your account manager will coordinate final reporting and settlement.'],
                'changes_requested' => ['We need a little more information', 'Your account manager will contact you to clarify your brief before matching.'],
                'declined' => ['Brief review completed', 'This brief cannot proceed at this time. Contact All In Streams to discuss the next steps.'],
            ];
            [$title, $body] = $messages[$status];
            Activity::create(['brief_id' => $brief->id, 'user_id' => $actor->id, 'title' => $title, 'body' => $body, 'audience' => 'business']);
            return $brief;
        });
    }

    public function invite(Brief $brief, CreatorProfile $creator, User $actor, array $terms = []): Invitation
    {
        $this->admin($actor);
        return DB::transaction(function () use ($brief, $creator, $actor, $terms) {
            $brief = Brief::lockForUpdate()->findOrFail($brief->id);
            $creator = CreatorProfile::withTrashed()->lockForUpdate()->findOrFail($creator->id);
            $this->canInvite($brief, 'creator');
            $this->check(! $creator->trashed() && $creator->status === 'approved', 'creator_profile_id', 'Review and approve this creator before sending an invitation.');
            $this->check(! $brief->invitations()->where('creator_profile_id', $creator->id)->exists(), 'creator_profile_id', 'This creator already has an offer for this campaign. Revise that offer instead.');
            $data = $this->offerTerms($brief, $terms, ['compensation' => $creator->rate]);
            $invitation = $brief->invitations()->create($data + ['creator_profile_id' => $creator->id, 'status' => 'pending', 'selection_status' => 'unselected', 'offer_version' => 1]);
            $this->event($invitation, $actor, 'offered', $invitation->compensation, $invitation->cpa_rate, $invitation->notes);
            $this->adminActivity($invitation, $actor, 'Provider invitation sent', $creator->code.' has been invited.');
            return $invitation;
        });
    }

    public function inviteAgency(Brief $brief, User $agency, User $actor, array $terms = []): Invitation
    {
        $this->admin($actor);
        return DB::transaction(function () use ($brief, $agency, $actor, $terms) {
            $brief = Brief::lockForUpdate()->findOrFail($brief->id);
            $agency = User::lockForUpdate()->findOrFail($agency->id);
            $this->canInvite($brief, 'agency');
            $this->check($brief->user_id !== $agency->id, 'agency_user_id', 'An agency cannot be proposed as the provider for its own client request.');
            $profile = AgencyProfile::where('user_id', $agency->id)->lockForUpdate()->first();
            $this->check($agency->role === 'agency' && $profile?->status === 'approved' && in_array('supply', $profile->services ?? [], true), 'agency_user_id', 'Approve this agency for supplying roster talent before inviting it.');
            $this->check(AgencyTalent::where('agency_user_id', $agency->id)->where('status', 'approved')->exists(), 'agency_user_id', 'The agency needs at least one approved roster member.');
            $this->check(! $brief->invitations()->where('agency_user_id', $agency->id)->exists(), 'agency_user_id', 'This agency already has an offer for this campaign. Revise that offer instead.');
            $data = $this->offerTerms($brief, $terms, ['compensation' => 0]);
            $invitation = $brief->invitations()->create($data + ['agency_user_id' => $agency->id, 'status' => 'pending', 'selection_status' => 'unselected', 'offer_version' => 1]);
            $this->event($invitation, $actor, 'offered', $invitation->compensation, $invitation->cpa_rate, $invitation->notes);
            $this->adminActivity($invitation, $actor, 'Agency invitation sent', $invitation->recipient_code.' has been invited.');
            return $invitation;
        });
    }

    public function respond(Invitation $invitation, User $actor, string $status, ?string $notes = null, int|float|string|null $counterAmount = null, int|float|string|null $counterCpa = null, ?int $offerVersion = null, array $talentIds = []): Invitation
    {
        abort_unless($invitation->ownedBy($actor), 403);
        return DB::transaction(function () use ($invitation, $actor, $status, $notes, $counterAmount, $counterCpa, $offerVersion, $talentIds) {
            $invitation = $this->lockedInvitation($invitation);
            abort_unless($invitation->ownedBy($actor), 403);
            $this->lockProvider($invitation, $talentIds);
            $this->check($invitation->status === 'pending' && $invitation->selection_status !== 'not_selected' && in_array($invitation->brief->status, ['matching', 'proposal'], true), 'status', 'This invitation is no longer awaiting a response.');
            $this->check($offerVersion === null || $offerVersion === $invitation->offer_version, 'offer_version', 'The offer changed. Review the latest terms before responding.');
            $data = validator(['status' => $status, 'notes' => $notes, 'counter_amount' => $counterAmount, 'counter_cpa_rate' => $counterCpa, 'talent_ids' => $talentIds], [
                'status' => ['required', 'in:interested,changes_requested,declined'],
                'notes' => ['required_if:status,changes_requested', 'nullable', 'string', 'max:3000'],
                'counter_amount' => ['prohibited_unless:status,changes_requested', 'nullable', 'numeric', 'min:0', 'max:999999999'],
                'counter_cpa_rate' => ['prohibited_unless:status,changes_requested', 'nullable', 'numeric', 'min:0', 'max:999999999'],
                'talent_ids' => ['array', 'max:30'], 'talent_ids.*' => ['integer', 'distinct'],
            ])->validate();
            $this->check($status === 'declined' || $invitation->recipientApproved(), 'status', 'Your provider profile must be approved and active before accepting or negotiating.');
            $ids = $this->talentIds($invitation, $talentIds, $status === 'interested');
            $invitation->update(['status' => $status, 'response_notes' => $data['notes'], 'counter_amount' => $counterAmount, 'counter_cpa_rate' => $counterCpa, 'proposed_talent_ids' => $ids, 'accepted_offer_version' => $status === 'interested' ? $invitation->offer_version : null]);
            $this->event($invitation, $actor, $status, $status === 'changes_requested' ? $counterAmount : $invitation->compensation, $status === 'changes_requested' ? $counterCpa : $invitation->cpa_rate, $notes);
            $this->adminActivity($invitation, $actor, 'Provider responded', $invitation->recipient_code.': '.str_replace('_', ' ', $status).'. Review the private negotiation thread.');
            return $invitation->refresh();
        });
    }

    public function reviseOffer(Invitation $invitation, User $actor, array $terms): Invitation
    {
        $this->admin($actor);
        return DB::transaction(function () use ($invitation, $actor, $terms) {
            $invitation = $this->lockedInvitation($invitation);
            $this->lockProvider($invitation);
            $this->check(in_array($invitation->brief->status, ['matching', 'proposal'], true) && $invitation->selection_status !== 'selected', 'status', 'Only an unselected matching or proposal offer can be revised.');
            $this->check(! $invitation->brief->invitations()->where('selection_status', 'selected')->exists(), 'status', 'A final provider is already selected. Change the final selection before reopening other offers.');
            $this->check($invitation->recipientApproved(), 'status', 'Review and approve this provider before revising an offer.');
            $defaults = $invitation->only(['compensation', 'cpa_rate', 'opportunity_title', 'deliverables', 'requirements', 'notes']) + ($invitation->offer_details ?? []);
            $data = $this->offerTerms($invitation->brief, $terms, $defaults);
            $invitation->update($data + ['status' => 'pending', 'offer_version' => $invitation->offer_version + 1, 'accepted_offer_version' => null, 'selection_status' => 'unselected', 'counter_amount' => null, 'counter_cpa_rate' => null, 'response_notes' => null]);
            $this->event($invitation, $actor, 'revised', $invitation->compensation, $invitation->cpa_rate, $invitation->notes);
            return $invitation->refresh();
        });
    }

    public function acceptCounter(Invitation $invitation, User $actor): Invitation
    {
        $this->admin($actor);
        return DB::transaction(function () use ($invitation, $actor) {
            $invitation = $this->lockedInvitation($invitation);
            $this->lockProvider($invitation);
            $this->check($invitation->status === 'changes_requested' && $invitation->selection_status !== 'selected' && in_array($invitation->brief->status, ['matching', 'proposal'], true), 'status', 'There is no open counter-offer to accept.');
            $this->check(! $invitation->brief->invitations()->where('selection_status', 'selected')->exists(), 'status', 'A final provider is already selected. Change the final selection before accepting another counter-offer.');
            $this->check($invitation->counter_amount !== null || $invitation->counter_cpa_rate !== null, 'status', 'For a schedule or scope change, revise the offer and ask the provider to confirm the new terms.');
            $this->check($invitation->recipientApproved(), 'status', 'Review and approve this provider first.');
            $this->check($invitation->currency === $invitation->brief->currency, 'currency', 'The campaign currency changed. Revise the offer and obtain a fresh confirmation.');
            $this->talentIds($invitation, $invitation->proposed_talent_ids ?? [], true);
            $amount = $invitation->counter_amount ?? $invitation->compensation;
            $this->withinBudget($invitation->brief, $amount);
            $version = $invitation->offer_version + 1;
            $invitation->update(['compensation' => $amount, 'cpa_rate' => $invitation->counter_cpa_rate ?? $invitation->cpa_rate, 'status' => 'interested', 'selection_status' => 'unselected', 'offer_version' => $version, 'accepted_offer_version' => $version]);
            $this->event($invitation, $actor, 'counter_accepted', $invitation->compensation, $invitation->cpa_rate, 'All In Streams accepted the provider\'s proposed compensation.');
            return $invitation->refresh();
        });
    }

    public function selectProvider(Invitation $invitation, User $actor): Invitation
    {
        $this->admin($actor);
        return DB::transaction(function () use ($invitation, $actor) {
            $invitation = $this->lockedInvitation($invitation);
            $this->lockProvider($invitation);
            $this->check(in_array($invitation->brief->status, ['matching', 'proposal'], true), 'status', 'Final selection can change only before contracts are prepared.');
            $this->acceptedProvider($invitation);
            $this->talentIds($invitation, $invitation->proposed_talent_ids ?? [], true);
            $this->withinBudget($invitation->brief, $invitation->compensation);
            if ($invitation->selection_status === 'selected') return $invitation;
            $invitation->brief->invitations()->whereKeyNot($invitation->id)->update(['selection_status' => 'not_selected', 'selected_at' => null, 'selected_by' => null]);
            $invitation->update(['selection_status' => 'selected', 'selected_at' => now(), 'selected_by' => $actor->id, 'introduced_at' => $invitation->introduced_at ?? now()]);
            $this->event($invitation, $actor, 'selected', $invitation->compensation, $invitation->cpa_rate, 'All In Streams approved this provider for the campaign.');
            Activity::create(['brief_id' => $invitation->brief_id, 'user_id' => $actor->id, 'title' => 'Final provider approved', 'body' => 'Your account manager approved '.$invitation->recipient_code.' and authorized the introduction. Separate agreements are next.', 'audience' => 'business']);
            return $invitation->refresh();
        });
    }

    public function introduce(Invitation $invitation, User $actor): Invitation
    {
        $this->admin($actor);
        return DB::transaction(function () use ($invitation, $actor) {
            $invitation = $this->lockedInvitation($invitation);
            $this->lockProvider($invitation);
            $this->acceptedProvider($invitation);
            $this->check($invitation->selection_status !== 'not_selected', 'status', 'This provider is not in the current proposal.');
            if (! $invitation->introduced_at) {
                $invitation->update(['introduced_at' => now()]);
                Activity::create(['brief_id' => $invitation->brief_id, 'user_id' => $actor->id, 'title' => 'Creator introduction authorized', 'body' => 'Your account manager has released the profile for '.$invitation->recipient_code.'.', 'audience' => 'business']);
            }
            return $invitation;
        });
    }

    public function deleteCreator(CreatorProfile $creator, User $actor): void
    {
        $this->admin($actor);
        DB::transaction(function () use ($creator) {
            $creator = CreatorProfile::withTrashed()->lockForUpdate()->findOrFail($creator->id);
            $this->check(! $creator->invitations()->where('selection_status', 'selected')->whereHas('brief', fn ($brief) => $brief->whereIn('status', ['contracts', 'live']))->exists(), 'creator_profile_id', 'This creator is selected for an active contract or live campaign. Complete that work before archiving the profile.');
            $creator->invitations()->whereHas('brief', fn ($brief) => $brief->whereNotIn('status', ['contracts', 'live', 'completed']))->update(['selection_status' => 'not_selected', 'selected_at' => null, 'selected_by' => null]);
            $creator->delete();
        });
    }

    private function canInvite(Brief $brief, string $type): void
    {
        $this->check($brief->status === 'matching', 'brief_id', 'Review and approve the brief for matching before inviting providers.');
        $this->check(! $brief->invitations()->where('selection_status', 'selected')->exists(), 'brief_id', 'A final provider is already selected for this campaign.');
        $this->check(in_array($brief->provider_type ?: 'either', ['either', $type], true), 'brief_id', 'This brief requests a different provider type.');
    }

    private function offerTerms(Brief $brief, array $terms, array $defaults = []): array
    {
        $details = Invitation::detailsFromBrief($brief);
        $payload = $terms + $defaults + ['compensation' => 0, 'cpa_rate' => null, 'opportunity_title' => $brief->vertical.' creator partnership', 'deliverables' => 'Campaign deliverables coordinated through All In Streams.', 'requirements' => null, 'notes' => null, 'start_date' => $details['start_date'], 'end_date' => $details['end_date'], 'currency' => $brief->currency];
        foreach (['start_date', 'end_date'] as $date) {
            if ($payload[$date] === null || $payload[$date] === '') $payload[$date] = $defaults[$date] ?? $details[$date];
        }
        $data = validator($payload, [
            'compensation' => ['required', 'numeric', 'min:0', 'max:999999999'], 'cpa_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'opportunity_title' => ['required', 'string', 'max:180'], 'deliverables' => ['required', 'string', 'max:5000'], 'requirements' => ['nullable', 'string', 'max:5000'], 'notes' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'], 'currency' => ['required', Rule::in([$brief->currency])],
        ])->validate();
        $this->withinBudget($brief, $data['compensation']);
        $details['start_date'] = \Illuminate\Support\Carbon::parse($data['start_date'])->toDateString();
        $details['end_date'] = \Illuminate\Support\Carbon::parse($data['end_date'])->toDateString();
        unset($data['start_date'], $data['end_date']);
        return $data + ['offer_details' => $details];
    }

    private function acceptedProvider(Invitation $invitation): void
    {
        $this->check($invitation->status === 'interested' && $invitation->accepted_offer_version === $invitation->offer_version, 'status', 'The provider must accept the current offer before it can proceed.');
        $this->check($invitation->currency === $invitation->brief->currency, 'currency', 'The campaign currency changed. Revise the offer and obtain a fresh confirmation.');
        $this->check($invitation->recipientApproved(), 'status', 'Review and approve the active provider profile and roster first.');
    }

    private function talentIds(Invitation $invitation, array $ids, bool $required): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (! $invitation->agency_user_id) {
            $this->check(! $ids, 'talent_ids', 'Independent creators cannot attach agency roster members.');
            return [];
        }
        $this->check(! $required || count($ids) > 0, 'talent_ids', 'Choose at least one approved member of your agency roster.');
        $count = AgencyTalent::where('agency_user_id', $invitation->agency_user_id)->where('status', 'approved')->whereIn('id', $ids)->lockForUpdate()->get()->count();
        $this->check($count === count($ids), 'talent_ids', 'Choose only active, approved talent belonging to this agency.');
        return $ids;
    }

    private function lockProvider(Invitation $invitation, array $talentIds = []): void
    {
        if ($invitation->agency_user_id) {
            $agency = User::whereKey($invitation->agency_user_id)->lockForUpdate()->firstOrFail();
            $agency->setRelation('agencyProfile', AgencyProfile::where('user_id', $invitation->agency_user_id)->lockForUpdate()->first());
            $agency->setRelation('agencyTalent', AgencyTalent::where('agency_user_id', $invitation->agency_user_id)->lockForUpdate()->get());
            $invitation->setRelation('agency', $agency);
        } else {
            $creator = CreatorProfile::withTrashed()->whereKey($invitation->creator_profile_id)->lockForUpdate()->firstOrFail();
            $invitation->setRelation('creatorProfile', $creator);
        }
    }

    private function lockedInvitation(Invitation $invitation): Invitation
    {
        Brief::whereKey($invitation->brief_id)->lockForUpdate()->firstOrFail();
        return Invitation::lockForUpdate()->findOrFail($invitation->id);
    }

    private function withinBudget(Brief $brief, int|float|string $amount): void
    {
        $this->check(round((float) $amount, 2) <= round((float) $brief->budget, 2), 'compensation', 'The fixed provider fee exceeds the available client campaign budget. Review the brief budget before accepting it.');
    }

    private function event(Invitation $invitation, User $actor, string $kind, int|float|string|null $amount, int|float|string|null $cpa, ?string $notes): void
    {
        $invitation->events()->create(['user_id' => $actor->id, 'kind' => $kind, 'amount' => $amount, 'cpa_rate' => $cpa, 'notes' => $notes]);
    }

    private function adminActivity(Invitation $invitation, User $actor, string $title, string $body): void
    {
        Activity::create(['brief_id' => $invitation->brief_id, 'user_id' => $actor->id, 'title' => $title, 'body' => $body, 'audience' => 'admin']);
    }

    private function check(bool $condition, string $field, string $message): void
    {
        if (! $condition) throw ValidationException::withMessages([$field => $message]);
    }

    private function admin(User $actor): void { abort_unless($actor->role === 'admin', 403); }
}
