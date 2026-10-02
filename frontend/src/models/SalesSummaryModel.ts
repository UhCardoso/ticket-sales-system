import type { Money } from '@/models/shared/MoneyModel'

/**
 * The sales summary as the API returns it, mirroring the backend's JsonResources one to one.
 *
 * This is the contract and nothing else — interfaces, never logic, never a default value. The
 * field names are the ones Laravel sends, in snake_case, and no layer renames them on the way
 * in. Anything the panel derives from these numbers — bar shares, sold-out flags, totals across
 * events — is presentation, so it is typed in the composable instead of here.
 */

/** A batch's position: the four numbers requirement 6.1 asks for. */
export interface BatchSalesSummary {
  id: number
  name: string
  price: Money
  total_quantity: number
  sold_quantity: number
  reserved_quantity: number
  available_quantity: number
  revenue: Money
}

/** An event's position, already added up across its batches by the backend's bcmath. */
export interface EventSalesTotals {
  total_quantity: number
  sold_quantity: number
  reserved_quantity: number
  available_quantity: number
  revenue: Money
}

export interface EventSalesSummary {
  id: number
  name: string
  date_time: string
  totals: EventSalesTotals
  batches: BatchSalesSummary[]
}

export interface SalesSummaryMeta {
  /** When the cached snapshot was built — not when this request was served. */
  generated_at: string
  /** Seconds the snapshot lives in the backend cache. */
  cache_ttl: number
}

export interface SalesSummaryResponse {
  data: EventSalesSummary[]
  meta: SalesSummaryMeta
}
