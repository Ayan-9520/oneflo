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
import { ApiError, apiData } from '@/lib/api'

const featureOptions = ['attendance', 'leave', 'payroll', 'documents', 'recruitment', 'performance', 'assets', 'expenses', 'helpdesk', 'engagement', 'reports', 'analytics', 'ai', 'workflows', 'integrations']

type Registration = { id: number; status: string; organization_id: number; organization: string | null; applicant: string | null }
type Plan = { id: number; code: string; name: string; trial_days: number; price_rupees: string | null; currency: string; employee_limit: number | null; features: string[]; status: string }
type OrganizationRow = { id: number; name: string; status: string }
type Checklist = { key: string; label: string; complete: boolean }
type Usage = { employees: number; employee_limit: number | null; issued_documents: number; locked_payroll_runs: number; open_positions: number }
type Subscription = { status: string; trial_ends_on: string | null; current_period_end: string | null; plan: { name: string | null; features: string[]; price_rupees: string | null; currency: string | null } } | null

export function PlatformPage() {
  const { organizationId, permissions } = useSession()
  const manage = permissions.has('billing.manage')
  const view = permissions.has('billing.view')
  const reports = permissions.has('reports.view')
  const ai = permissions.has('ai.use')
  const integrations = permissions.has('integrations.manage')
  const workflows = permissions.has('workflows.manage')

  return (
    <div className="flex flex-col gap-8">
      <PageHeader title="Platform" description="Organization approval, plans, subscription, and tenant-scoped assistant tools." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Platform' }]} />
      <RegistrationForm />
      {manage ? <Administration /> : null}
      {view && organizationId !== null ? <TenantBilling /> : null}
      {reports && organizationId !== null ? <AttritionPanel /> : null}
      {integrations && organizationId !== null ? <IntegrationForm /> : null}
      {ai && organizationId !== null ? <AssistantPanel /> : null}
      {workflows && organizationId !== null ? <WorkflowForm /> : null}
    </div>
  )
}

function useAction() {
  const notify = useToast().notify
  const queryClient = useQueryClient()
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)
  async function send(path: string, method: string, body: Record<string, unknown> | null, success: string, keys: string[]) {
    setPending(true)
    setError(null)
    try {
      await apiData(path, { method, body: body === null ? undefined : JSON.stringify(body) })
      notify(success, 'success')
      await Promise.all(keys.map((key) => queryClient.invalidateQueries({ queryKey: [key] })))
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The request failed.')
    } finally {
      setPending(false)
    }
  }
  return { error, pending, send }
}

function RegistrationForm() {
  const form = useAction()
  return (
    <form className="grid grid-cols-1 gap-3 sm:grid-cols-2" onSubmit={(event: FormEvent<HTMLFormElement>) => {
      event.preventDefault()
      const body = new FormData(event.currentTarget)
      void form.send('/api/v1/organization-registrations', 'POST', { name: String(body.get('name')), slug: String(body.get('slug') ?? '') || null }, 'Registration submitted.', ['organization-registrations'])
    }}>
      <h2 className="sm:col-span-2 text-lg font-semibold">Register an organization</h2>
      {form.error ? <ErrorState title="The registration could not be submitted" description={form.error} /> : null}
      <Field label="Name" htmlFor="registration-name"><Input id="registration-name" name="name" required maxLength={120} /></Field>
      <Field label="Address" htmlFor="registration-slug"><Input id="registration-slug" name="slug" maxLength={120} /></Field>
      <Button type="submit" disabled={form.pending}>Submit registration</Button>
    </form>
  )
}

