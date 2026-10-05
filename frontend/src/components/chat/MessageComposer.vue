<script setup lang="ts">
import { computed, ref } from 'vue'
import { useInboxStore } from '@/stores/inbox'

const inbox = useInboxStore()
const mode = ref<'line' | 'note'>('line')
const text = ref('')
const sending = ref(false)
const error = ref('')
const fileInput = ref<HTMLInputElement | null>(null)
const pendingFile = ref<File | null>(null)
const previewUrl = ref('')

const canSend = computed(() => Boolean(text.value.trim() || pendingFile.value))

const pendingLabel = computed(() => {
  if (!pendingFile.value) return ''
  const size = pendingFile.value.size
  const sizeLabel = size < 1024 * 1024
    ? `${(size / 1024).toFixed(1)} KB`
    : `${(size / (1024 * 1024)).toFixed(1)} MB`
  return `${pendingFile.value.name} (${sizeLabel})`
})

function openFilePicker() {
  if (sending.value) return
  fileInput.value?.click()
}

function onFilePicked(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  const maxBytes = 50 * 1024 * 1024
  if (file.size > maxBytes) {
    error.value = 'ไฟล์ต้องไม่เกิน 50MB'
    return
  }

  clearPendingFile()
  pendingFile.value = file
  error.value = ''
  if (file.type.startsWith('image/') || file.type.startsWith('video/')) {
    previewUrl.value = URL.createObjectURL(file)
  }
}

function clearPendingFile() {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = ''
  pendingFile.value = null
}

async function send() {
  if (!inbox.selectedId || !canSend.value) return
  sending.value = true
  error.value = ''
  try {
    if (mode.value === 'note') {
      await inbox.sendNote(text.value.trim(), pendingFile.value)
      text.value = ''
      clearPendingFile()
    } else if (pendingFile.value) {
      await inbox.sendMedia(pendingFile.value, text.value.trim())
      text.value = ''
      clearPendingFile()
    } else {
      await inbox.sendMessage(text.value.trim())
      text.value = ''
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'ส่งไม่สำเร็จ'
  } finally {
    sending.value = false
  }
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    void send()
  }
}
</script>

<template>
  <div class="composer">
    <div class="tabs">
      <button type="button" :class="{ active: mode === 'line' }" @click="mode = 'line'">ข้อความ LINE</button>
      <button type="button" :class="{ active: mode === 'note' }" @click="mode = 'note'">
        บันทึกภายใน
      </button>
    </div>
    <v-alert v-if="error" type="error" density="compact" class="mb-2" closable @click:close="error = ''">
      {{ error }}
    </v-alert>

    <div v-if="pendingFile" class="pending">
      <div class="pending-preview">
        <img v-if="pendingFile.type.startsWith('image/') && previewUrl" :src="previewUrl" alt="preview">
        <video v-else-if="pendingFile.type.startsWith('video/') && previewUrl" :src="previewUrl" muted />
        <v-icon v-else icon="mdi-file-outline" size="28" />
      </div>
      <div class="pending-meta">
        <strong>{{ pendingLabel }}</strong>
        <span>{{ mode === 'line' ? 'จะส่งไปยัง LINE OA' : 'จะแนบในบันทึกภายใน' }}</span>
      </div>
      <v-btn icon="mdi-close" variant="text" size="small" :disabled="sending" @click="clearPendingFile" />
    </div>

    <div class="box">
      <textarea
        v-model="text"
        rows="3"
        :placeholder="mode === 'line'
          ? (pendingFile ? 'คำบรรยายประกอบไฟล์ (ถ้ามี)...' : 'พิมพ์ข้อความตอบกลับ...')
          : (pendingFile ? 'คำอธิบายประกอบไฟล์ในบันทึก (ถ้ามี)...' : 'เขียนบันทึกภายใน (ไม่ส่งเข้า LINE)...')"
        @keydown="onKeydown"
      />
      <div class="actions">
        <div class="icons">
          <input
            ref="fileInput"
            type="file"
            class="file-input"
            accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.txt"
            @change="onFilePicked"
          >
          <v-btn
            icon="mdi-paperclip"
            variant="text"
            size="small"
            :disabled="sending"
            title="แนบรูป / วิดีโอ / ไฟล์"
            @click="openFilePicker"
          />
        </div>
        <v-btn color="primary" :loading="sending" :disabled="!canSend || sending" @click="send">
          {{ mode === 'line' ? (pendingFile ? 'ส่งไฟล์' : 'ส่งข้อความ') : (pendingFile ? 'บันทึกพร้อมไฟล์' : 'บันทึก') }}
        </v-btn>
      </div>
    </div>
  </div>
</template>

<style scoped>
.composer {
  border-top: 1px solid var(--color-outline-variant);
  background: var(--color-surface-lowest);
  padding: 12px 14px;
  flex-shrink: 0;
  box-shadow: 0 -1px 0 rgba(16, 24, 32, 0.02);
}
.tabs { display: flex; gap: 6px; margin-bottom: 8px; }
.tabs button {
  border: 1px solid var(--color-outline-variant);
  background: var(--color-surface-lowest);
  border-radius: var(--radius-md);
  padding: 5px 11px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  color: var(--color-ink-muted);
  transition: background 140ms ease, color 140ms ease, border-color 140ms ease;
}
.tabs button.active {
  background: var(--color-primary);
  color: #fff;
  border-color: var(--color-primary);
  box-shadow: 0 1px 2px color-mix(in srgb, var(--color-primary) 35%, transparent);
}
.pending {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
  padding: 8px 10px;
  border: 1px solid var(--color-primary-soft-border);
  background: var(--color-primary-soft);
  border-radius: var(--radius-md);
}
.pending-preview {
  width: 48px;
  height: 48px;
  border-radius: 6px;
  overflow: hidden;
  background: #e2e8f0;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.pending-preview img,
.pending-preview video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.pending-meta {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.pending-meta strong {
  font-size: 13px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.pending-meta span { font-size: 11px; color: var(--color-outline); }
.box {
  border: 1px solid var(--color-outline-variant);
  border-radius: var(--radius-lg);
  padding: 10px 12px;
  background: var(--color-surface-lowest);
  box-shadow: inset 0 1px 0 rgba(16, 24, 32, 0.015);
  transition: border-color 160ms ease, box-shadow 160ms ease;
}
.box:focus-within {
  border-color: color-mix(in srgb, var(--color-primary) 50%, var(--color-outline-variant));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 14%, transparent);
}
textarea {
  width: 100%; border: 0; resize: vertical; min-height: 72px; outline: none;
  font: inherit; font-size: 14px; line-height: 21px; color: var(--color-on-surface);
}
.actions { display: flex; justify-content: space-between; align-items: center; }
.icons { display: flex; gap: 2px; }
.file-input { display: none; }
</style>
