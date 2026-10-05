<script setup lang="ts">
import { onMounted, ref } from 'vue'
import api from '@/services/api'

const groups = ref<Record<string, any[]>>({})
const message = ref('')

onMounted(async () => {
  const { data } = await api.get('/settings')
  groups.value = data.data || {}
})

async function save() {
  const settings = Object.values(groups.value).flat().map((s: any) => ({
    key: s.key,
    value: s.value,
    group: s.group,
  }))
  const { data } = await api.put('/settings', { settings })
  groups.value = data.data || {}
  message.value = 'บันทึกการตั้งค่าแล้ว'
}
</script>

<template>
  <div class="page">
    <div class="head">
      <h1 class="typo-headline-xl">ตั้งค่า</h1>
      <v-btn color="primary" @click="save">บันทึก</v-btn>
    </div>
    <v-alert v-if="message" type="success" density="compact" class="mb-3">{{ message }}</v-alert>
    <div v-for="(items, group) in groups" :key="group" class="group">
      <h2 class="typo-headline-lg">{{ group }}</h2>
      <div v-for="item in items" :key="item.key" class="row">
        <label>{{ item.key }}</label>
        <v-text-field v-model="item.value" density="compact" hide-details />
      </div>
    </div>
  </div>
</template>

<style scoped>
.page { padding: 24px; height: 100%; overflow: auto; }
.head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.group { background: #fff; border: 1px solid var(--color-outline-variant); border-radius: 4px; padding: 16px; margin-bottom: 12px; }
.row { display: grid; grid-template-columns: 280px 1fr; gap: 12px; align-items: center; margin-top: 10px; }
label { font-size: 13px; color: var(--color-outline); }
</style>
