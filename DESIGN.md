# Wortschatz — Sistema de Design "Papel Eletrônico"

Referência de estilo do Wortschatz, pensada para ser reimplementada em outras
plataformas (web, mobile, etc.). A **fonte da verdade** em código é
[`resources/css/app.css`](resources/css/app.css) (tokens) +
[`tailwind.config.js`](tailwind.config.js) (mapeamento). Este documento descreve
a intenção por trás dos valores.

## Conceito

Uma interface **monocromática e quente, como um Kindle e-ink**. A decisão nasceu
das capas de livro: elas chegam do próprio aparelho em tons de cinza (o e-ink é
grayscale), então a UI inteira acompanha essa estética de papel.

### Princípios

1. **Papel e tinta, não branco e preto puros.** O fundo é um off-white quente
   (cor de papel) e o texto é uma "tinta" quase-preta amarronzada. Neutros com
   viés quente, nunca cinza clínico.
2. **Superfícies planas.** E-ink não tem brilho nem profundidade: bordas finas
   (hairline) em vez de sombras, cantos levemente arredondados.
3. **Cor só onde ela ensina.** A interface é acromática. Cor aparece apenas nas
   **pistas funcionais** — gênero dos substantivos (der/die/das) e notas de
   estudo — e, mesmo assim, **dessaturada**, no tom de um e-ink colorido
   (Kindle Colorsoft). Nada de cores vivas.
4. **Serifada para ler, sans para operar.** Títulos e o conteúdo em alemão usam
   uma serifada de leitura (Literata); controles, rótulos e dados usam sans.
5. **Dois modos.** Claro (papel) e escuro ("modo noturno" e-ink), ambos quentes.
   O escuro não é uma inversão ingênua — é recalibrado para contraste legível.

## Tokens de cor — base

Formato canônico: **HSL** (`H S% L%`), como as variáveis CSS as guardam. O hex é
conveniência para plataformas que preferem. Cada token tem valor no claro e no
escuro.

| Token | Uso | Claro (HSL) | Claro (hex) | Escuro (HSL) | Escuro (hex) |
|-------|-----|-------------|-------------|--------------|--------------|
| `background` | Fundo da página (papel) | `40 30% 96%` | `#F8F6F2` | `30 8% 10%` | `#1C1A17` |
| `foreground` | Texto (tinta) | `30 12% 15%` | `#2B2622` | `40 14% 87%` | `#E2DFD9` |
| `card` / `popover` | Superfícies elevadas | `40 33% 98.5%` | `#FCFCFA` | `30 8% 13%` | `#24211E` |
| `primary` | Ação principal (botão sólido) | `30 10% 18%` | `#322E29` | `40 14% 85%` | `#DEDBD3` |
| `muted` | Fundos sutis / preenchimentos | `40 18% 92%` | `#EEECE7` | `30 6% 17%` | `#2E2B29` |
| `muted-foreground` | Texto secundário | `35 8% 40%` | `#6E675E` | `38 8% 60%` | `#A19B91` |
| `accent` | Hover / seleção | `40 20% 89%` | `#E9E5DD` | `30 6% 21%` | `#393632` |
| `border` / `input` | Bordas hairline | `38 16% 83%` | `#DBD5CD` | `30 7% 22%` | `#3C3834` |
| `destructive` | Perigo / erro (tijolo suave) | `6 45% 47%` | `#AE4D42` | `6 48% 55%` | `#C36055` |

Observações:
- **`primary` inverte entre modos**: tinta escura no claro, papel claro no
  escuro. É sempre o oposto do fundo — um botão "carimbado", não colorido.
- `secondary`, `ring`, `sidebar-*` e `chart-*` seguem a mesma família; veja
  `app.css` para os valores completos. `chart-1` é um cinza-tinta (gráficos são
  monocromáticos); `chart-2..5` reaproveitam as cores funcionais quando um
  gráfico precisa de séries.

## Tokens de cor — funcionais (dessaturados, "e-ink colorido")

A **única** cor da interface. Saturação baixa (24–46%) de propósito — devem
parecer suaves, "lavadas", como tinta e-ink colorida.

| Token | Significado | Claro (HSL) | Claro (hex) | Escuro (HSL) | Escuro (hex) |
|-------|-------------|-------------|-------------|--------------|--------------|
| `der` | Substantivo masculino | `210 30% 46%` | `#527598` | `210 38% 67%` | `#8BABCB` |
| `die` | Substantivo feminino | `348 34% 52%` | `#AE5B6C` | `348 42% 69%` | `#D18F9C` |
| `das` | Substantivo neutro | `150 24% 38%` | `#4A7861` | `150 28% 57%` | `#73B091` |
| `grade-1` | Nota "Errei" | `6 42% 46%` | `#A74E44` | `6 46% 62%` | `#CB7A72` |
| `grade-2` | Nota "Difícil" | `34 42% 44%` | `#9F7641` | `34 46% 58%` | `#C59A63` |
| `grade-3` | Nota "Bom" (= `das`) | `150 24% 38%` | `#4A7861` | `150 28% 57%` | `#73B091` |
| `grade-4` | Nota "Fácil" (= `der`) | `210 30% 46%` | `#527598` | `210 38% 67%` | `#8BABCB` |

