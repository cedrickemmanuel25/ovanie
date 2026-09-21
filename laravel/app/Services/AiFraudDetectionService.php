<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Review;
use App\Models\Product;

class AiFraudDetectionService
{
    /**
     * Score simple de risque commande. Aucun fournisseur externe.
     */
    public function scoreOrder(Order $order): array
    {
        $score = 0;
        $reasons = [];

        $total = (float) ($order->total ?? $order->total_amount ?? 0);

        if ($total >= 1000000) {
            $score += 30;
            $reasons[] = 'Montant élevé';
        }

        if (($order->payment_method ?? null) === 'manual') {
            $score += 15;
            $reasons[] = 'Paiement manuel à vérifier';
        }

        if (empty($order->delivery_address ?? null)) {
            $score += 20;
            $reasons[] = 'Adresse de livraison incomplète';
        }

        return [
            'score' => min($score, 100),
            'risk_level' => $this->riskLevel($score),
            'reasons' => $reasons,
            'requires_manual_review' => $score >= 50,
        ];
    }

    public function scoreReview(Review $review): array
    {
        $score = 0;
        $comment = strtolower((string) ($review->comment ?? ''));

        if (strlen($comment) < 10) {
            $score += 10;
        }

        if (str_contains($comment, 'arnaque') || str_contains($comment, 'faux')) {
            $score += 20;
        }

        return [
            'score' => min($score, 100),
            'risk_level' => $this->riskLevel($score),
        ];
    }

    public function scoreProduct(Product $product): array
    {
        $score = 0;
        $reasons = [];

        if (empty($product->description)) {
            $score += 15;
            $reasons[] = 'Description manquante';
        }

        if ((float) ($product->price ?? 0) <= 0) {
            $score += 30;
            $reasons[] = 'Prix invalide';
        }

        return [
            'score' => min($score, 100),
            'risk_level' => $this->riskLevel($score),
            'reasons' => $reasons,
        ];
    }

    private function riskLevel(int $score): string
    {
        return match (true) {
            $score >= 70 => 'high',
            $score >= 40 => 'medium',
            default => 'low',
        };
    }
}
