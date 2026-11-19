import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'

import { useSession } from '@/app/session'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { NoticeBoard } from '@/features/workspace/notice-board'
import { PageHeader } from '@/components/ui/page-header'
import { Skeleton } from '@/components/ui/skeleton'
import { ApiError, apiData } from '@/lib/api'

type Person = {
  id: number
  full_name: string
  employee_code: string
  designation: { name: string } | null
  manager: { id: number; full_name: string } | null
}

const prompts = [
  { label: 'Open leave balances', to: '/leave?view=balance' },
  { label: 'Open attendance', to: '/attendance?view=punch' },
  { label: 'Open payslips', to: '/payroll' },
  { label: 'Open expenses', to: '/expenses?view=claims' },
  { label: 'Open people', to: '/people' },
  { label: 'Open letters', to: '/letters' },
]

export function AssistantPage() {
  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-5">
      <PageHeader title="Assistant" description="These open the saved page. OneFlo does not invent a leave balance or a payslip." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Assistant' }]} />
      <div className="flex flex-wrap gap-2">
        {prompts.map((item) => (
          <Link key={item.to} className="rounded-full border border-border bg-card px-4 py-2 text-sm font-semibold" to={item.to}>{item.label}</Link>
        ))}
      </div>
    </div>
  )
}

export function OrgChartPage() {
  const { organizationId } = useSession()
  const people = useQuery({
    queryKey: ['employees', organizationId, 'org-chart'],
    enabled: organizationId !== null,
    queryFn: () => apiData<Person[]>('/api/v1/employees'),
  })
  if (organizationId === null) return <EmptyState title="Select a business" description="The chart stays inside the active business." />
  if (people.isPending) return <Skeleton className="h-40 w-full" />
  if (people.isError) return <ErrorState title="People could not be loaded" description={people.error instanceof ApiError ? people.error.message : 'Try again.'} />
  const rows = people.data
  const byManager = new Map<number | null, Person[]>()
  for (const person of rows) {
    const key = person.manager?.id ?? null
    byManager.set(key, [...(byManager.get(key) ?? []), person])
  }
  const roots = byManager.get(null) ?? []
  return (
    <div className="flex flex-col gap-5">
      <PageHeader title="Org chart" description="Each card uses the manager saved on the employee. A person with no manager sits at the top." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Org chart' }]} />
      {rows.length === 0 ? <EmptyState title="No employees" description="The chart appears after people are on the payroll record." /> : (
        <div className="flex flex-col gap-4">
          {roots.map((person) => <ChartNode key={person.id} person={person} byManager={byManager} />)}
        </div>
      )}
    </div>
  )
}

function ChartNode({ person, byManager }: { person: Person; byManager: Map<number | null, Person[]> }) {
  const children = byManager.get(person.id) ?? []
  return (
    <div className="rounded-2xl border border-border bg-card p-4">
      <Link className="text-sm font-semibold" to={`/people/${person.id}`}>{person.full_name}</Link>
      <p className="text-xs text-muted-foreground">{person.employee_code}{person.designation ? ` · ${person.designation.name}` : ''}</p>
      {children.length > 0 ? (
        <div className="mt-3 grid gap-3 border-l border-border pl-4 sm:grid-cols-2">
          {children.map((child) => <ChartNode key={child.id} person={child} byManager={byManager} />)}
        </div>
      ) : null}
    </div>
  )
}

export function NotificationsPage() {
  const { organizationId } = useSession()
  if (organizationId === null) return <EmptyState title="Select a business" description="Notices stay inside the active business." />
  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-5">
      <PageHeader title="Notifications" description="Important announcements, birthday wishes, and special occasions saved for this business." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Notifications' }]} />
      <NoticeBoard />
    </div>
  )
}

export function ProfilePage() {
  const { account, organization } = useSession()
  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-4">
      <PageHeader title="Profile" description="This is the signed-in account. Employee details stay on My work." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Profile' }]} />
      <section className="rounded-3xl border border-border bg-card p-5">
        <p className="text-xl font-semibold">{account?.name ?? 'Account'}</p>
        <p className="mt-1 text-sm text-muted-foreground">{account?.email}</p>
        <p className="mt-1 text-sm text-muted-foreground">{organization?.name?.trim() || 'No business selected'}</p>
        <div className="mt-4 flex flex-wrap gap-2">
          <Link className="rounded-full bg-[#3a35e0] px-4 py-2 text-sm font-semibold text-white" to="/my-work">My work</Link>
          <Link className="rounded-full border border-border px-4 py-2 text-sm font-semibold" to="/forgot-password">Reset password</Link>
        </div>
      </section>
    </div>
  )
}

export function LoanPage() {
  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-4">
      <PageHeader title="Loan request" description="Salary advances are not stored as their own record." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Loan request' }]} />
      <EmptyState title="No loan ledger" description="A money claim that should be reimbursed is an expense. Payroll stays on its own page." />
      <div className="flex gap-2">
        <Link className="rounded-full bg-[#3a35e0] px-4 py-2 text-sm font-semibold text-white" to="/expenses?view=submit">Add expense</Link>
        <Link className="rounded-full border border-border px-4 py-2 text-sm font-semibold" to="/payroll">Payroll</Link>
      </div>
    </div>
  )
}

export function TasksPage() {
  const { organizationId } = useSession()
  const tickets = useQuery({
    queryKey: ['tickets', organizationId, 'tasks'],
    enabled: organizationId !== null,
    queryFn: () => apiData<Array<{ id: number; subject: string; priority: string; status: string; due_at: string | null }>>('/api/v1/tickets'),
  })
  if (organizationId === null) return <EmptyState title="Select a business" description="Tasks stay inside the active business." />
  if (tickets.isPending) return <Skeleton className="h-24 w-full" />
  if (tickets.isError) return <ErrorState title="Tasks could not be loaded" description={tickets.error instanceof ApiError ? tickets.error.message : 'Try again.'} />
  const open = tickets.data.filter((row) => row.status !== 'resolved')
  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="My tasks" description="Open helpdesk tickets are the task list. Resolved tickets stay on Helpdesk." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'My tasks' }]} />
      {open.length === 0 ? <EmptyState title="No open tasks" description="A ticket that is still open appears here." /> : (
        <ul className="flex flex-col gap-2">
          {open.map((row) => (
            <li key={row.id} className="rounded-2xl border border-border bg-card px-4 py-3 text-sm">
              <p className="font-semibold">{row.subject}</p>
              <p className="text-muted-foreground">{row.priority} · {row.status}{row.due_at ? ` · due ${row.due_at}` : ''}</p>
            </li>
          ))}
        </ul>
      )}
      <Link className="text-sm font-semibold text-[#3a35e0]" to="/helpdesk">Open helpdesk</Link>
    </div>
  )
}
