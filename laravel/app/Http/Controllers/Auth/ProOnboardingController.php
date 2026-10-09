<?php

namespace App\Http\Controllers\Auth;

use App\Models\ProCompanyMembership;
use App\Models\ProDocument;
use App\Models\ProOrganization;
use App\Models\ProProfessionalProfile;
use App\Models\ProUser;
use App\Support\ProForms\ProFormSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** ACC-03 qualification, ACC-04 à ACC-11 formulaires par profil, ACC-12 activation des rôles. */
class ProOnboardingController extends ProAccessController
{
    public function __construct(private readonly ProFormSchema $schema)
    {
    }

    public function showQualification(Request $request): View
    {
        $user = $this->proUser();
        $profile = $user->professionalProfile()->first();
        $preselected = (string) $request->query('profile', '');

        return view('pro.auth.qualification', [
            'user' => $user,
            'profile' => $profile,
            'selectedProfile' => old('professional_profile', $profile?->profile_key ?: ($this->schema->has($preselected) ? $preselected : null)),
            'profiles' => $this->schema->profiles(),
            'entityTypes' => (array) config('ovanie_pro_forms.entity_types', []),
            'compatibility' => collect(array_keys($this->schema->profiles()))->mapWithKeys(fn ($k) => [$k => $this->schema->entityTypes($k)])->all(),
            'documentSummary' => $this->schema->documentSummary(),
            'functions' => (array) config('ovanie_pro_forms.desired_functions', []),
            'presentation' => (array) config('ovanie_pro.profile_presentation', []),
            'countries' => $this->countries(),
            'onboarding' => ! $this->onboardingComplete($user),
        ]);
    }

    public function saveQualification(Request $request): RedirectResponse
    {
        $user = $this->proUser();
        $draft = $request->input('action') === 'draft';
        $required = $draft ? 'nullable' : 'required';

        $data = $request->validate([
            'action' => ['required', Rule::in(['draft', 'continue'])],
            'entity_type' => [$required, Rule::in(array_keys((array) config('ovanie_pro_forms.entity_types', [])))],
            // Même un brouillon doit indiquer le profil : c'est lui qui détermine tout le parcours.
            'professional_profile' => ['required', Rule::in(array_keys($this->schema->profiles()))],
            'activity_country' => [$required, Rule::in($this->countries())],
            // « Pays d'enregistrement (si différent) » : par défaut, le pays d'activité.
            'registration_country' => ['nullable', Rule::in($this->countries())],
            'desired_functions' => [$required, 'array'],
            'desired_functions.*' => [Rule::in(array_keys((array) config('ovanie_pro_forms.desired_functions', [])))],
        ], [
            'desired_functions.required' => 'Choisissez au moins une fonction.',
            'registration_country.required' => 'Le pays d’enregistrement est obligatoire pour une entreprise.',
        ], [
            'entity_type' => 'type d’entité', 'professional_profile' => 'profil professionnel',
            'activity_country' => 'pays d’activité', 'registration_country' => 'pays d’enregistrement',
        ]);

        // RG-38 : les profils « société » ne sont pas ouverts au statut indépendant.
        if (! empty($data['professional_profile']) && ! empty($data['entity_type'])
            && ! in_array($data['entity_type'], $this->schema->entityTypes($data['professional_profile']), true)) {
            throw ValidationException::withMessages([
                'professional_profile' => 'Ce profil est réservé aux entreprises. Choisissez « Entreprise / organisation » ou un profil ouvert aux indépendants.',
            ]);
        }

        $profile = $user->professionalProfile()->first() ?? new ProProfessionalProfile(['pro_user_id' => $user->id]);
        $changedPath = $profile->exists && ($profile->profile_key !== ($data['professional_profile'] ?? $profile->profile_key)
            || $profile->entity_type !== ($data['entity_type'] ?? $profile->entity_type));

        $profile->fill(array_filter([
            'profile_key' => $data['professional_profile'] ?? null,
            'entity_type' => $data['entity_type'] ?? null,
            'activity_country' => $data['activity_country'] ?? null,
            'registration_country' => $data['registration_country'] ?? (($data['entity_type'] ?? null) === 'organization' ? ($data['activity_country'] ?? null) : null),
            'desired_functions' => isset($data['desired_functions']) ? array_values(array_unique($data['desired_functions'])) : null,
        ], fn ($v) => $v !== null));

        // Changer de profil ou de statut impose de compléter le nouveau formulaire.
        if (! $profile->exists || $changedPath) {
            $profile->status = 'draft';
        }
        $profile->save();

        $user->forceFill([
            'account_type' => 'pro',
            'onboarding_path' => $user->onboarding_path === 'collaborator' ? 'collaborator' : 'professional',
            'professional_profile' => $profile->profile_key,
            'professional_status' => $profile->status,
            'country' => $profile->activity_country,
        ])->save();

        if ($draft && $request->boolean('quit')) {
            \Illuminate\Support\Facades\Auth::guard('pro')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('pro.login')->with('status', 'Votre progression est enregistrée. Reconnectez-vous pour reprendre là où vous vous êtes arrêté.');
        }
        if ($draft) {
            return back()->with('success', 'Brouillon enregistré. Vous pourrez reprendre votre qualification plus tard.');
        }

        return redirect()->route('pro.register.professional.form', ['profile' => $profile->profile_key]);
    }

