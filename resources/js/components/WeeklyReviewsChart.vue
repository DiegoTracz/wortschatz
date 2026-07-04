<script setup lang="ts">
import { computed, ref } from 'vue';

interface Day {
    label: string;
    date: string;
    total: number;
}

const props = defineProps<{ days: Day[] }>();

const hovered = ref<number | null>(null);

const max = computed(() => Math.max(...props.days.map((day) => day.total), 0));
const allZero = computed(() => max.value === 0);
const maxIndex = computed(() => props.days.findIndex((day) => day.total === max.value));

// Teto do eixo em número "redondo" e divisível por 2, para gridlines limpas.
const axisMax = computed(() => {
    if (max.value === 0) return 4;
    const power = Math.pow(10, Math.floor(Math.log10(max.value)));
    for (const step of [2, 4, 6, 8, 10]) {
        if (max.value <= step * power) return step * power;
    }
    return 10 * power;
});

const gridTicks = computed(() => [axisMax.value, axisMax.value / 2]);

function heightPct(total: number): number {
    return (total / axisMax.value) * 100;
}

/** Rótulo direto apenas no maior dia e no dia atual (o último). */
function showsLabel(index: number): boolean {
    const day = props.days[index];
    return day.total > 0 && (index === maxIndex.value || index === props.days.length - 1);
}
</script>

<template>
    <div>
        <div class="relative" aria-hidden="true">
            <!-- Gridlines hairline + rótulos do eixo -->
            <div class="pointer-events-none absolute inset-x-0 bottom-6 top-0">
                <div
                    v-for="tick in gridTicks"
                    :key="tick"
                    class="absolute inset-x-0 flex items-center gap-2"
                    :style="{ bottom: `${heightPct(tick)}%` }"
                >
                    <span class="w-5 text-right text-[10px] tabular-nums leading-none text-muted-foreground/70">{{ tick }}</span>
                    <span class="h-px flex-1 bg-border/60" />
                </div>
            </div>

            <!-- Colunas -->
            <div class="relative ml-7 flex h-44 items-stretch pb-6">
                <div
                    v-for="(day, index) in days"
                    :key="index"
                    class="group relative flex flex-1 cursor-default flex-col justify-end outline-none"
                    tabindex="0"
                    @mouseenter="hovered = index"
                    @mouseleave="hovered = null"
                    @focus="hovered = index"
                    @blur="hovered = null"
                >
                    <!-- Rótulo direto (seletivo: pico e hoje) -->
                    <span
                        v-if="showsLabel(index) && hovered !== index"
                        class="pointer-events-none mx-auto mb-1 text-[11px] leading-none text-muted-foreground"
                    >
                        {{ day.total }}
                    </span>

                    <!-- Barra: fina (≤24px), ponta arredondada, base reta -->
                    <div
                        v-if="day.total > 0"
                        class="mx-auto w-full max-w-6 rounded-t bg-[hsl(var(--chart-1))] transition-[filter,height] duration-200 group-hover:brightness-110 group-focus-visible:brightness-110"
                        :style="{ height: `${heightPct(day.total)}%` }"
                    />

                    <!-- Dia da semana -->
                    <span
                        class="absolute inset-x-0 -bottom-0.5 text-center text-[11px] capitalize leading-none"
                        :class="index === days.length - 1 ? 'font-medium text-foreground' : 'text-muted-foreground'"
                    >
                        {{ day.label }}
                    </span>

                    <!-- Tooltip -->
                    <div
                        v-if="hovered === index"
                        class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 -translate-x-1/2 whitespace-nowrap rounded-md border bg-popover px-2.5 py-1.5 text-xs shadow-md"
                    >
                        <span class="font-semibold text-popover-foreground">{{ day.total }}</span>
                        <span class="text-muted-foreground"> · {{ day.label }} {{ day.date }}</span>
                    </div>
                </div>

                <!-- Linha de base -->
                <div class="absolute inset-x-0 bottom-6 h-px bg-border" />
            </div>

            <p v-if="allZero" class="absolute inset-x-0 top-1/3 text-center text-sm text-muted-foreground">Nenhuma revisão nos últimos 7 dias</p>
        </div>

        <!-- Equivalente acessível do gráfico -->
        <table class="sr-only">
            <caption>
                Revisões por dia nos últimos 7 dias
            </caption>
            <thead>
                <tr>
                    <th scope="col">Dia</th>
                    <th scope="col">Revisões</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(day, index) in days" :key="index">
                    <td>{{ day.label }} {{ day.date }}</td>
                    <td>{{ day.total }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
