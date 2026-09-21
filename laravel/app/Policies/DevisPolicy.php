<?php

namespace App\Policies;

use App\Models\Devis;
use App\Models\User;

class DevisPolicy
{
    public function before(User $user): ?bool
    {
        return ((bool) $user->is_admin || $user->role === 'admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function viewAll(User $user): bool
    {
        return $user->hasStaffPermission('quotes.read');
    }

    public function view(User $user, Devis $devis): bool
    {
        return $this->viewAll($user) || (int) $devis->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Devis $devis): bool
    {
        return $this->view($user, $devis);
    }

    public function delete(User $user, Devis $devis): bool
    {
        return $this->view($user, $devis);
    }
}
