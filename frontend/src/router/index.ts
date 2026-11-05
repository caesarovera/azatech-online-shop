import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import type { Role } from '@/api/types'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    public?: boolean
    roles?: Role[]
    title?: string
  }
}

const routes: RouteRecordRaw[] = [
  { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { public: true, title: 'Login' } },
  {
    path: '/',
    component: () => import('@/components/AppLayout.vue'),
    children: [
      { path: '', name: 'dashboard', component: () => import('@/views/DashboardView.vue'), meta: { title: 'Dashboard' } },
      { path: 'products', name: 'products', component: () => import('@/views/ProductsView.vue'), meta: { title: 'Produk' } },
      { path: 'products/new', name: 'product-create', component: () => import('@/views/ProductFormView.vue'), meta: { roles: ['admin'], title: 'Tambah Produk' } },
      { path: 'products/:id/edit', name: 'product-edit', component: () => import('@/views/ProductFormView.vue'), meta: { roles: ['admin'], title: 'Ubah Produk' } },
      { path: 'products/:id/stock', name: 'product-stock', component: () => import('@/views/ProductStockView.vue'), meta: { roles: ['admin'], title: 'Stok Produk' } },
      { path: 'customers', name: 'customers', component: () => import('@/views/CustomersView.vue'), meta: { title: 'Pelanggan' } },
      { path: 'customers/new', name: 'customer-create', component: () => import('@/views/CustomerFormView.vue'), meta: { title: 'Tambah Pelanggan' } },
      { path: 'customers/:id/edit', name: 'customer-edit', component: () => import('@/views/CustomerFormView.vue'), meta: { title: 'Ubah Pelanggan' } },
      { path: 'orders', name: 'orders', component: () => import('@/views/OrdersView.vue'), meta: { title: 'Order' } },
      { path: 'orders/new', name: 'order-create', component: () => import('@/views/OrderCreateView.vue'), meta: { title: 'Buat Order' } },
      { path: 'orders/:id', name: 'order-detail', component: () => import('@/views/OrderDetailView.vue'), meta: { title: 'Detail Order' } },
      { path: 'reports', name: 'reports', component: () => import('@/views/ReportsView.vue'), meta: { roles: ['admin'], title: 'Laporan' } },
      { path: 'forbidden', name: 'forbidden', component: () => import('@/views/ForbiddenView.vue'), meta: { title: 'Akses ditolak' } },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

export const router = createRouter({ history: createWebHistory(), routes })

// Route guard: proteksi login + role. (Keamanan sebenarnya tetap di backend Policy.)
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.public) {
    return auth.isAuthenticated && to.name === 'login' ? { name: 'dashboard' } : true
  }

  if (!auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  try {
    await auth.loadUser()
  } catch {
    auth.clear()
    return { name: 'login' }
  }

  if (to.meta.roles && auth.role && !to.meta.roles.includes(auth.role)) {
    return { name: 'forbidden' }
  }

  return true
})

router.afterEach((to) => {
  document.title = `${to.meta.title ?? 'Azatech'} · Azatech Online Shop`
})