    /** ACC-04 à ACC-11 : un formulaire propre à chaque profil, pré-rempli (ACC-19). */
    public function showProfessionalForm(string $profileKey): View|RedirectResponse
    {
        abort_unless($this->schema->has($profileKey), 404);

        $user = $this->proUser();
        $profile = $user->professionalProfile()->with('organization.documents')->first();

        if (! $profile || $profile->profile_key !== $profileKey || ! $profile->entity_type) {
            return redirect()->route('pro.register.qualification', ['profile' => $profileKey])
                ->with('info', 'Commencez par qualifier votre activité : le formulaire dépend du profil et du statut choisis.');
        }

        $organization = $profile->organization;
        $documents = $organization
            ? $organization->documents->where('status', '!=', 'replaced')->groupBy('document_type')
            : collect();

        $viewName = view()->exists("pro.auth.professional.$profileKey")
            ? "pro.auth.professional.$profileKey"
            : 'pro.auth.professional';

        return view($viewName, [
            'user' => $user,
            'profile' => $profile,
            'profileKey' => $profileKey,
            'profileMeta' => (array) config("ovanie_pro.professional_profiles.$profileKey"),
            'entityType' => $profile->entity_type,
            'blocks' => $this->schema->blocks($profileKey, $profile->entity_type),
            'fields' => $this->schema->fields($profileKey, $profile->entity_type),
            'documents' => $this->schema->documents($profileKey, $profile->entity_type),
            'providedDocuments' => $documents,
            'values' => $this->currentValues($user, $profile, $organization),
            'onboarding' => ! $this->onboardingComplete($user),
            'countries' => $this->countries(),
            'phoneCodes' => $this->phoneCodes(),
        ]);
    }

