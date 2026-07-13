<script setup lang="ts">
import CardFormDialog from '@/components/CardFormDialog.vue';
import { Button } from '@/components/ui/button';
import { usePdf } from '@/composables/usePdf';
import { deleteJson, postJson } from '@/lib/api';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Loader2, Plus, Search, Trash2, X, ZoomIn, ZoomOut } from 'lucide-vue-next';
import type { PageViewport } from 'pdfjs-dist';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';

interface Rect {
    x0: number;
    y0: number;
    x1: number;
    y1: number;
}

interface PdfHighlight {
    id: number;
    content: string;
    page: number;
    anchor: { page?: number; rects: Rect[]; quote?: unknown };
    cards: { id: number; front: string }[];
}

interface Box {
    left: number;
    top: number;
    width: number;
    height: number;
}

const props = defineProps<{
    book: { id: number; title: string; page_count: number | null };
    fileUrl: string;
    highlights: PdfHighlight[];
}>();

const pdf = usePdf();

const canvasEl = ref<HTMLCanvasElement | null>(null);
const textLayerEl = ref<HTMLElement | null>(null);
const pageWrapperEl = ref<HTMLElement | null>(null);

const currentPage = ref(1);
const scale = ref(1.2);
const viewport = shallowRef<PageViewport | null>(null);
const rendering = ref(false);
const renderError = ref<string | null>(null);

// Cópia local: reflete criações/remoções na hora e ressincroniza quando a página
// Inertia recarrega os props (após criar cartão, com preserveState).
const localHighlights = ref<PdfHighlight[]>([...props.highlights]);
watch(
    () => props.highlights,
    (value) => {
        localHighlights.value = [...value];
    },
);

const pageInput = ref('1');
watch(currentPage, (value) => {
    pageInput.value = String(value);
});

const totalPages = computed(() => pdf.numPages.value || props.book.page_count || 1);

// Popover da seleção atual (criar destaque/cartão).
const selection = ref<{ content: string; rects: Rect[]; box: Box } | null>(null);

// Popover de um destaque existente (criar outro cartão / remover).
const activeHighlight = ref<{ highlight: PdfHighlight; left: number; top: number } | null>(null);

// Diálogo de cartão (componente reaproveitado do fluxo do Kindle).
const dialogOpen = ref(false);
const dialogHighlight = ref<{ id: number; content: string } | null>(null);
const dialogPreset = ref<string | null>(null);

const actionError = ref<string | null>(null);

// Busca dentro do PDF.
const searchOpen = ref(false);
const searchQuery = ref('');
const searchResults = ref<{ page: number; snippet: string }[]>([]);
const searching = ref(false);

function rectToBox(vp: PageViewport, rect: Rect): Box {
    const [a, b, c, d] = vp.convertToViewportRectangle([rect.x0, rect.y0, rect.x1, rect.y1]);
    return { left: Math.min(a, c), top: Math.min(b, d), width: Math.abs(c - a), height: Math.abs(d - b) };
}

// Overlays da página atual, já em coordenadas de tela.
const pageHighlights = computed(() => {
    const vp = viewport.value;
    if (!vp) {
        return [];
    }

    return localHighlights.value
        .filter((highlight) => highlight.page === currentPage.value)
        .map((highlight) => ({
            highlight,
            carded: highlight.cards.length > 0,
            boxes: highlight.anchor.rects.map((rect) => rectToBox(vp, rect)),
        }));
});

let renderToken = 0;

async function render(): Promise<void> {
    clearPopovers();
    await nextTick();
    if (!canvasEl.value || !textLayerEl.value) {
        return;
    }

    const token = ++renderToken;
    rendering.value = true;
    renderError.value = null;
    try {
        const vp = await pdf.renderPage(currentPage.value, canvasEl.value, textLayerEl.value, scale.value);
        // Ignora renders obsoletos (troca rápida de página/zoom).
        if (token === renderToken) {
            viewport.value = vp;
        }
    } catch (e) {
        if (token === renderToken) {
            renderError.value = e instanceof Error ? `${e.name}: ${e.message}` : String(e);
        }
    } finally {
        if (token === renderToken) {
            rendering.value = false;
        }
    }
}

