<script setup lang="ts">
import AppRail from '@/components/layout/AppRail.vue'
import AppTopBar from '@/components/layout/AppTopBar.vue'
</script>

<template>
  <div class="shell">
    <AppTopBar />
    <div class="workspace">
      <AppRail />
      <main class="main">
        <router-view />
      </main>
    </div>
  </div>
</template>

<style scoped>
.shell {
  height: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.workspace {
  flex: 1;
  display: flex;
  min-height: 0;
  height: calc(100vh - var(--app-topbar-height));
}
.main {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  background: var(--color-surface);
}
</style>
