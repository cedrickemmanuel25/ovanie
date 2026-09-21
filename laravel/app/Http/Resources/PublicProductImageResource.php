<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->public_url,
            'order' => (int) ($this->sort_order ?? 0),
            'is_main' => (bool) ($this->is_main ?? $this->is_primary),
            'alt' => $this->alt ?: null,
            'product_public_id' => $this->whenLoaded('product', fn () => $this->product?->slug),
        ];
    }
}
