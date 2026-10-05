import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api, { ensureCsrf } from '@/services/api'

export interface AuthUser {
  id: number
  name: string
  display_name?: string | null
  position?: string | null
  email: string
  roles: string[]
  permissions: string[]
  line_accounts: Array<{
    id: number
    name: string
    slug: string
    account_color?: string | null
    uuid: string
    is_active: boolean
  }>
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const loading = ref(false)

  const isAuthenticated = computed(() => !!user.value)
  const displayName = computed(() => user.value?.display_name || user.value?.name || '')

  async function fetchMe() {
    loading.value = true
    try {
      const { data } = await api.get('/me')
      user.value = data.user
      return user.value
    } catch {
      user.value = null
      return null
    } finally {
      loading.value = false
    }
  }

  async function login(email: string, password: string) {
    await ensureCsrf()
    const { data } = await api.post('/login', { email, password })
    user.value = data.user
    return user.value
  }

  async function logout() {
    try {
      await api.post('/logout')
    } finally {
      user.value = null
    }
  }

  function can(permission: string) {
    return user.value?.permissions?.includes(permission) ?? false
  }

  return { user, loading, isAuthenticated, displayName, fetchMe, login, logout, can }
})
