<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { parseApiError } from '@/api/client'
import { productApi, type ProductPayload } from '@/api/endpoints'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const id = computed(() => (route.params.id ? Number(route.params.id) : null))

const form = reactive<ProductPayload>({ product_code: '', product_name: '', price: '', low_stock_threshold: 5, stock: 0 })
const errors = ref<Record<string, string[]>>({})
const message = ref<string | null>(null)

const { data: existing } = useQuery({
  queryKey: ['product', id],
  queryFn: () => productApi.get(id.value!),
  enabled: computed(() => id.value !== null),
})

watch(existing, (p) => {
  if (p) Object.assign(form, { product_code: p.product_code, product_name: p.product_name, price: p.price, low_stock_threshold: p.low_stock_threshold })
}, { immediate: true })

const save = useMutation({
  mutationFn: () => {
    if (id.value) {
      const { stock: _ignored, ...rest } = form
      void _ignored
      return productApi.update(id.value, rest)
    }
    return productApi.create(form)
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['products'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    router.push({ name: 'products' })
  },
  onError: (e) => {
    const parsed = parseApiError(e)
    errors.value = parsed.errors
    message.value = parsed.message
  },
})
</script>

<template>
  <section class="max-w-xl">
    <h1 class="mb-4 text-2xl font-semibold">{{ id ? 'Ubah produk' : 'Tambah produk' }}</h1>
    <form class="card space-y-4" @submit.prevent="save.mutate()">
      <div v-if="message" class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">{{ message }}</div>
      <div>
        <label class="label" for="code">Kode produk</label>
        <input id="code" v-model="form.product_code" class="input" required>
        <p v-if="errors.product_code" class="field-error">{{ errors.product_code[0] }}</p>
      </div>
      <div>
        <label class="label" for="name">Nama produk</label>
        <input id="name" v-model="form.product_name" class="input" required>
        <p v-if="errors.product_name" class="field-error">{{ errors.product_name[0] }}</p>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="label" for="price">Harga</label>
          <input id="price" v-model="form.price" type="number" step="0.01" min="0.01" class="input" required>
          <p v-if="errors.price" class="field-error">{{ errors.price[0] }}</p>
        </div>
        <div>
          <label class="label" for="threshold">Batas stok minimum</label>
          <input id="threshold" v-model.number="form.low_stock_threshold" type="number" min="0" class="input">
          <p v-if="errors.low_stock_threshold" class="field-error">{{ errors.low_stock_threshold[0] }}</p>
        </div>
      </div>
      <div v-if="!id">
        <label class="label" for="stock">Stok awal</label>
        <input id="stock" v-model.number="form.stock" type="number" min="0" class="input">
        <p class="mt-1 text-xs text-slate-500">Setelah dibuat, stok hanya bisa diubah lewat Stock Adjustment (tercatat di ledger).</p>
        <p v-if="errors.stock" class="field-error">{{ errors.stock[0] }}</p>
      </div>
      <div class="flex gap-2">
        <button type="submit" class="btn-primary" :disabled="save.isPending.value">Simpan</button>
        <RouterLink :to="{ name: 'products' }" class="btn-secondary">Batal</RouterLink>
      </div>
    </form>
  </section>
</template>
