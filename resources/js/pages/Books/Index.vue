<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { postJson } from '@/lib/api';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, Layers, Sparkles, Upload } from 'lucide-vue-next';
import { onMounted, reactive } from 'vue';

interface BookSummary {
    id: number;
    title: string;
    author: string | null;
    cover_url: string | null;
    cover_pending: boolean;
    highlights_count: number;
    cards_count: number;
    last_highlight_at: string | null;
}

const props = defineProps<{ books: BookSummary[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Livros', href: '/livros' }];

// Estado local das capas: buscamos sob demanda as que ainda não foram tentadas,
// sem bloquear o carregamento da página.
const covers = reactive<Record<number, { url: string | null; pending: boolean; failed: boolean }>>(
    Object.fromEntries(props.books.map((book) => [book.id, { url: book.cover_url, pending: book.cover_pending, failed: false }])),
);

onMounted(async () => {
    for (const book of props.books) {
        const state = covers[book.id];
        if (!state.pending) continue;
        try {
            const { cover_url } = await postJson<{ cover_url: string | null }>(route('books.cover', book.id), {});
            state.url = cover_url;
        } catch {
            // Falha silenciosa — o livro fica com a capa de fallback gerada.
        } finally {
            state.pending = false;
        }
    }
});

// Valor estável derivado do título para variar levemente a "capa de pano".
function hue(title: string): number {
    let h = 0;
    for (const ch of title) h = (h * 31 + ch.charCodeAt(0)) % 360;
    return h;
}

// Capa-fallback em tom neutro e quente (cor de pano/e-ink), não mais colorida —
// só a claridade varia por título para os livros não ficarem idênticos.
function fallbackStyle(title: string) {
    const l = 26 + (hue(title) % 14);
    return { background: `linear-gradient(160deg, hsl(32 10% ${l + 5}%), hsl(28 8% ${l - 4}%))` };
}

function formatDate(date: string | null): string | null {
    if (!date) return null;
    return new Date(`${date}T00:00:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head title="Livros" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div v-if="!books.length" class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                <BookOpen class="size-10 text-muted-foreground" />
                <p class="font-medium">Nenhum livro ainda</p>
                <p class="max-w-sm text-sm text-muted-foreground">Importe o My Clippings.txt do seu Kindle para ver seus livros e destaques aqui.</p>
                <Button as-child>
                    <Link :href="route('import.create')"><Upload class="size-4" /> Importar destaques</Link>
                </Button>
            </div>

            <template v-else>
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Biblioteca</h1>
                        <p class="text-sm text-muted-foreground">{{ books.length }} livro(s) · ordenados pelos destaques mais recentes</p>
                    </div>
                    <Button variant="outline" as-child>
                        <Link :href="route('import.create')"><Upload class="size-4" /> Importar</Link>
                    </Button>
                </div>

                <div class="grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
                    <Link v-for="book in books" :key="book.id" :href="route('books.show', book.id)" class="group flex flex-col gap-2.5">
                        <div
                            class="relative aspect-[2/3] overflow-hidden rounded-md bg-muted shadow-md ring-1 ring-black/5 transition-all duration-200 group-hover:-translate-y-1 group-hover:shadow-xl dark:ring-white/10"
                        >
                            <!-- Lombada: gradiente sutil na borda esquerda para dar volume de livro -->
                            <div class="pointer-events-none absolute inset-y-0 left-0 z-10 w-3 bg-gradient-to-r from-black/25 to-transparent"></div>

                            <!-- Capa real -->
                            <img
                                v-if="covers[book.id].url"
                                :src="covers[book.id].url!"
                                :alt="`Capa de ${book.title}`"
                                loading="lazy"
                                class="size-full object-cover"
                                @error="covers[book.id].url = null"
                            />

                            <!-- Skeleton enquanto busca -->
                            <div v-else-if="covers[book.id].pending" class="size-full animate-pulse bg-muted-foreground/10"></div>

                            <!-- Fallback gerado quando não há capa -->
                            <div v-else class="flex size-full flex-col justify-between p-3 text-white" :style="fallbackStyle(book.title)">
                                <BookOpen class="size-5 opacity-70" />
                                <div>
                                    <p class="line-clamp-4 font-serif text-sm font-semibold leading-snug drop-shadow-sm">{{ book.title }}</p>
                                    <p v-if="book.author" class="mt-1 line-clamp-1 text-[11px] opacity-80">{{ book.author }}</p>
                                </div>
                            </div>

                            <!-- Contadores sobre a capa -->
                            <div
                                class="absolute inset-x-0 bottom-0 z-10 flex items-center gap-2 bg-gradient-to-t from-black/60 to-transparent p-2 text-[11px] font-medium text-white"
                            >
                                <span class="inline-flex items-center gap-1"><Sparkles class="size-3" /> {{ book.highlights_count }}</span>
                                <span v-if="book.cards_count" class="inline-flex items-center gap-1"
                                    ><Layers class="size-3" /> {{ book.cards_count }}</span
                                >
                            </div>
                        </div>

                        <div class="min-w-0">
                            <p class="line-clamp-2 font-serif text-sm font-medium leading-snug group-hover:text-primary">{{ book.title }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ book.author ?? 'Autor desconhecido' }}</p>
                            <p v-if="formatDate(book.last_highlight_at)" class="mt-0.5 text-[11px] text-muted-foreground/70">
                                {{ formatDate(book.last_highlight_at) }}
                            </p>
                        </div>
                    </Link>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
