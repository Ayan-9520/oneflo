import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

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
import { ApiError, apiData, apiFetch } from '@/lib/api'

type Entity = { id: number; legal_name: string }
type Requisition = { id: number; title: string; status: string; headcount: number }
type Opening = { id: number; title: string; status: string }
type Candidate = { id: number; full_name: string; email: string | null; status: string }
type Application = { id: number; candidate_name: string | null; stage: string; job_opening_id: number }

export function RecruitmentPage() {
  const { organizationId, permissions } = useSession()
  const canManage = permissions.has('recruitment.manage')
  const entities = useQuery({ queryKey: ['legal-entities', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Entity[]>('/api/v1/legal-entities') })
  const requisitions = useQuery({ queryKey: ['requisitions', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Requisition[]>('/api/v1/recruitment/requisitions') })
  const openings = useQuery({ queryKey: ['openings', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Opening[]>('/api/v1/recruitment/openings') })
  const candidates = useQuery({ queryKey: ['candidates', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Candidate[]>('/api/v1/recruitment/candidates') })
  const applications = useQuery({ queryKey: ['applications', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Application[]>('/api/v1/recruitment/applications') })

  if (organizationId === null) return <EmptyState title="Select a business" description="Recruitment belongs to the active business." />

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Recruitment" description="Requisitions, openings, candidates, interviews, and offers for the legal employer." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Recruitment' }]} />
      {requisitions.isPending ? <Skeleton className="h-20 w-full" /> : requisitions.isError ? <ErrorState title="Recruitment could not be loaded" description={requisitions.error instanceof ApiError ? requisitions.error.message : 'Try again.'} /> : requisitions.data.length === 0 ? <EmptyState title="No requisitions" description="Create a job requisition for a legal employer." /> : (
        <DataTable caption="Requisitions" rows={requisitions.data} getRowId={(row) => String(row.id)} columns={[
          { id: 'title', header: 'Title', cell: (row) => row.title },
          { id: 'headcount', header: 'Headcount', cell: (row) => String(row.headcount) },
          { id: 'status', header: 'Status', cell: (row) => row.status },
        ]} />
      )}
      {applications.data && applications.data.length > 0 ? (
        <DataTable caption="Applications" rows={applications.data} getRowId={(row) => String(row.id)} columns={[
          { id: 'candidate', header: 'Candidate', cell: (row) => row.candidate_name ?? '' },
          { id: 'stage', header: 'Stage', cell: (row) => row.stage },
        ]} />
      ) : null}
      {canManage ? <RecruitmentForms entities={entities.data ?? []} requisitions={requisitions.data ?? []} openings={openings.data ?? []} candidates={candidates.data ?? []} applications={applications.data ?? []} /> : null}
    </div>
  )
}

function useRecruitmentSubmit() {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  async function send(path: string, body: Record<string, unknown> | FormData | null, success: string) {
    setPending(true)
    setError(null)
    try {
      if (body instanceof FormData) {
        const response = await apiFetch(path, { method: 'POST', body })
        if (!response.ok) throw await response.json().then((payload) => new ApiError(payload.message ?? 'The request failed.', response.status))
      } else {
        await apiData(path, body === null ? { method: 'POST' } : { method: 'POST', body: JSON.stringify(body) })
      }
      await queryClient.invalidateQueries({ queryKey: ['requisitions'] })
      await queryClient.invalidateQueries({ queryKey: ['openings'] })
      await queryClient.invalidateQueries({ queryKey: ['candidates'] })
      await queryClient.invalidateQueries({ queryKey: ['applications'] })
      notify(success, 'success')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The recruitment request failed.')
    } finally {
      setPending(false)
    }
  }
  return { pending, error, send }
}

function RecruitmentForms({ entities, requisitions, openings, candidates, applications }: { entities: Entity[]; requisitions: Requisition[]; openings: Opening[]; candidates: Candidate[]; applications: Application[] }) {
  const form = useRecruitmentSubmit()
  return (
    <div className="grid gap-6">
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const body = new FormData(event.currentTarget)
        void form.send('/api/v1/recruitment/requisitions', {
          legal_entity_id: Number(body.get('legal_entity_id')),
          title: String(body.get('title') ?? ''),
          description: String(body.get('description') ?? ''),
          headcount: Number(body.get('headcount') ?? 1),
        }, 'Requisition saved.')
      }}>
        <h2 className="sm:col-span-2 text-base font-semibold">Job requisition</h2>
        <Field label="Legal employer" htmlFor="req-entity"><Select id="req-entity" name="legal_entity_id" options={entities.map((entity) => ({ value: String(entity.id), label: entity.legal_name }))} /></Field>
        <Field label="Title" htmlFor="req-title"><Input id="req-title" name="title" required /></Field>
        <Field label="Headcount" htmlFor="req-headcount"><Input id="req-headcount" name="headcount" type="number" min="1" defaultValue="1" required /></Field>
        <Field label="Description" htmlFor="req-description"><Textarea id="req-description" name="description" /></Field>
        {form.error ? <p className="text-sm text-destructive sm:col-span-2">{form.error}</p> : null}
        <Button type="submit" disabled={form.pending}>Save requisition</Button>
      </form>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const id = new FormData(event.currentTarget).get('requisition_id')
        void form.send(`/api/v1/recruitment/requisitions/${id}/approve`, null, 'Requisition approved.')
      }}>
        <h2 className="sm:col-span-2 text-base font-semibold">Approve and open</h2>
        <Field label="Requisition" htmlFor="approve-req"><Select id="approve-req" name="requisition_id" options={requisitions.map((row) => ({ value: String(row.id), label: `${row.title} (${row.status})` }))} /></Field>
        <div className="flex items-end gap-2">
          <Button type="submit" disabled={form.pending}>Approve</Button>
        </div>
      </form>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const id = new FormData(event.currentTarget).get('requisition_id')
        void form.send(`/api/v1/recruitment/requisitions/${id}/openings`, null, 'Opening published.')
      }}>
        <Field label="Approved requisition" htmlFor="open-req"><Select id="open-req" name="requisition_id" options={requisitions.filter((row) => row.status === 'approved').map((row) => ({ value: String(row.id), label: row.title }))} /></Field>
        <div className="flex items-end"><Button type="submit" disabled={form.pending}>Publish opening</Button></div>
      </form>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const body = new FormData(event.currentTarget)
        void form.send('/api/v1/recruitment/candidates', {
          given_name: String(body.get('given_name') ?? ''),
          family_name: String(body.get('family_name') ?? ''),
          email: String(body.get('email') ?? '') || null,
          source: String(body.get('source') ?? '') || null,
        }, 'Candidate saved.')
      }}>
        <h2 className="sm:col-span-2 text-base font-semibold">Candidate</h2>
        <Field label="Given name" htmlFor="cand-given"><Input id="cand-given" name="given_name" required /></Field>
        <Field label="Family name" htmlFor="cand-family"><Input id="cand-family" name="family_name" required /></Field>
        <Field label="Email" htmlFor="cand-email"><Input id="cand-email" name="email" type="email" /></Field>
        <Field label="Source" htmlFor="cand-source"><Input id="cand-source" name="source" /></Field>
        <Button type="submit" disabled={form.pending}>Save candidate</Button>
      </form>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const body = new FormData(event.currentTarget)
        const candidate = body.get('candidate_id')
        body.delete('candidate_id')
        void form.send(`/api/v1/recruitment/candidates/${candidate}/resume`, body, 'Resume stored privately.')
      }}>
        <Field label="Candidate" htmlFor="resume-candidate"><Select id="resume-candidate" name="candidate_id" options={candidates.map((row) => ({ value: String(row.id), label: row.full_name }))} /></Field>
        <Field label="Resume" htmlFor="resume-file"><Input id="resume-file" name="resume" type="file" accept=".pdf,.jpg,.jpeg,.png" required /></Field>
        <Button type="submit" disabled={form.pending}>Upload resume</Button>
      </form>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const body = new FormData(event.currentTarget)
        void form.send('/api/v1/recruitment/applications', {
          job_opening_id: Number(body.get('job_opening_id')),
          candidate_id: Number(body.get('candidate_id')),
        }, 'Application added to the pipeline.')
      }}>
        <h2 className="sm:col-span-2 text-base font-semibold">Application</h2>
        <Field label="Opening" htmlFor="app-opening"><Select id="app-opening" name="job_opening_id" options={openings.map((row) => ({ value: String(row.id), label: row.title }))} /></Field>
        <Field label="Candidate" htmlFor="app-candidate"><Select id="app-candidate" name="candidate_id" options={candidates.map((row) => ({ value: String(row.id), label: row.full_name }))} /></Field>
        <Button type="submit" disabled={form.pending}>Add application</Button>
      </form>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const body = new FormData(event.currentTarget)
        void form.send(`/api/v1/recruitment/applications/${body.get('application_id')}/interviews`, {
          scheduled_at: String(body.get('scheduled_at') ?? ''),
          interviewer: String(body.get('interviewer') ?? ''),
          mode: String(body.get('mode') ?? 'video'),
        }, 'Interview scheduled.')
      }}>
        <h2 className="sm:col-span-2 text-base font-semibold">Interview</h2>
        <Field label="Application" htmlFor="int-app"><Select id="int-app" name="application_id" options={applications.map((row) => ({ value: String(row.id), label: row.candidate_name ?? String(row.id) }))} /></Field>
        <Field label="When" htmlFor="int-when"><Input id="int-when" name="scheduled_at" type="datetime-local" required /></Field>
        <Field label="Interviewer" htmlFor="int-name"><Input id="int-name" name="interviewer" required /></Field>
        <Field label="Mode" htmlFor="int-mode"><Select id="int-mode" name="mode" defaultValue="video" options={[{ value: 'video', label: 'Video' }, { value: 'phone', label: 'Phone' }, { value: 'in_person', label: 'In person' }]} /></Field>
        <Button type="submit" disabled={form.pending}>Schedule</Button>
      </form>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const body = new FormData(event.currentTarget)
        void form.send('/api/v1/recruitment/offers', {
          job_application_id: Number(body.get('job_application_id')),
          legal_entity_id: Number(body.get('legal_entity_id')),
          designation: String(body.get('designation') ?? ''),
          compensation_note: String(body.get('compensation_note') ?? ''),
        }, 'Draft offer saved. Submit it for someone else to approve.')
      }}>
        <h2 className="sm:col-span-2 text-base font-semibold">Offer</h2>
        <Field label="Application" htmlFor="offer-app"><Select id="offer-app" name="job_application_id" options={applications.map((row) => ({ value: String(row.id), label: row.candidate_name ?? String(row.id) }))} /></Field>
        <Field label="Legal employer" htmlFor="offer-entity"><Select id="offer-entity" name="legal_entity_id" options={entities.map((entity) => ({ value: String(entity.id), label: entity.legal_name }))} /></Field>
        <Field label="Designation" htmlFor="offer-role"><Input id="offer-role" name="designation" required /></Field>
        <Field label="Compensation note" htmlFor="offer-pay"><Input id="offer-pay" name="compensation_note" required /></Field>
        <Button type="submit" disabled={form.pending}>Save draft offer</Button>
      </form>
    </div>
  )
}
