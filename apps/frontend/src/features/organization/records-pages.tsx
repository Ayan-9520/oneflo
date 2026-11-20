import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'

import { useSession } from '@/app/session'
import { Button } from '@/components/ui/button'
import { DataTable } from '@/components/ui/data-table'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { Field, Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/page-header'
import { Skeleton } from '@/components/ui/skeleton'
import { useToast } from '@/components/ui/toast'
import { ApiError, apiData } from '@/lib/api'

type NamedRecord = {
  id: number
  name: string
  slug?: string
  status?: string
  code?: string
  legal_name?: string
  employee_code_prefix?: string | null
  employee_code?: string
  given_name?: string
  family_name?: string
  permissions?: string[]
  scope?: string
}

function EmployeeCodeForm({ entities }: { entities: NamedRecord[] }) {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [error, setError] = useState<string | null>(null)
  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const body = new FormData(event.currentTarget)
    setError(null)
    try {
      await apiData(`/api/v1/legal-entities/${body.get('legal_entity_id')}/employee-code`, {
        method: 'PATCH',
        body: JSON.stringify({
          employee_code_prefix: String(body.get('employee_code_prefix') ?? ''),
          employee_code_padding: Number(body.get('employee_code_padding') ?? 5),
        }),
      })
      await queryClient.invalidateQueries({ queryKey: ['organization-records'] })
      notify('Employee ID format saved for this employer.', 'success')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The employee ID format could not be saved.')
    }
  }
  return (
    <form className="grid gap-3 border-t border-border pt-6 sm:grid-cols-3" onSubmit={save}>
      <h2 className="text-lg font-semibold sm:col-span-3">Employee IDs</h2>
      <p className="text-sm text-muted-foreground sm:col-span-3">New employees for this legal employer use this prefix. Existing codes stay as they are.</p>
      <Field label="Legal employer" htmlFor="code-entity"><select id="code-entity" name="legal_entity_id" className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm" required>{entities.map((entity) => <option key={entity.id} value={entity.id}>{entity.legal_name}</option>)}</select></Field>
      <Field label="Prefix" htmlFor="code-prefix"><Input id="code-prefix" name="employee_code_prefix" placeholder="RD" required minLength={2} maxLength={8} /></Field>
      <Field label="Digits" htmlFor="code-pad"><Input id="code-pad" name="employee_code_padding" type="number" min={3} max={8} defaultValue={5} required /></Field>
      {error ? <p className="text-sm text-destructive sm:col-span-3">{error}</p> : null}
      <div className="w-fit sm:col-span-3"><Button type="submit">Save employee ID format</Button></div>
    </form>
  )
}

function useOrganizationRecords(path: string, enabled: boolean) {
  const { organizationId } = useSession()

  return useQuery({
    queryKey: ['organization-records', path, organizationId],
    enabled: enabled && organizationId !== null,
    queryFn: () => apiData<NamedRecord[]>(path),
  })
}

export function BusinessesPage() {
  const { organizationId, permissions } = useSession()
  const brands = useOrganizationRecords('/api/v1/brands', organizationId !== null)
  const entities = useOrganizationRecords('/api/v1/legal-entities', organizationId !== null)
  const branches = useOrganizationRecords('/api/v1/branches', organizationId !== null)
  const departments = useOrganizationRecords('/api/v1/departments', organizationId !== null)
  const designations = useOrganizationRecords('/api/v1/designations', organizationId !== null)
  const canManage = permissions.has('organization.manage')

  return (
    <div className="mx-auto flex max-w-5xl flex-col gap-8">
      <PageHeader
        title="Businesses"
        description="Brands, legal entities, departments, designations, and branches for the active business."
        crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Businesses' }]}
      />
      {organizationId === null ? (
        <EmptyState title="Select a business" description="Choose a business from the switcher to load its structure." />
      ) : (
        <>
          <RecordSection title="Brands" query={brands} columns={['name', 'slug']} />
          <RecordSection title="Legal entities" query={entities} columns={['legal_name', 'employee_code_prefix']} />
          {canManage ? <EmployeeCodeForm entities={entities.data ?? []} /> : null}
          <StructureEditor title="Departments" path="/api/v1/departments" query={departments} columns={['name']} enabled={canManage} />
          <StructureEditor title="Designations" path="/api/v1/designations" query={designations} columns={['name']} enabled={canManage} />
          <RecordSection title="Branches" query={branches} columns={['name', 'code']} />
          {canManage ? <DomainPanel /> : null}
          <OrgChart />
        </>
      )}
    </div>
  )
}