function Administration() {
  const form = useAction()
  const registrations = useQuery({ queryKey: ['organization-registrations'], queryFn: () => apiData<Registration[]>('/api/v1/organization-registrations') })
  const plans = useQuery({ queryKey: ['subscription-plans'], queryFn: () => apiData<Plan[]>('/api/v1/subscription-plans') })
  const organizations = useQuery({ queryKey: ['organizations'], queryFn: () => apiData<OrganizationRow[]>('/api/v1/organizations') })
  const [features, setFeatures] = useState<string[]>(['attendance'])
  return (
    <section className="flex flex-col gap-4">
      <h2 className="text-lg font-semibold">SaaS administration</h2>
      {form.error ? <ErrorState title="The platform action failed" description={form.error} /> : null}
      {registrations.isPending ? <Skeleton className="h-16 w-full" /> : registrations.isError ? <ErrorState title="Registrations could not be loaded" description={registrations.error instanceof ApiError ? registrations.error.message : 'Try again.'} /> : registrations.data.length === 0 ? <EmptyState title="No registrations" description="New organizations appear here before approval." /> : (
        <DataTable caption="Registrations" rows={registrations.data} getRowId={(row) => String(row.id)} columns={[
          { id: 'organization', header: 'Organization', cell: (row) => row.organization ?? '' },
          { id: 'status', header: 'Status', cell: (row) => row.status },
          { id: 'applicant', header: 'Applicant', cell: (row) => row.applicant ?? '' },
          { id: 'action', header: 'Review', cell: (row) => row.status === 'pending' ? (
            <form className="flex flex-col gap-2 sm:flex-row" onSubmit={(event: FormEvent<HTMLFormElement>) => {
              event.preventDefault()
              const body = new FormData(event.currentTarget)
              void form.send(`/api/v1/organization-registrations/${row.id}/decision`, 'POST', { approve: body.get('approve') === 'yes', plan_id: Number(body.get('plan_id')) || null }, 'Registration reviewed.', ['organization-registrations', 'organizations'])
            }}>
              <Select name="approve" options={[{ value: 'yes', label: 'Approve' }, { value: 'no', label: 'Reject' }]} />
              <Select name="plan_id" options={[{ value: '', label: 'Plan' }, ...(plans.data ?? []).map((plan) => ({ value: String(plan.id), label: plan.name }))]} />
              <Button type="submit" disabled={form.pending}>Review</Button>
            </form>
          ) : row.status },
        ]} />
      )}
      <form className="grid grid-cols-1 gap-3 sm:grid-cols-2" onSubmit={(event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()
        const body = new FormData(event.currentTarget)
        void form.send('/api/v1/subscription-plans', 'POST', {
          code: String(body.get('code')),
          name: String(body.get('name')),
          interval: String(body.get('interval')),
          trial_days: Number(body.get('trial_days')),
          price_rupees: String(body.get('price_rupees') ?? '') || null,
          currency: String(body.get('currency') || 'INR'),
          employee_limit: String(body.get('employee_limit') ?? '') === '' ? null : Number(body.get('employee_limit')),
          features,
          status: 'active',
        }, 'Plan saved.', ['subscription-plans'])
      }}>
        <h3 className="sm:col-span-2 text-base font-semibold">Plan</h3>
        <Field label="Code" htmlFor="plan-code"><Input id="plan-code" name="code" required maxLength={40} /></Field>
        <Field label="Name" htmlFor="plan-name"><Input id="plan-name" name="name" required /></Field>
        <Field label="Interval" htmlFor="plan-interval"><Select id="plan-interval" name="interval" options={[{ value: 'monthly', label: 'Monthly' }, { value: 'yearly', label: 'Yearly' }]} /></Field>
        <Field label="Trial days" htmlFor="plan-trial"><Input id="plan-trial" name="trial_days" type="number" min={0} required defaultValue={0} /></Field>
        <Field label="Price (rupees)" htmlFor="plan-price"><Input id="plan-price" name="price_rupees" inputMode="decimal" /></Field>
        <Field label="Currency" htmlFor="plan-currency"><Input id="plan-currency" name="currency" defaultValue="INR" required maxLength={3} /></Field>
        <Field label="Employee limit" htmlFor="plan-limit"><Input id="plan-limit" name="employee_limit" type="number" min={1} /></Field>
        <fieldset className="sm:col-span-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
          <legend className="text-sm font-medium">Features</legend>
          {featureOptions.map((feature) => (
            <label key={feature} className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={features.includes(feature)} onChange={(event) => setFeatures((current) => event.target.checked ? [...current, feature] : current.filter((item) => item !== feature))} />
              {feature}
            </label>
          ))}
        </fieldset>
        <Button type="submit" disabled={form.pending}>Save plan</Button>
      </form>
      <form className="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end" onSubmit={(event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()
        const body = new FormData(event.currentTarget)
        void form.send(`/api/v1/organizations/${String(body.get('organization_id'))}/activation`, 'PATCH', { status: String(body.get('status')) }, 'Organization status saved.', ['organizations'])
      }}>
        <Field label="Organization" htmlFor="activation-org"><Select id="activation-org" name="organization_id" options={[{ value: '', label: 'Select' }, ...(organizations.data ?? []).filter((row) => row.status === 'active' || row.status === 'inactive').map((row) => ({ value: String(row.id), label: `${row.name} · ${row.status}` }))]} /></Field>
        <Field label="Status" htmlFor="activation-status"><Select id="activation-status" name="status" options={[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }]} /></Field>
        <Button type="submit" variant="outline" disabled={form.pending}>Update status</Button>
      </form>
    </section>
  )
}

