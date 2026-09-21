@props(['title' => null, 'icon' => 'box'])
<section {{ $attributes->class('ops-panel') }}>@if($title)<header class="ops-panel-heading"><h2><x-operations.icon :name="$icon"/>{{ $title }}</h2>@isset($actions)<div class="ops-panel-actions">{{ $actions }}</div>@endisset</header>@endif{{ $slot }}</section>