export function AccessPage() {
  const { organizationId } = useSession()
  const roles = useQuery({
    queryKey: ['roles', organizationId],
    enabled: organizationId !== null,
    queryFn: () => apiData<{ roles: NamedRecord[]; permissions: { name: string; description: string }[] }>('/api/v1/roles'),
  })

  return (
    <div>
      <PageHeader
        title="Access"
        description="Roles and permissions available in the active business."
        crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Access' }]}
      />
      {organizationId === null ? (
        <EmptyState title="Select a business" description="Role assignments are scoped to a business." />
      ) : roles.isPending ? (
        <Skeleton className="h-40 w-full" />
      ) : roles.isError ? (
        <ErrorState title="Roles could not be loaded" description={roles.error instanceof ApiError ? roles.error.message : 'Try again.'} />
      ) : (
        <DataTable
          caption="Roles"
          rows={roles.data.roles}
          getRowId={(row) => row.slug ?? row.name}
          columns={[
            { id: 'name', header: 'Role', cell: (row) => row.name },
            { id: 'scope', header: 'Scope', cell: (row) => row.scope ?? '' },
            { id: 'permissions', header: 'Permissions', cell: (row) => (row.permissions ?? []).join(', ') },
          ]}
        />
      )}
    </div>
  )
}

type OrganizationDomain = {
  id: number
  host: string
  status: string
  verification_name: string
  verification_token: string
  verified_at: string | null
}

function DomainPanel() {
  const { organizationId } = useSession()
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)
  const domains = useQuery({
    queryKey: ['organization-domains', organizationId],
    enabled: organizationId !== null,
    queryFn: () => apiData<OrganizationDomain[]>('/api/v1/organization-domains'),
  })

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    setPending(true)
    try {
      await apiData('/api/v1/organization-domains', {
        method: 'POST',
        body: JSON.stringify({ host: String(new FormData(form).get('host') ?? '') }),
      })
      form.reset()
      notify('Domain saved. Add the TXT record, then verify it.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['organization-domains', organizationId] })
    } catch (error) {
      notify(error instanceof ApiError ? error.message : 'The domain could not be saved.', 'danger')
    } finally {
      setPending(false)
    }
  }

  async function verify(domain: OrganizationDomain) {
    setPending(true)
    try {
      await apiData(`/api/v1/organization-domains/${domain.id}/verify`, { method: 'POST', body: JSON.stringify({}) })
      notify(`${domain.host} is verified.`, 'success')
      await queryClient.invalidateQueries({ queryKey: ['organization-domains', organizationId] })
    } catch (error) {
      notify(error instanceof ApiError ? error.message : 'DNS verification failed.', 'danger')
    } finally {
      setPending(false)
    }
  }

  async function remove(domain: OrganizationDomain) {
    setPending(true)
    try {
      await apiData(`/api/v1/organization-domains/${domain.id}`, { method: 'DELETE' })
      notify(`${domain.host} was removed.`, 'success')
      await queryClient.invalidateQueries({ queryKey: ['organization-domains', organizationId] })
    } catch (error) {
      notify(error instanceof ApiError ? error.message : 'The domain could not be removed.', 'danger')
    } finally {
      setPending(false)
    }
  }

  return (
    <section className="flex flex-col gap-3 border-t border-border pt-6">
      <h2 className="text-lg font-semibold">Custom domains</h2>
      <p className="text-sm text-muted-foreground">
        A domain selects this business only after the TXT record is verified. The OneFlo login address stays app.oneflo.in.
      </p>
      {domains.isPending ? <Skeleton className="h-16 w-full" /> : null}
      {domains.isError ? <ErrorState title="Domains could not be loaded" description={domains.error instanceof ApiError ? domains.error.message : 'Try again.'} /> : null}
      {domains.data && domains.data.length === 0 ? <p className="text-sm text-muted-foreground">No custom domain yet. Add one after its DNS points at OneFlo.</p> : null}
      {domains.data && domains.data.length > 0 ? (
        <ul className="flex flex-col gap-3">
          {domains.data.map((domain) => (
            <li key={domain.id} className="rounded-md border border-border p-3 text-sm">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-medium">{domain.host}</span>
                <span>{domain.status}</span>
              </div>
              <p className="mt-2 break-all text-muted-foreground">
                TXT {domain.verification_name}={domain.verification_token}
              </p>
              <div className="mt-3 flex gap-2">
                {domain.status !== 'verified' ? (
                  <Button type="button" size="sm" disabled={pending} onClick={() => void verify(domain)}>
                    Verify DNS
                  </Button>
                ) : null}
                <Button type="button" size="sm" variant="outline" disabled={pending} onClick={() => void remove(domain)}>
                  Remove
                </Button>
              </div>
            </li>
          ))}
        </ul>
      ) : null}
      <form className="flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={(event) => void submit(event)}>
        <Field label="Domain" htmlFor="organization-domain">
          <Input id="organization-domain" name="host" placeholder="hr.example.com" required />
        </Field>
        <Button type="submit" disabled={pending}>
          Add domain
        </Button>
      </form>
    </section>
  )
}

