<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppelOffre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AppelOffreController extends Controller
{
    public function __construct()
    {
        // Les actions de mutation nécessitent une authentification
        $this->middleware('auth')->only(['store', 'update', 'destroy', 'create']);
    }

    /**
     * Vérifie que l'utilisateur connecté est le propriétaire de l'appel d'offre.
     * Les administrateurs ont accès sans restriction.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function authorizeOwner(AppelOffre $appel): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(401, 'Non authentifié.');
        }

        // Les administrateurs peuvent tout modifier
        if ($user->is_admin) {
            return;
        }

        // Sinon, seul le propriétaire peut agir sur son appel d'offre
        if ((int) $appel->user_id !== (int) $user->id) {
            abort(403, "Vous n'êtes pas autorisé à modifier cet appel d'offre.");
        }
    }
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $search = $request->query('search');

        $query = AppelOffre::query()->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $page = $query->paginate($perPage);

        $data = $page->getCollection()->map(function ($a) {
            $item = $a->toArray();
            $item['image_url'] = $a->image && Storage::disk('public')->exists($a->image)
                ? Storage::url($a->image)
                : null;
            $item['services'] = is_string($a->services) ? json_decode($a->services, true) : $a->services;
            return $item;
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $appel = AppelOffre::findOrFail($id);
        $item = $appel->toArray();
        $item['image_url'] = $appel->image && Storage::disk('public')->exists($appel->image)
            ? Storage::url($appel->image)
            : null;
        $item['services'] = is_string($appel->services) ? json_decode($appel->services, true) : $appel->services;

        return response()->json($item);
    }

    public function store(Request $request)
    {
        // On associe automatiquement l'utilisateur connecté comme propriétaire
        $rules = [
            'secteur' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'services' => 'required|array|min:1',
            'services.*' => 'string|max:255',
            'prenom' => 'required|string|max:255',
            'nom' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telephone' => 'nullable|string|max:50',
            'pays' => 'nullable|string|max:100',
            'ville' => 'nullable|string|max:100',
            'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'budget' => 'required|numeric|min:0',
            'delai' => 'nullable|integer|min:1',
            'description' => 'required|string|min:10',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('appel_offres', 'public');
        }

        $data['services'] = json_encode($data['services']);

        // Associer le créateur
        $data['user_id'] = auth()->id();

        $appel = AppelOffre::create($data);

        $result = $appel->toArray();
        $result['services'] = json_decode($appel->services, true);
        $result['image_url'] = isset($data['image']) ? Storage::url($data['image']) : null;

        return response()->json([
            'message' => "Appel d'offre créé avec succès",
            'appel_offre' => $result
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $appel = AppelOffre::findOrFail($id);

        $this->authorizeOwner($appel);

        $rules = [
            'secteur' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|max:100',
            'services' => 'sometimes|required|array|min:1',
            'services.*' => 'string|max:255',
            'prenom' => 'sometimes|required|string|max:255',
            'nom' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255',
            'telephone' => 'nullable|string|max:50',
            'pays' => 'nullable|string|max:100',
            'ville' => 'nullable|string|max:100',
            'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'budget' => 'sometimes|required|numeric|min:0',
            'delai' => 'nullable|integer|min:1',
            'description' => 'sometimes|required|string|min:10',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('image')) {
            if ($appel->image && Storage::disk('public')->exists($appel->image)) {
                Storage::disk('public')->delete($appel->image);
            }
            $data['image'] = $request->file('image')->store('appel_offres', 'public');
        }

        if (isset($data['services'])) {
            $data['services'] = json_encode($data['services']);
        }

        $appel->update($data);

        $result = $appel->toArray();
        $result['services'] = json_decode($appel->services, true);
        $result['image_url'] = $appel->image ? Storage::url($appel->image) : null;

        return response()->json($result);
    }

    public function create()
    {
        return view('appel-offre');
    }

    public function destroy($id)
    {
        $appel = AppelOffre::findOrFail($id);

        $this->authorizeOwner($appel);

        if ($appel->image && Storage::disk('public')->exists($appel->image)) {
            Storage::disk('public')->delete($appel->image);
        }

        $appel->delete();

        return response()->json(null, 204);
    }
}