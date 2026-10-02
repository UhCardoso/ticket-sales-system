import type { Money } from '@/models/shared/MoneyModel'

/**
 * Adds money exactly — this layer's bcadd.
 *
 * Works on integer cents via BigInt, never on floats: `0.07 * 100` is already 7.000000000000001,
 * and adding revenue as floats is the silent bug that only shows up when the books are closed.
 * The backend accumulates with bcmath for the same reason; the panel must not undo it when it
 * totals the events up.
 *
 * Kept apart from `formatMoney.ts` on purpose: that file turns money into text for a human, this
 * one is arithmetic the figures depend on. Mixing them invites reaching for `toNumber` to sum.
 */
export function sumMoney(values: Iterable<Money>): Money {
  let cents = 0n

  for (const value of values) {
    cents += toCents(value)
  }

  return fromCents(cents)
}

/**
 * Parses a decimal string into integer cents, by string surgery rather than arithmetic.
 */
function toCents(value: Money): bigint {
  const [whole = '0', fraction = ''] = value.trim().split('.')
  const negative = whole.startsWith('-')
  const digits = whole.replace('-', '').padStart(1, '0') + `${fraction}00`.slice(0, 2)
  const cents = BigInt(digits || '0')

  return negative ? -cents : cents
}

/**
 * Renders integer cents back as the decimal string the domain carries.
 */
function fromCents(cents: bigint): Money {
  const negative = cents < 0n
  const absolute = negative ? -cents : cents
  const fraction = (absolute % 100n).toString().padStart(2, '0')

  return `${negative ? '-' : ''}${absolute / 100n}.${fraction}`
}
