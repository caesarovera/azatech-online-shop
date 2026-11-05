<script setup lang="ts">
const props = defineProps<{ column: string; label: string; sort: string; direction: 'asc' | 'desc' }>()
defineEmits<{ toggle: [column: string] }>()
const ariaSort = (): 'ascending' | 'descending' | 'none' =>
  props.sort === props.column ? (props.direction === 'asc' ? 'ascending' : 'descending') : 'none'
</script>

<template>
  <th :aria-sort="ariaSort()">
    <button class="flex items-center gap-1 font-semibold hover:underline" @click="$emit('toggle', column)">
      {{ label }}
      <span aria-hidden="true">{{ sort === column ? (direction === 'asc' ? '▲' : '▼') : '↕' }}</span>
    </button>
  </th>
</template>
