import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'

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

type Entity = { id: number; legal_name: string }
type ComponentRow = { id: number; code: string; name: string; kind: string }
type Structure = { id: number; name: string }
type Rule = { id: number; code: string; effective_on: string; confirmed: boolean }
type EmployeeRow = { id: number; full_name: string; employee_code: string }
type PayrollLine = { id: number; employee_code: string | null; name: string | null; lop_days: number; gross: string; deductions: string; net: string }
type PayrollRun = { id: number; year: number; month: number; status: string; warnings: string[]; lines: PayrollLine[] }
type Form16 = { disclaimer: string; deductor: { legal_name: string }; financial_year: string; form_12ba: string }

export function PayrollPage() {
  const { organizationId, permissions } = useSession()
  const canProcess = permissions.has('payroll.process')
  const entities = useQuery({ queryKey: ['legal-entities', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Entity[]>('/api/v1/legal-entities') })
  const components = useQuery({ queryKey: ['payroll-components', organizationId], enabled: organizationId !== null, queryFn: () => apiData<ComponentRow[]>('/api/v1/payroll/components') })
  const structures = useQuery({ queryKey: ['payroll-structures', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Structure[]>('/api/v1/payroll/structures') })
  const rules = useQuery({ queryKey: ['statutory-rules', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Rule[]>('/api/v1/statutory-rules') })
  const runs = useQuery({ queryKey: ['payroll-runs', organizationId], enabled: organizationId !== null, queryFn: () => apiData<PayrollRun[]>('/api/v1/payroll/runs') })
  const employees = useQuery({ queryKey: ['employees', organizationId, 'payroll'], enabled: organizationId !== null, queryFn: () => apiData<EmployeeRow[]>('/api/v1/employees') })

  if (organizationId === null) return <EmptyState title="Select a business" description="Payroll belongs to the active business and its legal employer." />

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Payroll" description="Salary structures, confirmed statutory rules, and locked payroll for the legal employer. Rates are entered by the business and are not a legal certificate." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Payroll' }]} />
      {runs.isPending ? <Skeleton className="h-24 w-full" /> : runs.isError ? <ErrorState title="Payroll could not be loaded" description={runs.error instanceof ApiError ? runs.error.message : 'Try again.'} /> : runs.data.length === 0 ? <EmptyState title="No payroll runs" description="Open a month after the salary structure and attendance are ready." /> : (
        <DataTable caption="Payroll runs" rows={runs.data} getRowId={(row) => String(row.id)} columns={[
          { id: 'period', header: 'Month', cell: (row) => `${row.year}-${String(row.month).padStart(2, '0')}` },
          { id: 'status', header: 'Status', cell: (row) => row.status },
          { id: 'net', header: 'First net', cell: (row) => row.lines[0]?.net ?? '—' },
          { id: 'warnings', header: 'Warnings', cell: (row) => String(row.warnings?.length ?? 0) },
        ]} />
      )}
      <Form16Panel entities={entities.data ?? []} />
      {canProcess ? (
        <>
          <ComponentForm />
          <StructureForm entities={entities.data ?? []} components={components.data ?? []} />
          <RuleForm entities={entities.data ?? []} rules={rules.data ?? []} />
          <CompensationForm employees={employees.data ?? []} structures={structures.data ?? []} />
          <RunForm entities={entities.data ?? []} runs={runs.data ?? []} />
        </>
      ) : null}
    </div>
  )
}

function usePayrollSubmit() {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function send(path: string, body: Record<string, unknown> | null, success: string) {
    setPending(true)
    setError(null)
    try {
      await apiData(path, body === null ? { method: 'POST' } : { method: 'POST', body: JSON.stringify(body) })
      await queryClient.invalidateQueries({ queryKey: ['payroll-components'] })
      await queryClient.invalidateQueries({ queryKey: ['payroll-structures'] })
      await queryClient.invalidateQueries({ queryKey: ['statutory-rules'] })
      await queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
      notify(success, 'success')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The payroll request failed.')
    } finally {
      setPending(false)
    }
  }

  return { pending, error, send }
}

function ComponentForm() {
  const form = usePayrollSubmit()
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const element = event.currentTarget
    const body = new FormData(element)
    await form.send('/api/v1/payroll/components', {
      code: String(body.get('code') ?? ''),
      name: String(body.get('name') ?? ''),
      kind: String(body.get('kind') ?? 'earning'),
      taxable: body.get('taxable') === 'yes',
    }, 'Salary component saved.')
    if (form.error === null) element.reset()
  }
  return (
    <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => void submit(event)}>
      <h2 className="sm:col-span-2 text-base font-semibold">Salary component</h2>
      <Field label="Code" htmlFor="component-code"><Input id="component-code" name="code" required /></Field>
      <Field label="Name" htmlFor="component-name"><Input id="component-name" name="name" required /></Field>
      <Field label="Kind" htmlFor="component-kind">
        <Select id="component-kind" name="kind" defaultValue="earning" options={[
          { value: 'earning', label: 'Earning' },
          { value: 'deduction', label: 'Deduction' },
          { value: 'employer_contribution', label: 'Employer contribution' },
          { value: 'reimbursement', label: 'Reimbursement' },
          { value: 'variable', label: 'Variable pay' },
        ]} />
      </Field>
      <Field label="Taxable" htmlFor="component-taxable">
        <Select id="component-taxable" name="taxable" defaultValue="yes" options={[{ value: 'yes', label: 'Yes' }, { value: 'no', label: 'No' }]} />
      </Field>
      {form.error ? <p className="text-sm text-destructive sm:col-span-2">{form.error}</p> : null}
      <Button type="submit" disabled={form.pending}>{form.pending ? 'Saving…' : 'Save component'}</Button>
    </form>
  )
}

