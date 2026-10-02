import type { Money } from '@/models/shared/MoneyModel'

/**
 * Display formatting for the values the domain carries as strings.
 *
 * Pure functions, no Vue and no state — the whole reason this is a util and not a composable.
 * Every pt-BR format the panel shows is defined here once, so a currency or a thousands
 * separator is never re-invented inside a component.
 *
 * `toNumber` is for layout maths only — bar widths and percentages, where a rounding error is
 * invisible. Never use it to display a value or to add two of them up; that is `sumMoney`.
 */

const BRL = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
})

const BRL_COMPACT = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
  notation: 'compact',
  maximumFractionDigits: 1,
})

const INTEGER = new Intl.NumberFormat('pt-BR')

const PERCENT = new Intl.NumberFormat('pt-BR', {
  style: 'percent',
  maximumFractionDigits: 0,
})

/**
 * Formats money for display: "1250.00" becomes "R$ 1.250,00".
 */
export function formatMoney(value: Money): string {
  return BRL.format(toNumber(value))
}

/**
 * Formats money short enough for a stat tile: "R$ 1,3 mil".
 *
 * Only for headline figures. The exact value goes in a tooltip beside it, so nothing is hidden.
 */
export function formatMoneyCompact(value: Money): string {
  return BRL_COMPACT.format(toNumber(value))
}

/**
 * Formats a ticket count with pt-BR thousands separators.
 */
export function formatQuantity(value: number): string {
  return INTEGER.format(value)
}

/**
 * Formats a 0-to-1 fraction as a whole percentage.
 */
export function formatPercent(fraction: number): string {
  return PERCENT.format(fraction)
}

/**
 * Reads money as a number, for layout maths only.
 */
export function toNumber(value: Money): number {
  const parsed = Number(value)

  return Number.isFinite(parsed) ? parsed : 0
}
