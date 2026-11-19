import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent, type ReactNode } from 'react'
import { useParams } from 'react-router'

import { useSession } from '@/app/session'
import { Button } from '@/components/ui/button'
import { Dialog } from '@/components/ui/dialog'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { Field, Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/page-header'
import { Select } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs } from '@/components/ui/tabs'
import { useToast } from '@/components/ui/toast'
import { EmployeeAssets, EmployeeExpenses, EmployeePerformance } from '@/features/operations/operations-pages'
import { EmployeeDocuments } from '@/features/recruitment/onboarding-page'
import { AttendanceHistory } from '@/features/attendance/attendance-page'
import { LeaveHistory } from '@/features/attendance/leave-page'
import { ApiError, apiData, apiFetch } from '@/lib/api'

import { employmentTypes, type EmployeeRecord, type EmploymentEvent, type NamedRef } from './employee'

type Placement = {
  legal_entities: NamedRef[]
  brands: NamedRef[]
  departments: NamedRef[]
  designations: NamedRef[]
  branches: NamedRef[]
}

export function EmployeeProfilePage() {
  const { employeeId } = useParams()
  const { organizationId, account, permissions } = useSession()
  const canUpdate = permissions.has('employees.update')
  const employee = useQuery({
    queryKey: ['employee', organizationId, employeeId],
    enabled: organizationId !== null && employeeId !== undefined,
    queryFn: () => apiData<EmployeeRecord>(`/api/v1/employees/${employeeId}`),
  })

  if (organizationId === null) {
    return <EmptyState title="Select a business" description="Open an employee from the active business." />
  }
  if (employee.isPending) return <Skeleton className="h-48 w-full" />
  if (employee.isError || !employee.data) {
    return <ErrorState title="Employee could not be loaded" description={employee.error instanceof ApiError ? employee.error.message : 'Try again.'} />
  }

  const record = employee.data
  return (
    <div>
      <PageHeader
        title={record.full_name}
        description={`${record.employee_code} · ${record.status}`}
        crumbs={[{ label: 'Workspace', to: '/' }, { label: 'People', to: '/people' }, { label: record.full_name }]}
      />
      <div className="mb-6 flex items-center gap-4">
        <EmployeePhoto employeeId={record.id} hasPhoto={record.has_photo} name={record.full_name} />
        {canUpdate ? <PhotoUpload employeeId={record.id} /> : null}
      </div>
      <Tabs
        tabs={[
          { id: 'overview', label: 'Overview', content: <Overview employee={record} /> },
          { id: 'personal', label: 'Personal Information', content: <PersonalForm employee={record} locked={!canUpdate} /> },
          { id: 'employment', label: 'Employment Details', content: <EmploymentForm employee={record} locked={!canUpdate} organizations={account?.organizations ?? []} /> },
          { id: 'documents', label: 'Documents', content: <EmployeeDocuments employeeId={record.id} /> },
          { id: 'attendance', label: 'Attendance', content: <AttendanceHistory employeeId={record.id} /> },
          { id: 'leave', label: 'Leave', content: <LeaveHistory employeeId={record.id} /> },
          { id: 'payroll', label: 'Payroll', content: <EmptyState title="Payroll" description="Payslips and tax statements are prepared after this legal employer's payroll is locked. The employer issues Form 16 through the authorized process." /> },
          { id: 'assets', label: 'Assets', content: <EmployeeAssets employeeId={record.id} /> },
          { id: 'performance', label: 'Performance', content: <EmployeePerformance employeeId={record.id} /> },
          { id: 'expenses', label: 'Expenses', content: <EmployeeExpenses /> },
          { id: 'activity', label: 'Activity History', content: <Activity employeeId={record.id} /> },
        ]}
      />
    </div>
  )
}

function Overview({ employee }: { employee: EmployeeRecord }) {
  const rows: Array<[string, string]> = [
    ['Email', employee.email ?? ''],
    ['Phone', employee.phone ?? ''],
    ['Business', employee.brand?.name ?? ''],
    ['Legal employer', employee.legal_entity?.name ?? ''],
    ['Department', employee.department?.name ?? ''],
    ['Designation', employee.designation?.name ?? ''],
    ['Reporting manager', employee.manager?.full_name ?? ''],
    ['Employment type', employee.employment_type],
    ['Joining date', employee.joined_on ?? ''],
    ['Status', employee.status],
    ['Branch', employee.branch?.name ?? ''],
    ['Work location', employee.work_location ?? ''],
  ]
  return (
    <dl className="grid gap-3 sm:grid-cols-2">
      {rows.map(([label, value]) => (
        <div key={label}>
          <dt className="text-xs tracking-wide text-muted-foreground uppercase">{label}</dt>
          <dd className="text-sm">{value || '—'}</dd>
        </div>
      ))}
    </dl>
  )
}

