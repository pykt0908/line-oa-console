<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'

const rows = ref<any[]>([])
const q = ref('')
const router = useRouter()

async function load() {
  const { data } = await api.get('/contacts', { params: { q: q.value || undefined } })
  rows.value = data.data || []
}

onMounted(load)
</script>

<template>
  <div class="page">
    <div class="head">
      <h1 class="typo-headline-xl">รายชื่อผู้ติดต่อ</h1>
      <v-text-field v-model="q" placeholder="ค้นหาชื่อ / รหัสนักศึกษา" style="max-width:320px" @keyup.enter="load" />
    </div>
    <v-table>
      <thead>
        <tr>
          <th>LINE Name</th>
          <th>รหัสนักศึกษา</th>
          <th>OA</th>
          <th>สถานะรหัส</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="c in rows" :key="c.id" class="clickable" @click="router.push(`/contacts/${c.id}`)">
          <td>{{ c.identities?.[0]?.display_name || '—' }}</td>
          <td>{{ c.student_code || '—' }}</td>
          <td>{{ c.identities_count || c.identities?.length || 0 }}</td>
          <td>{{ c.student_code ? 'ระบุรหัสแล้ว' : 'ยังไม่ระบุรหัสนักศึกษา' }}</td>
        </tr>
      </tbody>
    </v-table>
  </div>
</template>

<style scoped>
.page { padding: 24px; height: 100%; overflow: auto; }
.head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 12px; }
.clickable { cursor: pointer; }
.clickable:hover { background: #eef7f0; }
</style>
