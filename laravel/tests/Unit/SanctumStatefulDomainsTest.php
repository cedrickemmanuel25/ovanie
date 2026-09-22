<?php

namespace Tests\Unit;

use App\Support\SanctumStatefulDomains;
use PHPUnit\Framework\TestCase;

/**
 * Bug rapporté par l'utilisateur : le panneau IA renvoyait "Non authentifié"
 * même connecté. Cause : SANCTUM_STATEFUL_DOMAINS dans .env ne listait que
 * le domaine de production (copie d'un .env de prod), donc
 * EnsureFrontendRequestsAreStateful::fromFrontend() ne reconnaissait jamais
 * un `php artisan serve` local comme "frontend" - aucune session ne
 * chargeait pour les requêtes fetch() vers /api/*, d'où le 401. Reproduit
 * et confirmé avec un vrai navigateur (Playwright) avant ce correctif.
 */
class SanctumStatefulDomainsTest extends TestCase
{
    public function test_local_dev_hosts_are_added_even_when_env_only_lists_production(): void
    {
        $result = SanctumStatefulDomains::merge(
            'www.ovanie.com,ovanie.com',
            ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000'],
        );

        $this->assertContains('www.ovanie.com', $result);
        $this->assertContains('ovanie.com', $result);
        $this->assertContains('127.0.0.1', $result);
        $this->assertContains('127.0.0.1:8000', $result);
        $this->assertContains('localhost', $result);
        $this->assertContains('localhost:8000', $result);
    }

    public function test_no_duplicates_when_env_already_lists_a_required_host(): void
    {
        $result = SanctumStatefulDomains::merge(
            'ovanie.com,127.0.0.1',
            ['localhost', '127.0.0.1'],
        );

        $this->assertSame(['ovanie.com', '127.0.0.1', 'localhost'], $result);
    }

    public function test_blank_entries_from_a_trailing_comma_are_dropped(): void
    {
        $result = SanctumStatefulDomains::merge(
            'ovanie.com,',
            ['127.0.0.1'],
        );

        $this->assertSame(['ovanie.com', '127.0.0.1'], $result);
    }
}
