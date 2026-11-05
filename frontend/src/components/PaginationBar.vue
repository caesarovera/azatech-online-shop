<script setup lang="ts">
import type { PaginationMeta } from '@/api/types'

defineProps<{ meta: PaginationMeta | undefined }>()
defineEmits<{ change: [page: number] }>()
</script>

<template>
  <nav v-if="meta" class="mt-4 flex items-center justify-between text-sm" aria-label="Pagination">
    <span class="text-slate-600">Halaman {{ meta.page }} dari {{ Math.max(meta.total_pages, 1) }} · {{ meta.total }} data</span>
    <div class="flex gap-2">
      <button class="btn-secondary" :disabled="meta.page <= 1" @click="$emit('change', meta.page - 1)">Sebelumnya</button>
      <button class="btn-secondary" :disabled="meta.page >= meta.total_pages" @click="$emit('change', meta.page + 1)">Berikutnya</button>
    </div>
  </nav>
</template>
