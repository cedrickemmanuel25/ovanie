<?php

namespace App\Services\Geo;

class WazeLinkService
{
    public function navigationUrl(float $latitude, float $longitude): string
    {
        return 'https://waze.com/ul?ll=' . $latitude . ',' . $longitude . '&navigate=yes';
    }
}