function StructureForm({ entities, components }: { entities: Entity[]; components: ComponentRow[] }) {
  const form = usePayrollSubmit()
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const body = new FormData(event.currentTarget)
    const lines = [1, 2].flatMap((index) => {
      const component = String(body.get(`component_${index}`) ?? '')
      const amount = String(body.get(`amount_${index}`) ?? '')
      if (component === '' || amount === '') return []
      return [{ payroll_component_id: Number(component), monthly_amount: Number(amount) }]
    })
    await form.send('/api/v1/payroll/structures', {
      legal_entity_id: Number(body.get('legal_entity_id')),
      name: String(body.get('name') ?? ''),
      effective_on: String(body.get('effective_on') ?? ''),
      lines,
    }, 'Salary structure saved.')
  }
  const componentOptions = components.map((component) => ({ value: String(component.id), label: component.name }))
  return (
    <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => void submit(event)}>
      <h2 className="sm:col-span-2 text-base font-semibold">Salary structure</h2>
      <EntitySelect entities={entities} id="structure-entity" />
      <Field label="Name" htmlFor="structure-name"><Input id="structure-name" name="name" required /></Field>
      <Field label="Effective on" htmlFor="structure-effective"><Input id="structure-effective" name="effective_on" type="date" required /></Field>
      <Field label="Component" htmlFor="component-1"><Select id="component-1" name="component_1" options={componentOptions} /></Field>
      <Field label="Monthly amount" htmlFor="amount-1"><Input id="amount-1" name="amount_1" type="number" min="0" step="0.01" required /></Field>
      <Field label="Second component" htmlFor="component-2"><Select id="component-2" name="component_2" placeholder="None" options={componentOptions} /></Field>
      <Field label="Second amount" htmlFor="amount-2"><Input id="amount-2" name="amount_2" type="number" min="0" step="0.01" /></Field>
      {form.error ? <p className="text-sm text-destructive sm:col-span-2">{form.error}</p> : null}
      <Button type="submit" disabled={form.pending}>{form.pending ? 'Saving…' : 'Save structure'}</Button>
    </form>
  )
}

