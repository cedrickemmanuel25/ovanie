<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbidjanCommune;
use App\Models\AbidjanQuarter;
use App\Models\CommercialProspectingMission;
use App\Models\CommercialProspectingMissionQuarter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminProspectingMissionController extends Controller
{
    public function index(Request $request)
    {
        $query = CommercialProspectingMission::query()
            ->with(['commune:id,name', 'members:id,name,first_name,last_name'])
            ->withCount([
                'members',
                'missionQuarters',
                'missionQuarters as completed_quarters_count' => fn (Builder $q) => $q->where('status', 'completed'),
                'prospects',
                'prospects as shops_opened_count' => fn (Builder $q) => $q->whereIn('status', ['shop_opened', 'active_shop']),
                'visits',
            ]);

        if ($request->filled('commune_id')) {
            $query->where('commune_id', $request->integer('commune_id'));
        }
        if ($request->filled('status')) {
            $status = (string) $request->query('status');
            if ($status === 'active') {
                $query->whereNotIn('status', ['completed', 'cancelled'])
                    ->whereDate('starts_on', '<=', now()->toDateString())
                    ->whereDate('ends_on', '>=', now()->toDateString());
            } elseif ($status === 'scheduled') {
                $query->whereNotIn('status', ['completed', 'cancelled'])
                    ->whereDate('starts_on', '>', now()->toDateString());
            } elseif ($status === 'overdue') {
                $query->whereNotIn('status', ['completed', 'cancelled'])
                    ->whereDate('ends_on', '<', now()->toDateString());
            } else {
                $query->where('status', $status);
            }
        }

        $missions = $query->orderByRaw("CASE WHEN status = 'completed' THEN 2 ELSE 1 END")
            ->orderByDesc('starts_on')
            ->paginate(20)
            ->withQueryString();

        $today = now()->toDateString();
        $stats = [
            'active' => CommercialProspectingMission::whereNotIn('status', ['completed', 'cancelled'])
                ->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)->count(),
            'scheduled' => CommercialProspectingMission::whereNotIn('status', ['completed', 'cancelled'])
                ->whereDate('starts_on', '>', $today)->count(),
            'commercials_deployed' => User::where('role', 'commercial')->where('status', 'active')
                ->whereHas('prospectingMissions', function (Builder $q) use ($today) {
                    $q->whereNotIn('commercial_prospecting_missions.status', ['completed', 'cancelled'])
                        ->whereDate('starts_on', '<=', $today)
                        ->whereDate('ends_on', '>=', $today);
                })->count(),
            'shops_opened' => DB::table('commercial_prospects')->whereIn('status', ['shop_opened', 'active_shop'])
                ->whereNotNull('mission_id')->count(),
        ];

        return view('admin.prospecting-missions.index', [
            'missions' => $missions,
            'stats' => $stats,
            'communes' => AbidjanCommune::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('admin.prospecting-missions.create', [
            'communes' => AbidjanCommune::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'commercials' => $this->commercials(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'commune_id' => ['required', 'integer', 'exists:abidjan_communes,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'commercial_ids' => ['required', 'array', 'min:1'],
            'commercial_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'shop_target' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ]);

        $commercialIds = array_values(array_unique(array_map('intval', $data['commercial_ids'])));
        $this->assertCommercials($commercialIds);
        $this->assertNoConflicts((int) $data['commune_id'], $data['starts_on'], $data['ends_on'], $commercialIds);

        $quarterIds = AbidjanQuarter::where('commune_id', (int) $data['commune_id'])
            ->where('is_active', true)
            ->where('type', 'quartier')
            ->orderBy('name')
            ->pluck('id');

        if ($quarterIds->isEmpty()) {
            $quarterIds = AbidjanQuarter::where('commune_id', (int) $data['commune_id'])
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('id');
        }

        if ($quarterIds->isEmpty()) {
            throw ValidationException::withMessages([
                'commune_id' => 'Aucun quartier actif n’est enregistré pour cette commune.',
            ]);
        }

        $mission = DB::transaction(function () use ($request, $data, $commercialIds, $quarterIds) {
            $mission = CommercialProspectingMission::create([
                'commune_id' => (int) $data['commune_id'],
                'created_by_id' => auth('admin')->id(),
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'status' => now()->toDateString() < $data['starts_on'] ? 'scheduled' : 'in_progress',
                'shop_target' => $data['shop_target'] ?? null,
                'instructions' => $data['instructions'] ?? null,
            ]);

            $mission->members()->attach($commercialIds, ['assigned_at' => now()]);
            $mission->missionQuarters()->createMany($quarterIds->map(fn ($id) => [
                'quarter_id' => (int) $id,
                'status' => 'pending',
            ])->all());

            return $mission;
        });

        return redirect()->route('admin.prospecting-missions.show', $mission)
            ->with('success', 'Mission de prospection créée et affectée à l’équipe commerciale.');
    }

    public function show(CommercialProspectingMission $mission)
    {
        $mission->load([
            'commune:id,name',
            'creator:id,name',
            'members:id,name,first_name,last_name,email,phone',
            'missionQuarters.quarter:id,commune_id,name,type',
            'missionQuarters.updatedBy:id,name',
            'prospects' => fn ($q) => $q->with(['quarter:id,name', 'commercial:id,name', 'shop:id,name'])->latest()->limit(30),
        ])->loadCount([
            'prospects',
            'visits',
            'missionQuarters',
            'missionQuarters as completed_quarters_count' => fn (Builder $q) => $q->where('status', 'completed'),
            'missionQuarters as in_progress_quarters_count' => fn (Builder $q) => $q->where('status', 'in_progress'),
            'prospects as interested_count' => fn (Builder $q) => $q->whereIn('status', ['interested', 'follow_up', 'registration_in_progress']),
            'prospects as shops_opened_count' => fn (Builder $q) => $q->whereIn('status', ['shop_opened', 'active_shop']),
        ]);

        return view('admin.prospecting-missions.show', [
            'mission' => $mission,
            'commercials' => $this->commercials(),
        ]);
    }

    public function update(Request $request, CommercialProspectingMission $mission)
    {
        if ($mission->status === 'completed') {
            return back()->with('error', 'Une mission terminée ne peut plus être réaffectée.');
        }

        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'commercial_ids' => ['required', 'array', 'min:1'],
            'commercial_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'shop_target' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ]);

        $commercialIds = array_values(array_unique(array_map('intval', $data['commercial_ids'])));
        $this->assertCommercials($commercialIds);
        $this->assertNoConflicts($mission->commune_id, $data['starts_on'], $data['ends_on'], $commercialIds, $mission->id);

        DB::transaction(function () use ($mission, $data, $commercialIds) {
            $mission->update([
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'status' => now()->toDateString() < $data['starts_on'] ? 'scheduled' : 'in_progress',
                'shop_target' => $data['shop_target'] ?? null,
                'instructions' => $data['instructions'] ?? null,
            ]);
            $mission->members()->syncWithPivotValues($commercialIds, ['assigned_at' => now()]);
        });

        return back()->with('success', 'Affectation de la mission mise à jour.');
    }

    public function complete(CommercialProspectingMission $mission)
    {
        if ($mission->status !== 'completed') {
            $mission->update(['status' => 'completed', 'completed_at' => now()]);
        }

        return back()->with('success', 'Mission clôturée. Les commerciaux peuvent maintenant être affectés à une autre commune.');
    }

    private function commercials()
    {
        return User::where('role', 'commercial')
            ->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereDoesntHave('staffProfile')->orWhereHas('staffProfile', fn (Builder $x) => $x->where('is_active', true));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name', 'email', 'phone']);
    }

    private function assertCommercials(array $commercialIds): void
    {
        $valid = User::whereIn('id', $commercialIds)->where('role', 'commercial')->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $invalid = array_diff($commercialIds, $valid);
        if ($invalid) {
            throw ValidationException::withMessages(['commercial_ids' => 'Un ou plusieurs comptes sélectionnés ne sont pas des commerciaux actifs.']);
        }
    }

    private function assertNoConflicts(int $communeId, string $startsOn, string $endsOn, array $commercialIds, ?int $ignoreMissionId = null): void
    {
        $sameCommune = CommercialProspectingMission::query()
            ->when($ignoreMissionId, fn (Builder $q) => $q->where('commercial_prospecting_missions.id', '!=', $ignoreMissionId))
            ->where('commune_id', $communeId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn)
            ->exists();

        if ($sameCommune) {
            throw ValidationException::withMessages(['commune_id' => 'Une mission de prospection est déjà planifiée sur cette commune pendant cette période. Ajoutez les commerciaux à la même mission au lieu de créer une mission parallèle.']);
        }

        $conflictingNames = User::whereIn('id', $commercialIds)
            ->whereHas('prospectingMissions', function (Builder $q) use ($startsOn, $endsOn, $ignoreMissionId) {
                $q->when($ignoreMissionId, fn (Builder $x) => $x->where('commercial_prospecting_missions.id', '!=', $ignoreMissionId))
                    ->whereNotIn('commercial_prospecting_missions.status', ['completed', 'cancelled'])
                    ->whereDate('starts_on', '<=', $endsOn)
                    ->whereDate('ends_on', '>=', $startsOn);
            })
            ->pluck('name')
            ->filter()
            ->values();

        if ($conflictingNames->isNotEmpty()) {
            throw ValidationException::withMessages([
                'commercial_ids' => 'Ces commerciaux ont déjà une mission sur cette période : '.$conflictingNames->implode(', ').'.',
            ]);
        }
    }
}
