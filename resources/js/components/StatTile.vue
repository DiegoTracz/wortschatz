<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';

defineProps<{
    label: string;
    value: string | number;
    context?: string;
}>();
</script>

<template>
    <Card>
        <CardContent class="flex h-full flex-col justify-between gap-4 pt-6">
            <p class="flex items-center gap-1.5 text-sm text-muted-foreground">
                <slot name="icon" />
                {{ label }}
            </p>
            <div class="space-y-1">
                <p class="text-3xl font-semibold tracking-tight">{{ value }}</p>
                <p v-if="context" class="text-xs text-muted-foreground">{{ context }}</p>
            </div>
        </CardContent>
    </Card>
</template>
