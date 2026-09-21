param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('client','vendor','livreur','commercial')]
    [string]$App,

    [ValidateSet('local','test','production')]
    [string]$Environment = 'local',

    [ValidateSet('debug','release')]
    [string]$Mode = '',

    [string]$HostIp = '',
    [switch]$Emulator
)

. "$PSScriptRoot\common.ps1"

if ([string]::IsNullOrWhiteSpace($Mode)) {
    $Mode = if ($Environment -eq 'production') { 'release' } else { 'debug' }
}

$appPath = Get-OvanieAppPath -App $App
$apiBase = Get-OvanieApiBase -Environment $Environment -HostIp $HostIp -Emulator:$Emulator

Write-Host ''
Write-Host "Build OVANIE $App" -ForegroundColor Cyan
Write-Host "Environnement : $Environment" -ForegroundColor Cyan
Write-Host "Mode : $Mode" -ForegroundColor Cyan
Write-Host "API embarquée : $apiBase" -ForegroundColor Green
if ($Environment -eq 'local' -and -not $Emulator) {
    Write-Host 'ATTENTION : l''IP LAN est embarquée dans cet APK local. Si l''IP du PC change, reconstruisez l''APK.' -ForegroundColor Yellow
}
Write-Host ''

$args = @(
    'build', 'apk', "--$Mode",
    "--dart-define=OVANIE_ENV=$Environment",
    "--dart-define=OVANIE_API_BASE=$apiBase"
)

Push-Location $appPath
try {
    & flutter @args
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Write-Host ''
    Write-Host "APK généré dans : $appPath\build\app\outputs\flutter-apk" -ForegroundColor Green
} finally {
    Pop-Location
}
