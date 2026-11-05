<script setup lang="ts">
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { parseApiError } from '@/api/client'
import { productApi } from '@/api/endpoints'
import PaginationBar from '@/components/PaginationBar.vue'
import SortHeader from '@/components/SortHeader.vue'
import StateBlock from '@/components/StateBlock.vue'
import { useListQuery } from '@/composables/useListQuery'
import { formatCurrency } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const queryClient = useQueryClient()
const { params, str, update, setPage, toggleSort } = useListQuery({ sort: 'created_at' })

const lowStock = computed(() => str('low_stock') === '1')
const apiParams = computed(() => ({ ...params.value, low_stock: lowStock.value ? 1 : undefined }))

const { data, isLoading, error, refetch } = useQuery({
  queryKey: ['products', apiParams],
  queryFn: () => productApi.list(apiParams.value),
  placeholderData: keepPreviousData,
})

// Input search di-debounce lalu ditulis ke URL.
const search = ref(params.value.search)
let timer: ReturnType<typeof setTimeout> | undefined
watch(search, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => update({ search: value }), 350)
})

const remove = useMutation({
  mutationFn: productApi.remove,
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['products'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
  },
  onError: (e) => alert(parseApiError(e).message),
})

function confirmDelete(id: number, name: string): void {
  if (confirm(`Hapus produk "${name}"?`)) remove.mutate(id)
}
</script>

<template>
  <section>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">Produk</h1>
      <RouterLink v-if="auth.isAdmin" :to="{ name: 'product-create' }" class="btn-primary">+ Tambah produk</RouterLink>
    </div>

    <div class="mb-3 flex flex-wrap items-center gap-3">
      <label class="sr-only" for="product-search">Cari produk</label>
      <input id="product-search" v-model="search" type="search" class="input max-w-xs" placeholder="Cari kode / nama…">
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" :checked="lowStock" @change="update({ low_stock: lowStock ? undefined : '1' })"> Hanya stok menipis
      </label>
    </div>

    <StateBlock :loading="isLoading" :error="error" :empty="data?.data.length === 0" empty-text="Produk tidak ditemukan." @retry="refetch()">
      <div class="overflow-x-auto rounded-lg border bg-white">
        <table class="table">
          <thead>
            <tr>
              <SortHeader column="product_code" label="Kode" :sort="params.sort" :direction="params.direction" @toggle="toggleSort" />
              <SortHeader column="product_name" label="Nama" :sort="params.sort" :direction="params.direction" @toggle="toggleSort" />
              <SortHeader column="price" label="Harga" :sort="params.sort" :direction="params.direction" @toggle="toggleSort" />
              <SortHeader column="stock" label="Stok" :sort="params.sort" :direction="params.direction" @toggle="toggleSort" />
              <th v-if="auth.isAdmin">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="p in data?.data" :key="p.id">
              <td class="font-mono">{{ p.product_code }}</td>
              <td>{{ p.product_name }}</td>
              <td>{{ formatCurrency(p.price) }}</td>
              <td :class="p.is_low_stock ? 'font-semibold text-red-700' : ''">
                {{ p.stock }}<span v-if="p.is_low_stock" class="ml-1 text-xs">(menipis)</span>
              </td>
              <td v-if="auth.isAdmin" class="space-x-2 whitespace-nowrap">
                <RouterLink :to="{ name: 'product-edit', params: { id: p.id } }" class="text-blue-700 hover:underline">Ubah</RouterLink>
                <RouterLink :to="{ name: 'product-stock', params: { id: p.id } }" class="text-blue-700 hover:underline">Stok</RouterLink>
                <button class="text-red-700 hover:underline" @click="confirmDelete(p.id, p.product_name)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <PaginationBar :meta="data?.meta" @change="setPage" />
    </StateBlock>
  </section>
</template>
