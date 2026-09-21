<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubmissionController extends Controller
{
    /**
     * Affiche les messages envoyés depuis les formulaires publics OVANIE.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'in:newest,oldest,name_asc,name_desc'],
        ]);

        $query = Submission::query();

        if (! empty($filters['q'])) {
            $term = trim($filters['q']);

            $query->where(function ($builder) use ($term) {
                $builder
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('message', 'like', "%{$term}%");
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest(),
            'name_asc' => $query->orderBy('name')->latest('id'),
            'name_desc' => $query->orderByDesc('name')->latest('id'),
            default => $query->latest(),
        };

        $submissions = $query
            ->paginate(12)
            ->withQueryString();

        $summary = [
            'total' => Submission::count(),
            'today' => Submission::whereDate('created_at', today())->count(),
            'last_seven_days' => Submission::where('created_at', '>=', now()->subDays(7))->count(),
            'unique_senders' => Submission::query()
                ->whereNotNull('email')
                ->where('email', '<>', '')
                ->distinct()
                ->count('email'),
        ];

        return view('admin.submissions.index', compact('submissions', 'summary'));
    }
}
