import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      component: () => import('@/layouts/DefaultLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/inbox' },
        { path: 'dashboard', redirect: '/inbox' },
        { path: 'inbox', name: 'inbox', component: () => import('@/views/InboxView.vue') },
        { path: 'contacts', name: 'contacts', component: () => import('@/views/ContactsView.vue') },
        { path: 'contacts/:id', name: 'contact-detail', component: () => import('@/views/ContactDetailView.vue') },
        { path: 'line-accounts', name: 'line-accounts', component: () => import('@/views/LineAccountsView.vue') },
        { path: 'users', name: 'users', component: () => import('@/views/UsersView.vue') },
        { path: 'reports', name: 'reports', component: () => import('@/views/ReportsView.vue') },
        { path: 'settings', name: 'settings', component: () => import('@/views/SettingsView.vue') },
        { path: 'audit', name: 'audit', component: () => import('@/views/AuditView.vue') },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.user && to.meta.requiresAuth) {
    await auth.fetchMe()
  }
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'inbox' }
  }

  // Staff can only use Inbox.
  const isStaff = auth.user?.roles?.includes('Staff') ?? false
  if (isStaff && to.meta.requiresAuth) {
    const allowed = to.name === 'inbox' || to.path === '/' || to.path.startsWith('/inbox')
    if (!allowed) return { name: 'inbox' }
  }

  return true
})

export default router