function RuleForm({ entities, rules }: { entities: Entity[]; rules: Rule[] }) {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const element = event.currentTarget
    const body = new FormData(element)
    let config: unknown
    try {
      config = JSON.parse(String(body.get('config') ?? ''))
    } catch {
      setError('The rule config must be JSON from the compliance review.')
      return
    }
    setPending(true)
    setError(null)
    try {
      const created = await apiData<{ id: number }>('/api/v1/statutory-rules', {
        method: 'POST',
        body: JSON.stringify({
          legal_entity_id: Number(body.get('legal_entity_id')),
          code: String(body.get('code') ?? ''),
          effective_on: String(body.get('effective_on') ?? ''),
          citation: String(body.get('citation') ?? ''),
          config,
        }),
      })
      if (body.get('confirm') === 'yes') {
        await apiData(`/api/v1/statutory-rules/${created.id}/confirm`, { method: 'POST' })
      }
      await queryClient.invalidateQueries({ queryKey: ['statutory-rules'] })
      notify(body.get('confirm') === 'yes' ? 'Statutory rule confirmed for this effective date.' : 'Draft rule saved. Payroll will not use it until it is confirmed.', 'success')
      element.reset()
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The rule could not be saved.')
    } finally {
      setPending(false)
    }
  }

  return (
    <form className="grid gap-3" onSubmit={(event) => void submit(event)}>
      <h2 className="text-base font-semibold">Statutory rule</h2>
      <p className="text-sm text-muted-foreground">Enter the rate, ceiling, slabs, and citation the legal employer has reviewed. A draft is not used in payroll until it is confirmed.</p>
      <div className="grid gap-3 sm:grid-cols-2">
        <EntitySelect entities={entities} id="rule-entity" />
        <Field label="Code" htmlFor="rule-code">
          <Select id="rule-code" name="code" defaultValue="epf" options={['epf', 'esi', 'professional_tax', 'tds', 'tds_old', 'bonus', 'gratuity'].map((code) => ({ value: code, label: code }))} />
        </Field>
        <Field label="Effective on" htmlFor="rule-effective"><Input id="rule-effective" name="effective_on" type="date" required /></Field>
        <Field label="Confirm now" htmlFor="rule-confirm">
          <Select id="rule-confirm" name="confirm" defaultValue="no" options={[{ value: 'no', label: 'Save as draft' }, { value: 'yes', label: 'Confirm for payroll' }]} />
        </Field>
        <Field label="Citation" htmlFor="rule-citation"><Input id="rule-citation" name="citation" required placeholder="Notification or internal review reference" /></Field>
      </div>
      <Field label="Config JSON" htmlFor="rule-config"><Textarea id="rule-config" name="config" required rows={4} placeholder='{"employee_percent":0,"employer_percent":0,"wage_ceiling":null,"wage_components":["basic"]}' /></Field>
      {rules.length > 0 ? <ul className="text-sm text-muted-foreground">{rules.slice(0, 6).map((rule) => <li key={rule.id}>{rule.code} from {rule.effective_on} — {rule.confirmed ? 'confirmed' : 'draft'}</li>)}</ul> : null}
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      <Button type="submit" disabled={pending}>{pending ? 'Saving…' : 'Save rule'}</Button>
    </form>
  )
}

function CompensationForm({ employees, structures }: { employees: EmployeeRow[]; structures: Structure[] }) {
  const form = usePayrollSubmit()
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const body = new FormData(event.currentTarget)
    await form.send('/api/v1/payroll/compensations', {
      employee_id: Number(body.get('employee_id')),
      salary_structure_id: Number(body.get('salary_structure_id')),
      tax_regime: String(body.get('tax_regime') ?? 'new'),
      pan: String(body.get('pan') ?? '') || null,
    }, 'Compensation assigned. PAN is stored encrypted and shown masked.')
  }
  return (
    <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => void submit(event)}>
      <h2 className="sm:col-span-2 text-base font-semibold">Employee compensation</h2>
      <Field label="Employee" htmlFor="comp-employee">
        <Select id="comp-employee" name="employee_id" placeholder="Employee" options={employees.map((employee) => ({ value: String(employee.id), label: `${employee.employee_code} ${employee.full_name}` }))} />
      </Field>
      <Field label="Structure" htmlFor="comp-structure">
        <Select id="comp-structure" name="salary_structure_id" placeholder="Structure" options={structures.map((structure) => ({ value: String(structure.id), label: structure.name }))} />
      </Field>
      <Field label="Tax regime" htmlFor="comp-regime">
        <Select id="comp-regime" name="tax_regime" defaultValue="new" options={[{ value: 'new', label: 'New' }, { value: 'old', label: 'Old' }]} />
      </Field>
      <Field label="PAN" htmlFor="comp-pan"><Input id="comp-pan" name="pan" maxLength={10} /></Field>
      {form.error ? <p className="text-sm text-destructive sm:col-span-2">{form.error}</p> : null}
      <Button type="submit" disabled={form.pending}>{form.pending ? 'Saving…' : 'Assign compensation'}</Button>
    </form>
  )
}

