<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useInboxStore } from '@/stores/inbox'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const inbox = useInboxStore()

const isStaff = computed(() => auth.user?.roles?.includes('Staff') ?? false)

const items = computed(() => {
  if (isStaff.value) {
    return [
      { to: '/inbox', icon: 'mdi-inbox', label: 'Inbox', badge: inbox.meta.unread_total },
    ]
  }

  return [
    { to: '/inbox', icon: 'mdi-inbox', label: 'Inbox', show: auth.can('inbox.view'), badge: inbox.meta.unread_total },
    { to: '/contacts', icon: 'mdi-card-account-details-outline', label: 'รายชื่อ', show: auth.can('inbox.view') },
    { to: '/line-accounts', icon: 'mdi-hub', label: 'OA ช่อง', show: auth.can('line_account.manage') },
    { to: '/users', icon: 'mdi-account-group-outline', label: 'ผู้ใช้', show: auth.can('user.manage') },
    { to: '/reports', icon: 'mdi-chart-bar', label: 'รายงาน', show: auth.can('dashboard.view') },
    { to: '/settings', icon: 'mdi-cog-outline', label: 'ตั้งค่า', show: auth.can('settings.manage') },
  ].filter((i) => i.show)
})
</script>

<template>
  <nav class="app-rail" aria-label="Main Navigation">
    <div class="rail-top">
      <button
        v-for="item in items"
        :key="item.to"
        class="rail-item"
        :class="{ active: route.path.startsWith(item.to) }"
        :title="item.label"
        type="button"
        @click="router.push(item.to)"
      >
        <v-icon :icon="item.icon" size="20" />
        <span>{{ item.label }}</span>
        <span v-if="item.badge && item.badge > 0" class="rail-badge">{{ item.badge > 99 ? '99+' : item.badge }}</span>
      </button>
    </div>
    <div v-if="!isStaff" class="rail-bottom">
      <button class="rail-item" type="button" title="Audit" @click="router.push('/audit')" v-if="auth.can('audit.view')">
        <v-icon icon="mdi-shield-search" size="20" />
      </button>
      <button class="rail-item" type="button" title="Help">
        <v-icon icon="mdi-help-circle-outline" size="20" />
      </button>
    </div>
  </nav>
</template>

<style scoped>
.app-rail {
  width: var(--app-rail-width);
  flex-shrink: 0;
  background:
    linear-gradient(180deg, #f7f9f7 0%, var(--color-surface-low) 100%);
  border-right: 1px solid var(--color-outline-variant);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  z-index: 30;
}
.rail-top,
.rail-bottom {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  width: 100%;
  padding: 0 8px;
}
.rail-bottom {
  border-top: 1px solid var(--color-outline-variant);
  padding-top: 12px;
}
.rail-item {
  box-sizing: border-box;
  width: 48px;
  min-height: 44px;
  border: 0;
  background: transparent;
  border-radius: var(--radius-md);
  color: var(--color-ink-muted);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  cursor: pointer;
  position: relative;
  font-size: 9px;
  font-weight: 600;
  letter-spacing: 0.02em;
  line-height: 1.1;
  padding: 4px 2px;
  transition: background 140ms ease, color 140ms ease, box-shadow 140ms ease;
}
.rail-item > span:not(.rail-badge) {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
  text-align: center;
}
.rail-item:hover {
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-on-surface);
}
.rail-item.active {
  background: var(--color-surface-lowest);
  color: var(--color-primary);
  box-shadow: var(--shadow-xs), inset 0 0 0 1px var(--color-primary-soft-border);
  font-weight: 700;
}
.rail-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: var(--color-error);
  color: #fff;
  font-size: 9px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
