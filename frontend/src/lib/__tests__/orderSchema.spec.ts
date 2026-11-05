import { describe, expect, it } from 'vitest'
import { orderSchema, zodErrorsToMap } from '../orderSchema'

describe('orderSchema', () => {
  it('menerima payload valid', () => {
    const result = orderSchema.safeParse({
      customer_id: '9d2f6c1e-0000-4000-8000-000000000001',
      items: [{ product_id: 1, quantity: 2 }],
    })
    expect(result.success).toBe(true)
  })

  it('memetakan error ke path items.N.field (format sama dengan backend)', () => {
    const result = orderSchema.safeParse({ customer_id: 'x', items: [{ product_id: 1, quantity: 0 }] })
    expect(result.success).toBe(false)
    if (!result.success) {
      const map = zodErrorsToMap(result.error)
      expect(map['customer_id']).toBe('Pilih pelanggan')
      expect(map['items.0.quantity']).toBe('Minimal 1')
    }
  })
})
