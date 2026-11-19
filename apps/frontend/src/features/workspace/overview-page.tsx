import {
  CalendarCheck,
  CalendarDays,
  ClipboardCheck,
  FileText,
  Landmark,
  LifeBuoy,
  Megaphone,
  Receipt,
  Shield,
  Timer,
  Umbrella,
  UserRound,
  Users,
  Wallet,
  type LucideIcon,
} from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router'

import { canAccess } from '@/app/session'
import { useSession } from '@/app/session'

type Tile = {
  title: string
  caption: string
  to: string
  icon: LucideIcon
  tint: string
  access: 'everyone' | readonly string[]
}

const chips: Tile[] = [
  { title: 'Check in', caption: '', to: '/attendance', icon: CalendarCheck, tint: 'bg-[#3a35e0] text-white', access: ['attendance.view'] },
  { title: 'Directory', caption: '', to: '/people', icon: Users, tint: 'bg-white text-[#3a35e0]', access: ['employees.view'] },
  { title: 'Correction', caption: '', to: '/approvals', icon: ClipboardCheck, tint: 'bg-white text-[#3a35e0]', access: ['attendance.approve', 'leave.approve'] },
  { title: 'Timesheet', caption: '', to: '/attendance', icon: CalendarDays, tint: 'bg-white text-[#3a35e0]', access: ['attendance.view'] },
  { title: 'Letters', caption: '', to: '/letters', icon: FileText, tint: 'bg-white text-[#3a35e0]', access: ['documents.generate', 'recruitment.manage'] },
]

const sections: Array<{ title: string; action: string; to: string; tiles: Tile[] }> = [
  {
    title: 'Attendance and schedule',
    action: 'View attendance',
    to: '/attendance',
    tiles: [
      { title: 'Daily attendance', caption: 'Check records', to: '/attendance?view=punch', icon: CalendarCheck, tint: 'bg-sky-100 text-sky-700', access: ['attendance.view'] },
      { title: 'Monthly sheet', caption: 'Timesheet logs', to: '/attendance?view=month', icon: CalendarDays, tint: 'bg-indigo-100 text-indigo-700', access: ['attendance.view'] },
      { title: 'Correction', caption: 'Missed punches', to: '/approvals', icon: ClipboardCheck, tint: 'bg-amber-100 text-amber-700', access: ['attendance.approve', 'leave.approve'] },
      { title: 'Shift roster', caption: 'Weekly shifts', to: '/attendance?view=shifts', icon: Timer, tint: 'bg-emerald-100 text-emerald-700', access: ['attendance.view'] },
      { title: 'Overtime hours', caption: 'Track extra', to: '/attendance?view=month', icon: Timer, tint: 'bg-violet-100 text-violet-700', access: ['attendance.view'] },
      { title: 'Holiday list', caption: 'Public offs', to: '/attendance?view=holidays', icon: Umbrella, tint: 'bg-rose-100 text-rose-700', access: ['attendance.view'] },
    ],
  },
  {
    title: 'Requests and claims',
    action: 'All requests',
    to: '/approvals',
    tiles: [
      { title: 'Apply leave', caption: 'Time off', to: '/leave?view=apply', icon: Umbrella, tint: 'bg-emerald-100 text-emerald-700', access: ['leave.view'] },
      { title: 'Leave balance', caption: 'Saved balance', to: '/leave?view=balance', icon: CalendarDays, tint: 'bg-violet-100 text-violet-700', access: ['leave.view'] },
      { title: 'View payslip', caption: 'Locked payroll', to: '/payroll', icon: Wallet, tint: 'bg-amber-100 text-amber-700', access: ['payroll.view'] },
      { title: 'Add expense', caption: 'Reimbursement', to: '/expenses?view=submit', icon: Receipt, tint: 'bg-orange-100 text-orange-700', access: ['expenses.view', 'expenses.submit'] },
      { title: 'Expense claims', caption: 'Track status', to: '/expenses?view=claims', icon: Landmark, tint: 'bg-sky-100 text-sky-700', access: ['expenses.view', 'expenses.submit'] },
      { title: 'Loan request', caption: 'Salary advance', to: '/loans', icon: Landmark, tint: 'bg-fuchsia-100 text-fuchsia-700', access: 'everyone' },
    ],
  },
  {
    title: 'Workforce and team',
    action: 'Directory',
    to: '/people',
    tiles: [
      { title: 'My details', caption: 'Personal file', to: '/my-work', icon: UserRound, tint: 'bg-violet-100 text-violet-700', access: ['employees.view_own'] },
      { title: 'Team directory', caption: 'Colleagues', to: '/team', icon: Users, tint: 'bg-indigo-100 text-indigo-700', access: ['employees.view_team'] },
      { title: 'Org chart', caption: 'Hierarchy', to: '/org-chart', icon: Users, tint: 'bg-sky-100 text-sky-700', access: ['employees.view'] },
      { title: 'All employees', caption: 'Company staff', to: '/people', icon: Users, tint: 'bg-emerald-100 text-emerald-700', access: ['employees.view'] },
      { title: 'HR policies', caption: 'Handbook', to: '/policies', icon: Shield, tint: 'bg-rose-100 text-rose-700', access: ['organization.manage'] },
      { title: 'Give kudos', caption: 'Recognition', to: '/engagement?view=recognition', icon: Megaphone, tint: 'bg-amber-100 text-amber-700', access: ['engagement.view'] },
    ],
  },
  {
    title: 'Tools and helpdesk',
    action: 'Helpdesk',
    to: '/helpdesk',
    tiles: [
      { title: 'Assistant', caption: 'Open a saved page', to: '/assistant', icon: LifeBuoy, tint: 'bg-violet-100 text-violet-700', access: 'everyone' },
      { title: 'HR chat', caption: 'Helpdesk queries', to: '/helpdesk', icon: LifeBuoy, tint: 'bg-sky-100 text-sky-700', access: ['helpdesk.view'] },
      { title: 'Documents', caption: 'Letters and files', to: '/documents', icon: FileText, tint: 'bg-emerald-100 text-emerald-700', access: ['documents.view', 'documents.generate', 'branding.manage'] },
      { title: 'My tasks', caption: 'Open tickets', to: '/tasks', icon: ClipboardCheck, tint: 'bg-amber-100 text-amber-700', access: ['helpdesk.view'] },
      { title: 'Notifications', caption: 'Announcements and wishes', to: '/notifications', icon: Megaphone, tint: 'bg-rose-100 text-rose-700', access: 'everyone' },
      { title: 'Profile', caption: 'This account', to: '/profile', icon: UserRound, tint: 'bg-indigo-100 text-indigo-700', access: 'everyone' },
    ],
  },
]

