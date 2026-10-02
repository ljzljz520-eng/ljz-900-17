<template>
  <div class="employees-page">
    <header class="page-header">
      <h1 class="page-title">员工管理</h1>
      <p class="page-desc">管理员工 ID、姓名、token 与二维码，可查看检查/整改数量，并停用或重新启用链接</p>
    </header>

    <section v-loading="loading" class="employees-section">
      <div class="toolbar">
        <el-button type="primary" @click="openCreate">
          <el-icon><Plus /></el-icon>
          新增员工
        </el-button>
      </div>

      <div v-if="users.length === 0 && !loading" class="empty-state">
        <div class="empty-icon">
          <el-icon><User /></el-icon>
        </div>
        <p class="empty-text">暂无员工</p>
        <p class="empty-hint">请先在数据库中添加员工</p>
      </div>

      <div v-for="u in users" :key="u.id" class="employee-card" :class="{ 'is-disabled': !u.is_active }">
        <div class="employee-header">
          <div class="employee-avatar">{{ (u.name || '员')[0] }}</div>
          <div class="employee-info">
            <h2 class="employee-name">{{ u.name }}</h2>
            <div class="employee-ids">
              <span class="id-badge">ID: {{ u.id }}</span>
              <span class="token-badge" :title="u.token">token: {{ u.token }}</span>
              <el-tag v-if="u.is_active" type="success" size="small">启用中</el-tag>
              <el-tag v-else type="danger" size="small">已停用</el-tag>
            </div>
          </div>
          <div class="employee-counts">
            <div class="count-item">
              <span class="count-value">{{ u.check_count ?? 0 }}</span>
              <span class="count-label">检查数量</span>
            </div>
            <div class="count-divider"></div>
            <div class="count-item">
              <span class="count-value success">{{ u.fix_count ?? 0 }}</span>
              <span class="count-label">整改数量</span>
            </div>
          </div>
        </div>
        <div v-if="!u.is_active" class="disabled-banner">
          <el-icon><CircleClose /></el-icon>
          <span>链接已停用：员工通过旧二维码或链接将无法查看记录与上传整改图</span>
        </div>
        <div class="employee-actions">
          <el-button type="primary" size="default" :disabled="!u.is_active" @click="generateQr(u)">
            <el-icon><PictureFilled /></el-icon>
            重新生成二维码
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
        <div v-if="u.fixLink" class="employee-result">
          <div class="result-row">
            <label>整改链接：</label>
            <el-input v-model="u.fixLink" readonly size="default" class="result-input">
              <template #append>
                <el-button type="primary" @click="copyText(u.fixLink)">复制</el-button>
              </template>
            </el-input>
          </div>
          <div v-if="u.qr_code_url" class="result-qr-row">
            <label>二维码：</label>
            <img :src="imageUrl(u.qr_code_url)" alt="二维码" class="qr-thumb" :class="{ 'qr-disabled': !u.is_active }" />
          </div>
        </div>
      </div>
    </section>

    <el-dialog v-model="dialogVisible" :title="dialogMode === 'create' ? '新增员工' : '编辑员工'" width="420px" append-to-body>
      <el-form :model="form" label-width="90px">
        <el-form-item label="姓名">
          <el-input v-model="form.name" placeholder="请输入员工姓名" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="submitForm">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { User, PictureFilled, Plus, Edit, SwitchButton, Refresh, CircleClose } from '@element-plus/icons-vue'
import { api, apiBase } from '@/api/request'

const loading = ref(false)
const users = ref([])
const dialogVisible = ref(false)
const dialogMode = ref('create') // create | edit
const saving = ref(false)
const form = ref({ id: null, name: '' })

function getFixLink(token) {
  const base = typeof window !== 'undefined' ? window.location.origin + '/fix' : 'http://localhost:3000/fix'
  return `${base}?token=${encodeURIComponent(token)}`
}

