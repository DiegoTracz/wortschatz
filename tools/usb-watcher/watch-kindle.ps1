<#
.SYNOPSIS
    Observa a chegada do Kindle via USB e envia o My Clippings.txt ao Wortschatz.

.DESCRIPTION
    Roda no Windows (o app fica no WSL2, acessível em http://localhost:8000).
    Instale como tarefa de logon com o install-task.ps1; para testar manualmente
    com o Kindle já plugado, rode: .\watch-kindle.ps1 -ScanOnce
#>
param(
    [string]$ConfigPath = (Join-Path $PSScriptRoot 'config.json'),
    [switch]$ScanOnce
)

$ErrorActionPreference = 'Stop'
$logPath = Join-Path $PSScriptRoot 'watcher.log'

function Write-Log {
    param([string]$Message)

    $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') $Message"
    Add-Content -Path $logPath -Value $line
    Write-Host $line
}

if (-not (Test-Path $ConfigPath)) {
    Write-Log "Config nao encontrada em $ConfigPath - rode o install-task.ps1 (ou crie um config.json com token e url)."
    exit 1
}

$config = Get-Content $ConfigPath -Raw | ConvertFrom-Json

# O Kindle monta como unidade removivel; procura o arquivo em todas as letras
# (a letra pode mudar entre plugadas).
function Find-KindleClippings {
    foreach ($drive in (Get-PSDrive -PSProvider FileSystem)) {
        $path = Join-Path $drive.Root 'documents\My Clippings.txt'
        if (Test-Path -LiteralPath $path) {
            return $path
        }
    }

    return $null
}

function Send-Clippings {
    param([string]$Path)

    Write-Log "Kindle encontrado: $Path - enviando..."

    # curl.exe (nativo do Windows 10+) porque o PowerShell 5.1 nao tem
    # Invoke-RestMethod -Form para multipart.
    $raw = (& curl.exe -sS -w "`n%{http_code}" `
        -H "Authorization: Bearer $($config.token)" `
        -H "Accept: application/json" `
        -F "file=@`"$Path`"" `
        "$($config.url)/api/importar/arquivo" 2>&1) -join "`n"

    if ($LASTEXITCODE -ne 0) {
        Write-Log "App inacessivel em $($config.url) - tentara no proximo plug. Detalhe: $raw"
        return
    }

    $lines = $raw -split "`n"
    $status = $lines[-1].Trim()
    $body = ($lines[0..($lines.Count - 2)] -join "`n").Trim()

    if ($status -eq '200') {
        Write-Log "Importado: $body"
    } else {
        Write-Log "Falha HTTP ${status}: $body"
    }
}

function Invoke-Scan {
    $path = Find-KindleClippings

    if ($null -ne $path) {
        Send-Clippings -Path $path
        return $true
    }

    return $false
}

if ($ScanOnce) {
    if (-not (Invoke-Scan)) {
        Write-Log 'Nenhum Kindle encontrado (procurei <unidade>:\documents\My Clippings.txt em todas as unidades).'
    }
    exit 0
}

Write-Log 'Watcher iniciado; aguardando o Kindle.'

# O Kindle pode ja estar plugado quando o watcher sobe (ex.: no logon).
$lastSent = [DateTime]::MinValue
try {
    if (Invoke-Scan) { $lastSent = Get-Date }
} catch {
    Write-Log "Erro no scan inicial: $_"
}

# EventType 2 = chegada de volume (drive montado).
Register-CimIndicationEvent -Query 'SELECT * FROM Win32_VolumeChangeEvent WHERE EventType = 2' -SourceIdentifier KindleWatcher | Out-Null

try {
    while ($true) {
        $event = Wait-Event -SourceIdentifier KindleWatcher
        Remove-Event -EventIdentifier $event.EventIdentifier

        # O Windows dispara varios eventos por plugada; debounce de 60s.
        if (((Get-Date) - $lastSent).TotalSeconds -lt 60) {
            continue
        }

        Start-Sleep -Seconds 3  # da tempo do volume terminar de montar

        try {
            if (Invoke-Scan) { $lastSent = Get-Date }
        } catch {
            Write-Log "Erro: $_"
        }
    }
} finally {
    Unregister-Event -SourceIdentifier KindleWatcher -ErrorAction SilentlyContinue
}
