import { useQuery, useQueryClient } from '@tanstack/react-query'
import { type FormEvent, useState } from 'react'

import { useSession } from '@/app/session'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { Field, Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/page-header'
import { Select } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { useToast } from '@/components/ui/toast'
import { ApiError, apiData, apiFetch } from '@/lib/api'

type Portal = {
  profile: { id: number; employee_code: string; full_name: string; email: string | null; phone: string | null; department: string | null; designation: string | null; manager: { full_name: string; email: string | null } | null }
  attendance_today: { status: string; late_minutes: number; work_mode: string | null } | null
  attendance_history: Array<{ work_date: string | null; status: string }>
  leave_balances: Array<{ leave_type_id: number; available_days: string }>
  announcements: Array<{ id: number; title: string; body: string }>
  documents: { files: Array<{ id: number; label: string }>; issued: Array<{ id: number; document_type: string; number: string }> }
  assets: Array<{ asset_code: string | null; name: string | null; returned_on: string | null }>
  expenses: Array<{ id: number; category: string | null; amount: string; status: string }>
  performance: Array<{ id: number; cycle: string; status: string; manager_score: number | null }>
  tickets: Array<{ id: number; subject: string; status: string }>
  payslips: Array<{ id: number; year: number; month: number; net: string }>
}

type Team = {
  employees: Array<{ id: number; employee_code: string; full_name: string; department: string | null; attendance: string | null }>
  leave_queue: Array<{ id: number; employee: string | null; leave_type: string | null; starts_on: string | null; ends_on: string | null }>
  performance: Array<{ employee: string | null; cycle: string; status: string; manager_score: number | null }>
  expenses: Array<{ id: number; employee: string | null; amount: string; status: string }>
}

async function downloadPayslip(id: number) {
  const response = await apiFetch(`/api/v1/payroll/lines/${id}/payslip`)
  if (!response.ok) return
  const url = URL.createObjectURL(await response.blob())
  const link = document.createElement('a')
  link.href = url
  link.download = `payslip-${id}.pdf`
  link.click()
  URL.revokeObjectURL(url)
}

function today(): string {
  return new Date().toISOString().slice(0, 10)
}

export function MyWorkPage() {
  const { organizationId } = useSession()
  const portal = useQuery({ queryKey: ['me', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Portal>('/api/v1/me') })
  if (organizationId === null) return <EmptyState title="Select a business" description="Your workspace stays inside the active business." />
  if (portal.isPending) {
    return (
      <div className="flex flex-col gap-6">
        <PageHeader title="My work" description="Your profile, attendance, and requests." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'My work' }]} />
        <div className="grid gap-4 sm:grid-cols-2">
          <Skeleton className="h-28 rounded-2xl" />
          <Skeleton className="h-28 rounded-2xl" />
        </div>
      </div>
    )
  }
  if (portal.isError) {
    const message = portal.error instanceof ApiError ? portal.error.message : 'Try again.'
    return <ErrorState title="Your workspace could not be opened" description={message} />
  }
  const data = portal.data
  return (
    <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
      <PageHeader title={data.profile.full_name} description={`${data.profile.employee_code}${data.profile.designation ? ` · ${data.profile.designation}` : ''}${data.profile.department ? ` · ${data.profile.department}` : ''}`} crumbs={[{ label: 'Workspace', to: '/' }, { label: 'My work' }]} />
      <section className="rounded-lg border border-border bg-card p-4 text-sm">
        <h2 className="text-base font-semibold">Reporting manager</h2>
        <p className="mt-2">{data.profile.manager ? `${data.profile.manager.full_name}${data.profile.manager.email ? ` · ${data.profile.manager.email}` : ''}` : 'No reporting manager is recorded.'}</p>
      </section>
      <section className="rounded-lg border border-border bg-card p-4">
        <h2 className="text-base font-semibold">Today</h2>
        <p className="mt-2 text-sm">{data.attendance_today ? `${data.attendance_today.status}${data.attendance_today.late_minutes > 0 ? ` · ${data.attendance_today.late_minutes} minutes late` : ''}` : 'No attendance is recorded yet today.'}</p>
        <PunchButtons />
      </section>
      <section className="rounded-lg border border-border bg-card p-4 text-sm">
        <h2 className="text-base font-semibold">Leave balance</h2>
        {data.leave_balances.length === 0 ? <p className="mt-2">No balance is recorded for this year.</p> : <ul className="mt-2">{data.leave_balances.map((row) => <li key={row.leave_type_id}>Type {row.leave_type_id}: {row.available_days} days</li>)}</ul>}
        <LeaveForm employeeId={data.profile.id} />
      </section>
      <section className="text-sm">
        <h2 className="text-base font-semibold">Attendance history</h2>
        {data.attendance_history.length === 0 ? <p className="mt-2">No attendance days are recorded.</p> : <ul className="mt-2">{data.attendance_history.map((row) => <li key={row.work_date}>{row.work_date} · {row.status}</li>)}</ul>}
      </section>
      <section className="text-sm">
        <h2 className="text-base font-semibold">Payslips and tax draft</h2>
        {data.payslips.length === 0 ? <p className="mt-2">Payslips appear after payroll for your legal employer is locked.</p> : <ul className="mt-2 flex flex-col gap-2">{data.payslips.map((row) => <li key={row.id}><Button variant="outline" className="w-full sm:w-auto" onClick={() => void downloadPayslip(row.id)}>{row.year}-{String(row.month).padStart(2, '0')} · {row.net}</Button></li>)}</ul>}
        <TaxDraft />
      </section>
      <section className="text-sm">
        <h2 className="text-base font-semibold">Announcements</h2>
        {data.announcements.length === 0 ? <p className="mt-2">No announcements.</p> : <ul className="mt-2">{data.announcements.map((row) => <li key={row.id}><span className="font-medium">{row.title}</span> — {row.body}</li>)}</ul>}
      </section>
      <section className="text-sm">
        <h2 className="text-base font-semibold">My documents</h2>
        <ul className="mt-2">{data.documents.issued.map((row) => <li key={row.id}>{row.document_type} · {row.number}</li>)}{data.documents.files.map((row) => <li key={`file-${row.id}`}>{row.label}</li>)}</ul>
      </section>
      <section className="text-sm">
        <h2 className="text-base font-semibold">Assigned assets</h2>
        {data.assets.length === 0 ? <p className="mt-2">No assets are assigned.</p> : <ul className="mt-2">{data.assets.map((row) => <li key={`${row.asset_code}-${row.returned_on}`}>{row.asset_code} {row.name}{row.returned_on ? ` · returned ${row.returned_on}` : ''}</li>)}</ul>}
      </section>
      <section className="text-sm">
        <h2 className="text-base font-semibold">Expenses</h2>
        {data.expenses.length === 0 ? <p className="mt-2">No claims.</p> : <ul className="mt-2">{data.expenses.map((row) => <li key={row.id}>{row.category} · {row.amount} · {row.status}</li>)}</ul>}
      </section>
      <section className="text-sm">
        <h2 className="text-base font-semibold">Performance</h2>
        {data.performance.length === 0 ? <p className="mt-2">No reviews.</p> : <ul className="mt-2">{data.performance.map((row) => <li key={row.id}>{row.cycle} · {row.status}{row.manager_score === null ? '' : ` · ${row.manager_score}`}</li>)}</ul>}
      </section>
      <section className="text-sm">
        <h2 className="text-base font-semibold">Helpdesk</h2>
        {data.tickets.length === 0 ? <p className="mt-2">No queries.</p> : <ul className="mt-2">{data.tickets.map((row) => <li key={row.id}>{row.subject} · {row.status}</li>)}</ul>}
      </section>
      <Preferences />
    </div>
  )
}

