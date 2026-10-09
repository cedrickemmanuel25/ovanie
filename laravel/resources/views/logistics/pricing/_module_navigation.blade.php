<nav class="pricing-module-nav" aria-label="Navigation Tarification">
    <a href="{{ route('logistics.ovanie-pricing.index') }}" @class(['active'=>request()->routeIs('logistics.ovanie-pricing.index')])>
        <x-operations.icon name="list"/> <span>Tarification</span>
    </a>
    <a href="{{ route('logistics.ovanie-pricing.vehicles') }}" @class(['active'=>request()->routeIs('logistics.ovanie-pricing.vehicles')])>
        <x-operations.icon name="truck"/> <span>Véhicules & tarifs</span>
    </a>
    <a href="{{ route('logistics.ovanie-pricing.supplements') }}" @class(['active'=>request()->routeIs('logistics.ovanie-pricing.supplements')])>
        <x-operations.icon name="plus"/> <span>Frais éventuels</span>
    </a>
    <a href="{{ route('logistics.ovanie-pricing.simulator') }}" @class(['active'=>request()->routeIs('logistics.ovanie-pricing.simulator')])>
        <x-operations.icon name="chart"/> <span>Simulateur</span>
    </a>
</nav>
