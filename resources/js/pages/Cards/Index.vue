<script setup lang="ts">
import CardFormDialog from '@/components/CardFormDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { splitArticle } from '@/lib/german';
import type { BreadcrumbItem, CardData } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Layers, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    cards: { data: CardData[]; links: PaginationLink[]; total: number };
    search: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Cartões', href: '/cartoes' }];

const search = ref(props.search);

const applySearch = useDebounceFn((value: string) => {
    router.get(route('cards.index'), value ? { search: value } : {}, { preserveState: true, replace: true });
}, 400);

watch(search, applySearch);

const dialogOpen = ref(false);
const editingCard = ref<CardData | null>(null);

function newCard() {
    editingCard.value = null;
    dialogOpen.value = true;
}

function editCard(card: CardData) {
    editingCard.value = card;
    dialogOpen.value = true;
}

function deleteCard(card: CardData) {
    if (!confirm(`Excluir o cartão "${card.front}"? O histórico de revisões também será apagado.`)) return;
    router.delete(route('cards.destroy', card.id), { preserveScroll: true });
}

function dueLabel(card: CardData): string {
    if (card.is_due) return 'vencido';
    return new Date(`${card.due_at}T00:00:00`).toLocaleDateString('pt-BR');
}
</script>

<template>
    <Head title="Cartões" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <Input v-model="search" placeholder="Buscar cartões..." class="max-w-xs" />
                <Button @click="newCard"><Plus class="size-4" /> Novo cartão</Button>
            </div>

            <div v-if="!cards.data.length" class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                <Layers class="size-10 text-muted-foreground" />
                <p class="font-medium">{{ search ? 'Nada encontrado' : 'Nenhum cartão ainda' }}</p>
                <p class="max-w-sm text-sm text-muted-foreground">
                    {{ search ? 'Tente outra busca.' : 'Crie cartões a partir dos destaques dos seus livros ou manualmente.' }}
                </p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left text-muted-foreground">
                            <th class="px-4 py-3 font-medium">Frente (alemão)</th>
                            <th class="px-4 py-3 font-medium">Verso</th>
                            <th class="px-4 py-3 font-medium">Próxima revisão</th>
                            <th class="px-4 py-3 font-medium">Intervalo</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="card in cards.data" :key="card.id" class="border-b last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium" lang="de">
                                <template v-if="splitArticle(card.front).article">
                                    <span :class="splitArticle(card.front).color">{{ splitArticle(card.front).article }}</span>
                                    {{ splitArticle(card.front).rest }}
                                </template>
                                <template v-else>{{ card.front }}</template>
                            </td>
                            <td class="max-w-64 truncate px-4 py-3 text-muted-foreground">{{ card.back }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="card.is_due ? 'bg-amber-500/15 text-amber-600 dark:text-amber-500' : 'bg-muted text-muted-foreground'"
                                >
                                    {{ dueLabel(card) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ card.interval_days }} dia(s)</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" class="size-8" @click="editCard(card)"><Pencil class="size-4" /></Button>
                                    <Button variant="ghost" size="icon" class="size-8 text-destructive" @click="deleteCard(card)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="cards.links.length > 3" class="flex flex-wrap justify-center gap-1">
                <template v-for="(link, index) in cards.links" :key="index">
                    <Button v-if="link.url" :variant="link.active ? 'default' : 'outline'" size="sm" as-child>
                        <Link :href="link.url" preserve-scroll><span v-html="link.label" /></Link>
                    </Button>
                </template>
            </div>
        </div>

        <CardFormDialog v-model:open="dialogOpen" :card="editingCard" />
    </AppLayout>
</template>
