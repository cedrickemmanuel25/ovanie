@if(session('status'))
    <div class="opx-alert opx-alert--info" role="status">@proicon('alert', 'opx-icon')<span>{{ session('status') }}</span></div>
@endif
@if(session('success'))
    <div class="opx-alert opx-alert--success" role="status">@proicon('check', 'opx-icon')<span>{{ session('success') }}</span></div>
@endif
@if(session('info'))
    <div class="opx-alert opx-alert--info" role="status">@proicon('alert', 'opx-icon')<span>{{ session('info') }}</span></div>
@endif
@if($errors->any())
    <div class="opx-alert opx-alert--danger" role="alert">@proicon('alert', 'opx-icon')<div><strong>Vérifiez les informations saisies.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
@endif
