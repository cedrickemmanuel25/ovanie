$ErrorActionPreference = 'Stop'
$sdkRoot = Join-Path $env:LOCALAPPDATA 'Android\Sdk'
$env:ANDROID_AVD_HOME = Join-Path $env:USERPROFILE '.android\avd'
Start-Process -FilePath (Join-Path $sdkRoot 'emulator\emulator.exe') -ArgumentList @(
    '-avd', 'OVANIE_Pixel_4a_PlayStore_API34',
    '-gpu', 'swiftshader_indirect', '-memory', '2048', '-cores', '4',
    '-feature', '-GLDirectMem,-Vulkan,-ANGLE',
    '-no-snapshot', '-no-boot-anim', '-no-metrics', '-skip-adb-auth'
)
