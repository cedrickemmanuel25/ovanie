<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CommercialProspectingMission extends Model
{
    protected $fillable = [
        'commune_id',
        'created_by_id',
        'starts_on',
        'ends_on',
        'status',
        'shop_target',
        'instructions',
        'completed_at',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'completed_at' => 'datetime',
        'shop_target' => 'integer',
    ];

    public function commune()
    {
        return $this->belongsTo(AbidjanCommune::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'commercial_prospecting_mission_members', 'mission_id', 'commercial_id')
            ->withPivot(['assigned_at'])
            ->withTimestamps();
    }

    public function missionQuarters()
    {
        return $this->hasMany(CommercialProspectingMissionQuarter::class, 'mission_id');
    }

    public function prospects()
    {
        return $this->hasMany(CommercialProspect::class, 'mission_id');
    }

    public function visits()
    {
        return $this->hasMany(CommercialProspectingVisit::class, 'mission_id');
    }

    public function scopeForCommercial(Builder $query, int $commercialId): Builder
    {
        return $query->whereHas('members', fn (Builder $q) => $q->where('users.id', $commercialId));
    }

    public function isAssignedTo(int $commercialId): bool
    {
        if ($this->relationLoaded('members')) {
            return $this->members->contains(fn (User $user) => (int) $user->id === $commercialId);
        }

        return $this->members()->whereKey($commercialId)->exists();
    }

    public function effectiveStatus(): string
    {
        if (in_array($this->status, ['completed', 'cancelled'], true)) {
            return $this->status;
        }

        $today = now()->startOfDay();
        if ($this->starts_on && $this->starts_on->startOfDay()->gt($today)) {
            return 'scheduled';
        }
        if ($this->ends_on && $this->ends_on->endOfDay()->lt(now())) {
            return 'overdue';
        }

        return 'in_progress';
    }
}
