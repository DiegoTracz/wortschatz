<script setup lang="ts">
import StatTile from '@/components/StatTile.vue';
import WeeklyReviewsChart from '@/components/WeeklyReviewsChart.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Flame, GraduationCap, Upload } from 'lucide-vue-next';
import { computed } from 'vue';

interface Stats {
    due: number;
    new: number;
    cards: number;
    books: number;
    highlights: number;
    reviewsToday: number;
    streak: number;
    lastWeek: { label: string; date: string; total: number }[];
}

const props = defineProps<{ stats: Stats }>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const plural = (count: number, singular: string, pluralForm: string) => `${count} ${count === 1 ? singular : pluralForm}`;

const heroContext = computed(() => {
    if (props.stats.due === 0 && props.stats.cards === 0) {
        return 'Importe seus destaques do Kindle e crie os primeiros cartões.';
    }

    if (props.stats.due === 0) {
        return `Tudo em dia. ${plural(props.stats.reviewsToday, 'revisão feita', 'revisões feitas')} hoje — volte amanhã.`;
    }

    const parts = [plural(props.stats.new, 'cartão novo', 'cartões novos')];

    if (props.stats.reviewsToday > 0) {
        parts.push(plural(props.stats.reviewsToday, 'revisão feita hoje', 'revisões feitas hoje'));
    }

    return parts.join(' · ');
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-4 p-4">
            <section class="grid gap-4 lg:grid-cols-3">
                <!-- Hero: o número que manda no dia -->
                <Card class="relative overflow-hidden lg:col-span-2">
                    <div class="absolute right-6 top-6 flex gap-1.5" aria-hidden="true">
                        <span class="size-2 rounded-full bg-[#f0b429]" />
                        <span class="size-2 rounded-full bg-[#f0b429]" />
                    </div>
                    <CardContent class="flex h-full flex-col justify-between gap-8 pt-6">
                        <div>
                            <p class="text-sm text-muted-foreground">Para revisar agora</p>
                            <p class="mt-1 text-6xl font-semibold tracking-tight">{{ stats.due }}</p>
                            <p class="mt-3 max-w-md text-sm text-muted-foreground">{{ heroContext }}</p>
                        </div>
                        <div>
                            <Button v-if="stats.due" size="lg" as-child>
                                <Link :href="route('study.index')"><GraduationCap class="size-5" /> Estudar agora</Link>
                            </Button>
                            <Button v-else variant="outline" as-child>
                                <Link :href="route('import.create')"><Upload class="size-4" /> Importar destaques</Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <StatTile
                    label="Sequência de estudo"
                    :value="plural(stats.streak, 'dia', 'dias')"
                    :context="stats.reviewsToday > 0 ? plural(stats.reviewsToday, 'revisão hoje', 'revisões hoje') : 'nenhuma revisão hoje ainda'"
                >
                    <template #icon><Flame class="size-4 text-[#f0b429]" /></template>
                </StatTile>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">Revisões por dia</CardTitle>
                        <CardDescription>Últimos 7 dias</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <WeeklyReviewsChart :days="stats.lastWeek" />
                    </CardContent>
                </Card>

                <!-- Biblioteca -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">Biblioteca</CardTitle>
                        <CardDescription>Seu acervo de estudo</CardDescription>
                    </CardHeader>
                    <CardContent class="flex h-full flex-col justify-between gap-4">
                        <dl class="divide-y">
                            <div class="flex items-baseline justify-between py-2.5">
                                <dt class="text-sm text-muted-foreground">Cartões</dt>
                                <dd class="text-sm">
                                    <span class="font-semibold">{{ stats.cards }}</span>
                                    <span v-if="stats.new" class="text-muted-foreground"> · {{ stats.new }} novos</span>
                                </dd>
                            </div>
                            <div class="flex items-baseline justify-between py-2.5">
                                <dt class="text-sm text-muted-foreground">Destaques</dt>
                                <dd class="text-sm font-semibold">{{ stats.highlights }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between py-2.5">
                                <dt class="text-sm text-muted-foreground">Livros</dt>
                                <dd class="text-sm font-semibold">{{ stats.books }}</dd>
                            </div>
                        </dl>
                        <Button variant="ghost" size="sm" class="justify-start px-0 text-muted-foreground hover:text-foreground" as-child>
                            <Link :href="route('books.index')">Ver livros <ArrowRight class="size-4" /></Link>
                        </Button>
                    </CardContent>
                </Card>
            </section>
        </div>
    </AppLayout>
</template>
