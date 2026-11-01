<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Brief;
use App\Models\CreatorProfile;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CampaignWorkflow
{
    public function transition(Brief $brief, string $status, User $actor): Brief
    {
        $this->admin($actor);
        return DB::transaction(function () use ($brief, $status, $actor) {
            $brief = Brief::query()->lockForUpdate()->findOrFail($brief->id);
            $allowed = [
                'submitted' => ['review', 'matching', 'changes_requested', 'declined'],
                'review' => ['matching', 'changes_requested', 'declined'],
                'changes_requested' => ['review', 'matching', 'declined'],
                'matching' => ['proposal', 'changes_requested', 'declined'],
                'proposal' => ['contracts', 'matching', 'declined'],
                'contracts' => ['live', 'proposal'],
                'live' => ['completed'],
            ];
            if (! in_array($status, $allowed[$brief->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'This campaign cannot move from '.$brief->status.' to '.$status.'.']);
            }
            $interested = $brief->invitations()->where('status', 'interested')->get();
            if (in_array($status, ['proposal', 'contracts', 'live'], true) && $interested->isEmpty()) {
                throw ValidationException::withMessages(['status' => 'At least one creator must accept the invitation before preparing a proposal.']);
            }
            if (in_array($status, ['proposal', 'contracts', 'live'], true) && $interested->contains(fn (Invitation $invitation) => $invitation->creatorProfile->status !== 'approved')) {
                throw ValidationException::withMessages(['status' => 'Review and approve every participating creator profile before advancing this campaign.']);
            }
            if ($status === 'live') {
                $signedDocuments = $brief->documents()->where('signature_status', 'signed')->whereNotNull('file_path')->get()
                    ->filter(fn ($document) => Storage::disk('local')->exists($document->file_path));
                $businessSigned = $signedDocuments->contains('audience', 'business');
                $creatorsSigned = $interested->every(fn (Invitation $invitation) => $signedDocuments->contains(fn ($document) => $document->audience === 'creator' && $document->creator_profile_id === $invitation->creator_profile_id));
                if (! $brief->payment_ready || ! $businessSigned || ! $creatorsSigned) {
                    throw ValidationException::withMessages(['status' => 'Launch requires payment readiness and signed agreements for the business and every participating creator.']);
                }
            }
            $brief->update(['status' => $status]);
            $messages = [
                'review' => ['Your brief is being reviewed', 'Your account manager is reviewing the campaign requirements.'],
                'matching' => ['Creator matching has started', 'Your brief is approved. Our team is finding creators for your audience and markets.'],
                'proposal' => ['Your creator proposal is ready', 'Review the proposed creators in your private shortlist. All In Streams will coordinate introductions.'],
                'contracts' => ['Agreements are being prepared', 'Our team is coordinating separate agreements and payment readiness before launch.'],
                'live' => ['Your campaign is live', 'Agreements and payment readiness are confirmed. Campaign delivery is underway.'],
                'completed' => ['Campaign completed', 'Campaign delivery is complete. Your account manager will coordinate reporting and settlement.'],
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
            $brief = Brief::query()->lockForUpdate()->findOrFail($brief->id);
            if ($brief->status !== 'matching') {
                throw ValidationException::withMessages(['brief_id' => 'Creators can be invited only after the brief is approved for matching.']);
            }
            if ($creator->status !== 'approved') {
                throw ValidationException::withMessages(['creator_profile_id' => 'Review and approve this creator before sending an invitation.']);
            }
            if ($brief->invitations()->where('creator_profile_id', $creator->id)->exists()) {
                throw ValidationException::withMessages(['creator_profile_id' => 'This creator has already been invited to this brief.']);
            }
            $data = validator($terms, [
                'compensation' => ['sometimes', 'numeric', 'min:0', 'max:999999999'],
                'deliverables' => ['sometimes', 'required', 'string', 'max:5000'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ])->validate();
            $invitation = $brief->invitations()->create([
                'creator_profile_id' => $creator->id, 'status' => 'pending',
                'compensation' => $data['compensation'] ?? $creator->rate,
                'deliverables' => $data['deliverables'] ?? $brief->deliverables,
                'notes' => $data['notes'] ?? null,
            ]);
            Activity::create(['brief_id' => $brief->id, 'user_id' => $actor->id, 'title' => 'Creator invitation sent', 'body' => $creator->code.' has been invited to the campaign.', 'audience' => 'admin']);
            return $invitation;
        });
    }

    public function introduce(Invitation $invitation, User $actor): Invitation
    {
        $this->admin($actor);
        return DB::transaction(function () use ($invitation, $actor) {
            $invitation = Invitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if ($invitation->status !== 'interested') {
                throw ValidationException::withMessages(['status' => 'The creator must express interest before an introduction can be authorized.']);
            }
            if ($invitation->creatorProfile->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Review and approve this creator profile before authorizing the introduction.']);
            }
            if (! $invitation->introduced_at) {
                $invitation->update(['introduced_at' => now()]);
                Activity::create(['brief_id' => $invitation->brief_id, 'user_id' => $actor->id, 'title' => 'Creator introduction authorized', 'body' => 'Your account manager has released the profile for '.$invitation->creatorProfile->code.'.', 'audience' => 'business']);
            }
            return $invitation;
        });
    }

    public function respond(Invitation $invitation, User $actor, string $status, ?string $notes = null): Invitation
    {
        abort_unless($actor->role === 'creator' && $invitation->creatorProfile->user_id === $actor->id, 403);
        return DB::transaction(function () use ($invitation, $actor, $status, $notes) {
            $invitation = Invitation::query()->with('brief')->lockForUpdate()->findOrFail($invitation->id);
            if ($invitation->status !== 'pending' || ! in_array($invitation->brief->status, ['matching', 'proposal'], true)) {
                throw ValidationException::withMessages(['status' => 'This invitation is no longer awaiting a response.']);
            }
            validator(['status' => $status, 'notes' => $notes], ['status' => ['required', 'in:interested,changes_requested,declined'], 'notes' => ['nullable', 'string', 'max:3000']])->validate();
            $invitation->update(['status' => $status, 'response_notes' => $notes]);
            Activity::create(['brief_id' => $invitation->brief_id, 'user_id' => $actor->id, 'title' => 'Creator responded', 'body' => $invitation->creatorProfile->code.': '.str_replace('_', ' ', $status).($notes ? '. '.$notes : ''), 'audience' => 'admin']);
            return $invitation;
        });
    }

    private function admin(User $actor): void { abort_unless($actor->role === 'admin', 403); }
}
