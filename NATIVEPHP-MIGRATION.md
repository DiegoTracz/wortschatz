# Migração para NativePHP (handoff)

> Documento de continuidade. A migração começou numa sessão do Claude Code rodando
> no WSL2, mas o NativePHP precisa de PHP+Node nativos e o alvo é um app **desktop
> Windows** — então o trabalho continua numa sessão nova rodando **no Windows**.
> **Claude: leia este arquivo inteiro e retome a migração a partir da seção "Plano".**

## Por que NativePHP

Toda a fricção de importar do Kindle (token, watcher em PowerShell, "app precisa
estar de pé", Playwright no container × host, fronteira WSL2/Windows) vem de o app
ser um servidor isolado num container enquanto o Kindle é um dispositivo no Windows.
NativePHP dissolve essa fronteira: o app vira um programa desktop nativo, com acesso
direto ao sistema de arquivos (lê o `D:\documents\My Clippings.txt` sozinho) e a
processos (roda o scraper Playwright como processo filho). Tradeoff aceito pelo dono:
estudo passa a ser no computador (sem revisão pelo celular).

## Progresso (atualizado 2026-07-04, sessão Windows) — ✅ APP DESKTOP FUNCIONANDO

Ambiente montado do zero no Windows nativo (sem WSL/Docker):
- **PHP 8.4.23** (winget falhou com 404; baixado direto do windows.php.net para
  `%LOCALAPPDATA%\Programs\php`, `php.ini` com sqlite/mbstring/curl/openssl/zip/
  intl/fileinfo, adicionado ao PATH do usuário) e **Composer 2.10** (composer.phar
  + wrapper `.bat` no mesmo dir). **Node v24** já estava instalado.
- `composer install`, `npm install`, `.env`, `key:generate`, `database.sqlite`,
  `migrate`, `npm run build`.

Migração de código **feita, testada (58 testes verdes) e rodando no app desktop**:
1. ✅ NativePHP instalado (`nativephp/electron` 1.3.0 + `native:install`).
2. ✅ Identidade em `config/nativephp.php` via `.env` (`NATIVEPHP_APP_*`, updater
   desligado). Janela em `NativeAppServiceProvider::boot()` (`Window::open()
   ->route('dashboard')`, 1280×860, `rememberState()`).
3. ✅ **Import direto do Kindle**: `app/Services/KindleDriveLocator.php` varre as
   unidades atrás de `<letra>:\documents\My Clippings.txt`; `ImportController@kindle`
   (rota `POST importar/kindle`) reusa parser+importer e dispara `Notification`
   nativa; botão "Sincronizar Kindle" em `Import.vue` (só quando `native`).
   Testes em `tests/Feature/KindleImportTest.php`.
4. ✅ **Auth single-user**: middleware `AutoLoginNativeUser` (gateado por
   `config('nativephp-internal.running')`) autentica/cria o único usuário no app
   desktop; inerte no web e nos testes (login/registro intactos). A ordem é
   garantida em `bootstrap/app.php` via `prependToPriorityList(Authenticate,
   AutoLoginNativeUser)` — sem isso o `auth` roda antes e redireciona pro /login.
5. ✅ `native`/`import_error` compartilhados via `HandleInertiaRequests` (+ tipos).
6. ✅ **Legado removido**: `routes/api.php`, `Api\ImportController`, middleware
   `AuthenticateImportToken`, comando `clippings:token`, coluna `import_token`
   (migration apagada) e `tools/usb-watcher`. O app lê o drive direto; `tools/
   kindle-scraper` foi mantido (ver "Pendências"). `clippings:import` (CLI) fica.

Verificado com `native:serve`: janela abre, auto-login cai no `/dashboard`,
navegação (Importar/Livros/Estudar) e notificação nativa funcionam.

### 🔧 O fix do crash de inicialização (Electron/Node) — IMPORTANTE

`native:serve` crashava ao iniciar o Electron, antes de abrir a janela:
`cjsPreparseModuleExports: Cannot read properties of undefined (reading 'exports')`
no loader ESM→CJS do Node **embarcado no Electron**. Diagnóstico: reproduz no
Electron **32.3.3 (Node 20.18.1)** e **34.5.8 (Node 20.19.1)**; some no Electron
**35.x (Node 22.14)** — o bug é da linha Node 20.x. A NativePHP 1.3 fixa Electron
`^32.2.7`, então **o driver precisa de Electron ≥ 35**.

Como isso vive em `vendor/.../resources/js/node_modules` (resetado a cada
`composer install`), a correção é reaplicada automaticamente por
**`scripts/ensure-native-electron.php`** (idempotente, CI-safe), acionado por:
- `composer native:dev` (roda o script antes de subir), e
- o `prebuild` do `config/nativephp.php` (antes de `native:build`).
Também dá pra rodar manualmente: `composer native:fix-electron`.
Se um dia a NativePHP soltar release estável com Electron novo, dá pra remover o
script e o pin.

### Pendências (opcionais, decisão do dono)

- **Scraper como processo filho** (`tools/kindle-scraper`, passo 4 do plano
  original): ainda não integrado ao app. O import por USB (drive direto) já cobre
  o fluxo principal; o scraper do Amazon Notebook (destaques online, com login/2FA)
  seria um extra via `Native\Laravel\...ChildProcess`. Exige teste interativo de
  login — deixado documentado para uma próxima iteração.
- Reportar o bug do Electron/Node upstream (NativePHP) para eventualmente dispensar
  o `scripts/ensure-native-electron.php`.

## Ambiente alvo (Windows, nativo — NÃO WSL/Docker)

O NativePHP desaconselha explicitamente rodar de container/VM. Requisitos:
- PHP 8.3+ (recomendo **Laravel Herd** para Windows — já traz PHP + Composer)
- Node 22+ (o WSL tinha v20, insuficiente)
- Git (já instalado no Windows)

## Setup inicial no Windows (uma vez)

```powershell
# depois de instalar Herd (PHP+Composer) e Node 22+:
git clone \\wsl.localhost\Ubuntu\home\diego_tracz\wortschatz "C:\Users\Diego Tracz\wortschatz"
cd "C:\Users\Diego Tracz\wortschatz"
git checkout nativephp-migration
composer install
npm install
copy .env.example .env
php artisan key:generate
type nul > database\database.sqlite   # banco novo
php artisan migrate
npm run build                          # Inertia precisa do manifest
```

## Plano (executar no Windows, nesta ordem)

1. **Instalar NativePHP**: `composer require nativephp/electron` e
   `php artisan native:install` (publica `config/nativephp.php`,
   `NativeAppServiceProvider`, script `composer native:dev`, instala Electron).
2. **Configurar o app desktop**: em `NativeAppServiceProvider::boot()` abrir a janela
   principal (`Window::open()` apontando para a rota inicial, ex.: `/dashboard` ou
   `/estudar`). Definir nome/ícone em `config/nativephp.php`. NativePHP guarda o
   SQLite no diretório de dados do app — confirmar o path do banco.
3. **Import direto do Kindle**: substituir a necessidade de upload/token por leitura
   direta do drive. Adicionar um comando/serviço que localiza
   `<unidade>:\documents\My Clippings.txt` (varre as letras, procura o arquivo),
   reusa o `KindleClippingsParser` + `ClippingsImporter` (já existem). Um botão
   "Sincronizar Kindle" na tela `/importar` dispara isso. Notificação nativa com o
   resultado (`Notification::title(...)->show()`).
4. **Scraper como processo filho**: em vez do fluxo CLI, o app dispara o
   `tools/kindle-scraper` (Playwright) via `Native\Laravel\...ChildProcess` ou
   `Process::run`. O login (2FA) abre janela nativa uma vez; o scrape roda em
   background e reusa a API/`ClippingsImporter`. Node do Windows já atende ao
   requisito 22+.
5. **Auth single-user**: app desktop é single-user. Avaliar colapsar o login de
   sessão (auto-login no único usuário no boot) — manter o schema multi-user, só
   pular a tela de login. Não remover as Policies/checagens `user_id` sem cuidado.
6. **Legado**: `routes/api.php`, middleware `import.token` e `tools/usb-watcher`
   ficam vestigiais no desktop (o app lê o drive direto). Decidir com o dono se
   remove ou mantém como fallback. O `tools/kindle-scraper` é reaproveitado (passo 4).
7. **Empacotar**: `php artisan native:build` gera o instalador Windows. Verificar a
   janela abrindo com `php artisan native:serve` antes.

## Decisões já tomadas

- Banco continua **SQLite** (recomendado pelo NativePHP; já era o usado).
- Hash de dedupe é `Highlight::computeHash` — **fonte única**, não duplicar a lógica.
- Livro casa por título (autor fora da chave) — ver `ClippingsImporter`.
- O parser (`KindleClippingsParser`) e o importer (`ClippingsImporter`) são reusados
  em todos os caminhos; a migração muda só o *gatilho* (upload/API → leitura direta
  + processo filho), não o núcleo de parsing/persistência.

## Verificação

- `php artisan test` (os 68 testes devem seguir verdes; ajustar os que assumem
  contexto de servidor web se necessário).
- `php artisan native:serve` abre a janela — smoke test manual do fluxo
  importar → criar cartão → estudar.
- `php artisan native:build` gera o instalador.
