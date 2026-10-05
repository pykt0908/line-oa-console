<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import MediaHistoryDialog from '@/components/contacts/MediaHistoryDialog.vue'
import api from '@/services/api'
import { useInboxStore } from '@/stores/inbox'

const inbox = useInboxStore()
const studentCode = ref('')
const note = ref('')
const mergeCandidate = ref<any>(null)
const message = ref('')
const messageType = ref<'success' | 'warning' | 'error'>('success')
const newTagName = ref('')
const showCreateTag = ref(false)
const tagging = ref(false)
const savingNote = ref(false)
const deleteDialog = ref(false)
const tagPendingDelete = ref<any>(null)
const mediaDialog = ref(false)
let messageTimer: number | null = null
let lastConversationId: number | null = null

const attachedTagIds = computed(() => new Set((inbox.selected?.tags || []).map((t: any) => String(t.id))))

function showMessage(text: string, type: 'success' | 'warning' | 'error' = 'success', ms = 4000) {
  if (messageTimer) window.clearTimeout(messageTimer)
  message.value = text
  messageType.value = type
  if (ms > 0) {
    messageTimer = window.setTimeout(() => {
      message.value = ''
      messageTimer = null
    }, ms)
  }
}

watch(
  () => inbox.selected,
  (c) => {
    const convId = c?.id ?? null
    const switched = convId !== lastConversationId
    lastConversationId = convId
    studentCode.value = c?.contact?.student_code || ''
    note.value = c?.contact?.note || ''
    if (switched) {
      mergeCandidate.value = null
      if (messageTimer) window.clearTimeout(messageTimer)
      message.value = ''
    }
  },
  { immediate: true },
)

onMounted(() => {
  void inbox.fetchTags()
})

onUnmounted(() => {
  if (messageTimer) window.clearTimeout(messageTimer)
})

function onStudentCodeInput(value: string | number | null) {
  const digits = String(value ?? '').replace(/\D/g, '').slice(0, 11)
  studentCode.value = digits
}

async function saveStudentCode(merge = false) {
  if (!inbox.selected?.contact?.id) return
  if (!/^\d{1,11}$/.test(studentCode.value)) {
    showMessage('รหัสนักศึกษาต้องเป็นตัวเลข ไม่เกิน 11 หลัก', 'error')
    return
  }
  try {
    const { data } = await api.post(`/contacts/${inbox.selected.contact.id}/student-code`, {
      student_code: studentCode.value,
      merge,
    })
    mergeCandidate.value = null
    await inbox.selectConversation(inbox.selected.id)
    showMessage(
      data.status === 'merged' ? 'เชื่อมข้อมูลสำเร็จ' : 'บันทึกรหัสนักศึกษาแล้ว',
      'success',
      4000,
    )
  } catch (e: any) {
    if (e?.response?.status === 409) {
      mergeCandidate.value = e.response.data.existing_contact
      showMessage(e.response.data.message, 'warning', 0)
    } else {
      showMessage(
        e?.response?.data?.errors?.student_code?.[0]
          || e?.response?.data?.message
          || 'บันทึกไม่สำเร็จ',
        'error',
      )
    }
  }
}

async function saveNote() {
  if (!inbox.selected?.contact?.id || savingNote.value) return
  savingNote.value = true
  try {
    await api.patch(`/contacts/${inbox.selected.contact.id}`, { note: note.value })
    if (inbox.selected.contact) inbox.selected.contact.note = note.value
    showMessage('บันทึกหมายเหตุแล้ว', 'success')
  } catch (e: any) {
    showMessage(e?.response?.data?.message || 'บันทึกหมายเหตุไม่สำเร็จ', 'error')
  } finally {
    savingNote.value = false
  }
}

async function refreshAfterTagChange(conversation: any) {
  inbox.selected = conversation
  const id = conversation?.id || inbox.selectedId
  await Promise.all([inbox.fetchTags(), inbox.fetchConversations()])
  if (id) {
    const row = inbox.conversations.find((c) => c.id === id)
    if (row?.tags) inbox.selected = { ...inbox.selected, tags: row.tags }
  }
}

