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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA_VUE) - v2
- tailwindcss (TAILWINDCSS) - v3
- vue (VUE) - v3
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
