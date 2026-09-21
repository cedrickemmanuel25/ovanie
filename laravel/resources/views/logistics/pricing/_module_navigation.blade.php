<nav class="pricing-module-nav" aria-label="Navigation Tarification">
    <a href="{{ route('logistics.ovanie-pricing.index') }}" @class(['active'=>request()->routeIs('logistics.ovanie-pricing.index')])>
        <x-operations.icon name="list"/> <span>Tarification</span>
    </a>
    <a href="{{ route('logistics.ovanie-pricing.vehicles') }}" @class(['active'=>request()->routeIs('logistics.ovanie-pricing.vehicles')])>
        <x-operations.icon name="truck"/> <span>Véhicules & capacités</span>
    </a>
    <a href="{{ route('logistics.ovanie-pricing.communes') }}" @class(['active'=>request()->routeIs('logistics.ovanie-pricing.communes*')])>
        <x-operations.icon name="map"/> <span>Tarifs communes</span>
    </a>
    <a href="{{ route('logistics.ovanie-pricing.simulator') }}" @class(['active'=>request()->routeIs('logistics.ovanie-pricing.simulator')])>
        <x-operations.icon name="chart"/> <span>Simulateur logistique</span>
    </a>
    <a href="{{ route('logistics.ovanie-pricing.supplements') }}" @class(['active'=>request()->routeIs('logistics.ovanie-pricing.supplements')])>
        <x-operations.icon name="plus"/> <span>Suppléments</span>
    </a>
</nav>
