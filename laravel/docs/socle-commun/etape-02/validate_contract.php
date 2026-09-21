<?php

$contractPath = dirname(__DIR__, 3).'/config/ovanie_contract.php';

if (! is_file($contractPath)) {
    fwrite(STDERR, "[ERREUR] Contrat introuvable: {$contractPath}\n");
    exit(1);
}

$contract = require $contractPath;
$errors = [];

foreach (['definition_version', 'runtime_enabled', 'principles', 'enums', 'entities', 'legacy_mappings'] as $requiredKey) {
    if (! array_key_exists($requiredKey, $contract)) {
        $errors[] = "Clé racine manquante: {$requiredKey}";
    }
}

$enums = $contract['enums'] ?? [];
$entities = $contract['entities'] ?? [];

foreach ($enums as $enumName => $values) {
    if (! is_array($values) || $values === []) {
        $errors[] = "Enum vide ou invalide: {$enumName}";
        continue;
    }
    foreach ($values as $code => $label) {
        if (! is_string($code) || $code === '' || ! is_string($label) || $label === '') {
            $errors[] = "Valeur enum invalide dans {$enumName}";
        }
    }
}

foreach ($entities as $entityName => $entity) {
    if (empty($entity['source_model'])) {
        $errors[] = "source_model manquant pour {$entityName}";
    }
    $fields = $entity['canonical_fields'] ?? null;
    if (! is_array($fields) || $fields === []) {
        $errors[] = "canonical_fields vide pour {$entityName}";
        continue;
    }
    foreach ($fields as $fieldName => $definition) {
        if (empty($definition['type'])) {
            $errors[] = "Type manquant: {$entityName}.{$fieldName}";
        }
        if (isset($definition['enum']) && ! isset($enums[$definition['enum']])) {
            $errors[] = "Enum inconnu {$definition['enum']} utilisé par {$entityName}.{$fieldName}";
        }
    }
}

$expected = [
    'product' => ['product_state', 'commercial_sale_type', 'selling_mode', 'unit'],
    'shop' => ['seller_type', 'company_name', 'legal_form', 'rccm', 'taxpayer_number', 'logistics_type'],
    'client' => ['first_name', 'last_name', 'email', 'phone', 'secondary_phone', 'whatsapp_phone', 'birth_date', 'gender', 'city', 'account_type'],
    'address' => ['type', 'commune', 'quartier', 'address', 'latitude', 'longitude'],
    'driver' => ['onboarding_status', 'availability_status', 'vehicle_type', 'plate', 'availability_days', 'zone_ids'],
    'order' => ['order_status', 'payment_status', 'delivery_provider', 'delivery_status'],
    'order_item_workflow' => ['vendor_status', 'delivery_provider', 'delivery_status', 'reception_status', 'payout_status'],
    'delivery_mission' => ['mission_number', 'driver_id', 'status'],
    'return' => ['status', 'logistics_status', 'reason'],
    'payment' => ['method', 'status', 'amount'],
];

foreach ($expected as $entityName => $fields) {
    if (! isset($entities[$entityName])) {
        $errors[] = "Entité obligatoire absente: {$entityName}";
        continue;
    }
    foreach ($fields as $fieldName) {
        if (! isset($entities[$entityName]['canonical_fields'][$fieldName])) {
            $errors[] = "Champ obligatoire absent: {$entityName}.{$fieldName}";
        }
    }
}

$productDecisions = $entities['product']['decisions'] ?? [];
if (($productDecisions['legacy_sale_type_is_split'] ?? false) !== true) {
    $errors[] = 'La décision de séparation de sale_type n’est pas figée.';
}

if (($contract['runtime_enabled'] ?? true) !== false) {
    $errors[] = 'Étape 2 doit rester déclarative: runtime_enabled doit être false.';
}

if ($errors !== []) {
    fwrite(STDERR, "ÉCHEC — Contrat OVANIE Étape 2 invalide\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }
    exit(1);
}

$fieldCount = 0;
foreach ($entities as $entity) {
    $fieldCount += count($entity['canonical_fields'] ?? []);
}

echo "OK — Contrat OVANIE Étape 2 valide\n";
echo "Version de définition : {$contract['definition_version']}\n";
echo 'Entités : '.count($entities)."\n";
echo 'Enums : '.count($enums)."\n";
echo "Champs canoniques : {$fieldCount}\n";
echo "Runtime : désactivé (définition uniquement)\n";
