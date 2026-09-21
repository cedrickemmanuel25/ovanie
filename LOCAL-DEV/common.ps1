Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-OvanieWorkspaceRoot {
    return (Split-Path -Parent $PSScriptRoot)
}

function Get-OvanieAppPath {
    param(
        [Parameter(Mandatory = $true)]
        [ValidateSet('client','vendor','livreur','commercial')]
        [string]$App
    )

    $root = Get-OvanieWorkspaceRoot
    $relative = switch ($App) {
        'client'     { 'OVANIE-MOBILE\ovanie_app' }
        'vendor'     { 'OVANIE-MOBILE\ovanie_vendor' }
        'livreur'    { 'OVANIE-MOBILE\ovanie_livreur' }
        'commercial' { 'OVANIE-MOBILE\ovanie_commercial' }
    }

    $path = Join-Path $root $relative
    if (-not (Test-Path $path)) {
        throw "Projet Flutter introuvable : $path"
    }
    return $path
}

function Get-OvanieLanIPv4 {
    $candidates = @()

    try {
        $configs = Get-NetIPConfiguration | Where-Object {
            $_.IPv4DefaultGateway -ne $null -and
            $_.NetAdapter -ne $null -and
            $_.NetAdapter.Status -eq 'Up'
        }

        foreach ($config in $configs) {
            foreach ($address in @($config.IPv4Address)) {
                if ($null -eq $address) { continue }
                $ip = [string]$address.IPAddress
                if ($ip -match '^169\.254\.') { continue }
                if ($ip -eq '127.0.0.1') { continue }
                $candidates += $ip
            }
        }
    } catch {
        # Repli ci-dessous pour les environnements où Get-NetIPConfiguration
        # n'est pas disponible.
    }

    if ($candidates.Count -eq 0) {
        try {
            $candidates = @(Get-NetIPAddress -AddressFamily IPv4 | Where-Object {
                $_.IPAddress -ne '127.0.0.1' -and
                $_.IPAddress -notmatch '^169\.254\.' -and
                $_.AddressState -eq 'Preferred'
            } | Select-Object -ExpandProperty IPAddress)
        } catch {
            $candidates = @()
        }
    }

    $preferred = $candidates | Where-Object {
        $_ -match '^192\.168\.' -or
        $_ -match '^10\.' -or
        $_ -match '^172\.(1[6-9]|2[0-9]|3[01])\.'
    } | Select-Object -First 1

    if (-not $preferred) {
        $preferred = $candidates | Select-Object -First 1
    }

    if (-not $preferred) {
        throw 'Impossible de détecter automatiquement l''adresse IPv4 locale du PC. Relancez avec -HostIp 192.168.x.x.'
    }

    return [string]$preferred
}

function Get-OvanieApiBase {
    param(
        [Parameter(Mandatory = $true)]
        [ValidateSet('local','test','production')]
        [string]$Environment,
        [string]$HostIp = '',
        [switch]$Emulator
    )

    switch ($Environment) {
        'test'       { return 'https://test.ovanie.com/api' }
        'production' { return 'https://www.ovanie.com/api' }
        'local' {
            if ($Emulator) {
                return 'http://10.0.2.2:8000/api'
            }
            if ([string]::IsNullOrWhiteSpace($HostIp)) {
                $HostIp = Get-OvanieLanIPv4
            }
            return "http://$HostIp`:8000/api"
        }
    }
}
