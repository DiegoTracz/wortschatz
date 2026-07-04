<#
.SYNOPSIS
    Instala o watcher do Kindle como tarefa de logon do Windows.

.DESCRIPTION
    Copia o watch-kindle.ps1 para %LOCALAPPDATA%\WortschatzWatcher (a tarefa nao
    roda do caminho \\wsl.localhost porque o WSL pode nao estar de pe no logon),
    grava o config.json e registra/inicia a tarefa agendada. Rode de novo apos
    editar o script ou trocar o token.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File install-task.ps1 -Token SEU_TOKEN
#>
param(
    [Parameter(Mandatory = $true)][string]$Token,
    [string]$AppUrl = 'http://localhost:8000'
)

$ErrorActionPreference = 'Stop'
$taskName = 'WortschatzKindleWatcher'
$installDir = Join-Path $env:LOCALAPPDATA 'WortschatzWatcher'

New-Item -ItemType Directory -Path $installDir -Force | Out-Null
Copy-Item -Path (Join-Path $PSScriptRoot 'watch-kindle.ps1') -Destination $installDir -Force

@{ token = $Token; url = $AppUrl.TrimEnd('/') } | ConvertTo-Json |
    Set-Content -Path (Join-Path $installDir 'config.json') -Encoding UTF8

$action = New-ScheduledTaskAction -Execute 'powershell.exe' `
    -Argument "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$installDir\watch-kindle.ps1`""
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
$settings = New-ScheduledTaskSettingsSet -ExecutionTimeLimit ([TimeSpan]::Zero) `
    -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) `
    -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries

# Para a instancia antiga antes de substituir (a task pode estar rodando).
Stop-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null
Start-ScheduledTask -TaskName $taskName

Write-Host "Tarefa '$taskName' instalada e iniciada."
Write-Host "Log: $installDir\watcher.log"
