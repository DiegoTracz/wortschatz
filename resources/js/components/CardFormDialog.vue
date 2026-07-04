<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson } from '@/lib/api';
import type { CardData } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { Languages, LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    card?: CardData | null;
    highlight?: { id: number; content: string } | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const form = useForm({
    front: '',
    back: '',
    context: '' as string | null,
    highlight_id: null as number | null,
});

const translating = ref(false);
const translationError = ref<string | null>(null);

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.clearErrors();
        form.front = props.card?.front ?? '';
        form.back = props.card?.back ?? '';
        form.context = props.card?.context ?? props.highlight?.content ?? '';
        form.highlight_id = props.highlight?.id ?? null;
        translationError.value = null;
    },
);

// Palavras do destaque, clicáveis para montar a frente do cartão.
const words = computed(() =>
    (props.highlight?.content ?? '')
        .split(/\s+/)
        .map((word) => word.replace(/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/gu, ''))
        .filter((word) => word.length > 1),
);

function addWord(word: string) {
    form.front = form.front.trim() ? `${form.front.trim()} ${word}` : word;
}

async function translate() {
    if (!form.front.trim() || translating.value) return;

    translating.value = true;
    translationError.value = null;

    try {
        const { translation } = await postJson<{ translation: string }>(route('translate'), { text: form.front });
        form.back = translation;
    } catch (error) {
        translationError.value = error instanceof Error ? error.message : 'Falha na tradução.';
    } finally {
        translating.value = false;
    }
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    };

    if (props.card) {
        form.put(route('cards.update', props.card.id), options);
    } else {
        form.post(route('cards.store'), options);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ card ? 'Editar cartão' : 'Novo cartão' }}</DialogTitle>
                <DialogDescription>
                    {{ card ? 'Ajuste a palavra, a tradução ou o contexto.' : 'A frente é a palavra ou frase em alemão; o verso, a tradução.' }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div v-if="words.length" class="space-y-1.5">
                    <Label>Clique nas palavras do destaque para montar a frente</Label>
                    <div class="flex max-h-28 flex-wrap gap-1 overflow-y-auto rounded-md border border-input bg-muted/40 p-2">
                        <button
                            v-for="(word, index) in words"
                            :key="`${word}-${index}`"
                            type="button"
                            class="rounded bg-background px-1.5 py-0.5 text-sm shadow-sm transition-colors hover:bg-primary hover:text-primary-foreground"
                            @click="addWord(word)"
                        >
                            {{ word }}
                        </button>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="card-front">Frente (alemão)</Label>
                    <Input id="card-front" v-model="form.front" placeholder="das Fernweh" autocomplete="off" />
                    <InputError :message="form.errors.front" />
                </div>

                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <Label for="card-back">Verso (tradução / significado)</Label>
                        <Button type="button" variant="outline" size="sm" :disabled="translating || !form.front.trim()" @click="translate">
                            <LoaderCircle v-if="translating" class="size-4 animate-spin" />
                            <Languages v-else class="size-4" />
                            Traduzir
                        </Button>
                    </div>
                    <Input id="card-back" v-model="form.back" placeholder="a vontade de viajar, saudade de lugares distantes" autocomplete="off" />
                    <p v-if="translationError" class="text-sm text-destructive">{{ translationError }}</p>
                    <InputError :message="form.errors.back" />
                </div>

                <div class="space-y-1.5">
                    <Label for="card-context">Contexto (frase do livro)</Label>
                    <textarea
                        id="card-context"
                        v-model="form.context"
                        rows="3"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 md:text-sm"
                    />
                    <InputError :message="form.errors.context" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="emit('update:open', false)">Cancelar</Button>
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                        {{ card ? 'Salvar' : 'Criar cartão' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
