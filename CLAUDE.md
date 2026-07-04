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

## Arquitetura

Laravel 12 + Inertia 2 + Vue 3 (TypeScript), a partir do starter kit oficial de Vue: componentes shadcn-vue em `resources/js/components/ui/`, páginas em `resources/js/pages/` (minúsculo — `config/inertia.php` foi ajustado para isso; `assertInertia` depende desse path), Ziggy expõe `route()` global no front.

### Fluxo de domínio

`My Clippings.txt` (upload) → `KindleClippingsParser` → `books` + `highlights` → cartão criado a partir do destaque (`CardFormDialog.vue`) → sessão de estudo (`Study.vue` + `StudyController`) → `reviews`.

- **`app/Services/KindleClippingsParser.php`**: parse do formato do Kindle com metadados em pt/en/de. Dedupe por `sha1` (único por livro) — reimportar o arquivo inteiro é seguro e é o fluxo esperado. Marcadores são descartados; notas viram `highlights.type = 'note'`.
- **`app/Services/FsrsScheduler.php`**: FSRS-4.5 com os parâmetros padrão (`W`). Estado por cartão: `stability`/`difficulty` (null = nunca revisado). Notas 1–4 (errei/difícil/bom/fácil). Nota 1 → `interval_days = 0`, `due_at = now` e o front devolve o cartão ao fim da fila da mesma sessão. Intervalo = estabilidade ajustada à retenção alvo (`config/srs.php`, env `SRS_RETENTION`). `replay()` reconstrói o estado a partir do histórico — foi usado na migração SM-2→FSRS e serve para re-otimizações futuras; por isso `reviews` guarda cada resposta com `stability_after`/`difficulty_after`. Se mudar a escala de notas, precisa migrar `reviews.rating`.
- **`app/Services/Translator.php`** (MyMemory, DE→PT-BR) e **`app/Services/ArticleDetector.php`** (gênero via wikitext do Wiktionary alemão → der/die/das, cache de 30 dias incluindo misses como `''`): ambos degradam silenciosamente para null em falha — o front trata como recurso opcional.

### Dois estilos de resposta HTTP

1. **Páginas Inertia**: mutações redirecionam; flash messages são compartilhadas via prop `flash` em `HandleInertiaRequests` (ex.: `import_result`).
2. **Endpoints JSON** (`POST /estudar/{card}`, `/traduzir`, `/artigo`): chamados do Vue com `postJson` de `resources/js/lib/api.ts`, que lê o cookie `XSRF-TOKEN` (axios não está instalado). Usados onde um reload do Inertia atrapalharia (fila de estudo, tradução dentro do dialog).

Autorização é por checagem inline de `user_id` (`abort_unless`) nos controllers — não há Policies.

## Testes

Pest 4, tudo em `tests/Feature/` (o `RefreshDatabase` do `Pest.php` só cobre esse diretório; os services usam helpers como `now()` que exigem o app bootado). Funções helper declaradas em arquivos de teste são globais — nomes precisam ser únicos entre arquivos (`fsrsCard`, `createCard`, `clippingsFile`, `wiktionaryResponse`). APIs externas sempre com `Http::fake`. O CI roda `./vendor/bin/pest` (tests.yml).