// Debug temporário: manda o erro pro log do Laravel (o console do Electron fica
// inundado pelos broadcasts do NativePHP).
function reportError(where: string, message: string): void {
    postJson(route('debug.pdf_log'), { message: `[${where}] ${message}`, url: props.fileUrl }).catch(() => {});
}

// Captura infalível: qualquer erro/rejeição não tratada (inclusive crashes de
// update do Vue) vai pro log do Laravel enquanto o leitor está aberto.
function onGlobalRejection(event: PromiseRejectionEvent): void {
    const reason = event.reason as { stack?: string; message?: string } | undefined;
    reportError('unhandledrejection', reason?.stack ?? reason?.message ?? String(event.reason));
}
function onGlobalError(event: ErrorEvent): void {
    reportError('window.error', event.error?.stack ?? event.message);
}

onMounted(async () => {
    window.addEventListener('unhandledrejection', onGlobalRejection);
    window.addEventListener('error', onGlobalError);

    await pdf.load(props.fileUrl);
    if (pdf.error.value) {
        reportError('load', pdf.error.value);
        return;
    }
    await render();
    if (renderError.value) {
        reportError('render', renderError.value);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('unhandledrejection', onGlobalRejection);
    window.removeEventListener('error', onGlobalError);
    pdf.destroy();
});

watch([currentPage, scale], () => render());

function goToPage(page: number): void {
    const clamped = Math.min(Math.max(1, page), totalPages.value);
    if (clamped !== currentPage.value) {
        currentPage.value = clamped;
    }
    pageWrapperEl.value?.scrollIntoView({ block: 'start' });
}

function submitPageInput(): void {
    const parsed = Number.parseInt(pageInput.value, 10);
    if (Number.isFinite(parsed)) {
        goToPage(parsed);
    } else {
        pageInput.value = String(currentPage.value);
    }
}

function zoomIn(): void {
    scale.value = Math.min(3, Math.round((scale.value + 0.2) * 10) / 10);
}

function zoomOut(): void {
    scale.value = Math.max(0.6, Math.round((scale.value - 0.2) * 10) / 10);
}

function clearPopovers(): void {
    selection.value = null;
    activeHighlight.value = null;
    actionError.value = null;
}

function clearBrowserSelection(): void {
    window.getSelection()?.removeAllRanges();
}

/**
 * Captura da seleção: gera a âncora (retângulos em coordenadas PDF) ou, se o
 * clique caiu sobre um destaque existente, abre o popover dele.
 */
function onPointerUp(event: MouseEvent): void {
    const vp = viewport.value;
    const layer = textLayerEl.value;
    if (!vp || !layer) {
        return;
    }

    const sel = window.getSelection();
    const text = sel?.toString().trim() ?? '';

    if (sel && !sel.isCollapsed && sel.rangeCount && text.length >= 2) {
        const range = sel.getRangeAt(0);
        if (!layer.contains(range.commonAncestorContainer)) {
            return;
        }

        const layerRect = layer.getBoundingClientRect();
        const rects: Rect[] = [];
        let minLeft = Infinity;
        let maxBottom = -Infinity;

        for (const cr of Array.from(range.getClientRects())) {
            if (cr.width < 1 || cr.height < 1) {
                continue;
            }
            const vx0 = cr.left - layerRect.left;
            const vy0 = cr.top - layerRect.top;
            const vx1 = cr.right - layerRect.left;
            const vy1 = cr.bottom - layerRect.top;
            const [px0, py0] = vp.convertToPdfPoint(vx0, vy0);
            const [px1, py1] = vp.convertToPdfPoint(vx1, vy1);
            rects.push({ x0: Math.min(px0, px1), y0: Math.min(py0, py1), x1: Math.max(px0, px1), y1: Math.max(py0, py1) });
            minLeft = Math.min(minLeft, vx0);
            maxBottom = Math.max(maxBottom, vy1);
        }

        if (rects.length) {
            selection.value = { content: text, rects, box: { left: minLeft, top: maxBottom + 6, width: 0, height: 0 } };
            activeHighlight.value = null;
        }
        return;
    }

    // Clique simples: abre o destaque sob o cursor, se houver.
    const layerRect = layer.getBoundingClientRect();
    const cx = event.clientX - layerRect.left;
    const cy = event.clientY - layerRect.top;
    const hit = pageHighlights.value.find((ph) =>
        ph.boxes.some((b) => cx >= b.left && cx <= b.left + b.width && cy >= b.top && cy <= b.top + b.height),
    );

    if (hit) {
        const box = hit.boxes[0];
        activeHighlight.value = { highlight: hit.highlight, left: box.left, top: box.top + box.height + 6 };
        selection.value = null;
    } else {
        clearPopovers();
    }
}

async function persistSelection(): Promise<PdfHighlight | null> {
    if (!selection.value) {
        return null;
    }

    try {
        const saved = await postJson<{ id: number; content: string; page: number; anchor: PdfHighlight['anchor'] }>(
            route('highlights.store', props.book.id),
            {
                content: selection.value.content,
                page: currentPage.value,
                anchor: { page: currentPage.value, rects: selection.value.rects },
            },
        );

        let highlight = localHighlights.value.find((h) => h.id === saved.id);
        if (!highlight) {
            highlight = { id: saved.id, content: saved.content, page: saved.page, anchor: saved.anchor, cards: [] };
            localHighlights.value.push(highlight);
        }
        return highlight;
    } catch (error) {
        actionError.value = error instanceof Error ? error.message : 'Falha ao salvar destaque.';
        return null;
    }
}

async function markSelection(): Promise<void> {
    await persistSelection();
    selection.value = null;
    clearBrowserSelection();
}

async function createCardFromSelection(): Promise<void> {
    const text = selection.value?.content ?? '';
    const singleWord = !!text && !/\s/.test(text);
    const highlight = await persistSelection();
    selection.value = null;
    clearBrowserSelection();
    if (highlight) {
        openCardDialog(highlight, singleWord ? text : null);
    }
}

function openCardDialog(highlight: PdfHighlight, preset: string | null): void {
    dialogHighlight.value = { id: highlight.id, content: highlight.content };
    dialogPreset.value = preset;
    dialogOpen.value = true;
    clearPopovers();
}

async function removeHighlight(highlight: PdfHighlight): Promise<void> {
    try {
        await deleteJson(route('highlights.destroy', highlight.id));
        localHighlights.value = localHighlights.value.filter((h) => h.id !== highlight.id);
        activeHighlight.value = null;
    } catch (error) {
        actionError.value = error instanceof Error ? error.message : 'Falha ao remover destaque.';
    }
}

async function runSearch(): Promise<void> {
    const query = searchQuery.value.trim();
    if (query.length < 2) {
        searchResults.value = [];
        return;
    }

    searching.value = true;
    try {
        const url = `${route('books.search', props.book.id)}?q=${encodeURIComponent(query)}`;
        const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const data = (await response.json()) as { results: { page: number; snippet: string }[] };
        searchResults.value = data.results;
    } catch {
        searchResults.value = [];
    } finally {
        searching.value = false;
    }
}
</script>

<template>
    <Head :title="book.title" />

    <div class="pdf-reader flex h-screen flex-col bg-muted/40">
        <!-- Barra superior -->
        <header class="z-20 flex items-center gap-2 border-b bg-background px-3 py-2 shadow-sm">
            <Button variant="ghost" size="sm" as-child>
                <Link :href="route('books.show', book.id)"><ChevronLeft class="size-4" /> Voltar</Link>
            </Button>

            <p class="mx-2 min-w-0 flex-1 truncate text-sm font-medium" :title="book.title">{{ book.title }}</p>

            <div class="flex items-center gap-1">
                <Button variant="ghost" size="icon" :disabled="currentPage <= 1" title="Página anterior" @click="goToPage(currentPage - 1)">
                    <ChevronLeft class="size-4" />
                </Button>
                <div class="flex items-center gap-1 text-sm text-muted-foreground">
                    <input
                        v-model="pageInput"
                        class="w-12 rounded border border-input bg-background px-1.5 py-1 text-center text-sm"
                        inputmode="numeric"
                        @keydown.enter="submitPageInput"
                        @blur="submitPageInput"
                    />
                    <span>/ {{ totalPages }}</span>
                </div>
                <Button variant="ghost" size="icon" :disabled="currentPage >= totalPages" title="Próxima página" @click="goToPage(currentPage + 1)">
                    <ChevronRight class="size-4" />
                </Button>
            </div>

            <div class="mx-1 flex items-center gap-1">
                <Button variant="ghost" size="icon" title="Reduzir zoom" @click="zoomOut"><ZoomOut class="size-4" /></Button>
                <span class="w-10 text-center text-xs text-muted-foreground">{{ Math.round(scale * 100) }}%</span>
                <Button variant="ghost" size="icon" title="Aumentar zoom" @click="zoomIn"><ZoomIn class="size-4" /></Button>
            </div>

            <Button variant="ghost" size="icon" title="Buscar no livro" @click="searchOpen = !searchOpen"><Search class="size-4" /></Button>
        </header>

        <div class="relative flex min-h-0 flex-1">
            <!-- Painel de busca -->
            <aside v-if="searchOpen" class="flex w-72 shrink-0 flex-col border-r bg-background">
                <div class="flex items-center gap-2 border-b p-3">
                    <input
                        v-model="searchQuery"
                        placeholder="Buscar no livro…"
                        class="flex-1 rounded-md border border-input bg-background px-2.5 py-1.5 text-sm"
                        @keydown.enter="runSearch"
                    />
                    <Button size="sm" :disabled="searching" @click="runSearch">
                        <Loader2 v-if="searching" class="size-4 animate-spin" />
                        <Search v-else class="size-4" />
                    </Button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto p-2">
                    <p v-if="!searchResults.length" class="p-2 text-xs text-muted-foreground">
                        {{ searchQuery ? 'Nenhum resultado.' : 'Digite ao menos 2 letras.' }}
                    </p>
                    <button
                        v-for="result in searchResults"
                        :key="result.page"
                        class="mb-1 w-full rounded-md border border-transparent p-2 text-left text-xs hover:border-input hover:bg-muted"
                        @click="goToPage(result.page)"
                    >
                        <span class="font-medium text-foreground">Página {{ result.page }}</span>
                        <span class="mt-0.5 block text-muted-foreground">{{ result.snippet }}</span>
                    </button>
                </div>
            </aside>

            <!-- Área de leitura -->
            <main class="min-h-0 flex-1 overflow-auto p-6">
                <div v-if="pdf.loading.value" class="flex h-full items-center justify-center text-muted-foreground">
                    <Loader2 class="mr-2 size-5 animate-spin" /> Carregando PDF…
                </div>
                <div v-else-if="pdf.error.value" class="flex h-full items-center justify-center text-sm text-destructive">
                    {{ pdf.error.value }}
                </div>
                <div
                    v-else-if="renderError"
                    class="mx-auto max-w-md rounded-md border border-destructive/40 bg-destructive/10 p-4 text-center text-sm text-destructive"
                >
                    Erro ao renderizar a página: {{ renderError }}
                </div>

                <div v-show="!pdf.loading.value && !pdf.error.value" class="mx-auto w-fit">
                    <div ref="pageWrapperEl" class="relative shadow-lg ring-1 ring-black/10">
                        <!-- Subárvore do PDF.js (canvas + camada de texto): mantida SEM
                             irmãos v-if/v-for. O PDF.js injeta nós aqui; se o Vue
                             precisar mover nós dinâmicos ao redor, o patch quebra
                             ("Cannot read properties of null (reading 'parentNode')"). -->
                        <canvas ref="canvasEl" class="block"></canvas>
                        <div ref="textLayerEl" class="textLayer" @mousedown="clearPopovers" @mouseup="onPointerUp"></div>

                        <!-- Camada de overlays/popovers gerida pelo Vue, sobreposta e
                             isolada da subárvore do PDF.js. -->
                        <div class="pointer-events-none absolute inset-0">
                            <template v-for="ph in pageHighlights" :key="ph.highlight.id">
                                <div
                                    v-for="(box, index) in ph.boxes"
                                    :key="index"
                                    class="absolute rounded-sm"
                                    :class="ph.carded ? 'bg-emerald-400/30' : 'bg-yellow-300/40'"
                                    :style="{ left: `${box.left}px`, top: `${box.top}px`, width: `${box.width}px`, height: `${box.height}px` }"
                                ></div>
                            </template>

                            <!-- Popover da seleção -->
                            <div
                                v-if="selection"
                                class="pointer-events-auto absolute z-30 flex items-center gap-1 rounded-md border bg-popover p-1 shadow-md"
                                :style="{ left: `${selection.box.left}px`, top: `${selection.box.top}px` }"
                            >
                                <Button size="sm" class="h-7" @click="createCardFromSelection"><Plus class="size-3.5" /> Criar cartão</Button>
                                <Button size="sm" variant="ghost" class="h-7" @click="markSelection">Só marcar</Button>
                            </div>

                            <!-- Popover de um destaque existente -->
                            <div
                                v-if="activeHighlight"
                                class="pointer-events-auto absolute z-30 flex items-center gap-1 rounded-md border bg-popover p-1 shadow-md"
                                :style="{ left: `${activeHighlight.left}px`, top: `${activeHighlight.top}px` }"
                            >
                                <Button size="sm" class="h-7" @click="openCardDialog(activeHighlight.highlight, null)">
                                    <Plus class="size-3.5" /> Criar cartão
                                </Button>
                                <Button size="sm" variant="ghost" class="h-7 text-destructive" @click="removeHighlight(activeHighlight.highlight)">
                                    <Trash2 class="size-3.5" />
                                </Button>
                                <Button size="sm" variant="ghost" class="h-7" @click="clearPopovers"><X class="size-3.5" /></Button>
                            </div>
                        </div>
                    </div>

                    <p v-if="actionError" class="mt-2 text-center text-xs text-destructive">{{ actionError }}</p>
                </div>
            </main>
        </div>

        <CardFormDialog v-model:open="dialogOpen" :highlight="dialogHighlight" :preset-front="dialogPreset" preserve-state />
    </div>
</template>

<style>
/* Camada de texto do PDF.js (adaptada do pdf_viewer.css, sem aninhamento).
   Fica sobre o canvas e é o que permite selecionar o texto. */
.pdf-reader .textLayer {
    position: absolute;
    inset: 0;
    overflow: clip;
    opacity: 1;
    line-height: 1;
    text-align: initial;
    -webkit-text-size-adjust: none;
    text-size-adjust: none;
    forced-color-adjust: none;
    transform-origin: 0 0;
    z-index: 2;
    --min-font-size: 1;
    --text-scale-factor: calc(var(--total-scale-factor) * var(--min-font-size));
    --min-font-size-inv: calc(1 / var(--min-font-size));
}

.pdf-reader .textLayer span,
.pdf-reader .textLayer br {
    color: transparent;
    position: absolute;
    white-space: pre;
    cursor: text;
    transform-origin: 0 0;
    -webkit-user-select: text;
    user-select: text;
}

.pdf-reader .textLayer > :not(.markedContent),
.pdf-reader .textLayer .markedContent span:not(.markedContent) {
    z-index: 1;
    --font-height: 0;
    --scale-x: 1;
    --rotate: 0deg;
    font-size: calc(var(--text-scale-factor) * var(--font-height));
    transform: rotate(var(--rotate)) scaleX(var(--scale-x)) scale(var(--min-font-size-inv));
}

.pdf-reader .textLayer .markedContent {
    display: contents;
}

.pdf-reader .textLayer ::selection {
    background: rgba(59, 130, 246, 0.35);
    color: transparent;
}
</style>
