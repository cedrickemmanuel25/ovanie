<?php

namespace Tests\Unit;

use App\Models\Shop;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ShopGeoStatusTest extends TestCase
{
    #[DataProvider('statusProvider')]
    public function test_geo_status_labels_and_severity(string $status, string $label, string $severity): void
    {
        $shop = new Shop();
        $shop->geo_status = $status;

        self::assertSame($label, $shop->geo_status_label);
        self::assertSame($severity, $shop->geo_status_severity);
    }

    public static function statusProvider(): array
    {
        return [
            [Shop::GEO_STATUS_VERIFIED, 'Position vérifiée', 'success'],
            [Shop::GEO_STATUS_RELIABLE, 'Position fiable', 'success'],
            [Shop::GEO_STATUS_REVIEW_RECOMMENDED, 'Contrôle recommandé', 'warning'],
            [Shop::GEO_STATUS_VERIFICATION_REQUIRED, 'Vérification requise', 'danger'],
        ];
    }
}