async function loadUsers() {
  loading.value = true
  try {
    const list = await api.getUsers()
    const all = list || []
    users.value = all.filter((u) => u.role === 'employee').map((u) => ({
      ...u,
      fixLink: getFixLink(u.token),
    }))
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
  try {
    const baseUrl = typeof window !== 'undefined' ? window.location.origin + '/fix' : 'http://localhost:3000/fix'
    const data = await api.generateQr(u.id, baseUrl)
    u.fixLink = data?.link || getFixLink(u.token)
    if (data?.qr_code_url) u.qr_code_url = data.qr_code_url
    ElMessage.success('二维码已生成')
  } catch (_) {
    ElMessage.error('生成失败')
  }
}

async function toggleActive(u) {
  const stopping = !!u.is_active
  try {
    await ElMessageBox.confirm(
      stopping
        ? '停用后，员工通过旧二维码或链接将无法查看记录与上传整改图，确定停用该链接？'
        : '启用后员工可继续通过链接或二维码上传整改图，确定启用？',
      stopping ? '确认停用链接' : '确认启用',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
      }
    )
    const updated = await api.toggleUserActive(u.id)
    u.is_active = updated?.is_active
    ElMessage.success(stopping ? '链接已停用' : '链接已启用')
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('操作失败')
  }
}

async function resetToken(u) {
  try {
    await ElMessageBox.confirm('重置 token 后，旧链接/二维码将失效，确定继续？', '确认重置', {
      confirmButtonText: '重置',
      cancelButtonText: '取消',
      type: 'warning',
    })
    const updated = await api.resetUserToken(u.id)
    u.token = updated?.token || u.token
    u.qr_code_url = updated?.qr_code_url || null
    u.fixLink = getFixLink(u.token)
    ElMessage.success('token 已重置')
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('重置失败')
  }
}

function copyText(text) {
  if (!text) return
  navigator.clipboard.writeText(text).then(() => ElMessage.success('已复制到剪贴板'))
}

function imageUrl(path) {
  if (!path) return ''
  const base = apiBase() || (typeof window !== 'undefined' ? window.location.origin : '')
  return path.startsWith('http') ? path : (base.replace(/\/$/, '') + path)
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

.toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 12px;
}

.page-header {
  margin-bottom: 32px;
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

.employees-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
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
  padding: 24px;
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
  border: 1px solid rgba(0, 0, 0, 0.04);
}

.employee-card.is-disabled {
  background: #fafafa;
  border-color: rgba(239, 68, 68, 0.25);
}

.employee-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
}

.employee-counts {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 16px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 10px 18px;
}

.count-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 64px;
}

.count-value {
  font-size: 22px;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.2;
}

.count-value.success {
  color: #10b981;
}

.count-label {
  font-size: 12px;
  color: #94a3b8;
  margin-top: 2px;
}

.count-divider {
  width: 1px;
  height: 32px;
  background: #e2e8f0;
}

.disabled-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.25);
  color: #dc2626;
  font-size: 13px;
  padding: 10px 14px;
  border-radius: 10px;
  margin-bottom: 16px;
}

.disabled-banner .el-icon {
  flex-shrink: 0;
  font-size: 16px;
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
}

.employee-info {
  flex: 1;
  min-width: 0;
}

.employee-name {
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 6px;
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
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.employee-actions {
  margin-bottom: 16px;
}

.result-row {
  margin-bottom: 12px;
}

.result-row label,
.result-qr-row label {
  display: block;
  font-size: 13px;
  color: #64748b;
  margin-bottom: 6px;
}

.result-input {
  max-width: 100%;
}

.result-qr-row img.qr-thumb {
  width: 140px;
  height: 140px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  object-fit: contain;
  background: white;
}

.result-qr-row img.qr-disabled {
  filter: grayscale(1);
  opacity: 0.45;
}

@media (max-width: 640px) {
  .employee-header {
    flex-wrap: wrap;
  }

  .employee-counts {
    margin-left: 0;
    width: 100%;
    justify-content: center;
  }
}
</style>