function PunchButtons() {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  async function punch(kind: 'in' | 'out') {
    setPending(true)
    setError(null)
    const now = new Date()
    const stamp = `${today()} ${now.toTimeString().slice(0, 8)}`
    try {
      await apiData('/api/v1/attendance/punches', { method: 'POST', body: JSON.stringify({ kind, work_mode: 'office', work_date: today(), punched_at: stamp }) })
      await queryClient.invalidateQueries({ queryKey: ['me'] })
      notify(kind === 'in' ? 'Checked in.' : 'Checked out.', 'success')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The punch could not be recorded.')
    } finally {
      setPending(false)
    }
  }
  return (
    <div className="mt-3 flex flex-col gap-2 sm:flex-row">
      <Button className="w-full sm:w-auto" disabled={pending} onClick={() => void punch('in')}>Check in</Button>
      <Button className="w-full sm:w-auto" variant="secondary" disabled={pending} onClick={() => void punch('out')}>Check out</Button>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
    </div>
  )
}

function LeaveForm({ employeeId }: { employeeId: number }) {
  const { organizationId } = useSession()
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)
  const types = useQuery({ queryKey: ['leave-types', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; name: string }>>('/api/v1/leave/types') })
  return (
    <form className="mt-4 grid gap-3" onSubmit={(event: FormEvent<HTMLFormElement>) => {
      event.preventDefault()
      const body = new FormData(event.currentTarget)
      setPending(true)
      setError(null)
      void apiData('/api/v1/leave/applications', { method: 'POST', body: JSON.stringify({ employee_id: employeeId, leave_type_id: Number(body.get('leave_type_id')), starts_on: String(body.get('starts_on')), ends_on: String(body.get('ends_on')), reason: String(body.get('reason') ?? '') }) })
        .then(async () => { await queryClient.invalidateQueries({ queryKey: ['me'] }); notify('Leave application submitted.', 'success') })
        .catch((caught: unknown) => setError(caught instanceof ApiError ? caught.message : 'The application could not be submitted.'))
        .finally(() => setPending(false))
    }}>
      <Field label="Leave type" htmlFor="leave-type"><Select id="leave-type" name="leave_type_id" options={(types.data ?? []).map((row) => ({ value: String(row.id), label: row.name }))} /></Field>
      <Field label="From" htmlFor="leave-start"><Input id="leave-start" name="starts_on" type="date" required /></Field>
      <Field label="To" htmlFor="leave-end"><Input id="leave-end" name="ends_on" type="date" required /></Field>
      <Field label="Reason" htmlFor="leave-reason"><Input id="leave-reason" name="reason" /></Field>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      <Button className="w-full sm:w-auto" type="submit" disabled={pending || (types.data ?? []).length === 0}>Apply for leave</Button>
    </form>
  )
}

