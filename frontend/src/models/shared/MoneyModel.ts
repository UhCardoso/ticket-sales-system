/**
 * Money as the domain carries it: the fixed-point decimal string the API sends, e.g. "1250.00".
 *
 * Kept as a string on purpose. The backend accumulates revenue with bcmath precisely so cents
 * are never lost to a float, and parsing it into a number on arrival would undo that.
 *
 * It lives in `models/shared/` because it is not one endpoint's contract: every model that
 * carries a price or a revenue figure reuses it. Formatting lives in `utils/formatMoney.ts` and
 * exact addition in `utils/sumMoney.ts`; this is only the shape.
 */
export type Money = string
