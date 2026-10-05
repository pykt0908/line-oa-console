<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import api from '@/services/api'

const accounts = ref<any[]>([])
const dialog = ref(false)
const form = reactive({
  name: '',
  channel_id: '',
  channel_secret: '',
  channel_access_token: '',
  account_color: '#10B981',
})
const message = ref('')

async function load() {
  const { data } = await api.get('/line-accounts')
  accounts.value = data.data || []
}

async function create() {
  await api.post('/line-accounts', form)
  dialog.value = false
  Object.assign(form, { name: '', channel_id: '', channel_secret: '', channel_access_token: '', account_color: '#10B981' })
  await load()
}

async function test(id: number) {
  const { data } = await api.post(`/line-accounts/${id}/test`)
  message.value = data.ok ? 'เชื่อมต่อสำเร็จ' : `ทดสอบไม่ผ่าน (${data.status})`
  await load()
}

async function copy(text: string) {
  await navigator.clipboard.writeText(text)
  message.value = 'คัดลอก Webhook URL แล้ว'
}

onMounted(load)
</script>

<template>
  <div class="page">
    <div class="head">
      <h1 class="typo-headline-xl">LINE OA Channels</h1>
      <v-btn color="primary" @click="dialog = true">เพิ่ม OA</v-btn>
    </div>
    <v-alert v-if="message" type="info" density="compact" class="mb-3">{{ message }}</v-alert>
    <v-table>
      <thead>
        <tr>
          <th>ชื่อ</th>
          <th>สี</th>
          <th>Webhook URL</th>
          <th>Secret</th>
          <th>Last Webhook</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="a in accounts" :key="a.id">
          <td>
            <strong>{{ a.name }}</strong>
            <div class="muted">{{ a.is_active ? 'Active' : 'Inactive' }}</div>
          </td>
          <td><span class="dot" :style="{ background: a.account_color }" /></td>
          <td class="url">
            <code>{{ a.webhook_url }}</code>
            <v-btn size="x-small" variant="text" icon="mdi-content-copy" @click="copy(a.webhook_url)" />
          </td>
          <td><code>{{ a.channel_secret_masked }}</code></td>
          <td>{{ a.last_webhook_at || '—' }}</td>
          <td><v-btn size="small" variant="outlined" @click="test(a.id)">Test</v-btn></td>
        </tr>
      </tbody>
    </v-table>

    <v-dialog v-model="dialog" max-width="520">
      <v-card title="เพิ่ม LINE OA">
        <v-card-text>
          <v-text-field v-model="form.name" label="OA Name" class="mb-2" />
          <v-text-field v-model="form.channel_id" label="Channel ID" class="mb-2" />
          <v-text-field v-model="form.channel_secret" label="Channel Secret" class="mb-2" />
          <v-text-field v-model="form.channel_access_token" label="Channel Access Token" class="mb-2" />
          <v-text-field v-model="form.account_color" label="Color" type="color" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">ยกเลิก</v-btn>
          <v-btn color="primary" @click="create">บันทึก</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.page { padding: 24px; height: 100%; overflow: auto; }
.head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.dot { width: 14px; height: 14px; border-radius: 999px; display: inline-block; }
.url { max-width: 360px; }
.url code { font-size: 11px; word-break: break-all; }
.muted { color: var(--color-outline); font-size: 11px; }
</style>
