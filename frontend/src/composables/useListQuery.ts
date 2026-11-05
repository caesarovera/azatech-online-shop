import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

/**
 * State tabel (page, search, sort, filter) disimpan di URL query string,
 * sehingga halaman bisa di-bookmark / dibagikan dan tombol back browser bekerja.
 * URL adalah "single source of truth"; tidak ada state duplikat di komponen.
 */
export function useListQuery(defaults: { sort: string; direction?: 'asc' | 'desc'; perPage?: number }) {
  const route = useRoute()
  const router = useRouter()

  const str = (key: string): string => {
    const v = route.query[key]
    return typeof v === 'string' ? v : ''
  }

  const params = computed(() => ({
    page: Number(str('page')) || 1,
    per_page: Number(str('per_page')) || defaults.perPage || 10,
    search: str('search'),
    sort: str('sort') || defaults.sort,
    direction: (str('direction') || defaults.direction || 'desc') as 'asc' | 'desc',
  }))

  function update(patch: Record<string, string | number | undefined>, resetPage = true): void {
    const query: Record<string, string> = {}
    const merged = { ...route.query, ...patch, ...(resetPage ? { page: undefined } : {}) }
    for (const [k, v] of Object.entries(merged)) {
      if (v !== undefined && v !== '' && v !== null) query[k] = String(v)
    }
    router.replace({ query })
  }

  function setPage(page: number): void {
    update({ page }, false)
  }

  function toggleSort(column: string): void {
    const direction = params.value.sort === column && params.value.direction === 'asc' ? 'desc' : 'asc'
    update({ sort: column, direction })
  }

  return { params, str, update, setPage, toggleSort }
}
