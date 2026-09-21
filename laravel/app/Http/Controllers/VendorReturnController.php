<?php

namespace App\Http\Controllers;

use App\Models\ReturnModel;
use App\Services\OrderWorkflowService;
use App\Services\ReturnRefundService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:vendor']);
    }

    /**
     * Tableau de bord vendeur des retours et remboursements.
     * Les statistiques sont calculées sur l'ensemble des demandes appartenant
     * réellement à la boutique connectée. Les filtres ne modifient que la table.
     */
    public function index(Request $request): mixed
    {
        $shop = Auth::user()?->shop;
        abort_unless($shop, 403, 'Boutique introuvable.');

        $filters = $this->validatedFilters($request);
        $baseQuery = $this->vendorReturnQuery();

        $tableQuery = $this->applyFilters(
            (clone $baseQuery)->with(['order', 'orderItem.product', 'client']),
            $filters
        )->latest('request_date')->latest('id');

        // L'export réutilise la route GET existante /vendeur/returns.
        if ($request->string('export')->toString() === 'csv') {
            return $this->exportCsv((clone $tableQuery));
        }

        $perPage = in_array((int) $request->query('per_page', 10), [10, 20, 50], true)
            ? (int) $request->query('per_page', 10)
            : 10;

        $returns = $tableQuery->paginate($perPage)->withQueryString();

        $stats = $this->buildStats(clone $baseQuery);
        $chart = $this->buildThirtyDayChart(clone $baseQuery);
        $recentCase = $this->resolveRecentCase($request, clone $baseQuery);
        $timeline = $this->buildTimeline($recentCase);
        $documents = $this->buildDocuments($recentCase);
        $policy = $this->returnPolicy();

        return view('vendor.returns', compact(
            'returns',
            'filters',
            'stats',
            'chart',
            'recentCase',
            'timeline',
            'documents',
            'policy'
        ));
    }

    public function accept(Request $request, $id, ReturnRefundService $returnRefunds)
    {
        $validated = $request->validate([
            'vendor_response' => ['nullable', 'string', 'max:1000'],
        ]);

        [$return, $changed] = DB::transaction(function () use ($id, $returnRefunds, $validated) {
            $return = $this->vendorReturnQuery()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($return->status !== ReturnModel::STATUS_PENDING) {
                return [$return, false];
            }

            $logisticsStatus = method_exists($returnRefunds, 'acceptedLogisticsStatus')
                ? $returnRefunds->acceptedLogisticsStatus($return)
                : $this->fallbackAcceptedLogisticsStatus($return);

            $response = trim((string) ($validated['vendor_response'] ?? ''))
                ?: 'Retour accepté par la boutique. OVANIE Logistics doit confirmer les informations avant transmission au client.';

            $meta = is_array($return->meta) ? $return->meta : [];
            $meta['vendor_decision'] = [
                'type' => 'accept',
                'label' => 'Retour accepté',
                'response' => $response,
                'decided_at' => now()->toIso8601String(),
                'decided_by' => Auth::id(),
                'published_at' => null,
                'published_by' => null,
                'proofs' => (array) data_get($meta, 'vendor_decision.proofs', []),
            ];

            $return->update([
                'status' => ReturnModel::STATUS_ACCEPTED,
                'logistics_status' => $logisticsStatus,
                'vendor_response' => $response,
                'accepted_at' => now(),
                'meta' => $meta,
            ]);

            if ($return->orderItem) {
                $return->orderItem->forceFill([
                    'return_status' => ReturnModel::STATUS_ACCEPTED,
                    'payout_status' => 'blocked',
                ])->save();

                app(OrderWorkflowService::class)->recordHistory(
                    $return->order,
                    $return->orderItem,
                    'return',
                    ReturnModel::STATUS_PENDING,
                    ReturnModel::STATUS_ACCEPTED,
                    [
                        'actor_type' => 'vendor',
                        'user_id' => Auth::id(),
                        'label' => 'Retour accepté par la boutique',
                        'message' => $response.' En attente de confirmation par OVANIE Logistics avant information du client.',
                    ]
                );
            }

            return [$return->refresh(), true];
        });

        if (! $changed) {
            return back()->with('info', 'Cette demande a déjà été traitée.');
        }

        return back()->with('success', 'Retour accepté. La décision a été transmise à OVANIE Logistics pour confirmation au client.');
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'vendor_response' => ['required', 'string', 'min:10', 'max:1000'],
            'vendor_rejection_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $proof = $request->file('vendor_rejection_proof');
        $proofPath = $proof->store('private-documents/return-vendor-proof', 'local');

        [$return, $changed] = DB::transaction(function () use ($id, $validated, $proof, $proofPath) {
            $return = $this->vendorReturnQuery()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($return->status !== ReturnModel::STATUS_PENDING) {
                return [$return, false];
            }

            $meta = is_array($return->meta) ? $return->meta : [];
            $meta['vendor_decision'] = [
                'type' => 'reject',
                'label' => 'Retour rejeté',
                'response' => $validated['vendor_response'],
                'decided_at' => now()->toIso8601String(),
                'decided_by' => Auth::id(),
                'published_at' => null,
                'published_by' => null,
                'proofs' => [[
                    'path' => $proofPath,
                    'name' => $proof->getClientOriginalName(),
                    'type' => strtolower((string) $proof->getClientOriginalExtension()),
                    'date' => now()->toIso8601String(),
                ]],
            ];

            $return->update([
                'status' => ReturnModel::STATUS_REJECTED,
                'logistics_status' => 'not_required',
                'vendor_response' => $validated['vendor_response'],
                'rejected_at' => now(),
                'resolved_at' => now(),
                'meta' => $meta,
            ]);

            if ($return->orderItem) {
                $return->orderItem->forceFill([
                    'return_status' => ReturnModel::STATUS_REJECTED,
                ])->save();

                $workflow = app(OrderWorkflowService::class);
                $workflow->recordHistory(
                    $return->order,
                    $return->orderItem,
                    'return',
                    ReturnModel::STATUS_PENDING,
                    ReturnModel::STATUS_REJECTED,
                    [
                        'actor_type' => 'vendor',
                        'user_id' => Auth::id(),
                        'label' => 'Retour refusé par la boutique',
                        'message' => $validated['vendor_response'].' Une preuve a été jointe. En attente de validation logistique avant transmission au client.',
                    ]
                );
                $workflow->markPayoutsReadyWhenEligible($return->order);
            }

            return [$return->refresh(), true];
        });

        if (! $changed) {
            return back()->with('info', 'Cette demande a déjà été traitée.');
        }

        return back()->with('success', 'Refus enregistré avec preuve. OVANIE Logistics doit maintenant transmettre le rejet au client.');
    }

    public function refund(Request $request, $id, ReturnRefundService $returnRefunds)
    {
        $validated = $request->validate([
            'vendor_response' => ['nullable', 'string', 'max:1000'],
        ]);

        [$return, $changed] = DB::transaction(function () use ($id, $returnRefunds, $validated) {
            $return = $this->vendorReturnQuery()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($return->status !== ReturnModel::STATUS_PENDING) {
                return [$return, false];
            }

            $response = trim((string) ($validated['vendor_response'] ?? ''))
                ?: 'La boutique a décidé de rembourser le client. OVANIE Logistics doit confirmer et poursuivre le traitement financier.';

            $return->return_type = 'refund';
            $logisticsStatus = $returnRefunds->acceptedLogisticsStatus($return);
            $meta = is_array($return->meta) ? $return->meta : [];
            $meta['vendor_decision'] = [
                'type' => 'refund',
                'label' => 'Remboursement décidé',
                'response' => $response,
                'decided_at' => now()->toIso8601String(),
                'decided_by' => Auth::id(),
                'published_at' => null,
                'published_by' => null,
                'proofs' => [],
            ];

            $return->forceFill([
                'return_type' => 'refund',
                'status' => ReturnModel::STATUS_ACCEPTED,
                'logistics_status' => $logisticsStatus,
                'vendor_response' => $response,
                'accepted_at' => now(),
                'meta' => $meta,
            ])->save();

            if ($return->orderItem) {
                $return->orderItem->forceFill([
                    'return_status' => ReturnModel::STATUS_ACCEPTED,
                    'payout_status' => 'blocked',
                ])->save();

                app(OrderWorkflowService::class)->recordHistory(
                    $return->order,
                    $return->orderItem,
                    'return_refund',
                    ReturnModel::STATUS_PENDING,
                    ReturnModel::LOGISTICS_REFUND_REVIEW,
                    [
                        'actor_type' => 'vendor',
                        'user_id' => Auth::id(),
                        'label' => 'Remboursement décidé par la boutique',
                        'message' => $response.' En attente de confirmation OVANIE Logistics.',
                    ]
                );
            }

            return [$return->refresh(), true];
        });

        if (! $changed) {
            return back()->with('info', 'Cette demande a déjà été traitée.');
        }

        return back()->with('success', 'Décision de remboursement transmise à OVANIE Logistics.');
    }

    private function validatedFilters(Request $request): array
    {
        $status = trim((string) $request->query('status', ''));
        $type = trim((string) $request->query('type', ''));
        $search = trim((string) $request->query('q', ''));

        $allowedStatuses = [
            ReturnModel::STATUS_PENDING,
            ReturnModel::STATUS_ACCEPTED,
            ReturnModel::STATUS_REJECTED,
            ReturnModel::STATUS_CLOSED,
            ReturnModel::STATUS_REFUNDED,
            'resolved',
        ];

        $allowedTypes = ['return', 'claim', 'refund'];

        return [
            'q' => mb_substr($search, 0, 120),
            'status' => in_array($status, $allowedStatuses, true) ? $status : '',
            'type' => in_array($type, $allowedTypes, true) ? $type : '',
            'from' => $this->validDate((string) $request->query('from', '')),
            'to' => $this->validDate((string) $request->query('to', '')),
        ];
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        if ($filters['q'] !== '') {
            $search = $filters['q'];

            $query->where(function (Builder $builder) use ($search) {
                $builder->where('order_reference', 'like', "%{$search}%")
                    ->orWhere('product_name', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('order', fn (Builder $order) =>
                        $order->where('order_number', 'like', "%{$search}%")
                    )
                    ->orWhereHas('client', function (Builder $client) use ($search) {
                        $client->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['type'] !== '') {
            $query->where('return_type', $filters['type']);
        }

        if ($filters['from']) {
            $query->whereDate('request_date', '>=', $filters['from']);
        }

        if ($filters['to']) {
            $query->whereDate('request_date', '<=', $filters['to']);
        }

        return $query;
    }

    private function buildStats(Builder $query): array
    {
        $openStatuses = [ReturnModel::STATUS_PENDING, ReturnModel::STATUS_ACCEPTED];
        $inspectionStatuses = ['return_received', 'claim_review', 'refund_review'];

        $totalReturns = (clone $query)->count();
        $open = (clone $query)->whereIn('status', $openStatuses)->count();
        $toValidate = (clone $query)->where('status', ReturnModel::STATUS_PENDING)->count();
        $refunded = (clone $query)->where('status', ReturnModel::STATUS_REFUNDED)->count();
        $rejected = (clone $query)->where('status', ReturnModel::STATUS_REJECTED)->count();
        $accepted = (clone $query)->where('status', ReturnModel::STATUS_ACCEPTED)->count();
        $inInspection = (clone $query)->whereIn('logistics_status', $inspectionStatuses)->count();

        $refundedAmount = (float) (clone $query)
            ->where('status', ReturnModel::STATUS_REFUNDED)
            ->sum('refund_amount');

        $returnedItems = (int) (clone $query)
            ->whereIn('status', [
                ReturnModel::STATUS_ACCEPTED,
                ReturnModel::STATUS_CLOSED,
                ReturnModel::STATUS_REFUNDED,
            ])
            ->sum('quantity');

        return [
            'total' => $totalReturns,
            'open' => $open,
            'to_validate' => $toValidate,
            'refunded' => $refunded,
            'rejected' => $rejected,
            'accepted' => $accepted,
            'in_inspection' => $inInspection,
            'refunded_amount' => $refundedAmount,
            'returned_items' => $returnedItems,
            'linked_disputes' => $this->linkedDisputesCount(clone $query),
        ];
    }

    private function buildThirtyDayChart(Builder $query): array
    {
        $today = now()->startOfDay();
        $currentStart = $today->copy()->subDays(29);
        $previousStart = $currentStart->copy()->subDays(30);
        $previousEnd = $currentStart->copy()->subDay()->endOfDay();

        $currentDates = (clone $query)
            ->whereDate('request_date', '>=', $currentStart->toDateString())
            ->whereDate('request_date', '<=', $today->toDateString())
            ->pluck('request_date')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            ->countBy();

        $labels = [];
        $values = [];
        for ($i = 0; $i < 30; $i++) {
            $date = $currentStart->copy()->addDays($i);
            $key = $date->format('Y-m-d');
            $labels[] = $date->locale('fr')->translatedFormat('d M');
            $values[] = (int) ($currentDates[$key] ?? 0);
        }

        $currentTotal = array_sum($values);
        $previousTotal = (clone $query)
            ->whereDate('request_date', '>=', $previousStart->toDateString())
            ->whereDate('request_date', '<=', $previousEnd->toDateString())
            ->count();

        $change = $previousTotal > 0
            ? round((($currentTotal - $previousTotal) / $previousTotal) * 100, 1)
            : ($currentTotal > 0 ? 100.0 : 0.0);

        return [
            'labels' => $labels,
            'values' => $values,
            'total' => $currentTotal,
            'previous_total' => $previousTotal,
            'change_percent' => $change,
        ];
    }

    private function resolveRecentCase(Request $request, Builder $query): ?ReturnModel
    {
        $selectedId = (int) $request->query('selected', 0);

        $builder = (clone $query)->with(['order', 'orderItem.product', 'client']);

        if ($selectedId > 0) {
            $selected = (clone $builder)->whereKey($selectedId)->first();
            if ($selected) {
                return $selected;
            }
        }

        return $builder->latest('request_date')->latest('id')->first();
    }

    private function buildTimeline(?ReturnModel $return): array
    {
        if (! $return) {
            return [];
        }

        $events = collect();

        $events->push([
            'title' => 'Demande de retour reçue',
            'message' => 'La demande a été enregistrée et transmise pour traitement.',
            'date' => $return->request_date ?: $return->created_at,
            'tone' => 'blue',
        ]);

        if ($return->accepted_at) {
            $events->push([
                'title' => 'Retour validé',
                'message' => $return->vendor_response ?: 'La demande de retour a été acceptée.',
                'date' => $return->accepted_at,
                'tone' => 'green',
            ]);
        }

        if ($return->rejected_at) {
            $events->push([
                'title' => 'Demande refusée',
                'message' => $return->vendor_response ?: 'La demande de retour a été refusée.',
                'date' => $return->rejected_at,
                'tone' => 'red',
            ]);
        }

        if ($return->refund_prepared_at) {
            $events->push([
                'title' => 'Remboursement préparé',
                'message' => 'Le montant du remboursement a été calculé et préparé.',
                'date' => $return->refund_prepared_at,
                'tone' => 'blue',
            ]);
        }

        if ($return->refunded_at) {
            $events->push([
                'title' => 'Remboursement effectué',
                'message' => 'Le remboursement a été traité avec succès.',
                'date' => $return->refunded_at,
                'tone' => 'green',
            ]);
        }

        if ($return->resolved_at && ! $return->refunded_at && ! $return->rejected_at) {
            $events->push([
                'title' => 'Dossier clôturé',
                'message' => 'Le traitement du dossier est terminé.',
                'date' => $return->resolved_at,
                'tone' => 'green',
            ]);
        }

        // Complète la chronologie avec les historiques métier existants de la commande.
        try {
            if ($return->order && method_exists($return->order, 'statusHistories')) {
                $historyQuery = $return->order->statusHistories()
                    ->whereIn('status_type', ['return', 'return_logistics', 'return_refund']);

                if ($return->order_item_id) {
                    $historyQuery->where(function ($q) use ($return) {
                        $q->where('order_item_id', $return->order_item_id)
                            ->orWhereNull('order_item_id');
                    });
                }

                foreach ($historyQuery->oldest('created_at')->get() as $history) {
                    $events->push([
                        'title' => $history->label ?: 'Mise à jour du dossier',
                        'message' => $history->message ?: 'Le statut du dossier a été mis à jour.',
                        'date' => $history->created_at,
                        'tone' => str_contains((string) $history->new_status, 'reject') ? 'red' : 'green',
                    ]);
                }
            }
        } catch (\Throwable) {
            // La page reste fonctionnelle même si une ancienne base ne possède pas encore l'historique complet.
        }

        return $events
            ->filter(fn ($event) => ! empty($event['date']))
            ->unique(fn ($event) => implode('|', [
                (string) $event['title'],
                Carbon::parse($event['date'])->format('Y-m-d H:i'),
            ]))
            ->sortBy(fn ($event) => Carbon::parse($event['date'])->timestamp)
            ->values()
            ->all();
    }

    private function buildDocuments(?ReturnModel $return): array
    {
        if (! $return) {
            return [];
        }

        $documents = collect();

        if (filled($return->photo_proof)) {
            $documents->push([
                'name' => 'Photo de preuve du retour',
                'path' => (string) $return->photo_proof,
                'type' => pathinfo((string) $return->photo_proof, PATHINFO_EXTENSION) ?: 'image',
                'date' => $return->request_date ?: $return->created_at,
            ]);
        }

        $meta = is_array($return->meta) ? $return->meta : [];
        foreach (['documents', 'proofs', 'attachments'] as $key) {
            foreach ((array) ($meta[$key] ?? []) as $index => $document) {
                if (is_string($document)) {
                    $documents->push([
                        'name' => basename($document),
                        'path' => $document,
                        'type' => pathinfo($document, PATHINFO_EXTENSION) ?: 'fichier',
                        'date' => $return->updated_at,
                    ]);
                    continue;
                }

                if (is_array($document) && filled($document['path'] ?? $document['url'] ?? null)) {
                    $path = (string) ($document['path'] ?? $document['url']);
                    $documents->push([
                        'name' => (string) ($document['name'] ?? basename($path) ?: 'Document '.($index + 1)),
                        'path' => $path,
                        'type' => (string) ($document['type'] ?? pathinfo($path, PATHINFO_EXTENSION) ?: 'fichier'),
                        'date' => $document['date'] ?? $return->updated_at,
                    ]);
                }
            }
        }


        foreach ((array) data_get($meta, 'vendor_decision.proofs', []) as $index => $document) {
            if (is_string($document)) {
                $documents->push([
                    'name' => basename($document),
                    'path' => $document,
                    'type' => pathinfo($document, PATHINFO_EXTENSION) ?: 'fichier',
                    'date' => data_get($meta, 'vendor_decision.decided_at') ?: $return->updated_at,
                ]);
                continue;
            }

            if (is_array($document) && filled($document['path'] ?? null)) {
                $path = (string) $document['path'];
                $documents->push([
                    'name' => (string) ($document['name'] ?? basename($path) ?: 'Preuve vendeur '.($index + 1)),
                    'path' => $path,
                    'type' => (string) ($document['type'] ?? pathinfo($path, PATHINFO_EXTENSION) ?: 'fichier'),
                    'date' => $document['date'] ?? data_get($meta, 'vendor_decision.decided_at') ?? $return->updated_at,
                ]);
            }
        }

        return $documents->unique('path')->values()->all();
    }

    private function linkedDisputesCount(Builder $returnQuery): int
    {
        if (! class_exists(\App\Models\Dispute::class) || ! Schema::hasTable('disputes')) {
            return 0;
        }

        try {
            $query = \App\Models\Dispute::query();

            if (Schema::hasColumn('disputes', 'vendor_id')) {
                $query->where('vendor_id', Auth::id());
            }

            if (Schema::hasColumn('disputes', 'order_reference')) {
                $references = (clone $returnQuery)
                    ->whereNotNull('order_reference')
                    ->pluck('order_reference')
                    ->filter()
                    ->unique()
                    ->values();

                if ($references->isEmpty()) {
                    return 0;
                }

                $query->whereIn('order_reference', $references->all());
            }

            return $query->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function returnPolicy(): array
    {
        return [
            'active' => true,
            'window_days' => (int) config('returns.window_days', 14),
            'refund_method' => (string) config('returns.refund_method_label', 'Remboursement selon le moyen de paiement initial'),
            'after_sales' => (bool) config('returns.after_sales_enabled', true),
            'return_fee_payer' => (string) config('returns.return_fee_payer_label', 'Selon le motif du retour'),
        ];
    }

    private function exportCsv(Builder $query): StreamedResponse
    {
        $fileName = 'retours-remboursements-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Date', 'Référence retour', 'Commande', 'Client', 'Produit',
                'Motif', 'Montant', 'Statut', 'Type',
            ], ';');

            $query->chunkById(200, function ($returns) use ($handle) {
                foreach ($returns as $return) {
                    fputcsv($handle, [
                        optional($return->request_date ?: $return->created_at)->format('d/m/Y'),
                        $this->returnReference($return),
                        $return->order?->order_number ?: $return->order_reference,
                        $return->client?->name,
                        $return->orderItem?->product?->name ?: $return->product_name,
                        $return->reason,
                        number_format((float) $return->refund_amount, 0, ',', ' '),
                        $return->status,
                        $return->return_type,
                    ], ';');
                }
            }, 'id');

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function returnReference(ReturnModel $return): string
    {
        $date = Carbon::parse($return->request_date ?: $return->created_at ?: now())->format('Ymd');

        return 'RET-'.$date.'-'.str_pad((string) $return->id, 4, '0', STR_PAD_LEFT);
    }

    private function fallbackAcceptedLogisticsStatus(ReturnModel $return): string
    {
        $provider = $return->orderItem?->delivery_provider;

        return in_array($provider, [
            OrderWorkflowService::PROVIDER_OVANIE,
            defined(OrderWorkflowService::class.'::PROVIDER_PARTNER')
                ? constant(OrderWorkflowService::class.'::PROVIDER_PARTNER')
                : 'partner',
        ], true)
            ? ReturnModel::LOGISTICS_PENDING_PICKUP
            : 'not_required';
    }

    private function validDate(string $date): ?string
    {
        if (trim($date) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function notifyClient(ReturnModel $return, string $title, string $message): void
    {
        $return->loadMissing(['client', 'order']);

        app(OrderWorkflowService::class)->notify($return->client, $title, $message, [
            'category' => 'returns',
            'order_id' => $return->order_id,
            'order_item_id' => $return->order_item_id,
            'url' => route('client.returns'),
        ]);
    }

    private function vendorReturnQuery(): Builder
    {
        $shop = Auth::user()?->shop;
        abort_unless($shop, 403, 'Boutique introuvable.');

        return ReturnModel::query()
            ->where(function (Builder $query) use ($shop) {
                $query->where('shop_id', $shop->id)
                    ->orWhereHas('orderItem', function (Builder $itemQuery) use ($shop) {
                        $itemQuery->where('shop_id', $shop->id)
                            ->whereNotNull('vendor_visible_at');
                    });
            });
    }
}