function PersonalForm({ employee, locked }: { employee: EmployeeRecord; locked: boolean }) {
  return (
    <EmployeePatch
      employeeId={employee.id}
      locked={locked}
      success="Personal information saved."
      fields={
        <>
          <Field label="Given name" htmlFor="given_name">
            <Input id="given_name" name="given_name" defaultValue={employee.given_name} required disabled={locked} />
          </Field>
          <Field label="Family name" htmlFor="family_name">
            <Input id="family_name" name="family_name" defaultValue={employee.family_name} required disabled={locked} />
          </Field>
          <Field label="Email" htmlFor="profile-email">
            <Input id="profile-email" name="email" type="email" defaultValue={employee.email ?? ''} disabled={locked} />
          </Field>
          <Field label="Phone" htmlFor="profile-phone">
            <Input id="profile-phone" name="phone" defaultValue={employee.phone ?? ''} disabled={locked} />
          </Field>
        </>
      }
    />
  )
}

function EmploymentForm({ employee, locked, organizations }: { employee: EmployeeRecord; locked: boolean; organizations: Array<{ id: number; name: string }> }) {
  const { organizationId } = useSession()
  const options = usePlacementOptions()
  const [transferOpen, setTransferOpen] = useState(false)

  return (
    <div className="flex flex-col gap-6">
      <EmployeePatch
        employeeId={employee.id}
        locked={locked}
        success="Employment details saved. The previous period stays in the history."
        fields={
          <>
            <Choice label="Department" name="department_id" current={employee.department?.id} options={options.departments} disabled={locked} />
            <Choice label="Designation" name="designation_id" current={employee.designation?.id} options={options.designations} disabled={locked} />
            <Choice label="Branch" name="branch_id" current={employee.branch?.id} options={options.branches} disabled={locked} />
            <Choice label="Legal employer" name="legal_entity_id" current={employee.legal_entity?.id} options={options.legalEntities} disabled={locked} />
            <Choice label="Business / brand" name="brand_id" current={employee.brand?.id} options={options.brands} disabled={locked} />
            <Field label="Employment type" htmlFor="employment_type">
              <Select id="employment_type" name="employment_type" defaultValue={employee.employment_type} options={employmentTypes} disabled={locked} />
            </Field>
            <Field label="Work location" htmlFor="work_location">
              <Input id="work_location" name="work_location" defaultValue={employee.work_location ?? ''} disabled={locked} />
            </Field>
            <Field label="Reporting manager ID" htmlFor="manager_id">
              <Input id="manager_id" name="manager_id" defaultValue={employee.manager?.id ?? ''} disabled={locked} />
            </Field>
            <Field label="Effective date" htmlFor="effective_on">
              <Input id="effective_on" name="effective_on" type="date" disabled={locked} />
            </Field>
          </>
        }
      />
      {locked ? null : (
        <div className="flex flex-wrap gap-2">
          <StatusButton employeeId={employee.id} path="deactivate" label="Deactivate" />
          <StatusButton employeeId={employee.id} path="rejoin" label="Rejoin" needsDate />
          <StatusButton employeeId={employee.id} path="resignation" label="Resignation" needsDate />
          <StatusButton employeeId={employee.id} path="exit" label="Exit" needsDate />
          <Button variant="outline" onClick={() => setTransferOpen(true)}>
            Transfer
          </Button>
        </div>
      )}
      <TransferDialog
        open={transferOpen}
        employee={employee}
        currentOrganizationId={organizationId}
        organizations={organizations}
        onClose={() => setTransferOpen(false)}
      />
    </div>
  )
}

function Activity({ employeeId }: { employeeId: number }) {
  const { organizationId, account } = useSession()
  const history = useQuery({
    queryKey: ['employee-history', organizationId, employeeId],
    queryFn: () => apiData<EmploymentEvent[]>(`/api/v1/employees/${employeeId}/history`),
  })
  if (history.isPending) return <Skeleton className="h-24 w-full" />
  if (history.isError) return <ErrorState title="History could not be loaded" description={history.error instanceof ApiError ? history.error.message : 'Try again.'} />
  if (history.data.length === 0) return <EmptyState title="No employment history" description="Hiring this employee creates the first record." />
  const names = new Map((account?.organizations ?? []).map((organization) => [organization.id, organization.name]))
  return (
    <ul className="flex flex-col gap-3">
      {history.data.map((event) => (
        <li key={event.id} className="rounded-lg border border-border px-4 py-3 text-sm">
          <p className="font-medium">{event.change_type.replaceAll('_', ' ')}</p>
          <p className="text-muted-foreground">
            {names.get(event.organization_id) ?? `Business ${event.organization_id}`} · {event.status} · {event.effective_on}
            {event.ended_on ? ` to ${event.ended_on}` : ''}
          </p>
          {event.reason ? <p>{event.reason}</p> : null}
        </li>
      ))}
    </ul>
  )
}

