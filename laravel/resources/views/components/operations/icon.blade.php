@props(['name', 'class' => ''])
<svg {{ $attributes->class(['ops-icon', $class]) }} aria-hidden="true" viewBox="0 0 24 24"><use href="#ops-{{ $name }}"/></svg>
