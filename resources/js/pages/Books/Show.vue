<script setup lang="ts">
import CardFormDialog from '@/components/CardFormDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, HighlightData } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Plus, StickyNote } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    book: { id: number; title: string; author: string | null };
    highlights: HighlightData[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Livros', href: '/livros' },
    { title: props.book.title, href: `/livros/${props.book.id}` },
]);

const dialogOpen = ref(false);
const selectedHighlight = ref<{ id: number; content: string } | null>(null);

function createCard(highlight: HighlightData) {
    selectedHighlight.value = { id: highlight.id, content: highlight.content };
    dialogOpen.value = true;
}

function meta(highlight: HighlightData): string {
    const parts: string[] = [];
    if (highlight.page) parts.push(`página ${highlight.page}`);
    if (highlight.location) parts.push(`posição ${highlight.location}`);
    if (highlight.highlighted_at) parts.push(new Date(`${highlight.highlighted_at}T00:00:00`).toLocaleDateString('pt-BR'));
    return parts.join(' · ');
}
</script>

<template>
    <Head :title="book.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">{{ book.title }}</h1>
                <p class="text-sm text-muted-foreground">{{ book.author ?? 'Autor desconhecido' }} · {{ highlights.length }} destaque(s)</p>
            </div>

            <Card v-for="highlight in highlights" :key="highlight.id">
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
        </div>

        <CardFormDialog v-model:open="dialogOpen" :highlight="selectedHighlight" />
    </AppLayout>
</template>
