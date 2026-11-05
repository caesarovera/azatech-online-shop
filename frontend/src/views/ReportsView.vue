<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Tooltip } from 'chart.js'
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import { reportApi } from '@/api/endpoints'
import StateBlock from '@/components/StateBlock.vue'
import { useListQuery } from '@/composables/useListQuery'
import { formatCurrency, isoDate } from '@/lib/format'

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend)

const { str, update } = useListQuery({ sort: '' })
const today = new Date()
const defaultFrom = new Date(today.getTime() - 29 * 86_400_000)

// Filter juga disinkronkan ke URL.
const filters = computed(() => ({
  from: str('from') || isoDate(defaultFrom),
  to: str('to') || isoDate(today),
  group_by: (str('group_by') || 'day') as 'day' | 'month',
}))

const sales = useQuery({ queryKey: ['reports', 'sales', filters], queryFn: () => reportApi.sales(filters.value) })
const top = useQuery({
  queryKey: ['reports', 'top', filters],
  queryFn: () => reportApi.topProducts({ from: filters.value.from, to: filters.value.to, limit: 10 }),
})

const chartData = computed(() => ({
  labels: sales.data.value?.map((r) => r.period) ?? [],
  datasets: [{ label: 'Omzet (Rp)', data: sales.data.value?.map((r) => Number(r.revenue)) ?? [], backgroundColor: '#1d4ed8' }],
}))
const chartOptions = { responsive: true, maintainAspectRatio: false }
</script>

<template>
  <section class="space-y-6">
    <h1 class="text-2xl font-semibold">Laporan penjualan</h1>
    <div class="card flex flex-wrap items-end gap-3">
      <div><label class="label" for="r-from">Dari</label><input id="r-from" type="date" class="input" :value="filters.from" @change="update({ from: ($event.target as HTMLInputElement).value })"></div>
      <div><label class="label" for="r-to">Sampai</label><input id="r-to" type="date" class="input" :value="filters.to" @change="update({ to: ($event.target as HTMLInputElement).value })"></div>
      <div>
        <label class="label" for="r-group">Kelompok</label>
        <select id="r-group" class="input" :value="filters.group_by" @change="update({ group_by: ($event.target as HTMLSelectElement).value })">
          <option value="day">Per hari</option><option value="month">Per bulan</option>
        </select>
      </div>
    </div>

    <div class="card">
      <h2 class="mb-2 font-semibold">Omzet per {{ filters.group_by === 'day' ? 'hari' : 'bulan' }}</h2>
      <StateBlock :loading="sales.isLoading.value" :error="sales.error.value" :empty="sales.data.value?.length === 0" @retry="sales.refetch()">
        <div class="h-72"><Bar :data="chartData" :options="chartOptions" aria-label="Grafik omzet" role="img" /></div>
        <p class="mt-2 text-sm text-slate-600">
          Total: {{ sales.data.value?.reduce((s, r) => s + r.total_orders, 0) }} order ·
          {{ formatCurrency(sales.data.value?.reduce((s, r) => s + Number(r.revenue), 0) ?? 0) }}
        </p>
      </StateBlock>
    </div>

    <div class="card overflow-x-auto">
      <h2 class="mb-2 font-semibold">Produk terlaris</h2>
      <StateBlock :loading="top.isLoading.value" :error="top.error.value" :empty="top.data.value?.length === 0" @retry="top.refetch()">
        <table class="table">
          <thead><tr><th>#</th><th>Produk</th><th class="text-right">Qty terjual</th><th class="text-right">Omzet</th></tr></thead>
          <tbody class="divide-y">
            <tr v-for="(row, i) in top.data.value" :key="row.product_id">
              <td>{{ i + 1 }}</td><td>{{ row.product_code }} · {{ row.product_name }}</td>
              <td class="text-right">{{ row.total_quantity }}</td><td class="text-right">{{ formatCurrency(row.revenue) }}</td>
            </tr>
          </tbody>
        </table>
      </StateBlock>
    </div>
  </section>
</template>
