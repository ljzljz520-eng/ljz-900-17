import axios from 'axios'
import { ElMessage } from 'element-plus'

const baseURL = import.meta.env.VITE_API_BASE || ''

export const request = axios.create({
  baseURL: baseURL || (typeof window !== 'undefined' ? window.location.origin : ''),
  timeout: 15000,
  headers: { 'Content-Type': 'application/json' },
})

request.interceptors.request.use((config) => {
  if (config.data instanceof FormData) {
    delete config.headers['Content-Type']
  }
  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

function shouldRedirectToLogin(config, code) {
  if (code !== 401) return false
  const url = config?.url || ''
  if (url.includes('/api/auth/login')) return false
  // 员工端通过链接 token 访问（无管理员登录态），401 表示链接失效而非登录过期，
  // 交由业务页面自行提示，不强制跳转登录
  if (url.includes('/api/records') || url.includes('/api/upload/image')) return false
  return true
}

request.interceptors.response.use(
  (res) => {
    const d = res.data
    if (d && typeof d.code === 'number' && d.code !== 0) {
      if (d.code === 401 && shouldRedirectToLogin(res.config, 401)) {
        localStorage.removeItem('auth_token')
        localStorage.removeItem('auth_user')
        if (!window.__auth_redirect) {
          window.__auth_redirect = true
          window.location.href = '/login'
        }
        return Promise.reject(new Error(d.message || '未登录'))
      }
      ElMessage.error(d.message || '请求失败')
      const err = new Error(d.message || '请求失败')
      err.code = d.code
      return Promise.reject(err)
    }
    return res
  },
  (err) => {
    const code = err.response?.data?.code
    if (code !== undefined) err.code = code
    if (shouldRedirectToLogin(err.config, code)) {
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
      if (!window.__auth_redirect) {
        window.__auth_redirect = true
        window.location.href = '/login'
      }
    }
    const msg = err.response?.data?.message || err.message || '网络错误'
    ElMessage.error(msg)
    return Promise.reject(err)
  }
)

export const api = {
  login: (username, password) => request.post('/api/auth/login', { username, password }).then((r) => r.data?.data),
  getMe: () => request.get('/api/auth/me').then((r) => r.data?.data),
  logout: () => request.post('/api/auth/logout').then(() => {}),
  getUsers: () => request.get('/api/users').then((r) => r.data?.data ?? []),
  getUser: (id) => request.get(`/api/users/${id}`).then((r) => r.data?.data),
  createUser: (name) => request.post('/api/users', { name }).then((r) => r.data?.data),
  updateUser: (id, data) => request.put(`/api/users/${id}`, data).then((r) => r.data?.data),
  resetUserToken: (id) => request.post(`/api/users/${id}/reset-token`).then((r) => r.data?.data),
  toggleUserActive: (id) => request.post(`/api/users/${id}/toggle-active`).then((r) => r.data?.data),
  getInspectionItems: () => request.get('/api/inspection-items').then((r) => r.data?.data ?? []),
  getRecords: (params) => request.get('/api/records', { params }).then((r) => r.data?.data ?? []),
  // 兼容两种返回：
  // 1) 旧：data = records[]
  // 2) 新：data = { records: records[], link, qr_code_url }
  createRecords: (data) => request.post('/api/records', data).then((r) => r.data?.data ?? []),
  deleteRecord: (id) => request.delete(`/api/records/${id}`).then((r) => r.data),
  uploadFix: (id, fixImage, token) =>
    request.put(`/api/records/${id}/fix`, { fix_image: fixImage, token }).then((r) => r.data?.data),
  uploadImage: (file, token) => {
    const form = new FormData()
    form.append('file', file)
    if (token) form.append('token', token)
    return request.post('/api/upload/image', form).then((r) => r.data?.data)
  },
  generateQr: (userId, baseUrl) => request.post('/api/qr/generate', { user_id: userId, base_url: baseUrl }).then((r) => r.data?.data),
  getSummary: (params) => request.get('/api/summary', { params }).then((r) => r.data?.data ?? []),
}

export function apiBase() {
  return baseURL || (typeof window !== 'undefined' ? window.location.origin : '')
}
