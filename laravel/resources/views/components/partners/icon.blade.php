@props(['name', 'class' => ''])
<svg {{ $attributes->class(['partner-svg', $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
            @break
        @case('truck')
            <path d="M3 6h11v10H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>
            @break
        @case('route')
            <circle cx="5" cy="18" r="2.5"/><circle cx="19" cy="6" r="2.5"/><path d="M7.5 18h3.5a3 3 0 0 0 3-3V9a3 3 0 0 1 3-3h.5"/>
            @break
        @case('activity')
            <path d="M3 12h4l2-7 4 14 2-7h6"/>
            @break
        @case('briefcase')
            <rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2"/>
            @break
        @case('hardhat')
            <path d="M4 15a8 8 0 0 1 16 0"/><path d="M2 15h20v4H2zM9 7v8m6-8v8"/>
            @break
        @case('search')
            <circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>
            @break
        @case('download')
            <path d="M12 3v12m-4-4 4 4 4-4"/><path d="M4 18v3h16v-3"/>
            @break
        @case('plus')
            <circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>
            @break
        @case('refresh')
            <path d="M20 7v5h-5"/><path d="M4 17v-5h5"/><path d="M6.1 8a7 7 0 0 1 11.6-2L20 8M4 16l2.3 2a7 7 0 0 0 11.6-2"/>
            @break
        @case('eye')
            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6S2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.8"/>
            @break
        @case('phone')
            <path d="M5 4 8 3l2 5-2 2c1.7 3 4 5.3 7 7l2-2 5 2-1 3c-.4 1.2-1.7 2-3 1.8C9.7 20.6 3.4 14.3 2.2 7 2 5.7 2.8 4.4 4 4Z"/>
            @break
        @case('more')
            <circle cx="5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1" fill="currentColor" stroke="none"/>
            @break
        @case('chart')
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>
            @break
        @case('alert')
            <path d="M10.3 3.5 2.5 17a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.5a2 2 0 0 0-3.4 0Z"/><path d="M12 8v5M12 17h.01"/>
            @break
        @case('map')
            <path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/>
            @break
        @case('pin')
            <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>
            @break
        @case('file')
            <path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M9 12h6M9 16h6"/>
            @break
        @case('shield')
            <path d="M12 3 4 6v5c0 5 3.4 8.2 8 10 4.6-1.8 8-5 8-10V6z"/><path d="m9 12 2 2 4-4"/>
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1A1.7 1.7 0 0 0 4.6 15 1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>
            @break
        @case('check')
            <circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            @break
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/>
            @break
        @case('weight')
            <path d="M6 9h12l2 11H4z"/><circle cx="12" cy="6" r="3"/>
            @break
        @case('motorcycle')
            <circle cx="5" cy="17" r="3"/><circle cx="19" cy="17" r="3"/><path d="m5 17 5-7 4 7H5Zm5-7h6l3 7M15 6h3"/>
            @break
        @case('box')
            <path d="m4 7 8-4 8 4-8 4z"/><path d="M4 7v10l8 4 8-4V7M12 11v10"/>
            @break
        @case('back')
            <path d="m10 5-7 7 7 7M3 12h18"/>
            @break
        @case('edit')
            <path d="M4 20h4l11-11-4-4L4 16zM14 6l4 4"/>
            @break
        @case('list')
            <path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r=".8" fill="currentColor" stroke="none"/><circle cx="3.5" cy="12" r=".8" fill="currentColor" stroke="none"/><circle cx="3.5" cy="18" r=".8" fill="currentColor" stroke="none"/>
            @break
        @case('ban')
            <circle cx="12" cy="12" r="9"/><path d="m6 6 12 12"/>
            @break
        @case('play')
            <circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4z"/>
            @break
        @case('note')
            <path d="M5 3h14v18H5z"/><path d="M8 7h8M8 11h8M8 15h5"/>
            @break
        @case('upload')
            <path d="M12 16V4m-4 4 4-4 4 4M4 15v5h16v-5"/>
            @break
        @case('x')
            <path d="M5 5 19 19M19 5 5 19"/>
            @break
        @case('chevron')
            <path d="m9 6 6 6-6 6"/>
            @break
        @default
            <circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>
    @endswitch
</svg>
