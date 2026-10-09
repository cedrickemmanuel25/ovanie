{{-- Exigences du mot de passe (identiques à la règle serveur) : cochées en direct par le script. --}}
<div class="oa-rules {{ $class ?? '' }}" data-oa-rules>
    <strong>Votre mot de passe doit contenir :</strong>
    <ul>
        <li data-rule="length">@proicon('check', 'oa-ico')Au moins 8 caractères</li>
        <li data-rule="lower">@proicon('check', 'oa-ico')Une lettre minuscule</li>
        <li data-rule="upper">@proicon('check', 'oa-ico')Une lettre majuscule</li>
        <li data-rule="number">@proicon('check', 'oa-ico')Un chiffre</li>
        <li data-rule="symbol">@proicon('check', 'oa-ico')Un caractère spécial (ex. ! @ # $ %)</li>
    </ul>
</div>
