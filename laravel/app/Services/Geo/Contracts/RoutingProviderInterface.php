<?php

namespace App\Services\Geo\Contracts;

interface RoutingProviderInterface
{
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): array;
}