Estados semânticos genéricos reaproveitam esses tokens: **sucesso → `grade-3`**,
**atenção/pendência → `grade-2`**, **erro → `destructive`**. Não introduza cores
novas para status.

## Tipografia

| Papel | Família | Onde |
|-------|---------|------|
| Leitura / títulos | **Literata** (serifada de e-reader), fallback `Georgia, 'Palatino Linotype', serif` | `h1/h2/h3`, títulos de livro, a palavra em alemão no cartão de estudo |
| Interface | **Instrument Sans**, fallback `system-ui, sans-serif` | Corpo, controles, rótulos, dados, navegação |

Regras:
- A serifada carrega a "cara de livro"; use-a nos **títulos e no conteúdo em
  alemão**, não em botões/labels.
- Dados numéricos alinhados usam `tabular-nums`.
- Rótulos em maiúsculas levam leve `letter-spacing` (~0.14em).
- Corpo de leitura perto de 65 caracteres de largura.

No app as fontes vêm do bunny.net (ver [`app.blade.php`](resources/views/app.blade.php)).
Em outra plataforma, embarque as fontes ou use as fallbacks.

## Forma e elevação

- **Raio**: `--radius: 0.45rem` (≈7px). Derivados: `md = radius − 2px`,
  `sm = radius − 4px`. Levemente arredondado, não pílula.
- **Bordas**: hairline de 1px em `border`. É o principal recurso de separação —
  preferir borda a sombra.
- **Sombra**: mínima ou nenhuma. E-ink é plano. Onde houver (ex.: capa de livro),
  manter muito sutil.
- **Opacidade (convenções)**: bordas funcionais em `/40`; hover de fundo em
  `/10`; tinte de fundo sutil em `/5`; realce de badge em `/15`. Os tokens
  funcionais e `destructive` são definidos com `<alpha-value>` para permitir
  esses modificadores.

## Convenções de componente

- **Botão primário**: fundo `primary`, texto `primary-foreground` (tinta sólida
  invertida). **Botão outline/ghost**: transparente com borda `border`, hover
  `muted`/`accent`.
- **Card**: fundo `card`, borda `border`, sem sombra pesada.
- **Badge de status**: borda `/30–40` + texto no token, fundo opcional `/10–15`.
- **Capa de livro**: imagem real chega em tons de cinza (e-ink). Sem imagem,
  gera-se uma **"capa de pano"** — gradiente neutro e quente (`hsl(30 9% L%)`,
  claridade variando por título), nunca colorida.
- **Gráficos**: barras em `chart-1` (cinza-tinta); o ponto de destaque (dia
  atual) pode receber uma cor funcional suave.

## Portando para outras plataformas

1. **HSL é a fonte.** Guarde os tokens como HSL e derive hex/RGB na plataforma.
   Isso mantém as relações de claridade coerentes ao ajustar o tema.
2. **Dois temas via tokens.** Defina os dois conjuntos (claro/escuro) e troque
   no nível do token, nunca por cor hardcoded no componente. Deixe o componente
   referenciar sempre o token semântico (`background`, `primary`, `der`…).
3. **Alpha.** As pistas funcionais precisam de variações translúcidas (`/40`,
   `/10`). Garanta que a plataforma consiga aplicar opacidade sobre a cor do
   token (no CSS/Tailwind isso exige o placeholder `<alpha-value>`; em
   SwiftUI/Compose/Flutter use `Color.opacity(...)` / `.copy(alpha=...)`).
4. **Mapeamento por plataforma:**
   - **CSS/Tailwind**: já implementado — variáveis `--token` + `hsl(var(--token))`.
   - **SwiftUI**: um enum de `Color` por token, com `Color(light:dark:)` ou um
     `Asset Catalog` com Any/Dark Appearance.
   - **Jetpack Compose**: dois `ColorScheme` (claro/escuro) em um `MaterialTheme`
     custom; os tokens funcionais como cores extras no tema.
   - **Flutter**: `ThemeData` claro/escuro + uma extensão de tema
     (`ThemeExtension`) para `der/die/das/grade`.
5. **Não recolorir as pistas funcionais.** der/die/das e grade-1..4 são
   contrato de aprendizado — mantenha os mesmos matizes (só ajuste claridade por
   modo, como na tabela).

## Fonte da verdade (código)

| Arquivo | Papel |
|---------|-------|
| [`resources/css/app.css`](resources/css/app.css) | Todos os tokens (claro `:root` / escuro `.dark`) + serif nos títulos |
| [`tailwind.config.js`](tailwind.config.js) | Mapeia tokens → utilitários (`bg-*`, `text-*`, `font-serif`) |
| [`resources/js/lib/german.ts`](resources/js/lib/german.ts) | Cores dos artigos (der/die/das) |
| [`resources/views/app.blade.php`](resources/views/app.blade.php) | Carregamento das fontes |
