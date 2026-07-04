# Wortschatz 🇩🇪

App pessoal para estudar vocabulário de alemão a partir dos destaques do Kindle, com repetição espaçada (FSRS-4.5, o algoritmo do Anki moderno).

## Como funciona

1. **Importe** o arquivo `My Clippings.txt` do seu Kindle (conecte via USB e copie da pasta `documents`). Os destaques são agrupados por livro e deduplicados — pode reenviar o arquivo sempre que quiser. Funciona com o Kindle configurado em **português, inglês ou alemão**; notas também são importadas e marcadores de página são ignorados.
2. **Crie cartões** a partir dos destaques: clique nas palavras da frase para montar a frente do cartão e use o botão **Traduzir** (DE → PT via MyMemory) para preencher o verso automaticamente. Substantivos ganham o artigo (der/die/das) automaticamente, consultado no Wiktionary alemão (com cache de 30 dias).
3. **Estude** todos os dias: o FSRS agenda cada cartão conforme sua resposta (errei / difícil / bom / fácil), estimando a estabilidade da memória para atingir a retenção alvo (90% por padrão). Cartões errados voltam na mesma sessão; os botões mostram a prévia do próximo intervalo.

O dashboard mostra cartões vencidos, sequência de dias estudados e as revisões da última semana.

### Atalhos na sessão de estudo

| Tecla | Ação |
| --- | --- |
| `Espaço` ou `Enter` | Revelar a resposta |
| `1` | Errei (volta na mesma sessão) |
| `2` | Difícil |
| `3` | Bom |
| `4` | Fácil |

## Stack

- Laravel 12 + Inertia 2 + Vue 3 + Tailwind (starter kit oficial de Vue)
- SQLite
- Pest para testes

## Modelo de dados

```
users ─┬─ books ──── highlights ──┐
       ├─ cards ←─────────────────┘   (um destaque pode gerar vários cartões)
       └─ reviews ←── cards           (histórico completo de cada revisão)
```

| Tabela | O que guarda |
| --- | --- |
| `books` | Título e autor, extraídos da primeira linha de cada entrada do clippings |
| `highlights` | Texto do destaque/nota, posição/página, data e um hash `sha1` para deduplicação |
| `cards` | Frente (alemão), verso (tradução), contexto e o estado FSRS: `stability`, `difficulty`, `interval_days`, `due_at`, `last_reviewed_at`, `repetitions`, `lapses` |
| `reviews` | Cada resposta dada: nota (1–4), intervalos antes/depois e `stability`/`difficulty` resultantes — é o insumo para re-otimizar os parâmetros do FSRS no futuro |

## Rotas

Páginas (Inertia, autenticadas): `/dashboard`, `/estudar`, `/importar`, `/livros`, `/livros/{id}`, `/cartoes`.

Endpoints JSON (sessão web + CSRF):

| Endpoint | Função |
| --- | --- |
| `POST /estudar/{card}` | Registra a resposta (`rating` 1–4), reagenda pelo FSRS e devolve o cartão atualizado |
| `POST /traduzir` | Traduz um texto DE → PT-BR via MyMemory (`text`) |
| `POST /artigo` | Detecta o artigo de um substantivo via Wiktionary (`word`) → `der`/`die`/`das` ou `null` |

As demais rotas de livros e cartões são CRUD padrão via Inertia (ver `routes/web.php`).

## Importação automática

Além do upload manual em `/importar`, há uma API stateless (token via `Authorization: Bearer`, sem sessão/CSRF) pensada para automação:

| Endpoint | Payload | Usado por |
| --- | --- | --- |
| `POST /api/importar/arquivo` | multipart com o `My Clippings.txt` (campo `file`) | Watcher USB |
| `POST /api/importar/destaques` | JSON `{"entries": [{"title", "content", "author?", "type?", "location?", "page?", "highlighted_at?"}]}` (máx. 5000) | Scraper do Amazon Notebook |

Ambos respondem `{"imported": n, "skipped": n, "books": n}` e deduplicam pelo mesmo hash do upload manual — reenviar tudo é sempre seguro. O token é por usuário e fica no banco (nada no `.env`):

```bash
php artisan clippings:token            # gera (e substitui) o token
php artisan clippings:token --revoke   # revoga
php artisan clippings:import <path>    # import direto de um arquivo, sem HTTP
```

As duas automações prontas moram em `tools/` (fora do lint e do CI):

- **[`tools/kindle-scraper/`](tools/kindle-scraper/)** — puxa os destaques de `ler.amazon.com.br/notebook` com Playwright e envia para a API. Cobre livros comprados na Amazon, sem cabo.
- **[`tools/usb-watcher/`](tools/usb-watcher/)** — tarefa agendada do Windows que detecta o Kindle plugado no USB e envia o `My Clippings.txt`. Cobre livros sideloaded.

Nota: para o dedupe funcionar entre fontes, o livro é identificado **pelo título** (o autor só é gravado na criação) — as fontes grafam o autor de formas diferentes. Se o título do catálogo da Amazon divergir do gravado pelo Kindle, pode surgir livro duplicado; ajuste em `/livros`.

## Rodando com Sail (Docker)

Primeira vez:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
```

Dia a dia, um comando só (inicia o Docker se estiver parado, sobe o Sail e o Vite):

```bash
./dev          # sobe tudo
./dev down     # derruba os containers
```

App em <http://app.wortschatz.localhost:8000> (domínios `*.localhost` resolvem para a própria máquina nos navegadores modernos — não precisa mexer no hosts). Sem Docker, `php artisan serve` + `npm run dev` atendem na mesma URL.

> **WSL**: para o Docker iniciar sozinho junto com o WSL e o `./dev` nunca pedir senha, rode uma vez: `sudo systemctl enable docker`.

O banco é o arquivo `database/database.sqlite` no próprio projeto, montado no container via bind mount — subir/derrubar/reconstruir containers **não apaga nenhum dado**.

## Comandos úteis

```bash
php artisan test      # testes (parser do Kindle, FSRS, import, estudo, artigos)
./vendor/bin/pint     # formatação PHP
npm run format        # Prettier
npm run lint          # ESLint
```

## Configuração opcional

| Variável | Descrição |
| --- | --- |
| `MYMEMORY_EMAIL` | E-mail enviado à API do MyMemory; aumenta a cota gratuita de tradução de ~5k para ~50k caracteres/dia |
| `SRS_RETENTION` | Retenção alvo do FSRS (padrão `0.90`); valores maiores geram revisões mais frequentes |
| `SRS_MAX_INTERVAL` | Intervalo máximo entre revisões, em dias (padrão `36500`) |
