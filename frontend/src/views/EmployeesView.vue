<template>
  <div class="employees-page">
    <header class="page-header">
      <h1 class="page-title">员工列表管理</h1>
      <p class="page-desc">管理员工 ID、token、整改二维码与状态；停用链接后，员工打开旧二维码将无法上传整改图</p>
    </header>

    <section v-loading="loading" class="employees-section">
      <div class="toolbar">
        <div class="toolbar-left">
          <el-input
            v-model="keyword"
            placeholder="搜索姓名 / token / ID"
            clearable
            class="search-input"
          >
            <template #prefix>
              <el-icon><Search /></el-icon>
            </template>
          </el-input>
          <el-radio-group v-model="statusFilter">
            <el-radio-button label="all">全部</el-radio-button>
            <el-radio-button label="active">启用中</el-radio-button>
            <el-radio-button label="disabled">已停用</el-radio-button>
          </el-radio-group>
        </div>
        <el-button type="primary" @click="openCreate">
          <el-icon><Plus /></el-icon>
          新增员工
        </el-button>
      </div>

      <div v-if="filteredUsers.length === 0 && !loading" class="empty-state">
        <div class="empty-icon">
          <el-icon><User /></el-icon>
        </div>
        <p class="empty-text">暂无员工</p>
        <p class="empty-hint">点击右上角「新增员工」创建</p>
      </div>

      <div v-for="u in filteredUsers" :key="u.id" class="employee-card" :class="{ disabled: !u.is_active }">
        <div class="employee-main">
          <div class="employee-avatar">{{ (u.name || '员')[0] }}</div>
          <div class="employee-info">
            <div class="employee-title-row">
              <h2 class="employee-name">{{ u.name }}</h2>
              <el-tag :type="u.is_active ? 'success' : 'info'" size="small" effect="light">
                {{ u.is_active ? '启用中' : '已停用' }}
              </el-tag>
            </div>
            <div class="employee-ids">
              <span class="id-badge">ID: {{ u.id }}</span>
              <span class="token-badge" :title="u.token">token: {{ u.token }}</span>
            </div>
          </div>

          <div class="employee-stats">
            <div class="stat-box">
              <span class="stat-num">{{ u.check_count ?? 0 }}</span>
              <span class="stat-label">检查数量</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-box">
              <span class="stat-num success">{{ u.fix_count ?? 0 }}</span>
              <span class="stat-label">整改数量</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-box">
              <span class="stat-num muted">{{ Math.max((u.check_count ?? 0) - (u.fix_count ?? 0), 0) }}</span>
              <span class="stat-label">待整改</span>
            </div>
          </div>
        </div>

        <div class="employee-actions">
          <el-button type="primary" size="default" :disabled="!u.is_active" @click="generateQr(u)">
            <el-icon><PictureFilled /></el-icon>
            重新生成二维码
          </el-button>
          <el-button size="default" @click="showQr(u)">
            <el-icon><View /></el-icon>
            查看二维码
          </el-button>
          <el-button size="default" @click="openEdit(u)">
            <el-icon><Edit /></el-icon>
            编辑
          </el-button>
          <el-button :type="u.is_active ? 'danger' : 'success'" plain size="default" @click="toggleActive(u)">
            <el-icon><SwitchButton /></el-icon>
            {{ u.is_active ? '停用链接' : '启用链接' }}
          </el-button>
          <el-button type="warning" plain size="default" @click="resetToken(u)">
            <el-icon><Refresh /></el-icon>
            重置 token
          </el-button>
        </div>

        <div v-if="!u.is_active" class="disabled-note">
          <el-icon><WarningFilled /></el-icon>
          <span>链接已停用：该员工打开旧二维码/链接时将无法查看记录或上传整改图。</span>
        </div>

        <div v-if="u.fixLink" class="employee-result">
          <div class="result-row">
            <label>整改链接：</label>
            <el-input :model-value="u.fixLink" readonly size="default" class="result-input">
              <template #append>
                <el-button type="primary" @click="copyText(u.fixLink)">复制</el-button>
              </template>
            </el-input>
          </div>
          <div v-if="u.qr_code_url" class="result-qr-row">
            <label>当前二维码：</label>
            <img :src="imageUrl(u.qr_code_url)" alt="二维码" class="qr-thumb" />
          </div>
        </div>
      </div>
    </section>

    <!-- 新增 / 编辑员工 -->
    <el-dialog v-model="dialogVisible" :title="dialogMode === 'create' ? '新增员工' : '编辑员工'" width="420px" append-to-body>
      <el-form :model="form" label-width="90px" @submit.prevent>
        <el-form-item label="姓名">
          <el-input v-model="form.name" placeholder="请输入员工姓名" maxlength="64" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="submitForm">保存</el-button>
      </template>
    </el-dialog>

    <!-- 二维码大图 -->
    <el-dialog v-model="qrDialogVisible" title="员工整改二维码" width="380px" append-to-body>
      <div v-if="qrUser" class="qr-dialog-body">
        <p class="qr-dialog-name">{{ qrUser.name }}（ID: {{ qrUser.id }}）</p>
        <div class="qr-dialog-img-wrap">
          <img v-if="qrUser.qr_code_url" :src="imageUrl(qrUser.qr_code_url)" alt="二维码" class="qr-dialog-img" />
          <div v-else class="qr-dialog-empty">
            <el-icon><PictureFilled /></el-icon>
            <span>尚未生成二维码</span>
          </div>
        </div>
        <el-input :model-value="qrUser.fixLink" readonly size="small">
          <template #append>
            <el-button type="primary" @click="copyText(qrUser.fixLink)">复制链接</el-button>
          </template>
        </el-input>
        <el-button type="primary" class="qr-dialog-btn" :loading="qrGenerating" :disabled="!qrUser.is_active" @click="generateQr(qrUser)">
          重新生成二维码
        </el-button>
        <p v-if="!qrUser.is_active" class="qr-dialog-warn">链接已停用，重新启用后才能生成有效二维码</p>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  User,
  PictureFilled,
  Plus,
  Edit,
  SwitchButton,
  Refresh,
  View,
  Search,
  WarningFilled,
} from '@element-plus/icons-vue'
import { api, apiBase } from '@/api/request'

