<script setup lang="ts">
import { computed } from 'vue'
import type { StockShare } from '@/composables/useSalesDashboard'
import { formatQuantity } from '@/utils/formatMoney'

/**
 * The stacked capacity meter: sold and awaiting payment filling a track that is the total.
 *
 * This is the one mark that makes the backend's invariant visible — sold + awaiting +
 * available === total — so the health of a batch reads without reading a single number.
 * Only the two realised states get a hue; "available" stays the empty track, because
 * nothing-yet-happened is not a category. The 2px separator between the fills is the card
 * surface, never a border drawn around the marks.
 */
const props = defineProps<{
  share: StockShare
  soldQuantity: number
  reservedQuantity: number
  availableQuantity: number
  totalQuantity: number
}>()

const percent = (fraction: number) => `${(fraction * 100).toFixed(2)}%`

const label = computed(
  () =>
    `${formatQuantity(props.soldQuantity)} vendidos, ` +
    `${formatQuantity(props.reservedQuantity)} aguardando pagamento e ` +
    `${formatQuantity(props.availableQuantity)} disponíveis ` +
    `de ${formatQuantity(props.totalQuantity)}.`,
)
</script>

<template>
  <div
    class="bg-stock-track relative h-2 w-full overflow-hidden rounded-full"
    role="img"
    :aria-label="label"
  >
    <div
      v-if="share.sold > 0"
      class="bg-sold absolute inset-y-0 left-0 transition-[width] duration-500 motion-reduce:transition-none"
      :style="{ width: percent(share.sold) }"
    />
    <div
      v-if="share.reserved > 0"
      class="bg-awaiting border-card absolute inset-y-0 transition-[width,left] duration-500 motion-reduce:transition-none"
      :class="share.sold > 0 ? 'border-l-2' : ''"
      :style="{ left: percent(share.sold), width: percent(share.reserved) }"
    />
  </div>
</template>
