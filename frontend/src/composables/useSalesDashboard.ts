import { computed, onMounted, ref, type ComputedRef, type Ref } from 'vue'
import { ApiError } from '@/config/AxiosClient'
import type {
  BatchSalesSummary,
  EventSalesSummary,
  EventSalesTotals,
  SalesSummaryResponse,
} from '@/models/SalesSummaryModel'
import SalesDashboardService from '@/services/SalesDashboardService'
import { sumMoney } from '@/utils/sumMoney'
import { usePolling } from './usePolling'

/** How a capacity splits, as fractions of the total that add up to 1. */
export interface StockShare {
  sold: number
  reserved: number
  available: number
}

export interface BatchView extends BatchSalesSummary {
  share: StockShare
  soldOut: boolean
}

export interface EventView {
  id: number
  name: string
  dateTime: Date
  totals: EventSalesTotals
  share: StockShare
  soldOut: boolean
  batches: BatchView[]
}

export interface GrandTotals extends EventSalesTotals {
  share: StockShare
}

export interface UseSalesDashboard {
  events: ComputedRef<EventView[]>
  totals: ComputedRef<GrandTotals>
  /** When the backend built the snapshot being shown — null before the first reading. */
  generatedAt: ComputedRef<Date | null>
  /** True only until the first reading arrives; later polls never blank the panel. */
  loading: Ref<boolean>
  /** A poll is in flight over data already on screen. */
  refreshing: Ref<boolean>
  /** The last failure, kept alongside the last good data rather than replacing it. */
  error: Ref<string | null>
  hasData: ComputedRef<boolean>
  /** False while the tab is hidden and polling is paused. */
  polling: Ref<boolean>
  refresh: () => Promise<void>
}

const DEFAULT_INTERVAL = 5_000

/**
 * Drives the sales panel: polls the summary and derives what the components render.
 *
 * The error handling is the part that matters here. A panel left open all day will lose a poll
 * sooner or later, and blanking the screen over one failed GET is the worst thing it could do —
 * so a failure sets `error` and keeps the last good reading on screen, letting the operator see
 * both the numbers and how old they are. An aborted request is not a failure at all: it is a
 * poll the next one superseded, or a closed view.
 */
export function useSalesDashboard(): UseSalesDashboard {
  // Instanciado dentro da função, não no escopo do módulo: duas telas montadas ao mesmo tempo
  // não podem compartilhar estado nenhum vindo daqui.
  const service = new SalesDashboardService()

  const response = ref<SalesSummaryResponse | null>(null)
  const loading = ref(true)
  const refreshing = ref(false)
  const error = ref<string | null>(null)

  let inFlight: AbortController | undefined

  async function load(): Promise<void> {
    inFlight?.abort()
    inFlight = new AbortController()
    refreshing.value = true

    try {
      response.value = await service.getSalesSummary(inFlight.signal)
      error.value = null
    } catch (cause) {
      if (cause instanceof ApiError && cause.isAborted) {
        return
      }

      error.value = cause instanceof Error ? cause.message : 'Falha ao atualizar o painel.'
    } finally {
      refreshing.value = false
      loading.value = false
    }
  }

  const interval = Number(import.meta.env.VITE_POLL_INTERVAL) || DEFAULT_INTERVAL
  const { active, start, trigger } = usePolling(load, { interval })

  onMounted(() => {
    void load()
    start()
  })

  const events = computed<EventView[]>(() => (response.value?.data ?? []).map(toEventView))

  const totals = computed<GrandTotals>(() => toGrandTotals(response.value?.data ?? []))

  const generatedAt = computed<Date | null>(() =>
    response.value ? new Date(response.value.meta.generated_at) : null,
  )

  return {
    events,
    totals,
    generatedAt,
    loading,
    refreshing,
    error,
    hasData: computed(() => response.value !== null),
    polling: active,
    refresh: trigger,
  }
}

function toEventView(event: EventSalesSummary): EventView {
  return {
    id: event.id,
    name: event.name,
    dateTime: new Date(event.date_time),
    totals: event.totals,
    share: toStockShare(event.totals),
    soldOut: event.totals.available_quantity === 0,
    batches: event.batches.map((batch) => ({
      ...batch,
      share: toStockShare(batch),
      soldOut: batch.available_quantity === 0,
    })),
  }
}

/**
 * Adds every event up into the headline figures.
 *
 * Revenue goes through sumMoney rather than a float sum: the backend accumulated it with bcmath
 * and the panel has no business losing cents on the way to a stat tile.
 */
function toGrandTotals(events: EventSalesSummary[]): GrandTotals {
  const totals: EventSalesTotals = {
    total_quantity: sumBy(events, (event) => event.totals.total_quantity),
    sold_quantity: sumBy(events, (event) => event.totals.sold_quantity),
    reserved_quantity: sumBy(events, (event) => event.totals.reserved_quantity),
    available_quantity: sumBy(events, (event) => event.totals.available_quantity),
    revenue: sumMoney(events.map((event) => event.totals.revenue)),
  }

  return { ...totals, share: toStockShare(totals) }
}

/**
 * Splits a capacity into the fractions the stock meter draws.
 *
 * Nothing on sale reads as fully available rather than dividing by zero.
 */
function toStockShare(counters: {
  total_quantity: number
  sold_quantity: number
  reserved_quantity: number
  available_quantity: number
}): StockShare {
  if (counters.total_quantity <= 0) {
    return { sold: 0, reserved: 0, available: 1 }
  }

  return {
    sold: counters.sold_quantity / counters.total_quantity,
    reserved: counters.reserved_quantity / counters.total_quantity,
    available: counters.available_quantity / counters.total_quantity,
  }
}

function sumBy<T>(items: T[], value: (item: T) => number): number {
  return items.reduce((total, item) => total + value(item), 0)
}
