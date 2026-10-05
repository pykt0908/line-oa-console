<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import api from '@/services/api'

const props = defineProps<{
  modelValue: boolean
  conversationId?: number | null
  contactId?: number | null
  contactName?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const tab = ref<'all' | 'image' | 'video' | 'file'>('all')
const loading = ref(false)
const items = ref<any[]>([])
const error = ref('')
const lightboxOpen = ref(false)
const lightbox = ref<{ type: 'image' | 'video'; url: string; title: string } | null>(null)

const tabs = [
  { key: 'all' as const, label: 'ทั้งหมด' },
  { key: 'image' as const, label: 'รูปภาพ' },
  { key: 'video' as const, label: 'วิดีโอ' },
  { key: 'file' as const, label: 'ไฟล์' },
]

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

const filtered = computed(() => {
  if (tab.value === 'all') {
    return items.value.filter((i) => i.kind === 'image' || i.kind === 'video' || i.kind === 'file')
  }
  return items.value.filter((i) => i.kind === tab.value)
})

const counts = computed(() => ({
  all: items.value.filter((i) => ['image', 'video', 'file'].includes(i.kind)).length,
  image: items.value.filter((i) => i.kind === 'image').length,
  video: items.value.filter((i) => i.kind === 'video').length,
  file: items.value.filter((i) => i.kind === 'file').length,
}))

watch(
  () => [props.modelValue, props.conversationId, props.contactId] as const,
  ([isOpen]) => {
    if (isOpen) void loadMedia()
  },
)

async function loadMedia() {
  if (!props.contactId && !props.conversationId) return
  loading.value = true
  error.value = ''
  try {
    const path = props.contactId
      ? `/contacts/${props.contactId}/media`
      : `/conversations/${props.conversationId}/media`
    const { data } = await api.get(path, { params: { type: 'all', limit: 200 } })
    items.value = data.data || []
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'โหลดประวัติสื่อไม่สำเร็จ'
    items.value = []
  } finally {
    loading.value = false
  }
}

function formatTime(value?: string) {
  if (!value) return ''
  return new Date(value).toLocaleString('th-TH', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatSize(bytes?: number | null) {
  if (!bytes || bytes <= 0) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function openLightbox(item: any) {
  if (item.kind !== 'image' && item.kind !== 'video') return
  lightbox.value = {
    type: item.kind,
    url: item.url,
    title: item.download_name || item.original_name || item.kind,
  }
  lightboxOpen.value = true
}

function closeLightbox() {
  lightboxOpen.value = false
  lightbox.value = null
}
</script>

<template>
  <v-dialog v-model="open" max-width="860" scrollable>
    <v-card class="media-dialog">
      <v-card-title class="dialog-title">
        <div>
          <div class="title">ประวัติสื่อและไฟล์</div>
          <div v-if="contactName" class="subtitle">{{ contactName }}</div>
        </div>
        <v-btn icon="mdi-close" variant="text" @click="open = false" />
      </v-card-title>

      <div class="tabs">
        <button
          v-for="t in tabs"
          :key="t.key"
          type="button"
          class="tab"
          :class="{ active: tab === t.key }"
          @click="tab = t.key"
        >
          {{ t.label }}
          <span class="count">{{ counts[t.key] }}</span>
        </button>
      </div>

      <v-card-text class="dialog-body">
        <div v-if="loading" class="state">กำลังโหลด...</div>
        <div v-else-if="error" class="state error">{{ error }}</div>
        <div v-else-if="!filtered.length" class="state">ยังไม่มีรายการในหมวดนี้</div>

        <div v-else-if="tab === 'file' || (tab === 'all' && filtered.every(i => i.kind === 'file'))" class="file-list">
          <a
            v-for="item in filtered"
            :key="item.id"
            class="file-row"
            :href="item.url"
            :download="item.download_name"
            target="_blank"
            rel="noopener"
          >
            <v-icon icon="mdi-file-download-outline" size="20" />
            <div class="file-meta">
              <strong>{{ item.download_name || item.original_name || 'ไฟล์' }}</strong>
              <span>{{ formatTime(item.message?.created_at || item.created_at) }} · {{ formatSize(item.size_bytes) }}</span>
            </div>
          </a>
        </div>

        <div v-else class="media-grid">
          <template v-for="item in filtered" :key="item.id">
            <button
              v-if="item.kind === 'image' || item.kind === 'video'"
              type="button"
              class="media-tile"
              @click="openLightbox(item)"
            >
              <img v-if="item.kind === 'image'" :src="item.url" :alt="item.download_name" loading="lazy">
              <div v-else class="video-tile">
                <video :src="item.url" preload="metadata" muted />
                <v-icon icon="mdi-play-circle" size="36" class="play" />
              </div>
              <div class="tile-meta">
                <span>{{ item.kind === 'image' ? 'รูปภาพ' : 'วิดีโอ' }}</span>
                <span>{{ formatTime(item.message?.created_at || item.created_at) }}</span>
              </div>
            </button>

            <a
              v-else
              class="media-tile file-tile"
              :href="item.url"
              :download="item.download_name"
              target="_blank"
              rel="noopener"
            >
              <v-icon icon="mdi-file-outline" size="28" />
              <div class="tile-meta">
                <span>{{ item.download_name || 'ไฟล์' }}</span>
                <span>{{ formatTime(item.message?.created_at || item.created_at) }}</span>
              </div>
            </a>
          </template>
        </div>
      </v-card-text>
    </v-card>
  </v-dialog>

  <v-dialog
    :model-value="lightboxOpen"
    max-width="960"
    scrim="rgba(15, 23, 42, 0.82)"
    @update:model-value="(v:boolean) => !v && closeLightbox()"
  >
    <v-card v-if="lightbox" class="lightbox-card">
      <div class="lightbox-bar">
        <span class="lightbox-title">{{ lightbox.title }}</span>
        <div class="lightbox-actions">
          <v-btn
            :href="lightbox.url"
            target="_blank"
            rel="noopener"
            :download="lightbox.title"
            variant="text"
            size="small"
            prepend-icon="mdi-download"
          >
            ดาวน์โหลด
          </v-btn>
          <v-btn icon="mdi-close" variant="text" @click="closeLightbox" />
        </div>
      </div>
      <div class="lightbox-body">
        <img v-if="lightbox.type === 'image'" :src="lightbox.url" :alt="lightbox.title" class="lightbox-media">
        <video v-else :src="lightbox.url" class="lightbox-media" controls autoplay />
      </div>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.media-dialog { overflow: hidden; }
.dialog-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-bottom: 8px;
}
.title { font-size: 18px; font-weight: 700; line-height: 1.2; }
.subtitle { font-size: 12px; color: var(--color-outline); margin-top: 2px; }
.tabs {
  display: flex;
  gap: 6px;
  padding: 0 16px 12px;
  border-bottom: 1px solid var(--color-outline-variant);
  flex-wrap: wrap;
}
.tab {
  appearance: none;
  border: 1px solid var(--color-outline-variant);
  background: #fff;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 700;
  color: var(--color-outline);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.tab.active {
  background: #0b1c30;
  border-color: #0b1c30;
  color: #fff;
}
.count {
  min-width: 18px;
  height: 18px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.08);
  display: grid;
  place-items: center;
  font-size: 10px;
}
.tab.active .count { background: rgba(255, 255, 255, 0.18); }
.dialog-body {
  min-height: 280px;
  max-height: min(70vh, 640px);
}
.state {
  padding: 48px 16px;
  text-align: center;
  color: var(--color-outline);
}
.state.error { color: var(--color-error); }
.media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 10px;
}
.media-tile {
  appearance: none;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  border-radius: 8px;
  overflow: hidden;
  padding: 0;
  cursor: pointer;
  text-align: left;
  display: flex;
  flex-direction: column;
  min-height: 160px;
  text-decoration: none;
  color: inherit;
}
.media-tile img,
.video-tile video {
  width: 100%;
  height: 120px;
  object-fit: cover;
  background: #0f172a;
  display: block;
}
.video-tile { position: relative; }
.play {
  position: absolute;
  inset: 0;
  margin: auto;
  color: #fff;
  filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.45));
}
.tile-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px;
  font-size: 11px;
  color: var(--color-outline);
}
.tile-meta span:first-child {
  color: #0f172a;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.file-tile {
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px 10px;
  color: var(--color-primary);
}
.file-list { display: flex; flex-direction: column; gap: 8px; }
.file-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  text-decoration: none;
  color: inherit;
  background: #fff;
}
.file-row:hover { background: #f0fdf4; }
.file-meta { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.file-meta strong {
  font-size: 13px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.file-meta span { font-size: 11px; color: var(--color-outline); }
.lightbox-card { background: #0b1220; color: #fff; overflow: hidden; }
.lightbox-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.lightbox-title {
  font-size: 13px;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.lightbox-actions { display: flex; align-items: center; gap: 4px; }
.lightbox-body {
  display: grid;
  place-items: center;
  min-height: 280px;
  max-height: min(80vh, 820px);
  padding: 12px;
  background: #05080f;
}
.lightbox-media {
  max-width: 100%;
  max-height: min(75vh, 760px);
  border-radius: 4px;
}
</style>
