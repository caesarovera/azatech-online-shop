<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { parseApiError } from '@/api/client'
import { customerApi, type CustomerPayload } from '@/api/endpoints'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const id = computed(() => (typeof route.params.id === 'string' ? route.params.id : null))

const form = reactive<CustomerPayload>({ customer_name: '', customer_address: '', gender: null, birth_date: null, phone: '', email: '' })
const errors = ref<Record<string, string[]>>({})

const { data: existing } = useQuery({
  queryKey: ['customer', id],
  queryFn: () => customerApi.get(id.value!),
  enabled: computed(() => id.value !== null),
})
watch(existing, (c) => { if (c) Object.assign(form, c) }, { immediate: true })

const save = useMutation({
  mutationFn: () => {
    const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v])) as Partial<CustomerPayload>
    return id.value ? customerApi.update(id.value, payload) : customerApi.create(payload)
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['customers'] })
    router.push({ name: 'customers' })
  },
  onError: (e) => { errors.value = parseApiError(e).errors },
})
</script>

<template>
  <section class="max-w-xl">
    <h1 class="mb-4 text-2xl font-semibold">{{ id ? 'Ubah pelanggan' : 'Tambah pelanggan' }}</h1>
    <form class="card space-y-4" @submit.prevent="save.mutate()">
      <div>
        <label class="label" for="cname">Nama</label>
        <input id="cname" v-model="form.customer_name" class="input" required>
        <p v-if="errors.customer_name" class="field-error">{{ errors.customer_name[0] }}</p>
      </div>
      <div>
        <label class="label" for="caddress">Alamat</label>
        <input id="caddress" v-model="form.customer_address" class="input">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="label" for="cgender">Gender</label>
          <select id="cgender" v-model="form.gender" class="input">
            <option :value="null">-</option><option value="male">Pria</option><option value="female">Wanita</option>
          </select>
        </div>
        <div>
          <label class="label" for="cbirth">Tanggal lahir</label>
          <input id="cbirth" v-model="form.birth_date" type="date" class="input">
          <p v-if="errors.birth_date" class="field-error">{{ errors.birth_date[0] }}</p>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="label" for="cphone">Telepon</label>
          <input id="cphone" v-model="form.phone" class="input">
          <p v-if="errors.phone" class="field-error">{{ errors.phone[0] }}</p>
        </div>
        <div>
          <label class="label" for="cemail">Email</label>
          <input id="cemail" v-model="form.email" type="email" class="input">
          <p v-if="errors.email" class="field-error">{{ errors.email[0] }}</p>
        </div>
      </div>
      <div class="flex gap-2">
        <button type="submit" class="btn-primary" :disabled="save.isPending.value">Simpan</button>
        <RouterLink :to="{ name: 'customers' }" class="btn-secondary">Batal</RouterLink>
      </div>
    </form>
  </section>
</template>
