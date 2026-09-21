<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\SellerLogisticsValidator;
use Illuminate\Support\Facades\Auth;

class VendorShopStatusController extends Controller
{
    /**
     * Tableau de préparation opérationnelle de la boutique.
     * Le KYC, l'ouverture commerciale et la préparation logistique sont affichés
     * séparément afin de ne pas recréer un faux statut global "en attente".
     */
    public function index(SellerLogisticsValidator $logisticsValidator)
    {
        $shop = Auth::user()?->shop;

        if (! $shop) {
            return redirect()->route('open-shop')
                ->with('error', 'Vous devez créer une boutique avant d’accéder à cette page.');
        }

        $logisticsValidation = $logisticsValidator->validate($shop);
        if ($shop->logistics_status !== $logisticsValidation['status']) {
            $shop = $logisticsValidator->synchronizeShopStatus($shop);
            $logisticsValidation = $logisticsValidator->validate($shop);
        }

        $commercialOpen = $shop->status === Shop::STATUS_APPROVED && (bool) $shop->is_active;
        $canPublish = $shop->canPublishProducts();

        if ($shop->status === Shop::STATUS_REJECTED) {
            $statusLabel = 'Action administrative requise';
            $statusDescription = 'La boutique fait l’objet d’une restriction administrative. Consultez la raison indiquée et contactez OVANIE si nécessaire.';
            $statusTone = 'rejected';
        } elseif (! $commercialOpen) {
            $statusLabel = 'Boutique inactive';
            $statusDescription = 'L’ouverture commerciale de la boutique est actuellement inactive. Vos obligations sur les commandes existantes restent accessibles.';
            $statusTone = 'inactive';
        } elseif (! $logisticsValidation['complete']) {
            $statusLabel = 'Configuration logistique à compléter';
            $statusDescription = 'La boutique est ouverte, mais les nouveaux produits ne peuvent pas être publiés tant que la configuration logistique n’est pas complète.';
            $statusTone = 'pending';
        } else {
            $statusLabel = 'Boutique opérationnelle';
            $statusDescription = 'La boutique est ouverte et sa configuration logistique permet la publication des produits.';
            $statusTone = 'approved';
        }

        $identityDocumentsComplete = filled($shop->selfie)
            && (filled($shop->identity_file)
                || (filled($shop->identity_file_front) && filled($shop->identity_file_back)));

        $steps = [
            [
                'title' => 'Boutique créée',
                'description' => 'Votre espace vendeur et votre boutique commerciale sont enregistrés.',
                'done' => true,
            ],
            [
                'title' => 'Dossier documentaire',
                'description' => $shop->kyc_status === Shop::KYC_VERIFIED
                    ? 'Les documents sont vérifiés.'
                    : ($identityDocumentsComplete
                        ? 'Les documents requis sont présents et restent suivis séparément du statut commercial.'
                        : 'Complétez les documents d’identité manquants.'),
                'done' => $identityDocumentsComplete,
            ],
            [
                'title' => 'Configuration logistique',
                'description' => $logisticsValidation['complete']
                    ? 'Le mode logistique sélectionné est opérationnel.'
                    : 'Éléments manquants : ' . implode(', ', $logisticsValidation['missing'] ?? []),
                'done' => $logisticsValidation['complete'],
            ],
            [
                'title' => 'Publication produits',
                'description' => $canPublish
                    ? 'La boutique peut publier des produits sur OVANIE.'
                    : 'La publication reste bloquée jusqu’à la résolution des éléments opérationnels manquants.',
                'done' => $canPublish,
            ],
        ];

        return view('vendor.shop-status', compact(
            'shop',
            'statusLabel',
            'statusDescription',
            'statusTone',
            'steps',
            'commercialOpen',
            'canPublish',
            'logisticsValidation'
        ));
    }
}
