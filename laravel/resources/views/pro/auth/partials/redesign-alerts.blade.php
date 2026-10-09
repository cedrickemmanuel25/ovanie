@if(session('success'))
    <div class="opx-alert opx-alert--success" role="status">@proicon('check', 'opx-svg')<span>{{ session('success') }}</span></div>
@endif
@if(session('status'))
    <div class="opx-alert opx-alert--info" role="status">@proicon('alert', 'opx-svg')<span>{{ session('status') }}</span></div>
@endif
@if(session('info'))
    <div class="opx-alert opx-alert--info" role="status">@proicon('alert', 'opx-svg')<span>{{ session('info') }}</span></div>
@endif
@if($errors->any())
    <div class="opx-alert opx-alert--danger" role="alert">
        @proicon('alert', 'opx-svg')
        <div>
            <strong>Vérifiez les informations saisies.</strong>
            <ul>
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    </div>
@endif
