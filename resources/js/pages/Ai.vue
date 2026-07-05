<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, XCircle } from 'lucide-vue-next';

interface UsageSummary {
    requests: number;
    tokens: number;
    cost: number;
}

interface UsageRow {
    id: number;
    model: string;
    total_tokens: number;
    cost: number;
    created_at: string;
}

const props = defineProps<{
    configured: boolean;
    has_custom_key: boolean;
    env_key: boolean;
    model: string;
    models: { value: string; label: string }[];
    usd_brl: number;
    usage: {
        total: UsageSummary;
        month: UsageSummary;
        recent: UsageRow[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'IA', href: '/ia' }];

const form = useForm({ model: props.model, api_key: '', remove_key: false });

const submit = () => form.patch(route('ai.update'), { preserveScroll: true, onSuccess: () => form.reset('api_key') });

function removeKey() {
    useForm({ model: form.model, remove_key: true }).patch(route('ai.update'), { preserveScroll: true });
}

function money(value: number): string {
    return `US$ ${value.toFixed(value < 0.01 ? 5 : 4)}`;
}

function reais(usd: number): string {
    const value = usd * props.usd_brl;
    return value.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        minimumFractionDigits: 2,
        maximumFractionDigits: value < 0.01 ? 5 : 2,
    });
}

const rateLabel = `US$ 1 ≈ ${props.usd_brl.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}`;

function tokens(value: number): string {
    return value.toLocaleString('pt-BR');
}

function when(value: string): string {
    return new Date(value.replace(' ', 'T')).toLocaleString('pt-BR');
}
</script>

<template>
    <Head title="IA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <Heading title="Inteligência artificial" description="Escolha o modelo da OpenAI, informe sua chave e acompanhe o uso e o custo." />

            <!-- Estado da chave -->
            <div
                class="flex items-center gap-2 rounded-md border p-3 text-sm"
                :class="
                    configured ? 'border-green-500/30 text-green-700 dark:text-green-400' : 'border-amber-500/30 text-amber-700 dark:text-amber-500'
                "
            >
                <CheckCircle2 v-if="configured" class="size-4 shrink-0" />
                <XCircle v-else class="size-4 shrink-0" />
                <span v-if="has_custom_key">Usando a chave salva aqui.</span>
                <span v-else-if="env_key">Usando a chave do arquivo <code>.env</code> (OPEN_IA_TOKEN).</span>
                <span v-else
                    >Nenhuma chave configurada. Insira a chave da OpenAI abaixo ou defina <code>OPEN_IA_TOKEN</code> no <code>.env</code>.</span
                >
            </div>

            <!-- Modelo + chave -->
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid gap-2">
                    <Label for="ai-model">Modelo</Label>
                    <select
                        id="ai-model"
                        v-model="form.model"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option v-for="option in models" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <p class="text-xs text-muted-foreground">Modelos mais capazes geram mnemônicos melhores, mas custam mais por cartão.</p>
                </div>

                <div class="grid gap-2">
                    <Label for="ai-key">Chave da OpenAI</Label>
                    <Input
                        id="ai-key"
                        v-model="form.api_key"
                        type="password"
                        autocomplete="off"
                        :placeholder="has_custom_key ? '•••••••••• (salva)' : 'sk-...'"
                    />
                    <p class="text-xs text-muted-foreground">
                        Guardada criptografada. Deixe em branco para manter a atual. Tem precedência sobre a chave do <code>.env</code>.
                    </p>
                    <InputError :message="form.errors.api_key" />
                    <button v-if="has_custom_key" type="button" class="w-fit text-xs font-medium text-destructive hover:underline" @click="removeKey">
                        Remover chave salva
                    </button>
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="form.processing">Salvar</Button>
                    <TransitionRoot
                        :show="form.recentlySuccessful"
                        enter="transition ease-in-out"
                        enter-from="opacity-0"
                        leave="transition ease-in-out"
                        leave-to="opacity-0"
                    >
                        <p class="text-sm text-muted-foreground">Salvo.</p>
                    </TransitionRoot>
                </div>
            </form>

            <!-- Uso e custo -->
            <div class="space-y-3">
                <HeadingSmall
                    title="Uso e custo"
                    description="Consumo estimado das chamadas à OpenAI. A OpenAI cobra em dólar; convertido para real pela cotação do dia."
                />

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-lg border p-3">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Este mês</p>
                        <p class="mt-1 text-lg font-semibold">{{ reais(usage.month.cost) }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ money(usage.month.cost) }} · {{ usage.month.requests }} req · {{ tokens(usage.month.tokens) }} tokens
                        </p>
                    </div>
                    <div class="rounded-lg border p-3">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Total</p>
                        <p class="mt-1 text-lg font-semibold">{{ reais(usage.total.cost) }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ money(usage.total.cost) }} · {{ usage.total.requests }} req · {{ tokens(usage.total.tokens) }} tokens
                        </p>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">{{ rateLabel }} · atualizado a cada 12h.</p>

                <div v-if="usage.recent.length" class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50 text-left text-muted-foreground">
                                <th class="px-3 py-2 font-medium">Quando</th>
                                <th class="px-3 py-2 font-medium">Modelo</th>
                                <th class="px-3 py-2 text-right font-medium">Tokens</th>
                                <th class="px-3 py-2 text-right font-medium">Custo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in usage.recent" :key="row.id" class="border-b last:border-0">
                                <td class="px-3 py-2 text-muted-foreground">{{ when(row.created_at) }}</td>
                                <td class="px-3 py-2">{{ row.model }}</td>
                                <td class="px-3 py-2 text-right text-muted-foreground">{{ tokens(row.total_tokens) }}</td>
                                <td class="px-3 py-2 text-right">
                                    {{ reais(row.cost) }}
                                    <span class="block text-xs text-muted-foreground">{{ money(row.cost) }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-muted-foreground">Nenhuma geração ainda.</p>
            </div>
        </div>
    </AppLayout>
</template>