    public function saveProfessionalForm(Request $request, string $profileKey): RedirectResponse
    {
        abort_unless($this->schema->has($profileKey), 404);

        $user = $this->proUser();
        $profile = $user->professionalProfile()->with('organization.documents')->firstOrFail();
        abort_unless($profile->profile_key === $profileKey && $profile->entity_type, 403);

        $entityType = $profile->entity_type;

        // Le profil Entrepreneur / Artisan accepte les deux statuts. La maquette
        // permet à l'utilisateur de confirmer son mode d'exercice directement
        // dans l'écran Identité professionnelle ; on utilise ce choix pour la
        // validation du schéma et on le persiste avec le profil.
        if (in_array($profileKey, ['entrepreneur_artisan', 'prestataire_btp'], true)) {
            $request->validate([
                // Le Prestataire a déjà un statut choisi à l'étape 2 : le champ ne fait que le confirmer ou le changer.
                'profile_entity_type' => [$profileKey === 'prestataire_btp' ? 'nullable' : 'required', Rule::in($this->schema->entityTypes($profileKey))],
            ], [], [
                'profile_entity_type' => 'mode d’exercice',
            ]);
            $entityType = (string) ($request->input('profile_entity_type') ?: $entityType);
        }

        $draft = $request->input('action') !== 'submit';
        $organization = $profile->organization;

        if ($profileKey === 'fabricant_producteur') {
            // « Sur demande » : la valeur chiffrée n'est pas exigée.
            foreach (['moq' => 'moq_mode', 'production_lead_time' => 'production_lead_time_mode'] as $field => $mode) {
                if ($request->input($mode) === 'on_request' && blank($request->input($field))) {
                    $request->merge([$field => 'Sur demande']);
                }
            }
            // Onglet Responsable : nom et prénoms modifiables depuis le formulaire.
            $identity = $request->validate([
                'identity_last_name' => ['nullable', 'string', 'max:100'],
                'identity_first_name' => ['nullable', 'string', 'max:100'],
            ]);
            if (filled($identity['identity_last_name'] ?? null) && filled($identity['identity_first_name'] ?? null)) {
                $user->forceFill([
                    'last_name' => $identity['identity_last_name'],
                    'first_name' => $identity['identity_first_name'],
                    'name' => trim($identity['identity_first_name'] . ' ' . $identity['identity_last_name']),
                ])->save();
            }
        }
        $provided = $this->providedTypes($organization);

        $validated = $request->validate(
            array_merge(['action' => ['required', Rule::in(['draft', 'submit'])]], $this->schema->rules($profileKey, $entityType, $draft, $provided)),
            ['documents.*.required' => 'Ce document est obligatoire pour soumettre votre profil.'],
            $this->schema->attributes($profileKey, $entityType)
        );

        if ($profileKey === 'grossiste' && ! $draft) {
            $this->validateGrossisteSubmission($validated);
        }

        if ($profileKey === 'fabricant_producteur' && ! $draft) {
            $this->validateFabricantProducteurSubmission($request, $validated, $provided);
        }

        if ($profileKey === 'demi_grossiste' && ! $draft) {
            $this->validateDemiGrossisteSubmission($validated);
        }

        $parts = $this->schema->split($validated, $profileKey, $entityType);

        DB::transaction(function () use ($request, $user, $profile, $organization, $parts, $entityType, $draft, $profileKey) {
            $organization ??= new ProOrganization(['owner_pro_user_id' => $user->id, 'verification_status' => 'draft']);
            $organization->fill($parts['organization'] + [
                'entity_type' => $entityType,
                'country' => $profile->activity_country,
                'registration_country' => $profile->registration_country,
                'official_email' => $parts['profile']['professional_email'] ?? $organization->official_email,
                'official_phone' => $parts['profile']['professional_phone'] ?? $organization->official_phone,
            ]);
            if ($entityType === 'independent') {
                $organization->legal_name = null;
            }
            // RG-05 : la soumission place en attente ; seule l'administration vérifie.
            if (! $draft && ! in_array($organization->verification_status, ['in_progress', 'verified'], true)) {
                $organization->verification_status = 'pending';
                $organization->submitted_at = now();
            }
            $organization->save();

            // Le créateur administre son organisation ; ses collaborateurs viendront par invitation (ACC-13).
            ProCompanyMembership::query()->updateOrCreate(
                ['pro_user_id' => $user->id, 'pro_organization_id' => $organization->id],
                ['organization_name' => $organization->display_name, 'permission' => 'administrator', 'status' => 'active']
            );

            $this->storeDocuments($request, $user, $organization, $profileKey, $entityType);

            $profile->entity_type = $entityType;
            $profile->fill($parts['profile'] + [
                'pro_organization_id' => $organization->id,
                'business_name' => $organization->display_name,
                'trade_name' => $organization->trade_name,
                'activity' => $this->schema->label($profileKey),
                'country' => $profile->activity_country,
                'registration_country' => $organization->registration_country ?: $profile->registration_country,
                'city' => $organization->city,
                'address' => $organization->address,
                'legal_form' => $organization->legal_form,
                'registration_number' => $organization->registration_number,
                'tax_number' => $organization->tax_number,
                'founded_year' => $organization->founded_year,
                'website' => $organization->website,
            ]);
            $profile->profile_data = array_merge((array) $profile->profile_data, $parts['data']);
            if (! $draft) {
                $profile->status = 'pending';
                $profile->submitted_at = now();
            }
            $profile->save();

            $user->forceFill(['professional_status' => $profile->status])->save();
        });

        if ($draft) {
            return back()->with('success', 'Brouillon enregistré. Vos informations et documents sont conservés.');
        }

        return redirect()->route('pro.roles.edit')
            ->with('success', 'Profil soumis. Il est en attente de vérification par OVANIE Pro. Choisissez maintenant vos espaces.');
    }

