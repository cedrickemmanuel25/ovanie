@props(['name','size'=>20])
@php
$paths = [
 'truck' => '<path d="M3 6h10v10H3z"/><path d="M13 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
 'plus' => '<path d="M12 5v14M5 12h14"/>',
 'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
 'arrow-left' => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
 'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
 'refresh' => '<path d="M20 6v5h-5"/><path d="M4 18v-5h5"/><path d="M6.5 8a7 7 0 0 1 11.5-2L20 11M4 13l2 2a7 7 0 0 0 11.5 1"/>',
 'eye' => '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/>',
 'phone' => '<path d="M7 3H4.5A1.5 1.5 0 0 0 3 4.5C3 13.6 10.4 21 19.5 21A1.5 1.5 0 0 0 21 19.5V17l-4.3-1-1.4 3a15.4 15.4 0 0 1-10.3-10.3l3-1.4L7 3Z"/>',
 'dots' => '<circle cx="5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1" fill="currentColor" stroke="none"/>',
 'check' => '<path d="m5 12 4 4L19 6"/>',
 'wrench' => '<path d="M14.7 6.3a4.5 4.5 0 0 0-5.8 5.8L3 18v3h3l5.9-5.9a4.5 4.5 0 0 0 5.8-5.8l-3 3-3-3 3-3Z"/>',
 'warning' => '<path d="M12 3 2.5 20h19L12 3Z"/><path d="M12 9v4M12 17h.01"/>',
 'gauge' => '<path d="M4 14a8 8 0 1 1 16 0"/><path d="m12 14 4-4"/><path d="M5 19h14"/>',
 'map-pin' => '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
 'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
 'file' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h5"/>',
 'shield' => '<path d="M12 3 5 6v5c0 5 3 8 7 10 4-2 7-5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>',
 'inspection' => '<path d="M8 4h8v3H8z"/><path d="M6 6h12v15H6z"/><path d="m9 14 2 2 4-4"/>',
 'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1.5"/><path d="m5 18 5-5 3 3 2-2 4 4"/>',
 'snowflake' => '<path d="M12 2v20M4.2 6.5l15.6 11M4.2 17.5l15.6-11M9 4l3 2 3-2M9 20l3-2 3 2"/>',
 'package' => '<path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="m4 7 8 4v10l-8-4V7ZM20 7l-8 4v10l8-4V7Z"/>',
 'route' => '<circle cx="6" cy="18" r="2"/><circle cx="18" cy="6" r="2"/><path d="M8 18h4a4 4 0 0 0 4-4V8"/>',
 'rotate' => '<path d="M20 7v5h-5"/><path d="M4 17v-5h5"/><path d="M6 8a7 7 0 0 1 12 4M18 16a7 7 0 0 1-12-4"/>',
 'save' => '<path d="M5 3h12l2 2v16H5z"/><path d="M8 3v6h8V3M8 21v-7h8v7"/>',
 'upload' => '<path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 20h16"/>',
 'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
 'odometer' => '<path d="M4 14a8 8 0 1 1 16 0"/><path d="m12 14 3.5-5"/><circle cx="12" cy="14" r="1.5"/>',
 'document-check' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h5"/><path d="m9 14 2 2 4-4"/>',
];
@endphp
<svg {{ $attributes->merge(['class'=>'fleet-svg','width'=>$size,'height'=>$size,'viewBox'=>'0 0 24 24','fill'=>'none','stroke'=>'currentColor','stroke-width'=>'1.8','stroke-linecap'=>'round','stroke-linejoin'=>'round','aria-hidden'=>'true']) }}>{!! $paths[$name] ?? $paths['truck'] !!}</svg>
