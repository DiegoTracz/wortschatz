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

## Estado atual

- **main** (commit `5a3d167`): recurso de importação automática já pronto e testado —
  `ClippingsImporter`, `Highlight::computeHash` (hash normalizado, fonte única),
  API stateless com token (`routes/api.php` + middleware `import.token`), comandos
  `clippings:import`/`clippings:token`, e `tools/kindle-scraper` (Playwright) +
  `tools/usb-watcher` (PowerShell). **68 testes passando.**
- **branch `nativephp-migration`**: só carrega este handoff; a migração de código
  **ainda não começou**.
- Dados do usuário são desprezíveis (0 livros, 0 destaques) — pode migrar o banco do
  zero no Windows.

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
