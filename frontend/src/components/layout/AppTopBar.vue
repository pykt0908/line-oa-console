<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const q = ref('')

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <header class="topbar">
    <div class="brand-zone">
      <div class="brand">
        <div class="logo" aria-hidden="true">S</div>
        <h1 class="brand-title">STECH LINE OA CONSOLE</h1>
      </div>
    </div>
    <div class="search-zone">
      <div class="search">
        <v-icon icon="mdi-magnify" size="18" class="search-icon" />
        <input
          v-model="q"
          placeholder="ค้นหาทั่วทั้งระบบ (ชื่อ, รหัสนักศึกษา, ข้อความ)..."
          @keyup.enter="router.push({ name: 'inbox', query: { q } })"
        >
      </div>
    </div>
    <div class="right">
      <div class="staff">
        <v-avatar size="32" color="primary">
          <span class="text-caption">{{ (auth.displayName || 'U').slice(0, 1) }}</span>
        </v-avatar>
        <div>
          <div class="typo-body-sm staff-name">{{ auth.displayName }}</div>
          <div class="typo-label-sm muted">{{ auth.user?.position || auth.user?.roles?.[0] }}</div>
        </div>
        <v-btn icon="mdi-logout" variant="text" size="small" @click="logout" />
      </div>
    </div>
  </header>
</template>

<style scoped>
.topbar {
  height: var(--app-topbar-height);
  display: grid;
  grid-template-columns: calc(var(--app-rail-width) + var(--inbox-width)) minmax(0, 1fr) auto;
  align-items: center;
  background: color-mix(in srgb, var(--color-surface-lowest) 92%, var(--color-surface));
  border-bottom: 1px solid var(--color-outline-variant);
  box-shadow: var(--shadow-xs);
  flex-shrink: 0;
  z-index: 20;
  backdrop-filter: saturate(1.1);
}
.brand-zone {
  display: flex;
  align-items: center;
  min-width: 0;
  padding: 0 12px 0 16px;
}
.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}
.brand-title {
  margin: 0;
  font-family: var(--font-display);
  font-size: 14px;
  font-weight: 600;
  letter-spacing: 0.04em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: var(--color-on-surface);
}
.logo {
  width: 34px;
  height: 34px;
  border-radius: var(--radius-md);
  background:
    linear-gradient(145deg, #12a854 0%, var(--color-primary) 48%, var(--color-secondary) 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 15px;
  letter-spacing: -0.02em;
  flex-shrink: 0;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.22);
}
.search-zone {
  padding-left: 16px;
  min-width: 0;
}
.search {
  position: relative;
  width: min(420px, 100%);
}
.search input {
  width: 100%;
  padding: 8px 12px 8px 36px;
  border: 1px solid var(--color-outline-variant);
  border-radius: var(--radius-lg);
  background: var(--color-surface-lowest);
  font-size: 12.5px;
  outline: none;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}
.search input:focus {
  border-color: color-mix(in srgb, var(--color-primary) 55%, var(--color-outline-variant));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 16%, transparent);
}
.search-icon {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-outline);
}
.right {
  display: flex;
  align-items: center;
  padding: 0 16px 0 12px;
}
.staff {
  display: flex;
  align-items: center;
  gap: 12px;
}
.muted { color: var(--color-ink-muted); margin: 0; }
.staff-name { font-weight: 600; letter-spacing: -0.01em; }
</style>