const loading = ref(false)
const users = ref([])
const keyword = ref('')
const statusFilter = ref('all')

const dialogVisible = ref(false)
const dialogMode = ref('create') // create | edit
const saving = ref(false)
const form = ref({ id: null, name: '' })

const qrDialogVisible = ref(false)
const qrUser = ref(null)
const qrGenerating = ref(false)

function getFixLink(token) {
  const base = typeof window !== 'undefined' ? window.location.origin + '/fix' : 'http://localhost:3000/fix'
  return `${base}?token=${encodeURIComponent(token)}`
}

const filteredUsers = computed(() => {
  const kw = keyword.value.trim().toLowerCase()
  return users.value.filter((u) => {
    if (statusFilter.value === 'active' && !u.is_active) return false
    if (statusFilter.value === 'disabled' && u.is_active) return false
    if (!kw) return true
    return (
      String(u.id) === kw ||
      (u.name || '').toLowerCase().includes(kw) ||
      (u.token || '').toLowerCase().includes(kw)
    )
  })
})

async function loadUsers() {
  loading.value = true
  try {
    const list = await api.getUsers()
    users.value = (list || []).map((u) => ({ ...u, fixLink: getFixLink(u.token) }))
  } catch (_) {
    users.value = []
  } finally {
    loading.value = false
  }
}

function openCreate() {
  dialogMode.value = 'create'
  form.value = { id: null, name: '' }
  dialogVisible.value = true
}

function openEdit(u) {
  dialogMode.value = 'edit'
  form.value = { id: u.id, name: u.name || '' }
  dialogVisible.value = true
}

async function submitForm() {
  const name = (form.value.name || '').trim()
  if (!name) {
    ElMessage.warning('请输入姓名')
    return
  }
  saving.value = true
  try {
    if (dialogMode.value === 'create') {
      await api.createUser(name)
      ElMessage.success('员工已创建')
    } else {
      await api.updateUser(form.value.id, { name })
      ElMessage.success('员工已更新')
    }
    dialogVisible.value = false
    await loadUsers()
  } catch (_) {
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

async function generateQr(u) {
  if (!u.is_active) {
    ElMessage.warning('链接已停用，请先启用')
    return
  }
  qrGenerating.value = true
  try {
    const baseUrl = (typeof window !== 'undefined' ? window.location.origin : 'http://localhost:3000') + '/fix'
    const data = await api.generateQr(u.id, baseUrl)
    u.fixLink = data?.link || getFixLink(u.token)
    if (data?.qr_code_url) u.qr_code_url = data.qr_code_url
    ElMessage.success('二维码已重新生成')
  } catch (_) {
    ElMessage.error('生成失败')
  } finally {
    qrGenerating.value = false
  }
}

function showQr(u) {
  qrUser.value = u
  qrDialogVisible.value = true
}

async function toggleActive(u) {
  const willDisable = !!u.is_active
  try {
    await ElMessageBox.confirm(
      willDisable
        ? `确定停用「${u.name}」的整改链接吗？停用后其旧二维码/链接将无法查看记录或上传整改。`
        : `确定启用「${u.name}」的整改链接吗？`,
      willDisable ? '停用链接' : '启用链接',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: willDisable ? 'warning' : 'info',
      }
    )
    const updated = await api.toggleUserActive(u.id)
    u.is_active = updated?.is_active
    ElMessage.success(willDisable ? '链接已停用' : '链接已启用')
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('操作失败')
  }
}

async function resetToken(u) {
  try {
    await ElMessageBox.confirm(
      '重置 token 后，旧链接与旧二维码立即失效，需要重新生成二维码。确定继续？',
      '重置 token',
      { confirmButtonText: '重置', cancelButtonText: '取消', type: 'warning' }
    )
    const updated = await api.resetUserToken(u.id)
    u.token = updated?.token || u.token
    u.qr_code_url = updated?.qr_code_url || null
    u.fixLink = getFixLink(u.token)
    ElMessage.success('token 已重置，请重新生成二维码')
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('重置失败')
  }
}

function copyText(text) {
  if (!text) return
  const done = () => ElMessage.success('已复制到剪贴板')
  if (navigator.clipboard?.writeText) {
    navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopy(text, done))
  } else {
    fallbackCopy(text, done)
  }
}

