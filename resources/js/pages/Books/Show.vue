<script setup lang="ts">
import BarChart from '@/components/BarChart.vue';
import CardFormDialog from '@/components/CardFormDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { postJson } from '@/lib/api';
import type { BreadcrumbItem, HighlightData } from '@/types';
import { Head } from '@inertiajs/vue3';
import { CalendarRange, Check, GraduationCap, Layers, MapPin, Plus, Sparkles, StickyNote, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface WordCount {
    word: string;
    count: number;
    has_card: boolean;
}

interface BookStats {
    highlights: number;
    notes: number;
    cards: number;
    unique_words: number;
    first_at: string | null;
    last_at: string | null;
}

const props = defineProps<{
    book: { id: number; title: string; author: string | null; cover_url: string | null };
    highlights: HighlightData[];
    stats: BookStats;
    words: WordCount[];
    distribution: { start: number; end: number; count: number }[];
    timeline: { date: string; count: number }[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Livros', href: '/livros' },
    { title: props.book.title, href: `/livros/${props.book.id}` },
]);

const dialogOpen = ref(false);
const selectedHighlight = ref<{ id: number; content: string } | null>(null);
const presetFront = ref<string | null>(null);
const filter = ref('');

const filteredHighlights = computed(() => {
    const term = filter.value.trim().toLowerCase();
    if (!term) return props.highlights;
    return props.highlights.filter((h) => h.content.toLowerCase().includes(term));
});

// Escala o tamanho da fonte de cada palavra pela frequência (mapa de vocabulário).
const maxCount = computed(() => props.words.reduce((max, w) => Math.max(max, w.count), 1));
function wordStyle(count: number) {
    const ratio = count / maxCount.value; // 0..1
    return { fontSize: `${0.8 + ratio * 0.95}rem`, opacity: `${0.55 + ratio * 0.45}` };
}

function toggleWord(word: string) {
    filter.value = filter.value.trim().toLowerCase() === word ? '' : word;
}

// Palavras a estudar: as frequentes que ainda não têm cartão. O artigo
// (der/die/das) é buscado sob demanda ao focar/passar o mouse numa palavra
// (cacheado no servidor), evitando dezenas de requisições no carregamento.
const articles = reactive<Record<string, string | null>>({});
const wordsToStudy = computed(() => props.words.filter((w) => !w.has_card).slice(0, 15));

async function fetchArticle(word: string) {
    if (word in articles) return; // já buscado (ou em cache local)
    articles[word] = null;
    try {
        const { article } = await postJson<{ article: string | null }>(route('article.detect'), { word });
        articles[word] = article;
    } catch {
        // Detecção é opcional: mantém null.
    }
}

function capitalize(word: string): string {
    return word.charAt(0).toUpperCase() + word.slice(1);
}

function studyCardFront(word: string): string {
    const article = articles[word];
    // Com artigo é substantivo → capitaliza e prefixa (das Fernweh); senão, como está.
    return article ? `${article} ${capitalize(word)}` : word;
}

function createCard(highlight: HighlightData) {
    selectedHighlight.value = { id: highlight.id, content: highlight.content };
    presetFront.value = null;
    dialogOpen.value = true;
}

function createCardFromWord(word: string) {
    const source = props.highlights.find((h) => h.type === 'highlight' && h.content.toLowerCase().includes(word));
    selectedHighlight.value = source ? { id: source.id, content: source.content } : null;
    presetFront.value = studyCardFront(word);
    dialogOpen.value = true;
}

function formatDate(date: string | null): string | null {
    if (!date) return null;
    return new Date(`${date}T00:00:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
}

const period = computed(() => {
    const first = formatDate(props.stats.first_at);
    const last = formatDate(props.stats.last_at);
    if (!first && !last) return '—';
    if (first === last) return first;
    return `${first} – ${last}`;
});

const distributionBars = computed(() => props.distribution.map((d) => ({ label: `posição ${d.start}–${d.end}`, value: d.count })));
const timelineBars = computed(() => props.timeline.map((t) => ({ label: formatDate(t.date) ?? t.date, value: t.count })));

function meta(highlight: HighlightData): string {
    const parts: string[] = [];
    if (highlight.page) parts.push(`página ${highlight.page}`);
    if (highlight.location) parts.push(`posição ${highlight.location}`);
    if (highlight.highlighted_at) parts.push(formatDate(highlight.highlighted_at)!);
    return parts.join(' · ');
}
</script>

<template>
    <Head :title="book.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4">
            <!-- Cabeçalho com capa -->
            <div class="flex gap-4">
                <img
                    v-if="book.cover_url"
                    :src="book.cover_url"
                    :alt="`Capa de ${book.title}`"
                    class="h-28 w-[74px] shrink-0 rounded-md object-cover shadow-md ring-1 ring-black/5 dark:ring-white/10"
                />
                <div class="min-w-0 self-center">
                    <h1 class="text-xl font-semibold leading-tight">{{ book.title }}</h1>
                    <p class="text-sm text-muted-foreground">{{ book.author ?? 'Autor desconhecido' }}</p>
                </div>
            </div>

            <!-- Estatísticas -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border bg-card p-3">
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground"><Sparkles class="size-3.5" /> Destaques</div>
                    <p class="mt-1 text-xl font-semibold">{{ stats.highlights }}</p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground"><Layers class="size-3.5" /> Cartões</div>
                    <p class="mt-1 text-xl font-semibold">{{ stats.cards }}</p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground"><StickyNote class="size-3.5" /> Palavras únicas</div>
                    <p class="mt-1 text-xl font-semibold">{{ stats.unique_words }}</p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground"><CalendarRange class="size-3.5" /> Período</div>
                    <p class="mt-1 text-sm font-medium leading-tight">{{ period }}</p>
                </div>
            </div>

            <!-- Gráficos: distribuição por posição e leitura ao longo do tempo -->
            <div v-if="distributionBars.length || timelineBars.length" class="grid gap-4 sm:grid-cols-2">
                <div v-if="distributionBars.length" class="rounded-lg border bg-card p-4">
                    <h2 class="mb-3 flex items-center gap-1.5 text-sm font-semibold">
                        <MapPin class="size-4 text-muted-foreground" /> Destaques pelo livro
                    </h2>
                    <BarChart
                        :bars="distributionBars"
                        :start-label="`posição ${distribution[0].start}`"
                        :end-label="`${distribution[distribution.length - 1].end}`"
                    />
                </div>
                <div v-if="timelineBars.length" class="rounded-lg border bg-card p-4">
                    <h2 class="mb-3 flex items-center gap-1.5 text-sm font-semibold">
                        <CalendarRange class="size-4 text-muted-foreground" /> Leitura ao longo do tempo
                    </h2>
                    <BarChart
                        :bars="timelineBars"
                        :start-label="formatDate(timeline[0].date) ?? ''"
                        :end-label="formatDate(timeline[timeline.length - 1].date) ?? ''"
                    />
                </div>
            </div>

            <!-- Palavras a estudar (frequentes ainda sem cartão) -->
            <div v-if="wordsToStudy.length" class="rounded-lg border bg-card p-4">
                <h2 class="mb-1 flex items-center gap-1.5 text-sm font-semibold">
                    <GraduationCap class="size-4 text-muted-foreground" /> Palavras a estudar
                </h2>
                <p class="mb-3 text-xs text-muted-foreground">Frequentes neste livro e ainda sem cartão.</p>
                <div class="flex flex-wrap gap-2" lang="de">
                    <button
                        v-for="w in wordsToStudy"
                        :key="w.word"
                        type="button"
                        class="group inline-flex items-center gap-1.5 rounded-full border bg-background py-1 pl-3 pr-2 text-sm transition-colors hover:border-primary/50"
                        :title="`Criar cartão · ${w.count}×`"
                        @mouseenter="fetchArticle(w.word)"
                        @focus="fetchArticle(w.word)"
                        @click="createCardFromWord(w.word)"
                    >
                        <span class="font-medium">
                            <span v-if="articles[w.word]" class="text-muted-foreground">{{ articles[w.word] }} </span>{{ w.word }}
                        </span>
                        <span class="text-xs text-muted-foreground">{{ w.count }}×</span>
                        <Plus
                            class="size-4 rounded-full bg-muted p-0.5 text-muted-foreground group-hover:bg-primary group-hover:text-primary-foreground"
                        />
                    </button>
                </div>
            </div>

            <!-- Mapa de palavras frequentes -->
            <div v-if="words.length" class="rounded-lg border bg-card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold">Palavras frequentes</h2>
                    <span class="text-xs text-muted-foreground">clique para filtrar</span>
                </div>
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1.5" lang="de">
                    <button
                        v-for="w in words"
                        :key="w.word"
                        type="button"
                        :style="wordStyle(w.count)"
                        class="inline-flex items-center gap-0.5 font-medium leading-none transition-colors hover:text-primary"
                        :class="filter.trim().toLowerCase() === w.word ? 'text-primary underline underline-offset-4' : 'text-foreground'"
                        :title="`${w.count}×${w.has_card ? ' · já tem cartão' : ''}`"
                        @click="toggleWord(w.word)"
                    >
                        {{ w.word }}<Check v-if="w.has_card" class="size-3 text-primary" />
                    </button>
                </div>
            </div>

            <!-- Filtro + lista de destaques -->
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <input
                        v-model="filter"
                        type="search"
                        placeholder="Filtrar destaques…"
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                    />
                    <button
                        v-if="filter"
                        type="button"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        @click="filter = ''"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <span class="shrink-0 text-sm text-muted-foreground">{{ filteredHighlights.length }} de {{ highlights.length }}</span>
            </div>

            <Card v-for="highlight in filteredHighlights" :key="highlight.id">
                <CardContent class="space-y-3 pt-6">
                    <p class="whitespace-pre-line leading-relaxed" lang="de">
                        <StickyNote v-if="highlight.type === 'note'" class="mr-1 inline size-4 align-text-top text-amber-500" />{{
                            highlight.content
                        }}
                    </p>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-xs text-muted-foreground">{{ meta(highlight) }}</span>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span
                                v-for="card in highlight.cards"
                                :key="card.id"
                                class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                                :title="`Cartão: ${card.front}`"
                            >
                                {{ card.front }}
                            </span>
                            <Button size="sm" variant="outline" @click="createCard(highlight)"><Plus class="size-4" /> Criar cartão</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <p v-if="!filteredHighlights.length" class="py-8 text-center text-sm text-muted-foreground">Nenhum destaque para “{{ filter }}”.</p>
        </div>

        <CardFormDialog v-model:open="dialogOpen" :highlight="selectedHighlight" :preset-front="presetFront" />
    </AppLayout>
</template>
