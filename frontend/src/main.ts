import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import { setUnauthorizedHandler } from './api/client'
import { router } from './router'
import { useAuthStore } from './stores/auth'
import './style.css'

const app = createApp(App)
const pinia = createPinia()

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { staleTime: 30_000, retry: 1, refetchOnWindowFocus: false },
  },
})

app.use(pinia)
app.use(router)
app.use(VueQueryPlugin, { queryClient })

// Token expired / di-revoke → paksa logout & bersihkan cache server-state.
setUnauthorizedHandler(() => {
  useAuthStore().clear()
  queryClient.clear()
  if (router.currentRoute.value.name !== 'login') {
    router.push({ name: 'login', query: { expired: '1' } })
  }
})

app.mount('#app')
