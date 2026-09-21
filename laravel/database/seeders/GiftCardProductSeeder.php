<?php

namespace Database\Seeders;

use App\Models\GiftCardProduct;
use Illuminate\Database\Seeder;

class GiftCardProductSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Migration douce des 3 anciens slugs de bons d'achat
        |--------------------------------------------------------------------------
        |
        | La V2 utilisait les slugs carte-cadeau-50k / 150k / 200k pour les
        | bons d'achat. On conserve les mêmes lignes (et donc les mêmes ID) en
        | les renommant lorsqu'aucun nouveau slug n'existe encore.
        |
        */
        $this->renameLegacySlug('carte-cadeau-50k', 'bon-achat-50k');
        $this->renameLegacySlug('carte-cadeau-150k', 'bon-achat-150k');
        $this->renameLegacySlug('carte-cadeau-200k', 'bon-achat-200k');

        $cards = [
            // ---------------------------------------------------------------
            // 3 BONS D'ACHAT - VALIDITÉ 30 JOURS
            // ---------------------------------------------------------------
            [
                'name' => 'Bon d’Achat OVANIE 50 000 FCFA',
                'slug' => 'bon-achat-50k',
                'family' => 'fixed',
                'face_value' => 50000,
                'activation_price' => 50000,
                'initial_balance' => 50000,
                'validity_days' => 30,
                'validity_months' => null,
                'is_rechargeable' => false,
                'max_total_recharge' => null,
                'image_path' => 'images/gift-cards/bon-achat-50k.png',
                'description' => 'Économisez sur vos prochains achats avec un bon de réduction OVANIE.',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'Bon d’Achat OVANIE 150 000 FCFA',
                'slug' => 'bon-achat-150k',
                'family' => 'fixed',
                'face_value' => 150000,
                'activation_price' => 150000,
                'initial_balance' => 150000,
                'validity_days' => 30,
                'validity_months' => null,
                'is_rechargeable' => false,
                'max_total_recharge' => null,
                'image_path' => 'images/gift-cards/bon-achat-150k.png',
                'description' => 'Économisez sur vos prochains achats avec un bon de réduction OVANIE.',
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'name' => 'Bon d’Achat OVANIE 200 000 FCFA',
                'slug' => 'bon-achat-200k',
                'family' => 'fixed',
                'face_value' => 200000,
                'activation_price' => 200000,
                'initial_balance' => 200000,
                'validity_days' => 30,
                'validity_months' => null,
                'is_rechargeable' => false,
                'max_total_recharge' => null,
                'image_path' => 'images/gift-cards/bon-achat-200k.png',
                'description' => 'Économisez sur vos prochains achats avec un bon de réduction OVANIE.',
                'sort_order' => 30,
                'is_active' => true,
            ],

            // ---------------------------------------------------------------
            // 4 CARTES CADEAU - VALIDITÉ 90 JOURS
            // ---------------------------------------------------------------
            [
                'name' => 'Carte Cadeau OVANIE 100 000 FCFA',
                'slug' => 'carte-cadeau-100k',
                'family' => 'fixed',
                'face_value' => 100000,
                'activation_price' => 100000,
                'initial_balance' => 100000,
                'validity_days' => 90,
                'validity_months' => null,
                'is_rechargeable' => false,
                'max_total_recharge' => null,
                'image_path' => 'images/gift-cards/carte-cadeau-100k.png',
                'description' => 'Offrez un bon shopping à vos proches sur toute la plateforme OVANIE.',
                'sort_order' => 40,
                'is_active' => true,
            ],
            [
                'name' => 'Carte Cadeau OVANIE 200 000 FCFA',
                'slug' => 'carte-cadeau-200k',
                'family' => 'fixed',
                'face_value' => 200000,
                'activation_price' => 200000,
                'initial_balance' => 200000,
                'validity_days' => 90,
                'validity_months' => null,
                'is_rechargeable' => false,
                'max_total_recharge' => null,
                'image_path' => 'images/gift-cards/carte-cadeau-200k.png',
                'description' => 'Offrez un bon shopping à vos proches sur toute la plateforme OVANIE.',
                'sort_order' => 50,
                'is_active' => true,
            ],
            [
                'name' => 'Carte Cadeau OVANIE 300 000 FCFA',
                'slug' => 'carte-cadeau-300k',
                'family' => 'fixed',
                'face_value' => 300000,
                'activation_price' => 300000,
                'initial_balance' => 300000,
                'validity_days' => 90,
                'validity_months' => null,
                'is_rechargeable' => false,
                'max_total_recharge' => null,
                'image_path' => 'images/gift-cards/carte-cadeau-300k.png',
                'description' => 'Offrez un bon shopping à vos proches sur toute la plateforme OVANIE.',
                'sort_order' => 60,
                'is_active' => true,
            ],
            [
                'name' => 'Carte Cadeau OVANIE 400 000 FCFA',
                'slug' => 'carte-cadeau-400k',
                'family' => 'fixed',
                'face_value' => 400000,
                'activation_price' => 400000,
                'initial_balance' => 400000,
                'validity_days' => 90,
                'validity_months' => null,
                'is_rechargeable' => false,
                'max_total_recharge' => null,
                'image_path' => 'images/gift-cards/carte-cadeau-400k.png',
                'description' => 'Offrez un bon shopping à vos proches sur toute la plateforme OVANIE.',
                'sort_order' => 70,
                'is_active' => true,
            ],

            // ---------------------------------------------------------------
            // 3 CARTES VIRTUELLES RECHARGEABLES
            // ---------------------------------------------------------------
            [
                'name' => 'Carte Virtuelle OVANIE ACCÈS',
                'slug' => 'carte-acces-150k',
                'family' => 'rechargeable',
                'face_value' => 150000,
                'activation_price' => 150000,
                'initial_balance' => 150000,
                'validity_days' => null,
                'validity_months' => 12,
                'is_rechargeable' => true,
                'max_total_recharge' => 2500000,
                'image_path' => 'images/gift-cards/carte-virtuelle-acces.png',
                'description' => 'Votre portefeuille OVANIE premium. Rechargez et payez partout.',
                'sort_order' => 80,
                'is_active' => true,
            ],
            [
                'name' => 'Carte Virtuelle OVANIE PREMIUM',
                'slug' => 'carte-premium-250k',
                'family' => 'rechargeable',
                'face_value' => 250000,
                'activation_price' => 250000,
                'initial_balance' => 250000,
                'validity_days' => null,
                'validity_months' => 24,
                'is_rechargeable' => true,
                'max_total_recharge' => 5000000,
                'image_path' => 'images/gift-cards/carte-virtuelle-premium.png',
                'description' => 'Votre portefeuille OVANIE premium. Rechargez et payez partout.',
                'sort_order' => 90,
                'is_active' => true,
            ],
            [
                'name' => 'Carte Virtuelle OVANIE GOLD',
                'slug' => 'carte-gold-450k',
                'family' => 'rechargeable',
                'face_value' => 450000,
                'activation_price' => 450000,
                'initial_balance' => 450000,
                'validity_days' => null,
                'validity_months' => 36,
                'is_rechargeable' => true,
                'max_total_recharge' => null,
                'image_path' => 'images/gift-cards/carte-virtuelle-gold.png',
                'description' => 'Votre portefeuille OVANIE premium. Rechargez et payez partout.',
                'sort_order' => 100,
                'is_active' => true,
            ],
        ];

        foreach ($cards as $card) {
            GiftCardProduct::query()->updateOrCreate(
                ['slug' => $card['slug']],
                $card
            );
        }
    }

    private function renameLegacySlug(string $oldSlug, string $newSlug): void
    {
        // Si le nouveau slug existe déjà, la migration a déjà été faite.
        if (GiftCardProduct::query()->where('slug', $newSlug)->exists()) {
            return;
        }

        $old = GiftCardProduct::query()->where('slug', $oldSlug)->first();

        if (! $old) {
            return;
        }

        $old->update(['slug' => $newSlug]);
    }
}
