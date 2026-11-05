import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import type { Customer, Product } from '@/api/types'
import OrderForm from '../OrderForm.vue'

const customers: Customer[] = [
  { id: '9d2f6c1e-0000-4000-8000-000000000001', customer_name: 'Budi Santoso', customer_address: 'Jakarta', gender: 'male', birth_date: null, phone: null, email: null, created_at: '' },
]

const products: Product[] = [
  { id: 1, product_code: 'P0001', product_name: 'Hydrococo 150ml', stock: 10, price: '12000.00', low_stock_threshold: 5, is_low_stock: false, created_at: '' },
  { id: 3, product_code: 'P0003', product_name: 'Teh Botol', stock: 3, price: '5000.50', low_stock_threshold: 5, is_low_stock: true, created_at: '' },
]

function factory(props: Partial<{ submitting: boolean; serverErrors: Record<string, string[]>; serverMessage: string }> = {}) {
  return mount(OrderForm, { props: { customers, products, submitting: false, ...props } })
}

describe('OrderForm', () => {
  it('menghitung total secara real-time dari beberapa baris', async () => {
    const wrapper = factory()
    await wrapper.find('select#product-1').setValue(1)
    await wrapper.find('input#qty-1').setValue(2)

    await wrapper.find('button[type="button"]:not([aria-label])').trigger('click') // + Tambah item
    const rows = wrapper.findAll('[data-testid="order-row"]')
    expect(rows).toHaveLength(2)
    await rows[1]!.find('select').setValue(3)
    await rows[1]!.find('input').setValue(2)

    // 2 × 12.000 + 2 × 5.000,50 = 34.001
    expect(wrapper.get('[data-testid="order-total"]').text()).toContain('34.001')
  })

  it('menampilkan error validasi Zod dan tidak emit submit jika form tidak valid', async () => {
    const wrapper = factory()
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')).toBeUndefined()
    expect(wrapper.text()).toContain('Pilih pelanggan')
    expect(wrapper.text()).toContain('Pilih produk')
  })

  it('menolak produk yang sama di dua baris', async () => {
    const wrapper = factory()
    await wrapper.find('select#customer').setValue(customers[0]!.id)
    await wrapper.find('select#product-1').setValue(1)
    await wrapper.find('button[type="button"]:not([aria-label])').trigger('click')
    await wrapper.findAll('[data-testid="order-row"]')[1]!.find('select').setValue(1)
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')).toBeUndefined()
    expect(wrapper.text()).toContain('Produk sudah dipilih di baris lain')
  })

  it('emit payload yang valid (tanpa harga — harga dihitung server)', async () => {
    const wrapper = factory()
    await wrapper.find('select#customer').setValue(customers[0]!.id)
    await wrapper.find('select#product-1').setValue(1)
    await wrapper.find('input#qty-1').setValue(3)
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')?.[0]?.[0]).toEqual({
      customer_id: customers[0]!.id,
      items: [{ product_id: 1, quantity: 3 }],
    })
  })

  it('menampilkan error server per item', () => {
    const wrapper = factory({
      serverMessage: 'Stok tidak mencukupi',
      serverErrors: { 'items.0.quantity': ['Stok Hydrococo 150ml tersisa 3, diminta 5'] },
    })
    expect(wrapper.get('[role="alert"]').text()).toBe('Stok tidak mencukupi')
    expect(wrapper.get('[data-testid="quantity-error"]').text()).toContain('tersisa 3, diminta 5')
  })

  it('mencegah double submit saat request sedang berjalan', async () => {
    const wrapper = factory({ submitting: true })
    const button = wrapper.get('button[type="submit"]')
    expect(button.attributes('disabled')).toBeDefined()
    await wrapper.find('form').trigger('submit')
    expect(wrapper.emitted('submit')).toBeUndefined()
  })
})
