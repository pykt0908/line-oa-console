import { onMounted, onUnmounted, ref, watch, type Ref } from 'vue'

type PollFn = () => void | Promise<void>

export function useAdaptivePolling(
  fn: PollFn,
  options: {
    activeMs?: number
    idleMs?: number
    hiddenMs?: number
    enabled?: Ref<boolean>
  } = {},
) {
  const activeMs = options.activeMs ?? 3000
  const idleMs = options.idleMs ?? 5000
  const hiddenMs = options.hiddenMs ?? 25000
  const enabled = options.enabled
  const timer = ref<number | null>(null)

  function delay() {
    if (document.hidden) return hiddenMs
    return activeMs
  }

  async function tick() {
    if (enabled && !enabled.value) {
      schedule()
      return
    }
    try {
      await fn()
    } finally {
      schedule()
    }
  }

  function schedule(ms?: number) {
    if (timer.value) window.clearTimeout(timer.value)
    timer.value = window.setTimeout(tick, ms ?? delay())
  }

  function onVisibility() {
    if (!document.hidden) {
      void tick()
    } else {
      schedule(hiddenMs)
    }
  }

  onMounted(() => {
    schedule(idleMs)
    document.addEventListener('visibilitychange', onVisibility)
  })

  onUnmounted(() => {
    if (timer.value) window.clearTimeout(timer.value)
    document.removeEventListener('visibilitychange', onVisibility)
  })

  if (enabled) {
    watch(enabled, (v) => {
      if (v) void tick()
    })
  }

  return { refreshNow: () => tick() }
}
