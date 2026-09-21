<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Les URI qui doivent être exemptées de vérification CSRF.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Par exemple, si tu veux exempté certaines routes API ou webhook
        // '/api/*',
        // '/stripe/webhook',
    ];
}
