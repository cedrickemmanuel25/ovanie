<?php

namespace App\Support\ProForms;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Lit config/ovanie_pro_forms.php : un seul schéma pour afficher les formulaires
 * ACC-04 à ACC-11 et pour les valider côté serveur (RG-46, RG-38, RG-40).
 */
class ProFormSchema
{
    public function profiles(): array
    {
        return (array) config('ovanie_pro.professional_profiles', []);
    }

    public function has(string $profile): bool
    {
        return array_key_exists($profile, (array) config('ovanie_pro_forms.profiles', []));
    }

    public function label(string $profile): string
    {
        return (string) config("ovanie_pro.professional_profiles.$profile.label", $profile);
    }

    /** Statuts admis pour un profil : les 6 profils « société » n'acceptent pas l'indépendant. */
    public function entityTypes(string $profile): array
    {
        return (array) config("ovanie_pro_forms.profiles.$profile.entity_types", ['organization']);
    }

    public function entityLabels(): array
    {
        return collect((array) config('ovanie_pro_forms.entity_types', []))->map(fn ($e) => $e['label'])->all();
    }

    /** Blocs visibles pour un profil et un statut (entreprise / indépendant). */
    public function blocks(string $profile, string $entityType): array
    {
        $blocks = (array) config("ovanie_pro_forms.profiles.$profile.blocks", []);

        return array_filter($blocks, fn (array $block) => ! isset($block['entity']) || $block['entity'] === $entityType);
    }

    /** Champs à plat : nom => définition (avec « required » résolu en booléen). */
    public function fields(string $profile, string $entityType): array
    {
        $fields = [];
        foreach ($this->blocks($profile, $entityType) as $block) {
            foreach ($block['fields'] as $name => $field) {
                $field['required'] = $this->resolveRequired($field['required'] ?? false, $entityType);
                $field['target'] = $field['target'] ?? 'data';
                $fields[$name] = $field;
            }
        }

        return $fields;
    }

    /** Documents demandés, « required » résolu selon le statut. */
    public function documents(string $profile, string $entityType): array
    {
        $documents = (array) config("ovanie_pro_forms.profiles.$profile.documents", []);

        return collect($documents)
            ->map(function (array $doc) use ($entityType) {
                $doc['required'] = $this->resolveRequired($doc['required'] ?? false, $entityType);

                return $doc;
            })
            ->all();
    }

    /** Récapitulatif ACC-03 : documents qui seront demandés, par profil et statut. */
    public function documentSummary(): array
    {
        $summary = [];
        foreach (array_keys((array) config('ovanie_pro_forms.profiles', [])) as $profile) {
            foreach ($this->entityTypes($profile) as $entityType) {
                $summary[$profile][$entityType] = collect($this->documents($profile, $entityType))
                    ->map(fn ($doc) => ['label' => $doc['label'], 'required' => $doc['required']])
                    ->values()
                    ->all();
            }
        }

        return $summary;
    }

    /**
     * Règles de validation. En brouillon, rien n'est obligatoire mais les formats restent contrôlés.
     * Un document déjà fourni n'est jamais redemandé (ACC-19).
     */
    public function rules(string $profile, string $entityType, bool $draft, array $providedDocumentTypes = []): array
    {
        $rules = [];
        foreach ($this->fields($profile, $entityType) as $name => $field) {
            $base = [($field['required'] && ! $draft) ? 'required' : 'nullable'];
            $rules[$name] = array_merge($base, $this->typeRules($field));
            if ($field['type'] === 'checkboxes') {
                $rules[$name . '.*'] = [Rule::in(array_keys($field['options']))];
            }
        }

        $limits = (array) config('ovanie_pro_forms.document_rules', []);
        foreach ($this->documents($profile, $entityType) as $type => $doc) {
            $media = ! empty($doc['media']);
            $fileRule = ['file', 'mimes:' . ($media ? $limits['media_mimes'] : $limits['mimes']), 'max:' . ($media ? $limits['media_max_kb'] : $limits['max_kb'])];
            $mustProvide = $doc['required'] && ! $draft && ! in_array($type, $providedDocumentTypes, true);

            if (! empty($doc['multiple'])) {
                $rules["documents.$type"] = [$mustProvide ? 'required' : 'nullable', 'array', 'max:6'];
                $rules["documents.$type.*"] = $fileRule;
            } else {
                $rules["documents.$type"] = array_merge([$mustProvide ? 'required' : 'nullable'], $fileRule);
            }
        }

        return $rules;
    }

    public function attributes(string $profile, string $entityType): array
    {
        $attributes = collect($this->fields($profile, $entityType))->map(fn ($f) => mb_strtolower($f['label']))->all();
        foreach ($this->documents($profile, $entityType) as $type => $doc) {
            $attributes["documents.$type"] = $doc['label'];
            $attributes["documents.$type.*"] = $doc['label'];
        }

        return $attributes;
    }

    /** Répartit les données validées entre organisation, profil et bloc métier. */
    public function split(array $validated, string $profile, string $entityType): array
    {
        $parts = ['organization' => [], 'profile' => [], 'data' => []];
        foreach ($this->fields($profile, $entityType) as $name => $field) {
            if (array_key_exists($name, $validated)) {
                $value = $validated[$name];
                if (is_string($value)) {
                    $value = trim($value) === '' ? null : trim($value);
                }
                $parts[$field['target']][$name] = $value;
            }
        }

        return $parts;
    }

    /** Champs obligatoires encore vides : sert au résumé « informations manquantes » (ACC-19). */
    public function missing(string $profile, string $entityType, array $values, array $providedDocumentTypes): array
    {
        $missing = [];
        foreach ($this->fields($profile, $entityType) as $name => $field) {
            if ($field['required'] && blank(Arr::get($values, $name))) {
                $missing[] = $field['label'];
            }
        }
        foreach ($this->documents($profile, $entityType) as $type => $doc) {
            if ($doc['required'] && ! in_array($type, $providedDocumentTypes, true)) {
                $missing[] = $doc['label'];
            }
        }

        return $missing;
    }

    private function resolveRequired(bool|string $required, string $entityType): bool
    {
        return is_string($required) ? $required === $entityType : $required;
    }

    private function typeRules(array $field): array
    {
        $year = (int) date('Y');

        return match ($field['type']) {
            'email' => ['email', 'max:255'],
            'tel' => ['string', 'max:40', 'regex:/^[0-9+().\s-]{6,40}$/'],
            'url' => ['url', 'max:255'],
            'number' => ['integer', 'min:0', 'max:1000000000'],
            'year' => ['integer', 'min:1800', "max:$year"],
            'textarea' => ['string', 'max:3000'],
            'select', 'radio' => [Rule::in(array_keys($field['options']))],
            'checkboxes' => ['array'],
            'country' => ['string', Rule::in((array) config('ovanie_pro.countries', []))],
            default => ['string', 'max:180'],
        };
    }
}