function TenantBilling() {
  const { organizationId } = useSession()
  const form = useAction()
  const subscription = useQuery({ queryKey: ['subscription', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Subscription>('/api/v1/subscription') })
  const usage = useQuery({ queryKey: ['usage', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Usage>('/api/v1/usage') })
  const onboarding = useQuery({ queryKey: ['tenant-onboarding', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Checklist[]>('/api/v1/tenant-onboarding') })
  const invoices = useQuery({ queryKey: ['billing-invoices', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; amount_rupees: string; currency: string; status: string; period_end: string | null }>>('/api/v1/billing-invoices') })
  return (
    <section className="flex flex-col gap-4">
      <h2 className="text-lg font-semibold">This business</h2>
      {form.error ? <ErrorState title="Billing could not be updated" description={form.error} /> : null}
      {subscription.isPending || usage.isPending || onboarding.isPending ? <Skeleton className="h-20 w-full" /> : subscription.isError ? <ErrorState title="Subscription could not be loaded" description={subscription.error instanceof ApiError ? subscription.error.message : 'Try again.'} /> : (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div className="rounded-lg border border-border bg-card p-4">
            <p className="text-sm text-muted-foreground">Subscription</p>
            <p className="mt-1 text-lg font-semibold">{subscription.data === null ? 'No plan assigned' : `${subscription.data.plan.name ?? 'Plan'} · ${subscription.data.status}`}</p>
            {subscription.data === null ? <p className="mt-2 text-sm">Features stay available until a plan is approved for this business.</p> : <p className="mt-2 text-sm">Renews through {subscription.data.current_period_end}. Trial ends {subscription.data.trial_ends_on ?? '—'}.</p>}
            {subscription.data !== null ? <Button className="mt-3" type="button" disabled={form.pending} onClick={() => void form.send('/api/v1/subscription/renew', 'POST', {}, 'Renewal recorded.', ['subscription', 'billing-invoices'])}>Renew</Button> : null}
          </div>
          <div className="rounded-lg border border-border bg-card p-4">
            <p className="text-sm text-muted-foreground">Usage</p>
            {usage.data ? <p className="mt-1 text-lg font-semibold">{usage.data.employees}{usage.data.employee_limit === null ? '' : ` / ${usage.data.employee_limit}`} employees</p> : null}
            {usage.data ? <p className="mt-2 text-sm">{usage.data.issued_documents} documents · {usage.data.locked_payroll_runs} locked payroll runs · {usage.data.open_positions} open positions</p> : null}
          </div>
        </div>
      )}
      {onboarding.data ? <DataTable caption="Tenant onboarding" rows={onboarding.data} getRowId={(row) => row.key} columns={[{ id: 'label', header: 'Step', cell: (row) => row.label }, { id: 'complete', header: 'State', cell: (row) => row.complete ? 'Complete' : 'Missing' }]} /> : null}
      {invoices.data && invoices.data.length > 0 ? <DataTable caption="Billing history" rows={invoices.data} getRowId={(row) => String(row.id)} columns={[{ id: 'amount', header: 'Amount', cell: (row) => `${row.amount_rupees} ${row.currency}` }, { id: 'status', header: 'Status', cell: (row) => row.status }, { id: 'end', header: 'Period end', cell: (row) => row.period_end ?? '' }]} /> : null}
    </section>
  )
}

function AttritionPanel() {
  const { organizationId } = useSession()
  const attrition = useQuery({ queryKey: ['attrition', organizationId], enabled: organizationId !== null, queryFn: () => apiData<{ exits: number; headcount: number; rate: string | null }>('/api/v1/attrition') })
  if (attrition.isPending) return <Skeleton className="h-16 w-full" />
  if (attrition.isError) return <ErrorState title="Attrition could not be loaded" description={attrition.error instanceof ApiError ? attrition.error.message : 'Try again.'} />
  return <section className="rounded-lg border border-border bg-card p-4"><h2 className="text-lg font-semibold">Attrition</h2><p className="mt-2 text-sm">{attrition.data.exits} exits in the last year · {attrition.data.headcount} active employees · rate {attrition.data.rate ?? 'not calculated'}</p></section>
}

function IntegrationForm() {
  const form = useAction()
  return (
    <form className="grid grid-cols-1 gap-3 sm:grid-cols-2" onSubmit={(event: FormEvent<HTMLFormElement>) => {
      event.preventDefault()
      const body = new FormData(event.currentTarget)
      void form.send('/api/v1/integration-providers', 'POST', { kind: String(body.get('kind')), endpoint: String(body.get('endpoint')), secret: String(body.get('secret')), enabled: true }, 'Integration saved.', ['integration-providers'])
    }}>
      <h2 className="sm:col-span-2 text-lg font-semibold">Integrations</h2>
      {form.error ? <ErrorState title="The integration could not be saved" description={form.error} /> : null}
      <Field label="Kind" htmlFor="integration-kind"><Select id="integration-kind" name="kind" options={[{ value: 'biometric', label: 'Biometric attendance' }, { value: 'payroll', label: 'Payroll' }, { value: 'accounting', label: 'Accounting' }]} /></Field>
      <Field label="HTTPS endpoint" htmlFor="integration-endpoint"><Input id="integration-endpoint" name="endpoint" type="url" required /></Field>
      <Field label="Secret" htmlFor="integration-secret"><Input id="integration-secret" name="secret" required /></Field>
      <Button type="submit" disabled={form.pending}>Save integration</Button>
    </form>
  )
}

function AssistantPanel() {
  const { permissions } = useSession()
  const form = useAction()
  const [matches, setMatches] = useState<Array<{ id: number; number: string; document_type: string }>>([])
  const [searchError, setSearchError] = useState<string | null>(null)
  const documents = permissions.has('documents.view')
  return (
    <section className="flex flex-col gap-3">
      <h2 className="text-lg font-semibold">Assistant</h2>
      {form.error ? <ErrorState title="The assistant request failed" description={form.error} /> : null}
      <form className="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto] sm:items-end" onSubmit={(event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()
        const question = String(new FormData(event.currentTarget).get('question'))
        void form.send('/api/v1/ai/ask', 'POST', { question }, 'Answer received.', [])
      }}>
        <Field label="Question" htmlFor="ai-question"><Input id="ai-question" name="question" required /></Field>
        <Button type="submit" disabled={form.pending}>Ask</Button>
      </form>
      {permissions.has('recruitment.view') ? <RecruitmentAssist /> : null}
      {documents ? (
        <form className="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto] sm:items-end" onSubmit={(event: FormEvent<HTMLFormElement>) => {
          event.preventDefault()
          const q = String(new FormData(event.currentTarget).get('q'))
          void (async () => {
            setSearchError(null)
            try {
              const result = await apiData<{ mode: string; matches: Array<{ id: number; number: string; document_type: string }> }>(`/api/v1/ai/documents?q=${encodeURIComponent(q)}`)
              setMatches(result.matches)
            } catch (caught) {
              setMatches([])
              setSearchError(caught instanceof ApiError ? caught.message : 'The search failed.')
            }
          })()
        }}>
          <Field label="Search stored documents" htmlFor="doc-search"><Input id="doc-search" name="q" required minLength={2} /></Field>
          <Button type="submit" variant="outline">Search</Button>
        </form>
      ) : null}
      {searchError ? <ErrorState title="Document search failed" description={searchError} /> : null}
      {matches.length > 0 ? <DataTable caption="Document matches" rows={matches} getRowId={(row) => String(row.id)} columns={[{ id: 'number', header: 'Number', cell: (row) => row.number }, { id: 'type', header: 'Type', cell: (row) => row.document_type }]} /> : null}
    </section>
  )
}

function RecruitmentAssist() {
  const { organizationId } = useSession()
  const openings = useQuery({
    queryKey: ['ai-recruitment', organizationId],
    enabled: organizationId !== null,
    queryFn: () => apiData<{ mode: string; openings: Array<{ id: number; title: string; status: string; applications: number }> }>('/api/v1/ai/recruitment'),
  })
  if (openings.isPending) return <Skeleton className="h-16 w-full" />
  if (openings.isError) return <ErrorState title="Recruitment assistance could not be loaded" description={openings.error instanceof ApiError ? openings.error.message : 'Try again.'} />
  return openings.data.openings.length === 0 ? <EmptyState title="No openings" description="Published openings in this business appear here with their application counts." /> : <DataTable caption="Recruitment assistance" rows={openings.data.openings} getRowId={(row) => String(row.id)} columns={[{ id: 'title', header: 'Opening', cell: (row) => row.title }, { id: 'status', header: 'Status', cell: (row) => row.status }, { id: 'applications', header: 'Applications', cell: (row) => String(row.applications) }]} />
}

function WorkflowForm() {
  const form = useAction()
  return (
    <form className="grid grid-cols-1 gap-3 sm:grid-cols-2" onSubmit={(event: FormEvent<HTMLFormElement>) => {
      event.preventDefault()
      const body = new FormData(event.currentTarget)
      void form.send('/api/v1/workflows', 'POST', { name: String(body.get('name')), event: String(body.get('event')), steps: [{ permission: String(body.get('permission')) }], enabled: true }, 'Workflow saved.', ['workflows'])
    }}>
      <h2 className="sm:col-span-2 text-lg font-semibold">Workflow</h2>
      {form.error ? <ErrorState title="The workflow could not be saved" description={form.error} /> : null}
      <Field label="Name" htmlFor="workflow-name"><Input id="workflow-name" name="name" required /></Field>
      <Field label="Event" htmlFor="workflow-event"><Select id="workflow-event" name="event" options={[{ value: 'leave.applied', label: 'Leave applied' }, { value: 'expense.submitted', label: 'Expense submitted' }]} /></Field>
      <Field label="Notify permission" htmlFor="workflow-permission"><Input id="workflow-permission" name="permission" required defaultValue="leave.approve" /></Field>
      <Button type="submit" disabled={form.pending}>Save workflow</Button>
    </form>
  )
}
