. "$PSScriptRoot\common.ps1"

$root = Get-OvanieWorkspaceRoot
$laravel = Join-Path $root 'laravel'
if (-not (Test-Path (Join-Path $laravel 'artisan'))) {
    throw "Projet Laravel introuvable : $laravel"
}

$ip = Get-OvanieLanIPv4
Write-Host ''
Write-Host 'OVANIE - Laravel local' -ForegroundColor Cyan
Write-Host "PC : http://127.0.0.1:8000" -ForegroundColor Green
Write-Host "Téléphone : http://$ip`:8000" -ForegroundColor Green
Write-Host "API : http://$ip`:8000/api" -ForegroundColor Green
Write-Host ''
Write-Host 'Laissez ce terminal ouvert pendant les tests.' -ForegroundColor Yellow
Write-Host 'Si le téléphone ne se connecte pas, vérifiez le pare-feu Windows et que le téléphone est sur le même Wi-Fi.' -ForegroundColor Yellow
Write-Host ''

Push-Location $laravel
try {
    & php artisan serve --host=0.0.0.0 --port=8000
} finally {
    Pop-Location
}
