<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivitySector;
use Illuminate\Http\Request;

class ActivitySectorController extends Controller
{
    /**
     * Afficher la liste des secteurs d'activité (optionnellement actifs seulement).
     */
    public function index(Request $request)
    {
        if ($request->query('active')) {
            $sectors = ActivitySector::active()->get();
        } else {
            $sectors = ActivitySector::all();
        }

        return response()->json($sectors);
    }

    public function activites($slug)
    {
        $sector = ActivitySector::where('slug', $slug)->first();

        if (!$sector) {
            return response()->json([], 404);
        }

        return response()->json($sector->activities);
    }
    /**
     * Créer un nouveau secteur d'activité.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:activity_sectors,slug'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $sector = ActivitySector::create($data);

        return response()->json($sector, 201);
    }

    /**
     * Afficher un secteur d'activité spécifique.
     */
    public function show(ActivitySector $activitySector)
    {
        return response()->json($activitySector);
    }

    /**
     * Mettre à jour un secteur d'activité.
     */
    public function update(Request $request, ActivitySector $activitySector)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:activity_sectors,slug,' . $activitySector->id],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $activitySector->update($data);

        return response()->json($activitySector);
    }

    /**
     * Supprimer un secteur d'activité.
     */
    public function destroy(ActivitySector $activitySector)
    {
        $activitySector->delete();

        return response()->json(null, 204);
    }
}
