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

## Rodando com Sail (Docker)

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite

./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

App em <http://localhost>. Sem Docker, use `php artisan serve` + `npm run dev`.

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
