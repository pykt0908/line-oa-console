import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

let csrfReady = false

export async function ensureCsrf(): Promise<void> {
  if (csrfReady) return
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
  csrfReady = true
}

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401 || error.response?.status === 419) {
      csrfReady = false
      const loginPath = `${import.meta.env.BASE_URL}login`.replace(/\/{2,}/g, '/')
      if (!window.location.pathname.includes('/login')) {
        window.location.href = loginPath
      }
    }
    return Promise.reject(error)
  },
)

export default api
