# Watcher USB do Kindle

Roda no **Windows** (fora do WSL): detecta quando o Kindle é plugado, acha o `My Clippings.txt` e envia para o Wortschatz via `POST /api/importar/arquivo`. É o único caminho automático que cobre livros sideloaded. Reenviar o arquivo inteiro é seguro — o app deduplica por hash.

## Instalar (uma vez, no PowerShell do Windows)

```powershell
# gere o token antes, no WSL: php artisan clippings:token
powershell -ExecutionPolicy Bypass -File \\wsl.localhost\Ubuntu\home\diego_tracz\wortschatz\tools\usb-watcher\install-task.ps1 -Token SEU_TOKEN
```

Isso copia o script para `%LOCALAPPDATA%\WortschatzWatcher\` (a tarefa não roda direto do caminho `\\wsl.localhost` porque o WSL pode não estar de pé no logon), grava o `config.json` e registra a tarefa `WortschatzKindleWatcher`, que sobe no logon e fica aguardando eventos de chegada de volume.

- **Editou o script ou trocou o token?** Rode o `install-task.ps1` de novo — a tarefa executa a cópia, não o original.
- **Desinstalar:** `uninstall-task.ps1`.

## Testar manualmente

Com o Kindle plugado:

```powershell
powershell -ExecutionPolicy Bypass -File \\wsl.localhost\Ubuntu\home\diego_tracz\wortschatz\tools\usb-watcher\watch-kindle.ps1 -ScanOnce
```

(Nesse modo ele procura o `config.json` ao lado do script — para testar a partir do repositório, crie um `tools/usb-watcher/config.json` com `{"token": "...", "url": "http://localhost:8000"}`; ele está no `.gitignore`.)

## Como funciona / limitações

- Log em `%LOCALAPPDATA%\WortschatzWatcher\watcher.log`.
- O watcher procura `<unidade>:\documents\My Clippings.txt` em todas as letras — não fixa `D:` porque o Windows pode trocar a letra.
- Se o app não estiver de pé (`./dev`), ele loga e tenta de novo na próxima plugada.
- Kindles com firmware recente (2024+) podem montar via **MTP** (aparecem sem letra de unidade). Nesse caso o evento de volume não dispara — use o scraper (`tools/kindle-scraper/`) ou `php artisan clippings:import`.
- Os `.ps1` usam texto sem acentos de propósito: o Windows PowerShell 5.1 lê UTF-8 sem BOM com encoding errado.
