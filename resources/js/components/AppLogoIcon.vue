<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <!-- Marca do Wortschatz: "W" com trema, monocromática (papel/tinta). Usa os
         tokens de tema, então inverte sozinha no modo claro/escuro. -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" :class="className" v-bind="$attrs">
        <rect width="40" height="40" rx="9" style="fill: hsl(var(--primary))" />
        <circle cx="16.2" cy="9.6" r="2.1" style="fill: hsl(var(--primary-foreground))" />
        <circle cx="23.8" cy="9.6" r="2.1" style="fill: hsl(var(--primary-foreground))" />
        <path
            d="M8.5 15.5 L13.2 30.5 L20 18.5 L26.8 30.5 L31.5 15.5"
            fill="none"
            style="stroke: hsl(var(--primary-foreground))"
            stroke-width="3.4"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>
