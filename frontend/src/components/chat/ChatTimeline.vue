<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useInboxStore } from '@/stores/inbox'

const inbox = useInboxStore()
const root = ref<HTMLElement | null>(null)
const loadingOlder = ref(false)
const lightboxOpen = ref(false)
const lightbox = ref<{ type: 'image' | 'video'; url: string; title: string } | null>(null)

const timeline = computed(() => {
  const msgs = inbox.messages.map((m) => ({ kind: 'message' as const, at: m.created_at, ...m }))
  const notes = inbox.notes.map((n) => ({ kind: 'note' as const, at: n.created_at, ...n }))
  return [...msgs, ...notes].sort((a, b) => new Date(a.at).getTime() - new Date(b.at).getTime())
})

/** Changes when conversation switches or a newer item lands at the end. */
const bottomKey = computed(() => {
  const items = timeline.value
  if (!items.length) return `${inbox.selectedId}:empty`
  const last = items[items.length - 1]
  return `${inbox.selectedId}:${last.kind}-${last.id}:${items.length}`
})

function scrollToBottom() {
  const el = root.value
  if (!el) return
  el.scrollTop = el.scrollHeight
}

async function stickToBottom() {
  await nextTick()
  scrollToBottom()
  requestAnimationFrame(() => {
    scrollToBottom()
    requestAnimationFrame(scrollToBottom)
  })
}

watch(
  bottomKey,
  () => {
    if (loadingOlder.value) return
    void stickToBottom()
  },
  { flush: 'post', immediate: true },
)

onMounted(() => {
  void stickToBottom()
})

async function loadOlder() {
  const el = root.value
  const prevHeight = el?.scrollHeight ?? 0
  const prevTop = el?.scrollTop ?? 0
  loadingOlder.value = true
  try {
    await inbox.loadOlder()
    await nextTick()
    if (el) el.scrollTop = el.scrollHeight - prevHeight + prevTop
  } finally {
    loadingOlder.value = false
  }
}

