<#
.SYNOPSIS
    Remove a tarefa agendada do watcher e os arquivos instalados.
#>
$ErrorActionPreference = 'Stop'
$taskName = 'WortschatzKindleWatcher'

Stop-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force (Join-Path $env:LOCALAPPDATA 'WortschatzWatcher') -ErrorAction SilentlyContinue

Write-Host "Tarefa '$taskName' removida."