function RunForm({ entities, runs }: { entities: Entity[]; runs: PayrollRun[] }) {
  const form = usePayrollSubmit()
  const [selected, setSelected] = useState<string>(runs[0] ? String(runs[0].id) : '')
  const run = runs.find((item) => String(item.id) === selected) ?? null
  async function open(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const body = new FormData(event.currentTarget)
    const [year, month] = String(body.get('period') ?? '').split('-')
    await form.send('/api/v1/payroll/runs', {
      legal_entity_id: Number(body.get('legal_entity_id')),
      year: Number(year),
      month: Number(month),
    }, 'Payroll month opened.')
  }
  return (
    <div className="grid gap-3">
      <h2 className="text-base font-semibold">Payroll month</h2>
      <form className="grid gap-3 sm:grid-cols-3" onSubmit={(event) => void open(event)}>
        <EntitySelect entities={entities} id="run-entity" />
        <Field label="Month" htmlFor="run-period"><Input id="run-period" name="period" type="month" required /></Field>
        <div className="flex items-end"><Button type="submit" disabled={form.pending}>Open run</Button></div>
      </form>
      <Field label="Open run" htmlFor="run-select">
        <Select id="run-select" value={selected} onChange={(event) => setSelected(event.target.value)} placeholder="Select a run" options={runs.map((item) => ({ value: String(item.id), label: `${item.year}-${item.month} ${item.status}` }))} />
      </Field>
      {run ? (
        <div className="flex flex-wrap gap-2">
          <Button type="button" disabled={form.pending} onClick={() => void form.send(`/api/v1/payroll/runs/${run.id}/preview`, null, 'Preview calculated.')}>Preview</Button>
          <Button type="button" disabled={form.pending} onClick={() => void form.send(`/api/v1/payroll/runs/${run.id}/submit`, null, 'Submitted for approval.')}>Submit</Button>
          <Button type="button" disabled={form.pending} onClick={() => void form.send(`/api/v1/payroll/runs/${run.id}/approve`, null, 'Approved.')}>Approve</Button>
          <Button type="button" disabled={form.pending} onClick={() => void form.send(`/api/v1/payroll/runs/${run.id}/lock`, null, 'Payroll locked.')}>Lock</Button>
          <Button type="button" variant="secondary" disabled={form.pending} onClick={() => void form.send(`/api/v1/payroll/runs/${run.id}/reverse`, { reason: 'Controlled reversal' }, 'Payroll reversed. Lines stay unchanged.')}>Reverse</Button>
        </div>
      ) : null}
      {run && run.warnings.length > 0 ? <ul className="text-sm text-muted-foreground">{run.warnings.map((warning) => <li key={warning}>{warning}</li>)}</ul> : null}
      {run && run.lines.length > 0 ? (
        <DataTable caption="Preview lines" rows={run.lines} getRowId={(row) => String(row.id)} columns={[
          { id: 'code', header: 'Employee', cell: (row) => `${row.employee_code ?? ''} ${row.name ?? ''}` },
          { id: 'lop', header: 'LOP days', cell: (row) => String(row.lop_days) },
          { id: 'gross', header: 'Gross', cell: (row) => row.gross },
          { id: 'deductions', header: 'Deductions', cell: (row) => row.deductions },
          { id: 'net', header: 'Net', cell: (row) => row.net },
        ]} />
      ) : null}
      {form.error ? <p className="text-sm text-destructive">{form.error}</p> : null}
    </div>
  )
}

function Form16Panel({ entities }: { entities: Entity[] }) {
  const [statement, setStatement] = useState<Form16 | null>(null)
  const [error, setError] = useState<string | null>(null)
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const body = new FormData(event.currentTarget)
    setError(null)
    try {
      setStatement(await apiData<Form16>(`/api/v1/payroll/form-16?legal_entity_id=${body.get('legal_entity_id')}&financial_year=${body.get('financial_year')}`))
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The statement could not be loaded.')
    }
  }
  return (
    <form className="grid gap-3" onSubmit={(event) => void submit(event)}>
      <h2 className="text-base font-semibold">Draft salary tax computation</h2>
      <p className="text-sm text-muted-foreground">OneFlo prepares the figures from locked payroll. The legal employer issues Form 16 through the authorized process.</p>
      <div className="grid gap-3 sm:grid-cols-3">
        <EntitySelect entities={entities} id="form16-entity" />
        <Field label="Financial year start" htmlFor="form16-year"><Input id="form16-year" name="financial_year" type="number" min="2000" required /></Field>
        <div className="flex items-end"><Button type="submit">Load statement</Button></div>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {statement ? (
        <div className="text-sm">
          <p className="font-medium">{statement.deductor.legal_name} · {statement.financial_year}</p>
          <p>{statement.disclaimer}</p>
          <p className="mt-2">{statement.form_12ba}</p>
        </div>
      ) : null}
    </form>
  )
}

function EntitySelect({ entities, id }: { entities: Entity[]; id: string }) {
  return (
    <Field label="Legal employer" htmlFor={id}>
      <Select id={id} name="legal_entity_id" options={entities.map((entity) => ({ value: String(entity.id), label: entity.legal_name }))} />
    </Field>
  )
}
