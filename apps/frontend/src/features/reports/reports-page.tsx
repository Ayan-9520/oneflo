import { useQuery, useQueryClient } from '@tanstack/react-query'
import { type FormEvent, useState } from 'react'

import { useSession } from '@/app/session'
import { Button } from '@/components/ui/button'
import { DataTable } from '@/components/ui/data-table'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { Field, Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/page-header'
import { Select } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { useToast } from '@/components/ui/toast'
import { ApiError, apiData, apiFetch } from '@/lib/api'

const reports = [
  ['headcount', 'Headcount'],
  ['attendance', 'Attendance'],
  ['leave', 'Leave'],
  ['payroll', 'Payroll'],
  ['department', 'Department'],
  ['employee-cost', 'Employee cost'],
  ['expense', 'Expense'],
  ['asset', 'Asset'],
  ['performance', 'Performance'],
  ['recruitment', 'Recruitment'],
  ['attrition', 'Attrition'],
  ['employee-attendance', 'Individual attendance'],
  ['employee-leave', 'Individual leave'],
  ['employee-payslips', 'Payslip history'],
  ['employee-tax', 'Tax documents'],
  ['employee-performance', 'Performance history'],
  ['employee-assets', 'Assigned assets'],
] as const

type Dashboard = {
  headcount: number
  joiners: number
  exits: number
  present_today: number
  absent_today: number
  leave_pending: number
  payroll_gross: string
  openings: number
  expense_claims: number
}

type ReportTable = { columns: string[]; rows: string[][] }

type Filters = {
  from: string
  to: string
  legal_entity_id: string
  department_id: string
  branch_id: string
  designation_id: string
  employee_id: string
}

const emptyFilters: Filters = {
  from: '',
  to: '',
  legal_entity_id: '',
  department_id: '',
  branch_id: '',
  designation_id: '',
  employee_id: '',
}

function queryString(filters: Filters): string {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(filters)) {
    if (value !== '') params.set(key, value)
  }
  const text = params.toString()
  return text === '' ? '' : `?${text}`
}

async function downloadReport(report: string, filters: Filters, format: 'csv' | 'xlsx' | 'pdf') {
  const response = await apiFetch(`/api/v1/reports/${report}/export${queryString({ ...filters })}${queryString(filters) === '' ? '?' : '&'}format=${format}`)
  if (!response.ok) {
    const body = await response.json().catch(() => null) as { message?: string } | null
    throw new ApiError(body?.message ?? 'The export failed.', response.status)
  }
  const url = URL.createObjectURL(await response.blob())
  const link = document.createElement('a')
  link.href = url
  link.download = `${report}.${format === 'xlsx' ? 'xlsx' : format}`
  link.click()
  URL.revokeObjectURL(url)
}

