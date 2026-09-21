<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessPublicResource;
use App\Models\AppelOffre;
use App\Models\Devis;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\PayDunyaService;

class BusinessController extends Controller
{
    public function json(Request $request)
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $perPage = min((int) ($validated['per_page'] ?? 20), 50);

        $appels = DB::table('appel_offres')->select([
            'id as internal_id',
            DB::raw("'appel_offre' as request_type"),
            DB::raw("'Appel d’offre' as title"),
            'secteur as category',
            'ville as approximate_area',
            'budget',
            'description',
            'delai as deadline',
            'created_at as published_at',
        ]);

        $devis = DB::table('devis')->select([
            'id as internal_id',
            DB::raw("'devis' as request_type"),
            'projet as title',
            'secteur as category',
            'ville as approximate_area',
            'budget',
            'message as description',
            DB::raw('NULL as deadline'),
            'created_at as published_at',
        ]);

        $items = $appels
            ->unionAll($devis)
            ->orderByDesc('published_at')
            ->orderByDesc('internal_id')
            ->orderBy('request_type')
            ->paginate($perPage)
            ->withQueryString();

        return BusinessPublicResource::collection($items);
    }


    public function pay(Request $request, PayDunyaService $paydunya)
    {
        $validated = $request->validate([
            'request_id' => ['required', 'integer', 'min:1'],
            'request_type' => ['required', 'string', 'in:devis,appel_offre'],
        ]);

        $user = Auth::user();
        $businessRequest = $validated['request_type'] === 'devis'
            ? Devis::query()->find($validated['request_id'])
            : AppelOffre::query()->find($validated['request_id']);

        if (! $businessRequest || ($businessRequest->status !== null
            && ! in_array($businessRequest->status, ['active', 'published', 'open'], true))) {
            return response()->json(['message' => 'Demande Business indisponible'], 404);
        }

        $amount = (int) config('services.business.contact_access_price', 5000);

        if ($amount <= 0) {
            return response()->json(['message' => 'Paiement Business indisponible'], 503);
        }

        $successUrl = route('business.pay.success');
        $cancelUrl = route('business.pay.cancel');

        $invoice = $paydunya->createOrderInvoice([
            'item_name' => 'Accès contact OVANIE PRO',
            'description' => 'Paiement pour accéder au contact Business',
            'amount' => $amount,
            'return_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        if ($invoice->create()) {

            $businessContext = [
                'business_request_type' => $validated['request_type'],
                'business_request_id' => $businessRequest->getKey(),
                'user_id' => $user->id,
                'server_price' => $amount,
                'expected_amount' => $amount,
            ];

            if ($companyId = $user->getAttribute('company_id')) {
                $businessContext['company_id'] = $companyId;
            }

            Payment::create([
                'order_id' => null,
                'user_id' => $user->id,
                'amount' => $amount,
                'method' => 'paydunya',
                'status' => 'pending',

                'type' => 'business_contact',

                'reference' => $invoice->token
                    ?? $invoice->invoice_token
                    ?? ($invoice->response_array['token'] ?? null)
                    ?? ($invoice->response['token'] ?? null)
                    ?? 'BUS-' . strtoupper(uniqid()),

                'provider_payload' => $businessContext,
            ]);

            return response()->json([
                'success' => true,
                'redirect_url' => $invoice->getInvoiceUrl(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $invoice->response_text ?? 'Erreur paiement',
        ], 422);
    }

    public function paySuccess(Request $request)
    {
        $token = (string) $request->query('token', '');
        $payment = Payment::query()
            ->where('type', 'business_contact')
            ->where('user_id', $request->user()->id)
            ->where('reference', $token)
            ->first();

        if (! $payment) {
            return redirect()->route('catalog.business')->with('error', 'Paiement Business introuvable');
        }

        return match ($payment->status) {
            Payment::STATUS_PAID,
            Payment::STATUS_ESCROW_HELD,
            Payment::STATUS_RELEASED_TO_VENDOR => redirect()->route('catalog.business')
                ->with('success', 'Paiement confirmé'),
            Payment::STATUS_CANCELLED => redirect()->route('catalog.business')
                ->with('error', 'Paiement annulé'),
            Payment::STATUS_FAILED => redirect()->route('catalog.business')
                ->with('error', 'Paiement échoué'),
            default => redirect()->route('catalog.business')
                ->with('warning', 'Paiement en cours de vérification'),
        };
    }



    public function payCancel()
    {
        return redirect()->route('catalog.business')
            ->with('error', 'Paiement annulé');
    }
}
