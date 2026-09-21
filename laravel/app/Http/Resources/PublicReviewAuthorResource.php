<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PublicReviewAuthorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $firstName = trim((string) ($this->first_name ?: Str::before((string) $this->name, ' ')));
        $lastName = trim((string) ($this->last_name ?: Str::after((string) $this->name, ' ')));
        $displayName = $firstName !== '' ? $firstName : 'Client OVANIE';
        if ($lastName !== '' && $lastName !== $firstName) {
            $displayName .= ' ' . mb_strtoupper(mb_substr($lastName, 0, 1)) . '.';
        }

        return [
            'display_name' => $displayName,
            'avatar_url' => $this->publicAvatarUrl(),
        ];
    }

    private function publicAvatarUrl(): ?string
    {
        $avatar = trim((string) $this->avatar);
        if ($avatar === '') {
            return null;
        }

        return Str::startsWith($avatar, ['https://', 'http://'])
            ? $avatar
            : asset('storage/' . ltrim(Str::after($avatar, 'storage/'), '/'));
    }
}
