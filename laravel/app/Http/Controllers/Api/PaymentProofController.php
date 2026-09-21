<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentProofController extends Controller
{
    public function index(Request $request)
    {
        $proofs = PaymentProof::query()
            ->with('payment:id,user_id,order_id')
            ->whereHas('payment', fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest()
            ->get()
            ->map(fn (PaymentProof $proof) => $this->clientPayload($proof));

        return response()->json(['data' => $proofs]);
    }

    public function show(Request $request, $id)
    {
        $proof = PaymentProof::with('payment')->findOrFail($id);
        $this->authorizeProof($request, $proof);

        return response()->json(['data' => $this->clientPayload($proof)]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $payment = Payment::query()->whereKey($validated['payment_id'])->firstOrFail();
        abort_if((int) $payment->user_id !== (int) $request->user()->id, 403);

        if (! $payment->isPending()) {
            throw ValidationException::withMessages([
                'payment_id' => 'Une preuve ne peut être ajoutée qu’à un paiement en attente.',
            ]);
        }

        $path = $request->file('file')->store('payment_proofs', 'local');

        $proof = PaymentProof::create([
            'payment_id' => $payment->id,
            'file' => $path,
            'status' => PaymentProof::STATUS_PENDING,
        ])->load('payment');

        return response()->json(['data' => $this->clientPayload($proof)], 201);
    }

    public function update(Request $request, $id)
    {
        $proof = PaymentProof::with('payment')->findOrFail($id);
        $this->authorizeProof($request, $proof);

        if (! $proof->isPending()) {
            throw ValidationException::withMessages([
                'file' => 'Une preuve déjà traitée ne peut plus être remplacée.',
            ]);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $newPath = $request->file('file')->store('payment_proofs', 'local');
        $oldPath = $proof->file;
        $proof->forceFill(['file' => $newPath])->save();

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json(['data' => $this->clientPayload($proof->refresh())]);
    }

    public function destroy(Request $request, $id)
    {
        $proof = PaymentProof::with('payment')->findOrFail($id);
        $this->authorizeProof($request, $proof);

        if (! $proof->isPending()) {
            throw ValidationException::withMessages([
                'proof' => 'Une preuve déjà traitée ne peut plus être supprimée.',
            ]);
        }

        if ($proof->file) {
            Storage::disk('local')->delete($proof->file);
        }

        $proof->delete();

        return response()->json(['message' => 'Preuve de paiement supprimée.']);
    }

    private function authorizeProof(Request $request, PaymentProof $proof): void
    {
        $proof->loadMissing('payment');
        abort_if(! $proof->payment || (int) $proof->payment->user_id !== (int) $request->user()->id, 403);
    }

    public function download(Request $request, PaymentProof $paymentProof): BinaryFileResponse
    {
        $this->authorizeProof($request, $paymentProof);
        abort_unless($paymentProof->file && Storage::disk('local')->exists($paymentProof->file), 404);

        return response()->download(
            Storage::disk('local')->path($paymentProof->file),
            'preuve-paiement-'.$paymentProof->id.'.'.pathinfo($paymentProof->file, PATHINFO_EXTENSION),
            [
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function clientPayload(PaymentProof $proof): array
    {
        return [
            'id' => $proof->id,
            'payment_id' => $proof->payment_id,
            'status' => $proof->status,
            'file_url' => $proof->file ? route('api.payment-proofs.download', $proof) : null,
            'created_at' => optional($proof->created_at)->toIso8601String(),
            'updated_at' => optional($proof->updated_at)->toIso8601String(),
        ];
    }
}
