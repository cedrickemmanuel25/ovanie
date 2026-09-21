<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialProspect;
use App\Models\CommercialProspectingMission;
use App\Models\CommercialProspectingMissionQuarter;
use App\Models\CommercialProspectingVisit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommercialMobileProspectingController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        return $this->mission($request);
    }

    public function mission(Request $request): JsonResponse
    {
        $commercialId = (int) $request->user()->id;
        $today = now()->toDateString();

        $mission = CommercialProspectingMission::query()
            ->forCommercial($commercialId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->with([
                'commune:id,name',
                'members:id,name,first_name,last_name',
                'missionQuarters' => fn ($q) => $q->with(['quarter:id,name,commune_id', 'updatedBy:id,name'])->orderBy('id'),
            ])
            ->withCount([
                'prospects',
                'visits',
                'missionQuarters',
                'missionQuarters as completed_quarters_count' => fn (Builder $q) => $q->where('status', 'completed'),
                'prospects as interested_count' => fn (Builder $q) => $q->whereIn('status', ['interested', 'follow_up', 'registration_in_progress']),
                'prospects as shops_opened_count' => fn (Builder $q) => $q->whereIn('status', ['shop_opened', 'active_shop']),
            ])
            ->first();

        $upcoming = CommercialProspectingMission::query()
            ->forCommercial($commercialId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('starts_on', '>', $today)
            ->with(['commune:id,name', 'members:id,name'])
            ->orderBy('starts_on')
            ->first();

        $overdue = $mission ? null : CommercialProspectingMission::query()
            ->forCommercial($commercialId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('ends_on', '<', $today)
            ->with('commune:id,name')
            ->latest('ends_on')
            ->first();

        if (! $mission) {
            return response()->json([
                'mission' => null,
                'upcoming_mission' => $upcoming ? $this->briefMissionPayload($upcoming) : null,
                'overdue_mission' => $overdue ? $this->briefMissionPayload($overdue) : null,
                'summary' => ['quarters_total' => 0, 'quarters_completed' => 0, 'prospects' => 0, 'interested' => 0, 'shops_opened' => 0, 'visits' => 0],
            ]);
        }

        $quarterStats = CommercialProspect::where('mission_id', $mission->id)
            ->whereNotNull('quarter_id')
            ->selectRaw('quarter_id, COUNT(*) as prospects_count')
            ->selectRaw("SUM(CASE WHEN status IN ('interested','follow_up','registration_in_progress') THEN 1 ELSE 0 END) as interested_count")
            ->selectRaw("SUM(CASE WHEN status IN ('shop_opened','active_shop') THEN 1 ELSE 0 END) as shops_count")
            ->groupBy('quarter_id')->get()->keyBy('quarter_id');

        return response()->json([
            'mission' => $this->missionPayload($mission, $quarterStats),
            'upcoming_mission' => $upcoming ? $this->briefMissionPayload($upcoming) : null,
            'overdue_mission' => null,
            'summary' => [
                'quarters_total' => (int) $mission->mission_quarters_count,
                'quarters_completed' => (int) $mission->completed_quarters_count,
                'prospects' => (int) $mission->prospects_count,
                'interested' => (int) $mission->interested_count,
                'shops_opened' => (int) $mission->shops_opened_count,
                'visits' => (int) $mission->visits_count,
            ],
        ]);
    }

    public function prospects(Request $request): JsonResponse
    {
        $commercialId = (int) $request->user()->id;
        $missionId = $request->integer('mission_id');

        $mission = $missionId
            ? CommercialProspectingMission::query()->forCommercial($commercialId)->find($missionId)
            : $this->currentMission($commercialId);

        if (! $mission) {
            return response()->json(['prospects' => []]);
        }

        $query = CommercialProspect::where('mission_id', $mission->id)
            ->with(['mission.commune:id,name', 'quarter:id,name', 'shop:id,name', 'commercial:id,name']);

        if ($request->filled('quarter_id')) {
            $query->where('quarter_id', $request->integer('quarter_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }
        if ($request->filled('q')) {
            $needle = '%'.trim((string) $request->q).'%';
            $query->where(fn (Builder $q) => $q->where('business_name', 'like', $needle)
                ->orWhere('category', 'like', $needle)
                ->orWhere('contact_name', 'like', $needle)
                ->orWhere('phone', 'like', $needle));
        }

        return response()->json([
            'prospects' => $query->latest()->limit(250)->get()->map(fn ($p) => $this->prospectPayload($p))->values(),
        ]);
    }

    public function storeProspect(Request $request): JsonResponse
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
            'potential' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $mission = $this->currentMission((int) $request->user()->id, (int) $data['mission_id']);
        if (! $mission) {
            return response()->json(['message' => 'Cette mission ne vous est pas affectée ou n’est pas active aujourd’hui.'], 403);
        }

        $missionQuarter = CommercialProspectingMissionQuarter::where('mission_id', $mission->id)
            ->where('quarter_id', (int) $data['quarter_id'])
            ->first();

        if (! $missionQuarter) {
            return response()->json(['message' => 'Ce quartier ne fait pas partie de votre mission.'], 422);
        }
        if ($missionQuarter->status === 'completed') {
            return response()->json(['message' => 'Ce quartier est déjà marqué comme terminé.'], 422);
        }

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
                'potential' => $data['potential'] ?? 'medium',
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $prospect->load(['mission.commune:id,name', 'quarter:id,name', 'commercial:id,name']);
        return response()->json(['message' => 'Vendeur enregistré dans la mission.', 'prospect' => $this->prospectPayload($prospect)], 201);
    }

    public function startQuarter(Request $request, CommercialProspectingMission $mission, CommercialProspectingMissionQuarter $missionQuarter): JsonResponse
    {
        if (! $this->missionIsActiveFor($mission, (int) $request->user()->id) || (int) $missionQuarter->mission_id !== (int) $mission->id) {
            return response()->json(['message' => 'Ce quartier ne fait pas partie de votre mission active.'], 403);
        }

        if ($missionQuarter->status === 'pending') {
            $missionQuarter->update(['status' => 'in_progress', 'started_at' => now(), 'updated_by_commercial_id' => $request->user()->id]);
        }

        return response()->json(['message' => 'Quartier marqué en cours.']);
    }

    public function finishQuarter(Request $request, CommercialProspectingMission $mission, CommercialProspectingMissionQuarter $missionQuarter): JsonResponse
    {
        if (! $this->missionIsActiveFor($mission, (int) $request->user()->id) || (int) $missionQuarter->mission_id !== (int) $mission->id) {
            return response()->json(['message' => 'Ce quartier ne fait pas partie de votre mission active.'], 403);
        }

        if ($missionQuarter->status !== 'completed') {
            $missionQuarter->update([
                'status' => 'completed',
                'started_at' => $missionQuarter->started_at ?: now(),
                'completed_at' => now(),
                'updated_by_commercial_id' => $request->user()->id,
            ]);
        }

        $remaining = $mission->missionQuarters()->where('status', '!=', 'completed')->count();
        if ($remaining === 0) {
            $mission->update(['status' => 'completed', 'completed_at' => now()]);
        }

        return response()->json(['message' => $remaining === 0 ? 'Mission terminée : tous les quartiers sont couverts.' : "Quartier terminé. Il reste {$remaining} quartier(s).", 'remaining_quarters' => $remaining]);
    }

    public function visit(Request $request, CommercialProspect $prospect): JsonResponse
    {
        if (! $prospect->mission_id || ! CommercialProspectingMission::query()->forCommercial((int) $request->user()->id)->whereKey($prospect->mission_id)->exists()) {
            return response()->json(['message' => 'Ce vendeur n’appartient pas à l’une de vos missions.'], 403);
        }

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

        $prospect->refresh()->load(['mission.commune:id,name', 'quarter:id,name', 'shop:id,name', 'commercial:id,name']);
        return response()->json(['message' => 'Visite enregistrée.', 'prospect' => $this->prospectPayload($prospect)]);
    }

    private function currentMission(int $commercialId, ?int $missionId = null): ?CommercialProspectingMission
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

    private function missionIsActiveFor(CommercialProspectingMission $mission, int $commercialId): bool
    {
        $today = now()->toDateString();
        return $mission->isAssignedTo($commercialId)
            && ! in_array($mission->status, ['completed', 'cancelled'], true)
            && $mission->starts_on?->toDateString() <= $today
            && $mission->ends_on?->toDateString() >= $today;
    }

    private function briefMissionPayload(CommercialProspectingMission $mission): array
    {
        return [
            'id' => $mission->id,
            'commune' => $mission->commune?->name ?? '',
            'starts_on' => optional($mission->starts_on)->toDateString(),
            'ends_on' => optional($mission->ends_on)->toDateString(),
            'status' => $mission->effectiveStatus(),
            'team_count' => $mission->relationLoaded('members') ? $mission->members->count() : $mission->members()->count(),
        ];
    }

    private function missionPayload(CommercialProspectingMission $mission, $quarterStats): array
    {
        $total = (int) $mission->mission_quarters_count;
        $completed = (int) $mission->completed_quarters_count;
        return [
            'id' => $mission->id,
            'commune_id' => $mission->commune_id,
            'commune' => $mission->commune?->name ?? '',
            'starts_on' => optional($mission->starts_on)->toDateString(),
            'ends_on' => optional($mission->ends_on)->toDateString(),
            'status' => $mission->effectiveStatus(),
            'instructions' => $mission->instructions ?? '',
            'shop_target' => $mission->shop_target,
            'progress_percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'team' => $mission->members->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'quarters' => $mission->missionQuarters->sortBy(fn ($mq) => $mq->quarter?->name)->values()->map(function ($mq) use ($quarterStats) {
                $stats = $quarterStats->get($mq->quarter_id);
                return [
                    'id' => $mq->id,
                    'quarter_id' => $mq->quarter_id,
                    'name' => $mq->quarter?->name ?? '',
                    'status' => $mq->status,
                    'prospects_count' => (int) ($stats->prospects_count ?? 0),
                    'interested_count' => (int) ($stats->interested_count ?? 0),
                    'shops_count' => (int) ($stats->shops_count ?? 0),
                    'started_at' => optional($mq->started_at)->toIso8601String(),
                    'completed_at' => optional($mq->completed_at)->toIso8601String(),
                    'updated_by' => $mq->updatedBy?->name,
                ];
            }),
        ];
    }

    private function prospectPayload(CommercialProspect $p): array
    {
        return [
            'id' => $p->id,
            'mission_id' => $p->mission_id,
            'commune_id' => $p->commune_id,
            'quarter_id' => $p->quarter_id,
            'business_name' => $p->business_name,
            'category' => $p->category ?? 'Vendeur BTP',
            'contact_name' => $p->contact_name ?? '',
            'phone' => $p->phone ?? '',
            'whatsapp' => $p->whatsapp ?? '',
            'address' => $p->address ?? '',
            'landmark' => $p->landmark ?? '',
            'latitude' => $p->latitude !== null ? (float) $p->latitude : null,
            'longitude' => $p->longitude !== null ? (float) $p->longitude : null,
            'status' => $p->status,
            'potential' => $p->potential,
            'notes' => $p->notes ?? '',
            'commune' => $p->mission?->commune?->name ?? '',
            'quarter' => $p->quarter?->name ?? '',
            'last_visited_at' => optional($p->last_visited_at)->toIso8601String(),
            'next_follow_up_at' => optional($p->next_follow_up_at)->toIso8601String(),
            'shop_id' => $p->shop_id,
            'shop_name' => $p->shop?->name,
            'discovered_by' => $p->commercial?->name,
        ];
    }
}
