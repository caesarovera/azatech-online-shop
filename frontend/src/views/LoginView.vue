<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { parseApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref<string | null>(route.query.expired ? 'Sesi Anda berakhir, silakan login kembali.' : null)

async function submit(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    await auth.login(email.value, password.value)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
    router.push(redirect)
  } catch (e) {
    error.value = parseApiError(e).message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-slate-100 px-4">
    <form class="card w-full max-w-sm space-y-4" @submit.prevent="submit">
      <h1 class="text-xl font-semibold">Masuk ke Azatech Shop</h1>
      <div v-if="error" class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">{{ error }}</div>
      <div>
        <label class="label" for="email">Email</label>
        <input id="email" v-model="email" type="email" class="input" autocomplete="username" required>
      </div>
      <div>
        <label class="label" for="password">Password</label>
        <input id="password" v-model="password" type="password" class="input" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn-primary w-full" :disabled="loading">{{ loading ? 'Memproses…' : 'Login' }}</button>
      <p class="text-xs text-slate-500">Demo: admin@azatech.id / cashier@azatech.id — password: <code>password</code></p>
    </form>
  </main>
</template>
