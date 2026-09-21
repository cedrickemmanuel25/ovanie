<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Services\OrderWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;

class VendorDisputeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:vendor']);
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $perPage = max(5, min(50, (int) $request->query('per_page', 10)));

        $all = $this->vendorDisputeQuery()
            ->with(['order.client'])
            ->latest('updated_at')
            ->get();

        $all->each(function (Dispute $dispute) {
            $dispute->setAttribute(
                'vendor_display_status',
                $this->resolveDisplayStatus($dispute)
            );
        });

        $statusCounts = [
            'all' => $all->count(),
            'new' => $all->where('vendor_display_status', 'new')->count(),
            'in_progress' => $all->where('vendor_display_status', 'in_progress')->count(),
            'waiting_ovanie' => $all->where('vendor_display_status', 'waiting_ovanie')->count(),
            'resolved' => $all->where('vendor_display_status', 'resolved')->count(),
            'closed' => $all->where('vendor_display_status', 'closed')->count(),
        ];

        $filtered = $all
            ->filter(function (Dispute $dispute) use ($search) {
                if ($search === '') {
                    return true;
                }

                $haystack = strtolower(implode(' ', array_filter([
                    $dispute->id,
                    $dispute->order_reference,
                    $dispute->client_name,
                    $dispute->reason,
                    $dispute->response,
                    $dispute->order?->order_number,
                    $dispute->order?->client?->name,
                    $dispute->order?->client?->phone,
                ])));

                return str_contains($haystack, strtolower($search));
            })
            ->when($status !== 'all', fn (Collection $items) =>
                $items->where('vendor_display_status', $status)
            )
            ->when($dateFrom, fn (Collection $items) =>
                $items->filter(fn (Dispute $d) =>
                    $d->created_at && $d->created_at->toDateString() >= $dateFrom
                )
            )
            ->when($dateTo, fn (Collection $items) =>
                $items->filter(fn (Dispute $d) =>
                    $d->created_at && $d->created_at->toDateString() <= $dateTo
                )
            )
            ->values();

        if ($request->boolean('export')) {
            return $this->exportCsv($filtered);
        }

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pageItems = $filtered
            ->slice(($currentPage - 1) * $perPage, $perPage)
            ->values();

        $disputes = new LengthAwarePaginator(
            $pageItems,
            $filtered->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('vendor.disputes', compact(
            'disputes',
            'statusCounts',
            'search',
            'status',
            'dateFrom',
            'dateTo',
            'perPage'
        ));
    }

    public function respond(Request $request, $id)
    {
        $validated = $request->validate([
            'response' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        DB::transaction(function () use ($id, $validated) {
            $dispute = $this->vendorDisputeQuery()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($dispute->responded_at || filled($dispute->response)) {
                throw ValidationException::withMessages([
                    'response' => 'Une réponse a déjà été enregistrée. Escaladez le dossier si un complément est nécessaire.',
                ]);
            }

            $dispute->update([
                'response' => $validated['response'],
                'responded_at' => now(),
            ]);
        });

        return back()->with('success', 'Votre réponse a été enregistrée dans le dossier du litige.');
    }

    public function escalate($id, OrderWorkflowService $workflow)
    {
        [$dispute, $justEscalated] = DB::transaction(function () use ($id) {
            $dispute = $this->vendorDisputeQuery()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $justEscalated = ! (bool) $dispute->escalated;

            if ($justEscalated) {
                $dispute->update([
                    'escalated' => true,
                    'escalated_at' => now(),
                ]);
            }

            return [$dispute->refresh(), $justEscalated];
        });

        if ($justEscalated && $dispute->order) {
            $workflow->notify(
                $dispute->order->client,
                'Votre litige a été transmis à OVANIE',
                'La boutique a demandé l’intervention de l’équipe OVANIE pour examiner votre dossier.',
                [
                    'category' => 'disputes',
                    'order_id' => $dispute->order_id,
                    'url' => route('client.orders.show', $dispute->order_id),
                ]
            );
        }

        return back()->with(
            'success',
            $justEscalated
                ? 'Le litige a été transmis à OVANIE pour médiation.'
                : 'Ce litige est déjà en cours d’examen par OVANIE.'
        );
    }

    /**
     * Statut d'affichage en 5 états, partagé avec l'API mobile
     * (VendorMobileController::disputePayload) pour que web et mobile
     * montrent toujours la même progression d'un litige.
     */
    public static function resolveDisplayStatus(Dispute $dispute): string
    {
        $status = strtolower((string) ($dispute->status ?? ''));

        if (in_array($status, ['closed', 'ferme', 'fermé'], true)) {
            return 'closed';
        }

        if (in_array($status, ['resolved', 'resolu', 'résolu'], true)) {
            return 'resolved';
        }

        if ((bool) $dispute->escalated) {
            return 'waiting_ovanie';
        }

        if (filled($dispute->response) || filled($dispute->responded_at)) {
            return 'in_progress';
        }

        return 'new';
    }

    private function exportCsv(Collection $disputes)
    {
        $filename = 'litiges-vendeur-' . now()->format('Ymd-His') . '.csv';

        return Response::streamDownload(function () use ($disputes) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Litige',
                'Commande',
                'Client',
                'Motif',
                'Statut',
                'Ouvert le',
                'Dernière activité',
            ], ';');

            foreach ($disputes as $dispute) {
                fputcsv($handle, [
                    'LTG-' . str_pad((string) $dispute->id, 8, '0', STR_PAD_LEFT),
                    $dispute->order_reference ?: $dispute->order?->order_number,
                    $dispute->client_name ?: $dispute->order?->client?->name,
                    $dispute->reason,
                    $dispute->vendor_display_status,
                    optional($dispute->created_at)->format('d/m/Y H:i'),
                    optional($dispute->updated_at)->format('d/m/Y H:i'),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Même clé de rattachement (shop_id) que l'API mobile
     * (VendorMenuEndpoints::menuCases) et que les retours vendeur
     * (VendorReturnController::vendorReturnQuery), pour que web et mobile
     * affichent toujours la même liste de litiges — y compris si une
     * boutique est un jour gérée par plusieurs comptes.
     */
    private function vendorDisputeQuery(): Builder
    {
        $shop = Auth::user()?->shop;
        abort_unless($shop, 403, 'Boutique introuvable.');

        return Dispute::query()
            ->where(function (Builder $query) use ($shop) {
                $query->where('shop_id', $shop->id)
                    ->orWhere(function (Builder $legacy) use ($shop) {
                        $legacy->whereNull('shop_id')
                            ->whereHas('orderItem', fn (Builder $item) => $item->where('shop_id', $shop->id));
                    });
            });
    }
}
