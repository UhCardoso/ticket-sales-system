<script setup lang="ts">
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

/**
 * Stands in for the panel until the first reading arrives — and only then.
 *
 * Later polls update the numbers in place. Showing this every few seconds would turn a panel
 * meant to be left open into a strobe.
 */
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <Card v-for="tile in 4" :key="tile">
        <CardContent class="space-y-2">
          <Skeleton class="h-3 w-24" />
          <Skeleton class="h-7 w-20" />
        </CardContent>
      </Card>
    </div>

    <Card v-for="event in 2" :key="event">
      <CardHeader class="space-y-2">
        <Skeleton class="h-5 w-48" />
        <Skeleton class="h-3 w-36" />
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <Skeleton v-for="figure in 4" :key="figure" class="h-10" />
        </div>
        <Skeleton class="h-2 w-full rounded-full" />
        <Skeleton class="h-24 w-full" />
      </CardContent>
    </Card>
  </div>
</template>