function EmployeePatch({ employeeId, fields, locked, success }: { employeeId: number; fields: ReactNode; locked: boolean; success: string }) {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const body: Record<string, string | number | null> = {}
    form.forEach((value, key) => {
      const text = String(value)
      if (text === '') return
      body[key] = key.endsWith('_id') ? Number(text) : text
    })
    setPending(true)
    setError(null)
    try {
      await apiData(`/api/v1/employees/${employeeId}`, { method: 'PATCH', body: JSON.stringify(body) })
      notify(success, 'success')
      await queryClient.invalidateQueries({ queryKey: ['employee'] })
      await queryClient.invalidateQueries({ queryKey: ['employees'] })
      await queryClient.invalidateQueries({ queryKey: ['employee-history'] })
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The employee could not be saved.')
    } finally {
      setPending(false)
    }
  }

  return (
    <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => void submit(event)}>
      {fields}
      {error ? <p className="text-sm text-destructive sm:col-span-2">{error}</p> : null}
      {locked ? null : (
        <div className="sm:col-span-2">
          <Button type="submit" disabled={pending}>
            {pending ? 'Saving…' : 'Save'}
          </Button>
        </div>
      )}
    </form>
  )
}

function Choice({ label, name, current, options, disabled }: { label: string; name: string; current?: number; options: NamedRef[]; disabled: boolean }) {
  return (
    <Field label={label} htmlFor={name}>
      <Select
        id={name}
        name={name}
        defaultValue={current ? String(current) : ''}
        disabled={disabled}
        options={[{ value: '', label: 'Unassigned' }, ...options.map((option) => ({ value: String(option.id), label: option.name }))]}
      />
    </Field>
  )
}

function StatusButton({ employeeId, path, label, needsDate = false }: { employeeId: number; path: string; label: string; needsDate?: boolean }) {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const mutation = useMutation({
    mutationFn: (effectiveOn?: string) =>
      apiData(`/api/v1/employees/${employeeId}/${path}`, {
        method: 'POST',
        body: JSON.stringify(needsDate ? { effective_on: effectiveOn, reason: label } : {}),
      }),
    onSuccess: async () => {
      notify(`${label} recorded.`, 'success')
      await queryClient.invalidateQueries({ queryKey: ['employee'] })
      await queryClient.invalidateQueries({ queryKey: ['employee-history'] })
    },
    onError: (error) => notify(error instanceof ApiError ? error.message : 'The change failed.', 'danger'),
  })

  return (
    <Button
      variant="outline"
      disabled={mutation.isPending}
      onClick={() => {
        const effectiveOn = needsDate ? window.prompt('Effective date (YYYY-MM-DD)') ?? '' : undefined
        if (needsDate && !effectiveOn) return
        mutation.mutate(effectiveOn)
      }}
    >
      {label}
    </Button>
  )
}

function TransferDialog({
  open,
  employee,
  organizations,
  currentOrganizationId,
  onClose,
}: {
  open: boolean
  employee: EmployeeRecord
  organizations: Array<{ id: number; name: string }>
  currentOrganizationId: number | null
  onClose: () => void
}) {
  const notify = useToast().notify
  const [targetId, setTargetId] = useState('')
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const placement = useQuery({
    queryKey: ['organization-placement', targetId],
    enabled: open && targetId !== '',
    queryFn: () => apiData<Placement>(`/api/v1/organizations/${targetId}`),
  })
  const targets = organizations.filter((organization) => organization.id !== currentOrganizationId)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setPending(true)
    setError(null)
    try {
      await apiData(`/api/v1/employees/${employee.id}/transfer`, {
        method: 'POST',
        body: JSON.stringify({
          target_organization_id: Number(targetId),
          legal_entity_id: Number(form.get('legal_entity_id')),
          employee_code: String(form.get('employee_code') ?? ''),
          department_id: numberOrNull(form.get('department_id')),
          designation_id: numberOrNull(form.get('designation_id')),
          branch_id: numberOrNull(form.get('branch_id')),
          brand_id: numberOrNull(form.get('brand_id')),
          effective_on: String(form.get('effective_on') ?? '') || null,
        }),
      })
      notify('Employee transferred. The previous legal employer remains on the closed record.', 'success')
      onClose()
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The transfer failed.')
    } finally {
      setPending(false)
    }
  }

  return (
    <Dialog open={open} title="Transfer employee" description="A new employment record is opened in the destination business." onClose={onClose}>
      <form className="grid gap-3" onSubmit={(event) => void submit(event)}>
        <Field label="Destination business" htmlFor="target_organization_id">
          <Select
            id="target_organization_id"
            value={targetId}
            placeholder="Select"
            options={targets.map((organization) => ({ value: String(organization.id), label: organization.name }))}
            onChange={(event) => setTargetId(event.target.value)}
          />
        </Field>
        <Field label="Legal employer" htmlFor="transfer-legal">
          <Select
            id="transfer-legal"
            name="legal_entity_id"
            placeholder="Select"
            options={(placement.data?.legal_entities ?? []).map((entity) => ({ value: String(entity.id), label: entity.name }))}
          />
        </Field>
        <Field label="New employee ID" htmlFor="transfer-code">
          <Input id="transfer-code" name="employee_code" required />
        </Field>
        <Field label="Effective date" htmlFor="transfer-date">
          <Input id="transfer-date" name="effective_on" type="date" />
        </Field>
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <Button type="submit" disabled={pending || targetId === ''}>
          {pending ? 'Transferring…' : 'Transfer'}
        </Button>
      </form>
    </Dialog>
  )
}

