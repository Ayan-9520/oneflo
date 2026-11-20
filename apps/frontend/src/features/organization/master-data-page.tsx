import { useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'

import { useSession } from '@/app/session'
import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { Field, Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/page-header'
import { Select } from '@/components/ui/select'
import { ApiError, apiData } from '@/lib/api'

type Department = { id: number; name: string; status: string; employees_count: number; code: string | null }
type Designation = { id: number; name: string; status: string; code: string | null; department_id: number | null }
type Template = { id: number; name: string; category: string | null }

export function MasterDataPage() {
  const { organizationId } = useSession()
  const [tab, setTab] = useState<'departments' | 'designations'>('departments')
  if (organizationId === null) return <EmptyState title="Select a business" description="Master data belongs to the active business." />

  return (
    <div className="mx-auto flex max-w-5xl flex-col gap-8">
      <PageHeader
        title="Master data"
        description="Templates are suggestions. Activate the ones this business uses, or add a custom name. Archived rows stay on employee history."
        crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Master data' }]}
      />
      <div className="flex gap-2">
        <Button type="button" variant={tab === 'departments' ? 'default' : 'outline'} onClick={() => setTab('departments')}>Departments</Button>
        <Button type="button" variant={tab === 'designations' ? 'default' : 'outline'} onClick={() => setTab('designations')}>Designations</Button>
      </div>
      {tab === 'departments' ? <DepartmentMaster /> : <DesignationMaster />}
    </div>
  )
}

function DepartmentMaster() {
  const { organizationId } = useSession()
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('')
  const [name, setName] = useState('')
  const [error, setError] = useState<string | null>(null)
  const saved = useQuery({ queryKey: ['departments', organizationId, 'all'], queryFn: () => apiData<Department[]>('/api/v1/departments?include=all') })
  const templates = useQuery({ queryKey: ['department-templates'], queryFn: () => apiData<Template[]>('/api/v1/department-templates') })
  const categories = useMemo(() => [...new Set((templates.data ?? []).map((row) => row.category).filter(Boolean))] as string[], [templates.data])
  const savedNames = new Set((saved.data ?? []).map((row) => row.name))
  const visible = (templates.data ?? []).filter((row) => {
    const matchesSearch = row.name.toLowerCase().includes(search.trim().toLowerCase())
    const matchesCategory = category === '' || row.category === category
    return matchesSearch && matchesCategory && !savedNames.has(row.name)
  })

  async function activate(id: number) {
    setError(null)
    try {
      await apiData('/api/v1/departments/adopt', { method: 'POST', body: JSON.stringify({ template_ids: [id] }) })
      await queryClient.invalidateQueries({ queryKey: ['departments'] })
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The department could not be activated.')
    }
  }

  async function addCustom() {
    setError(null)
    try {
      await apiData('/api/v1/departments', { method: 'POST', body: JSON.stringify({ name }) })
      setName('')
      await queryClient.invalidateQueries({ queryKey: ['departments'] })
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The department could not be saved.')
    }
  }

  async function archive(id: number) {
    await apiData(`/api/v1/departments/${id}`, { method: 'DELETE' })
    await queryClient.invalidateQueries({ queryKey: ['departments'] })
  }

  return (
    <div className="flex flex-col gap-8">
      <section className="flex flex-col gap-3 border-t border-border pt-6">
        <h2 className="text-lg font-semibold">Active in this business</h2>
        {saved.isError ? <ErrorState title="Departments could not be loaded" description={saved.error instanceof ApiError ? saved.error.message : 'Try again.'} /> : null}
        {(saved.data ?? []).length === 0 ? <p className="text-sm text-muted-foreground">No department is activated yet.</p> : (
          <ul className="flex flex-col gap-2 text-sm">
            {(saved.data ?? []).map((row) => (
              <li key={row.id} className="flex flex-wrap items-center justify-between gap-2">
                <span>{row.name} · {row.employees_count} people · {row.status}</span>
                {row.status === 'active' ? <Button type="button" variant="outline" onClick={() => void archive(row.id)}>Archive</Button> : null}
              </li>
            ))}
          </ul>
        )}
        <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
          <Field label="Custom department" htmlFor="custom-department"><Input id="custom-department" value={name} onChange={(event) => setName(event.target.value)} /></Field>
          <Button type="button" disabled={name.trim() === ''} onClick={() => void addCustom()}>Add department</Button>
        </div>
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
      </section>
      <section className="flex flex-col gap-3 border-t border-border pt-6">
        <h2 className="text-lg font-semibold">Suggested templates</h2>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Search" htmlFor="department-search"><Input id="department-search" value={search} onChange={(event) => setSearch(event.target.value)} /></Field>
          <Field label="Category" htmlFor="department-category">
            <Select id="department-category" value={category} placeholder="All categories" options={categories.map((item) => ({ value: item, label: item }))} onChange={(event) => setCategory(event.target.value)} />
          </Field>
        </div>
        {visible.length === 0 ? <p className="text-sm text-muted-foreground">No template matches that search.</p> : (
          <ul className="flex flex-col gap-2 text-sm">
            {visible.map((row) => (
              <li key={row.id} className="flex flex-wrap items-center justify-between gap-2">
                <span>{row.name}{row.category ? ` · ${row.category}` : ''}</span>
                <Button type="button" variant="outline" onClick={() => void activate(row.id)}>Activate</Button>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}

function DesignationMaster() {
  const { organizationId } = useSession()
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('')
  const [name, setName] = useState('')
  const [error, setError] = useState<string | null>(null)
  const saved = useQuery({ queryKey: ['designations', organizationId, 'all'], queryFn: () => apiData<Designation[]>('/api/v1/designations?include=all') })
  const templates = useQuery({ queryKey: ['designation-templates'], queryFn: () => apiData<Template[]>('/api/v1/designation-templates') })
  const categories = useMemo(() => [...new Set((templates.data ?? []).map((row) => row.category).filter(Boolean))] as string[], [templates.data])
  const savedNames = new Set((saved.data ?? []).map((row) => row.name))
  const visible = (templates.data ?? []).filter((row) => {
    const matchesSearch = row.name.toLowerCase().includes(search.trim().toLowerCase())
    return matchesSearch && (category === '' || row.category === category) && !savedNames.has(row.name)
  })

  async function activate(id: number) {
    setError(null)
    try {
      await apiData('/api/v1/designations/adopt', { method: 'POST', body: JSON.stringify({ template_ids: [id] }) })
      await queryClient.invalidateQueries({ queryKey: ['designations'] })
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The designation could not be activated.')
    }
  }

  async function addCustom() {
    setError(null)
    try {
      await apiData('/api/v1/designations', { method: 'POST', body: JSON.stringify({ name }) })
      setName('')
      await queryClient.invalidateQueries({ queryKey: ['designations'] })
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The designation could not be saved.')
    }
  }

  async function archive(id: number) {
    await apiData(`/api/v1/designations/${id}`, { method: 'DELETE' })
    await queryClient.invalidateQueries({ queryKey: ['designations'] })
  }

  return (
    <div className="flex flex-col gap-8">
      <section className="flex flex-col gap-3 border-t border-border pt-6">
        <h2 className="text-lg font-semibold">Active in this business</h2>
        {(saved.data ?? []).length === 0 ? <p className="text-sm text-muted-foreground">No designation is activated yet.</p> : (
          <ul className="flex flex-col gap-2 text-sm">
            {(saved.data ?? []).map((row) => (
              <li key={row.id} className="flex flex-wrap items-center justify-between gap-2">
                <span>{row.name} · {row.status}</span>
                {row.status === 'active' ? <Button type="button" variant="outline" onClick={() => void archive(row.id)}>Archive</Button> : null}
              </li>
            ))}
          </ul>
        )}
        <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
          <Field label="Custom designation" htmlFor="custom-designation"><Input id="custom-designation" value={name} onChange={(event) => setName(event.target.value)} /></Field>
          <Button type="button" disabled={name.trim() === ''} onClick={() => void addCustom()}>Add designation</Button>
        </div>
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
      </section>
      <section className="flex flex-col gap-3 border-t border-border pt-6">
        <h2 className="text-lg font-semibold">Suggested templates</h2>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Search" htmlFor="designation-search"><Input id="designation-search" value={search} onChange={(event) => setSearch(event.target.value)} /></Field>
          <Field label="Category" htmlFor="designation-category">
            <Select id="designation-category" value={category} placeholder="All categories" options={categories.map((item) => ({ value: item, label: item }))} onChange={(event) => setCategory(event.target.value)} />
          </Field>
        </div>
        <ul className="flex max-h-[32rem] flex-col gap-2 overflow-y-auto text-sm">
          {visible.map((row) => (
            <li key={row.id} className="flex flex-wrap items-center justify-between gap-2">
              <span>{row.name}{row.category ? ` · ${row.category}` : ''}</span>
              <Button type="button" variant="outline" onClick={() => void activate(row.id)}>Activate</Button>
            </li>
          ))}
        </ul>
      </section>
    </div>
  )
}
