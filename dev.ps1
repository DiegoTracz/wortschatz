# Sobe o app desktop Wortschatz em modo dev (Electron + Vite juntos).
#
# Uso:  .\dev.ps1
#
# Existe porque o php/composer/node ficam no PATH do usuário, mas terminais
# abertos antes da instalação herdam um PATH velho e não os encontram. Este
# script garante o PATH e chama `composer native:dev`. (Depois de reiniciar o
# VS Code uma vez, o PATH passa a ser herdado nativamente e isto vira só um
# atalho de conveniência.)

$env:Path = "$env:LOCALAPPDATA\Programs\php;C:\Program Files\nodejs;$env:Path"

foreach ($bin in @('php', 'composer', 'node', 'npm')) {
    if (-not (Get-Command $bin -ErrorAction SilentlyContinue)) {
        Write-Error "Não encontrei '$bin'. Confira os caminhos em CLAUDE.md (seção 'Ferramentas no Windows')."
        exit 1
    }
}

Write-Host "Subindo Wortschatz (Electron + Vite). Feche a janela ou Ctrl+C para parar." -ForegroundColor Cyan
composer native:dev
