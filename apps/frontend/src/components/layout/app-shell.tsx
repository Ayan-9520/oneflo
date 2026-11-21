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

export function AppShell() {
  const { account, domainLocked, organization, permissions, setOrganizationId, signOut } = useSession()
  const items = visibleNavigation(permissions)
  const [collapsed, setCollapsed] = useState(false)
  const [mobileOpen, setMobileOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [searchOpen, setSearchOpen] = useState(false)
  const [notificationsOpen, setNotificationsOpen] = useState(false)
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
                  <span className="inline-flex h-10 max-w-40 items-center truncate rounded-xl border border-border bg-card px-3.5 text-sm font-semibold">
                    {account?.name ?? 'Account'}
                  </span>
                }
                items={[
                  { id: 'help', label: 'Help center', onSelect: () => void navigate('/help') },
                  { id: 'design', label: 'Design system', onSelect: () => void navigate('/design-system') },
                  {
                    id: 'logout',
                    label: 'Sign out',
                    onSelect: () => {
                      void signOut().then(() => navigate('/login'))
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
        {collapsed ? (
          <img src="/images/oneflo-mark.png" alt="OneFlo" className="h-8 w-auto" />
        ) : (
          <img src="/images/oneflo-logo.jpg?v=2" alt="OneFlo — From job to workforce" className="h-10 w-auto max-w-full rounded-md bg-white" />
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
