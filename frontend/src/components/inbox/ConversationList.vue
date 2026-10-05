<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useInboxStore } from '@/stores/inbox'

const inbox = useInboxStore()
const auth = useAuthStore()

const chips = [
  { key: 'all', label: 'ทั้งหมด' },
  { key: 'unread', label: 'ยังไม่อ่าน' },
]

const activeChip = computed(() => (inbox.filters.unread ? 'unread' : 'all'))

const tagFilterItems = computed(() => [
  { id: '', name: 'ทุกแท็ก' },
  ...(inbox.tags || []).map((t: any) => ({ id: String(t.id), name: `#${t.name}` })),
])

onMounted(() => {
  void inbox.fetchTags()
})

function setChip(key: string) {
  inbox.filters.unread = key === 'unread' ? '1' : ''
  inbox.filters.status = ''
  inbox.fetchConversations()
}

function setTagFilter(tagId: string | number | null) {
  inbox.filters.tag_id = tagId ? String(tagId) : ''
  inbox.fetchConversations()
}

function timeLabel(value?: string) {
  if (!value) return ''
  return new Date(value).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <section class="list-pane">
    <div class="pane-header">
      <div class="title-row">
        <h2 class="typo-headline-lg">กล่องข้อความ (Inbox)</h2>
        <span v-if="inbox.meta.unread_total > 0" class="count">{{ inbox.meta.unread_total }}</span>
      </div>

      <v-select
        :model-value="inbox.filters.line_account_id || ''"
        :items="[{ id: '', name: `ทุกบัญชี LINE OA (${auth.user?.line_accounts?.length || 0})` }, ...(auth.user?.line_accounts || [])]"
        item-title="name"
        item-value="id"
        density="compact"
        hide-details
        @update:model-value="(v:any) => { inbox.filters.line_account_id = v; inbox.fetchConversations() }"
      />

      <div class="filter-row">
        <div class="chips">
          <button
            v-for="chip in chips"
            :key="chip.key"
            type="button"
            class="chip"
            :class="[`chip--${chip.key}`, { 'chip--active': activeChip === chip.key }]"
            @click="setChip(chip.key)"
          >
            {{ chip.label }}
          </button>
        </div>
        <v-select
          class="tag-filter"
          :model-value="inbox.filters.tag_id ? String(inbox.filters.tag_id) : ''"
          :items="tagFilterItems"
          item-title="name"
          item-value="id"
          density="compact"
          hide-details
          clearable
          placeholder="กรองตามแท็ก"
          @update:model-value="setTagFilter"
        />
      </div>

      <v-text-field
        v-model="inbox.filters.q"
        prepend-inner-icon="mdi-magnify"
        placeholder="ค้นหาชื่อ LINE, รหัสนักศึกษา..."
        density="compact"
        hide-details
        clearable
        @keyup.enter="inbox.fetchConversations()"
        @click:clear="inbox.fetchConversations()"
      />
    </div>

    <div class="threads custom-scrollbar">
      <button
        v-for="c in inbox.conversations"
        :key="c.id"
        type="button"
        class="thread"
        :class="{ active: inbox.selectedId === c.id }"
        @click="inbox.selectConversation(c.id)"
      >
        <v-avatar size="40">
          <v-img v-if="c.line_identity?.picture_url" :src="c.line_identity.picture_url" />
          <span v-else>{{ (c.line_identity?.display_name || '?').slice(0, 1) }}</span>
        </v-avatar>
        <div class="meta">
          <div class="row">
            <div class="name-wrap">
              <strong>{{ c.line_identity?.display_name || 'Unknown' }}</strong>
              <span v-if="c.contact?.student_code" class="code">{{ c.contact.student_code }}</span>
            </div>
            <span class="time">{{ timeLabel(c.last_message_at) }}</span>
          </div>
          <div class="oa-row">
            <span class="oa-dot" :style="{ background: c.line_account?.account_color || '#10B981' }" />
            <span>{{ c.line_account?.name }}</span>
            <span v-if="!c.contact?.student_code" class="warn">ยังไม่ระบุรหัสนักศึกษา</span>
          </div>
          <div class="preview-row">
            <p>{{ c.last_message?.text_content || '—' }}</p>
            <span
              v-if="c.unread_count && c.id !== inbox.selectedId"
              class="unread-badge"
            >{{ c.unread_count }}</span>
          </div>
          <div v-if="c.tags?.length" class="tag-row">
            <span v-for="tag in c.tags" :key="tag.id" class="thread-tag">#{{ tag.name }}</span>
          </div>
        </div>
      </button>
      <div v-if="!inbox.conversations.length" class="empty typo-body-md">ยังไม่มีบทสนทนา</div>
    </div>
  </section>
</template>

<style scoped>
.list-pane {
  width: var(--inbox-width);
  flex-shrink: 0;
  border-right: 1px solid var(--color-outline-variant);
  background: var(--color-surface-lowest);
  display: flex;
  flex-direction: column;
  height: 100%;
}
.pane-header {
  padding: 14px 12px;
  border-bottom: 1px solid var(--color-outline-variant);
  display: flex;
  flex-direction: column;
  gap: 10px;
  background: color-mix(in srgb, var(--color-surface) 70%, white);
}
.title-row { display: flex; align-items: center; gap: 8px; }
.count {
  background: var(--color-error); color: #fff; border-radius: 999px;
  font-size: 10px; font-weight: 700; padding: 2px 8px;
}
.filter-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.chips { display: flex; gap: 4px; flex-shrink: 0; }
.chip {
  appearance: none;
  border: 1px solid var(--color-outline-variant);
  background: var(--color-surface-lowest);
  border-radius: var(--radius-md);
  padding: 4px 10px;
  min-height: 26px;
  font-size: 11px;
  font-weight: 600;
  line-height: 14px;
  color: var(--color-ink-muted);
  white-space: nowrap;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background 140ms ease, color 140ms ease, border-color 140ms ease;
}
.chip--active {
  background: var(--color-on-surface);
  color: #fff;
  border-color: var(--color-on-surface);
}
.tag-filter { flex: 1; min-width: 0; }
.threads { flex: 1; overflow-y: auto; }
.thread {
  width: 100%; text-align: left; display: flex; gap: 10px; padding: 12px 14px;
  border: 0; border-bottom: 1px solid color-mix(in srgb, var(--color-outline-variant) 80%, transparent);
  background: transparent; cursor: pointer;
  transition: background 140ms ease;
}
.thread:hover { background: color-mix(in srgb, var(--color-primary) 6%, transparent); }
.thread.active {
  background: var(--color-primary-soft);
  box-shadow: inset 3px 0 0 var(--color-primary);
}
.meta { flex: 1; min-width: 0; }
.row, .preview-row, .oa-row, .name-wrap { display: flex; align-items: center; gap: 6px; }
.row, .preview-row { justify-content: space-between; }
.name-wrap { min-width: 0; }
.name-wrap strong {
  font-size: 14px;
  font-weight: 600;
  letter-spacing: -0.01em;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.code {
  background: var(--color-primary-soft); color: #0a5c2e; border: 1px solid var(--color-primary-soft-border);
  border-radius: var(--radius-sm); font-size: 10px; font-weight: 700; padding: 1px 6px;
}
.time, .oa-row { font-size: 11px; color: var(--color-ink-muted); }
.oa-dot { width: 6px; height: 6px; border-radius: 999px; }
.warn {
  border-radius: var(--radius-sm); font-size: 10px; font-weight: 600; padding: 1px 6px;
  background: #fff8eb; color: #92400e; border: 1px solid #f3dfb5;
}
.preview-row p {
  margin: 4px 0 0; font-size: 12px; color: var(--color-ink-muted);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1;
}
.unread-badge {
  min-width: 18px; height: 18px; border-radius: 999px; background: var(--color-primary);
  color: #fff; font-size: 10px; font-weight: 700; display: grid; place-items: center;
}
.tag-row { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.thread-tag {
  font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px;
  background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4;
}
.empty { padding: 24px; text-align: center; color: var(--color-outline); }
</style>
