# Plano — Extensão de navegador estilo Readlang → Wortschatz

> Extensão de navegador que permite marcar palavras/frases em qualquer página web,
> ver a tradução inline e criar flashcards no Wortschatz automaticamente.

## Decisões consolidadas

- **Backend:** deploy remoto (HTTPS), com URL configurável na extensão (para poder testar também em `localhost`).
- **Fluxo (Readlang):** marcar palavra(s) → tooltip com tradução inline → **marcar já cria o card** (highlight + card) e grifa a palavra na página. Idioma **DE/EN** selecionável.
- **Agrupamento:** um **"livro" por site/página** (título da aba como título do livro; domínio/URL como origem).

## Arquitetura

Uma extensão MV3 tem 3 peças que conversam com o Laravel:

```
┌─────────────────────────────────────────────────────────┐
│  NAVEGADOR (qualquer página web em alemão)               │
│                                                          │
│  content script  ──► detecta seleção de texto           │
│       │              mostra tooltip com tradução         │
│       │                                                  │
│       ▼                                                  │
│  background (service worker) ──► fala com o Laravel      │
└───────────────────────────────┬─────────────────────────┘
                                 │  HTTP + Bearer token
                                 ▼
┌─────────────────────────────────────────────────────────┐
│  SEU LARAVEL (deploy remoto ou localhost)                │
│  routes/api.php  → cria highlight + card, traduz, artigo │
│  middleware import.token  → autentica a extensão         │
│  CORS  → permite a extensão chamar                       │
└─────────────────────────────────────────────────────────┘
```

**Por que token e não sessão/CSRF?** A extensão roda em *outros* domínios (spiegel.de, um blog, etc.).
Ela não tem o cookie de sessão do app nem consegue ler o `XSRF-TOKEN`. A forma limpa é uma API
stateless autenticada por um token pessoal — exatamente o que o `CLAUDE.md` já previa
(`users.import_token`, comando `clippings:token`). A extensão guarda o token e o envia em todo request.

> **Nota:** o `CLAUDE.md` descreve uma API stateless de importação (`routes/api.php`, `import.token`,
> CORS) que **ainda não existe no código** — é estado desejado. Parte deste plano é criar essa camada.

---

## Parte A — Backend Laravel (a API que hoje não existe)

### A1. Coluna, comando e middleware de token

Materializa o que o `CLAUDE.md` já descrevia.

- **Migration** `add_import_token_to_users`: coluna `import_token` (string, nullable, unique).
- **Model `User`**: adicionar `import_token` ao `$hidden` (o `HandleInertiaRequests` serializa o user inteiro para o front — o token não pode vazar).
- **Comando** `app/Console/Commands/GenerateImportToken.php` (`clippings:token`): gera `Str::random(64)`, grava via `forceFill`, imprime uma vez. Reaproveitável pela extensão e pelos scripts em `tools/`.
- **Middleware** `app/Http/Middleware/AuthenticateImportToken.php` (alias `import.token` em `bootstrap/app.php`): lê `Authorization: Bearer <token>`, casa com `users.import_token`, faz `Auth::login($user)` na request (stateless), `abort(401)` se falhar.

### A2. CORS

- **`config/cors.php`**: `paths => ['api/*']`, `allowed_methods => ['*']`, `allowed_origins` com a origem da extensão (`chrome-extension://<ID>`), o deploy (`https://<seu-deploy>`) e `http://localhost:*`. `supports_credentials => false` (usamos Bearer token, não cookie). Registrar `HandleCors` no `bootstrap/app.php`.
- **Detalhe MV3:** a origem de uma extensão é `chrome-extension://<extension-id>`. O ID muda entre a versão de dev (unpacked) e a publicada. Solução: gerar uma `key` fixa no `manifest.json` para travar o ID, ou permitir o padrão via callback de origem. Decidido na implementação.

### A3. Rotas de API (`routes/api.php`, registrado em `bootstrap/app.php`)

Grupo com middleware `import.token`:

