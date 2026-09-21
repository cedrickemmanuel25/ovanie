param(
    [string]$HostIp = ''
)

. "$PSScriptRoot\common.ps1"

if ([string]::IsNullOrWhiteSpace($HostIp)) {
    $HostIp = Get-OvanieLanIPv4
}

$metaUrl = "http://$HostIp`:8000/api/mobile/v1/reference-data/meta"
$referenceUrl = "http://$HostIp`:8000/api/mobile/v1/reference-data"

Write-Host ''
Write-Host 'Test API OVANIE locale' -ForegroundColor Cyan
Write-Host "Meta : $metaUrl" -ForegroundColor Green

try {
    $meta = Invoke-RestMethod -Uri $metaUrl -Method Get -TimeoutSec 10
    if ($meta.ok -ne $true) {
        throw 'La route meta a répondu mais ok != true.'
    }
    Write-Host "OK - schema_version : $($meta.meta.schema_version)" -ForegroundColor Green

    $refs = Invoke-RestMethod -Uri $referenceUrl -Method Get -TimeoutSec 20
    if ($refs.ok -ne $true) {
        throw 'La route reference-data a répondu mais ok != true.'
    }
    Write-Host 'OK - reference-data accessible depuis le réseau local.' -ForegroundColor Green
} catch {
    Write-Host "ECHEC - $($_.Exception.Message)" -ForegroundColor Red
    Write-Host 'Vérifiez que LOCAL-DEV/start-laravel.ps1 est lancé et que le pare-feu autorise PHP/port 8000.' -ForegroundColor Yellow
    exit 1
}
