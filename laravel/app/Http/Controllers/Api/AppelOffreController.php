<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppelOffre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AppelOffreController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AppelOffre::class);
        $perPage = min(max($request->integer('per_page', 20), 1), 50);
        $query = AppelOffre::query()->latest();

        if (! $request->user()->can('viewAll', AppelOffre::class)) {
            $query->where('user_id', $request->user()->id);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q
                ->where('description', 'like', "%{$search}%")
                ->orWhere('secteur', 'like', "%{$search}%"));
        }

        $page = $query->paginate($perPage);
        $page->getCollection()->transform(fn (AppelOffre $appelOffre) => $this->privatePayload($appelOffre));

        return response()->json($page);
    }

    public function show(AppelOffre $appelOffre)
    {
        $this->authorize('view', $appelOffre);

        return response()->json(['data' => $this->privatePayload($appelOffre)]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', AppelOffre::class);
        $data = $this->validated($request, true);
        $data['services'] = $data['services'] ?? [];
        unset($data['category'], $data['user_id'], $data['company_id']);
        $data['user_id'] = $request->user()->id;

        if ($companyId = $request->user()->getAttribute('company_id')) {
            $data['company_id'] = $companyId;
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')
                ->store('private-documents/business/appel-offres', 'local');
        }

        $appelOffre = AppelOffre::query()->create($data);

        return response()->json(['data' => $this->privatePayload($appelOffre)], 201);
    }

    public function update(Request $request, AppelOffre $appelOffre)
    {
        $this->authorize('update', $appelOffre);
        $data = $this->validated($request, false);
        if (array_key_exists('services', $data)) {
            $data['services'] ??= [];
        }
        unset($data['category'], $data['user_id'], $data['company_id']);

        if ($request->hasFile('image')) {
            $oldPath = $appelOffre->image;
            $data['image'] = $request->file('image')
                ->store('private-documents/business/appel-offres', 'local');
        }

        $appelOffre->update($data);

        if (isset($oldPath) && $oldPath !== $appelOffre->image) {
            $this->deleteStoredAttachment($oldPath);
        }

        return response()->json(['data' => $this->privatePayload($appelOffre->fresh())]);
    }

    public function destroy(AppelOffre $appelOffre)
    {
        $this->authorize('delete', $appelOffre);
        $path = $appelOffre->image;
        $appelOffre->forceFill(['image' => null])->save();
        $appelOffre->delete();
        $this->deleteStoredAttachment($path);

        return response()->json(null, 204);
    }

    public function downloadAttachment(AppelOffre $appelOffre)
    {
        $this->authorize('view', $appelOffre);
        abort_unless($appelOffre->image && Storage::disk('local')->exists($appelOffre->image), 404);

        $extension = pathinfo($appelOffre->image, PATHINFO_EXTENSION) ?: 'bin';

        $response = response()->download(
            Storage::disk('local')->path($appelOffre->image),
            'piece-jointe-appel-offres-' . $appelOffre->id . '.' . $extension,
            [
                'Content-Type' => Storage::disk('local')->mimeType($appelOffre->image) ?: 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="piece-jointe-appel-offres-' . $appelOffre->id . '.' . $extension . '"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );

        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function privatePayload(AppelOffre $appelOffre): array
    {
        $payload = $appelOffre->toArray();
        unset($payload['image']);
        $payload['services'] = $appelOffre->services ?? [];
        $payload['attachment_available'] = ! empty($appelOffre->image);

        return $payload;
    }

    private function deleteStoredAttachment(?string $path): void
    {
        if (! $path) {
            return;
        }

        $disk = str_starts_with($path, 'private-documents/business/') ? 'local' : 'public';
        Storage::disk($disk)->delete($path);
    }

    private function validated(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'secteur' => [$required, 'string', 'max:255'],
            'category' => [$required, 'string', 'max:100'],
            'services' => [$required, 'array', 'min:1'],
            'services.*' => ['string', 'max:255'],
            'prenom' => [$required, 'string', 'max:255'],
            'nom' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'pays' => ['nullable', 'string', 'max:100'],
            'ville' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'file', 'image', 'max:5120'],
            'budget' => [$required, 'numeric', 'min:0'],
            'delai' => ['nullable', 'integer', 'min:1'],
            'description' => [$required, 'string', 'min:10'],
        ]);
    }
}
