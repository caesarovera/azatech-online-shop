import { z } from 'zod'

/** Skema validasi form order (client-side). Validasi final tetap di backend (StoreOrderRequest). */
export const orderItemSchema = z.object({
  product_id: z.number({ message: 'Pilih produk' }).int().positive('Pilih produk'),
  quantity: z.number({ message: 'Isi jumlah' }).int('Jumlah harus bilangan bulat').min(1, 'Minimal 1').max(10000, 'Maksimal 10000'),
})

export const orderSchema = z
  .object({
    customer_id: z.string().uuid('Pilih pelanggan'),
    items: z.array(orderItemSchema).min(1, 'Minimal 1 item'),
  })
  .superRefine((value, ctx) => {
    const seen = new Set<number>()
    value.items.forEach((item, index) => {
      if (seen.has(item.product_id)) {
        ctx.addIssue({ code: 'custom', path: ['items', index, 'product_id'], message: 'Produk sudah dipilih di baris lain' })
      }
      seen.add(item.product_id)
    })
  })

export type OrderFormValues = z.infer<typeof orderSchema>

/** Ubah issue Zod menjadi { 'items.0.quantity': 'pesan' } — format yang sama dengan error backend. */
export function zodErrorsToMap(error: z.ZodError): Record<string, string> {
  const map: Record<string, string> = {}
  for (const issue of error.issues) {
    const key = issue.path.join('.')
    if (!map[key]) map[key] = issue.message
  }
  return map
}
