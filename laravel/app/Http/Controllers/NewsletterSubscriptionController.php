<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsletterSubscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'newsletter_email' => ['required', 'email:rfc', 'max:255'],
            'newsletter_source' => ['nullable', 'string', 'max:50'],
        ], [
            'newsletter_email.required' => 'Saisissez votre adresse e-mail.',
            'newsletter_email.email' => 'Saisissez une adresse e-mail valide.',
        ]);

        $subscriber = NewsletterSubscriber::query()->updateOrCreate(
            ['email' => Str::lower(trim($validated['newsletter_email']))],
            [
                'status' => 'active',
                'source' => $validated['newsletter_source'] ?? 'homepage_footer',
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]
        );

        $message = $subscriber->wasRecentlyCreated
            ? 'Merci ! Votre inscription aux offres OVANIE est confirmée.'
            : 'Votre abonnement aux offres OVANIE est bien actif.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
            ]);
        }

        return back()
            ->with('newsletter_success', $message)
            ->withFragment('newsletter');
    }
}
