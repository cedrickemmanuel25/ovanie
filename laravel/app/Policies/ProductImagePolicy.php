<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;

class ProductImagePolicy
{
    public function before(User $user): ?bool
    {
        return ((bool) $user->is_admin || $user->role === 'admin') ? true : null;
    }

    public function create(User $user, Product $product): bool
    {
        return (int) $product->shop?->user_id === (int) $user->id;
    }

    public function update(User $user, ProductImage $image): bool
    {
        return $image->product !== null && $this->create($user, $image->product);
    }

    public function delete(User $user, ProductImage $image): bool
    {
        return $this->update($user, $image);
    }
}