| Método | Rota | Ação |
|---|---|---|
| `POST` | `/api/traduzir` | Reusa `Translator::translate($text, $source)` → `{translation}` |
| `POST` | `/api/artigo` | Reusa `ArticleDetector::detect($word)` → `{article}` |
| `POST` | `/api/web/cartao` | **Endpoint principal:** cria book(origem)+highlight+card a partir de texto marcado |
| `GET` | `/api/web/marcados?url=` | (opcional) devolve palavras já marcadas de uma URL, para regrifar ao reabrir a página |

Os endpoints web-específicos ficam sob `/api/web/*` para não colidir com a API stateless de importação
em lote que o `CLAUDE.md` prevê (`/api/importar/*`).

### A4. Controller principal — `WebCardController@store` (`POST /api/web/cartao`)

Payload da extensão:

```json
{
  "text": "Verständnis",            // palavra/frase marcada (frente do card)
  "context": "...frase inteira...", // contexto = sentença da página
  "source": "de",                   // idioma (de|en)
  "url": "https://spiegel.de/...",  // origem
  "page_title": "Título do artigo"  // vira nome do 'livro'
}
```

Lógica:

1. `firstOrCreate` do **Book** por `(user_id, title)` onde `title = page_title || domínio`, guardando a URL/domínio como origem.
2. Traduz (`Translator`) e detecta artigo (`ArticleDetector`) server-side → `back`.
3. Cria **Highlight** (`content = text`, `location = url`) com dedupe pelo `Highlight::computeHash(...)` — remarcar a mesma palavra não duplica.
4. Cria **Card** via `user()->cards()->create([...])` com `front`, `back`, `context`, `due_at => now()`.
5. Retorna JSON `{ card_id, translation, article, already_existed }`.

> **Ponto de atenção:** o `Book` pode ter colunas `NOT NULL` (algo que a importação Kindle sempre
> preenche). Verificar o schema antes do `firstOrCreate`. Pode ser necessária uma migration para
> representar a origem web (ex.: `books.source_url` nullable).

### A5. Testes (Pest, `tests/Feature/`)

- `WebApiAuthTest`: 401 sem token / com token errado; 200 com token válido.
- `WebCardCreationTest`: cria book+highlight+card; `Http::fake` para MyMemory e Wiktionary; remarcar a mesma palavra não duplica (dedupe por hash); ownership.
- `WebTranslateTest` / `WebArticleTest`: espelham os testes de `/traduzir` e `/artigo` já existentes, agora via Bearer token.
- Helpers globais únicos (convenção do projeto): `webApiUser`, `webCardPayload`.

---

## Parte B — Extensão (Chrome/Firefox, Manifest V3)

Pasta nova `extension/`, fora do lint/CI (como `tools/` já é — adicionar ao `.prettierignore` e `eslint.config.js`).

### B1. `manifest.json`

MV3, `permissions: [storage, activeTab, scripting]`, `host_permissions` para o backend, content script em `<all_urls>` (ou lista de domínios), `options_page`, `background.service_worker`.

### B2. Página de opções (`options.html/js`)

Campos: **URL do backend** (default do deploy, editável para `localhost`) e **token de importação** (o que sai do `clippings:token`). Salvos em `chrome.storage.sync`.

### B3. Content script (`content.js` + `content.css`) — o coração Readlang

- Escuta seleção de texto (`mouseup` / `selectionchange`).
- Ao selecionar, mostra um **tooltip flutuante** na seleção com estado "traduzindo…".
- Manda a seleção ao **background** → que chama `POST /api/web/cartao` (marcar já cria o card).
- Tooltip exibe **artigo + tradução** retornados; a palavra na página ganha um **grifo** (`<span>` com classe da extensão) = "salvo no Wortschatz".
- Seletor de idioma (DE/EN) no tooltip ou nas opções.
- Ao reabrir a página, opcionalmente busca `/api/web/marcados?url=` e regrifa o que já foi salvo.

### B4. Background service worker (`background.js`)

- Recebe mensagens do content script, lê URL+token do storage, faz `fetch` com `Authorization: Bearer`, trata erros (401 → avisa "configure o token nas opções"), devolve a resposta.
- Centralizar o `fetch` aqui evita problemas de CORS/mixed-content no content script.

