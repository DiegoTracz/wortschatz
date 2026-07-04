# Kindle Scraper

Puxa os destaques da página de anotações da Amazon ([ler.amazon.com.br/notebook](https://ler.amazon.com.br/notebook)) e envia para o Wortschatz via `POST /api/importar/destaques`. Cobre os livros comprados na Amazon; para livros sideloaded, use o watcher USB (`tools/usb-watcher/`) ou `php artisan clippings:import`.

## Setup (uma vez)

```bash
cd tools/kindle-scraper
npm install
npx playwright install chromium
cp .env.example .env
# preencha IMPORT_TOKEN com a saída de: php artisan clippings:token
npm run login   # abre um navegador; faça login na Amazon (com 2FA) e aguarde salvar a sessão
```

O login abre um navegador com interface gráfica — no WSL2 isso exige WSLg (padrão no Windows 11). Se não tiver, rode este diretório com o Node do Windows.

## Uso

```bash
npm run scrape        # extrai tudo e envia para o app (o ./dev precisa estar de pé)
npm run scrape:dry    # só imprime o JSON extraído, sem enviar — bom para inspecionar títulos
```

Reexecutar é seguro: o app deduplica por hash (destaques já importados voltam como "ignorados"). Quando a sessão da Amazon expirar, o script avisa — basta rodar `npm run login` de novo.

### Agendando

Cron no WSL (todo dia às 8h, exige app de pé):

```
0 8 * * * cd ~/wortschatz/tools/kindle-scraper && npm run scrape >> scrape.log 2>&1
```

## Limitações

- A Amazon limita os destaques exportáveis a ~10% do livro (definido pela editora) — o notebook pode não mostrar tudo.
- O DOM da página não é documentado e muda de tempos em tempos; os seletores ficam centralizados em `src/selectors.js`.
- Se o título no catálogo da Amazon divergir do título gravado no `My Clippings.txt` (subtítulos, edição), o livro pode duplicar — confira com `npm run scrape:dry` e ajuste em `/livros`.
