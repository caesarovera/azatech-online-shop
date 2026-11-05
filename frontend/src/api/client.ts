import axios, { AxiosError } from 'axios'
import type { ApiError } from './types'

const TOKEN_KEY = 'azatech_token'

export const tokenStorage = {
  get: (): string | null => localStorage.getItem(TOKEN_KEY),
  set: (token: string): void => localStorage.setItem(TOKEN_KEY, token),
  clear: (): void => localStorage.removeItem(TOKEN_KEY),
}

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api',
  headers: { Accept: 'application/json' },
  timeout: 15000,
})

http.interceptors.request.use((config) => {
  const token = tokenStorage.get()
  if (token) config.headers.Authorization = `Bearer ${token}`
  config.headers['X-Request-Id'] = crypto.randomUUID()
  return config
})

/** Dipanggil saat token tidak valid/expired → paksa logout (di-set oleh auth store). */
let onUnauthorized: () => void = () => {}
export function setUnauthorizedHandler(handler: () => void): void {
  onUnauthorized = handler
}

http.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    const isLoginRequest = error.config?.url?.includes('/auth/login')
    if (error.response?.status === 401 && !isLoginRequest) {
      onUnauthorized()
    }
    return Promise.reject(error)
  },
)

/** Ambil pesan & error per field dari response API standar. */
export function parseApiError(error: unknown): { message: string; errors: Record<string, string[]>; status?: number } {
  if (axios.isAxiosError<ApiError>(error)) {
    return {
      message: error.response?.data?.message ?? error.message,
      errors: error.response?.data?.errors ?? {},
      status: error.response?.status,
    }
  }
  return { message: error instanceof Error ? error.message : 'Terjadi kesalahan', errors: {} }
}
