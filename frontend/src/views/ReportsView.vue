<script setup lang="ts">
import { onMounted, ref } from 'vue'
import api from '@/services/api'

const data = ref<any>(null)
onMounted(async () => {
  const res = await api.get('/dashboard')
  data.value = res.data
})
</script>

<template>
  <div class="page">
    <h1 class="typo-headline-xl mb-4">รายงาน</h1>
    <h2 class="typo-headline-lg mb-2">ปริมาณข้อความ 7 วันล่าสุด ต่อ OA</h2>
    <v-table>
      <thead><tr><th>OA</th><th>จำนวนข้อความ</th></tr></thead>
      <tbody>
        <tr v-for="row in data?.volume_per_oa || []" :key="row.line_account_id">
          <td>{{ row.line_account?.name || row.line_account_id }}</td>
          <td>{{ row.total }}</td>
        </tr>
      </tbody>
    </v-table>
  </div>
</template>

<style scoped>
.page { padding: 24px; height: 100%; overflow: auto; }
</style>
