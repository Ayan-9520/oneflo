import { createContext, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'

import { apiData, ApiError, setApiOrganizationId } from '@/lib/api'

export type AccountOrganization = {
  id: number
  name: string
  slug: string
  permissions: string[]
}

export type Account = {
  id: number
  name: string
  email: string
  is_active: boolean
  email_verified: boolean
  two_factor_enabled: boolean
  platform_roles: string[]
  platform_permissions: string[]
  organizations: AccountOrganization[]
}

type SessionContextValue = {
  status: 'loading' | 'ready'
  account: Account | null
  organizationId: number | null
  organization: AccountOrganization | null
  domainLocked: boolean
  permissions: ReadonlySet<string>
  setOrganizationId: (organizationId: number) => void
  signIn: (email: string, password: string, oneTimePassword?: string) => Promise<void>
  signOut: () => Promise<void>
  refresh: () => Promise<void>
}

const SessionContext = createContext<SessionContextValue | null>(null)
const organizationStorageKey = 'oneflo.organization-id'

function isAccount(value: unknown): value is Account {
  if (typeof value !== 'object' || value === null) {
    return false
  }

  const account = value as Record<string, unknown>
  return typeof account.id === 'number' && typeof account.email === 'string' && Array.isArray(account.organizations)
}

function storedOrganizationId(organizations: AccountOrganization[]): number | null {
  const stored = sessionStorage.getItem(organizationStorageKey)
  const parsed = stored ? Number(stored) : null
  if (parsed && organizations.some((organization) => organization.id === parsed)) {
    return parsed
  }

  return organizations[0]?.id ?? null
}

type TenantDomain = {
  host: string | null
  locked: boolean
  organization: { id: number; name: string } | null
}

export function SessionProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<'loading' | 'ready'>('loading')
  const [account, setAccount] = useState<Account | null>(null)
  const [organizationId, setOrganizationIdState] = useState<number | null>(null)
  const [domainLocked, setDomainLocked] = useState(false)
  const domainLock = useRef<number | null>(null)

  function rememberOrganization(nextOrganizationId: number | null) {
    setOrganizationIdState(nextOrganizationId)
    setApiOrganizationId(nextOrganizationId)
    if (nextOrganizationId) {
      sessionStorage.setItem(organizationStorageKey, String(nextOrganizationId))
    } else {
      sessionStorage.removeItem(organizationStorageKey)
    }
  }

  function applyAccount(next: Account | null) {
    setAccount(next)
    const lockedId = domainLock.current
    const nextOrganizationId = lockedId ?? (next ? storedOrganizationId(next.organizations) : null)
    rememberOrganization(nextOrganizationId)
  }

  async function refresh() {
    try {
      const next = await apiData<unknown>('/api/v1/auth/me')
      applyAccount(isAccount(next) ? next : null)
    } catch (error) {
      if (error instanceof ApiError && (error.status === 401 || error.status === 419)) {
        applyAccount(null)
        return
      }
      applyAccount(null)
    } finally {
      setStatus('ready')
    }
  }

  useEffect(() => {
    let cancelled = false
    void (async () => {
      const [domainResult, accountResult] = await Promise.allSettled([
        apiData<TenantDomain>('/api/v1/tenant-domain'),
        apiData<unknown>('/api/v1/auth/me'),
      ])
      if (cancelled) {
        return
      }

      const domain = domainResult.status === 'fulfilled' ? domainResult.value : null
      const lockedId = domain?.locked && domain.organization ? domain.organization.id : null
      domainLock.current = lockedId
      setDomainLocked(lockedId !== null)

      if (accountResult.status === 'fulfilled' && isAccount(accountResult.value)) {
        applyAccount(accountResult.value)
      } else {
        applyAccount(null)
      }
      setStatus('ready')
    })()
    return () => {
      cancelled = true
    }
  }, [])

  const value = useMemo<SessionContextValue>(() => {
    const organization = account?.organizations.find((item) => item.id === organizationId) ?? null
    const permissions = new Set(account?.platform_permissions ?? [])
    organization?.permissions.forEach((permission) => permissions.add(permission))

    return {
      status,
      account,
      organizationId,
      organization,
      domainLocked,
      permissions,
      setOrganizationId: (next) => {
        if (domainLock.current !== null && next !== domainLock.current) {
          return
        }
        rememberOrganization(next)
      },
      signIn: async (email, password, oneTimePassword) => {
        const next = await apiData<unknown>('/api/v1/auth/login', {
          method: 'POST',
          body: JSON.stringify({
            email,
            password,
            one_time_password: oneTimePassword || undefined,
          }),
        })
        if (!isAccount(next)) {
          throw new ApiError('The sign-in response was incomplete.', 500)
        }
        applyAccount(next)
        setStatus('ready')
      },
      signOut: async () => {
        await apiData('/api/v1/auth/logout', { method: 'POST', body: JSON.stringify({}) })
        applyAccount(null)
      },
      refresh,
    }
  }, [account, domainLocked, organizationId, status])

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>
}

export function useSession() {
  const context = useContext(SessionContext)
  if (!context) {
    throw new Error('useSession must be used within SessionProvider')
  }
  return context
}

export function canAccess(access: 'everyone' | readonly string[], permissions: ReadonlySet<string>) {
  return access === 'everyone' || access.some((permission) => permissions.has(permission))
}
