<script setup lang="ts">
defineProps<{ loading?: boolean; error?: unknown; empty?: boolean; emptyText?: string }>()
defineEmits<{ retry: [] }>()
</script>

<template>
  <div v-if="loading" class="py-10 text-center text-slate-500" role="status" aria-live="polite">Memuat data…</div>
  <div v-else-if="error" class="rounded-md border border-red-200 bg-red-50 p-4 text-red-800" role="alert">
    Gagal memuat data.
    <button class="ml-2 underline" @click="$emit('retry')">Coba lagi</button>
  </div>
  <div v-else-if="empty" class="py-10 text-center text-slate-500">{{ emptyText ?? 'Tidak ada data.' }}</div>
  <slot v-else />
</template>