export function ReportsPage() {
  const { organizationId, permissions } = useSession()
  const canExport = permissions.has('reports.export')
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [report, setReport] = useState<string>('headcount')
  const [filters, setFilters] = useState<Filters>(emptyFilters)
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)
  const [filterName, setFilterName] = useState('')
  const dashboard = useQuery({
    queryKey: ['reports-dashboard', organizationId, filters],
    enabled: organizationId !== null,
    queryFn: () => apiData<Dashboard>(`/api/v1/reports/dashboard${queryString(filters)}`),
  })
  const table = useQuery({
    queryKey: ['reports', organizationId, report, filters],
    enabled: organizationId !== null,
    queryFn: () => apiData<ReportTable>(`/api/v1/reports/${report}${queryString(filters)}`),
  })
  const entities = useQuery({ queryKey: ['legal-entities', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; legal_name: string }>>('/api/v1/legal-entities') })
  const departments = useQuery({ queryKey: ['departments', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; name: string }>>('/api/v1/departments') })
  const branches = useQuery({ queryKey: ['branches', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; name: string }>>('/api/v1/branches') })
  const designations = useQuery({ queryKey: ['designations', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; name: string }>>('/api/v1/designations') })
  const employees = useQuery({ queryKey: ['employees', organizationId, 'reports'], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; employee_code: string; full_name: string }>>('/api/v1/employees') })

  if (organizationId === null) return <EmptyState title="Select a business" description="Reports stay inside the active business." />

  function update(key: keyof Filters, value: string) {
    setFilters((current) => ({ ...current, [key]: value }))
  }

  async function saveFilter(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setPending(true)
    setError(null)
    try {
      await apiData('/api/v1/reports/filters', { method: 'POST', body: JSON.stringify({ name: filterName, report, filters }) })
      notify('Filter saved.', 'success')
      setFilterName('')
      await queryClient.invalidateQueries({ queryKey: ['report-filters'] })
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The filter could not be saved.')
    } finally {
      setPending(false)
    }
  }

  async function schedule(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const body = new FormData(event.currentTarget)
    setPending(true)
    setError(null)
    try {
      await apiData('/api/v1/reports/schedules', {
        method: 'POST',
        body: JSON.stringify({
          report,
          filters,
          format: String(body.get('format')),
          cadence: String(body.get('cadence')),
          next_run_on: String(body.get('next_run_on')),
        }),
      })
      notify('Report scheduled.', 'success')
      event.currentTarget.reset()
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The schedule could not be saved.')
    } finally {
      setPending(false)
    }
  }

  async function exportFile(format: 'csv' | 'xlsx' | 'pdf') {
    setPending(true)
    setError(null)
    try {
      await downloadReport(report, filters, format)
      notify('Export downloaded.', 'success')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The export failed.')
    } finally {
      setPending(false)
    }
  }

  const cards: Array<[string, string]> = dashboard.data === undefined ? [] : [
    ['Active employees', String(dashboard.data.headcount)],
    ['New joiners', String(dashboard.data.joiners)],
    ['Exits', String(dashboard.data.exits)],
    ['Present today', String(dashboard.data.present_today)],
    ['Absent today', String(dashboard.data.absent_today)],
    ['Leave pending', String(dashboard.data.leave_pending)],
    ['Locked payroll gross', dashboard.data.payroll_gross],
    ['Open positions', String(dashboard.data.openings)],
    ['Expense claims', String(dashboard.data.expense_claims)],
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Reports" description="Executive counts and business reports for the active organization. The business is the organization you have selected." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Reports' }]} />
      {dashboard.isPending ? <Skeleton className="h-24 w-full" /> : dashboard.isError ? <ErrorState title="The dashboard could not be loaded" description={dashboard.error instanceof ApiError ? dashboard.error.message : 'Try again.'} /> : (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {cards.map(([label, value]) => (
            <div key={label} className="rounded-lg border border-border bg-card p-4">
              <p className="text-sm text-muted-foreground">{label}</p>
              <p className="mt-1 text-2xl font-semibold">{value}</p>
            </div>
          ))}
        </div>
      )}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Field label="Report" htmlFor="report">
          <Select id="report" value={report} options={reports.map(([value, label]) => ({ value, label }))} onChange={(event) => setReport(event.target.value)} />
        </Field>
        <Field label="From" htmlFor="from"><Input id="from" type="date" value={filters.from} onChange={(event) => update('from', event.target.value)} /></Field>
        <Field label="To" htmlFor="to"><Input id="to" type="date" value={filters.to} onChange={(event) => update('to', event.target.value)} /></Field>
        <Field label="Legal entity" htmlFor="legal-entity">
          <Select id="legal-entity" value={filters.legal_entity_id} options={[{ value: '', label: 'All legal entities' }, ...(entities.data ?? []).map((row) => ({ value: String(row.id), label: row.legal_name }))]} onChange={(event) => update('legal_entity_id', event.target.value)} />
        </Field>
        <Field label="Department" htmlFor="department">
          <Select id="department" value={filters.department_id} options={[{ value: '', label: 'All departments' }, ...(departments.data ?? []).map((row) => ({ value: String(row.id), label: row.name }))]} onChange={(event) => update('department_id', event.target.value)} />
        </Field>
        <Field label="Branch" htmlFor="branch">
          <Select id="branch" value={filters.branch_id} options={[{ value: '', label: 'All branches' }, ...(branches.data ?? []).map((row) => ({ value: String(row.id), label: row.name }))]} onChange={(event) => update('branch_id', event.target.value)} />
        </Field>
        <Field label="Designation" htmlFor="designation">
          <Select id="designation" value={filters.designation_id} options={[{ value: '', label: 'All designations' }, ...(designations.data ?? []).map((row) => ({ value: String(row.id), label: row.name }))]} onChange={(event) => update('designation_id', event.target.value)} />
        </Field>
        <Field label="Employee" htmlFor="employee">
          <Select id="employee" value={filters.employee_id} options={[{ value: '', label: 'All visible employees' }, ...(employees.data ?? []).map((row) => ({ value: String(row.id), label: `${row.full_name} · ${row.employee_code}` }))]} onChange={(event) => update('employee_id', event.target.value)} />
        </Field>
      </div>
      {error ? <ErrorState title="The report action failed" description={error} /> : null}
      <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        {canExport ? <Button type="button" disabled={pending} onClick={() => void exportFile('xlsx')}>Excel</Button> : null}
        {canExport ? <Button type="button" variant="outline" disabled={pending} onClick={() => void exportFile('csv')}>CSV</Button> : null}
        {canExport ? <Button type="button" variant="outline" disabled={pending} onClick={() => void exportFile('pdf')}>PDF</Button> : null}
        <Button type="button" variant="secondary" onClick={() => window.print()}>Print</Button>
      </div>
      {table.isPending ? <Skeleton className="h-40 w-full" /> : table.isError ? <ErrorState title="The report could not be loaded" description={table.error instanceof ApiError ? table.error.message : 'Try again.'} /> : table.data.rows.length === 0 ? <EmptyState title="No rows" description="No records match these filters." /> : (
        <div id="report-print">
          <DataTable
            caption={reports.find(([value]) => value === report)?.[1] ?? 'Report'}
            rows={table.data.rows.map((cells, index) => ({ id: String(index), cells }))}
            getRowId={(row) => row.id}
            columns={table.data.columns.map((column, index) => ({ id: column, header: column, cell: (row: { id: string; cells: string[] }) => row.cells[index] ?? '' }))}
          />
        </div>
      )}
      <form className="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto] sm:items-end" onSubmit={(event) => void saveFilter(event)}>
        <Field label="Save these filters" htmlFor="filter-name"><Input id="filter-name" value={filterName} required maxLength={120} onChange={(event) => setFilterName(event.target.value)} /></Field>
        <Button type="submit" variant="outline" disabled={pending}>Save filter</Button>
      </form>
      {canExport ? (
        <form className="grid grid-cols-1 gap-3 sm:grid-cols-4 sm:items-end" onSubmit={(event) => void schedule(event)}>
          <Field label="Format" htmlFor="schedule-format"><Select id="schedule-format" name="format" options={[{ value: 'csv', label: 'CSV' }, { value: 'xlsx', label: 'Excel' }, { value: 'pdf', label: 'PDF' }]} /></Field>
          <Field label="Cadence" htmlFor="schedule-cadence"><Select id="schedule-cadence" name="cadence" options={[{ value: 'daily', label: 'Daily' }, { value: 'weekly', label: 'Weekly' }, { value: 'monthly', label: 'Monthly' }]} /></Field>
          <Field label="Next run" htmlFor="schedule-date"><Input id="schedule-date" name="next_run_on" type="date" required /></Field>
          <Button type="submit" disabled={pending}>Schedule</Button>
        </form>
      ) : null}
    </div>
  )
}