function TaxDraft() {
  const [year, setYear] = useState(String(new Date().getMonth() >= 3 ? new Date().getFullYear() : new Date().getFullYear() - 1))
  const [text, setText] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  return (
    <form className="mt-3 grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); setError(null); void apiData<{ disclaimer: string; financial_year: string }>(`/api/v1/me/tax-documents?financial_year=${year}`).then((draft) => setText(`${draft.financial_year}: ${draft.disclaimer}`)).catch((caught: unknown) => setError(caught instanceof ApiError ? caught.message : 'The tax draft could not be loaded.')) }}>
      <Field label="Financial year" htmlFor="tax-year"><Input id="tax-year" value={year} onChange={(event) => setYear(event.target.value)} required /></Field>
      <div className="flex items-end"><Button className="w-full sm:w-auto" type="submit">Open tax draft</Button></div>
      {text ? <p className="sm:col-span-2 text-sm">{text}</p> : null}
      {error ? <p className="sm:col-span-2 text-sm text-destructive">{error}</p> : null}
    </form>
  )
}

type Preference = { event: string; in_app: boolean; email: boolean; sms: boolean; whatsapp: boolean }

function Preferences() {
  const { organizationId } = useSession()
  const notify = useToast().notify
  const queryClient = useQueryClient()
  const [error, setError] = useState<string | null>(null)
  const preferences = useQuery({ queryKey: ['notification-preferences', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Preference[]>('/api/v1/notification-preferences') })
  if (!preferences.data) return null
  return (
    <section className="text-sm">
      <h2 className="text-base font-semibold">Notification preferences</h2>
      <ul className="mt-3 flex flex-col gap-3">
        {preferences.data.map((row) => (
          <li key={row.event} className="rounded-lg border border-border p-3">
            <p className="font-medium">{row.event}</p>
            <div className="mt-2 flex flex-wrap gap-2">
              {(['in_app', 'email', 'sms', 'whatsapp'] as const).map((channel) => (
                <Button key={channel} size="sm" variant={row[channel] ? 'default' : 'outline'} onClick={() => {
                  const next = { ...row, [channel]: !row[channel] }
                  setError(null)
                  void apiData('/api/v1/notification-preferences', { method: 'PUT', body: JSON.stringify(next) })
                    .then(async () => { await queryClient.invalidateQueries({ queryKey: ['notification-preferences'] }); notify('Preference saved.', 'success') })
                    .catch((caught: unknown) => setError(caught instanceof ApiError ? caught.message : 'The preference could not be saved.'))
                }}>{channel}</Button>
              ))}
            </div>
          </li>
        ))}
      </ul>
      {error ? <p className="mt-2 text-destructive">{error}</p> : null}
    </section>
  )
}

export function ManagerPage() {
  const { organizationId } = useSession()
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [error, setError] = useState<string | null>(null)
  const team = useQuery({ queryKey: ['manager', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Team>('/api/v1/manager') })
  if (organizationId === null) return <EmptyState title="Select a business" description="Team information stays inside the active business." />
  if (team.isError) return <ErrorState title="The team could not be loaded" description={team.error instanceof ApiError ? team.error.message : 'Try again.'} />
  const data = team.data
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="My team" description="People, attendance, leave, expenses, and reviews in your assigned scope." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'My team' }]} />
      {team.isPending ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <Skeleton className="h-24 rounded-2xl" />
          <Skeleton className="h-24 rounded-2xl" />
          <Skeleton className="h-24 rounded-2xl" />
        </div>
      ) : data.employees.length === 0 ? (
        <EmptyState title="No team members" description="Employees appear when a department is assigned to your access." />
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {data.employees.map((row) => (
            <Card key={row.id}>
              <CardHeader>
                <CardTitle>{row.full_name}</CardTitle>
              </CardHeader>
              <CardContent className="flex items-center justify-between gap-3 text-sm">
                <p className="text-muted-foreground">{row.employee_code} · {row.department ?? 'No department'}</p>
                <Badge tone={row.attendance === 'present' ? 'success' : row.attendance === 'absent' ? 'danger' : 'neutral'}>{row.attendance ?? 'No attendance'}</Badge>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
      {data ? <div className="grid gap-4 lg:grid-cols-3">
        <Card>
          <CardHeader><CardTitle>Pending leave</CardTitle></CardHeader>
          <CardContent className="flex flex-col gap-3 text-sm">
            {data.leave_queue.length === 0 ? <p className="text-muted-foreground">No leave is waiting.</p> : data.leave_queue.map((row) => (
              <div key={row.id} className="flex flex-col gap-2">
                <p>{row.employee} · {row.leave_type}</p>
                <p className="text-muted-foreground">{row.starts_on} to {row.ends_on}</p>
                <Button className="w-full sm:w-auto" onClick={() => { setError(null); void apiData(`/api/v1/leave/applications/${row.id}/decision`, { method: 'POST', body: JSON.stringify({ approve: true }) }).then(async () => { await queryClient.invalidateQueries({ queryKey: ['manager'] }); notify('Leave approved.', 'success') }).catch((caught: unknown) => setError(caught instanceof ApiError ? caught.message : 'The decision could not be saved.')) }}>Approve</Button>
              </div>
            ))}
            {error ? <p className="text-destructive">{error}</p> : null}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle>Team performance</CardTitle></CardHeader>
          <CardContent className="text-sm">
            {data.performance.length === 0 ? <p className="text-muted-foreground">No reviews.</p> : (
              <ul className="flex flex-col gap-2">{data.performance.map((row) => <li key={`${row.employee}-${row.cycle}`}>{row.employee} · {row.cycle} · {row.status}</li>)}</ul>
            )}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle>Pending expenses</CardTitle></CardHeader>
          <CardContent className="text-sm">
            {data.expenses.length === 0 ? <p className="text-muted-foreground">No claims are waiting.</p> : (
              <ul className="flex flex-col gap-2">{data.expenses.map((row) => <li key={row.id}>{row.employee} · {row.amount} · {row.status}</li>)}</ul>
            )}
          </CardContent>
        </Card>
      </div> : null}
    </div>
  )
}
