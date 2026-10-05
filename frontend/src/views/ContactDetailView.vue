<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'

const route = useRoute()
const contact = ref<any>(null)
const history = ref<any[]>([])

onMounted(async () => {
  const id = route.params.id
  const [c, h] = await Promise.all([
    api.get(`/contacts/${id}`),
    api.get(`/contacts/${id}/unified-history`),
  ])
  contact.value = c.data.data
  history.value = h.data.data || []
})
</script>

<template>
  <div v-if="contact" class="page">
    <h1 class="typo-headline-xl">{{ contact.identities?.[0]?.display_name || `Contact #${contact.id}` }}</h1>
    <p class="muted">{{ contact.student_code ? `รหัสนักศึกษา ${contact.student_code}` : 'ยังไม่ระบุรหัสนักศึกษา' }}</p>

    <h2 class="typo-headline-lg mt-6 mb-2">LINE Identities</h2>
    <div v-for="i in contact.identities" :key="i.id" class="row">
      <span class="dot" :style="{ background: i.line_account?.account_color }" />
      {{ i.line_account?.name }} · {{ i.display_name }} · {{ i.line_user_id }}
    </div>

    <h2 class="typo-headline-lg mt-6 mb-2">Unified History</h2>
    <div v-for="m in history" :key="m.id" class="msg">
      <span class="oa">{{ m.line_account?.name }}</span>
      <span>{{ m.text_content }}</span>
      <span class="time">{{ m.created_at }}</span>
    </div>
  </div>
</template>

<style scoped>
.page { padding: 24px; height: 100%; overflow: auto; }
.muted { color: var(--color-outline); }
.row, .msg { display: flex; gap: 10px; align-items: center; padding: 8px 0; border-bottom: 1px solid #eef2ff; font-size: 13px; }
.dot { width: 8px; height: 8px; border-radius: 999px; }
.oa { font-weight: 700; min-width: 100px; }
.time { margin-left: auto; color: var(--color-outline); font-size: 11px; }
</style>
