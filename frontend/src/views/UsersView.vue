<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import api from '@/services/api'

const users = ref<any[]>([])
const roles = ref<string[]>([])
const accounts = ref<any[]>([])
const dialog = ref(false)
const form = reactive({
  name: '',
  email: '',
  password: '',
  display_name: '',
  position: '',
  role: 'Staff',
  line_account_ids: [] as number[],
})

async function load() {
  const [u, r, a] = await Promise.all([
    api.get('/users'),
    api.get('/roles'),
    api.get('/line-accounts'),
  ])
  users.value = u.data.data || []
  roles.value = r.data.data || []
  accounts.value = a.data.data || []
}

async function create() {
  await api.post('/users', form)
  dialog.value = false
  await load()
}

onMounted(load)
</script>

<template>
  <div class="page">
    <div class="head">
      <h1 class="typo-headline-xl">ผู้ใช้และสิทธิ์</h1>
      <v-btn color="primary" @click="dialog = true">เพิ่มผู้ใช้</v-btn>
    </div>
    <v-table>
      <thead>
        <tr><th>ชื่อ</th><th>อีเมล</th><th>บทบาท</th><th>OA</th><th>สถานะ</th></tr>
      </thead>
      <tbody>
        <tr v-for="u in users" :key="u.id">
          <td>{{ u.display_name || u.name }}</td>
          <td>{{ u.email }}</td>
          <td>{{ u.roles?.map((r: any) => r.name).join(', ') }}</td>
          <td>{{ u.line_accounts?.map((a: any) => a.name).join(', ') || '—' }}</td>
          <td>{{ u.is_active ? 'Active' : 'Disabled' }}</td>
        </tr>
      </tbody>
    </v-table>

    <v-dialog v-model="dialog" max-width="560">
      <v-card title="เพิ่มผู้ใช้">
        <v-card-text>
          <v-text-field v-model="form.name" label="ชื่อ" class="mb-2" />
          <v-text-field v-model="form.display_name" label="ชื่อแสดง" class="mb-2" />
          <v-text-field v-model="form.position" label="ตำแหน่ง" class="mb-2" />
          <v-text-field v-model="form.email" label="อีเมล" class="mb-2" />
          <v-text-field v-model="form.password" label="รหัสผ่าน" type="password" class="mb-2" />
          <v-select v-model="form.role" :items="roles" label="บทบาท" class="mb-2" />
          <v-select
            v-model="form.line_account_ids"
            :items="accounts"
            item-title="name"
            item-value="id"
            label="OA ที่เข้าถึงได้"
            multiple
            chips
          />
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
</style>
