<?php

namespace App\Services;

use App\Models\Brief;
use App\Models\DeliverySubmission;
use App\Models\Invitation;
use App\Models\PaymentMilestone;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProviderDelivery
{
    public function submit(Invitation $invitation, User $actor, array $input, ?UploadedFile $file = null): DeliverySubmission
    {
        abort_unless($invitation->ownedBy($actor), 403);
        $data = validator(array_merge($input, ['evidence' => $file]), [
            'submission_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'content_url' => ['required', 'url:http,https', 'max:2048'],
            'delivered_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reported_impressions' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'reported_clicks' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'reported_conversions' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,gif', 'max:10240'],
        ])->validate();
        unset($data['evidence']);
        $newPath = null;
        $oldPath = null;

        try {
            $submission = DB::transaction(function () use ($invitation, $actor, $data, $file, &$newPath, &$oldPath) {
                $invitation = $this->lockedInvitation($invitation);
                abort_unless($invitation->ownedBy($actor), 403);
                $this->selected($invitation);
                if ($invitation->brief->status !== 'live') {
                    throw ValidationException::withMessages(['invitation' => 'Delivery evidence can be submitted only while your selected campaign is live.']);
                }

                $submission = null;
                if (! empty($data['submission_id'])) {
                    $submission = DeliverySubmission::query()->where('invitation_id', $invitation->id)->lockForUpdate()->findOrFail($data['submission_id']);
                    if ($submission->status !== 'changes_requested') {
                        throw ValidationException::withMessages(['submission_id' => 'Only evidence returned for changes can be resubmitted.']);
                    }
                    $oldPath = $submission->file_path;
                }
                $values = array_diff_key($data, ['submission_id' => true]);
                $values = array_merge($values, [
                    'invitation_id' => $invitation->id,
                    'user_id' => $actor->id,
                    'status' => 'submitted',
                    'review_notes' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);
                if ($file) {
                    $newPath = $file->store('provider-deliveries', 'local');
                    if (! $newPath) {
                        throw ValidationException::withMessages(['evidence' => 'The evidence could not be stored. Please try again.']);
                    }
                    $values['file_path'] = $newPath;
                }
                if ($submission) {
                    $submission->update($values);
                    return $submission;
                }

                return DeliverySubmission::create($values);
            });
        } catch (Throwable $exception) {
            if ($newPath) { Storage::disk('local')->delete($newPath); }
            throw $exception;
        }
        if ($newPath && $oldPath) { Storage::disk('local')->delete($oldPath); }

        return $submission;
    }

    public function review(DeliverySubmission $submission, User $actor, string $status, ?string $notes = null): DeliverySubmission
    {
        $this->admin($actor);
        validator(['status' => $status, 'review_notes' => $notes], [
            'status' => ['required', 'in:approved,changes_requested'],
            'review_notes' => ['nullable', 'required_if:status,changes_requested', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($submission, $actor, $status, $notes) {
            $invitation = $this->lockedInvitation($submission->invitation);
            $submission = DeliverySubmission::query()->lockForUpdate()->findOrFail($submission->id);
            $submission->setRelation('invitation', $invitation);
            $this->selected($invitation);
            if (! in_array($invitation->brief->status, ['live', 'completed'], true)) {
                throw ValidationException::withMessages(['status' => 'Review is available only for live or completed campaigns.']);
            }
            if ($submission->status !== 'submitted' && ! ($submission->status === 'approved' && $status === 'changes_requested' && $invitation->brief->status === 'live')) {
                throw ValidationException::withMessages(['status' => 'Review submitted evidence, or reopen approved evidence while the campaign is live.']);
            }
            $submission->update(['status' => $status, 'review_notes' => $notes, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);

            return $submission;
        });
    }

    public function createMilestone(Invitation $invitation, User $actor, array $input): PaymentMilestone
    {
        $this->admin($actor);
        $data = validator($input, [
            'label' => ['required', 'string', 'max:200'],
            'kind' => ['required', 'in:fixed_fee,validated_cpa,other'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
        ])->validate();

        return DB::transaction(function () use ($invitation, $data) {
            $invitation = $this->lockedInvitation($invitation);
            $this->payable($invitation);

            return PaymentMilestone::create(array_merge($data, ['invitation_id' => $invitation->id, 'currency' => $invitation->currency, 'status' => 'pending']));
        });
    }

    public function approveMilestone(PaymentMilestone $milestone, User $actor): PaymentMilestone
    {
        $this->admin($actor);

        return DB::transaction(function () use ($milestone, $actor) {
            $invitation = $this->lockedInvitation($milestone->invitation);
            $milestone = PaymentMilestone::query()->lockForUpdate()->findOrFail($milestone->id);
            $milestone->setRelation('invitation', $invitation);
            $this->payable($invitation);
            if ($milestone->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only a pending milestone can be approved.']);
            }
            $milestone->update(['status' => 'approved', 'approved_by' => $actor->id]);

            return $milestone;
        });
    }

    public function markPaid(PaymentMilestone $milestone, User $actor, array $input): PaymentMilestone
    {
        $this->admin($actor);
        $data = validator($input, ['paid_at' => ['required', 'date', 'before_or_equal:now'], 'reference' => ['nullable', 'string', 'max:255']])->validate();

        return DB::transaction(function () use ($milestone, $data) {
            $invitation = $this->lockedInvitation($milestone->invitation);
            $milestone = PaymentMilestone::query()->lockForUpdate()->findOrFail($milestone->id);
            $milestone->setRelation('invitation', $invitation);
            $this->payable($invitation);
            if ($milestone->status !== 'approved' || ! $milestone->approved_by) {
                throw ValidationException::withMessages(['status' => 'Approve this milestone before recording an external payment.']);
            }
            $milestone->update(array_merge($data, ['status' => 'paid']));

            return $milestone;
        });
    }

    private function lockedInvitation(Invitation $invitation): Invitation
    {
        $brief = Brief::query()->lockForUpdate()->findOrFail($invitation->brief_id);
        $invitation = Invitation::query()->lockForUpdate()->findOrFail($invitation->id);
        abort_unless($invitation->brief_id === $brief->id, 409);

        return $invitation->setRelation('brief', $brief);
    }

    private function selected(Invitation $invitation): void
    {
        if ($invitation->selection_status !== 'selected' || $invitation->status !== 'interested' || $invitation->accepted_offer_version !== $invitation->offer_version) {
            throw ValidationException::withMessages(['invitation' => 'This action requires the selected provider and their accepted current offer.']);
        }
    }

    private function payable(Invitation $invitation): void
    {
        $this->selected($invitation);
        if (! in_array($invitation->brief->status, ['contracts', 'live', 'completed'], true)) {
            throw ValidationException::withMessages(['invitation_id' => 'Payment milestones require a selected provider in contracts, live delivery or completion.']);
        }
    }

    private function admin(User $actor): void { abort_unless($actor->role === 'admin', 403); }
}