    /** ACC-12 : rôles cumulables, prérequis contrôlés côté serveur. */
    public function showRoles(): View
    {
        $user = $this->proUser();
        $profile = $user->professionalProfile()->first();
        $activeRoles = $user->roles()->pluck('role')->all();

        // « Votre progression » : reflète l'état réel du compte, étape par étape.
        $progress = [
            ['label' => 'Entreprise / professionnel', 'done' => (bool) $profile?->entity_type],
            ['label' => 'Services', 'done' => ! empty($profile?->desired_functions)],
            ['label' => 'Informations métier', 'done' => (bool) $profile?->submitted_at],
            ['label' => 'Fonctions', 'done' => $activeRoles !== []],
        ];

        return view('pro.auth.roles', [
            'user' => $user,
            'roles' => $this->businessRoles(),
            'presentation' => (array) config('ovanie_pro.business_role_presentation', []),
            'activeRoles' => $activeRoles,
            'professionalReady' => $this->professionalReady($user),
            'profile' => $profile,
            'progress' => $progress,
            'onboarding' => ! $this->onboardingComplete($user) || $user->onboarding_path === 'professional' && $activeRoles === [],
        ]);
    }

    public function saveRoles(Request $request): RedirectResponse
    {
        $user = $this->proUser();
        $roles = $this->businessRoles();

        // « Je le ferai plus tard » : termine l'inscription sans modifier les fonctions.
        if ($request->input('action') === 'later') {
            $this->completeOnboarding($user);

            return redirect()->route('pro.account')->with('success', 'Votre compte OVANIE PRO est prêt. Vous pourrez activer vos fonctions à tout moment.');
        }

        $data = $request->validate([
            // L'étape 4 est facultative et les fonctions sont modifiables plus tard.
            // Un tableau vide est donc un état valide (aucun espace actif).
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::in(array_keys($roles))],
        ]);

        $selected = array_values(array_unique((array) ($data['roles'] ?? [])));
        $ready = $this->professionalReady($user);
        $blocked = array_filter($selected, fn ($role) => ($roles[$role]['requires_pro'] ?? false) && ! $ready);

        if ($blocked) {
            throw ValidationException::withMessages([
                'roles' => 'Les espaces ' . implode(' et ', array_map(fn ($r) => $roles[$r]['label'], $blocked))
                    . ' demandent un profil professionnel soumis. Complétez d’abord votre profil PRO.',
            ]);
        }

        DB::transaction(function () use ($user, $selected, $roles) {
            $user->roles()->whereIn('role', array_keys($roles))->whereNotIn('role', $selected)->delete();
            foreach ($selected as $role) {
                $user->roles()->firstOrCreate(['role' => $role]);
            }
        });

        $this->completeOnboarding($user);

