<?php

namespace App\Services\Geo\Contracts;

interface GeocoderInterface
{
    public function search(string $query, ?string $countryCode = null): array;

    public function reverse(float $latitude, float $longitude): array;
}
