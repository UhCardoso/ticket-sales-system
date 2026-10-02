import { onScopeDispose, ref, type Ref } from 'vue'

/**
 * A clock that ticks, so a relative time on screen ages on its own.
 *
 * Without it "atualizado há 3s" would freeze at whatever it said when the data arrived, which
 * is worse than no timestamp: it would look fresh exactly when the panel had gone stale.
 *
 * @param intervalMs How often the clock advances
 */
export function useNow(intervalMs = 1_000): Ref<Date> {
  const now = ref(new Date())
  const timer = setInterval(() => {
    now.value = new Date()
  }, intervalMs)

  onScopeDispose(() => clearInterval(timer))

  return now
}
