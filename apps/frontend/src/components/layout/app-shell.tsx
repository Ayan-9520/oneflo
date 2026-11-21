import { Bell, Building2, Check, ChevronDown, ChevronLeft, ChevronRight, Menu, Search } from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router'

import { useSession } from '@/app/session'
import { visibleNavigation } from '@/app/navigation'
import { Button } from '@/components/ui/button'
import { Drawer } from '@/components/ui/drawer'
import { DropdownMenu } from '@/components/ui/dropdown-menu'
import { Tooltip } from '@/components/ui/tooltip'
import { NoticeBoard } from '@/features/workspace/notice-board'
import { cn } from '@/lib/utils'

function initials(name: string | undefined) {
  const parts = (name ?? 'A').trim().split(/\s+/).filter(Boolean)
  return (parts[0]?.[0] ?? 'A').concat(parts.length > 1 ? parts[parts.length - 1][0] : '').toUpperCase()
}

export function AppShell() {
  const { account, domainLocked, organization, permissions, setOrganizationId, signOut } = useSession()
  const items = visibleNavigation(permissions)
  const [collapsed, setCollapsed] = useState(false)
  const [mobileOpen, setMobileOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [searchOpen, setSearchOpen] = useState(false)
  const [notificationsOpen, setNotificationsOpen] = useState(false)
  const [signingOut, setSigningOut] = useState(false)
  const navigate = useNavigate()
  const location = useLocation()

  const matches = useMemo(() => {
    const term = query.trim().toLowerCase()
    if (!term) {
      return []
    }
    return items.filter((item) => item.label.toLowerCase().includes(term))
  }, [items, query])

  return (
    <div className="h-svh overflow-hidden bg-background">
      <div className="flex h-svh">
        <aside
          className={cn(
            'hidden h-svh shrink-0 flex-col bg-gradient-to-b from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] text-white lg:flex',
            collapsed ? 'w-[4.75rem]' : 'w-64',
          )}
        >
          <SidebarBody items={items} collapsed={collapsed} pathname={location.pathname} />
        </aside>

        <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-y-auto bg-[#f4f6fb]">
          <header className="sticky top-0 z-20 border-b border-border bg-card">
            <div className="flex h-16 items-center gap-2 px-3 sm:gap-3 sm:px-5">
              <Button className="lg:hidden" size="icon" variant="ghost" aria-label="Open navigation" onClick={() => setMobileOpen(true)}>
                <Menu className="size-5" />
              </Button>
              <Button
                className="hidden lg:inline-flex"
                size="icon"
                variant="ghost"
                aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                onClick={() => setCollapsed((current) => !current)}
              >
                {collapsed ? <ChevronRight className="size-4" /> : <ChevronLeft className="size-4" />}
              </Button>

              <div className="relative min-w-0 flex-1">
                <label className="relative block max-w-xl">
                  <span className="sr-only">Search navigation</span>
                  <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                  <input
                    value={query}
                    onChange={(event) => {
                      setQuery(event.target.value)
                      setSearchOpen(true)
                    }}
                    onFocus={() => setSearchOpen(true)}
                    placeholder="Search pages you can open"
                    className="h-10 w-full rounded-xl border border-border bg-[#f4f6fb] pr-4 pl-10 text-sm"
                  />
                </label>
                {searchOpen && query.trim() ? (
                  <div className="absolute z-30 mt-2 w-full max-w-xl rounded-lg border border-border bg-card p-1 shadow-[var(--shadow-card)]">
                    {matches.length === 0 ? (
                      <p className="px-3 py-2 text-sm text-muted-foreground">No visible pages match that search.</p>
                    ) : (
                      matches.map((item) => (
                        <button
                          key={item.id}
                          type="button"
                          className="flex w-full rounded-md px-3 py-2 text-left text-sm hover:bg-muted"
                          onClick={() => {
                            setSearchOpen(false)
                            setQuery('')
                            void navigate(item.to)
                          }}
                        >
                          {item.label}
                        </button>
                      ))
                    )}
                  </div>
                ) : null}
              </div>

              {domainLocked ? (
                <span className="inline-flex h-10 max-w-48 items-center gap-2 truncate rounded-xl border border-border bg-card px-3.5 text-sm font-semibold">
                  <Building2 className="size-4 shrink-0" />
                  {organization?.name ?? 'This address'}
                </span>
              ) : (
                <BusinessMenu
                  organizations={account?.organizations ?? []}
                  currentId={organization?.id ?? null}
                  currentName={organization?.name ?? 'Select a business'}
                  onSelect={setOrganizationId}
                />
              )}
              <Tooltip label="Notifications">
                <Button size="icon" variant="ghost" aria-label="Notifications" onClick={() => setNotificationsOpen(true)}>
                  <Bell className="size-4" />
                </Button>
              </Tooltip>
              <DropdownMenu
                label="Account menu"
                trigger={
                  <span title={account?.name ?? 'Account'} className="inline-flex h-10 max-w-64 items-center gap-2 rounded-full border border-border bg-card px-1.5 pr-3 text-sm font-semibold">
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#3a35e0] to-[#e23d9a] text-[11px] font-bold text-white">{initials(account?.name)}</span>
                    <span className="truncate">{account?.name ?? 'Account'}</span>
                  </span>
                }
                header={
                  <div className="mb-1 border-b border-border px-3 py-2.5">
                    <p className="text-sm font-semibold text-foreground">{account?.name ?? 'Account'}</p>
                    <p className="truncate text-xs text-muted-foreground">{account?.email}</p>
                  </div>
                }
                items={[
                  { id: 'help', label: 'Help center', hint: 'How this workspace is organized', onSelect: () => void navigate('/help') },
                  { id: 'design', label: 'Design system', hint: 'Interface components', onSelect: () => void navigate('/design-system') },
                  {
                    id: 'logout',
                    label: 'Sign out',
                    hint: 'Leave this account on this device',
                    tone: 'danger',
                    onSelect: () => {
                      setSigningOut(true)
                      void signOut()
                        .then(() => navigate('/login'))
                        .catch(() => setSigningOut(false))
                    },
                  },
                ]}
              />
            </div>
          </header>
          <main className="flex-1 px-4 py-5 sm:px-6 lg:px-7">
            <Outlet />
          </main>
        </div>
      </div>

      <Drawer open={mobileOpen} title="Navigation" side="left" onClose={() => setMobileOpen(false)}>
        <nav className="flex flex-col gap-1" aria-label="Mobile">
          {items.map((item) => (
            <NavLink
              key={item.id}
              to={item.to}
              end={item.to === '/'}
              onClick={() => setMobileOpen(false)}
              className={({ isActive }) =>
                cn('rounded-full px-3 py-2 text-sm', isActive ? 'bg-primary text-primary-foreground' : 'hover:bg-muted')
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>
      </Drawer>
      {signingOut ? (
        <div role="status" aria-live="polite" className="fixed inset-0 z-[90] flex items-center justify-center bg-[#120b2e]/80 px-6 text-white backdrop-blur-md">
          <div className="flex w-full max-w-sm flex-col items-center rounded-3xl border border-white/15 bg-white/10 px-8 py-10 text-center shadow-[0_30px_80px_rgb(8_4_30_/_0.45)]">
            <img src="/images/oneflo-mark.png" alt="" className="h-14 w-auto animate-pulse" />
            <p className="mt-5 text-lg font-extrabold">Signing out</p>
            <p className="mt-1 text-sm text-white/70">{account?.name ? `${account.name} is leaving this device.` : 'Closing this session.'}</p>
            <div className="mt-6 h-1 w-40 overflow-hidden rounded-full bg-white/15">
              <div className="h-full w-1/2 animate-[oneflo-signout_1.1s_ease-in-out_infinite] rounded-full bg-gradient-to-r from-[#7aa2ff] to-[#ff4fd8]" />
            </div>
          </div>
          <style>{'@keyframes oneflo-signout{0%{transform:translateX(-120%)}100%{transform:translateX(280%)}}'}</style>
        </div>
      ) : null}
      <Drawer open={notificationsOpen} title="Notifications" onClose={() => setNotificationsOpen(false)}>
        <NotificationList open={notificationsOpen} />
      </Drawer>
    </div>
  )
}

function SidebarBody({
  items,
  collapsed,
  pathname,
}: {
  items: ReturnType<typeof visibleNavigation>
  collapsed: boolean
  pathname: string
}) {
  return (
    <div className="flex h-full flex-col">
      <div className={cn('flex h-16 items-center gap-3 px-4', collapsed && 'justify-center px-2')}>
        <img src="/images/oneflo-mark.png" alt="OneFlo" className="h-8 w-auto shrink-0" />
        {collapsed ? null : (
          <span className="min-w-0">
            <span className="block text-sm font-extrabold leading-none text-white">OneFlo</span>
            <span className="mt-1 block truncate text-[10px] text-white/70">From job to workforce</span>
          </span>
        )}
      </div>
      <nav aria-label="Primary" className="flex flex-1 flex-col gap-1 overflow-y-auto px-3 pb-4">
        {items.map((item) => {
          const Icon = item.icon
          const active = item.to === '/' ? pathname === '/' : pathname.startsWith(item.to)
          const link = (
            <NavLink
              to={item.to}
              end={item.to === '/'}
              title={collapsed ? item.label : undefined}
              className={cn(
                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-white/85 hover:bg-white/10',
                active && 'bg-[#24156b]/55 font-semibold text-white shadow-sm',
                collapsed && 'justify-center px-2',
              )}
            >
              <Icon className="size-4 shrink-0" aria-hidden="true" />
              {collapsed ? <span className="sr-only">{item.label}</span> : item.label}
            </NavLink>
          )
          return collapsed ? (
            <Tooltip key={item.id} label={item.label}>
              {link}
            </Tooltip>
          ) : (
            <span key={item.id}>{link}</span>
          )
        })}
      </nav>
    </div>
  )
}

function BusinessMenu({
  organizations,
  currentId,
  currentName,
  onSelect,
}: {
  organizations: Array<{ id: number; name: string }>
  currentId: number | null
  currentName: string
  onSelect: (id: number) => void
}) {
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    const onPointerDown = (event: MouseEvent) => {
      if (!ref.current?.contains(event.target as Node)) setOpen(false)
    }
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setOpen(false)
    }
    document.addEventListener('mousedown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.removeEventListener('mousedown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open])

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        aria-haspopup="listbox"
        aria-expanded={open}
        className="inline-flex h-10 max-w-52 items-center gap-2 rounded-xl border border-border bg-card px-3 text-sm font-semibold"
        onClick={() => setOpen((current) => !current)}
      >
        <Building2 className="size-4 shrink-0 text-[#3a35e0]" />
        <span className="truncate">{currentName}</span>
        <ChevronDown className={cn('size-4 shrink-0 text-muted-foreground transition', open && 'rotate-180')} />
      </button>
      {open ? (
        <div role="listbox" className="absolute right-0 z-40 mt-2 w-64 rounded-2xl border border-border bg-card p-1.5 shadow-[0_16px_40px_rgb(28_20_51/0.12)]">
          <p className="px-3 py-2 text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">Business</p>
          {organizations.length === 0 ? <p className="px-3 py-2 text-sm text-muted-foreground">No businesses are assigned to this account.</p> : null}
          {organizations.map((item) => {
            const active = item.id === currentId
            return (
              <button
                key={item.id}
                type="button"
                role="option"
                aria-selected={active}
                className={cn('flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left text-sm', active ? 'bg-[#f3f1ff] font-semibold text-[#24156b]' : 'hover:bg-muted')}
                onClick={() => {
                  onSelect(item.id)
                  setOpen(false)
                }}
              >
                {item.name}
                {active ? <Check className="size-4 text-[#3a35e0]" /> : null}
              </button>
            )
          })}
        </div>
      ) : null}
    </div>
  )
}

function NotificationList({ open }: { open: boolean }) {
  if (!open) return null
  return <NoticeBoard />
}
