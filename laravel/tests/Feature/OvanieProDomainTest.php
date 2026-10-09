<?php

namespace Tests\Feature;

use App\Http\Middleware\OvanieSharedSessionConfig;
use Illuminate\Http\Request;
use Tests\TestCase;

class OvanieProDomainTest extends TestCase
{
    public function test_business_catalog_named_route_points_to_pro_subdomain(): void
    {
        $url = route('catalog.business');

        $this->assertSame('pro.ovanie.com', parse_url($url, PHP_URL_HOST));
        $this->assertSame('/', parse_url($url, PHP_URL_PATH) ?: '/');
    }

    public function test_legacy_business_catalog_url_redirects_to_pro_and_preserves_filters(): void
    {
        $response = $this->get('https://ovanie.com/catalog-business?q=ciment&offer_type=catalogue');

        $response->assertStatus(301);
        $this->assertSame(
            route('catalog.business', ['q' => 'ciment', 'offer_type' => 'catalogue']),
            $response->headers->get('Location')
        );
    }

    public function test_business_action_routes_use_the_pro_subdomain(): void
    {
        foreach (['appel.offre', 'devis.create', 'business.pay', 'business.pay.success', 'business.pay.cancel'] as $name) {
            $this->assertSame(
                'pro.ovanie.com',
                parse_url(route($name), PHP_URL_HOST),
                "La route {$name} doit être canonique sur OVANIE Pro."
            );
        }
    }

    public function test_main_and_pro_hosts_use_the_same_dedicated_web_session_cookie(): void
    {
        $middleware = app(OvanieSharedSessionConfig::class);

        foreach (['ovanie.com', 'www.ovanie.com', 'pro.ovanie.com'] as $host) {
            config([
                'session.domain' => null,
                'session.cookie' => 'temporary_test_cookie',
                'session.secure' => false,
            ]);

            $request = Request::create("https://{$host}/", 'GET');
            $middleware->handle($request, fn () => response('ok'));

            $this->assertSame('.ovanie.com', config('session.domain'));
            $this->assertSame('ovanie_web_session', config('session.cookie'));
            $this->assertTrue(config('session.secure'));
        }
    }

    public function test_unrelated_subdomain_is_not_reconfigured_as_a_shared_web_session_host(): void
    {
        config([
            'session.domain' => null,
            'session.cookie' => 'beta_session',
            'session.secure' => false,
        ]);

        $request = Request::create('https://beta.ovanie.com/', 'GET');
        app(OvanieSharedSessionConfig::class)->handle($request, fn () => response('ok'));

        $this->assertNull(config('session.domain'));
        $this->assertSame('beta_session', config('session.cookie'));
        $this->assertFalse(config('session.secure'));
    }
}
