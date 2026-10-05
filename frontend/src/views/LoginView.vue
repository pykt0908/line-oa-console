<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const email = ref('admin@example.com')
const password = ref('password')
const error = ref('')
const loading = ref(false)
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    router.replace((route.query.redirect as string) || '/inbox')
  } catch (e: any) {
    error.value = e?.response?.data?.message || e?.response?.data?.errors?.email?.[0] || 'เข้าสู่ระบบไม่สำเร็จ'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <v-card class="login-card" elevation="0" rounded="lg">
      <div class="brand">
        <div class="logo" aria-hidden="true">S</div>
        <h1 class="brand-title">STECH LINE OA CONSOLE</h1>
        <p class="typo-body-md muted">เข้าสู่ระบบสำหรับเจ้าหน้าที่</p>
      </div>
      <v-alert v-if="error" type="error" density="compact" class="mb-3">{{ error }}</v-alert>
      <v-form @submit.prevent="submit">
        <v-text-field v-model="email" label="อีเมล" type="email" class="mb-2" />
        <v-text-field v-model="password" label="รหัสผ่าน" type="password" class="mb-4" />
        <v-btn type="submit" color="primary" block :loading="loading">เข้าสู่ระบบ</v-btn>
      </v-form>
      <p class="hint typo-label-md">Demo: admin@example.com / password</p>
    </v-card>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100%;
  display: grid;
  place-items: center;
  background:
    radial-gradient(900px 420px at 85% -5%, color-mix(in srgb, var(--color-primary) 18%, transparent), transparent 55%),
    radial-gradient(700px 360px at 10% 100%, color-mix(in srgb, var(--color-secondary) 10%, transparent), transparent 50%),
    linear-gradient(180deg, #f7f8f7 0%, var(--color-surface) 100%);
  padding: 24px;
}
.login-card {
  width: 100%;
  max-width: 420px;
  padding: 32px 28px;
  border: 1px solid var(--color-outline-variant);
  box-shadow: var(--shadow-md);
  background: var(--color-surface-lowest);
}
.brand { text-align: center; margin-bottom: 22px; }
.brand-title {
  margin: 0;
  font-family: var(--font-display);
  font-size: 18px;
  font-weight: 600;
  letter-spacing: 0.05em;
}
.logo {
  width: 52px;
  height: 52px;
  margin: 0 auto 14px;
  border-radius: var(--radius-lg);
  background: linear-gradient(145deg, #12a854 0%, var(--color-primary) 48%, var(--color-secondary) 100%);
  color: #fff;
  display: grid;
  place-items: center;
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 22px;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.22), var(--shadow-sm);
}
.muted { color: var(--color-ink-muted); }
.hint { margin-top: 16px; text-align: center; color: var(--color-ink-muted); }
</style>