        return redirect()->route('pro.account')->with('success', 'Vos espaces de travail sont à jour.');
    }

    /**
     * Contraintes métier propres au parcours Fabricant / Producteur.
     * Le schéma garde les champs conditionnels facultatifs afin de permettre
     * un brouillon ; à la soumission on applique les dépendances métier réelles.
     */
    private function validateFabricantProducteurSubmission(Request $request, array $validated, array $providedDocumentTypes): void
    {
        $errors = [];

        if (($validated['export_enabled'] ?? null) === 'yes') {
            if (empty(array_filter((array) ($validated['shipping_modes'] ?? [])))) {
                $errors['shipping_modes'] = 'Sélectionnez au moins un mode d’expédition si vous exportez vos produits.';
            }
            if (blank($validated['incoterms'] ?? null)) {
                $errors['incoterms'] = 'Indiquez au moins un Incoterm utilisé pour vos opérations d’export.';
            }
        }

        // Les certifications sont facultatives. Si l'utilisateur en commence une,
        // l'ensemble minimal doit être cohérent et accompagné d'un justificatif.
        $certifications = [];
        $json = (string) ($validated['certifications_json'] ?? '');
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $certifications = array_values(array_filter($decoded, static function ($row) {
                    if (! is_array($row)) {
                        return false;
                    }
                    return collect($row)->contains(static fn ($value) => filled($value));
                }));
            }
        }

        $legacyCertificationStarted = collect([
            $validated['certification_type'] ?? null,
            $validated['certification_body'] ?? null,
            $validated['certification_number'] ?? null,
            $validated['certification_issue_date'] ?? null,
            $validated['certification_expiry_date'] ?? null,
        ])->contains(static fn ($value) => filled($value));

        if ($legacyCertificationStarted || $certifications !== [] || $request->hasFile('documents.certifications')) {
            foreach ([
                'certification_type' => 'le type de certification',
                'certification_body' => 'l’organisme de certification',
                'certification_number' => 'le numéro de certification',
                'certification_issue_date' => 'la date d’émission de la certification',
            ] as $field => $label) {
                if (blank($validated[$field] ?? null)) {
                    $errors[$field] = 'Renseignez ' . $label . '.';
                }
            }

            $alreadyProvided = in_array('certifications', $providedDocumentTypes, true);
            if (! $request->hasFile('documents.certifications') && ! $alreadyProvided) {
                $errors['documents.certifications'] = 'Ajoutez le document correspondant à la certification.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Contraintes métier propres au parcours Grossiste.
     * Elles complètent le schéma générique avec les dépendances conditionnelles
     * (entrepôts, import/export, moyens de transport).
     */
    private function validateGrossisteSubmission(array $validated): void
    {
        $errors = [];

        $flow = (string) ($validated['trade_flows'] ?? '');
        if (in_array($flow, ['import', 'both'], true) && blank($validated['supply_countries'] ?? null)) {
            $errors['supply_countries'] = 'Sélectionnez au moins un pays d’approvisionnement.';
        }
        if (in_array($flow, ['export', 'both'], true) && blank($validated['destination_countries'] ?? null)) {
            $errors['destination_countries'] = 'Sélectionnez au moins un pays de destination.';
        }
        if ($flow !== 'none' && $flow !== '' && empty(array_filter((array) ($validated['transport_modes'] ?? [])))) {
            $errors['transport_modes'] = 'Sélectionnez au moins un mode de transport.';
        }

        if (($validated['has_warehouses'] ?? null) === 'yes') {
            $warehouses = json_decode((string) ($validated['warehouses'] ?? '[]'), true);
            if (! is_array($warehouses) || count($warehouses) < 1) {
                $errors['warehouses'] = 'Ajoutez au moins un entrepôt ou choisissez « Non ».';
            } else {
                foreach ($warehouses as $index => $warehouse) {
                    foreach (['name' => 'nom / référence', 'country' => 'pays', 'city' => 'ville', 'address' => 'adresse', 'type' => 'type'] as $field => $label) {
                        if (blank($warehouse[$field] ?? null)) {
                            $errors['warehouses'] = 'Complétez le ' . $label . ' de l’entrepôt ' . ($index + 1) . '.';
                            break 2;
                        }
                    }
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Contraintes métier propres au parcours Demi-grossiste.
     * Le schéma générique contrôle les champs simples ; ici on valide la
     * structure des paliers de quantité stockés dans profile_data.
     */
    private function validateDemiGrossisteSubmission(array $validated): void
    {
        $errors = [];
        $raw = (string) ($validated['quantity_tiers'] ?? '');
        $tiers = json_decode($raw, true);

        if (! is_array($tiers) || count($tiers) < 1) {
            $errors['quantity_tiers'] = 'Ajoutez au moins un palier de quantité.';
        } else {
            foreach ($tiers as $index => $tier) {
                if (! is_array($tier)) {
                    $errors['quantity_tiers'] = 'Le palier ' . ($index + 1) . ' est invalide.';
                    break;
                }

                $from = trim((string) ($tier['from'] ?? ''));
                $to = trim((string) ($tier['to'] ?? ''));
                $unit = trim((string) ($tier['unit'] ?? ''));

                if ($from === '' || ! is_numeric(str_replace(',', '.', $from)) || $unit === '') {
                    $errors['quantity_tiers'] = 'Complétez la quantité de départ et l’unité du palier ' . ($index + 1) . '.';
                    break;
                }

                if ($to !== '' && ! is_numeric(str_replace(',', '.', $to))) {
                    $errors['quantity_tiers'] = 'La quantité de fin du palier ' . ($index + 1) . ' doit être numérique.';
                    break;
                }

                if ($to !== '' && (float) str_replace(',', '.', $to) < (float) str_replace(',', '.', $from)) {
                    $errors['quantity_tiers'] = 'La fin du palier ' . ($index + 1) . ' doit être supérieure ou égale à son début.';
                    break;
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function currentValues(ProUser $user, ProProfessionalProfile $profile, ?ProOrganization $organization): array
    {
        $values = (array) $profile->profile_data;
        foreach (['responsible_position', 'professional_email', 'professional_phone', 'professional_whatsapp'] as $column) {
            $values[$column] = $profile->{$column};
        }
        $values['professional_email'] ??= $user->email;
        $values['professional_phone'] ??= $user->phone;

        if ($organization) {
            foreach ($organization->getFillable() as $column) {
                $values[$column] ??= $organization->{$column};
            }
        }

        return $values;
    }

    private function providedTypes(?ProOrganization $organization): array
    {
        if (! $organization) {
            return [];
        }

        return $organization->documents()->where('status', '!=', 'replaced')->distinct()->pluck('document_type')->all();
    }

    /** Documents privés (disque local) rattachés à l'organisation, jamais redemandés ensuite. */
    private function storeDocuments(Request $request, ProUser $user, ProOrganization $organization, string $profileKey, string $entityType): void
    {
        foreach ($this->schema->documents($profileKey, $entityType) as $type => $definition) {
            $raw = $request->file("documents.$type");
            $files = $raw instanceof UploadedFile ? [$raw] : array_filter((array) $raw);
            if (! $files) {
                continue;
            }

            // Un document unique remplace le précédent ; l'historique reste consultable par l'administration.
            if (empty($definition['multiple'])) {
                $organization->documents()->where('document_type', $type)->where('status', '!=', 'replaced')->update(['status' => 'replaced']);
            }

            foreach ($files as $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    continue;
                }
                ProDocument::query()->create([
                    'pro_organization_id' => $organization->id,
                    'uploaded_by_pro_user_id' => $user->id,
                    'document_type' => $type,
                    'label' => $definition['label'],
                    'path' => $file->store("pro/organizations/{$organization->id}/$type", 'local'),
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 250),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'status' => 'submitted',
                ]);
            }
        }
    }

    /** Fonctions validées (ou reportées) avec un profil soumis : le parcours est terminé. */
    private function completeOnboarding(\App\Models\ProUser $user): void
    {
        if (! $user->onboarding_completed_at && $user->professionalProfile()->whereNotNull('submitted_at')->exists()) {
            $user->forceFill(['onboarding_completed_at' => now()])->save();
        }
    }
}
