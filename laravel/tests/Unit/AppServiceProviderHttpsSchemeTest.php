<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * Bug rapporté par l'utilisateur : sur `php artisan serve` local, la page
 * catalogue restait bloquée sur "Chargement des produits…". Cause : le
 * .env local avait APP_ENV=production (copie d'un .env de prod), ce qui
 * faisait forcer https sur tous les asset() alors que le serveur local ne
 * parle pas TLS - chaque requête vers /js/catalog.js, /css/*.css, etc.
 * échouait silencieusement (ERR_CONNECTION_CLOSED), et catalog.js ne
 * s'exécutait donc jamais. Reproduit et confirmé avec un vrai navigateur
 * (Playwright) avant ce correctif.
 */
class AppServiceProviderHttpsSchemeTest extends TestCase
{
    public function test_never_forces_https_for_a_real_local_dev_request_even_in_production_env(): void
    {
        $this->assertFalse(AppServiceProvider::shouldForceHttpsScheme(
            runningInConsole: false,
            requestHost: '127.0.0.1',
            appUrl: 'https://www.ovanie.com',
            isProductionEnv: true,
        ));

        $this->assertFalse(AppServiceProvider::shouldForceHttpsScheme(
            runningInConsole: false,
            requestHost: 'localhost',
            appUrl: 'https://www.ovanie.com',
            isProductionEnv: true,
        ));
    }

    public function test_still_forces_https_for_a_real_request_on_any_other_host_in_production(): void
    {
        $this->assertTrue(AppServiceProvider::shouldForceHttpsScheme(
            runningInConsole: false,
            requestHost: 'www.ovanie.com',
            appUrl: 'https://www.ovanie.com',
            isProductionEnv: true,
        ));
    }

    public function test_still_forces_https_in_console_or_queue_context_even_with_a_local_looking_url(): void
    {
        // Les emails/notifications générés par artisan queue:work doivent
        // toujours pointer vers des liens https en production, peu importe
        // ce que request()->getHost() vaudrait (aucune vraie requête HTTP).
        $this->assertTrue(AppServiceProvider::shouldForceHttpsScheme(
            runningInConsole: true,
            requestHost: '',
            appUrl: 'https://www.ovanie.com',
            isProductionEnv: true,
        ));
    }

    public function test_still_forces_https_for_an_ngrok_url_even_outside_production_env(): void
    {
        $this->assertTrue(AppServiceProvider::shouldForceHttpsScheme(
            runningInConsole: false,
            requestHost: 'abc123.ngrok-free.dev',
            appUrl: 'https://abc123.ngrok-free.dev',
            isProductionEnv: false,
        ));
    }

    public function test_never_forces_https_for_ordinary_local_development(): void
    {
        $this->assertFalse(AppServiceProvider::shouldForceHttpsScheme(
            runningInConsole: false,
            requestHost: '127.0.0.1',
            appUrl: 'http://127.0.0.1:8000',
            isProductionEnv: false,
        ));
    }
}
