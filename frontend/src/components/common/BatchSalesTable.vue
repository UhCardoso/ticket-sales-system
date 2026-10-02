<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import StockMeter from '@/components/charts/StockMeter.vue'
import type { BatchView } from '@/composables/useSalesDashboard'
import { formatMoney, formatQuantity } from '@/utils/formatMoney'

/**
 * The per-batch breakdown, and the panel's table view of the meter.
 *
 * Every number a meter encodes is also readable here, so nothing is reachable only by colour
 * or only on hover. Numeric columns carry tabular figures, which is what keeps the digits
 * aligned down the column as the counters move.
 */
defineProps<{ batches: BatchView[] }>()
</script>

<template>
  <Table>
    <TableHeader>
      <TableRow>
        <TableHead>Lote</TableHead>
        <TableHead class="text-right">Preço</TableHead>
        <TableHead class="text-right">Vendidos</TableHead>
        <TableHead class="text-right">Aguardando</TableHead>
        <TableHead class="text-right">Disponíveis</TableHead>
        <TableHead class="text-right">Receita</TableHead>
        <TableHead class="w-32">Ocupação</TableHead>
      </TableRow>
    </TableHeader>
    <TableBody>
      <TableRow v-for="batch in batches" :key="batch.id">
        <TableCell class="font-medium">
          <span class="flex items-center gap-2">
            {{ batch.name }}
            <Badge v-if="batch.soldOut" variant="secondary">Esgotado</Badge>
          </span>
        </TableCell>
        <TableCell class="text-right tabular-nums">{{ formatMoney(batch.price) }}</TableCell>
        <TableCell class="text-right tabular-nums">
          {{ formatQuantity(batch.sold_quantity) }}
        </TableCell>
        <TableCell class="text-right tabular-nums">
          {{ formatQuantity(batch.reserved_quantity) }}
        </TableCell>
        <TableCell class="text-right tabular-nums">
          {{ formatQuantity(batch.available_quantity) }}
        </TableCell>
        <TableCell class="text-right font-medium tabular-nums">
          {{ formatMoney(batch.revenue) }}
        </TableCell>
        <TableCell>
          <StockMeter
            :share="batch.share"
            :sold-quantity="batch.sold_quantity"
            :reserved-quantity="batch.reserved_quantity"
            :available-quantity="batch.available_quantity"
            :total-quantity="batch.total_quantity"
          />
        </TableCell>
      </TableRow>
    </TableBody>
  </Table>
</template>
