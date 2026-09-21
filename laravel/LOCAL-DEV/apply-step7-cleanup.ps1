$ErrorActionPreference = 'Stop'

$workspace = Split-Path -Parent $PSScriptRoot
$mobileCandidates = @(
    (Join-Path $workspace 'OVANIE-MOBILE'),
    (Join-Path $workspace 'ovanie-mobile')
)
$mobileRoot = $mobileCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $mobileRoot) {
    throw 'Dossier OVANIE-MOBILE/ovanie-mobile introuvable.'
}

$appRoot = Join-Path $mobileRoot 'ovanie_app'
$targets = @(
    (Join-Path $appRoot 'lib\features\checkout\data\abidjan_localities.dart'),
    (Join-Path $appRoot 'lib\features\categories\data\official_categories.dart')
)

$references = Get-ChildItem (Join-Path $appRoot 'lib') -Recurse -Filter '*.dart' |
    Where-Object { $targets -notcontains $_.FullName } |
    Select-String -Pattern 'abidjan_localities|official_categories'

if ($references) {
    Write-Host 'Nettoyage annulé : une référence vers un ancien fichier local existe encore.' -ForegroundColor Red
    $references | ForEach-Object { Write-Host (" - {0}:{1}" -f $_.Path, $_.LineNumber) }
    exit 1
}

$removed = 0
foreach ($target in $targets) {
    if (Test-Path $target) {
        Remove-Item $target -Force
        Write-Host ("SUPPRIME : {0}" -f $target) -ForegroundColor Green
        $removed++
    } else {
        Write-Host ("DEJA ABSENT : {0}" -f $target) -ForegroundColor DarkGray
    }
}

Write-Host ''
Write-Host ("OK - Nettoyage Etape 7 termine. Fichiers supprimes maintenant : {0}" -f $removed) -ForegroundColor Green
