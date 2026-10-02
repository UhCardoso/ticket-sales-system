import { onScopeDispose, ref, type Ref } from 'vue'

export interface UsePollingOptions {
  /** Milliseconds between runs. */
  interval: number
  /** Run once as soon as polling starts, instead of waiting out the first interval. */
  immediate?: boolean
}

export interface UsePolling {
  /** Whether the timer is running — false while the tab is hidden. */
  active: Ref<boolean>
  start: () => void
  stop: () => void
  /** Runs the task now and restarts the interval from this moment. */
  trigger: () => Promise<void>
}

/**
 * Runs a task on an interval, pausing whenever the tab is not visible.
 *
 * Two behaviours matter for a panel left open all day. The tab going hidden stops the timer,
 * so thirty idle dashboards stop asking; coming back runs the task at once rather than showing
 * stale numbers until the next tick. And a run that is still in flight suppresses the next
 * tick instead of stacking on top of it, so a slow response cannot pile up requests.
 *
 * @param task What to run on each tick; its rejection is swallowed, the caller reports it
 */
export function usePolling(task: () => Promise<void>, options: UsePollingOptions): UsePolling {
  const active = ref(false)

  let timer: ReturnType<typeof setInterval> | undefined
  let running = false

  async function run(): Promise<void> {
    if (running) {
      return
    }

    running = true

    try {
      await task()
    } catch {
      // O chamador já expõe o erro; aqui ele só não pode derrubar o timer.
    } finally {
      running = false
    }
  }

  function startTimer(): void {
    stopTimer()
    timer = setInterval(() => void run(), options.interval)
    active.value = true
  }

  function stopTimer(): void {
    if (timer !== undefined) {
      clearInterval(timer)
      timer = undefined
    }

    active.value = false
  }

  function start(): void {
    if (document.visibilityState === 'hidden') {
      return
    }

    startTimer()

    if (options.immediate) {
      void run()
    }
  }

  async function trigger(): Promise<void> {
    await run()

    if (active.value) {
      startTimer()
    }
  }

  function onVisibilityChange(): void {
    if (document.visibilityState === 'hidden') {
      stopTimer()

      return
    }

    startTimer()
    void run()
  }

  document.addEventListener('visibilitychange', onVisibilityChange)

  onScopeDispose(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange)
    stopTimer()
  })

  return { active, start, stop: stopTimer, trigger }
}
