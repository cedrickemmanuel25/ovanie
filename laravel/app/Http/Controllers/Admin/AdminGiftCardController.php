<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\GiftCardProduct;
use App\Models\GiftCardTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminGiftCardController extends Controller
{
    public function index(Request $request): View
    {
        $cards = GiftCard::query()
            ->with(['product', 'owner', 'purchaser'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim((string) $request->search);
                $q->where(function ($sub) use ($search) {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('beneficiary_name', 'like', "%{$search}%")
                        ->orWhere('beneficiary_phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $products = GiftCardProduct::query()->orderBy('sort_order')->get();
        $stats = [
            'active' => GiftCard::query()->where('status', GiftCard::STATUS_ACTIVE)->count(),
            'blocked' => GiftCard::query()->where('status', GiftCard::STATUS_BLOCKED)->count(),
            'balance' => (float) GiftCard::query()->whereIn('status', [GiftCard::STATUS_ACTIVE, GiftCard::STATUS_EXHAUSTED])->sum('current_balance'),
            'transactions' => GiftCardTransaction::query()->count(),
        ];

        return view('admin.gift-cards.index', compact('cards', 'products', 'stats'));
    }

    public function updateProduct(Request $request, GiftCardProduct $giftCardProduct): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'activation_price' => ['required', 'numeric', 'min:0'],
            'initial_balance' => ['required', 'numeric', 'min:0'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'validity_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'max_total_recharge' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $giftCardProduct->update([
            ...$data,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Modèle de carte mis à jour.');
    }

    public function block(GiftCard $giftCard): RedirectResponse
    {
        $giftCard->update(['status' => GiftCard::STATUS_BLOCKED]);
        return back()->with('success', 'Carte bloquée.');
    }

    public function unblock(GiftCard $giftCard): RedirectResponse
    {
        if ($giftCard->isExpired()) {
            return back()->with('error', 'Cette carte est expirée et ne peut pas être réactivée.');
        }

        $giftCard->update([
            'status' => (float) $giftCard->current_balance > 0
                ? GiftCard::STATUS_ACTIVE
                : GiftCard::STATUS_EXHAUSTED,
        ]);

        return back()->with('success', 'Carte réactivée.');
    }
}
