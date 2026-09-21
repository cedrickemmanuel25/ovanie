<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppelOffre;
use App\Models\Devis;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SubmissionsController extends Controller
{
    public function indexAll(Request $request): View
    {
        $all = $this->buildSubmissionCollection();

        $stats = [
            'total' => $all->count(),
            'devis' => $all->where('source_type', 'devis')->count(),
            'appel_offre' => $all->where('source_type', 'appel_offre')->count(),
            'budget' => (float) $all->sum('budget'),
            'commission' => (float) $all->sum('commission'),
            'pending' => $all->where('commission_status', '!=', 'validated')->count(),
        ];

        $filtered = $all;
        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            $needle = mb_strtolower($search);

            $filtered = $filtered->filter(function (object $submission) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $submission->type ?? null,
                    $submission->prenom ?? null,
                    $submission->nom ?? null,
                    $submission->email ?? null,
                    $submission->telephone ?? null,
                    $submission->ville ?? null,
                    $submission->pays ?? null,
                    $submission->secteur ?? null,
                    $submission->projet ?? null,
                    $submission->description ?? null,
                ])));

                return str_contains($haystack, $needle);
            });
        }

        if ($type = $request->query('type')) {
            $filtered = $filtered->where('source_type', $type);
        }

        if ($commissionStatus = $request->query('commission_status')) {
            if ($commissionStatus === 'pending') {
                $filtered = $filtered->filter(
                    fn (object $submission): bool => ($submission->commission_status ?? 'pending') !== 'validated'
                );
            } else {
                $filtered = $filtered->where('commission_status', $commissionStatus);
            }
        }

        $filtered = match ($request->query('sort', 'recent')) {
            'oldest' => $filtered->sortBy('created_at'),
            'budget_desc' => $filtered->sortByDesc('budget'),
            'budget_asc' => $filtered->sortBy('budget'),
            default => $filtered->sortByDesc('created_at'),
        };

        $allSubmissions = $this->paginateCollection($filtered->values(), $request, 12);

        return view('admin.submissions.indexs', compact('allSubmissions', 'stats'));
    }

    public function show(string $type, int $id): View
    {
        $submission = $this->findSubmission($type, $id);

        return view('admin.submissions.show', compact('submission', 'type'));
    }

    public function destroy(string $type, int $id)
    {
        $submission = $this->findSubmissionModel($type, $id);
        $submission->delete();

        return redirect()
            ->route('admin.submissions.indexAll')
            ->with('success', 'La soumission a été supprimée avec succès.');
    }

    public function validateCommission(string $type, int $id)
    {
        $submission = $this->findSubmissionModel($type, $id);
        $submission->commission_status = 'validated';
        $submission->save();

        return redirect()
            ->route('admin.submissions.indexAll')
            ->with('success', 'La commission a été validée avec succès.');
    }

    private function buildSubmissionCollection(): Collection
    {
        $devis = Devis::query()->latest('created_at')->get()->map(function (Devis $item): object {
            $budget = (float) ($item->budget ?? 0);

            return (object) [
                'id' => $item->id,
                'source_type' => 'devis',
                'type' => 'Devis',
                'secteur' => $item->secteur,
                'activites' => $item->activites,
                'prenom' => $item->prenom,
                'nom' => $item->nom,
                'email' => $item->email,
                'telephone' => $item->telephone,
                'pays' => $item->pays,
                'ville' => $item->ville,
                'image_path' => $item->image_path,
                'budget' => $budget,
                'commission' => $budget * 0.05,
                'commission_status' => $item->commission_status ?? 'pending',
                'projet' => $item->projet ?? null,
                'message' => $item->message ?? null,
                'description' => $item->message ?? null,
                'delai' => null,
                'created_at' => $item->created_at,
            ];
        });

        $appelOffres = AppelOffre::query()->latest('created_at')->get()->map(function (AppelOffre $item): object {
            $budget = (float) ($item->budget ?? 0);

            return (object) [
                'id' => $item->id,
                'source_type' => 'appel_offre',
                'type' => "Appel d'offres",
                'secteur' => $item->secteur,
                'activites' => $item->services,
                'prenom' => $item->prenom,
                'nom' => $item->nom,
                'email' => $item->email,
                'telephone' => $item->telephone,
                'pays' => $item->pays,
                'ville' => $item->ville,
                'image_path' => $item->image,
                'budget' => $budget,
                'commission' => $budget * 0.05,
                'commission_status' => $item->commission_status ?? 'pending',
                'projet' => $item->projet ?? null,
                'delai' => $item->delai ?? null,
                'description' => $item->description ?? null,
                'message' => $item->description ?? null,
                'created_at' => $item->created_at,
            ];
        });

        return $devis->concat($appelOffres)->values();
    }

    private function paginateCollection(Collection $items, Request $request, int $perPage): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pageItems = $items->forPage($currentPage, $perPage)->values();

        return new LengthAwarePaginator(
            $pageItems,
            $items->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function findSubmissionModel(string $type, int $id)
    {
        return match ($type) {
            'devis' => Devis::findOrFail($id),
            'appel_offre' => AppelOffre::findOrFail($id),
            default => abort(404),
        };
    }

    private function findSubmission(string $type, int $id): object
    {
        $item = $this->findSubmissionModel($type, $id);
        $budget = (float) ($item->budget ?? 0);

        if ($type === 'devis') {
            return (object) [
                'id' => $item->id,
                'source_type' => 'devis',
                'type' => 'Devis',
                'secteur' => $item->secteur,
                'activites' => $item->activites,
                'prenom' => $item->prenom,
                'nom' => $item->nom,
                'email' => $item->email,
                'telephone' => $item->telephone,
                'pays' => $item->pays,
                'ville' => $item->ville,
                'image_path' => $item->image_path,
                'budget' => $budget,
                'commission' => $budget * 0.05,
                'commission_status' => $item->commission_status ?? 'pending',
                'projet' => $item->projet ?? null,
                'message' => $item->message ?? null,
                'description' => $item->message ?? null,
                'delai' => null,
                'created_at' => $item->created_at,
            ];
        }

        return (object) [
            'id' => $item->id,
            'source_type' => 'appel_offre',
            'type' => "Appel d'offres",
            'secteur' => $item->secteur,
            'activites' => $item->services,
            'prenom' => $item->prenom,
            'nom' => $item->nom,
            'email' => $item->email,
            'telephone' => $item->telephone,
            'pays' => $item->pays,
            'ville' => $item->ville,
            'image_path' => $item->image,
            'budget' => $budget,
            'commission' => $budget * 0.05,
            'commission_status' => $item->commission_status ?? 'pending',
            'projet' => $item->projet ?? null,
            'delai' => $item->delai ?? null,
            'description' => $item->description ?? null,
            'message' => $item->description ?? null,
            'created_at' => $item->created_at,
        ];
    }
}
