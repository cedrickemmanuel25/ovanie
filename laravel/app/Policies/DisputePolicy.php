<?php

namespace App\Policies;

use App\Models\Dispute;
use App\Models\User;

class DisputePolicy
{
    public function before(User $user): ?bool
    {
        return ((bool) $user->is_admin || $user->role === 'admin') ? true : null;
    }

    public function viewAny(User $user): bool { return true; }
    public function viewAll(User $user): bool { return $user->hasStaffPermission('disputes.read'); }
    public function create(User $user): bool { return true; }

    public function view(User $user, Dispute $dispute): bool
    {
        return $this->viewAll($user)
            || (int) $dispute->client_id === (int) $user->id
            || (int) $dispute->vendor_id === (int) $user->id
            || (int) $dispute->shop?->user_id === (int) $user->id;
    }

    public function update(User $user, Dispute $dispute): bool { return $this->view($user, $dispute); }
    public function manage(User $user, Dispute $dispute): bool { return $this->viewAll($user); }
    public function viewInternalNotes(User $user, Dispute $dispute): bool { return $this->viewAll($user); }
    public function delete(User $user, Dispute $dispute): bool { return $this->viewAll($user); }
}
