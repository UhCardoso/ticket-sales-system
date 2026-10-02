<script setup lang="ts">
import { computed } from 'vue'
import { Inbox, RefreshCw, TriangleAlert } from '@lucide/vue'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import DashboardSkeleton from '@/components/common/DashboardSkeleton.vue'
import EventSalesCard from '@/components/common/EventSalesCard.vue'
import LiveIndicator from '@/components/common/LiveIndicator.vue'
import StatTile from '@/components/common/StatTile.vue'
import { useSalesDashboard } from '@/composables/useSalesDashboard'
import { formatMoney, formatQuantity } from '@/utils/formatMoney'

/**
 * The sales panel (requirements 6.1–6.3).
 *
 * A failed poll never blanks the screen: the warning sits above the last good reading, and the
 * indicator in the header says how old it is. Emptying the panel because one GET timed out
 * would be the worst thing it could do to whoever is watching the sale.
 */
const { events, totals, generatedAt, loading, refreshing, error, hasData, polling, refresh } =
  useSalesDashboard()

const tiles = computed(() => [
  {
    label: 'Ingressos vendidos',
    value: formatQuantity(totals.value.sold_quantity),
    swatchClass: 'bg-sold',
  },
  {
    label: 'Aguardando pagamento',
    value: formatQuantity(totals.value.reserved_quantity),
    swatchClass: 'bg-awaiting',
  },
  {
    label: 'Disponíveis',
    value: formatQuantity(totals.value.available_quantity),
    swatchClass: 'bg-stock-track',
    hint: `de ${formatQuantity(totals.value.total_quantity)} no total`,
  },
  {
    label: 'Receita acumulada',
    value: formatMoney(totals.value.revenue),
  },
])
</script>

<template>
  <div class="bg-background min-h-svh">
    <div class="mx-auto w-full max-w-7xl space-y-6 px-4 py-8 sm:px-6">
      <header class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
        <div class="space-y-1">
          <h1 class="text-2xl font-semibold tracking-tight">Painel de vendas</h1>
          <LiveIndicator
            v-if="hasData"
            :generated-at="generatedAt"
            :polling="polling"
            :refreshing="refreshing"
          />
        </div>

        <Button variant="outline" size="sm" :disabled="refreshing" @click="refresh">
          <RefreshCw
            class="size-3.5"
            :class="refreshing ? 'animate-spin motion-reduce:animate-none' : ''"
            aria-hidden="true"
          />
          Atualizar
        </Button>
      </header>

      <Alert v-if="error" variant="destructive">
        <TriangleAlert aria-hidden="true" />
        <AlertTitle>Não foi possível atualizar o painel</AlertTitle>
        <AlertDescription>
          {{ error }}
          {{ hasData ? 'Os números abaixo são da última leitura bem-sucedida.' : '' }}
        </AlertDescription>
      </Alert>

      <DashboardSkeleton v-if="loading" />

      <!--
        Sem nenhuma leitura bem-sucedida não há o que mostrar: renderizar os tiles aqui
        exibiria zeros como se fossem números reais, que é pior do que não mostrar nada.
        O alerta acima já explica o que aconteceu.
      -->
      <template v-else-if="hasData">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <StatTile
            v-for="tile in tiles"
            :key="tile.label"
            :label="tile.label"
            :value="tile.value"
            :swatch-class="tile.swatchClass"
            :hint="tile.hint"
          />
        </div>

        <Card v-if="events.length === 0 && !error">
          <CardContent class="flex flex-col items-center gap-2 py-12 text-center">
            <Inbox class="text-muted-foreground size-6" aria-hidden="true" />
            <p class="font-medium">Nenhum evento cadastrado</p>
            <p class="text-muted-foreground text-sm">
              Rode <code class="font-mono text-xs">php artisan db:seed</code> no backend para
              carregar os eventos de exemplo.
            </p>
          </CardContent>
        </Card>

        <div class="space-y-6">
          <EventSalesCard v-for="event in events" :key="event.id" :event="event" />
        </div>
      </template>
    </div>
  </div>
</template>
