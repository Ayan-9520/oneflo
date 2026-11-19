import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'

import { useSession } from '@/app/session'
import { Button } from '@/components/ui/button'
import { DataTable } from '@/components/ui/data-table'
import { Dialog } from '@/components/ui/dialog'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { FilterBar } from '@/components/ui/filter-bar'
import { Field, Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/page-header'
import { Select } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { useToast } from '@/components/ui/toast'
import { ApiError, apiData, apiFetch } from '@/lib/api'

import { employmentStatuses, employmentTypes, type EmployeeRecord, type NamedRef } from './employee'

type Filters = {
  q: string
  status: string
  departmentId: string
}

const emptyFilters: Filters = { q: '', status: '', departmentId: '' }

export function PeoplePage() {
  const { organizationId, permissions } = useSession()
  const [filters, setFilters] = useState<Filters>(emptyFilters)
  const [adding, setAdding] = useState(false)
  const [importing, setImporting] = useState(false)
  const [selected, setSelected] = useState<number[]>([])
  const [bulkDepartment, setBulkDepartment] = useState('')
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const canCreate = permissions.has('employees.create')
  const canUpdate = permissions.has('employees.update')

  const departments = useQuery({
    queryKey: ['departments', organizationId],
    enabled: organizationId !== null,
    queryFn: () => apiData<NamedRef[]>('/api/v1/departments'),
  })

  const params = new URLSearchParams()
  if (filters.q) params.set('q', filters.q)
  if (filters.status) params.set('status', filters.status)
  if (filters.departmentId) params.set('department_id', filters.departmentId)
  const query = params.toString()

  const employees = useQuery({
    queryKey: ['employees', organizationId, query],
    enabled: organizationId !== null,
    queryFn: () => apiData<EmployeeRecord[]>(`/api/v1/employees${query ? `?${query}` : ''}`),
  })

  const bulk = useMutation({
    mutationFn: () =>
      apiData('/api/v1/employees/bulk', {
        method: 'POST',
        body: JSON.stringify({ employee_ids: selected, department_id: Number(bulkDepartment) }),
      }),
    onSuccess: async () => {
      notify('Selected employees were updated.', 'success')
      setSelected([])
      await queryClient.invalidateQueries({ queryKey: ['employees'] })
    },
    onError: (error) => notify(error instanceof ApiError ? error.message : 'The bulk update failed.', 'danger'),
  })

  async function exportEmployees() {
    const response = await apiFetch('/api/v1/employees/export')
    if (!response.ok) {
      notify('The employee export failed.', 'danger')
      return
    }
    const url = URL.createObjectURL(await response.blob())
    const link = document.createElement('a')
    link.href = url
    link.download = 'employees.xlsx'
    link.click()
    URL.revokeObjectURL(url)
  }

  if (organizationId === null) {
    return (
      <div>
        <PageHeader title="People" description="Employees in the active business." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'People' }]} />
        <EmptyState title="Select a business" description="Employee records stay inside the selected business." />
      </div>
    )
  }

  return (
    <div>
      <PageHeader
        title="People"
        description="Directory, imports, and employment changes for the active business."
        crumbs={[{ label: 'Workspace', to: '/' }, { label: 'People' }]}
        actions={
          <>
            <Button variant="outline" onClick={() => void exportEmployees()}>
              Export
            </Button>
            {canCreate ? (
              <Button variant="outline" onClick={() => setImporting(true)}>
                Import
              </Button>
            ) : null}
            {canCreate ? <Button onClick={() => setAdding(true)}>Add employee</Button> : null}
          </>
        }
      />
      <FilterBar>
        <Input
          aria-label="Search employees"
          placeholder="Search name, email, or ID"
          value={filters.q}
          onChange={(event) => setFilters((current) => ({ ...current, q: event.target.value }))}
        />
        <Select
          aria-label="Employment status"
          value={filters.status}
          options={employmentStatuses}
          onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}
        />
        <Select
          aria-label="Department"
          value={filters.departmentId}
          options={[{ value: '', label: 'All departments' }, ...(departments.data ?? []).map((department) => ({ value: String(department.id), label: department.name }))]}
          onChange={(event) => setFilters((current) => ({ ...current, departmentId: event.target.value }))}
        />
      </FilterBar>
      <div className="mt-4">
        {employees.isPending ? (
          <Skeleton className="h-40 w-full" />
        ) : employees.isError ? (
          <ErrorState title="Employees could not be loaded" description={employees.error instanceof ApiError ? employees.error.message : 'Try again.'} />
        ) : employees.data.length === 0 ? (
          <EmptyState title="No employees" description="Add an employee or import an Excel file for this business." />
        ) : (
          <DataTable
            caption="Employees"
            rows={employees.data}
            getRowId={(row) => String(row.id)}
            columns={[
              {
                id: 'select',
                header: '',
                cell: (row) => (
                  <input
                    type="checkbox"
                    aria-label={`Select ${row.full_name}`}
                    checked={selected.includes(row.id)}
                    onChange={(event) =>
                      setSelected((current) => (event.target.checked ? [...current, row.id] : current.filter((id) => id !== row.id)))
                    }
                  />
                ),
              },
              { id: 'code', header: 'Employee ID', cell: (row) => row.employee_code },
              {
                id: 'name',
                header: 'Name',
                cell: (row) => (
                  <Link className="font-medium text-primary-dark hover:underline" to={`/people/${row.id}`}>
                    {row.full_name}
                  </Link>
                ),
              },
              { id: 'email', header: 'Email', cell: (row) => row.email ?? '' },
              { id: 'phone', header: 'Phone', cell: (row) => row.phone ?? '' },
              { id: 'brand', header: 'Business', cell: (row) => row.brand?.name ?? '' },
              { id: 'legal', header: 'Legal employer', cell: (row) => row.legal_entity?.name ?? '' },
              { id: 'department', header: 'Department', cell: (row) => row.department?.name ?? '' },
              { id: 'designation', header: 'Designation', cell: (row) => row.designation?.name ?? '' },
              { id: 'manager', header: 'Manager', cell: (row) => row.manager?.full_name ?? '' },
              { id: 'type', header: 'Type', cell: (row) => row.employment_type },
              { id: 'joined', header: 'Joined', cell: (row) => row.joined_on ?? '' },
              { id: 'status', header: 'Status', cell: (row) => row.status },
              { id: 'branch', header: 'Branch', cell: (row) => row.branch?.name ?? '' },
              { id: 'location', header: 'Work location', cell: (row) => row.work_location ?? '' },
            ]}
          />
        )}
      </div>
      {canUpdate && selected.length > 0 ? (
        <div className="mt-4 flex flex-wrap items-end gap-2">
          <Field label="Move selected to department" htmlFor="bulk-department">
            <Select
              id="bulk-department"
              value={bulkDepartment}
              placeholder="Department"
              options={(departments.data ?? []).map((department) => ({ value: String(department.id), label: department.name }))}
              onChange={(event) => setBulkDepartment(event.target.value)}
            />
          </Field>
          <Button disabled={bulkDepartment === '' || bulk.isPending} onClick={() => bulk.mutate()}>
            {bulk.isPending ? 'Updating…' : 'Update selected'}
          </Button>
        </div>
      ) : null}
      <EmployeeFormDialog open={adding} onClose={() => setAdding(false)} />
      <ImportDialog open={importing} onClose={() => setImporting(false)} />
    </div>
  )
}

function EmployeeFormDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const { organizationId } = useSession()
  const [error, setError] = useState<ApiError | null>(null)
  const [pending, setPending] = useState(false)
  const departments = useQuery({
    queryKey: ['departments', organizationId],
    enabled: open,
    queryFn: () => apiData<NamedRef[]>('/api/v1/departments'),
  })
  const designations = useQuery({
    queryKey: ['designations', organizationId],
    enabled: open,
    queryFn: () => apiData<NamedRef[]>('/api/v1/designations'),
  })
  const branches = useQuery({
    queryKey: ['branches', organizationId],
    enabled: open,
    queryFn: () => apiData<Array<NamedRef & { code?: string }>>('/api/v1/branches'),
  })
  const entities = useQuery({
    queryKey: ['legal-entities', organizationId],
    enabled: open,
    queryFn: () => apiData<Array<{ id: number; legal_name: string }>>('/api/v1/legal-entities'),
  })
  const brands = useQuery({
    queryKey: ['brands', organizationId],
    enabled: open,
    queryFn: () => apiData<NamedRef[]>('/api/v1/brands'),
  })

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const body: Record<string, string | number | null> = {
      employee_code: String(form.get('employee_code') ?? ''),
      given_name: String(form.get('given_name') ?? ''),
      family_name: String(form.get('family_name') ?? ''),
      email: String(form.get('email') ?? '') || null,
      phone: String(form.get('phone') ?? '') || null,
      employment_type: String(form.get('employment_type') ?? 'permanent'),
      joined_on: String(form.get('joined_on') ?? '') || null,
      work_location: String(form.get('work_location') ?? '') || null,
    }
    for (const field of ['department_id', 'designation_id', 'branch_id', 'legal_entity_id', 'brand_id', 'manager_id']) {
      const value = String(form.get(field) ?? '')
      if (value !== '') body[field] = Number(value)
    }
    setPending(true)
    setError(null)
    try {
      await apiData('/api/v1/employees', { method: 'POST', body: JSON.stringify(body) })
      notify('Employee added.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['employees'] })
      onClose()
    } catch (caught) {
      setError(caught instanceof ApiError ? caught : new ApiError('The employee could not be added.', 0))
    } finally {
      setPending(false)
    }
  }

  const message = (field: string) => error?.errors[field]?.[0]

  return (
    <Dialog open={open} title="Add employee" description="The employee is created in the active business." onClose={onClose}>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => void submit(event)}>
        <Field label="Employee ID" htmlFor="employee_code" error={message('employee_code')}>
          <Input id="employee_code" name="employee_code" required />
        </Field>
        <Field label="Joining date" htmlFor="joined_on" error={message('joined_on')}>
          <Input id="joined_on" name="joined_on" type="date" />
        </Field>
        <Field label="Given name" htmlFor="given_name" error={message('given_name')}>
          <Input id="given_name" name="given_name" required />
        </Field>
        <Field label="Family name" htmlFor="family_name" error={message('family_name')}>
          <Input id="family_name" name="family_name" required />
        </Field>
        <Field label="Email" htmlFor="email" error={message('email')}>
          <Input id="email" name="email" type="email" />
        </Field>
        <Field label="Phone" htmlFor="phone" error={message('phone')}>
          <Input id="phone" name="phone" />
        </Field>
        <Field label="Legal employer" htmlFor="legal_entity_id" error={message('legal_entity_id')}>
          <Select id="legal_entity_id" name="legal_entity_id" placeholder="Select" options={(entities.data ?? []).map((entity) => ({ value: String(entity.id), label: entity.legal_name }))} />
        </Field>
        <Field label="Business / brand" htmlFor="brand_id" error={message('brand_id')}>
          <Select id="brand_id" name="brand_id" placeholder="Select" options={(brands.data ?? []).map((brand) => ({ value: String(brand.id), label: brand.name }))} />
        </Field>
        <Field label="Department" htmlFor="department_id" error={message('department_id')}>
          <Select id="department_id" name="department_id" placeholder="Select" options={(departments.data ?? []).map((item) => ({ value: String(item.id), label: item.name }))} />
        </Field>
        <Field label="Designation" htmlFor="designation_id" error={message('designation_id')}>
          <Select id="designation_id" name="designation_id" placeholder="Select" options={(designations.data ?? []).map((item) => ({ value: String(item.id), label: item.name }))} />
        </Field>
        <Field label="Branch" htmlFor="branch_id" error={message('branch_id')}>
          <Select id="branch_id" name="branch_id" placeholder="Select" options={(branches.data ?? []).map((item) => ({ value: String(item.id), label: item.name }))} />
        </Field>
        <Field label="Employment type" htmlFor="employment_type" error={message('employment_type')}>
          <Select id="employment_type" name="employment_type" options={employmentTypes} defaultValue="permanent" />
        </Field>
        <Field label="Work location" htmlFor="work_location" error={message('work_location')}>
          <Input id="work_location" name="work_location" />
        </Field>
        <Field label="Reporting manager ID" htmlFor="manager_id" error={message('manager_id')}>
          <Input id="manager_id" name="manager_id" inputMode="numeric" />
        </Field>
        {error && Object.keys(error.errors).length === 0 ? <p className="text-sm text-destructive sm:col-span-2">{error.message}</p> : null}
        <div className="sm:col-span-2">
          <Button type="submit" disabled={pending}>
            {pending ? 'Saving…' : 'Save employee'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}

function ImportDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)
  const [summary, setSummary] = useState<string | null>(null)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const file = new FormData(event.currentTarget).get('file')
    if (!(file instanceof File)) return
    const body = new FormData()
    body.append('file', file)
    setPending(true)
    try {
      const result = await apiData<{ created: number; errors: Array<{ row: number; message: string }> }>('/api/v1/employees/import', {
        method: 'POST',
        body,
      })
      setSummary(`${result.created} employees added. ${result.errors.length} rows need attention.`)
      notify('Import finished.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['employees'] })
    } catch (error) {
      notify(error instanceof ApiError ? error.message : 'The import failed.', 'danger')
    } finally {
      setPending(false)
    }
  }

  return (
    <Dialog open={open} title="Import employees" description="Use the exported column names. Unknown departments are reported and not created." onClose={onClose}>
      <form className="flex flex-col gap-3" onSubmit={(event) => void submit(event)}>
        <Field label="Excel file" htmlFor="import-file">
          <Input id="import-file" name="file" type="file" accept=".xlsx" required />
        </Field>
        {summary ? <p className="text-sm text-muted-foreground">{summary}</p> : null}
        <Button type="submit" disabled={pending}>
          {pending ? 'Importing…' : 'Import'}
        </Button>
      </form>
    </Dialog>
  )
}
