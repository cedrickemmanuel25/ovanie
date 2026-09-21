<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'date' => $this->created_at?->toIso8601String(),
            'verified_purchase' => (bool) ($this->verified_purchase ?? false),
            'author' => new PublicReviewAuthorResource($this->whenLoaded('user')),
        ];
    }
}
