<?php

namespace App\Policies;

use App\Models\AppelOffre;
use App\Models\User;

class AppelOffrePolicy
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

    public function view(User $user, AppelOffre $appelOffre): bool
    {
        return $this->viewAll($user) || (int) $appelOffre->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AppelOffre $appelOffre): bool
    {
        return $this->view($user, $appelOffre);
    }

    public function delete(User $user, AppelOffre $appelOffre): bool
    {
        return $this->view($user, $appelOffre);
    }
}
