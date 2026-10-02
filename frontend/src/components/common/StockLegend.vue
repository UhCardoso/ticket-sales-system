<script setup lang="ts">
/**
 * Names the three states of the meter.
 *
 * Present whenever a meter is, so identity never rests on colour alone. The text stays in ink
 * tokens; the swatch beside it carries the colour.
 */
</script>

<template>
  <ul class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
    <li class="flex items-center gap-1.5">
      <span class="bg-sold size-2 shrink-0 rounded-full" aria-hidden="true" />
      Vendidos
    </li>
    <li class="flex items-center gap-1.5">
      <span class="bg-awaiting size-2 shrink-0 rounded-full" aria-hidden="true" />
      Aguardando pagamento
    </li>
    <li class="flex items-center gap-1.5">
      <span class="bg-stock-track size-2 shrink-0 rounded-full" aria-hidden="true" />
      Disponíveis
    </li>
  </ul>
</template>
