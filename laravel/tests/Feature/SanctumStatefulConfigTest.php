<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Garde-fou d'intégration pour SanctumStatefulDomainsTest (tests/Unit) :
 * vérifie que config/sanctum.php appelle bien SanctumStatefulDomains::merge()
 * et que les hôtes de développement local sont donc réellement présents
 * dans la configuration effective de l'application, quel que soit ce que
 * contient .env.
 */
class SanctumStatefulConfigTest extends TestCase
{
    public function test_local_dev_hosts_are_always_present_in_the_resolved_sanctum_config(): void
    {
        $stateful = config('sanctum.stateful');

        $this->assertIsArray($stateful);
        $this->assertContains('127.0.0.1', $stateful);
        $this->assertContains('127.0.0.1:8000', $stateful);
        $this->assertContains('localhost', $stateful);
        $this->assertContains('localhost:8000', $stateful);
    }
}
