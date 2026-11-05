<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { parseApiError } from '@/api/client'
import { customerApi, orderApi, productApi } from '@/api/endpoints'
import OrderForm from '@/components/OrderForm.vue'
import StateBlock from '@/components/StateBlock.vue'
import type { OrderFormValues } from '@/lib/orderSchema'

const router = useRouter()
const queryClient = useQueryClient()

// Sederhana: ambil 100 data pertama untuk dropdown. (Perbaikan: combobox dengan search server-side.)
const customers = useQuery({ queryKey: ['customers', 'options'], queryFn: () => customerApi.list({ per_page: 100, sort: 'customer_name', direction: 'asc' }) })
const products = useQuery({ queryKey: ['products', 'options'], queryFn: () => productApi.list({ per_page: 100, sort: 'product_name', direction: 'asc' }) })

const serverErrors = ref<Record<string, string[]>>({})
const serverMessage = ref<string | null>(null)

/**
 * Idempotency-Key dibuat SEKALI per percobaan submit. Jika request yang sama di-retry
 * (mis. jaringan putus), key yang sama dipakai → server tidak membuat order ganda.
 * Jika server menolak (422) dan user mengubah form, key baru dibuat di submit berikutnya.
 */
let pendingKey: string | null = null
let pendingPayload: string | null = null

const create = useMutation({
  mutationFn: (values: OrderFormValues) => {
    const payloadJson = JSON.stringify(values)
    if (pendingKey === null || pendingPayload !== payloadJson) {
      pendingKey = crypto.randomUUID()
      pendingPayload = payloadJson
    }
    return orderApi.create(values, pendingKey)
  },
  onSuccess: (order) => {
    pendingKey = null
    // Stok berubah setelah order → invalidate cache produk, order, dashboard.
    queryClient.invalidateQueries({ queryKey: ['products'] })
    queryClient.invalidateQueries({ queryKey: ['orders'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    router.push({ name: 'order-detail', params: { id: order.id } })
  },
  onError: (e) => {
    const parsed = parseApiError(e)
    serverErrors.value = parsed.errors
    serverMessage.value = parsed.message
    if (parsed.status !== undefined && parsed.status < 500) pendingKey = null
    queryClient.invalidateQueries({ queryKey: ['products', 'options'] }) // stok terbaru
  },
})
</script>

<template>
  <section class="max-w-4xl">
    <h1 class="mb-4 text-2xl font-semibold">Buat order</h1>
    <div class="card">
      <StateBlock :loading="customers.isLoading.value || products.isLoading.value" :error="customers.error.value || products.error.value">
        <OrderForm
          :customers="customers.data.value?.data ?? []"
          :products="products.data.value?.data ?? []"
          :submitting="create.isPending.value"
          :server-errors="serverErrors"
          :server-message="serverMessage"
          @submit="create.mutate"
        />
      </StateBlock>
    </div>
  </section>
</template>
