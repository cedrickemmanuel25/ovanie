<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialProspect;
use App\Models\CommercialProspectingMission;
use App\Models\CommercialProspectingMissionQuarter;
use App\Models\CommercialProspectingVisit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommercialProspectingController extends Controller
{
    public function areas(Request $request)
    {
        $commercialId = (int) $request->user()->id;
        $today = now()->toDateString();

        $currentMission = CommercialProspectingMission::query()
            ->forCommercial($commercialId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->with([
                'commune:id,name',
                'members:id,name,first_name,last_name',
                'missionQuarters' => fn ($q) => $q->with(['quarter:id,commune_id,name,type', 'updatedBy:id,name'])->orderBy('id'),
            ])
            ->withCount([
                'prospects',
                'visits',
                'missionQuarters',
                'missionQuarters as completed_quarters_count' => fn (Builder $q) => $q->where('status', 'completed'),
                'prospects as interested_count' => fn (Builder $q) => $q->whereIn('status', ['interested', 'follow_up', 'registration_in_progress']),
                'prospects as shops_opened_count' => fn (Builder $q) => $q->whereIn('status', ['shop_opened', 'active_shop']),
            ])
            ->orderBy('starts_on')
            ->first();

        $upcomingMission = CommercialProspectingMission::query()
            ->forCommercial($commercialId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('starts_on', '>', $today)
            ->with(['commune:id,name', 'members:id,name'])
            ->orderBy('starts_on')
            ->first();

        $overdueMission = $currentMission ? null : CommercialProspectingMission::query()
            ->forCommercial($commercialId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('ends_on', '<', $today)
            ->with(['commune:id,name', 'members:id,name'])
            ->latest('ends_on')
            ->first();

        $recentMissions = CommercialProspectingMission::query()
            ->forCommercial($commercialId)
            ->where('status', 'completed')
            ->with('commune:id,name')
            ->withCount([
                'prospects',
                'missionQuarters',
                'missionQuarters as completed_quarters_count' => fn (Builder $q) => $q->where('status', 'completed'),
                'prospects as shops_opened_count' => fn (Builder $q) => $q->whereIn('status', ['shop_opened', 'active_shop']),
            ])
            ->latest('completed_at')
            ->limit(5)
            ->get();

        $quarterStats = collect();
        if ($currentMission) {
            $quarterStats = CommercialProspect::where('mission_id', $currentMission->id)
                ->whereNotNull('quarter_id')
                ->selectRaw('quarter_id, COUNT(*) as prospects_count')
                ->selectRaw("SUM(CASE WHEN status IN ('interested','follow_up','registration_in_progress') THEN 1 ELSE 0 END) as interested_count")
                ->selectRaw("SUM(CASE WHEN status IN ('shop_opened','active_shop') THEN 1 ELSE 0 END) as shops_count")
                ->groupBy('quarter_id')
                ->get()
                ->keyBy('quarter_id');
        }

        return view('commercial.prospecting.areas', compact(
            'currentMission',
            'upcomingMission',
            'overdueMission',
            'recentMissions',
            'quarterStats'
        ));
    }

    public function prospects(Request $request)
    {
        $commercialId = (int) $request->user()->id;
        $assignedMissionIds = CommercialProspectingMission::query()->forCommercial($commercialId)->pluck('id');

        $query = CommercialProspect::query()
            ->whereIn('mission_id', $assignedMissionIds)
            ->with(['mission.commune:id,name', 'quarter:id,name', 'commercial:id,name', 'shop:id,name']);

        if ($request->filled('mission_id')) {
            $missionId = $request->integer('mission_id');
            abort_unless($assignedMissionIds->contains($missionId), 403, 'Cette mission ne vous est pas affectée.');
            $query->where('mission_id', $missionId);
        }
        if ($request->filled('quarter_id')) {
            $query->where('quarter_id', $request->integer('quarter_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }
        if ($request->filled('q')) {
            $needle = '%'.trim((string) $request->q).'%';
            $query->where(fn (Builder $q) => $q->where('business_name', 'like', $needle)
                ->orWhere('contact_name', 'like', $needle)
                ->orWhere('phone', 'like', $needle)
                ->orWhere('category', 'like', $needle));
        }

        $missions = CommercialProspectingMission::query()
            ->forCommercial($commercialId)
            ->with('commune:id,name')
            ->latest('starts_on')
            ->get(['id', 'commune_id', 'starts_on', 'ends_on', 'status']);

        $baseStats = CommercialProspect::whereIn('mission_id', $assignedMissionIds);

        return view('commercial.prospecting.prospects', [
            'prospects' => $query->latest()->paginate(30)->withQueryString(),
            'missions' => $missions,
            'stats' => [
                'total' => (clone $baseStats)->count(),
                'to_visit' => (clone $baseStats)->whereIn('status', ['to_visit', 'visited'])->count(),
                'interested' => (clone $baseStats)->whereIn('status', ['interested', 'follow_up', 'registration_in_progress'])->count(),
                'converted' => (clone $baseStats)->whereIn('status', ['shop_opened', 'active_shop'])->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $commercialId = (int) $request->user()->id;
        $mission = $this->activeMissionFor($commercialId, $request->integer('mission_id') ?: null);

        if (! $mission) {
            return redirect()->route('commercial.prospecting.areas')
                ->with('error', 'Aucune mission de prospection active ne vous est affectée.');
        }

        $mission->load([
            'commune:id,name',
            'missionQuarters' => fn ($q) => $q->whereIn('status', ['pending', 'in_progress'])->with('quarter:id,commune_id,name')->orderBy('id'),
        ]);

        $selectedQuarterId = $request->integer('quarter_id') ?: null;
        if ($selectedQuarterId && ! $mission->missionQuarters->contains(fn ($mq) => (int) $mq->quarter_id === $selectedQuarterId)) {
            $selectedQuarterId = null;
        }

        return view('commercial.prospecting.create', compact('mission', 'selectedQuarterId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'mission_id' => ['required', 'integer', 'exists:commercial_prospecting_missions,id'],
            'quarter_id' => ['required', 'integer', 'exists:abidjan_quarters,id'],
            'business_name' => ['required', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:120'],
            'contact_name' => ['nullable', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'potential' => ['required', Rule::in(['low', 'medium', 'high'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $commercialId = (int) $request->user()->id;
        $mission = $this->activeMissionFor($commercialId, (int) $data['mission_id']);
        abort_unless($mission, 403, 'Cette mission ne vous est pas affectée ou n’est pas active aujourd’hui.');

        $missionQuarter = CommercialProspectingMissionQuarter::where('mission_id', $mission->id)
            ->where('quarter_id', (int) $data['quarter_id'])
            ->firstOrFail();

        abort_if($missionQuarter->status === 'completed', 422, 'Ce quartier est déjà marqué comme terminé.');

        $prospect = DB::transaction(function () use ($request, $mission, $missionQuarter, $data) {
            if ($missionQuarter->status === 'pending') {
                $missionQuarter->update([
                    'status' => 'in_progress',
                    'started_at' => now(),
                    'updated_by_commercial_id' => $request->user()->id,
                ]);
            }

            return CommercialProspect::create([
                'mission_id' => $mission->id,
                'area_id' => null,
                'commune_id' => $mission->commune_id,
                'quarter_id' => $missionQuarter->quarter_id,
                'locality_id' => null,
                'discovered_by_commercial_id' => $request->user()->id,
                'business_name' => $data['business_name'],
                'category' => $data['category'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'address' => $data['address'] ?? null,
                'landmark' => $data['landmark'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'status' => 'to_visit',
                'potential' => $data['potential'],
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return redirect()->route('commercial.prospecting.prospects.show', $prospect)
            ->with('success', 'Vendeur potentiel ajouté à la mission de prospection.');
    }

    public function show(Request $request, CommercialProspect $prospect)
    {
        $this->authorizeProspect($request, $prospect);
        $prospect->load(['mission.commune', 'quarter', 'commercial', 'vendor', 'shop', 'visits.commercial']);
        $labels = $this->statusLabels();

        return view('commercial.prospecting.show', compact('prospect', 'labels'));
    }

    public function update(Request $request, CommercialProspect $prospect)
    {
        $this->authorizeProspect($request, $prospect);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->statusLabels()))],
            'potential' => ['required', Rule::in(['low', 'medium', 'high'])],
            'next_follow_up_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $prospect->update($data);

        return back()->with('success', 'Vendeur potentiel mis à jour.');
    }

    public function visit(Request $request, CommercialProspect $prospect)
    {
        $this->authorizeProspect($request, $prospect);
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['visited', 'interested', 'follow_up', 'unavailable', 'refused', 'registration_in_progress'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'next_follow_up_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        DB::transaction(function () use ($request, $prospect, $data) {
            CommercialProspectingVisit::create([
                'prospect_id' => $prospect->id,
                'mission_id' => $prospect->mission_id,
                'commercial_id' => $request->user()->id,
                'session_id' => null,
                'outcome' => $data['outcome'],
                'notes' => $data['notes'] ?? null,
                'visited_at' => now(),
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ]);
            $prospect->update([
                'status' => $data['outcome'],
                'last_visited_at' => now(),
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
                'notes' => $data['notes'] ?? $prospect->notes,
            ]);
        });

        return back()->with('success', 'Visite enregistrée et partagée avec l’équipe de la mission.');
    }

    public function startQuarter(Request $request, CommercialProspectingMission $mission, CommercialProspectingMissionQuarter $missionQuarter)
    {
        $this->authorizeMission($request, $mission, true);
        abort_unless((int) $missionQuarter->mission_id === (int) $mission->id, 404);

        if ($missionQuarter->status === 'pending') {
            $missionQuarter->update([
                'status' => 'in_progress',
                'started_at' => now(),
                'updated_by_commercial_id' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'Quartier marqué en cours. Toute l’équipe voit maintenant cet avancement.');
    }

    public function finishQuarter(Request $request, CommercialProspectingMission $mission, CommercialProspectingMissionQuarter $missionQuarter)
    {
        $this->authorizeMission($request, $mission, true);
        abort_unless((int) $missionQuarter->mission_id === (int) $mission->id, 404);

        if ($missionQuarter->status !== 'completed') {
            $missionQuarter->update([
                'status' => 'completed',
                'started_at' => $missionQuarter->started_at ?: now(),
                'completed_at' => now(),
                'updated_by_commercial_id' => $request->user()->id,
            ]);
        }

        $remaining = $mission->missionQuarters()->where('status', '!=', 'completed')->count();
        if ($remaining === 0 && $mission->status !== 'completed') {
            $mission->update(['status' => 'completed', 'completed_at' => now()]);
            return redirect()->route('commercial.prospecting.areas')
                ->with('success', 'Tous les quartiers sont terminés. La mission de '.$mission->commune?->name.' est clôturée.');
        }

        return back()->with('success', 'Quartier terminé. Il reste '.$remaining.' quartier(s) à couvrir dans la commune.');
    }

    private function activeMissionFor(int $commercialId, ?int $missionId = null): ?CommercialProspectingMission
    {
        $today = now()->toDateString();
        return CommercialProspectingMission::query()
            ->forCommercial($commercialId)
            ->when($missionId, fn (Builder $q) => $q->whereKey($missionId))
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->first();
    }

    private function authorizeMission(Request $request, CommercialProspectingMission $mission, bool $requireActive = false): void
    {
        abort_unless($mission->isAssignedTo((int) $request->user()->id), 403, 'Cette mission ne vous est pas affectée.');
        if ($requireActive) {
            $today = now()->toDateString();
            abort_if($mission->status === 'completed' || $mission->status === 'cancelled' || $mission->starts_on?->toDateString() > $today || $mission->ends_on?->toDateString() < $today, 403, 'Cette mission n’est pas active aujourd’hui.');
        }
    }

    private function authorizeProspect(Request $request, CommercialProspect $prospect): void
    {
        abort_unless($prospect->mission_id, 403, 'Ce prospect n’appartient pas à une mission affectée.');
        $mission = CommercialProspectingMission::findOrFail($prospect->mission_id);
        $this->authorizeMission($request, $mission, false);
    }

    private function statusLabels(): array
    {
        return [
            'to_visit' => 'À visiter',
            'visited' => 'Visité',
            'interested' => 'Intéressé',
            'follow_up' => 'À relancer',
            'unavailable' => 'Indisponible',
            'refused' => 'Refus',
            'registration_in_progress' => 'Inscription en cours',
            'vendor_created' => 'Compte vendeur créé',
            'shop_opened' => 'Boutique ouverte',
            'active_shop' => 'Boutique active',
        ];
    }
}