### B5. Ícones + README de instalação

Como carregar unpacked (`chrome://extensions` → modo dev → "carregar sem compactação"), como pegar o token, como apontar para local vs. deploy.

---

## Parte C — Publicação / distribuição

Da pasta `extension/` até algo instalável:

```
extension/  (código)
   │  empacotar
   ▼
extension.zip  (manifest.json na raiz do zip)
   │  upload no Developer Dashboard
   ▼
Ficha da loja (ícone, descrição, screenshots, política de privacidade)
   │  revisão do Google
   ▼
Publicada  →  instalável via link/busca
```

### C1. Caminho recomendado para desenvolver — **unpacked (sem loja)**

Para uso pessoal e durante todo o desenvolvimento, não precisa de loja:

1. `chrome://extensions`
2. Ligar **"Modo do desenvolvedor"** (canto superior direito).
3. **"Carregar sem compactação"** → apontar para a pasta `extension/`.

- **Prós:** grátis, instantâneo, sem revisão; editar o código e recarregar na hora.
- **Contras:** só na máquina local (repetir em cada computador); aviso periódico do Chrome sobre "extensões em modo desenvolvedor"; o **ID da extensão muda a cada recarga** — a menos que se fixe uma `key` no `manifest.json` (importa para o CORS, ver A2 e o risco de ID).

### C2. Caminho para usar em qualquer máquina — **Chrome Web Store**

1. **Conta de desenvolvedor:** taxa **única de US$ 5** no [Developer Dashboard](https://chrome.google.com/webstore/devconsole) (vale para sempre, até 20 extensões).
2. **Empacotar:** zipar o conteúdo de `extension/` (com `manifest.json` na raiz). O manifest precisa de `version`, `name`, `description` e ícones 16/48/128.
3. **Ficha da loja:** ícone 128×128, nome, descrição, **≥1 screenshot** (1280×800 ou 640×400), categoria, idioma.
4. **Política de privacidade (obrigatória):** a extensão coleta dados (o texto selecionado e o token vão para o backend do usuário). Exige URL de política de privacidade e declaração de uso de dados.
5. **Justificar permissões:** `host_permissions` amplo (`<all_urls>`) + envio de texto a servidor externo → o Google pede justificativa por permissão e pode acionar **revisão manual** (de dias a ~2 semanas). Justificativa legítima: "envia o texto selecionado ao backend do próprio usuário para criar flashcards".
6. **Escolher visibilidade:**

   | Modo | Quem instala | Revisão | Bom para |
   |---|---|---|---|
   | **Público** | Qualquer um na loja | Completa (mais rígida) | Distribuir para todos |
   | **Não listado** | Só quem tem o link | Mais leve | Você + amigos, sem aparecer na busca |
   | **Privado** | Só contas autorizadas | Mais leve | Uso pessoal / grupo fechado |

### C3. Recomendação para este projeto

- **Desenvolver e validar:** unpacked (C1), zero burocracia.
- **Usar de qualquer máquina** (o backend já é deploy remoto): publicar em modo **não listado** ou **privado** (C2), evitando a revisão pública mais rígida.
- **Fixar a `key` no manifest** desde cedo para o ID ser estável entre dev e loja (destrava o CORS — A2).

---

## Ordem de execução

1. **Backend A1–A2** (token + CORS) — fundação.
2. **Backend A3–A4** (rotas + `WebCardController`) — verificando o schema de `Book` antes.
3. **Backend A5** (testes) — rodar `php artisan test`, Pint.
4. **Extensão B1–B5** — testável contra o backend já pronto.
5. Ajuste de CORS com o ID real da extensão.

## Riscos / pontos a confirmar na implementação

- **Schema do `Book`** para representar origem web sem violar `NOT NULL` (pode pedir migration).
- **ID da extensão no CORS** (dev vs. publicada).
- **Rate limit do MyMemory** (tradução): marcar muitas palavras rápido pode estourar cota — talvez cache/debounce.
- **Ambiente Windows/NativePHP:** o deploy remoto contorna isso; o `clippings:token` roda local, sem problema.
