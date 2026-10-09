<?php

namespace App\Http\Controllers\Auth;

use App\Models\Category;
use App\Models\ProPublication;
use App\Models\ProPublicationFile;
use App\Models\ProUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Compte simple OVANIE PRO : choix offre / besoin, création du compte,
 * formulaire, vérification puis publication.
 */
class ProPublicationController extends ProAccessController
{
    /** Segment d'URL → type interne. */
    private const KINDS = ['offre' => 'offer', 'besoin' => 'need'];

    // ---------- 1. Que souhaitez-vous publier ? ----------

    public function start(Request $request): View
    {
        return view('pro.publication.start', [
            'selected' => in_array($request->query('type'), ['offre', 'besoin'], true) ? $request->query('type') : null,
        ]);
    }

    public function choose(Request $request): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(self::KINDS))]], [
            'type.required' => 'Choisissez ce que vous souhaitez publier : une offre ou un besoin.',
        ]);

        return $this->proUser()
            ? redirect()->route('pro.publish.form', ['type' => $data['type']])
            : redirect()->route('pro.publish.account', ['type' => $data['type']]);
    }

    // ---------- 2. Créez votre compte ----------

    public function account(string $type): View|RedirectResponse
    {
        if ($this->proUser()) {
            return redirect()->route('pro.publish.form', ['type' => $type]);
        }

        return view('pro.publication.account', [
            'type' => $type,
            'kind' => self::KINDS[$type],
            'countries' => $this->countries(),
            'languages' => $this->languages(),
            'phoneCodes' => $this->phoneCodes(),
        ]);
    }

    public function register(Request $request, string $type): RedirectResponse
    {
        if ($this->proUser()) {
            return redirect()->route('pro.publish.form', ['type' => $type]);
        }

        $this->mergePhone($request);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'residence_country' => ['required', Rule::in($this->countries())],
            'phone_country' => $this->phoneCodeRule(),
            'phone' => ['required', 'string', 'regex:/^\+[0-9]{8,15}$/', Rule::unique('pro_users', 'phone')],
            'email' => ['required', 'email', 'max:255', Rule::unique('pro_users', 'email')],
            'preferred_language' => ['required', Rule::in(array_keys($this->languages()))],
            'password' => ['required', 'confirmed', $this->passwordRule()],
            'terms' => ['accepted'],
        ], [
            'phone.unique' => 'Ce numéro est déjà utilisé par un compte OVANIE PRO. Connectez-vous pour publier.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée. Connectez-vous pour publier.',
            'phone.regex' => 'Saisissez un numéro de téléphone valide.',
            'terms.accepted' => 'Vous devez accepter les conditions d’utilisation.',
        ], [
            'last_name' => 'nom', 'first_name' => 'prénoms', 'residence_country' => 'pays de résidence',
            'phone' => 'téléphone', 'email' => 'adresse e-mail', 'password' => 'mot de passe', 'preferred_language' => 'langue préférée',
        ]);

        $user = DB::transaction(function () use ($data, $type) {
            $user = ProUser::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'name' => trim($data['first_name'] . ' ' . $data['last_name']),
                'email' => $data['email'],
                'phone' => $data['phone'],
                'residence_country' => $data['residence_country'],
                'preferred_language' => $data['preferred_language'],
                'locale' => $data['preferred_language'],
                'password' => $data['password'],
                'account_type' => 'simple',
                'onboarding_path' => 'simple',
                'status' => 'active',
                'professional_status' => 'not_started',
                'currency' => 'XOF',
            ]);
            $user->roles()->create(['role' => self::KINDS[$type] === 'offer' ? 'advertiser' : 'requester']);

            return $user;
        });

        Auth::guard('pro')->login($user);
        $request->session()->regenerate();
        $request->session()->put('pro_password_hash_' . $user->id, $user->getAuthPassword());

        return redirect()->route('pro.publish.form', ['type' => $type])
            ->with('success', 'Votre compte est créé. Décrivez maintenant votre ' . ($type === 'offre' ? 'offre' : 'besoin') . '.');
    }

    // ---------- 3. Publiez votre offre / Décrivez votre besoin ----------

    public function form(string $type, ?ProPublication $publication = null): View
    {
        $kind = self::KINDS[$type];
        $publication = $publication ? $this->owned($publication, $kind) : new ProPublication(['currency' => 'XOF', 'country' => $this->proUser()->residence_country]);
        $this->ensureRole($kind);

        return view('pro.publication.form', $this->formData($type, $kind, $publication));
    }

    public function save(Request $request, string $type, ?ProPublication $publication = null): RedirectResponse
    {
        $kind = self::KINDS[$type];
        $publication = $publication ? $this->owned($publication, $kind) : null;
        $draft = $request->input('action') === 'draft';
        $this->ensureRole($kind);

        $data = $request->validate($this->rules($kind, $draft, $publication), $this->messages(), $this->attributes());
        $this->validateSubcategory($data);

        $existingPhotos = $publication ? $publication->photos()->whereNotIn('id', (array) ($data['remove_files'] ?? []))->count() : 0;
        if (! $draft && $kind === 'offer' && $existingPhotos + count((array) $request->file('photos', [])) === 0) {
            throw ValidationException::withMessages(['photos' => 'Ajoutez au moins une photo de votre offre.']);
        }

        $publication = DB::transaction(function () use ($request, $data, $kind, $publication) {
            $publication ??= new ProPublication();
            $publication->forceFill(['pro_user_id' => $this->proUser()->id, 'kind' => $kind]);
            $publication->fill($this->attributesFrom($data, $kind));
            if (! $publication->exists) {
                $publication->status = 'draft';
            }
            $publication->save();

            $this->syncFiles($request, $publication, (array) ($data['remove_files'] ?? []));

            return $publication;
        });

        if ($draft) {
            return redirect()->route('pro.publish.edit', ['type' => $type, 'publication' => $publication])
                ->with('success', 'Brouillon enregistré. Vous pourrez le reprendre à tout moment.');
        }

        return redirect()->route('pro.publish.review', ['type' => $type, 'publication' => $publication]);
    }

    // ---------- 4. Vérification & publication ----------

    /** Fiche publique d'une annonce publiée (accessible sans compte). */
    public function showPublic(int $publication): View
    {
        $announcement = ProPublication::query()->visible()->with(['category:id,name', 'subcategory:id,name', 'files'])->findOrFail($publication);

        return view('pro.publication.public', [
            'p' => $announcement,
            'options' => (array) config('ovanie_pro.publication', []),
            'others' => ProPublication::query()->visible()->with(['category:id,name', 'files'])
                ->whereKeyNot($announcement->id)->where('kind', $announcement->kind)->latest('published_at')->limit(3)->get(),
        ]);
    }

    public function review(string $type, ProPublication $publication): View
    {
        $kind = self::KINDS[$type];
        $this->owned($publication, $kind);

        return view('pro.publication.review', $this->formData($type, $kind, $publication) + [
            'checks' => $this->checks($publication),
        ]);
    }

    public function publish(Request $request, string $type, ProPublication $publication): RedirectResponse
    {
        $kind = self::KINDS[$type];
        $this->owned($publication, $kind);

        $request->validate([
            'confirm_accuracy' => ['accepted'],
            'confirm_rights' => ['accepted'],
            'accept_rules' => ['accepted'],
        ], [
            'confirm_accuracy.accepted' => 'Confirmez que les informations publiées sont exactes.',
            'confirm_rights.accepted' => 'Confirmez disposer des droits sur les fichiers ajoutés.',
            'accept_rules.accepted' => 'Acceptez les règles de publication OVANIE PRO.',
        ]);

        $missing = collect($this->checks($publication))->where('ok', false);
        if ($missing->isNotEmpty()) {
            return redirect()->route('pro.publish.edit', ['type' => $type, 'publication' => $publication])
                ->withErrors(['publication' => 'Complétez votre ' . $type . ' avant de la publier : ' . $missing->pluck('title')->implode(', ') . '.']);
        }

        $publication->forceFill(['status' => 'published', 'published_at' => now()])->save();
        if (! $this->proUser()->onboarding_completed_at) {
            $this->proUser()->forceFill(['onboarding_completed_at' => now()])->save();
        }

        return redirect()->route('pro.account')
            ->with('success', $kind === 'offer'
                ? 'Votre offre est publiée. Les professionnels intéressés pourront vous répondre.'
                : 'Votre besoin est publié. Vous recevrez les propositions des professionnels.');
    }

    // ---------- Outils ----------

    private function owned(ProPublication $publication, string $kind): ProPublication
    {
        abort_unless((int) $publication->pro_user_id === (int) $this->proUser()->id && $publication->kind === $kind, 404);
        abort_if(in_array($publication->status, ['withdrawn'], true), 404);

        return $publication->load(['files', 'category', 'subcategory']);
    }

    /** Un compte qui publie reçoit l'espace correspondant (Annonceur / Demandeur). */
    private function ensureRole(string $kind): void
    {
        $this->proUser()->roles()->firstOrCreate(['role' => $kind === 'offer' ? 'advertiser' : 'requester']);
    }

    private function formData(string $type, string $kind, ProPublication $publication): array
    {
        $categories = Category::query()->roots()->active()->ordered()
            ->where('slug', 'not like', '%carte%')
            ->with(['children' => fn ($q) => $q->active()->ordered()])
            ->get(['id', 'name', 'slug', 'parent_id']);

        return [
            'type' => $type,
            'kind' => $kind,
            'publication' => $publication,
            'options' => (array) config('ovanie_pro.publication'),
            'natures' => (array) config("ovanie_pro.publication.natures.$kind"),
            'categories' => $categories,
            'subcategoryMap' => $categories->mapWithKeys(fn ($c) => [$c->id => $c->children->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()]),
            'countries' => $this->countries(),
            'user' => $this->proUser(),
        ];
    }

    private function rules(string $kind, bool $draft, ?ProPublication $publication): array
    {
        $req = $draft ? 'nullable' : 'required';
        $options = (array) config('ovanie_pro.publication');
        $units = array_keys($options['units']);

        $rules = [
            'action' => ['required', Rule::in(['draft', 'continue'])],
            'nature' => [$req, Rule::in(array_keys((array) $options['natures'][$kind]))],
            'title' => [$req, 'string', 'max:160'],
            'category_id' => [$req, 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'subcategory_id' => [$req, 'integer', Rule::exists('categories', 'id')],
            'description' => [$req, 'string', 'max:1000'],
            'country' => [$req, Rule::in($this->countries())],
            'city' => [$req, 'string', 'max:120'],
            'location_detail' => ['nullable', 'string', 'max:180'],
            'photos' => ['nullable', 'array', 'max:' . $options['max_photos']],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'documents' => ['nullable', 'array', 'max:' . $options['max_documents']],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
            'remove_files' => ['nullable', 'array'],
            'remove_files.*' => ['integer'],
        ];

        if ($kind === 'offer') {
            return $rules + [
                'quantity' => [$req, 'numeric', 'gt:0', 'max:999999999'],
                'unit' => [$req, Rule::in($units)],
                'price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
                'currency' => ['nullable', Rule::in(array_keys($options['currencies']))],
                'price_negotiable' => ['nullable', 'boolean'],
                'price_on_request' => ['nullable', 'boolean'],
                'min_order_quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
                'min_order_unit' => ['nullable', Rule::in($units)],
                'availability' => [$req, Rule::in(array_keys($options['availability']))],
                'validity_days' => [$req, Rule::in(['7', '15', '30', 'custom'])],
                'expires_on' => [$req, 'date', 'after:today', 'before_or_equal:' . now()->addYear()->toDateString()],
            ];
        }

        return $rules + [
            'quantity_to_define' => ['nullable', 'boolean'],
            'quantity' => [$draft ? 'nullable' : 'required_unless:quantity_to_define,1', 'nullable', 'numeric', 'gt:0', 'max:999999999'],
            'unit' => [$draft ? 'nullable' : 'required_unless:quantity_to_define,1', 'nullable', Rule::in($units)],
            'deadline' => [$req, Rule::in(array_keys($options['deadlines']))],
            'needed_by' => [$draft ? 'nullable' : 'required_if:deadline,date', 'nullable', 'date', 'after:today'],
            'specifications' => ['nullable', 'string', 'max:1000'],
            'brand' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:120'],
            'alternatives_accepted' => ['nullable', 'boolean'],
            'budget_mode' => [$req, Rule::in(['proposals', 'range'])],
            'budget_min' => [$draft ? 'nullable' : 'required_if:budget_mode,range', 'nullable', 'numeric', 'min:0'],
            'budget_max' => [$draft ? 'nullable' : 'required_if:budget_mode,range', 'nullable', 'numeric', 'min:0', 'gte:budget_min'],
        ];
    }

    private function validateSubcategory(array $data): void
    {
        if (! empty($data['subcategory_id']) && ! empty($data['category_id'])) {
            $ok = Category::query()->whereKey($data['subcategory_id'])->where('parent_id', $data['category_id'])->exists();
            if (! $ok) {
                throw ValidationException::withMessages(['subcategory_id' => 'Choisissez une sous-catégorie de la catégorie sélectionnée.']);
            }
        }
    }

    private function attributesFrom(array $data, string $kind): array
    {
        $common = [
            'nature' => $data['nature'] ?? null,
            'title' => $data['title'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'subcategory_id' => $data['subcategory_id'] ?? null,
            'description' => $data['description'] ?? null,
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'location_detail' => $data['location_detail'] ?? null,
        ];

        if ($kind === 'offer') {
            $days = $data['validity_days'] ?? null;
            $expires = $data['expires_on'] ?? null;
            if ($days && $days !== 'custom' && ! $expires) {
                $expires = now()->addDays((int) $days)->toDateString();
            }

            return $common + [
                'quantity' => $data['quantity'] ?? null,
                'unit' => $data['unit'] ?? null,
                'price' => ! empty($data['price_on_request']) ? null : ($data['price'] ?? null),
                'currency' => $data['currency'] ?? 'XOF',
                'price_negotiable' => (bool) ($data['price_negotiable'] ?? false),
                'price_on_request' => (bool) ($data['price_on_request'] ?? false),
                'min_order_quantity' => $data['min_order_quantity'] ?? null,
                'min_order_unit' => $data['min_order_unit'] ?? null,
                'availability' => $data['availability'] ?? null,
                'validity_days' => $days && $days !== 'custom' ? (int) $days : null,
                'expires_on' => $expires,
            ];
        }

        $toDefine = (bool) ($data['quantity_to_define'] ?? false);
        $range = ($data['budget_mode'] ?? null) === 'range';

        return $common + [
            'quantity_to_define' => $toDefine,
            'quantity' => $toDefine ? null : ($data['quantity'] ?? null),
            'unit' => $toDefine ? null : ($data['unit'] ?? null),
            'deadline' => $data['deadline'] ?? null,
            'needed_by' => ($data['deadline'] ?? null) === 'date' ? ($data['needed_by'] ?? null) : null,
            'specifications' => $data['specifications'] ?? null,
            'brand' => $data['brand'] ?? null,
            'reference' => $data['reference'] ?? null,
            'alternatives_accepted' => array_key_exists('alternatives_accepted', $data) ? (bool) $data['alternatives_accepted'] : null,
            'budget_mode' => $data['budget_mode'] ?? null,
            'budget_min' => $range ? ($data['budget_min'] ?? null) : null,
            'budget_max' => $range ? ($data['budget_max'] ?? null) : null,
        ];
    }

    /** Photos sur le disque public (affichées), documents sur le disque privé. */
    private function syncFiles(Request $request, ProPublication $publication, array $remove): void
    {
        if ($remove) {
            $publication->files()->whereIn('id', $remove)->get()->each(function (ProPublicationFile $file) {
                Storage::disk($file->disk)->delete($file->path);
                $file->delete();
            });
        }

        $order = (int) $publication->files()->max('sort_order');
        foreach (['photos' => ['photo', 'public'], 'documents' => ['document', 'local']] as $field => [$type, $disk]) {
            foreach ((array) $request->file($field, []) as $upload) {
                if (! $upload instanceof UploadedFile || ! $upload->isValid()) {
                    continue;
                }
                $publication->files()->create([
                    'type' => $type,
                    'disk' => $disk,
                    'path' => $upload->store("pro/publications/{$publication->id}/{$field}", $disk),
                    'original_name' => mb_substr($upload->getClientOriginalName(), 0, 250),
                    'mime_type' => $upload->getMimeType(),
                    'size' => $upload->getSize(),
                    'sort_order' => ++$order,
                ]);
            }
        }

        // Une seule photo principale : la première.
        $photos = $publication->photos()->get();
        $photos->each(fn ($photo, $i) => $photo->update(['is_main' => $i === 0]));
    }

    /** Points contrôlés avant publication (colonne « Avant de publier »). */
    private function checks(ProPublication $p): array
    {
        $p->loadMissing('files');
        $photos = $p->files->where('type', 'photo')->count();

        if ($p->kind === 'offer') {
            return [
                ['title' => 'Informations complètes', 'text' => 'Toutes les informations principales sont renseignées.', 'ok' => $p->nature && $p->title && $p->category_id && $p->subcategory_id && $p->description && $p->quantity && $p->unit && $p->availability],
                ['title' => 'Photos ajoutées', 'text' => $photos . ' photo(s) ajoutée(s).', 'ok' => $photos > 0],
                ['title' => 'Localisation renseignée', 'text' => 'La zone de disponibilité est définie.', 'ok' => $p->country && $p->city],
                ['title' => 'Validité définie', 'text' => $p->expires_on ? 'Valable jusqu’au ' . $p->expires_on->format('d/m/Y') . '.' : 'Indiquez une date de fin de validité.', 'ok' => $p->expires_on && $p->expires_on->isFuture()],
            ];
        }

        return [
            ['title' => 'Besoin clairement décrit', 'text' => 'Le titre et la description sont renseignés.', 'ok' => $p->nature && $p->title && $p->description && $p->category_id && $p->subcategory_id],
            ['title' => 'Quantité renseignée', 'text' => $p->quantity_to_define ? 'Quantité à définir avec le professionnel.' : 'La quantité et l’unité sont précisées.', 'ok' => $p->quantity_to_define || ($p->quantity && $p->unit)],
            ['title' => 'Destination renseignée', 'text' => 'La ville ou la zone est définie.', 'ok' => $p->country && $p->city],
            ['title' => 'Délai renseigné', 'text' => 'Vous avez indiqué votre délai.', 'ok' => $p->deadline && ($p->deadline !== 'date' || $p->needed_by)],
        ];
    }

    private function messages(): array
    {
        return [
            'expires_on.after' => 'La date de fin de validité doit être dans le futur.',
            'quantity.required_unless' => 'Indiquez la quantité, ou cochez « Quantité à définir avec le professionnel ».',
            'unit.required_unless' => 'Choisissez l’unité, ou cochez « Quantité à définir avec le professionnel ».',
            'needed_by.required_if' => 'Indiquez la date à laquelle vous avez besoin de cette solution.',
            'budget_min.required_if' => 'Indiquez votre budget minimum.',
            'budget_max.required_if' => 'Indiquez votre budget maximum.',
            'photos.*.image' => 'Les photos doivent être des images JPG, PNG ou WEBP.',
            'photos.*.max' => 'Chaque photo doit faire moins de 10 Mo.',
            'documents.*.max' => 'Chaque document doit faire moins de 10 Mo.',
        ];
    }

    private function attributes(): array
    {
        return [
            'nature' => 'type', 'title' => 'titre', 'category_id' => 'catégorie', 'subcategory_id' => 'sous-catégorie',
            'description' => 'description', 'country' => 'pays', 'city' => 'ville / zone', 'quantity' => 'quantité', 'unit' => 'unité',
            'price' => 'prix indicatif', 'availability' => 'disponibilité', 'validity_days' => 'durée de validité',
            'expires_on' => 'date de fin de validité', 'deadline' => 'délai', 'budget_mode' => 'budget', 'needed_by' => 'date souhaitée',
        ];
    }
}
