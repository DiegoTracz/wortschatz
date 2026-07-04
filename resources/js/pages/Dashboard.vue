<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, Flame, GraduationCap, Layers, Sparkles, Upload } from 'lucide-vue-next';
import { computed } from 'vue';

interface Stats {
    due: number;
    new: number;
    cards: number;
    books: number;
    highlights: number;
    reviewsToday: number;
    streak: number;
    lastWeek: { label: string; total: number }[];
}

const props = defineProps<{ stats: Stats }>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const maxWeek = computed(() => Math.max(1, ...props.stats.lastWeek.map((day) => day.total)));
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Chamada para estudar -->
            <Card class="border-primary/30 bg-primary/5">
                <CardContent class="flex flex-wrap items-center justify-between gap-4 pt-6">
                    <div>
                        <p class="text-lg font-semibold">
                            {{ stats.due ? `Você tem ${stats.due} cartão(ões) para revisar` : 'Tudo revisado por hoje! 🎉' }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                stats.due
                                    ? 'A repetição espaçada funciona melhor quando você revisa todo dia.'
                                    : 'Volte amanhã ou importe novos destaques do Kindle.'
                            }}
                        </p>
                    </div>
                    <Button v-if="stats.due" size="lg" as-child>
                        <Link :href="route('study.index')"><GraduationCap class="size-5" /> Estudar agora</Link>
                    </Button>
                    <Button v-else variant="outline" as-child>
                        <Link :href="route('import.create')"><Upload class="size-4" /> Importar destaques</Link>
                    </Button>
                </CardContent>
            </Card>

            <!-- Estatísticas -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription class="flex items-center gap-1.5"><Flame class="size-4 text-orange-500" /> Sequência</CardDescription>
                        <CardTitle class="text-3xl">{{ stats.streak }} dia(s)</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-muted-foreground">{{ stats.reviewsToday }} revisão(ões) hoje</CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription class="flex items-center gap-1.5"><Layers class="size-4 text-primary" /> Cartões</CardDescription>
                        <CardTitle class="text-3xl">{{ stats.cards }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-muted-foreground">{{ stats.new }} novo(s) para aprender</CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription class="flex items-center gap-1.5"><BookOpen class="size-4 text-emerald-600" /> Livros</CardDescription>
                        <CardTitle class="text-3xl">{{ stats.books }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-muted-foreground">{{ stats.highlights }} destaque(s) importado(s)</CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription class="flex items-center gap-1.5"><Sparkles class="size-4 text-violet-500" /> Vencidos</CardDescription>
                        <CardTitle class="text-3xl">{{ stats.due }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-muted-foreground">aguardando revisão</CardContent>
                </Card>
            </div>

            <!-- Últimos 7 dias -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Revisões nos últimos 7 dias</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex h-32 items-end gap-2">
                        <div v-for="(day, index) in stats.lastWeek" :key="index" class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                            <span class="text-xs text-muted-foreground">{{ day.total || '' }}</span>
                            <div
                                class="w-full rounded-t transition-all"
                                :class="day.total ? 'bg-primary/80' : 'bg-muted'"
                                :style="{ height: day.total ? `${(day.total / maxWeek) * 75}%` : '4px' }"
                            />
                            <span class="text-xs capitalize text-muted-foreground">{{ day.label }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
