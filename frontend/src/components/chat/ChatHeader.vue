<script setup lang="ts">
import { useInboxStore } from '@/stores/inbox'

const inbox = useInboxStore()
</script>

<template>
  <div v-if="inbox.selected" class="chat-header">
    <div class="left">
      <v-avatar size="40">
        <v-img v-if="inbox.selected.line_identity?.picture_url" :src="inbox.selected.line_identity.picture_url" />
        <span v-else>{{ (inbox.selected.line_identity?.display_name || '?').slice(0, 1) }}</span>
      </v-avatar>
      <div>
        <div class="title-row">
          <h2 class="typo-headline-lg">{{ inbox.selected.line_identity?.display_name }}</h2>
          <span v-if="inbox.selected.contact?.student_code" class="pill ok">
            <v-icon icon="mdi-check-circle" size="13" /> รหัส: {{ inbox.selected.contact.student_code }}
          </span>
          <span v-else class="pill warn">ยังไม่ระบุรหัสนักศึกษา</span>
          <span class="pill muted">
            <span class="dot" :style="{ background: inbox.selected.line_account?.account_color || '#10B981' }" />
            LINE OA: {{ inbox.selected.line_account?.name }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.chat-header {
  height: 64px; padding: 0 16px;
  background: var(--color-surface-lowest);
  border-bottom: 1px solid var(--color-outline-variant);
  box-shadow: var(--shadow-xs);
  display: flex; align-items: center; flex-shrink: 0;
}
.left, .title-row { display: flex; align-items: center; gap: 10px; }
.pill {
  display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px;
  border-radius: var(--radius-md); font-size: 11px; font-weight: 600;
  border: 1px solid var(--color-outline-variant);
}
.pill.ok {
  background: var(--color-primary-soft);
  color: #0a5c2e;
  border-color: var(--color-primary-soft-border);
}
.pill.warn { background: #fff8eb; color: #92400e; border-color: #f3dfb5; }
.pill.muted { background: #f3f5f7; color: #3d4a55; }
.dot { width: 8px; height: 8px; border-radius: 999px; background: var(--color-primary); display: inline-block; }
</style>
