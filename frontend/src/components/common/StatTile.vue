<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'

/**
 * One headline figure of the panel.
 *
 * The value uses the font's proportional figures, not tabular ones: equal-width digits make a
 * short number look loose at this size, and each tile is its own grid cell, so a value growing
 * a digit cannot shift anything else on screen.
 */
defineProps<{
  label: string
  value: string
  /** Optional swatch colour class, tying the tile to its segment in the meter. */
  swatchClass?: string
  hint?: string
}>()
</script>

<template>
  <Card>
    <CardContent class="flex flex-col gap-1">
      <p class="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
        <span v-if="swatchClass" :class="swatchClass" class="size-2 shrink-0 rounded-full" aria-hidden="true" />
        {{ label }}
      </p>
      <p class="text-2xl leading-tight font-semibold tracking-tight">{{ value }}</p>
      <p v-if="hint" class="text-muted-foreground text-xs">{{ hint }}</p>
    </CardContent>
  </Card>
</template>
