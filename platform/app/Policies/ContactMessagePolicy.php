<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

class ContactMessagePolicy
{
    public function viewAny(User $user): bool { return $user->role === 'admin'; }

    public function view(User $user, ContactMessage $message): bool { return $this->viewAny($user); }

    public function create(User $user): bool { return false; }

    public function update(User $user, ContactMessage $message): bool { return $this->viewAny($user); }

    public function delete(User $user, ContactMessage $message): bool { return $this->viewAny($user); }

    public function deleteAny(User $user): bool { return $this->viewAny($user); }
}
