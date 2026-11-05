<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { reportApi } from '@/api/endpoints'
import StateBlock from '@/components/StateBlock.vue'
import { formatCurrency, formatDateTime } from '@/lib/format'

const { data, isLoading, error, refetch } = useQuery({ queryKey: ['dashboard'], queryFn: reportApi.dashboard })
</script>

<template>
  <section>
    <h1 class="mb-4 text-2xl font-semibold">Dashboard</h1>
    <StateBlock :loading="isLoading" :error="error" @retry="refetch()">
      <div v-if="data" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card"><p class="text-sm text-slate-500">Order hari ini</p><p class="text-2xl font-semibold">{{ data.today_orders }}</p></div>
        <div class="card"><p class="text-sm text-slate-500">Omzet hari ini</p><p class="text-2xl font-semibold">{{ formatCurrency(data.today_revenue) }}</p></div>
        <div class="card"><p class="text-sm text-slate-500">Order PENDING</p><p class="text-2xl font-semibold">{{ data.pending_orders }}</p></div>
        <div class="card"><p class="text-sm text-slate-500">Omzet 30 hari</p><p class="text-2xl font-semibold">{{ formatCurrency(data.revenue_last_30_days) }}</p></div>
        <div class="card"><p class="text-sm text-slate-500">Total produk</p><p class="text-2xl font-semibold">{{ data.total_products }}</p></div>
        <RouterLink :to="{ name: 'products', query: { low_stock: '1' } }" class="card hover:border-red-300">
          <p class="text-sm text-slate-500">Stok menipis</p><p class="text-2xl font-semibold text-red-700">{{ data.low_stock_products }}</p>
        </RouterLink>
        <div class="card"><p class="text-sm text-slate-500">Total pelanggan</p><p class="text-2xl font-semibold">{{ data.total_customers }}</p></div>
      </div>
      <p v-if="data" class="mt-3 text-xs text-slate-500">Data cache dibuat: {{ formatDateTime(data.generated_at) }}</p>
    </StateBlock>
  </section>
</template>
