<?php

return [
    'prefix' => 'private-documents',

    'sources' => [
        ['category' => 'vendor-kyc', 'table' => 'shops', 'columns' => ['identity_file', 'identity_file_front', 'identity_file_back', 'selfie', 'rccm_file', 'tax_file']],
        ['category' => 'order-evidence', 'table' => 'orders', 'columns' => ['payment_proof', 'receipt_path']],
        ['category' => 'payment-proof', 'table' => 'payment_proofs', 'columns' => ['file']],
        ['category' => 'delivery-evidence', 'table' => 'order_items', 'columns' => ['pickup_photo', 'delivery_photo', 'vendor_loading_photo', 'vendor_delivery_photo']],
        ['category' => 'delivery-proof', 'table' => 'delivery_proofs', 'columns' => ['proof_photo', 'signature_path']],
        ['category' => 'return-proof', 'table' => 'returns', 'columns' => ['photo_proof']],
        ['category' => 'incident-proof', 'table' => 'delivery_incidents', 'columns' => ['photo_path']],
        ['category' => 'courier-identity', 'table' => 'order_reception_form_items', 'columns' => ['courier_id_front_path', 'courier_id_back_path']],
        ['category' => 'payout-receipt', 'table' => 'vendor_payouts', 'columns' => ['transfer_receipt_path']],
    ],
];
