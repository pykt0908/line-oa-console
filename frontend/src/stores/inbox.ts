import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useInboxStore = defineStore('inbox', () => {
  const conversations = ref<any[]>([])
  const selectedId = ref<number | null>(null)
  const selected = ref<any | null>(null)
  const messages = ref<any[]>([])
  const notes = ref<any[]>([])
  const filters = ref<Record<string, any>>({
    q: '',
    status: '',
    unread: '',
    line_account_id: '',
    has_student_code: '',
    tag_id: '',
  })
  const tags = ref<any[]>([])
  const meta = ref({ total: 0, unread_total: 0 })
  const loading = ref(false)

  function recomputeUnreadTotal() {
    meta.value.unread_total = conversations.value.reduce((s: number, c: any) => {
      // Hide unread for the chat currently open.
      if (selectedId.value && c.id === selectedId.value) return s
      return s + (c.unread_count || 0)
    }, 0)
  }

  function clearSelectedUnreadLocally() {
    if (!selectedId.value) return
    const row = conversations.value.find((c) => c.id === selectedId.value)
    if (row) row.unread_count = 0
    recomputeUnreadTotal()
  }

  async function markSelectedRead() {
    if (!selectedId.value) return
    const id = selectedId.value
    clearSelectedUnreadLocally()
    try {
      await api.post(`/conversations/${id}/read`)
    } catch {
      // ignore; will retry when selecting again
    }
  }

  async function fetchConversations() {
    loading.value = true
    try {
      const params: Record<string, any> = {}
      Object.entries(filters.value).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined) params[k] = v
      })
      const { data } = await api.get('/conversations', { params })
      conversations.value = data.data || []
      meta.value.total = data.total || conversations.value.length

      // Keep the open chat visually read without wiping other chats' badges.
      clearSelectedUnreadLocally()
      recomputeUnreadTotal()
    } finally {
      loading.value = false
    }
  }

  async function fetchTags() {
    const { data } = await api.get('/tags')
    tags.value = data.data || []
  }

  async function deleteTag(tagId: number) {
    await api.delete(`/tags/${tagId}`)
    tags.value = tags.value.filter((t) => t.id !== tagId)
    if (String(filters.value.tag_id) === String(tagId)) {
      filters.value.tag_id = ''
    }
    if (selected.value?.tags) {
      selected.value = {
        ...selected.value,
        tags: selected.value.tags.filter((t: any) => t.id !== tagId),
      }
    }
    await fetchConversations()
  }

  async function selectConversation(id: number) {
    selectedId.value = id
    messages.value = []
    notes.value = []
    const [{ data: conv }, { data: msgs }, { data: noteData }] = await Promise.all([
      api.get(`/conversations/${id}`),
      api.get(`/conversations/${id}/messages`, { params: { limit: 50 } }),
      api.get(`/conversations/${id}/notes`),
    ])
    if (selectedId.value !== id) return
    selected.value = conv.data
    messages.value = msgs.data || []
    notes.value = noteData.data || []
    await markSelectedRead()
  }

  async function pollMessages() {
    if (!selectedId.value) return
    const lastId = messages.value.length ? messages.value[messages.value.length - 1].id : 0
    const { data } = await api.get(`/conversations/${selectedId.value}/messages`, {
      params: { after_id: lastId, limit: 50 },
    })
    if (data.data?.length) {
      messages.value.push(...data.data)
      const hasInbound = data.data.some((m: any) => m.direction === 'inbound')
      if (hasInbound) await markSelectedRead()
      else clearSelectedUnreadLocally()
    }
  }

  async function sendMessage(text: string) {
    if (!selectedId.value) return
    const { data } = await api.post(`/conversations/${selectedId.value}/messages`, { text })
    messages.value.push(data.data)
    await fetchConversations()
  }

  async function sendMedia(file: File, caption = '') {
    if (!selectedId.value) return
    const body = new FormData()
    body.append('file', file)
    if (caption.trim()) body.append('caption', caption.trim())
    const { data } = await api.post(`/conversations/${selectedId.value}/media`, body, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    messages.value.push(data.data)
    await fetchConversations()
    return data.data
  }

  async function sendNote(note: string, file?: File | null) {
    if (!selectedId.value) return
    const body = new FormData()
    if (note.trim()) body.append('note', note.trim())
    if (file) body.append('file', file)
    const { data } = await api.post(`/conversations/${selectedId.value}/notes`, body, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    notes.value.push(data.data)
    return data.data
  }

  async function loadOlder() {
    if (!selectedId.value || !messages.value.length) return
    const beforeId = messages.value[0].id
    const { data } = await api.get(`/conversations/${selectedId.value}/messages`, {
      params: { before_id: beforeId, limit: 50 },
    })
    if (data.data?.length) {
      messages.value = [...data.data, ...messages.value]
    }
  }

  return {
    conversations,
    selectedId,
    selected,
    messages,
    notes,
    filters,
    tags,
    meta,
    loading,
    fetchConversations,
    fetchTags,
    deleteTag,
    selectConversation,
    pollMessages,
    sendMessage,
    sendMedia,
    sendNote,
    loadOlder,
  }
})
