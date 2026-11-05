<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import type { Customer, Product } from '@/api/types'
import { formatCurrency, toCents } from '@/lib/format'
import { orderSchema, zodErrorsToMap, type OrderFormValues } from '@/lib/orderSchema'

/**
 * Form order murni (presentational): tidak memanggil API sendiri.
 * Parent (OrderCreateView) yang mengirim ke server, lalu meneruskan error server via prop.
 * Dengan begitu komponen ini mudah di-test tanpa mock HTTP.
 */
const props = defineProps<{
  customers: Customer[]
  products: Product[]
  submitting: boolean
  serverErrors?: Record<string, string[]>
  serverMessage?: string | null
}>()

const emit = defineEmits<{ submit: [values: OrderFormValues] }>()

interface Row { key: number; product_id: number | null; quantity: number }
let nextKey = 1
const customerId = ref('')
const rows = reactive<Row[]>([{ key: nextKey++, product_id: null, quantity: 1 }])
const clientErrors = ref<Record<string, string>>({})

const productMap = computed(() => new Map(props.products.map((p) => [p.id, p])))

function rowSubtotalCents(row: Row): number {
  const product = row.product_id ? productMap.value.get(row.product_id) : undefined
  return product ? toCents(product.price) * (Number(row.quantity) || 0) : 0
}

// Total real-time (perkiraan). Total final selalu dihitung ulang oleh server.
const totalCents = computed(() => rows.reduce((sum, row) => sum + rowSubtotalCents(row), 0))

function addRow(): void {
  rows.push({ key: nextKey++, product_id: null, quantity: 1 })
}

function removeRow(index: number): void {
  if (rows.length > 1) rows.splice(index, 1)
}

function errorFor(path: string): string | undefined {
  return clientErrors.value[path] ?? props.serverErrors?.[path]?.[0]
}

function submit(): void {
  if (props.submitting) return // cegah double submit (selain tombol disabled)

  const parsed = orderSchema.safeParse({
    customer_id: customerId.value,
    items: rows.map((r) => ({ product_id: r.product_id ?? 0, quantity: Number(r.quantity) })),
  })

  if (!parsed.success) {
    clientErrors.value = zodErrorsToMap(parsed.error)
    return
  }

  clientErrors.value = {}
  emit('submit', parsed.data)
}
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="submit">
    <div v-if="serverMessage" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
      {{ serverMessage }}
    </div>

    <div>
      <label class="label" for="customer">Pelanggan</label>
      <select id="customer" v-model="customerId" class="input" :aria-invalid="!!errorFor('customer_id')" aria-describedby="customer-error">
        <option value="">— Pilih pelanggan —</option>
        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.customer_name }}</option>
      </select>
      <p v-if="errorFor('customer_id')" id="customer-error" class="field-error">{{ errorFor('customer_id') }}</p>
    </div>

    <fieldset class="space-y-3">
      <legend class="label">Item</legend>
      <div v-for="(row, index) in rows" :key="row.key" class="grid grid-cols-12 items-start gap-2" data-testid="order-row">
        <div class="col-span-12 sm:col-span-6">
          <label class="sr-only" :for="`product-${row.key}`">Produk baris {{ index + 1 }}</label>
          <select :id="`product-${row.key}`" v-model.number="row.product_id" class="input" :aria-invalid="!!errorFor(`items.${index}.product_id`)">
            <option :value="null">— Pilih produk —</option>
            <option v-for="p in products" :key="p.id" :value="p.id" :disabled="p.stock === 0">
              {{ p.product_code }} · {{ p.product_name }} (stok {{ p.stock }}) — {{ formatCurrency(p.price) }}
            </option>
          </select>
          <p v-if="errorFor(`items.${index}.product_id`)" class="field-error">{{ errorFor(`items.${index}.product_id`) }}</p>
        </div>
        <div class="col-span-5 sm:col-span-2">
          <label class="sr-only" :for="`qty-${row.key}`">Jumlah baris {{ index + 1 }}</label>
          <input :id="`qty-${row.key}`" v-model.number="row.quantity" type="number" min="1" class="input" :aria-invalid="!!errorFor(`items.${index}.quantity`)">
          <p v-if="errorFor(`items.${index}.quantity`)" class="field-error" data-testid="quantity-error">{{ errorFor(`items.${index}.quantity`) }}</p>
        </div>
        <div class="col-span-5 py-2 text-right sm:col-span-3" data-testid="row-subtotal">{{ formatCurrency(rowSubtotalCents(row) / 100) }}</div>
        <div class="col-span-2 sm:col-span-1">
          <button type="button" class="btn-secondary w-full" :disabled="rows.length === 1" :aria-label="`Hapus baris ${index + 1}`" @click="removeRow(index)">✕</button>
        </div>
      </div>
      <p v-if="errorFor('items')" class="field-error">{{ errorFor('items') }}</p>
      <button type="button" class="btn-secondary" @click="addRow">+ Tambah item</button>
    </fieldset>

    <div class="flex items-center justify-between border-t pt-4">
      <p class="text-lg font-semibold">Total: <span data-testid="order-total">{{ formatCurrency(totalCents / 100) }}</span></p>
      <button type="submit" class="btn-primary" :disabled="submitting">{{ submitting ? 'Menyimpan…' : 'Buat Order' }}</button>
    </div>
  </form>
</template>