async function attachExistingTag(tagId: number) {
  if (!inbox.selected || !tagId || tagging.value) return
  tagging.value = true
  try {
    const { data } = await api.post(`/conversations/${inbox.selected.id}/tags`, {
      tag_id: tagId,
    })
    await refreshAfterTagChange(data.data)
    showMessage('ติดแท็กแล้ว', 'success')
  } catch (e: any) {
    showMessage(e?.response?.data?.message || 'ติดแท็กไม่สำเร็จ', 'error')
  } finally {
    tagging.value = false
  }
}

async function createAndAttachTag() {
  const name = newTagName.value.trim().replace(/^#/, '')
  if (!inbox.selected) return
  if (!name) {
    showMessage('กรุณาใส่ชื่อแท็ก', 'error')
    return
  }

  const existing = (inbox.tags || []).find(
    (t: any) => String(t.name).toLowerCase() === name.toLowerCase(),
  )
  tagging.value = true
  try {
    const { data } = await api.post(`/conversations/${inbox.selected.id}/tags`, existing
      ? { tag_id: existing.id }
      : { name })
    newTagName.value = ''
    showCreateTag.value = false
    await refreshAfterTagChange(data.data)
    showMessage(existing ? 'ติดแท็กที่มีอยู่แล้ว' : 'สร้างแท็กแล้ว', 'success')
  } catch (e: any) {
    showMessage(
      e?.response?.data?.message
        || e?.response?.data?.errors?.name?.[0]
        || 'สร้างแท็กไม่สำเร็จ',
      'error',
    )
  } finally {
    tagging.value = false
  }
}

async function removeTag(tag: any) {
  if (!inbox.selected) return
  tagging.value = true
  try {
    const { data } = await api.delete(`/conversations/${inbox.selected.id}/tags/${tag.id}`)
    await refreshAfterTagChange(data.data)
    showMessage('เอาแท็กออกจากแชทนี้แล้ว', 'success')
  } catch (e: any) {
    showMessage(e?.response?.data?.message || 'เอาแท็กออกไม่สำเร็จ', 'error')
  } finally {
    tagging.value = false
  }
}

function askDeleteSystemTag(tag: any) {
  if (!tag?.id || tagging.value) return
  tagPendingDelete.value = tag
  deleteDialog.value = true
}

function closeDeleteDialog() {
  if (tagging.value) return
  deleteDialog.value = false
  tagPendingDelete.value = null
}

async function confirmDeleteSystemTag() {
  const tag = tagPendingDelete.value
  if (!tag?.id) return
  tagging.value = true
  try {
    await inbox.deleteTag(tag.id)
    deleteDialog.value = false
    tagPendingDelete.value = null
    showMessage(`ลบแท็ก #${tag.name} ออกจากระบบแล้ว`, 'success')
  } catch (e: any) {
    showMessage(e?.response?.data?.message || 'ลบแท็กไม่สำเร็จ', 'error')
  } finally {
    tagging.value = false
  }
}
</script>

<template>
  <aside v-if="inbox.selected" class="sidebar custom-scrollbar">
    <div class="card profile">
      <v-avatar size="72">
        <v-img v-if="inbox.selected.line_identity?.picture_url" :src="inbox.selected.line_identity.picture_url" />
        <span v-else>{{ (inbox.selected.line_identity?.display_name || '?').slice(0, 1) }}</span>
      </v-avatar>
      <h3 class="typo-headline-lg">{{ inbox.selected.line_identity?.display_name }}</h3>
      <p class="typo-body-sm muted">LINE Display Name</p>

      <div class="profile-student">
        <div class="label">รหัสนักศึกษา</div>
        <div v-if="inbox.selected.contact?.student_code" class="code-row mb-2">
          <v-icon icon="mdi-shield-check" color="primary" size="18" />
          <strong>{{ inbox.selected.contact.student_code }}</strong>
          <span class="ok">ระบุรหัสแล้ว</span>
        </div>
        <div v-else class="warn mb-2">ยังไม่ระบุรหัสนักศึกษา</div>

        <v-text-field
          :model-value="studentCode"
          label="ใส่/แก้ไขรหัส (สูงสุด 11 หลัก)"
          type="text"
          inputmode="numeric"
          maxlength="11"
          counter="11"
          density="compact"
          hide-details="auto"
          class="code-input"
          @update:model-value="onStudentCodeInput"
          @keydown.enter.prevent="saveStudentCode(false)"
        >
          <template #append-inner>
            <v-btn
              icon="mdi-content-save"
              color="primary"
              size="x-small"
              variant="text"
              :disabled="!studentCode"
              title="บันทึกรหัสนักศึกษา"
              @click="saveStudentCode(false)"
            />
          </template>
        </v-text-field>
        <div v-if="mergeCandidate" class="merge-box mt-2">
          <p>พบรหัสนี้อยู่ที่ Contact #{{ mergeCandidate.id }} แล้ว ต้องการเชื่อมข้อมูลหรือไม่?</p>
          <v-btn color="primary" size="small" class="mr-2" @click="saveStudentCode(true)">เชื่อมข้อมูล</v-btn>
          <v-btn size="small" variant="outlined" @click="mergeCandidate = null">ยกเลิก</v-btn>
        </div>
      </div>

      <div class="profile-tags">
        <div class="label">แท็ก</div>
        <div class="tags">
          <v-chip
            v-for="tag in inbox.selected.tags || []"
            :key="tag.id"
            size="small"
            class="ma-1"
            closable
            :disabled="tagging"
            @click:close="removeTag(tag)"
          >
            #{{ tag.name }}
          </v-chip>
          <span v-if="!(inbox.selected.tags || []).length" class="muted typo-body-sm">ยังไม่มีแท็ก</span>
        </div>

        <div class="label mt-2">แท็กในระบบ</div>
        <div v-if="inbox.tags.length" class="tag-options">
          <div v-for="tag in inbox.tags" :key="tag.id" class="tag-option-wrap">
            <button
              type="button"
              class="tag-option"
              :class="{ 'tag-option--used': attachedTagIds.has(String(tag.id)) }"
              :disabled="tagging || attachedTagIds.has(String(tag.id))"
              @click="attachExistingTag(tag.id)"
            >
              #{{ tag.name }}
            </button>
            <button
              type="button"
              class="tag-delete"
              title="ลบแท็กออกจากระบบ"
              :disabled="tagging"
              @click="askDeleteSystemTag(tag)"
            >
              ×
            </button>
          </div>
        </div>
        <p v-else class="muted typo-body-sm">ยังไม่มีแท็กในระบบ</p>

        <button
          type="button"
          class="create-toggle"
          @click="showCreateTag = !showCreateTag"
        >
          {{ showCreateTag ? 'ยกเลิกการสร้างแท็กใหม่' : '+ สร้างแท็กใหม่' }}
        </button>
        <div v-if="showCreateTag" class="tag-create mt-2">
          <v-text-field
            v-model="newTagName"
            label="ชื่อแท็กใหม่"
            placeholder="เช่น ทุนการศึกษา"
            density="compact"
            hide-details="auto"
            :disabled="tagging"
            @keydown.enter.prevent="createAndAttachTag"
          />
          <v-btn
            class="mt-2"
            size="small"
            color="primary"
            block
            :loading="tagging && showCreateTag"
            :disabled="!newTagName.trim() || tagging"
            @click.prevent="createAndAttachTag"
          >
            สร้างและติดแท็ก
          </v-btn>
        </div>
      </div>

      <v-alert
        v-if="message"
        :type="messageType"
        density="compact"
        variant="tonal"
        class="mt-2"
        closable
        @click:close="message = ''"
      >
        {{ message }}
      </v-alert>
    </div>

    <div class="card media-card">
      <div class="note-head">
        <v-icon icon="mdi-folder-multiple-image" size="18" color="primary" />
        <div class="label note-label">ประวัติสื่อและไฟล์</div>
      </div>
      <p class="media-desc muted">ดูรูป วิดีโอ และไฟล์ที่เคยส่งในแชทนี้</p>
      <v-btn
        color="primary"
        variant="tonal"
        block
        size="small"
        prepend-icon="mdi-history"
        @click="mediaDialog = true"
      >
        ดูประวัติ
      </v-btn>
    </div>

    <div class="card note-card">
      <div class="note-head">
        <v-icon icon="mdi-notebook-outline" size="18" color="primary" />
        <div class="label note-label">หมายเหตุ Contact</div>
        <v-btn
          class="note-save"
          icon="mdi-content-save"
          color="primary"
          size="small"
          variant="tonal"
          :loading="savingNote"
          :disabled="savingNote"
          title="บันทึกหมายเหตุ"
          @click="saveNote"
        />
      </div>
      <v-textarea
        v-model="note"
        rows="3"
        density="compact"
        variant="outlined"
        hide-details
        placeholder="บันทึกข้อมูลเพิ่มเติมเกี่ยวกับ contact นี้..."
        class="note-input"
      />
    </div>

    <MediaHistoryDialog
      v-model="mediaDialog"
      :conversation-id="inbox.selected?.id"
      :contact-id="inbox.selected?.contact?.id"
      :contact-name="inbox.selected?.line_identity?.display_name"
    />

    <v-dialog v-model="deleteDialog" max-width="420" persistent>
      <v-card>
        <v-card-title class="text-h6">ลบแท็กออกจากระบบ?</v-card-title>
        <v-card-text>
          ต้องการลบแท็ก <strong>#{{ tagPendingDelete?.name }}</strong> ใช่หรือไม่
          แท็กนี้จะถูกเอาออกจากทุกแชท
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="tagging" @click="closeDeleteDialog">ยกเลิก</v-btn>
          <v-btn color="error" :loading="tagging" @click="confirmDeleteSystemTag">ลบแท็ก</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </aside>
</template>

<style scoped>
.sidebar {
  width: var(--sidebar-width); flex-shrink: 0; border-left: 1px solid var(--color-outline-variant);
  background: color-mix(in srgb, var(--color-surface) 45%, white);
  overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 10px;
}
.card {
  border: 1px solid var(--color-outline-variant);
  border-radius: var(--radius-lg);
  padding: 14px;
  background: var(--color-surface-lowest);
  box-shadow: var(--shadow-xs);
}
.profile { text-align: center; }
.profile-student,
.profile-tags {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #f1f5f9;
  text-align: left;
}
.profile-student .code-row { justify-content: flex-start; }
.code-input :deep(.v-field__append-inner) {
  align-items: center;
  padding-top: 0;
}
.tags { min-height: 28px; }
.mb-2 { margin-bottom: 8px; }
.mt-2 { margin-top: 8px; }
.muted { color: var(--color-outline); }
.label {
  font-size: 11px;
  font-weight: 700;
  color: var(--color-ink-muted);
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.code-row { display: flex; align-items: center; gap: 8px; }
.ok {
  background: var(--color-primary-soft);
  color: #0a5c2e;
  border: 1px solid var(--color-primary-soft-border);
  border-radius: var(--radius-sm);
  font-size: 10px;
  padding: 2px 6px;
  font-weight: 700;
}
.warn {
  background: #fff8eb;
  color: #92400e;
  border: 1px solid #f3dfb5;
  border-radius: var(--radius-md);
  padding: 6px 8px;
  font-size: 12px;
}
.kv { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px; }
.tag-options {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.tag-option-wrap {
  display: inline-flex;
  align-items: stretch;
  border-radius: 4px;
  overflow: hidden;
  border: 1px solid #99f6e4;
}
.tag-option {
  appearance: none;
  border: 0;
  background: #f0fdfa;
  color: #0f766e;
  padding: 4px 8px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}
.tag-option:hover:not(:disabled) {
  background: #ccfbf1;
}
.tag-option--used,
.tag-option:disabled {
  opacity: 0.55;
  cursor: default;
}
.tag-delete {
  appearance: none;
  border: 0;
  border-left: 1px solid #99f6e4;
  background: #fff;
  color: #b91c1c;
  padding: 0 7px;
  font-size: 14px;
  font-weight: 700;
  line-height: 1;
  cursor: pointer;
}
.tag-delete:hover:not(:disabled) {
  background: #fef2f2;
}
.tag-delete:disabled {
  opacity: 0.5;
  cursor: default;
}
.tag-create { text-align: left; }
.create-toggle {
  margin-top: 8px;
  border: 0;
  background: transparent;
  color: var(--color-primary);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  padding: 0;
  text-align: left;
}
.merge-box { font-size: 13px; }
.note-card,
.media-card { display: flex; flex-direction: column; gap: 4px; }
.note-head {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 8px;
}
.note-label { margin-bottom: 0; flex: 1; text-align: left; }
.media-desc {
  margin: 0 0 8px;
  font-size: 12px;
  line-height: 1.4;
}
.note-input :deep(.v-field) {
  background: #f8fafc;
}
.note-save { flex-shrink: 0; }
</style>
