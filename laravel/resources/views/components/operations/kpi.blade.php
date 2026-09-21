@props(['icon', 'label', 'value', 'tone' => 'blue', 'detail' => null])
<div {{ $attributes->class(['ops-kpi', 'tone-'.$tone]) }}><span class="ops-kpi-icon"><x-operations.icon :name="$icon"/></span><div><span class="ops-kpi-label">{{ $label }}</span><div class="ops-kpi-value">{{ $value }}@if($detail)<small>{{ $detail }}</small>@endif</div>{{ $slot }}</div></div>
