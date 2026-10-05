<script setup lang="ts">
import { onMounted, ref } from 'vue'
import api from '@/services/api'

const rows = ref<any[]>([])
onMounted(async () => {
  const { data } = await api.get('/audit-logs')
  rows.value = data.data || []
})
</script>

<template>
  <div class="page">
    <h1 class="typo-headline-xl mb-4">Audit Log</h1>
    <v-table density="compact">
      <thead>
        <tr><th>เวลา</th><th>ผู้ใช้</th><th>Action</th><th>รายละเอียด</th></tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ row.created_at }}</td>
          <td>{{ row.user?.display_name || row.user?.name || 'system' }}</td>
          <td>{{ row.action }}</td>
          <td><code>{{ JSON.stringify(row.metadata_json || {}) }}</code></td>
        </tr>
      </tbody>
    </v-table>
  </div>
</template>

<style scoped>
.page { padding: 24px; height: 100%; overflow: auto; }
code { font-size: 11px; }
</style>
