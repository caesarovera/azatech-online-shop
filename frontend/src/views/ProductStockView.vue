<script setup lang="ts">
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { parseApiError } from '@/api/client'
import { productApi } from '@/api/endpoints'
import PaginationBar from '@/components/PaginationBar.vue'
import StateBlock from '@/components/StateBlock.vue'
import { formatDateTime } from '@/lib/format'

const route = useRoute()
const queryClient = useQueryClient()
const id = computed(() => Number(route.params.id))
const page = ref(1)

const { data: product } = useQuery({ queryKey: ['product', id], queryFn: () => productApi.get(id.value) })
const movements = useQuery({
  queryKey: ['stock-movements', id, page],
  queryFn: () => productApi.movements(id.value, { page: page.value, per_page: 15 }),
  placeholderData: keepPreviousData,
})

const quantityChange = ref<number | null>(null)
const note = ref('')
const errors = ref<Record<string, string[]>>({})

const adjust = useMutation({
  mutationFn: () => productApi.adjustStock(id.value, { quantity_change: Number(quantityChange.value), note: note.value }),
  onSuccess: () => {
    quantityChange.value = null
    note.value = ''
    errors.value = {}
    queryClient.invalidateQueries({ queryKey: ['product', id] })
    queryClient.invalidateQueries({ queryKey: ['stock-movements', id] })
    queryClient.invalidateQueries({ queryKey: ['products'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
  },
  onError: (e) => { errors.value = parseApiError(e).errors },
})
</script>

<template>
  <section class="space-y-6">
    <div>
      <RouterLink :to="{ name: 'products' }" class="text-sm text-blue-700 hover:underline">← Produk</RouterLink>
      <h1 class="text-2xl font-semibold">Stok: {{ product?.product_name }}</h1>
      <p class="text-slate-600">Stok saat ini: <strong>{{ product?.stock }}</strong> (minimum {{ product?.low_stock_threshold }})</p>
    </div>

    <form class="card grid gap-3 sm:grid-cols-4" @submit.prevent="adjust.mutate()">
      <div>
        <label class="label" for="qty-change">Perubahan (+/-)</label>
        <input id="qty-change" v-model.number="quantityChange" type="number" class="input" placeholder="mis. 50 atau -3" required>
        <p v-if="errors.quantity_change" class="field-error">{{ errors.quantity_change[0] }}</p>
      </div>
      <div class="sm:col-span-2">
        <label class="label" for="note">Catatan (wajib)</label>
        <input id="note" v-model="note" class="input" placeholder="Stock opname bulanan" required>
        <p v-if="errors.note" class="field-error">{{ errors.note[0] }}</p>
      </div>
      <div class="flex items-end">
        <button type="submit" class="btn-primary w-full" :disabled="adjust.isPending.value">Simpan penyesuaian</button>
      </div>
    </form>

    <div>
      <h2 class="mb-2 text-lg font-semibold">Riwayat pergerakan stok (ledger)</h2>
      <StateBlock :loading="movements.isLoading.value" :error="movements.error.value" :empty="movements.data.value?.data.length === 0" @retry="movements.refetch()">
        <div class="overflow-x-auto rounded-lg border bg-white">
          <table class="table">
            <thead><tr><th>Waktu</th><th>Tipe</th><th>Perubahan</th><th>Stok setelah</th><th>Referensi</th><th>Catatan</th><th>Oleh</th></tr></thead>
            <tbody class="divide-y">
              <tr v-for="m in movements.data.value?.data" :key="m.id">
                <td>{{ formatDateTime(m.created_at) }}</td>
                <td>{{ m.type }}</td>
                <td :class="m.quantity_change < 0 ? 'text-red-700' : 'text-green-700'">{{ m.quantity_change > 0 ? '+' : '' }}{{ m.quantity_change }}</td>
                <td>{{ m.stock_after }}</td>
                <td>
                  <RouterLink v-if="m.reference_type === 'order'" :to="{ name: 'order-detail', params: { id: m.reference_id } }" class="text-blue-700 hover:underline">order #{{ m.reference_id }}</RouterLink>
                  <span v-else>{{ m.reference_type ?? '-' }}</span>
                </td>
                <td>{{ m.note }}</td>
                <td>{{ m.created_by ?? '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <PaginationBar :meta="movements.data.value?.meta" @change="(p) => (page = p)" />
      </StateBlock>
    </div>
  </section>
</template>
