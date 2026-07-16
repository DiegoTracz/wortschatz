<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, Download, FileJson, HardDriveUpload, LoaderCircle, RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{
    stats: { books: number; highlights: number; cards: number; reviews: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Sincronizar', href: '/sincronizar' }];

const page = usePage<SharedData>();
const result = computed(() => page.props.flash.sync_result);
const error = computed(() => page.props.flash.sync_error);

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
    form.post(route('sync.import'), {
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Sincronizar entre computadores" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
            <Card v-if="result" class="border-grade-3/40 bg-grade-3/5">
                <CardContent class="flex items-start gap-3 pt-6">
                    <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-grade-3" />
                    <div class="space-y-1 text-sm">
                        <p class="font-medium">Sincronização concluída!</p>
                        <p class="text-muted-foreground">
                            {{ result.cards }} cartão(ões) e {{ result.reviews }} revisão(ões) novos; {{ result.highlights }} destaque(s) em
                            {{ result.books }} livro(s) novo(s).
                            <template v-if="result.cards_updated"> {{ result.cards_updated }} cartão(ões) atualizados.</template>
                            O que já existia foi ignorado.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="error" class="border-destructive/40 bg-destructive/5">
                <CardContent class="flex items-start gap-3 pt-6">
                    <AlertCircle class="mt-0.5 size-5 shrink-0 text-destructive" />
                    <p class="text-sm">{{ error }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"><Download class="size-4" /> Exportar deste computador</CardTitle>
                    <CardDescription>
                        Baixa um arquivo JSON com seus {{ stats.books }} livro(s), {{ stats.highlights }} destaque(s), {{ stats.cards }} cartão(ões) e
                        {{ stats.reviews }} revisão(ões). Guarde-o no seu Google Drive (ou outro serviço) para levar a outro computador.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button as-child class="w-full">
                        <a :href="route('sync.export')">
                            <Download class="size-4" />
                            Baixar meus dados (.json)
                        </a>
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"><HardDriveUpload class="size-4" /> Importar de outro computador</CardTitle>
                    <CardDescription>
                        Envie aqui o arquivo exportado na outra máquina. O merge é seguro: nada é apagado, duplicados são ignorados e o agendamento
                        das revisões é recalculado a partir do histórico completo.
                    </CardDescription>
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
                                accept=".json,application/json"
                                class="sr-only"
                                @change="selectFile(($event.target as HTMLInputElement).files)"
                            />
                            <template v-if="form.file">
                                <FileJson class="size-8 text-primary" />
                                <p class="text-sm font-medium">{{ form.file.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ (form.file.size / 1024).toFixed(0) }} KB — clique para trocar</p>
                            </template>
                            <template v-else>
                                <HardDriveUpload class="size-8 text-muted-foreground" />
                                <p class="text-sm font-medium">Arraste o arquivo aqui ou clique para escolher</p>
                                <p class="text-xs text-muted-foreground">wortschatz-*.json (máx. 50 MB)</p>
                            </template>
                        </label>
                        <InputError :message="form.errors.file" />

                        <Button type="submit" class="w-full" :disabled="!form.file || form.processing">
                            <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                            Importar e mesclar
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base"><RefreshCw class="size-4" /> Como funciona</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 text-sm text-muted-foreground">
                    <ol class="list-inside list-decimal space-y-1">
                        <li>No computador com os dados mais recentes, clique em <strong>Baixar meus dados</strong>.</li>
                        <li>Suba o arquivo para o seu Google Drive (ou envie por e-mail, pendrive etc.).</li>
                        <li>No outro computador, baixe o arquivo do Drive e importe-o aqui.</li>
                        <li>Pode repetir sempre que quiser, em qualquer direção — importar duas vezes não duplica nada.</li>
                    </ol>
                    <p>Os arquivos PDF dos livros não entram no export — só os metadados, destaques, cartões e revisões.</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
