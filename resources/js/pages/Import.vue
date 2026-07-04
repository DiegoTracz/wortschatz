<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { BookOpen, CheckCircle2, FileText, LoaderCircle, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Importar', href: '/importar' }];

const page = usePage<SharedData>();
const result = computed(() => page.props.flash.import_result);

const form = useForm({
    file: null as File | null,
});

const fileInput = ref<HTMLInputElement | null>(null);
const dragging = ref(false);

function selectFile(files: FileList | null) {
    form.file = files?.[0] ?? null;
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    selectFile(event.dataTransfer?.files ?? null);
}

function submit() {
    form.post(route('import.store'), {
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Importar destaques" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
            <Card v-if="result" class="border-green-600/40 bg-green-500/5">
                <CardContent class="flex items-start gap-3 pt-6">
                    <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-green-600" />
                    <div class="space-y-1 text-sm">
                        <p class="font-medium">Importação concluída!</p>
                        <p class="text-muted-foreground">
                            {{ result.imported }} novo(s) destaque(s) em {{ result.books }} livro(s).
                            <template v-if="result.skipped"> {{ result.skipped }} já existiam e foram ignorados.</template>
                        </p>
                        <Link
                            :href="route('books.index')"
                            class="inline-block font-medium text-green-700 underline underline-offset-2 dark:text-green-500"
                        >
                            Ver meus livros →
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Importar do Kindle</CardTitle>
                    <CardDescription>Envie o arquivo <strong>My Clippings.txt</strong> com seus destaques e notas.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <label
                            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed p-10 text-center transition-colors"
                            :class="dragging ? 'border-primary bg-primary/5' : 'border-input hover:border-primary/50'"
                            @dragover.prevent="dragging = true"
                            @dragleave.prevent="dragging = false"
                            @drop.prevent="onDrop"
                        >
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".txt,text/plain"
                                class="sr-only"
                                @change="selectFile(($event.target as HTMLInputElement).files)"
                            />
                            <template v-if="form.file">
                                <FileText class="size-8 text-primary" />
                                <p class="text-sm font-medium">{{ form.file.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ (form.file.size / 1024).toFixed(0) }} KB — clique para trocar</p>
                            </template>
                            <template v-else>
                                <Upload class="size-8 text-muted-foreground" />
                                <p class="text-sm font-medium">Arraste o arquivo aqui ou clique para escolher</p>
                                <p class="text-xs text-muted-foreground">My Clippings.txt (máx. 10 MB)</p>
                            </template>
                        </label>
                        <InputError :message="form.errors.file" />

                        <Button type="submit" class="w-full" :disabled="!form.file || form.processing">
                            <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                            Importar destaques
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base"><BookOpen class="size-4" /> Onde encontro esse arquivo?</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 text-sm text-muted-foreground">
                    <ol class="list-inside list-decimal space-y-1">
                        <li>Conecte o Kindle ao computador com o cabo USB.</li>
                        <li>No Windows, abra o Explorador de Arquivos e acesse a unidade <strong>Kindle</strong>.</li>
                        <li>
                            Na pasta <strong>documents</strong>, copie o arquivo <strong>My Clippings.txt</strong> — ele reúne todos os destaques e
                            notas de todos os livros.
                        </li>
                        <li>Envie o arquivo aqui. Pode reenviar sempre que quiser: destaques repetidos são ignorados automaticamente.</li>
                    </ol>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
