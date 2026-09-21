<dialog class="directory-modal" id="driver-access">
    <header><div><h2>Connexion à l’application livreur</h2><p>Accès par numéro de téléphone et code SMS à usage unique.</p></div><button data-directory-close aria-label="Fermer">×</button></header>
    <div class="directory-stack">
        <x-operations.panel title="Numéro de connexion" icon="phone">
            <strong>{{ $driver->phone }}</strong>
            <p>Le livreur saisit ce numéro dans l’application OVANIE Livreur, puis le code OTP reçu par SMS.</p>
        </x-operations.panel>
        <x-operations.panel title="État du dossier" icon="user">
            @php([$accessLabel, $accessTone] = \App\ViewModels\LogisticsDirectoryData::driverOnboarding($driver))
            <x-operations.tag :tone="$accessTone">{{ $accessLabel }}</x-operations.tag>
            <p>Avant validation, le livreur peut compléter son inscription et suivre son dossier. Les missions sont accessibles après validation et activation du compte.</p>
            @if($driver->onboarding_status === 'pending_review')
                <a class="ops-button ops-button-primary" href="{{ route('logistics.drivers.review', $driver) }}">Examiner le dossier</a>
            @endif
        </x-operations.panel>
    </div>
    <footer><button type="button" class="ops-button" data-directory-close>Fermer</button></footer>
</dialog>
