<script setup lang="ts">
import type { OrderStatus } from '@/api/types'

defineProps<{ status: OrderStatus }>()

const classes: Record<OrderStatus, string> = {
  PENDING: 'bg-amber-100 text-amber-900',
  PAID: 'bg-blue-100 text-blue-900',
  SHIPPED: 'bg-indigo-100 text-indigo-900',
  COMPLETED: 'bg-green-100 text-green-900',
  CANCELLED: 'bg-red-100 text-red-900',
}
</script>

<template>
  <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold" :class="classes[status]">{{ status }}</span>
</template>
