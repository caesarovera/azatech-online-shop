// Kontrak response API (lihat backend/app/Support/ApiResponse.php)
export interface ApiSuccess<T> {
  success: true
  message: string
  data: T
  meta?: PaginationMeta
}

export interface ApiError {
  success: false
  message: string
  errors?: Record<string, string[]>
}

export interface PaginationMeta {
  page: number
  per_page: number
  total: number
  total_pages: number
}

export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
}

export type Role = 'admin' | 'cashier'

export interface User {
  id: number
  name: string
  email: string
  role: Role
}

export interface Product {
  id: number
  product_code: string
  product_name: string
  stock: number
  price: string
  low_stock_threshold: number
  is_low_stock: boolean
  created_at: string
}

export interface Customer {
  id: string
  customer_name: string
  customer_address: string | null
  gender: 'male' | 'female' | null
  birth_date: string | null
  phone: string | null
  email: string | null
  created_at: string
}

export type OrderStatus = 'PENDING' | 'PAID' | 'SHIPPED' | 'COMPLETED' | 'CANCELLED'

export interface OrderItem {
  id: number
  product_id: number
  product_code: string
  product_name: string
  quantity: number
  unit_price: string
  subtotal: string
}

export interface StatusHistory {
  from_status: OrderStatus | null
  to_status: OrderStatus
  note: string | null
  changed_by: string | null
  created_at: string
}

export interface Order {
  id: number
  order_number: string
  status: OrderStatus
  total_amount: string
  order_date: string
  paid_at: string | null
  shipped_at: string | null
  completed_at: string | null
  cancelled_at: string | null
  cancel_reason: string | null
  customer?: { id: string; customer_name: string; email: string | null }
  created_by?: { id: number; name: string }
  items_count?: number
  items?: OrderItem[]
  status_histories?: StatusHistory[]
  available_actions: OrderStatus[]
}

export interface StockMovement {
  id: number
  type: 'INITIAL' | 'ORDER_OUT' | 'CANCEL_RESTORE' | 'ADJUSTMENT'
  quantity_change: number
  stock_after: number
  reference_type: string | null
  reference_id: string | null
  note: string | null
  created_by: string | null
  created_at: string
}

export interface DashboardSummary {
  today_orders: number
  today_revenue: string
  pending_orders: number
  revenue_last_30_days: string
  total_products: number
  low_stock_products: number
  total_customers: number
  generated_at: string
}

export interface SalesRow {
  period: string
  total_orders: number
  revenue: string
}

export interface TopProductRow {
  product_id: number
  product_code: string
  product_name: string
  total_quantity: number
  revenue: string
}

export interface ListParams {
  page?: number
  per_page?: number
  search?: string
  sort?: string
  direction?: 'asc' | 'desc'
  [key: string]: string | number | boolean | undefined
}
