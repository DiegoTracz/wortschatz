<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { postJson } from '@/lib/api';
import type { BreadcrumbItem, StudyCard } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, GraduationCap } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{ cards: StudyCard[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Estudar', href: '/estudar' }];

const queue = ref<StudyCard[]>([...props.cards]);
const sessionTotal = ref(props.cards.length);
const done = ref(0);
const revealed = ref(false);
const submitting = ref(false);
const error = ref<string | null>(null);

const current = computed(() => queue.value[0] ?? null);
const progress = computed(() => (sessionTotal.value ? Math.round((done.value / sessionTotal.value) * 100) : 0));

const ratings = [
    { value: 1, label: 'Errei', key: '1', classes: 'border-red-500/40 text-red-600 hover:bg-red-500/10 dark:text-red-400' },
    { value: 2, label: 'Difícil', key: '2', classes: 'border-amber-500/40 text-amber-600 hover:bg-amber-500/10 dark:text-amber-400' },
    { value: 3, label: 'Bom', key: '3', classes: 'border-green-500/40 text-green-600 hover:bg-green-500/10 dark:text-green-400' },
    { value: 4, label: 'Fácil', key: '4', classes: 'border-sky-500/40 text-sky-600 hover:bg-sky-500/10 dark:text-sky-400' },
];

function previewLabel(days: number): string {
    if (days === 0) return 'agora';
    if (days === 1) return '1 dia';
    if (days < 30) return `${days} dias`;
    if (days < 365) return `${(days / 30).toFixed(1).replace('.0', '')} meses`;
    return `${(days / 365).toFixed(1).replace('.0', '')} anos`;
}

async function rate(rating: number) {
    if (!current.value || submitting.value) return;

    submitting.value = true;
    error.value = null;

    try {
        const response = await postJson<{ card: StudyCard; remaining: number }>(route('study.review', current.value.id), { rating });

        queue.value.shift();
        revealed.value = false;

        if (rating === 1) {
            // Errou: o cartão volta para o fim da fila da sessão.
            queue.value.push(response.card);
        } else {
            done.value++;
        }
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Erro ao salvar a revisão.';
    } finally {
        submitting.value = false;
    }
}

function onKeydown(event: KeyboardEvent) {
    if (!current.value || event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) return;

    if (!revealed.value && (event.code === 'Space' || event.code === 'Enter')) {
        event.preventDefault();
        revealed.value = true;
        return;
    }

    if (revealed.value) {
        const rating = ratings.find((r) => r.key === event.key);
        if (rating) {
            event.preventDefault();
            rate(rating.value);
        }
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Head title="Estudar" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
            <!-- Sessão concluída / nada para revisar -->
            <div v-if="!current" class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                <template v-if="done > 0">
                    <CheckCircle2 class="size-12 text-green-600" />
                    <h2 class="text-xl font-semibold">Sessão concluída!</h2>
                    <p class="text-sm text-muted-foreground">Você revisou {{ done }} cartão(ões) hoje. Bis morgen! 🇩🇪</p>
                </template>
                <template v-else>
                    <GraduationCap class="size-12 text-muted-foreground" />
                    <h2 class="text-xl font-semibold">Nada para revisar agora</h2>
                    <p class="max-w-sm text-sm text-muted-foreground">
                        Nenhum cartão vencido. Importe novos destaques ou crie cartões para continuar aprendendo.
                    </p>
                </template>
                <Button as-child variant="outline"><Link :href="route('dashboard')">Voltar ao dashboard</Link></Button>
            </div>

            <template v-else>
                <!-- Progresso -->
                <div class="space-y-1">
                    <div class="flex justify-between text-xs text-muted-foreground">
                        <span>{{ done }} de {{ sessionTotal }} revisados</span>
                        <span>{{ queue.length }} na fila</span>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${progress}%` }" />
                    </div>
                </div>

                <!-- Cartão -->
                <Card class="flex flex-1 cursor-pointer select-none" @click="!revealed && (revealed = true)">
                    <CardContent class="flex flex-1 flex-col items-center justify-center gap-6 py-12 text-center">
                        <span v-if="current.book" class="text-xs text-muted-foreground">{{ current.book }}</span>

                        <p class="text-3xl font-semibold" lang="de">{{ current.front }}</p>

                        <template v-if="revealed">
                            <div class="h-px w-24 bg-border" />
                            <p class="text-xl">{{ current.back }}</p>
                            <p v-if="current.context" class="max-w-md text-sm italic leading-relaxed text-muted-foreground" lang="de">
                                „{{ current.context }}“
                            </p>
                        </template>
                        <p v-else class="text-sm text-muted-foreground">Clique ou pressione espaço para revelar</p>
                    </CardContent>
                </Card>

                <p v-if="error" class="text-center text-sm text-destructive">{{ error }}</p>

                <!-- Botões de resposta -->
                <div v-if="revealed" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <Button
                        v-for="rating in ratings"
                        :key="rating.value"
                        variant="outline"
                        class="h-auto flex-col gap-0.5 py-3"
                        :class="rating.classes"
                        :disabled="submitting"
                        @click="rate(rating.value)"
                    >
                        <span class="font-semibold">{{ rating.label }}</span>
                        <span class="text-xs opacity-70">{{ previewLabel(current.previews[rating.value]) }}</span>
                    </Button>
                </div>
                <p v-if="revealed" class="text-center text-xs text-muted-foreground">Atalhos: 1 = errei, 2 = difícil, 3 = bom, 4 = fácil</p>
            </template>
        </div>
    </AppLayout>
</template>
