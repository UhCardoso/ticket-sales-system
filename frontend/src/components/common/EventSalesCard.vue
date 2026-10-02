<script setup lang="ts">
import { computed } from 'vue'
import { CalendarDays } from '@lucide/vue'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import StockMeter from '@/components/charts/StockMeter.vue'
import type { EventView } from '@/composables/useSalesDashboard'
import { formatMoney, formatPercent, formatQuantity } from '@/utils/formatMoney'
import BatchSalesTable from './BatchSalesTable.vue'
import StockLegend from './StockLegend.vue'

/**
 * One event: its own totals, the capacity meter, and the breakdown per batch.
 */
const props = defineProps<{ event: EventView }>()

const dateLabel = computed(() =>
  new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(props.event.dateTime),
)

const figures = computed(() => [
  { label: 'Vendidos', value: formatQuantity(props.event.totals.sold_quantity) },
  { label: 'Aguardando', value: formatQuantity(props.event.totals.reserved_quantity) },
  { label: 'Disponíveis', value: formatQuantity(props.event.totals.available_quantity) },
  { label: 'Receita', value: formatMoney(props.event.totals.revenue) },
])
</script>

<template>
  <Card>
    <CardHeader>
      <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
        <div class="space-y-1">
          <CardTitle class="flex items-center gap-2">
            {{ event.name }}
            <Badge v-if="event.soldOut" variant="secondary">Esgotado</Badge>
          </CardTitle>
          <p class="text-muted-foreground flex items-center gap-1.5 text-sm">
            <CalendarDays class="size-3.5" aria-hidden="true" />
            {{ dateLabel }}
          </p>
        </div>
        <p class="text-muted-foreground text-sm">
          {{ formatPercent(event.share.sold) }} da capacidade vendida
        </p>
      </div>
    </CardHeader>

    <CardContent class="space-y-6">
      <div class="space-y-3">
        <dl class="grid max-w-xl grid-cols-2 gap-x-8 gap-y-3 sm:grid-cols-4">
          <div v-for="figure in figures" :key="figure.label" class="space-y-0.5">
            <dt class="text-muted-foreground text-xs font-medium">{{ figure.label }}</dt>
            <dd class="text-lg leading-tight font-semibold">{{ figure.value }}</dd>
          </div>
        </dl>

        <StockMeter
          :share="event.share"
          :sold-quantity="event.totals.sold_quantity"
          :reserved-quantity="event.totals.reserved_quantity"
          :available-quantity="event.totals.available_quantity"
          :total-quantity="event.totals.total_quantity"
        />
        <StockLegend />
      </div>

      <Separator />

      <div class="-mx-6 overflow-x-auto px-6">
        <BatchSalesTable :batches="event.batches" />
      </div>
    </CardContent>
  </Card>
</template>
