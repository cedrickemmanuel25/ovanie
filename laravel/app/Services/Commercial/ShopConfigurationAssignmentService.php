<?php

namespace App\Services\Commercial;

use App\Models\Shop;
use App\Models\ShopConfigurationAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopConfigurationAssignmentService
{
    public function assign(Shop $shop, User $commercial, ?User $admin, array $tasks, ?string $notes = null): ShopConfigurationAssignment
    {
        if ($commercial->role !== 'commercial' || $commercial->status !== 'active') {
            throw ValidationException::withMessages(['commercial_id' => 'Sélectionnez un commercial actif.']);
        }

        if (! ($tasks['configure_logistics'] ?? false) && ! ($tasks['configure_payout'] ?? false) && ! ($tasks['manage_products'] ?? false)) {
            throw ValidationException::withMessages(['tasks' => 'Sélectionnez au moins une tâche pour cette mission.']);
        }

        return DB::transaction(function () use ($shop, $commercial, $admin, $tasks, $notes) {
            ShopConfigurationAssignment::query()
                ->where('shop_id', $shop->id)
                ->whereIn('status', [ShopConfigurationAssignment::STATUS_PENDING, ShopConfigurationAssignment::STATUS_IN_PROGRESS])
                ->lockForUpdate()
                ->update([
                    'status' => ShopConfigurationAssignment::STATUS_CANCELLED,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            return ShopConfigurationAssignment::create([
                'shop_id' => $shop->id,
                'commercial_id' => $commercial->id,
                'assigned_by_admin_id' => $admin?->id,
                'configure_logistics' => (bool) ($tasks['configure_logistics'] ?? false),
                'configure_payout' => (bool) ($tasks['configure_payout'] ?? false),
                'manage_products' => (bool) ($tasks['manage_products'] ?? false),
                'status' => ShopConfigurationAssignment::STATUS_PENDING,
                'assigned_at' => now(),
                'notes' => filled($notes) ? trim($notes) : null,
            ]);
        });
    }

    public function start(ShopConfigurationAssignment $assignment): ShopConfigurationAssignment
    {
        if ($assignment->status === ShopConfigurationAssignment::STATUS_PENDING) {
            $assignment->forceFill([
                'status' => ShopConfigurationAssignment::STATUS_IN_PROGRESS,
                'started_at' => $assignment->started_at ?: now(),
            ])->save();
        }
        return $assignment->refresh();
    }

    public function sync(ShopConfigurationAssignment $assignment): ShopConfigurationAssignment
    {
        $assignment->loadMissing('shop');
        $shop = $assignment->shop;
        $changes = [];

        if ($assignment->configure_logistics && ! $assignment->logistics_completed_at && $shop?->hasConfirmedLogistics()) {
            $changes['logistics_completed_at'] = now();
        }
        if ($assignment->configure_payout && ! $assignment->payout_completed_at && $shop?->hasConfiguredPayout()) {
            $changes['payout_completed_at'] = now();
        }
        if ($changes !== []) {
            $assignment->forceFill($changes)->save();
        }
        return $assignment->refresh();
    }

    public function completeProducts(ShopConfigurationAssignment $assignment): ShopConfigurationAssignment
    {
        if (! $assignment->manage_products) {
            throw ValidationException::withMessages(['products' => 'Cette mission ne contient pas de tâche produits.']);
        }

        $assignment = $this->start($assignment);
        if (! $assignment->products_completed_at) {
            $assignment->forceFill(['products_completed_at' => now()])->save();
        }

        return $assignment->refresh();
    }

    public function complete(ShopConfigurationAssignment $assignment): ShopConfigurationAssignment
    {
        $assignment = $this->sync($assignment);
        if (! $assignment->requiredConfigurationComplete()) {
            throw ValidationException::withMessages(['mission' => 'Terminez toutes les tâches affectées (logistique, reversement et/ou produits) avant de clôturer la mission.']);
        }
        $assignment->forceFill([
            'status' => ShopConfigurationAssignment::STATUS_COMPLETED,
            'completed_at' => now(),
        ])->save();
        return $assignment->refresh();
    }
}
