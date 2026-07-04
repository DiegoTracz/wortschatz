<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, Upload } from 'lucide-vue-next';

interface BookSummary {
    id: number;
    title: string;
    author: string | null;
    highlights_count: number;
    cards_count: number;
}

defineProps<{ books: BookSummary[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Livros', href: '/livros' }];
</script>

<template>
    <Head title="Livros" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div v-if="!books.length" class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                <BookOpen class="size-10 text-muted-foreground" />
                <p class="font-medium">Nenhum livro ainda</p>
                <p class="max-w-sm text-sm text-muted-foreground">Importe o My Clippings.txt do seu Kindle para ver seus livros e destaques aqui.</p>
                <Button as-child>
                    <Link :href="route('import.create')"><Upload class="size-4" /> Importar destaques</Link>
                </Button>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link v-for="book in books" :key="book.id" :href="route('books.show', book.id)" class="group">
                    <Card class="h-full transition-colors group-hover:border-primary/50">
                        <CardHeader>
                            <CardTitle class="line-clamp-2 text-base leading-snug">{{ book.title }}</CardTitle>
                            <CardDescription>{{ book.author ?? 'Autor desconhecido' }}</CardDescription>
                        </CardHeader>
                        <CardContent class="flex gap-4 text-sm text-muted-foreground">
                            <span>{{ book.highlights_count }} destaque(s)</span>
                            <span>{{ book.cards_count }} com cartão</span>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
