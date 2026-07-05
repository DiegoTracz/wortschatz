# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## O projeto

Wortschatz é um app pessoal (single-user na prática, mas multi-user no schema) para estudar vocabulário de alemão: importa destaques do Kindle, transforma em flashcards e agenda revisões com FSRS-4.5. UI em pt-BR, conteúdo dos cartões em alemão, rotas em português (`/estudar`, `/importar`, `/livros`, `/cartoes`).

## Comandos

```bash
php artisan test                          # suíte completa (Pest)
php artisan test --filter="nome do test"  # um teste específico
./vendor/bin/pint --dirty                 # formata PHP (só arquivos alterados)
npm run format && npm run lint            # Prettier + ESLint (--fix)
npm run build                             # build Vite
php artisan serve                         # dev server (+ npm run dev em outro terminal)
```

- **Antes de commitar, rode sempre**: Pint, Prettier e ESLint (preferência do dono do projeto). O CI (`.github/workflows/lint.yml`) falha se algum estiver sujo.
- **Os testes exigem assets buildados**: sem `npm run build` ao menos uma vez, toda página Inertia devolve 500 ("Vite manifest not found") nos testes.
- Banco é SQLite (`database/database.sqlite`); Sail está instalado sem serviços extras (`compose.yaml`) — Docker é opcional.

## Ferramentas no Windows (LEIA se `php`/`composer`/`node` "não existir")

O ambiente é **Windows nativo** (migração NativePHP, ver `NATIVEPHP-MIGRATION.md`). As
ferramentas **estão instaladas** e no PATH **persistente do usuário**, mas um terminal/sessão
aberto *antes* da instalação herda um PATH velho e não as acha. **Nunca conclua que "PHP não
está instalado"** — só falta o PATH. Caminhos:

- **PHP 8.4**: `C:\Users\Diego Tracz\AppData\Local\Programs\php\php.exe` (`php.ini` com sqlite/mbstring/curl/openssl/zip/intl/fileinfo)
- **Composer 2**: mesma pasta — `composer.phar` + `composer.bat`
- **Node/npm**: `C:\Program Files\nodejs`

**Como rodar (use PowerShell — no Git Bash o `php` pode não aparecer).** Prefixe o PATH no
início de cada comando da sessão:

```powershell
$env:Path = "$env:LOCALAPPDATA\Programs\php;C:\Program Files\nodejs;$env:Path"
php artisan test        # agora funciona; idem pint/pest/migrate
composer install        # (ou: php "$env:LOCALAPPDATA\Programs\php\composer.phar" ...)
```

**Subir o app desktop em dev:** `.\dev.ps1` (na raiz — já garante o PATH e roda
`composer native:dev`, que sobe Electron + Vite juntos). Feche a janela ou Ctrl+C para parar.

Comandos de rede (`composer install/require`, `npm install`, `native:serve/build`) podem exigir
`dangerouslyDisableSandbox: true` na chamada do tool.

**Correção definitiva (uma vez):** reiniciar o Claude Code / VS Code — aí as sessões novas
herdam o PATH do usuário nativamente e o prefixo acima deixa de ser necessário.

## Arquitetura

Laravel 12 + Inertia 2 + Vue 3 (TypeScript), a partir do starter kit oficial de Vue: componentes shadcn-vue em `resources/js/components/ui/`, páginas em `resources/js/pages/` (minúsculo — `config/inertia.php` foi ajustado para isso; `assertInertia` depende desse path), Ziggy expõe `route()` global no front.

### Fluxo de domínio

`My Clippings.txt` (upload, comando `clippings:import` ou API) → `KindleClippingsParser` → `ClippingsImporter` → `books` + `highlights` → cartão criado a partir do destaque (`CardFormDialog.vue`) → sessão de estudo (`Study.vue` + `StudyController`) → `reviews`.

- **`app/Services/KindleClippingsParser.php`**: parse do formato do Kindle com metadados em pt/en/de. Marcadores são descartados; notas viram `highlights.type = 'note'`.
- **`app/Services/ClippingsImporter.php`**: persiste entradas (do parser ou de JSON solto do scraper) com dedupe por hash — reimportar tudo é seguro e é o fluxo esperado. **`Highlight::computeHash()` é a fonte única do hash** (sha1 normalizado: whitespace colapsado, localização reduzida ao número inicial) para que o mesmo destaque vindo do `My Clippings.txt` e do Amazon Notebook colida; se mudar a fórmula, é preciso recalcular os hashes existentes (precedente: migration `recompute_highlight_hashes`). O livro casa por `(user_id, title)` — autor fica fora da chave porque as fontes o grafam diferente.
- **`app/Services/FsrsScheduler.php`**: FSRS-4.5 com os parâmetros padrão (`W`). Estado por cartão: `stability`/`difficulty` (null = nunca revisado). Notas 1–4 (errei/difícil/bom/fácil). Nota 1 → `interval_days = 0`, `due_at = now` e o front devolve o cartão ao fim da fila da mesma sessão. Intervalo = estabilidade ajustada à retenção alvo (`config/srs.php`, env `SRS_RETENTION`). `replay()` reconstrói o estado a partir do histórico — foi usado na migração SM-2→FSRS e serve para re-otimizações futuras; por isso `reviews` guarda cada resposta com `stability_after`/`difficulty_after`. Se mudar a escala de notas, precisa migrar `reviews.rating`.
- **`app/Services/Translator.php`** (MyMemory, DE→PT-BR) e **`app/Services/ArticleDetector.php`** (gênero via wikitext do Wiktionary alemão → der/die/das, cache de 30 dias incluindo misses como `''`): ambos degradam silenciosamente para null em falha — o front trata como recurso opcional.

### Três estilos de resposta HTTP

1. **Páginas Inertia**: mutações redirecionam; flash messages são compartilhadas via prop `flash` em `HandleInertiaRequests` (ex.: `import_result`).
2. **Endpoints JSON de sessão** (`POST /estudar/{card}`, `/traduzir`, `/artigo`): chamados do Vue com `postJson` de `resources/js/lib/api.ts`, que lê o cookie `XSRF-TOKEN` (axios não está instalado). Usados onde um reload do Inertia atrapalharia (fila de estudo, tradução dentro do dialog).
3. **API stateless de importação** (`routes/api.php`: `POST /api/importar/arquivo` e `/api/importar/destaques`): sem sessão/CSRF, autenticada pelo middleware `import.token` (`AuthenticateImportToken`) contra `users.import_token` — coluna gerada por `clippings:token`, escrita via `forceFill` e **listada em `$hidden`** (o `HandleInertiaRequests` serializa o user inteiro para o front). Usada pelas automações em `tools/` (scraper Playwright do Amazon Notebook e watcher USB em PowerShell), que ficam fora do lint (`eslint.config.js` e `.prettierignore` ignoram `tools/`) e do `npm ci` do CI.

Autorização é por checagem inline de `user_id` (`abort_unless`) nos controllers — não há Policies.

## Testes

Pest 4, tudo em `tests/Feature/` (o `RefreshDatabase` do `Pest.php` só cobre esse diretório; os services usam helpers como `now()` que exigem o app bootado). Funções helper declaradas em arquivos de teste são globais — nomes precisam ser únicos entre arquivos (`fsrsCard`, `createCard`, `clippingsFile`, `wiktionaryResponse`, `tokenUser`, `apiClippingsFile`, `clippingsFixturePath`). APIs externas sempre com `Http::fake`. O CI roda `./vendor/bin/pest` (tests.yml).