export function OverviewPage() {
  const { organization, permissions } = useSession()
  const business = organization?.name?.trim() || 'This business'
  const [now, setNow] = useState(() => new Date())
  const allowed = (tile: Tile) => canAccess(tile.access, permissions)

  useEffect(() => {
    const timer = window.setInterval(() => setNow(new Date()), 30000)
    return () => window.clearInterval(timer)
  }, [])

  const time = new Intl.DateTimeFormat('en-IN', { hour: 'numeric', minute: '2-digit' }).format(now)
  const date = new Intl.DateTimeFormat('en-IN', { day: 'numeric', month: 'long', year: 'numeric' }).format(now)
  const openChips = chips.filter(allowed)

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6">
      <section className="flex items-center justify-between gap-4 rounded-3xl bg-[#3154d6] px-6 py-6 text-white">
        <div>
          <p className="text-xs font-medium text-white/80">{business}</p>
          <h2 className="mt-2 text-2xl font-semibold tracking-tight">Quick actions</h2>
          <p className="mt-1 text-sm text-white/80">Attendance, requests, people, and tools for this business.</p>
        </div>
        <div className="rounded-2xl bg-white/15 px-4 py-3 text-right">
          <p className="text-xl font-semibold">{time}</p>
          <p className="text-[11px] tracking-wide text-white/75 uppercase">{date}</p>
        </div>
      </section>

      <section className="rounded-3xl border border-border bg-card px-5 py-4">
        <p className="text-sm font-semibold">Daily shift attendance</p>
        <p className="mt-1 text-sm text-muted-foreground">Punch, month sheet, corrections, and holidays are on Attendance.</p>
        <div className="mt-3 flex flex-wrap gap-2">
          {openChips.map((chip) => {
            const Icon = chip.icon
            const primary = chip.title === 'Check in'
            return (
              <Link key={chip.title} to={chip.to} className={`inline-flex h-9 items-center gap-2 rounded-full border px-3 text-sm font-semibold ${primary ? 'border-[#3a35e0] bg-[#3a35e0] text-white' : 'border-border bg-white text-[#243056]'}`}>
                <Icon className="size-4" />
                {chip.title}
              </Link>
            )
          })}
        </div>
      </section>

      {sections.map((section) => {
        const tiles = section.tiles.filter(allowed)
        if (tiles.length === 0) return null
        return (
          <section key={section.title}>
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-sm font-semibold">{section.title}</h3>
              <Link className="text-sm font-medium text-[#3154d6]" to={section.to}>{section.action}</Link>
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
              {tiles.map((tile) => {
                const Icon = tile.icon
                return (
                  <Link key={tile.title} to={tile.to} className="flex min-h-40 flex-col items-center justify-center rounded-2xl border border-[#e7e9f2] bg-white px-3 py-5 text-center shadow-[0_8px_24px_rgb(28_32_64/0.04)]">
                    <span className={`grid size-12 place-items-center rounded-2xl ${tile.tint}`}>
                      <Icon className="size-5" />
                    </span>
                    <span className="mt-3 text-sm font-semibold">{tile.title}</span>
                    <span className="mt-1 text-xs text-muted-foreground">{tile.caption}</span>
                  </Link>
                )
              })}
            </div>
          </section>
        )
      })}
    </div>
  )
}
