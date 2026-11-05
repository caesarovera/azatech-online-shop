import { http } from './client'
import type {
  ApiSuccess, Customer, DashboardSummary, ListParams, Order, Paginated, Product,
  SalesRow, StockMovement, TopProductRow, User,
} from './types'

function cleanParams(params: ListParams): ListParams {
  return Object.fromEntries(Object.entries(params).filter(([, v]) => v !== undefined && v !== '')) as ListParams
}

async function list<T>(url: string, params: ListParams): Promise<Paginated<T>> {
  const { data } = await http.get<ApiSuccess<T[]>>(url, { params: cleanParams(params) })
  return { data: data.data, meta: data.meta! }
}

export const authApi = {
  async login(email: string, password: string) {
    const { data } = await http.post<ApiSuccess<{ token: string; expires_at: string; user: User }>>('/auth/login', { email, password })
    return data.data
  },
  async logout() {
    await http.post('/auth/logout')
  },
  async me() {
    const { data } = await http.get<ApiSuccess<User>>('/auth/me')
    return data.data
  },
}

export type ProductPayload = Pick<Product, 'product_code' | 'product_name' | 'price' | 'low_stock_threshold'> & { stock?: number }
export type CustomerPayload = Omit<Customer, 'id' | 'created_at'>

export const productApi = {
  list: (params: ListParams) => list<Product>('/products', params),
  get: async (id: number) => (await http.get<ApiSuccess<Product>>(`/products/${id}`)).data.data,
  create: async (payload: ProductPayload) => (await http.post<ApiSuccess<Product>>('/products', payload)).data.data,
  update: async (id: number, payload: Partial<ProductPayload>) => (await http.put<ApiSuccess<Product>>(`/products/${id}`, payload)).data.data,
  remove: async (id: number) => { await http.delete(`/products/${id}`) },
  adjustStock: async (id: number, payload: { quantity_change: number; note: string }) =>
    (await http.post<ApiSuccess<{ product: Product; movement: StockMovement }>>(`/products/${id}/stock-adjustments`, payload)).data.data,
  movements: (id: number, params: ListParams) => list<StockMovement>(`/products/${id}/stock-movements`, params),
}

export const customerApi = {
  list: (params: ListParams) => list<Customer>('/customers', params),
  get: async (id: string) => (await http.get<ApiSuccess<Customer>>(`/customers/${id}`)).data.data,
  create: async (payload: Partial<CustomerPayload>) => (await http.post<ApiSuccess<Customer>>('/customers', payload)).data.data,
  update: async (id: string, payload: Partial<CustomerPayload>) => (await http.put<ApiSuccess<Customer>>(`/customers/${id}`, payload)).data.data,
  remove: async (id: string) => { await http.delete(`/customers/${id}`) },
}

export interface CreateOrderPayload {
  customer_id: string
  items: { product_id: number; quantity: number }[]
}

export const orderApi = {
  list: (params: ListParams) => list<Order>('/orders', params),
  get: async (id: number) => (await http.get<ApiSuccess<Order>>(`/orders/${id}`)).data.data,
  create: async (payload: CreateOrderPayload, idempotencyKey: string) =>
    (await http.post<ApiSuccess<Order>>('/orders', payload, { headers: { 'Idempotency-Key': idempotencyKey } })).data.data,
  transition: async (id: number, action: 'pay' | 'ship' | 'complete') =>
    (await http.post<ApiSuccess<Order>>(`/orders/${id}/${action}`)).data.data,
  cancel: async (id: number, cancel_reason: string) =>
    (await http.post<ApiSuccess<Order>>(`/orders/${id}/cancel`, { cancel_reason })).data.data,
  async downloadInvoice(order: Order) {
    const { data } = await http.get<Blob>(`/orders/${order.id}/invoice`, { responseType: 'blob' })
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = `invoice-${order.order_number}.html`
    a.click()
    URL.revokeObjectURL(url)
  },
}

export const reportApi = {
  dashboard: async () => (await http.get<ApiSuccess<DashboardSummary>>('/dashboard/summary')).data.data,
  sales: async (params: { from: string; to: string; group_by: 'day' | 'month' }) =>
    (await http.get<ApiSuccess<SalesRow[]>>('/reports/sales', { params })).data.data,
  topProducts: async (params: { from: string; to: string; limit: number }) =>
    (await http.get<ApiSuccess<TopProductRow[]>>('/reports/top-products', { params })).data.data,
}
