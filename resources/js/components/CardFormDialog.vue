<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson } from '@/lib/api';
import { splitArticle } from '@/lib/german';
import type { CardData } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { Languages, LoaderCircle, Sparkles } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    card?: CardData | null;
    highlight?: { id: number; content: string } | null;
    presetFront?: string | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

interface EnrichUsage {
    model: string;
    total_tokens: number;
    cost: number;
}

interface EnrichResult {
    article: string;
    meanings: string;
    examples: string[];
    mnemonic: string;
    usage: EnrichUsage;
}

const form = useForm({
    front: '',
    back: '',
    context: '' as string | null,
    mnemonic: '' as string | null,
    highlight_id: null as number | null,
});

// Eselsbrücke é opcional: o usuário decide se entra no cartão.
const includeMnemonic = ref(false);

const translating = ref(false);
const translationError = ref<string | null>(null);
const detectingArticle = ref(false);
const articleNote = ref<string | null>(null);

const enriching = ref(false);
const enrichError = ref<string | null>(null);
const enrichUsage = ref<EnrichUsage | null>(null);

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.clearErrors();
        form.front = props.card?.front ?? props.presetFront ?? '';
        form.back = props.card?.back ?? '';
        form.context = props.card?.context ?? props.highlight?.content ?? '';
        form.mnemonic = props.card?.mnemonic ?? '';
        form.highlight_id = props.highlight?.id ?? null;
        includeMnemonic.value = !!props.card?.mnemonic;
        translationError.value = null;
        articleNote.value = null;
        enrichError.value = null;
        enrichUsage.value = null;
    },
);

// Palavras do destaque, clicáveis para montar a frente do cartão.
const words = computed(() =>
    (props.highlight?.content ?? '')
        .split(/\s+/)
        .map((word) => word.replace(/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/gu, ''))
        .filter((word) => word.length > 1),
);

const usageLabel = computed(() => {
    const usage = enrichUsage.value;
    if (!usage) return null;
    return `Gerado com ${usage.model} · ${usage.total_tokens} tokens · US$ ${usage.cost.toFixed(5)}`;
});

function addWord(word: string) {
    form.front = form.front.trim() ? `${form.front.trim()} ${word}` : word;
    detectArticle();
}

// Remove a marcação <b> dos exemplos ao gravar no verso (texto puro no estudo).
function stripTags(text: string): string {
    return text.replace(/<\/?[^>]+>/g, '');
}

// Prefixa o artigo (der/die/das) na frente quando ainda não houver e capitaliza
// o substantivo (alemão exige inicial maiúscula), para o color-coding de gênero
// aparecer certo no cartão.
function applyArticle(article: string) {
    if (!article) return;
    if (splitArticle(form.front).article) return;
    const word = form.front.trim();
    form.front = `${article} ${word.charAt(0).toUpperCase()}${word.slice(1)}`;
}

/**
 * Substantivos alemães são capitalizados: quando a frente é uma única palavra
 * com inicial maiúscula e sem artigo, busca o gênero no Wiktionary e
 * completa com der/die/das.
 */
async function detectArticle() {
    const word = form.front.trim();

    if (detectingArticle.value || !/^\p{Lu}[\p{L}-]*$/u.test(word)) return;

    detectingArticle.value = true;
    articleNote.value = null;

    try {
        const { article } = await postJson<{ article: string | null }>(route('article.detect'), { word });

        if (article && form.front.trim() === word) {
            form.front = `${article} ${word}`;
            articleNote.value = `Artigo "${article}" detectado automaticamente.`;
        }
    } catch {
        // Detecção é melhoria opcional: falha silenciosa.
    } finally {
        detectingArticle.value = false;
    }
}

/** Tradução simples e gratuita (MyMemory). */
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

/**
 * Tradução rica com a OpenAI: preenche o verso com os significados e a(s)
 * frase(s) de exemplo juntas, aplica o gênero na frente e, se marcado, gera a
 * Eselsbrücke no campo próprio.
 */
async function enrich() {
    if (!form.front.trim() || enriching.value) return;

    enriching.value = true;
    enrichError.value = null;

    try {
        const result = await postJson<EnrichResult>(route('enrich'), { word: form.front, context: form.context });

        applyArticle(result.article);

        const parts: string[] = [];
        if (result.meanings) parts.push(result.meanings);
        const examples = result.examples.map(stripTags).filter(Boolean);
        if (examples.length) parts.push(examples.join('\n'));
        if (parts.length) form.back = parts.join('\n\n');

        if (result.mnemonic) {
            form.mnemonic = result.mnemonic;
            includeMnemonic.value = true;
        }

        enrichUsage.value = result.usage;
    } catch (error) {
        enrichError.value = error instanceof Error ? error.message : 'Falha ao gerar com IA.';
    } finally {
        enriching.value = false;
    }
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    };

    // Só envia a Eselsbrücke quando marcada para entrar no cartão.
    form.transform((data) => ({
        ...data,
        mnemonic: includeMnemonic.value ? data.mnemonic : null,
    }));

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
                    <Input id="card-front" v-model="form.front" placeholder="das Fernweh" autocomplete="off" @blur="detectArticle" />
                    <p v-if="detectingArticle" class="text-xs text-muted-foreground">Detectando artigo…</p>
                    <p v-else-if="articleNote" class="text-xs text-muted-foreground">{{ articleNote }}</p>
                    <InputError :message="form.errors.front" />
                </div>

                <div class="space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <Label for="card-back">Verso (tradução + exemplo)</Label>
                        <div class="flex gap-1.5">
                            <Button type="button" variant="outline" size="sm" :disabled="translating || !form.front.trim()" @click="translate">
                                <LoaderCircle v-if="translating" class="size-4 animate-spin" />
                                <Languages v-else class="size-4" />
                                Traduzir
                            </Button>
                            <Button type="button" variant="outline" size="sm" :disabled="enriching || !form.front.trim()" @click="enrich">
                                <LoaderCircle v-if="enriching" class="size-4 animate-spin" />
                                <Sparkles v-else class="size-4" />
                                IA
                            </Button>
                        </div>
                    </div>
                    <textarea
                        id="card-back"
                        v-model="form.back"
                        rows="4"
                        placeholder="a vontade de viajar, saudade de lugares distantes"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 md:text-sm"
                    />
                    <p v-if="translationError" class="text-sm text-destructive">{{ translationError }}</p>
                    <p v-if="enrichError" class="text-xs text-destructive">{{ enrichError }}</p>
                    <p v-else-if="usageLabel" class="text-xs text-muted-foreground">{{ usageLabel }}</p>
                    <InputError :message="form.errors.back" />
                </div>

                <div class="space-y-1.5">
                    <Label for="card-context">Contexto (frase do livro)</Label>
                    <textarea
                        id="card-context"
                        v-model="form.context"
                        rows="2"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 md:text-sm"
                    />
                    <InputError :message="form.errors.context" />
                </div>

                <div class="space-y-1.5 rounded-md border border-input bg-muted/30 p-3">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input v-model="includeMnemonic" type="checkbox" class="size-4 rounded border-input accent-primary" />
                        🧠 Incluir Eselsbrücke (mnemônico) no cartão
                    </label>
                    <textarea
                        id="card-mnemonic"
                        v-model="form.mnemonic"
                        rows="2"
                        placeholder="Uma associação para lembrar a palavra e o gênero…"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 md:text-sm"
                    />
                    <InputError :message="form.errors.mnemonic" />
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