function formatTime(value: string) {
  return new Date(value).toLocaleString('th-TH', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short' })
}

function mediaAttachments(item: any) {
  return (item.attachments || []).filter((a: any) => a.url && a.download_status === 'completed')
}

function imageAttachments(item: any) {
  return mediaAttachments(item).filter((a: any) => !a.mime_type || String(a.mime_type).startsWith('image/'))
}

function videoAttachments(item: any) {
  return mediaAttachments(item).filter((a: any) => String(a.mime_type || '').startsWith('video/'))
}

function audioAttachments(item: any) {
  return mediaAttachments(item).filter((a: any) => String(a.mime_type || '').startsWith('audio/'))
}

function fileAttachments(item: any) {
  return mediaAttachments(item).filter((a: any) => {
    const mime = String(a.mime_type || '')
    return !mime.startsWith('image/') && !mime.startsWith('video/') && !mime.startsWith('audio/')
  })
}

function fileLabel(att: any) {
  return att.download_name || att.original_name || 'ไฟล์แนบ'
}

function stickerUrl(item: any): string | null {
  return item.sticker?.preview_url || null
}

function stickerFallback(event: Event, item: any) {
  const img = event.target as HTMLImageElement
  const id = item.sticker?.sticker_id
  if (!id) return
  const fallback = `https://stickershop.line-scdn.net/stickershop/v1/sticker/${id}/android/sticker.png`
  if (img.src !== fallback) img.src = fallback
}

function typeLabel(type: string) {
  return ({
    image: 'รูปภาพ',
    sticker: 'สติกเกอร์',
    video: 'วิดีโอ',
    audio: 'เสียง',
    file: 'ไฟล์',
    location: 'ตำแหน่ง',
  } as Record<string, string>)[type] || type
}

function showText(item: any) {
  if (item.message_type === 'image' && imageAttachments(item).length) return false
  if (item.message_type === 'sticker' && stickerUrl(item)) return false
  if (item.message_type === 'video' && videoAttachments(item).length) return false
  if (item.message_type === 'audio' && audioAttachments(item).length) return false
  if (item.message_type === 'file' && fileAttachments(item).length) return false
  return Boolean(item.text_content)
}

function openLightbox(type: 'image' | 'video', url: string, title?: string) {
  lightbox.value = { type, url, title: title || (type === 'image' ? 'รูปภาพ' : 'วิดีโอ') }
  lightboxOpen.value = true
}

function closeLightbox() {
  lightboxOpen.value = false
  lightbox.value = null
}
</script>

<template>
  <div ref="root" class="timeline custom-scrollbar">
    <div class="load-older">
      <v-btn size="small" variant="text" @click="loadOlder">โหลดข้อความก่อนหน้า</v-btn>
    </div>

    <template v-for="item in timeline" :key="`${item.kind}-${item.id}`">
      <div v-if="item.kind === 'note'" class="note-card">
        <div class="note-label">
          <v-icon icon="mdi-notebook-outline" size="14" /> บันทึกภายใน · เห็นเฉพาะเจ้าหน้าที่
        </div>
        <p v-if="item.note">{{ item.note }}</p>

        <div v-if="imageAttachments(item).length" class="image-wrap note-media">
          <button
            v-for="att in imageAttachments(item)"
            :key="att.id"
            type="button"
            class="media-btn"
            @click="openLightbox('image', att.url, fileLabel(att))"
          >
            <img
              class="chat-image"
              :src="att.url"
              :alt="fileLabel(att)"
              loading="lazy"
              @load="stickToBottom"
            >
          </button>
        </div>

        <div v-if="videoAttachments(item).length" class="video-wrap note-media">
          <button
            v-for="att in videoAttachments(item)"
            :key="att.id"
            type="button"
            class="media-btn video-btn"
            @click="openLightbox('video', att.url, fileLabel(att))"
          >
            <video
              class="chat-video"
              :src="att.url"
              preload="metadata"
              muted
              @loadeddata="stickToBottom"
            />
            <span class="play-badge">
              <v-icon icon="mdi-play-circle" size="42" />
            </span>
          </button>
        </div>

        <div v-if="fileAttachments(item).length || audioAttachments(item).length" class="note-files">
          <audio
            v-for="att in audioAttachments(item)"
            :key="`na-${att.id}`"
            class="chat-audio"
            :src="att.url"
            controls
            preload="metadata"
          />
          <a
            v-for="att in fileAttachments(item)"
            :key="`nf-${att.id}`"
            class="file-link"
            :href="att.url"
            :download="fileLabel(att)"
            target="_blank"
            rel="noopener"
          >
            <v-icon icon="mdi-file-download-outline" size="18" />
            <span>{{ fileLabel(att) }}</span>
          </a>
        </div>

        <div class="meta">{{ item.user?.display_name || item.user?.name }} · {{ formatTime(item.at) }}</div>
      </div>

      <div v-else class="msg" :class="item.direction">
        <div class="bubble" :class="{ media: item.message_type === 'image' || item.message_type === 'sticker' || item.message_type === 'video' }">
          <div v-if="item.direction === 'outbound'" class="sender">
            {{ item.sender?.display_name || item.sender?.name || 'เจ้าหน้าที่' }}
          </div>
          <div v-else class="sender">
            {{ inbox.selected?.line_identity?.display_name || 'ผู้ใช้ LINE' }}
          </div>

          <div v-if="item.message_type !== 'text'" class="type">{{ typeLabel(item.message_type) }}</div>

          <div v-if="item.message_type === 'sticker' && stickerUrl(item)" class="sticker-wrap">
            <img
              class="sticker"
              :src="stickerUrl(item)!"
              alt="sticker"
              loading="lazy"
              @load="stickToBottom"
              @error="stickerFallback($event, item)"
            >
          </div>

          <div v-else-if="imageAttachments(item).length" class="image-wrap">
            <button
              v-for="att in imageAttachments(item)"
              :key="att.id"
              type="button"
              class="media-btn"
              @click="openLightbox('image', att.url, fileLabel(att))"
            >
              <img
                class="chat-image"
                :src="att.url"
                :alt="fileLabel(att)"
                loading="lazy"
                @load="stickToBottom"
              >
            </button>
          </div>

          <div v-else-if="videoAttachments(item).length" class="video-wrap">
            <button
              v-for="att in videoAttachments(item)"
              :key="att.id"
              type="button"
              class="media-btn video-btn"
              @click="openLightbox('video', att.url, fileLabel(att))"
            >
              <video
                class="chat-video"
                :src="att.url"
                preload="metadata"
                muted
                @loadeddata="stickToBottom"
              />
              <span class="play-badge">
                <v-icon icon="mdi-play-circle" size="42" />
              </span>
            </button>
          </div>

          <template v-else>
            <audio
              v-for="att in audioAttachments(item)"
              :key="`a-${att.id}`"
              class="chat-audio"
              :src="att.url"
              controls
              preload="metadata"
            />
            <a
              v-for="att in fileAttachments(item)"
              :key="`f-${att.id}`"
              class="file-link"
              :href="att.url"
              :download="fileLabel(att)"
              target="_blank"
              rel="noopener"
            >
              <v-icon icon="mdi-file-download-outline" size="18" />
              <span>{{ fileLabel(att) }}</span>
            </a>
          </template>

          <p v-if="showText(item)">{{ item.text_content }}</p>
          <p v-else-if="item.message_type === 'image' && !imageAttachments(item).length" class="pending">
            กำลังโหลดรูปภาพ…
          </p>

          <div class="meta">
            <span>{{ formatTime(item.at) }}</span>
            <span v-if="item.delivery_status === 'failed'" class="fail"> · ส่งไม่สำเร็จ</span>
          </div>
        </div>
      </div>
    </template>

    <v-dialog
      :model-value="lightboxOpen"
      max-width="960"
      scrim="rgba(15, 23, 42, 0.82)"
      transition="fade-transition"
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
          <img
            v-if="lightbox.type === 'image'"
            :src="lightbox.url"
            :alt="lightbox.title"
            class="lightbox-image"
          >
          <video
            v-else
            :src="lightbox.url"
            class="lightbox-video"
            controls
            autoplay
          />
        </div>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.timeline {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 18px 22px;
  background:
    radial-gradient(1200px 420px at 80% -10%, color-mix(in srgb, var(--color-primary) 8%, transparent), transparent 60%),
    var(--color-surface);
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.load-older { text-align: center; }
.msg { display: flex; }
.msg.inbound { justify-content: flex-start; }
.msg.outbound { justify-content: flex-end; }
.bubble {
  max-width: min(70%, 560px); padding: 11px 13px; border-radius: var(--radius-xl);
  background: var(--color-surface-lowest);
  border: 1px solid var(--color-outline-variant);
  box-shadow: var(--shadow-xs);
}
.bubble.media { padding: 8px; }
.msg.inbound .bubble { border-top-left-radius: 4px; }
.msg.outbound .bubble {
  background: var(--color-primary-soft);
  border-color: var(--color-primary-soft-border);
  border-top-right-radius: 4px;
}
.bubble p { margin: 0; font-size: 14px; line-height: 21px; white-space: pre-wrap; }
.pending { color: var(--color-ink-muted); font-style: italic; }
.sender {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.01em;
  color: var(--color-primary);
  margin-bottom: 4px;
}
.msg.inbound .sender { color: var(--color-ink-muted); }
.meta { margin-top: 6px; font-size: 11px; color: var(--color-ink-muted); }
.fail { color: var(--color-error); }
.type {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: var(--color-ink-muted);
  margin-bottom: 4px;
  text-transform: uppercase;
}
.image-wrap, .sticker-wrap, .video-wrap { display: flex; flex-direction: column; gap: 6px; }
.media-btn {
  appearance: none;
  border: 0;
  padding: 0;
  background: transparent;
  cursor: zoom-in;
  text-align: left;
  position: relative;
}
.chat-image {
  display: block;
  max-width: min(280px, 100%);
  max-height: 320px;
  border-radius: 6px;
  object-fit: contain;
  background: #f8fafc;
}
.sticker {
  display: block;
  width: 120px;
  height: 120px;
  object-fit: contain;
}
.video-btn { cursor: pointer; }
.chat-video {
  display: block;
  max-width: min(280px, 100%);
  border-radius: 6px;
  background: #000;
}
.play-badge {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  color: #fff;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.45);
  pointer-events: none;
}
.chat-audio { width: min(260px, 100%); margin-top: 4px; }
.file-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 2px;
  padding: 8px 10px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-outline-variant);
  background: color-mix(in srgb, var(--color-surface) 55%, white);
  font-size: 13px;
  font-weight: 600;
  color: var(--color-primary);
  text-decoration: none;
}
.file-link:hover { background: #eef7f0; }
.lightbox-card {
  background: #0b1220;
  color: #fff;
  overflow: hidden;
}
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
.lightbox-image,
.lightbox-video {
  max-width: 100%;
  max-height: min(75vh, 760px);
  border-radius: 4px;
}
.note-card {
  margin: 4px auto; width: min(90%, 640px); background: #fff9ef;
  border: 1px solid #edd9a8; border-radius: var(--radius-xl); padding: 11px 13px;
  box-shadow: var(--shadow-xs);
}
.note-label {
  display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: #8a5a12; margin-bottom: 4px;
  letter-spacing: 0.02em;
}
.note-card p { margin: 0 0 8px; font-size: 13px; white-space: pre-wrap; }
.note-media { margin: 6px 0; }
.note-files {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 6px 0;
}
.note-card .meta {
  margin-top: 6px;
  font-size: 11px;
  color: #a16207;
}
</style>
