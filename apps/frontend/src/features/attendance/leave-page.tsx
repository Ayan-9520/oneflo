import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router'

import { useSession } from '@/app/session'
import { Button } from '@/components/ui/button'
import { DataTable } from '@/components/ui/data-table'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { Field, Input, Textarea } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/page-header'
import { Select } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { useToast } from '@/components/ui/toast'
import { ApiError, apiData } from '@/lib/api'

type LeaveType = { id: number; name: string; code: string; paid: boolean }
type LeavePolicy = { id: number; leave_type: string | null; year: number; annual_days: string; accrual_days: string; carry_forward_max: string; approval_steps: string[] }
type Application = { id: number; employee_name: string | null; leave_type: string | null; starts_on: string; ends_on: string; days: string; status: string; current_step: string | null }
type Balance = { employee_id: number; leave_type_id: number; available_days: string; used_days: string; accrued_days: string; carried_days: string }

export function LeavePage() {
  const { organizationId, permissions } = useSession()
  const canManage = permissions.has('leave.approve') || permissions.has('organization.manage')
  const year = new Date().getFullYear()
  const [search] = useSearchParams()
  const focus = search.get('view')
  const types = useQuery({ queryKey: ['leave-types', organizationId], enabled: organizationId !== null, queryFn: () => apiData<LeaveType[]>('/api/v1/leave/types') })
  const policies = useQuery({ queryKey: ['leave-policies', organizationId], enabled: organizationId !== null, queryFn: () => apiData<LeavePolicy[]>('/api/v1/leave/policies') })
  const balances = useQuery({ queryKey: ['leave-balances', organizationId, year], enabled: organizationId !== null, queryFn: () => apiData<Balance[]>(`/api/v1/leave/balances?year=${year}`) })
  const applications = useQuery({ queryKey: ['leave-applications', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Application[]>('/api/v1/leave/applications') })

  useEffect(() => {
    const target = focus === 'balance' ? 'leave-balance' : focus === 'apply' ? 'leave-apply' : null
    if (target) document.getElementById(target)?.scrollIntoView({ block: 'start' })
  }, [focus, balances.data])

  if (organizationId === null) return <EmptyState title="Select a business" description="Leave policies belong to the active business." />

  return (
    <div className="mx-auto flex max-w-5xl flex-col gap-8">
      <PageHeader title="Leave" description="Apply for an employee, then set the leave type and the year's policy. Balances appear after a policy is accrued." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Leave' }]} />
      <section id="leave-apply" className="flex flex-col gap-3">
        <h2 className="text-lg font-semibold">Apply</h2>
        <p className="text-sm text-muted-foreground">Choose a person from People and a leave type that has already been saved.</p>
        <ApplicationForm types={types.data ?? []} />
      </section>
      <section className="flex flex-col gap-3 border-t border-border pt-6">
        <h2 className="text-lg font-semibold">Applications</h2>
        <ApplicationList query={applications} />
      </section>
      <section id="leave-balance" className="flex flex-col gap-3 border-t border-border pt-6">
        <h2 className="text-lg font-semibold">Balances</h2>
        {balances.isPending ? <Skeleton className="h-20 w-full" /> : balances.isError ? <ErrorState title="Balances could not be loaded" description={balances.error instanceof ApiError ? balances.error.message : 'Try again.'} /> : balances.data.length === 0 ? <p className="text-sm text-muted-foreground">No balance yet. Save a policy, then accrue it for active employees.</p> : (
          <DataTable caption="Leave balances" rows={balances.data} getRowId={(row) => `${row.employee_id}-${row.leave_type_id}`} columns={[
            { id: 'employee', header: 'Employee', cell: (row) => String(row.employee_id) },
            { id: 'type', header: 'Type', cell: (row) => String(row.leave_type_id) },
            { id: 'available', header: 'Available', cell: (row) => row.available_days },
            { id: 'accrued', header: 'Accrued', cell: (row) => row.accrued_days },
            { id: 'carried', header: 'Carried', cell: (row) => row.carried_days },
            { id: 'used', header: 'Used', cell: (row) => row.used_days },
          ]} />
        )}
      </section>
      {canManage ? <PolicyForms types={types.data ?? []} policies={policies.data ?? []} /> : null}
    </div>
  )
}

export function LeaveHistory({ employeeId }: { employeeId: number }) {
  const { organizationId } = useSession()
  const applications = useQuery({
    queryKey: ['leave-applications', organizationId, employeeId],
    enabled: organizationId !== null,
    queryFn: () => apiData<Array<Application & { employee_id: number }>>('/api/v1/leave/applications'),
  })
  if (applications.isPending) return <Skeleton className="h-24 w-full" />
  if (applications.isError) return <ErrorState title="Leave could not be loaded" description={applications.error instanceof ApiError ? applications.error.message : 'Try again.'} />
  const rows = applications.data.filter((row) => row.employee_id === employeeId)
  if (rows.length === 0) return <EmptyState title="No leave applications" description="Applications for this employee appear here." />
  return <ApplicationTable rows={rows} />
}

function ApplicationForm({ types }: { types: LeaveType[] }) {
  const { organizationId } = useSession()
  const employees = useQuery({ queryKey: ['employees', organizationId, 'leave'], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; full_name: string }>>('/api/v1/employees') })
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const body = new FormData(event.currentTarget)
    setPending(true)
    setError(null)
    try {
      await apiData('/api/v1/leave/applications', {
        method: 'POST',
        body: JSON.stringify({
          employee_id: Number(body.get('employee_id')),
          leave_type_id: Number(body.get('leave_type_id')),
          starts_on: String(body.get('starts_on')),
          ends_on: String(body.get('ends_on')),
          reason: String(body.get('reason') ?? ''),
        }),
      })
      notify('Leave application submitted.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['leave-applications'] })
      await queryClient.invalidateQueries({ queryKey: ['leave-balances'] })
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The application could not be submitted.')
    } finally {
      setPending(false)
    }
  }

  return (
    <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => void submit(event)}>
      <Field label="Employee" htmlFor="leave-employee">
        <Select id="leave-employee" name="employee_id" placeholder="Employee" options={(employees.data ?? []).map((employee) => ({ value: String(employee.id), label: employee.full_name }))} />
      </Field>
      <Field label="Leave type" htmlFor="leave-type">
        <Select id="leave-type" name="leave_type_id" placeholder="Type" options={types.map((type) => ({ value: String(type.id), label: type.name }))} />
      </Field>
      <Field label="From" htmlFor="leave-from"><Input id="leave-from" name="starts_on" type="date" required /></Field>
      <Field label="To" htmlFor="leave-to"><Input id="leave-to" name="ends_on" type="date" required /></Field>
      <Field label="Reason" htmlFor="leave-reason"><Textarea id="leave-reason" name="reason" /></Field>
      {error ? <p className="text-sm text-destructive sm:col-span-2">{error}</p> : null}
      <div className="sm:col-span-2"><Button type="submit" disabled={pending}>{pending ? 'Submitting…' : 'Apply'}</Button></div>
    </form>
  )
}

