<script setup lang="ts">
import { computed } from 'vue'
import { Pause } from '@lucide/vue'
import { useNow } from '@/composables/useNow'

/**
 * Says how old the numbers on screen are.
 *
 * The timestamp comes from the backend's cached snapshot, not from when the request returned,
 * so this reports the real age of the data instead of implying it is live. It is announced
 * politely rather than assertively: a panel that interrupts a screen reader every few seconds
 * is unusable.
 */
const props = defineProps<{
  generatedAt: Date | null
  /** False while the tab is hidden and polling is paused. */
  polling: boolean
  refreshing: boolean
}>()

const now = useNow()

const secondsAgo = computed(() => {
  if (!props.generatedAt) {
    return null
  }

  return Math.max(0, Math.round((now.value.getTime() - props.generatedAt.getTime()) / 1000))
})

const text = computed(() => {
  if (secondsAgo.value === null) {
    return 'carregando…'
  }

  if (secondsAgo.value < 5) {
    return 'atualizado agora'
  }

  if (secondsAgo.value < 60) {
    return `atualizado há ${secondsAgo.value}s`
  }

  const minutes = Math.floor(secondsAgo.value / 60)

  return `atualizado há ${minutes} min`
})

/** Past a minute the panel stops looking healthy, whatever the dot is doing. */
const stale = computed(() => (secondsAgo.value ?? 0) >= 60)
</script>

<template>
  <p
    class="text-muted-foreground flex items-center gap-1.5 text-xs"
    role="status"
    aria-live="polite"
  >
    <Pause v-if="!polling" class="size-3" aria-hidden="true" />
    <span
      v-else
      class="size-1.5 shrink-0 rounded-full"
      :class="[
        stale ? 'bg-muted-foreground' : 'bg-sold',
        refreshing ? 'animate-pulse motion-reduce:animate-none' : '',
      ]"
      aria-hidden="true"
    />
    {{ polling ? text : `pausado · ${text}` }}
  </p>
</template>
