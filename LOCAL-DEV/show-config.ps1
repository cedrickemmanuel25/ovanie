. "$PSScriptRoot\common.ps1"

$ip = Get-OvanieLanIPv4
Write-Host ''
Write-Host 'OVANIE - configuration locale détectée' -ForegroundColor Cyan
Write-Host "IP du PC : $ip" -ForegroundColor Green
Write-Host "Laravel téléphone : http://$ip`:8000" -ForegroundColor Green
Write-Host "API mobile locale : http://$ip`:8000/api" -ForegroundColor Green
Write-Host 'Android Emulator : http://10.0.2.2:8000/api' -ForegroundColor Green
Write-Host 'Web/Windows : http://127.0.0.1:8000/api' -ForegroundColor Green
Write-Host ''
