<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { parseApiError } from '@/api/client'
import { orderApi } from '@/api/endpoints'
import type { OrderStatus } from '@/api/types'
import StateBlock from '@/components/StateBlock.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { formatCurrency, formatDateTime } from '@/lib/format'

const route = useRoute()
const queryClient = useQueryClient()
const id = computed(() => Number(route.params.id))

const { data: order, isLoading, error, refetch } = useQuery({ queryKey: ['order', id], queryFn: () => orderApi.get(id.value) })

const actionError = ref<string | null>(null)
const showCancel = ref(false)
const cancelReason = ref('')

const actionLabels: Partial<Record<OrderStatus, { label: string; action: 'pay' | 'ship' | 'complete' }>> = {
  PAID: { label: 'Tandai PAID', action: 'pay' },
  SHIPPED: { label: 'Kirim (SHIPPED)', action: 'ship' },
  COMPLETED: { label: 'Selesaikan (COMPLETED)', action: 'complete' },
}

function afterChange(): void {
  actionError.value = null
  showCancel.value = false
  cancelReason.value = ''
  queryClient.invalidateQueries({ queryKey: ['order', id] })
  queryClient.invalidateQueries({ queryKey: ['orders'] })
  queryClient.invalidateQueries({ queryKey: ['products'] })
  queryClient.invalidateQueries({ queryKey: ['dashboard'] })
}

const transition = useMutation({
  mutationFn: (action: 'pay' | 'ship' | 'complete') => orderApi.transition(id.value, action),
  onSuccess: afterChange,
  onError: (e) => { actionError.value = parseApiError(e).message; refetch() },
})

const cancel = useMutation({
  mutationFn: () => orderApi.cancel(id.value, cancelReason.value),
  onSuccess: afterChange,
  onError: (e) => { actionError.value = parseApiError(e).message; refetch() },
})

const busy = computed(() => transition.isPending.value || cancel.isPending.value)
const canDownloadInvoice = computed(() => order.value && ['PAID', 'SHIPPED', 'COMPLETED'].includes(order.value.status))
</script>

<template>
  <section>
    <RouterLink :to="{ name: 'orders' }" class="text-sm text-blue-700 hover:underline">← Order</RouterLink>
    <StateBlock :loading="isLoading" :error="error" @retry="refetch()">
      <div v-if="order" class="mt-2 grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
          <div class="card">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h1 class="font-mono text-xl font-semibold">{{ order.order_number }}</h1>
              <StatusBadge :status="order.status" />
            </div>
            <p class="mt-1 text-sm text-slate-600">
              {{ formatDateTime(order.order_date) }} · Pelanggan: {{ order.customer?.customer_name }} · Dibuat oleh: {{ order.created_by?.name }}
            </p>
            <p v-if="order.cancel_reason" class="mt-2 text-sm text-red-700">Alasan batal: {{ order.cancel_reason }}</p>
          </div>

          <div class="card overflow-x-auto">
            <table class="table">
              <thead><tr><th>Produk</th><th class="text-right">Qty</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th></tr></thead>
              <tbody class="divide-y">
                <tr v-for="item in order.items" :key="item.id">
                  <td>{{ item.product_code }} · {{ item.product_name }}</td>
                  <td class="text-right">{{ item.quantity }}</td>
                  <td class="text-right">{{ formatCurrency(item.unit_price) }}</td>
                  <td class="text-right">{{ formatCurrency(item.subtotal) }}</td>
                </tr>
              </tbody>
              <tfoot><tr><th colspan="3" class="text-right">Total</th><th class="text-right">{{ formatCurrency(order.total_amount) }}</th></tr></tfoot>
            </table>
          </div>
        </div>

        <aside class="space-y-4">
          <div class="card space-y-2">
            <h2 class="font-semibold">Aksi</h2>
            <div v-if="actionError" class="rounded bg-red-50 p-2 text-sm text-red-800" role="alert">{{ actionError }}</div>
            <!-- Tombol dari available_actions yang dihitung server (status + role) -->
            <template v-for="status in order.available_actions" :key="status">
              <button v-if="actionLabels[status]" class="btn-primary w-full" :disabled="busy" @click="transition.mutate(actionLabels[status]!.action)">
                {{ actionLabels[status]!.label }}
              </button>
            </template>
            <button v-if="order.available_actions.includes('CANCELLED') && !showCancel" class="btn-danger w-full" :disabled="busy" @click="showCancel = true">
              Batalkan order
            </button>
            <form v-if="showCancel" class="space-y-2" @submit.prevent="cancel.mutate()">
              <label class="label" for="cancel-reason">Alasan pembatalan</label>
              <textarea id="cancel-reason" v-model="cancelReason" class="input" rows="2" required minlength="3" />
              <div class="flex gap-2">
                <button type="submit" class="btn-danger" :disabled="busy || cancelReason.trim().length < 3">Konfirmasi batal</button>
                <button type="button" class="btn-secondary" @click="showCancel = false">Tutup</button>
              </div>
            </form>
            <button v-if="canDownloadInvoice" class="btn-secondary w-full" @click="orderApi.downloadInvoice(order)">Unduh invoice</button>
            <p v-if="order.available_actions.length === 0 && !canDownloadInvoice" class="text-sm text-slate-500">Tidak ada aksi tersedia.</p>
          </div>

          <div class="card">
            <h2 class="mb-2 font-semibold">Timeline status</h2>
            <ol class="relative space-y-3 border-l border-slate-300 pl-4">
              <li v-for="(h, i) in order.status_histories" :key="i">
                <span class="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full bg-blue-600" aria-hidden="true" />
                <p class="text-sm font-medium">{{ h.from_status ?? '—' }} → {{ h.to_status }}</p>
                <p class="text-xs text-slate-500">{{ formatDateTime(h.created_at) }} · {{ h.changed_by }}</p>
                <p v-if="h.note" class="text-xs text-slate-600">{{ h.note }}</p>
              </li>
            </ol>
          </div>
        </aside>
      </div>
    </StateBlock>
  </section>
</template>
