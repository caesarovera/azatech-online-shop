<script setup lang="ts">
import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'
import { orderApi } from '@/api/endpoints'
import type { OrderStatus } from '@/api/types'
import PaginationBar from '@/components/PaginationBar.vue'
import SortHeader from '@/components/SortHeader.vue'
import StateBlock from '@/components/StateBlock.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { useListQuery } from '@/composables/useListQuery'
import { formatCurrency, formatDateTime } from '@/lib/format'

const statuses: OrderStatus[] = ['PENDING', 'PAID', 'SHIPPED', 'COMPLETED', 'CANCELLED']
const { params, str, update, setPage, toggleSort } = useListQuery({ sort: 'order_date' })

const apiParams = computed(() => ({
  ...params.value,
  status: str('status') || undefined,
  date_from: str('date_from') || undefined,
  date_to: str('date_to') || undefined,
}))

const { data, isLoading, error, refetch } = useQuery({
  queryKey: ['orders', apiParams],
  queryFn: () => orderApi.list(apiParams.value),
  placeholderData: keepPreviousData,
})
</script>

<template>
  <section>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">Order</h1>
      <RouterLink :to="{ name: 'order-create' }" class="btn-primary">+ Buat order</RouterLink>
    </div>

    <div class="mb-3 flex flex-wrap items-end gap-3">
      <div>
        <label class="label" for="f-status">Status</label>
        <select id="f-status" class="input" :value="str('status')" @change="update({ status: ($event.target as HTMLSelectElement).value })">
          <option value="">Semua</option>
          <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>
      <div>
        <label class="label" for="f-from">Dari</label>
        <input id="f-from" type="date" class="input" :value="str('date_from')" @change="update({ date_from: ($event.target as HTMLInputElement).value })">
      </div>
      <div>
        <label class="label" for="f-to">Sampai</label>
        <input id="f-to" type="date" class="input" :value="str('date_to')" @change="update({ date_to: ($event.target as HTMLInputElement).value })">
      </div>
    </div>

    <StateBlock :loading="isLoading" :error="error" :empty="data?.data.length === 0" empty-text="Belum ada order." @retry="refetch()">
      <div class="overflow-x-auto rounded-lg border bg-white">
        <table class="table">
          <thead>
            <tr>
              <SortHeader column="order_number" label="No. Order" :sort="params.sort" :direction="params.direction" @toggle="toggleSort" />
              <SortHeader column="order_date" label="Tanggal" :sort="params.sort" :direction="params.direction" @toggle="toggleSort" />
              <th>Pelanggan</th><th>Item</th>
              <SortHeader column="total_amount" label="Total" :sort="params.sort" :direction="params.direction" @toggle="toggleSort" />
              <SortHeader column="status" label="Status" :sort="params.sort" :direction="params.direction" @toggle="toggleSort" />
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="o in data?.data" :key="o.id">
              <td><RouterLink :to="{ name: 'order-detail', params: { id: o.id } }" class="font-mono text-blue-700 hover:underline">{{ o.order_number }}</RouterLink></td>
              <td>{{ formatDateTime(o.order_date) }}</td>
              <td>{{ o.customer?.customer_name }}</td>
              <td>{{ o.items_count }}</td>
              <td>{{ formatCurrency(o.total_amount) }}</td>
              <td><StatusBadge :status="o.status" /></td>
            </tr>
          </tbody>
        </table>
      </div>
      <PaginationBar :meta="data?.meta" @change="setPage" />
    </StateBlock>
  </section>
</template>
