<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookMarked, GraduationCap, Languages, Upload } from 'lucide-vue-next';

const page = usePage<SharedData>();

const features = [
    {
        icon: Upload,
        title: 'Importe do Kindle',
        description: 'Envie o My Clippings.txt e seus destaques viram uma biblioteca organizada por livro, sem duplicatas.',
    },
    {
        icon: Languages,
        title: 'Cartões em um clique',
        description: 'Monte a frente clicando nas palavras da frase; tradução e artigo (der/die/das) são preenchidos automaticamente.',
    },
    {
        icon: GraduationCap,
        title: 'Revisões no momento certo',
        description: 'O FSRS — o algoritmo do Anki moderno — agenda cada cartão para revisar pouco antes de você esquecer.',
    },
];

const articleExamples = [
    { article: 'der', word: 'Hund', color: 'text-sky-600 dark:text-sky-400' },
    { article: 'die', word: 'Zeit', color: 'text-rose-600 dark:text-rose-400' },
    { article: 'das', word: 'Buch', color: 'text-emerald-600 dark:text-emerald-400' },
];
</script>

<template>
    <Head title="Bem-vindo" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-6 py-5">
            <div class="flex items-center gap-2">
                <AppLogoIcon class="size-8" />
                <span class="text-lg font-semibold">Wortschatz</span>
            </div>
            <nav class="flex items-center gap-2">
                <Button v-if="page.props.auth.user" as-child>
                    <Link :href="route('dashboard')">Ir para o dashboard</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="route('login')">Entrar</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="route('register')">Criar conta</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main class="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-14 px-6 py-12">
            <section class="flex flex-col items-center gap-6 text-center">
                <AppLogoIcon class="size-16" />
                <h1 class="max-w-2xl text-4xl font-bold leading-tight tracking-tight sm:text-5xl">Seu vocabulário de alemão, direto do Kindle</h1>
                <p class="max-w-xl text-lg text-muted-foreground">
                    Transforme os destaques dos seus livros em cartões de estudo e fixe cada palavra com repetição espaçada.
                </p>

                <div class="flex flex-wrap items-center justify-center gap-2">
                    <span
                        v-for="example in articleExamples"
                        :key="example.article"
                        class="rounded-full border bg-card px-4 py-1.5 text-sm font-medium shadow-sm"
                        lang="de"
                    >
                        <span :class="example.color">{{ example.article }}</span>
                        {{ example.word }}
                    </span>
                </div>

                <div class="mt-2 flex flex-wrap justify-center gap-3">
                    <Button v-if="page.props.auth.user" size="lg" as-child>
                        <Link :href="route('study.index')"><GraduationCap class="size-5" /> Estudar agora</Link>
                    </Button>
                    <template v-else>
                        <Button size="lg" as-child>
                            <Link :href="route('register')">Começar agora</Link>
                        </Button>
                        <Button size="lg" variant="outline" as-child>
                            <Link :href="route('login')">Já tenho conta</Link>
                        </Button>
                    </template>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-3">
                <Card v-for="feature in features" :key="feature.title">
                    <CardContent class="space-y-3 pt-6">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <component :is="feature.icon" class="size-5" />
                        </div>
                        <h2 class="font-semibold">{{ feature.title }}</h2>
                        <p class="text-sm leading-relaxed text-muted-foreground">{{ feature.description }}</p>
                    </CardContent>
                </Card>
            </section>
        </main>

        <footer class="mx-auto flex w-full max-w-5xl items-center justify-center gap-2 px-6 py-6 text-sm text-muted-foreground">
            <BookMarked class="size-4" />
            <span>Wortschatz — do destaque à memória de longo prazo.</span>
        </footer>
    </div>
</template>
