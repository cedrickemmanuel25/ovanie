<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VendorNotificationController extends Controller
{
    /**
     * Marque une notification appartenant au vendeur connecté comme lue
     * puis ouvre la destination interne enregistrée dans la notification.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user, 401);

        $item = $user->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $item->markAsRead();

        $target = $this->safeInternalTarget(
            $request,
            data_get($item->data, 'url')
        );

        return redirect()->to(
            $target ?: route('vendor.dashboard')
        );
    }

    /**
     * Marque toutes les notifications du vendeur connecté comme lues.
     */
    public function readAll(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user, 401);

        $user->unreadNotifications()
            ->update([
                'read_at' => now(),
            ]);

        return back()->with(
            'success',
            'Toutes vos notifications ont été marquées comme lues.'
        );
    }

    /**
     * Autorise uniquement les URL internes au site.
     */
    private function safeInternalTarget(Request $request, mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return null;
        }

        $host = $parts['host'] ?? null;

        if (! $host || strcasecmp($host, $request->getHost()) !== 0) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $path . $query . $fragment;
    }
}
