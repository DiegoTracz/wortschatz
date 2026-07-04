<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { postJson } from '@/lib/api';
import { splitArticle } from '@/lib/german';
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
const front = computed(() => (current.value ? splitArticle(current.value.front) : null));
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

<style scoped>
.reveal-enter-active {
    transition:
        opacity 0.22s ease,
        transform 0.22s ease;
}

.reveal-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

.reveal-leave-active {
    transition: opacity 0.1s ease;
}

.reveal-leave-to {
    opacity: 0;
}

.card-enter-active,
.card-leave-active {
    transition:
        opacity 0.16s ease,
        transform 0.16s ease;
}

.card-enter-from {
    opacity: 0;
    transform: translateX(14px);
}

.card-leave-to {
    opacity: 0;
    transform: translateX(-14px);
}

@media (prefers-reduced-motion: reduce) {
    .reveal-enter-active,
    .reveal-leave-active,
    .card-enter-active,
    .card-leave-active {
        transition: none;
    }
}
</style>

<template>
    <Head title="Estudar" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
            <!-- Sessão concluída / nada para revisar -->
            <div v-if="!current" class="flex flex-1 flex-col items-center justify-center gap-4 text-center">
                <template v-if="done > 0">
                    <div class="flex size-16 items-center justify-center rounded-full bg-primary/10 ring-4 ring-[#f0b429]/25">
                        <CheckCircle2 class="size-8 text-primary" />
                    </div>
                    <div class="space-y-1">
                        <h2 class="text-2xl font-semibold tracking-tight">Sessão concluída</h2>
                        <p class="text-sm text-muted-foreground">{{ done }} {{ done === 1 ? 'cartão revisado' : 'cartões revisados' }} hoje.</p>
                    </div>
                    <p class="text-sm italic text-muted-foreground" lang="de">Bis morgen.</p>
                </template>
                <template v-else>
                    <GraduationCap class="size-12 text-muted-foreground" />
                    <div class="space-y-1">
                        <h2 class="text-2xl font-semibold tracking-tight">Nada para revisar agora</h2>
                        <p class="max-w-sm text-sm text-muted-foreground">
                            Nenhum cartão vencido. Importe novos destaques ou crie cartões para continuar aprendendo.
                        </p>
                    </div>
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
                <Transition name="card" mode="out-in">
                    <Card
                        :key="current.id"
                        class="flex flex-1 select-none"
                        :class="{ 'cursor-pointer': !revealed }"
                        @click="!revealed && (revealed = true)"
                    >
                        <CardContent class="flex flex-1 flex-col items-center justify-center gap-6 py-12 text-center">
                            <span v-if="current.book" class="text-xs text-muted-foreground">{{ current.book }}</span>

                            <p v-if="front" class="text-4xl font-semibold tracking-tight" lang="de">
                                <template v-if="front.article"
                                    ><span :class="front.color">{{ front.article }}</span> </template
                                >{{ front.rest }}
                            </p>

                            <Transition name="reveal" mode="out-in">
                                <div v-if="revealed" class="flex flex-col items-center gap-6">
                                    <div class="h-px w-24 bg-border" />
                                    <p class="text-xl">{{ current.back }}</p>
                                    <p v-if="current.context" class="max-w-md text-sm italic leading-relaxed text-muted-foreground" lang="de">
                                        „{{ current.context }}“
                                    </p>
                                </div>
                                <p v-else class="text-sm text-muted-foreground">
                                    Clique ou pressione <kbd class="rounded border bg-muted px-1.5 py-0.5 text-[11px]">espaço</kbd> para revelar
                                </p>
                            </Transition>
                        </CardContent>
                    </Card>
                </Transition>

                <p v-if="error" class="text-center text-sm text-destructive">{{ error }}</p>

                <!-- Botões de resposta -->
                <Transition name="reveal">
                    <div v-if="revealed" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <Button
                            v-for="rating in ratings"
                            :key="rating.value"
                            variant="outline"
                            class="h-auto flex-col gap-1 py-3 transition-transform active:scale-[0.97]"
                            :class="rating.classes"
                            :disabled="submitting"
                            @click="rate(rating.value)"
                        >
                            <span class="flex items-center gap-1.5 font-semibold">
                                <kbd class="hidden rounded border border-current px-1 text-[10px] font-normal opacity-60 sm:inline">{{
                                    rating.key
                                }}</kbd>
                                {{ rating.label }}
                            </span>
                            <span class="text-xs opacity-70">{{ previewLabel(current.previews[rating.value]) }}</span>
                        </Button>
                    </div>
                </Transition>
            </template>
        </div>
    </AppLayout>
</template>
