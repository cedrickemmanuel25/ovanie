<section class="ovh-section ovh-section--tight" aria-labelledby="ovh-actions-title">
    <div class="ovh-container">
        <x-home.section-heading
            eyebrow="Services rapides"
            title="Avancez plus vite sur votre projet"
            description="Des outils utiles pour acheter, estimer et préparer vos besoins chantier."
        />

        <div class="ovh-action-grid">
            @foreach([
                ['url' => $devisUrl, 'icon' => 'sparkles', 'title' => 'Votre devis avec OVANIE IA', 'text' => 'Structurez rapidement votre besoin et préparez votre demande.'],
                ['url' => $calculatorUrl, 'icon' => 'calculator', 'title' => 'Calculateur de besoins', 'text' => 'Estimez les quantités selon votre type de travaux.'],
                ['url' => $appelOffreUrl, 'icon' => 'briefcase-business', 'title' => 'Appels d’offres', 'text' => 'Publiez un besoin important et centralisez les propositions.'],
                ['url' => $sellUrl, 'icon' => 'store', 'title' => 'Vendre sur OVANIE', 'text' => 'Ouvrez votre boutique et développez votre activité.'],
            ] as $action)
                <a href="{{ $action['url'] }}" class="ovh-action-card">
                    <span class="ovh-action-card__icon"><i data-lucide="{{ $action['icon'] }}"></i></span>
                    <div>
                        <h3>{{ $action['title'] }}</h3>
                        <p>{{ $action['text'] }}</p>
                    </div>
                    <i data-lucide="arrow-up-right" class="ovh-action-card__arrow"></i>
                </a>
            @endforeach
        </div>
    </div>
</section>
