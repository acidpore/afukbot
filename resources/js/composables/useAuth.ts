import { ref, computed } from 'vue'
import axios from 'axios'

type PermissionMap = Record<string, {
  can_view: boolean
  can_create: boolean
  can_edit: boolean
  can_delete: boolean
}>

interface AuthUser {
  id: number
  name: string
  email: string
  role: 'super_admin' | 'admin'
  permissions: PermissionMap
}

const user = ref<AuthUser | null>(null)
const loaded = ref(false)

export function useAuth() {
  const isSuperAdmin = computed(() => user.value?.role === 'super_admin')

  function can(feature: string, action: 'view' | 'create' | 'edit' | 'delete' | 'adjust'): boolean {
    if (isSuperAdmin.value) return true
    const perm = user.value?.permissions?.[feature]
    if (!perm) return action === 'view'
    return perm[`can_${action}` as keyof typeof perm] as boolean
  }

  async function loadUser() {
    if (loaded.value) return
    try {
      const res = await axios.get('/auth/me')
      user.value = res.data.user
    } catch {
      user.value = null
    } finally {
      loaded.value = true
    }
  }

  function setUser(u: AuthUser) {
    user.value = u
    loaded.value = true
  }

  function clearUser() {
    user.value = null
    loaded.value = false
  }

  return { user, isSuperAdmin, loaded, can, loadUser, setUser, clearUser }
}
