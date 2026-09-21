<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class BusinessPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->publicIdentifier(),
            'request_type' => (string) $this->request_type,
            'title' => $this->cleanText($this->title ?: 'Demande Business'),
            'category' => $this->cleanText($this->category ?: 'Autre'),
            'approximate_area' => $this->cleanText($this->approximate_area ?: 'Zone non précisée'),
            'budget_range' => $this->budgetRange(),
            'description' => $this->cleanDescription((string) $this->description),
            'deadline' => $this->deadline ? (int) $this->deadline : null,
            'published_at' => $this->published_at,
            'public_status' => 'published',
        ];
    }

    private function publicIdentifier(): string
    {
        $value = $this->request_type . ':' . $this->internal_id;
        $secret = (string) config('app.key');

        return 'biz_' . substr(hash_hmac('sha256', $value, $secret), 0, 20);
    }

    private function budgetRange(): ?string
    {
        $budget = (float) $this->budget;

        if ($budget <= 0) {
            return null;
        }

        $magnitude = 10 ** max(0, strlen((string) (int) $budget) - 1);
        $lower = (int) (floor($budget / $magnitude) * $magnitude);
        $upper = $lower + $magnitude;

        return number_format($lower, 0, ',', ' ') . ' – '
            . number_format($upper, 0, ',', ' ') . ' FCFA';
    }

    private function cleanDescription(string $description): string
    {
        $description = strip_tags($description);
        $description = preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u', '[coordonnée masquée]', $description);
        $description = preg_replace('/(?:\+?\d[\s().-]*){8,}/u', '[coordonnée masquée]', $description);
        $description = preg_replace('/\b(?:adresse|localisation)\s*:\s*[^\r\n]+/iu', '[localisation masquée]', $description);

        return Str::limit($this->cleanText($description), 1000, '…');
    }

    private function cleanText(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');
    }
}
