<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Devis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DevisController extends Controller
{
    /**
     * Lister les devis avec pagination et recherche simple.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Devis::class);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $search = $request->query('search');

        $query = Devis::query()->orderByDesc('created_at');

        if (! $request->user()->can('viewAll', Devis::class)) {
            $query->where('user_id', $request->user()->id);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $page = $query->paginate($perPage);

        $data = $page->getCollection()->map(function ($d) {
            $item = $d->toArray();
            unset($item['image_path']);
            $item['activites'] = $d->activites ?? [];
            $item['attachment_available'] = ! empty($d->image_path);
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

    /**
     * Afficher un devis précis.
     */
    public function show(Devis $devis)
    {
        $this->authorize('view', $devis);

        $item = $devis->toArray();
        unset($item['image_path']);
        $item['activites'] = $devis->activites ?? [];
        $item['attachment_available'] = ! empty($devis->image_path);

        return response()->json($item);
    }

    /**
     * Créer un nouveau devis avec activités et image.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Devis::class);

        $rules = [
            'secteur' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'nom' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telephone' => 'nullable|string|max:50',
            'pays' => 'required|string|max:255',
            'ville' => 'nullable|string|max:255',
            'budget' => 'required|numeric|min:0',
            'projet' => 'nullable|string|max:255',
            'message' => 'required|string|min:10',
            'activites' => 'required|array|min:1',
            'activites.*' => 'string|max:255',
            'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:5120'
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['activites'] = $data['activites'] ?? [];
        unset($data['category'], $data['user_id'], $data['company_id']);
        $data['user_id'] = $request->user()->id;

        if ($companyId = $request->user()->getAttribute('company_id')) {
            $data['company_id'] = $companyId;
        }

        // Gestion upload image
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')
                ->store('private-documents/business/devis', 'local');
        }

        $devis = Devis::create($data);

        $result = $devis->toArray();
        unset($result['image_path']);
        $result['attachment_available'] = isset($data['image_path']);
        $result['activites'] = $devis->activites ?? [];

        return response()->json($result, 201);
    }

    /**
     * Mettre à jour un devis.
     */
    public function update(Request $request, Devis $devis)
    {
        $this->authorize('update', $devis);

        $rules = [
            'secteur' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|max:255',
            'prenom' => 'sometimes|required|string|max:255',
            'nom' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255',
            'telephone' => 'nullable|string|max:50',
            'pays' => 'sometimes|required|string|max:255',
            'ville' => 'nullable|string|max:255',
            'budget' => 'sometimes|required|numeric|min:0',
            'projet' => 'nullable|string|max:255',
            'message' => 'sometimes|required|string|min:10',
            'activites' => 'sometimes|required|array|min:1',
            'activites.*' => 'string|max:255',
            'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:5120'
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        if (array_key_exists('activites', $data)) {
            $data['activites'] ??= [];
        }
        unset($data['category'], $data['user_id'], $data['company_id']);

        if ($request->hasFile('image')) {
            $oldPath = $devis->image_path;
            $data['image_path'] = $request->file('image')
                ->store('private-documents/business/devis', 'local');
        }

        $devis->update($data);

        if (isset($oldPath) && $oldPath !== $devis->image_path) {
            $this->deleteStoredAttachment($oldPath);
        }

        $result = $devis->toArray();
        unset($result['image_path']);
        $result['attachment_available'] = ! empty($devis->image_path);
        $result['activites'] = $devis->activites ?? [];

        return response()->json($result);
    }

    /**
     * Supprimer un devis.
     */
    public function destroy(Devis $devis)
    {
        $this->authorize('delete', $devis);

        $path = $devis->image_path;
        $devis->forceFill(['image_path' => null])->save();
        $devis->delete();
        $this->deleteStoredAttachment($path);

        return response()->json(null, 204);
    }

    public function downloadAttachment(Devis $devis)
    {
        $this->authorize('view', $devis);
        abort_unless($devis->image_path && Storage::disk('local')->exists($devis->image_path), 404);

        $extension = pathinfo($devis->image_path, PATHINFO_EXTENSION) ?: 'bin';

        $response = response()->download(
            Storage::disk('local')->path($devis->image_path),
            'piece-jointe-devis-' . $devis->id . '.' . $extension,
            [
                'Content-Type' => Storage::disk('local')->mimeType($devis->image_path) ?: 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="piece-jointe-devis-' . $devis->id . '.' . $extension . '"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );

        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function deleteStoredAttachment(?string $path): void
    {
        if (! $path) {
            return;
        }

        $disk = str_starts_with($path, 'private-documents/business/') ? 'local' : 'public';
        Storage::disk($disk)->delete($path);
    }

    public function createForm()
    {
        return view('devis');
    }
}
