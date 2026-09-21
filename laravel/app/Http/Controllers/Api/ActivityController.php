<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AppelOffre;
use App\Models\BusinessRequest;
use App\Models\Devis;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::query();
        if ($request->has('active') && filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_active', true);
        }

        return response()->json($query->get());
    }

    public function show($id)
    {
        $activity = Activity::find($id);

        return $activity
            ? response()->json($activity)
            : response()->json(['message' => 'Activité non trouvée'], 404);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'activity_sector_id' => ['required', 'exists:activity_sectors,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:activities,slug'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        return response()->json(Activity::create($validated), 201);
    }

    public function update(Request $request, $id)
    {
        $activity = Activity::find($id);
        if (! $activity) {
            return response()->json(['message' => 'Activité non trouvée'], 404);
        }
        $validated = $request->validate([
            'activity_sector_id' => ['sometimes', 'required', 'exists:activity_sectors,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'unique:activities,slug,' . $id],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
        $activity->update($validated);

        return response()->json($activity);
    }

    public function destroy($id)
    {
        $activity = Activity::find($id);
        if (! $activity) {
            return response()->json(['message' => 'Activité non trouvée'], 404);
        }
        if ($this->isUsed($activity)) {
            $activity->update(['is_active' => false]);

            return response()->json([
                'message' => 'Activité utilisée : elle a été désactivée et conservée.',
                'activity' => $activity->fresh(),
            ]);
        }

        $activity->delete();

        return response()->json(['message' => 'Activité supprimée avec succès']);
    }

    private function isUsed(Activity $activity): bool
    {
        $values = array_values(array_unique([
            (int) $activity->id,
            (string) $activity->id,
            (string) $activity->slug,
            (string) $activity->name,
        ], SORT_REGULAR));

        foreach ($values as $value) {
            if (Devis::query()->whereJsonContains('activites', $value)->exists()
                || AppelOffre::query()->whereJsonContains('services', $value)->exists()) {
                return true;
            }
        }

        return BusinessRequest::query()->where(function ($query) use ($activity) {
            $query->where('category', $activity->slug)
                ->orWhere('category', $activity->name)
                ->orWhere('sector', $activity->slug)
                ->orWhere('sector', $activity->name)
                ->orWhere('type', $activity->slug);
        })->exists();
    }
}
