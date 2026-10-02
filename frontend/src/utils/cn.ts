import type { ClassValue } from 'clsx'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

/**
 * Merges Tailwind class lists, letting a later class win over an earlier conflicting one.
 *
 * Used by every `components/ui/` primitive so a caller's `class` can override the component's
 * own without `!important`. `components.json` points the shadcn-vue CLI here, so generated
 * components import it from this path.
 */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
