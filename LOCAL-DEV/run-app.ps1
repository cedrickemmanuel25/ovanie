param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('client','vendor','livreur','commercial')]
    [string]$App,

    [ValidateSet('local','test','production')]
    [string]$Environment = 'local',

    [string]$HostIp = '',
    [string]$DeviceId = '',
    [switch]$Emulator
)

. "$PSScriptRoot\common.ps1"

$appPath = Get-OvanieAppPath -App $App
$apiBase = Get-OvanieApiBase -Environment $Environment -HostIp $HostIp -Emulator:$Emulator

Write-Host ''
Write-Host "OVANIE $App" -ForegroundColor Cyan
Write-Host "Environnement : $Environment" -ForegroundColor Cyan
Write-Host "API : $apiBase" -ForegroundColor Green
if ($Environment -eq 'local' -and -not $Emulator) {
    Write-Host 'Le téléphone physique doit être sur le même réseau que le PC.' -ForegroundColor Yellow
}
Write-Host ''

$args = @(
    'run',
    "--dart-define=OVANIE_ENV=$Environment",
    "--dart-define=OVANIE_API_BASE=$apiBase"
)
if (-not [string]::IsNullOrWhiteSpace($DeviceId)) {
    $args += @('-d', $DeviceId)
}

Push-Location $appPath
try {
    & flutter @args
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
} finally {
    Pop-Location
}
