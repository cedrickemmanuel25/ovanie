@if(empty($bag))
    @if(session('success'))
        <div class="pa-alert pa-alert--success" role="status">@proicon('check'){{ session('success') }}</div>
    @endif
    @foreach(['status', 'info'] as $key)
        @if(session($key))
            <div class="pa-alert pa-alert--info" role="status">@proicon('alert')<span>{{ session($key) }}</span></div>
        @endif
    @endforeach
@endif
@if(($bag ?? null) ? $errors->getBag($bag)->any() : $errors->any())
    @php($list = ($bag ?? null) ? $errors->getBag($bag)->all() : $errors->all())
    <div class="pa-alert pa-alert--error" role="alert">
        @proicon('alert')
        <div>
            @if(count($list) === 1)
                {{ $list[0] }}
            @else
                Merci de corriger les points suivants :
                <ul>@foreach(array_slice($list, 0, 6) as $message)<li>{{ $message }}</li>@endforeach</ul>
            @endif
        </div>
    </div>
@endif
