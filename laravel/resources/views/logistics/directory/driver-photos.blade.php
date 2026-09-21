@php
    $photos = [];
    if ($driver->vehiclePhotoPath()) $photos[$driver->vehiclePhotoPath()] = 'Photo du véhicule';
    if (data_get($driver->profile, 'plate_photo')) $photos[data_get($driver->profile, 'plate_photo')] = 'Photo de la plaque';
    foreach (data_get($driver->profile, 'vehicle_photos', []) as $path) {
        if (!isset($photos[$path])) $photos[$path] = 'Autre photo transmise';
    }
@endphp
@if(count($photos))
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:16px 0">
        @foreach($photos as $path => $label)
            <a href="{{ Storage::disk('public')->url($path) }}" target="_blank" rel="noopener" style="display:block">
                <img src="{{ Storage::disk('public')->url($path) }}" alt="{{ $label }}" loading="lazy" style="display:block;width:100%;height:210px;object-fit:contain;background:#f3f7fb;border-radius:12px">
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </div>
@else
    <p class="directory-empty">Aucune photo du véhicule transmise.</p>
@endif
