<script setup lang="ts">
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { ref, watch } from 'vue'
import { parseApiError } from '@/api/client'
import { customerApi } from '@/api/endpoints'
import PaginationBar from '@/components/PaginationBar.vue'
import SortHeader from '@/components/SortHeader.vue'
import StateBlock from '@/components/StateBlock.vue'
import { useListQuery } from '@/composables/useListQuery'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const queryClient = useQueryClient()
const { params, update, setPage, toggleSort } = useListQuery({ sort: 'created_at' })

const { data, isLoading, error, refetch } = useQuery({
  queryKey: ['customers', params],
  queryFn: () => customerApi.list(params.value),
  placeholderData: keepPreviousData,
})

const search = ref(params.value.search)
let timer: ReturnType<typeof setTimeout> | undefined
watch(search, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => update({ search: value }), 350)
})

const remove = useMutation({
  mutationFn: customerApi.remove,
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ['customers'] }),
  onError: (e) => alert(parseApiError(e).message),
})

function confirmDelete(id: string, name: string): void {
  if (confirm(`Hapus ${name}?`)) remove.mutate(id)
}
</script>

<template>
  <section>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">Pelanggan</h1>
      <RouterLink :to="{ name: 'customer-create' }" class="btn-primary">+ Tambah pelanggan</RouterLink>
    </div>
    <label class="sr-only" for="customer-search">Cari pelanggan</label>
    <input id="customer-search" v-model="search" type="search" class="input mb-3 max-w-xs" placeholder="Cari nama / email / telepon…">

    <StateBlock :loading="isLoading" :error="error" :empty="data?.data.length === 0" empty-text="Pelanggan tidak ditemukan." @retry="refetch()">
      <div class="overflow-x-auto rounded-lg border bg-white">
        <table class="table">
          <thead>
            <tr>
              <SortHeader column="customer_name" label="Nama" :sort="params.sort" :direction="params.direction" @toggle="toggleSort" />
              <SortHeader column="email" label="Email" :sort="params.sort" :direction="params.direction" @toggle="toggleSort" />
              <th>Telepon</th><th>Alamat</th><th>Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="c in data?.data" :key="c.id">
              <td>{{ c.customer_name }}</td>
              <td>{{ c.email ?? '-' }}</td>
              <td>{{ c.phone ?? '-' }}</td>
              <td>{{ c.customer_address ?? '-' }}</td>
              <td class="space-x-2 whitespace-nowrap">
                <RouterLink :to="{ name: 'customer-edit', params: { id: c.id } }" class="text-blue-700 hover:underline">Ubah</RouterLink>
                <!-- Kasir tidak boleh menghapus (backend juga menolak dengan 403) -->
                <button v-if="auth.isAdmin" class="text-red-700 hover:underline" @click="confirmDelete(c.id, c.customer_name)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <PaginationBar :meta="data?.meta" @change="setPage" />
    </StateBlock>
  </section>
</template>