function EmployeePhoto({ employeeId, hasPhoto, name }: { employeeId: number; hasPhoto: boolean; name: string }) {
  const photo = useQuery({
    queryKey: ['employee-photo', employeeId, hasPhoto],
    enabled: hasPhoto,
    queryFn: async () => {
      const response = await apiFetch(`/api/v1/employees/${employeeId}/photo`)
      if (!response.ok) throw new ApiError('Photo unavailable', response.status)
      return URL.createObjectURL(await response.blob())
    },
  })
  if (!hasPhoto || !photo.data) {
    return <div className="flex size-16 items-center justify-center rounded-full bg-muted text-sm font-medium">{name.slice(0, 1)}</div>
  }
  return <img src={photo.data} alt="" className="size-16 rounded-full object-cover" />
}

function PhotoUpload({ employeeId }: { employeeId: number }) {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)

  async function upload(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const file = new FormData(event.currentTarget).get('file')
    if (!(file instanceof File) || file.size === 0) return
    const body = new FormData()
    body.append('file', file)
    setPending(true)
    try {
      await apiData(`/api/v1/employees/${employeeId}/photo`, { method: 'POST', body })
      notify('Profile photo saved.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['employee'] })
      await queryClient.invalidateQueries({ queryKey: ['employee-photo'] })
    } catch (error) {
      notify(error instanceof ApiError ? error.message : 'The photo could not be saved.', 'danger')
    } finally {
      setPending(false)
    }
  }

  return (
    <form className="flex items-end gap-2" onSubmit={(event) => void upload(event)}>
      <Field label="Profile photo" htmlFor="photo-file">
        <Input id="photo-file" name="file" type="file" accept="image/png,image/jpeg,image/webp" />
      </Field>
      <Button type="submit" variant="outline" disabled={pending}>
        {pending ? 'Uploading…' : 'Upload'}
      </Button>
    </form>
  )
}

function usePlacementOptions() {
  const { organizationId } = useSession()
  const entities = useQuery({
    queryKey: ['legal-entities', organizationId],
    enabled: organizationId !== null,
    queryFn: () => apiData<Array<{ id: number; legal_name: string }>>('/api/v1/legal-entities'),
  })
  const brands = useQuery({ queryKey: ['brands', organizationId], enabled: organizationId !== null, queryFn: () => apiData<NamedRef[]>('/api/v1/brands') })
  const departments = useQuery({ queryKey: ['departments', organizationId], enabled: organizationId !== null, queryFn: () => apiData<NamedRef[]>('/api/v1/departments') })
  const designations = useQuery({ queryKey: ['designations', organizationId], enabled: organizationId !== null, queryFn: () => apiData<NamedRef[]>('/api/v1/designations') })
  const branches = useQuery({ queryKey: ['branches', organizationId], enabled: organizationId !== null, queryFn: () => apiData<NamedRef[]>('/api/v1/branches') })
  return {
    legalEntities: (entities.data ?? []).map((entity) => ({ id: entity.id, name: entity.legal_name })),
    brands: brands.data ?? [],
    departments: departments.data ?? [],
    designations: designations.data ?? [],
    branches: branches.data ?? [],
  }
}

function numberOrNull(value: FormDataEntryValue | null): number | null {
  const text = String(value ?? '')
  return text === '' ? null : Number(text)
}
