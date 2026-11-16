<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnerVerification
{
    public function verify(User $actor, User $partner): User
    {
        return $this->changeStatus($actor, $partner, 'verified');
    }

    public function reject(User $actor, User $partner): User
    {
        return $this->changeStatus($actor, $partner, 'rejected');
    }

    public function revoke(User $actor, User $partner): User
    {
        return $this->changeStatus($actor, $partner, 'pending');
    }

    private function changeStatus(User $actor, User $partner, string $status): User
    {
        return DB::transaction(function () use ($actor, $partner, $status) {
            abort_unless($actor->fresh()?->role === 'admin', 403);
            $account = User::whereKey($partner->id)->lockForUpdate()->firstOrFail();
            if ($account->role === 'admin') {
                throw ValidationException::withMessages(['partner_verification_status' => 'Administrator accounts do not use partner verification.']);
            }
            if ($account->partner_verification_status === $status) return $account;
            $account->forceFill([
                'partner_verification_status' => $status,
                'partner_verified_at' => $status === 'verified' ? now() : null,
                'partner_verified_by' => $status === 'verified' ? $actor->id : null,
            ])->save();
            return $account;
        });
    }
}