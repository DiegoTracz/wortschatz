<script setup lang="ts">
import { computed, ref } from 'vue';

interface Bar {
    label: string;
    value: number;
}

const props = withDefaults(
    defineProps<{
        bars: Bar[];
        height?: number;
        startLabel?: string;
        endLabel?: string;
        unit?: string;
    }>(),
    { height: 80, unit: 'destaque(s)' },
);

const max = computed(() => props.bars.reduce((m, b) => Math.max(m, b.value), 1));
const hovered = ref<number | null>(null);

// Posição horizontal do tooltip: centro da barra sob o cursor.
const tooltipLeft = computed(() => (hovered.value === null ? 0 : ((hovered.value + 0.5) / props.bars.length) * 100));
</script>

<template>
    <div class="relative">
        <div
            v-if="hovered !== null"
            class="pointer-events-none absolute -top-1 z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-md border bg-popover px-2 py-1 text-xs text-popover-foreground shadow-md"
            :style="{ left: `${tooltipLeft}%` }"
        >
            <span class="font-medium">{{ bars[hovered].label }}</span>
            <span class="text-muted-foreground"> · {{ bars[hovered].value }} {{ unit }}</span>
        </div>

        <div class="flex items-end gap-0.5" :style="{ height: `${height}px` }">
            <div
                v-for="(bar, i) in bars"
                :key="i"
                class="min-h-[2px] flex-1 rounded-t-sm bg-primary/60 transition-colors hover:bg-primary"
                :class="{ 'bg-primary': hovered === i }"
                :style="{ height: `${Math.max(2, (bar.value / max) * 100)}%` }"
                @mouseenter="hovered = i"
                @mouseleave="hovered = null"
            ></div>
        </div>

        <div v-if="startLabel || endLabel" class="mt-1.5 flex justify-between text-[11px] text-muted-foreground">
            <span>{{ startLabel }}</span>
            <span>{{ endLabel }}</span>
        </div>
    </div>
</template>
