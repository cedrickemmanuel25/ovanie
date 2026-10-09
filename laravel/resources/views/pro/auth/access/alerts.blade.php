{{-- Messages de session et erreurs de validation. Variable facultative : $except (champs dont l'erreur est affichée sous le champ). --}}
@if(session('success'))
    <div class="oa-alert oa-alert--success" role="status">@proicon('check', 'oa-ico')<span>{{ session('success') }}</span></div>
@endif
@foreach(['status', 'info'] as $flash)
    @if(session($flash))
        <div class="oa-alert oa-alert--info" role="status">@proicon('info', 'oa-ico')<span>{{ session($flash) }}</span></div>
    @endif
@endforeach
@php
    $globalErrors = collect($errors->getMessages())->except($except ?? [])->flatten()->unique()->values();
@endphp
@if($globalErrors->isNotEmpty())
    <div class="oa-alert oa-alert--error" role="alert">
        @proicon('alert', 'oa-ico')
        @if($globalErrors->count() === 1)
            <span>{{ $globalErrors->first() }}</span>
        @else
            <div><strong>Vérifiez les informations saisies :</strong>
                <ul>@foreach($globalErrors->take(6) as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif
    </div>
@endif