function fallbackCopy(text, done) {
  const el = document.createElement('textarea')
  el.value = text
  el.style.position = 'fixed'
  el.style.opacity = '0'
  document.body.appendChild(el)
  el.select()
  try {
    document.execCommand('copy')
    done()
  } catch {
    ElMessage.warning('复制失败，请手动复制')
  } finally {
    document.body.removeChild(el)
  }
}

function imageUrl(path) {
  if (!path) return ''
  const base = apiBase() || (typeof window !== 'undefined' ? window.location.origin : '')
  return path.startsWith('http') ? path : base.replace(/\/$/, '') + path
}

onMounted(loadUsers)
</script>

<script>
export default {
  name: 'EmployeesView',
}
</script>

<style scoped>
.employees-page {
  max-width: 1120px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 28px;
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 8px;
  letter-spacing: -0.02em;
}

.page-desc {
  font-size: 15px;
  color: #64748b;
  margin: 0;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.toolbar-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search-input {
  width: 240px;
}

.employees-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.empty-state {
  background: white;
  border-radius: 16px;
  padding: 80px 40px;
  text-align: center;
  border: 2px dashed #e2e8f0;
}

.empty-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 24px;
  border-radius: 20px;
  background: #f1f5f9;
  color: #94a3b8;
  font-size: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-text {
  font-size: 18px;
  font-weight: 500;
  color: #64748b;
  margin: 0 0 8px;
}

.empty-hint {
  font-size: 14px;
  color: #94a3b8;
  margin: 0;
}

.employee-card {
  background: white;
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
  border: 1px solid rgba(0, 0, 0, 0.04);
}

.employee-card.disabled {
  background: #f8fafc;
  border-color: #e2e8f0;
}

.employee-main {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.employee-avatar {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #0ea5e9, #06b6d4);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 20px;
  flex-shrink: 0;
}

.employee-card.disabled .employee-avatar {
  background: linear-gradient(135deg, #94a3b8, #cbd5e1);
}

.employee-info {
  flex: 1;
  min-width: 220px;
}

.employee-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}

.employee-name {
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.employee-ids {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.id-badge {
  background: rgba(14, 165, 233, 0.12);
  color: #0ea5e9;
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
}

.token-badge {
  background: #f1f5f9;
  color: #64748b;
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 12px;
  max-width: 260px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.employee-stats {
  display: flex;
  align-items: center;
  gap: 18px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 10px 18px;
}

.employee-card.disabled .employee-stats {
  opacity: 0.7;
}

.stat-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 56px;
}

.stat-num {
  font-size: 20px;
  font-weight: 700;
  color: #0ea5e9;
  line-height: 1.2;
}

.stat-num.success {
  color: #10b981;
}

.stat-num.muted {
  color: #f59e0b;
}

.stat-label {
  font-size: 12px;
  color: #94a3b8;
  margin-top: 2px;
}

.stat-divider {
  width: 1px;
  height: 28px;
  background: #e2e8f0;
}

.employee-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 4px;
}

.disabled-note {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 10px 0 4px;
  padding: 8px 12px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  border-radius: 10px;
  font-size: 13px;
}

.employee-result {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px dashed #e2e8f0;
  display: flex;
  align-items: flex-start;
  gap: 24px;
  flex-wrap: wrap;
}

.result-row {
  flex: 1;
  min-width: 280px;
}

.result-row label,
.result-qr-row label {
  display: block;
  font-size: 13px;
  color: #64748b;
  margin-bottom: 6px;
}

.result-qr-row img.qr-thumb {
  width: 120px;
  height: 120px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  object-fit: contain;
  background: white;
}

.qr-dialog-body {
  text-align: center;
}

.qr-dialog-name {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 14px;
}

.qr-dialog-img-wrap {
  display: flex;
  justify-content: center;
  margin-bottom: 14px;
}

.qr-dialog-img {
  width: 240px;
  height: 240px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  object-fit: contain;
  background: white;
}

.qr-dialog-empty {
  width: 240px;
  height: 240px;
  border-radius: 12px;
  border: 2px dashed #e2e8f0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: #94a3b8;
  font-size: 13px;
}

.qr-dialog-btn {
  width: 100%;
  margin-top: 12px;
}

.qr-dialog-warn {
  margin: 10px 0 0;
  font-size: 12px;
  color: #dc2626;
}
</style>
