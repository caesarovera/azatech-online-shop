<script setup lang="ts">
import { useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { Role } from '@/api/types'

const auth = useAuthStore()
const router = useRouter()
const queryClient = useQueryClient()
const mobileOpen = ref(false)

// Menu berbasis role. Backend tetap menolak (403) jika role tidak berhak.
const menu: { to: string; label: string; roles?: Role[] }[] = [
  { to: '/', label: 'Dashboard' },
  { to: '/orders', label: 'Order' },
  { to: '/products', label: 'Produk' },
  { to: '/customers', label: 'Pelanggan' },
  { to: '/reports', label: 'Laporan', roles: ['admin'] },
]

const visibleMenu = computed(() => menu.filter((m) => !m.roles || (auth.role && m.roles.includes(auth.role))))

async function logout(): Promise<void> {
  await auth.logout()
  queryClient.clear()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-slate-100">
    <header class="bg-slate-900 text-white">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
        <RouterLink to="/" class="font-semibold">Azatech Shop</RouterLink>
        <button class="md:hidden btn text-white" :aria-expanded="mobileOpen" aria-controls="main-nav" @click="mobileOpen = !mobileOpen">
          Menu
        </button>
        <nav id="main-nav" class="hidden gap-1 md:flex" aria-label="Navigasi utama">
          <RouterLink
            v-for="item in visibleMenu" :key="item.to" :to="item.to"
            class="rounded px-3 py-2 text-sm hover:bg-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
            active-class="bg-slate-700"
            :exact-active-class="item.to === '/' ? 'bg-slate-700' : ''"
          >{{ item.label }}</RouterLink>
        </nav>
        <div class="hidden items-center gap-3 md:flex">
          <span class="text-sm">{{ auth.user?.name }} <span class="rounded bg-slate-700 px-2 py-0.5 text-xs uppercase">{{ auth.role }}</span></span>
          <button class="btn-secondary" @click="logout">Logout</button>
        </div>
      </div>
      <nav v-if="mobileOpen" class="flex flex-col gap-1 px-4 pb-3 md:hidden" aria-label="Navigasi mobile">
        <RouterLink v-for="item in visibleMenu" :key="item.to" :to="item.to" class="rounded px-3 py-2 hover:bg-slate-700" @click="mobileOpen = false">
          {{ item.label }}
        </RouterLink>
        <button class="btn-secondary mt-2" @click="logout">Logout ({{ auth.role }})</button>
      </nav>
    </header>
    <main class="mx-auto max-w-7xl px-4 py-6">
      <RouterView />
    </main>
  </div>
</template>
