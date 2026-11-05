import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi } from '@/api/endpoints'
import { tokenStorage } from '@/api/client'
import type { Role, User } from '@/api/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(tokenStorage.get())

  const isAuthenticated = computed(() => token.value !== null)
  const role = computed<Role | null>(() => user.value?.role ?? null)
  const isAdmin = computed(() => role.value === 'admin')

  async function login(email: string, password: string): Promise<void> {
    const result = await authApi.login(email, password)
    tokenStorage.set(result.token)
    token.value = result.token
    user.value = result.user
  }

  /** Dipanggil saat app dibuka ulang: token ada di storage tapi user belum dimuat. */
  async function loadUser(): Promise<void> {
    if (!token.value || user.value) return
    user.value = await authApi.me()
  }

  function clear(): void {
    tokenStorage.clear()
    token.value = null
    user.value = null
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } finally {
      clear()
    }
  }

  return { user, token, isAuthenticated, role, isAdmin, login, loadUser, logout, clear }
})