function RecordSection({
  title,
  query,
  columns,
}: {
  title: string
  query: ReturnType<typeof useOrganizationRecords>
  columns: Array<keyof NamedRecord>
}) {
  if (query.isPending) {
    return <Skeleton className="h-32 w-full" />
  }

  if (query.isError) {
    return <ErrorState title={`${title} could not be loaded`} description={query.error instanceof ApiError ? query.error.message : 'Try again.'} />
  }

  if (!query.data || query.data.length === 0) {
    return (
      <section className="border-t border-border pt-6">
        <h2 className="text-lg font-semibold">{title}</h2>
        <p className="mt-1 text-sm text-muted-foreground">None saved for this business.</p>
      </section>
    )
  }

  return (
    <section className="border-t border-border pt-6">
      <h2 className="mb-3 text-lg font-semibold">{title}</h2>
      <DataTable
        caption={title}
        rows={query.data}
        getRowId={(row) => String(row.id)}
        columns={columns.map((column) => ({
          id: column,
          header: column.replaceAll('_', ' '),
          cell: (row: NamedRecord) => String(row[column] ?? ''),
        }))}
      />
    </section>
  )
}

function StructureEditor({
  title,
  path,
  query,
  columns,
  enabled,
}: {
  title: string
  path: string
  query: ReturnType<typeof useOrganizationRecords>
  columns: Array<keyof NamedRecord>
  enabled: boolean
}) {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    setPending(true)
    try {
      await apiData(path, { method: 'POST', body: JSON.stringify({ name: String(new FormData(form).get('name') ?? '') }) })
      form.reset()
      notify(`${title.slice(0, -1)} saved.`, 'success')
      await queryClient.invalidateQueries({ queryKey: ['organization-records', path] })
      await queryClient.invalidateQueries({ queryKey: ['departments'] })
      await queryClient.invalidateQueries({ queryKey: ['designations'] })
    } catch (error) {
      notify(error instanceof ApiError ? error.message : 'The record could not be saved.', 'danger')
    } finally {
      setPending(false)
    }
  }

  return (
    <section className="flex flex-col gap-3 border-t border-border pt-6">
      <h2 className="text-lg font-semibold">{title}</h2>
      {query.isPending ? <Skeleton className="h-16 w-full" /> : null}
      {query.isError ? <ErrorState title={`${title} could not be loaded`} description={query.error instanceof ApiError ? query.error.message : 'Try again.'} /> : null}
      {query.data && query.data.length === 0 ? <p className="text-sm text-muted-foreground">None saved yet. Add one below, then it appears in People.</p> : null}
      {query.data && query.data.length > 0 ? (
        <ul className="text-sm">{query.data.map((row) => <li key={row.id}>{columns.map((column) => String(row[column] ?? '')).filter(Boolean).join(' · ')}</li>)}</ul>
      ) : null}
      {enabled ? (
        <form className="flex flex-wrap items-end gap-2" onSubmit={(event) => void create(event)}>
          <Field label={`New ${title.slice(0, -1).toLowerCase()}`} htmlFor={`${path}-name`}>
            <Input id={`${path}-name`} name="name" required />
          </Field>
          <Button type="submit" disabled={pending}>
            {pending ? 'Saving…' : 'Add'}
          </Button>
        </form>
      ) : null}
    </section>
  )
}

type ChartNode = { id: number; full_name: string; manager_id: number | null; designation: string | null; department: string | null }

function OrgChart() {
  const { organizationId } = useSession()
  const chart = useQuery({
    queryKey: ['org-chart', organizationId],
    enabled: organizationId !== null,
    queryFn: () => apiData<ChartNode[]>('/api/v1/org-chart'),
  })
  if (chart.isPending) return <Skeleton className="h-24 w-full" />
  if (chart.isError) return <ErrorState title="Organization chart could not be loaded" description={chart.error instanceof ApiError ? chart.error.message : 'Try again.'} />
  if (chart.data.length === 0) return <p className="border-t border-border pt-6 text-sm text-muted-foreground">No reporting line yet. Set a manager on an employee in People.</p>
  const ids = new Set(chart.data.map((node) => node.id))
  const roots = chart.data.filter((node) => node.manager_id === null || !ids.has(node.manager_id))
  return (
    <section className="border-t border-border pt-6">
      <h2 className="mb-3 text-lg font-semibold">Organization chart</h2>
      <ul className="flex flex-col gap-2">
        {roots.map((node) => (
          <ChartBranch key={node.id} node={node} nodes={chart.data} />
        ))}
      </ul>
    </section>
  )
}

function ChartBranch({ node, nodes }: { node: ChartNode; nodes: ChartNode[] }) {
  const children = nodes.filter((item) => item.manager_id === node.id)
  return (
    <li>
      <p className="text-sm">
        <span className="font-medium">{node.full_name}</span>
        {node.designation ? <span className="text-muted-foreground"> · {node.designation}</span> : null}
      </p>
      {children.length > 0 ? (
        <ul className="mt-1 ml-4 border-l border-border pl-3">
          {children.map((child) => (
            <ChartBranch key={child.id} node={child} nodes={nodes} />
          ))}
        </ul>
      ) : null}
    </li>
  )
}