function ApplicationList({ query }: { query: ReturnType<typeof useQuery<Application[]>> }) {
  if (query.isPending) return <Skeleton className="h-24 w-full" />
  if (query.isError) return <ErrorState title="Applications could not be loaded" description={query.error instanceof ApiError ? query.error.message : 'Try again.'} />
  if (query.data.length === 0) return <p className="text-sm text-muted-foreground">No application yet.</p>
  return <ApplicationTable rows={query.data} />
}

function ApplicationTable({ rows }: { rows: Application[] }) {
  return (
    <DataTable caption="Leave applications" rows={rows} getRowId={(row) => String(row.id)} columns={[
      { id: 'employee', header: 'Employee', cell: (row) => row.employee_name ?? '' },
      { id: 'type', header: 'Type', cell: (row) => row.leave_type ?? '' },
      { id: 'from', header: 'From', cell: (row) => row.starts_on },
      { id: 'to', header: 'To', cell: (row) => row.ends_on },
      { id: 'days', header: 'Days', cell: (row) => row.days },
      { id: 'status', header: 'Status', cell: (row) => row.current_step ? `${row.status} · ${row.current_step}` : row.status },
    ]} />
  )
}

function PolicyForms({ types, policies }: { types: LeaveType[]; policies: LeavePolicy[] }) {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)

  async function createType(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    const body = new FormData(form)
    setPending(true)
    try {
      await apiData('/api/v1/leave/types', { method: 'POST', body: JSON.stringify({ name: String(body.get('name')), code: String(body.get('code')), paid: body.get('paid') === '1' }) })
      form.reset()
      notify('Leave type saved.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['leave-types'] })
    } catch (error) {
      notify(error instanceof ApiError ? error.message : 'The leave type could not be saved.', 'danger')
    } finally {
      setPending(false)
    }
  }

  async function createPolicy(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const body = new FormData(event.currentTarget)
    setPending(true)
    try {
      await apiData('/api/v1/leave/policies', {
        method: 'POST',
        body: JSON.stringify({
          leave_type_id: Number(body.get('leave_type_id')),
          year: Number(body.get('year')),
          annual_days: Number(body.get('annual_days')),
          accrual_days: Number(body.get('accrual_days')),
          carry_forward_max: Number(body.get('carry_forward_max')),
          encashment_allowed: body.get('encashment_allowed') === '1',
          encashment_max: Number(body.get('encashment_max') || 0),
          approval_steps: String(body.get('approval_steps')).split(',').map((step) => step.trim()).filter(Boolean),
        }),
      })
      notify('Leave policy saved.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['leave-policies'] })
    } catch (error) {
      notify(error instanceof ApiError ? error.message : 'The policy could not be saved.', 'danger')
    } finally {
      setPending(false)
    }
  }

  return (
    <section className="flex flex-col gap-6 border-t border-border pt-6">
      <div>
        <h2 className="text-lg font-semibold">Leave type</h2>
        <p className="mt-1 text-sm text-muted-foreground">Example: Casual leave, code CL, paid.</p>
      </div>
      <form className="grid gap-3 sm:grid-cols-3" onSubmit={(event) => void createType(event)}>
        <Field label="Type name" htmlFor="type-name"><Input id="type-name" name="name" required /></Field>
        <Field label="Code" htmlFor="type-code"><Input id="type-code" name="code" required /></Field>
        <Field label="Paid" htmlFor="type-paid"><Select id="type-paid" name="paid" options={[{ value: '1', label: 'Paid' }, { value: '0', label: 'Unpaid' }]} /></Field>
        <div className="sm:col-span-3"><Button type="submit" disabled={pending}>Add type</Button></div>
      </form>
      <div>
        <h2 className="text-lg font-semibold">Year policy</h2>
        <p className="mt-1 text-sm text-muted-foreground">Annual days, monthly accrual, carry-forward, and who approves.</p>
        {policies.length === 0 ? <p className="mt-2 text-sm text-muted-foreground">No policy saved for this business.</p> : (
          <ul className="mt-2 text-sm text-muted-foreground">{policies.map((policy) => <li key={policy.id}>{policy.year} · {policy.leave_type} · {policy.annual_days} days</li>)}</ul>
        )}
      </div>
      <form className="grid gap-3 sm:grid-cols-3" onSubmit={(event) => void createPolicy(event)}>
        <Field label="Type" htmlFor="policy-type"><Select id="policy-type" name="leave_type_id" placeholder="Type" options={types.map((type) => ({ value: String(type.id), label: type.name }))} /></Field>
        <Field label="Year" htmlFor="policy-year"><Input id="policy-year" name="year" type="number" required defaultValue={new Date().getFullYear()} /></Field>
        <Field label="Annual days" htmlFor="policy-annual"><Input id="policy-annual" name="annual_days" type="number" step="0.5" required /></Field>
        <Field label="Monthly accrual" htmlFor="policy-accrual"><Input id="policy-accrual" name="accrual_days" type="number" step="0.5" required /></Field>
        <Field label="Carry-forward max" htmlFor="policy-carry"><Input id="policy-carry" name="carry_forward_max" type="number" step="0.5" required /></Field>
        <Field label="Encashment max" htmlFor="policy-encash"><Input id="policy-encash" name="encashment_max" type="number" step="0.5" defaultValue={0} /></Field>
        <Field label="Encashment" htmlFor="policy-encash-flag"><Select id="policy-encash-flag" name="encashment_allowed" options={[{ value: '0', label: 'Not allowed' }, { value: '1', label: 'Allowed' }]} /></Field>
        <Field label="Approval steps" htmlFor="policy-steps"><Input id="policy-steps" name="approval_steps" defaultValue="manager,hr" /></Field>
        <div className="sm:col-span-3"><Button type="submit" variant="outline" disabled={pending}>Save policy</Button></div>
      </form>
    </section>
  )
}
