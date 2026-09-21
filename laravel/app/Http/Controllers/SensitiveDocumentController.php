<?php

namespace App\Http\Controllers;

use App\Models\DeliveryIncident;
use App\Models\DeliveryProof;
use App\Models\OrderItem;
use App\Models\OrderReceptionFormItem;
use App\Models\ReturnModel;
use App\Models\VendorPayout;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SensitiveDocumentController extends Controller
{
    public function adminOrderItem(OrderItem $item, string $type): Response
    {
        $column = match ($type) {
            'pickup' => 'pickup_photo', 'delivery' => 'delivery_photo',
            'vendor-loading' => 'vendor_loading_photo', 'vendor-delivery' => 'vendor_delivery_photo',
            default => abort(404),
        };
        return $this->serve($item->{$column});
    }

    public function adminReception(OrderReceptionFormItem $item, string $side): Response
    {
        return $this->serve($side === 'front' ? $item->courier_id_front_path : ($side === 'back' ? $item->courier_id_back_path : abort(404)));
    }

    public function vendorPayout(VendorPayout $payout): Response
    {
        abort_unless((int) $payout->vendor_id === (int) auth()->id(), 403);
        return $this->serve($payout->transfer_receipt_path);
    }

    public function vendorReturn(ReturnModel $return, int $document = 0): Response
    {
        abort_unless((int) $return->vendor_id === (int) auth()->id(), 403);
        $paths = collect([$return->photo_proof]);
        foreach (['documents', 'proofs', 'attachments'] as $key) {
            foreach ((array) data_get($return->meta, $key, []) as $entry) {
                $paths->push(is_string($entry) ? $entry : data_get($entry, 'path'));
            }
        }
        foreach ((array) data_get($return->meta, 'vendor_decision.proofs', []) as $entry) {
            $paths->push(is_string($entry) ? $entry : data_get($entry, 'path'));
        }
        return $this->serve($paths->filter()->unique()->values()->get($document));
    }

    public function clientReturn(ReturnModel $return): Response
    {
        abort_unless((int) $return->client_id === (int) auth()->id(), 403);
        return $this->serve($return->photo_proof);
    }

    public function clientReturnDecisionProof(ReturnModel $return, int $document): Response
    {
        abort_unless((int) $return->client_id === (int) auth()->id(), 403);
        abort_unless((bool) data_get($return->meta, 'vendor_decision.published_at'), 404);

        return $this->serve($this->decisionProofPath($return, $document));
    }

    public function logisticsReturn(ReturnModel $return): Response
    {
        return $this->serve($return->photo_proof);
    }

    public function logisticsReturnDecisionProof(ReturnModel $return, int $document): Response
    {
        return $this->serve($this->decisionProofPath($return, $document));
    }

    private function decisionProofPath(ReturnModel $return, int $document): ?string
    {
        $proof = collect((array) data_get($return->meta, 'vendor_decision.proofs', []))
            ->values()
            ->get($document);

        return is_string($proof) ? $proof : data_get($proof, 'path');
    }

    public function logisticsIncident(DeliveryIncident $incident): Response
    {
        return $this->serve($incident->photo_path);
    }

    public function logisticsProof(DeliveryProof $proof): Response
    {
        return $this->serve($proof->proof_photo);
    }

    private function serve(?string $storedPath): Response
    {
        abort_if(blank($storedPath), 404);
        $path = $this->normalise((string) $storedPath);
        [$disk, $file] = $this->locate($path);
        abort_unless($disk->exists($file), 404);

        return response($disk->get($file), 200, [
            'Content-Type' => $disk->mimeType($file) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="document.'.(pathinfo($file, PATHINFO_EXTENSION) ?: 'bin').'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function locate(string $path): array
    {
        if (Str::startsWith($path, trim(config('private_documents.prefix'), '/').'/')) {
            return [Storage::disk('local'), $path];
        }
        return [Storage::disk('public'), $path];
    }

    private function normalise(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^(?:https?:)?//[^/]+/storage/#i', '', $path) ?: $path;
        return ltrim(Str::after($path, 'public/storage/'), '/');
    }
}
