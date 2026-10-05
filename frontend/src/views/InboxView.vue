<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ChatHeader from '@/components/chat/ChatHeader.vue'
import ChatTimeline from '@/components/chat/ChatTimeline.vue'
import MessageComposer from '@/components/chat/MessageComposer.vue'
import ContactSidebar from '@/components/contacts/ContactSidebar.vue'
import ConversationList from '@/components/inbox/ConversationList.vue'
import { useAdaptivePolling } from '@/composables/useAdaptivePolling'
import { useInboxStore } from '@/stores/inbox'

const inbox = useInboxStore()
const route = useRoute()
const enabled = ref(true)

onMounted(async () => {
  if (route.query.q) inbox.filters.q = String(route.query.q)
  if (route.query.line_account_id) inbox.filters.line_account_id = String(route.query.line_account_id)
  await inbox.fetchConversations()
  // Keep previous selection if still in the list; otherwise wait for user to pick a chat
  // so unread badges remain visible.
  if (inbox.selectedId) {
    const stillThere = inbox.conversations.some((c) => c.id === inbox.selectedId)
    if (stillThere) await inbox.selectConversation(inbox.selectedId)
    else {
      inbox.selectedId = null
      inbox.selected = null
    }
  }
})

useAdaptivePolling(async () => {
  await inbox.fetchConversations()
  await inbox.pollMessages()
}, { activeMs: 3000, idleMs: 5000, hiddenMs: 25000, enabled })
</script>

<template>
  <div class="inbox">
    <ConversationList />
    <section class="stage">
      <template v-if="inbox.selected">
        <ChatHeader />
        <ChatTimeline />
        <MessageComposer />
      </template>
      <div v-else class="empty">
        <v-icon icon="mdi-inbox" size="48" color="outline" />
        <p>เลือกบทสนทนาจาก Inbox</p>
      </div>
    </section>
    <ContactSidebar />
  </div>
</template>

<style scoped>
.inbox { height: 100%; display: flex; min-width: 0; min-height: 0; }
.stage {
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--color-surface);
}
.empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  text-align: center;
  color: var(--color-ink-muted);
  background:
    radial-gradient(800px 320px at 70% 0%, color-mix(in srgb, var(--color-primary) 8%, transparent), transparent 60%),
    var(--color-surface);
}
.empty p {
  margin: 0;
}
@media (max-width: 1279px) {
  :deep(.sidebar) { display: none; }
}
</style>
