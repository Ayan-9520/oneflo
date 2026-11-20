import { useQuery, useQueryClient } from '@tanstack/react-query'
import { FileSpreadsheet, FlaskConical } from 'lucide-react'
import { Link } from 'react-router'
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
import { CandidateImportPanel } from '@/features/recruitment/candidate-import'
import { PipelineBoard, type PipelineApplication } from '@/features/recruitment/pipeline-board'
import { ApiError, apiData, apiFetch } from '@/lib/api'

type Entity = { id: number; legal_name: string }
type Requisition = { id: number; title: string; status: string; headcount: number }
type Opening = { id: number; title: string; status: string; slug: string | null; is_public: boolean }

const careerCategories = ['Sales & Telecalling', 'Management', 'Insurance', 'Loans & Finance', 'Automotive', 'Digital Marketing', 'Marketing', 'Operations', 'Customer Support', 'Travel & Tourism', 'Technology & IT', 'Human Resources', 'Business Development', 'Other']
type Candidate = { id: number; full_name: string; email: string | null; phone?: string | null; source?: string | null; status: string; is_test_data?: boolean }
type Application = PipelineApplication

export function RecruitmentPage() {
  const { organizationId, organization, permissions } = useSession()
  const [importing, setImporting] = useState(false)
  const canManage = permissions.has('recruitment.manage')
  const canApproveRequisition = permissions.has('recruitment.requisitions.approve')
  const canApproveOffer = permissions.has('recruitment.offers.approve')
  const entities = useQuery({ queryKey: ['legal-entities', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Entity[]>('/api/v1/legal-entities') })
  const requisitions = useQuery({ queryKey: ['requisitions', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Requisition[]>('/api/v1/recruitment/requisitions') })
  const openings = useQuery({ queryKey: ['openings', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Opening[]>('/api/v1/recruitment/openings') })
  const candidates = useQuery({ queryKey: ['candidates', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Candidate[]>('/api/v1/recruitment/candidates') })
  const applications = useQuery({ queryKey: ['applications', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Application[]>('/api/v1/recruitment/applications') })
  const offers = useQuery({ queryKey: ['offers', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; designation: string; status: string }>>('/api/v1/recruitment/offers') })
  const departments = useQuery({ queryKey: ['departments', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; name: string }>>('/api/v1/departments') })
  const designations = useQuery({ queryKey: ['designations', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; name: string }>>('/api/v1/designations') })

  if (organizationId === null) return <EmptyState title="Select a business" description="Recruitment belongs to the active business." />

  return (
    <div className="mx-auto flex max-w-5xl flex-col gap-8">
      <PageHeader title="Recruitment" description="Save a job, approve it, publish the opening, then add the candidate." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Recruitment' }]}
        actions={canManage ? <Button onClick={() => setImporting(true)} disabled={importing}><FileSpreadsheet className="size-4" /> Import Candidates</Button> : null} />
      {importing && canManage ? <CandidateImportPanel businessName={organization?.name ?? 'this business'} onClose={() => setImporting(false)} /> : null}
      <section className="flex flex-col gap-3">
        <div className="flex items-center justify-between gap-3">
          <h2 className="text-lg font-semibold">Candidates</h2>
          {(candidates.data ?? []).some((row) => row.is_test_data) ? <span className="text-xs text-muted-foreground">{(candidates.data ?? []).filter((row) => row.is_test_data).length} marked as test data</span> : null}
        </div>
        {candidates.isPending ? <Skeleton className="h-16 w-full" /> : (candidates.data ?? []).length === 0 ? <p className="text-sm text-muted-foreground">No candidates yet. Add one below or use Import Candidates.</p> : (
          <DataTable caption="Candidates" rows={(candidates.data ?? []).slice(0, 50)} getRowId={(row) => String(row.id)} columns={[
            { id: 'name', header: 'Name', cell: (row) => <span className="inline-flex items-center gap-2">{row.full_name}{row.is_test_data ? <span className="inline-flex items-center gap-1 rounded-full bg-[#f3efff] px-2 py-0.5 text-[10px] font-semibold text-[#6d38d6]"><FlaskConical className="size-3" />Test data</span> : null}</span> },
            { id: 'contact', header: 'Contact', cell: (row) => row.email ?? row.phone ?? '' },
            { id: 'source', header: 'Source', cell: (row) => row.source ?? '' },
            { id: 'status', header: 'Status', cell: (row) => row.status },
          ]} />
        )}
      </section>
      <section className="flex flex-col gap-3">
        <h2 className="text-lg font-semibold">Open jobs</h2>
        {requisitions.isPending ? <Skeleton className="h-20 w-full" /> : requisitions.isError ? <ErrorState title="Recruitment could not be loaded" description={requisitions.error instanceof ApiError ? requisitions.error.message : 'Try again.'} /> : requisitions.data.length === 0 ? <p className="text-sm text-muted-foreground">No job yet. Save a requisition below.</p> : (
          <DataTable caption="Requisitions" rows={requisitions.data} getRowId={(row) => String(row.id)} columns={[
            { id: 'title', header: 'Title', cell: (row) => row.title },
            { id: 'headcount', header: 'Headcount', cell: (row) => String(row.headcount) },
            { id: 'status', header: 'Status', cell: (row) => row.status },
          ]} />
        )}
      </section>
      {applications.isPending ? <Skeleton className="h-40 w-full" /> : (
        <PipelineBoard applications={applications.data ?? []} entities={entities.data ?? []} canManage={canManage} canApproveOffer={canApproveOffer} canConfirmJoining={permissions.has('recruitment.joining.confirm')} />
      )}
      <section className="border-t border-border pt-6">
        <h2 className="mb-3 text-lg font-semibold">Offers</h2>
        {offers.isPending ? <Skeleton className="h-16 w-full" /> : offers.isError ? <p className="text-sm text-muted-foreground">Offers could not be loaded.</p> : (offers.data ?? []).length === 0 ? <p className="text-sm text-muted-foreground">No offer yet.</p> : (
          <DataTable caption="Offers" rows={offers.data ?? []} getRowId={(row) => String(row.id)} columns={[
            { id: 'role', header: 'Designation', cell: (row) => row.designation },
            { id: 'status', header: 'Status', cell: (row) => row.status },
          ]} />
        )}
      </section>
      {canManage ? <RecruitmentForms entities={entities.data ?? []} requisitions={requisitions.data ?? []} openings={openings.data ?? []} candidates={candidates.data ?? []} applications={applications.data ?? []} offers={offers.data ?? []} departments={departments.data ?? []} designations={designations.data ?? []} canApproveRequisition={canApproveRequisition} canApproveOffer={canApproveOffer} /> : null}
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
      await queryClient.invalidateQueries({ queryKey: ['offers'] })
      notify(success, 'success')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The recruitment request failed.')
    } finally {
      setPending(false)
    }
  }
  return { pending, error, send }
}

function RecruitmentForms({ entities, requisitions, openings, candidates, applications, offers, departments, designations, canApproveRequisition, canApproveOffer }: { entities: Entity[]; requisitions: Requisition[]; openings: Opening[]; candidates: Candidate[]; applications: Application[]; offers: Array<{ id: number; designation: string; status: string }>; departments: Array<{ id: number; name: string }>; designations: Array<{ id: number; name: string }>; canApproveRequisition: boolean; canApproveOffer: boolean }) {
  const form = useRecruitmentSubmit()
  return (
    <div className="grid gap-8">
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const body = new FormData(event.currentTarget)
        void form.send('/api/v1/recruitment/requisitions', {
          legal_entity_id: Number(body.get('legal_entity_id')),
          title: String(body.get('title') ?? ''),
          description: String(body.get('description') ?? ''),
          headcount: Number(body.get('headcount') ?? 1),
          department_id: body.get('department_id') ? Number(body.get('department_id')) : null,
          designation_id: body.get('designation_id') ? Number(body.get('designation_id')) : null,
        }, 'Requisition saved.')
      }}>
        <h2 className="text-lg font-semibold sm:col-span-2">Job requisition</h2>
        <Field label="Legal employer" htmlFor="req-entity"><Select id="req-entity" name="legal_entity_id" options={entities.map((entity) => ({ value: String(entity.id), label: entity.legal_name }))} /></Field>
        <Field label="Title" htmlFor="req-title"><Input id="req-title" name="title" required /></Field>
        <Field label="Headcount" htmlFor="req-headcount"><Input id="req-headcount" name="headcount" type="number" min="1" defaultValue="1" required /></Field>
        <Field label="Department" htmlFor="req-department"><Select id="req-department" name="department_id" placeholder={departments.length ? 'Optional' : 'None activated yet'} options={departments.map((row) => ({ value: String(row.id), label: row.name }))} /></Field>
        <Field label="Designation" htmlFor="req-designation"><Select id="req-designation" name="designation_id" placeholder={designations.length ? 'Optional' : 'None activated yet'} options={designations.map((row) => ({ value: String(row.id), label: row.name }))} /></Field>
        <Field label="Description" htmlFor="req-description"><Textarea id="req-description" name="description" /></Field>
        {form.error ? <p className="text-sm text-destructive sm:col-span-2">{form.error}</p> : null}
        <div className="w-fit sm:col-span-2"><Button type="submit" disabled={form.pending}>Save requisition</Button></div>
      </form>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const id = new FormData(event.currentTarget).get('requisition_id')
        void form.send(`/api/v1/recruitment/requisitions/${id}/submit`, null, 'Requisition submitted.')
      }}>
        <h2 className="text-lg font-semibold sm:col-span-2">Submit for approval</h2>
        <Field label="Draft requisition" htmlFor="submit-req"><Select id="submit-req" name="requisition_id" options={requisitions.filter((row) => row.status === 'draft').map((row) => ({ value: String(row.id), label: row.title }))} /></Field>
        <div className="flex items-end"><Button type="submit" disabled={form.pending}>Submit</Button></div>
      </form>
      {canApproveRequisition ? <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const id = new FormData(event.currentTarget).get('requisition_id')
        void form.send(`/api/v1/recruitment/requisitions/${id}/approve`, {}, 'Requisition approved.')
      }}>
        <h2 className="text-lg font-semibold sm:col-span-2">Approve requisition</h2>
        <Field label="Waiting" htmlFor="approve-req"><Select id="approve-req" name="requisition_id" options={requisitions.filter((row) => row.status === 'pending_approval').map((row) => ({ value: String(row.id), label: row.title }))} /></Field>
        <div className="flex items-end"><Button type="submit" disabled={form.pending}>Approve</Button></div>
      </form> : null}
      {canApproveRequisition ? <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const body = new FormData(event.currentTarget)
        void form.send(`/api/v1/recruitment/requisitions/${body.get('requisition_id')}/reject`, { comments: String(body.get('comments') ?? '') }, 'Requisition rejected.')
      }}>
        <h2 className="text-lg font-semibold sm:col-span-2">Reject requisition</h2>
        <Field label="Waiting" htmlFor="reject-req"><Select id="reject-req" name="requisition_id" options={requisitions.filter((row) => row.status === 'pending_approval').map((row) => ({ value: String(row.id), label: row.title }))} /></Field>
        <Field label="Reason" htmlFor="reject-reason"><Input id="reject-reason" name="comments" required /></Field>
        <div className="w-fit sm:col-span-2"><Button type="submit" disabled={form.pending}>Reject</Button></div>
      </form> : null}
      {canApproveOffer ? <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const id = new FormData(event.currentTarget).get('offer_id')
        void form.send(`/api/v1/recruitment/offers/${id}/approve`, {}, 'Offer approved.')
      }}>
        <h2 className="text-lg font-semibold sm:col-span-2">Approve offer</h2>
        <Field label="Waiting offer" htmlFor="approve-offer"><Select id="approve-offer" name="offer_id" options={offers.filter((row) => row.status === 'pending_approval').map((row) => ({ value: String(row.id), label: `${row.designation} (${row.status})` }))} /></Field>
        <div className="flex items-end"><Button type="submit" disabled={form.pending}>Approve offer</Button></div>
      </form> : null}
      {canApproveOffer ? <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const body = new FormData(event.currentTarget)
        void form.send(`/api/v1/recruitment/offers/${body.get('offer_id')}/reject`, { comments: String(body.get('comments') ?? '') }, 'Offer rejected.')
      }}>
        <h2 className="text-lg font-semibold sm:col-span-2">Reject offer</h2>
        <Field label="Waiting offer" htmlFor="reject-offer"><Select id="reject-offer" name="offer_id" options={offers.filter((row) => row.status === 'pending_approval').map((row) => ({ value: String(row.id), label: row.designation }))} /></Field>
        <Field label="Reason" htmlFor="offer-reject-reason"><Input id="offer-reject-reason" name="comments" required /></Field>
        <div className="w-fit sm:col-span-2"><Button type="submit" disabled={form.pending}>Reject offer</Button></div>
      </form> : null}
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
        const text = (name: string) => String(body.get(name) ?? '').trim() || null
        const number = (name: string) => (text(name) === null ? null : Number(text(name)))
        void form.send(`/api/v1/recruitment/openings/${body.get('opening_id')}/publish`, {
          location: String(body.get('location') ?? ''),
          employment_type: String(body.get('employment_type') ?? 'full_time'),
          category: String(body.get('category') ?? 'Other'),
          experience_min: number('experience_min'),
          experience_max: number('experience_max'),
          salary_public: body.get('salary_public') === 'on',
          salary_note: text('salary_note'),
          show_headcount: body.get('show_headcount') === 'on',
          deadline_on: text('deadline_on'),
          description: text('description'),
          responsibilities: text('responsibilities'),
          qualifications: text('qualifications'),
          skills: text('skills'),
          benefits: text('benefits'),
        }, 'Opening is public on Careers.')
      }}>
        <h2 className="text-lg font-semibold sm:col-span-2">Public careers</h2>
        <p className="text-sm text-muted-foreground sm:col-span-2">The public page is <Link className="font-semibold text-[#3a35e0]" to="/careers">/careers</Link>. Publishing again updates the public details.</p>
        <Field label="Opening" htmlFor="public-opening"><Select id="public-opening" name="opening_id" options={openings.filter((row) => row.status === 'open').map((row) => ({ value: String(row.id), label: `${row.title}${row.is_public ? ' (public)' : ''}` }))} /></Field>
        <Field label="Location" htmlFor="public-location"><Input id="public-location" name="location" required /></Field>
        <Field label="Category" htmlFor="public-category"><Select id="public-category" name="category" options={careerCategories.map((row) => ({ value: row, label: row }))} /></Field>
        <Field label="Employment type" htmlFor="public-type"><Select id="public-type" name="employment_type" options={[{ value: 'full_time', label: 'Full time' }, { value: 'part_time', label: 'Part time' }, { value: 'contract', label: 'Contract' }, { value: 'intern', label: 'Internship' }]} /></Field>
        <Field label="Minimum experience (years)" htmlFor="public-exp-min"><Input id="public-exp-min" name="experience_min" type="number" min={0} /></Field>
        <Field label="Maximum experience (years)" htmlFor="public-exp-max"><Input id="public-exp-max" name="experience_max" type="number" min={0} /></Field>
        <Field label="Salary text" htmlFor="public-salary"><Input id="public-salary" name="salary_note" placeholder="For example: ₹3–4 LPA" /></Field>
        <Field label="Apply by" htmlFor="public-deadline"><Input id="public-deadline" name="deadline_on" type="date" /></Field>
        <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="salary_public" /> Show salary publicly</label>
        <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="show_headcount" /> Show number of openings</label>
        {(['description', 'responsibilities', 'qualifications', 'skills', 'benefits'] as const).map((name) => (
          <Field key={name} label={name[0].toUpperCase() + name.slice(1)} htmlFor={`public-${name}`}><textarea id={`public-${name}`} name={name} rows={3} className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" /></Field>
        ))}
        <div className="w-fit sm:col-span-2"><Button type="submit" disabled={form.pending}>Show on careers</Button></div>
      </form>
      {openings.some((row) => row.slug) ? (
        <div className="grid gap-2">
          <h2 className="text-lg font-semibold">Careers listings</h2>
          {openings.filter((row) => row.slug).map((row) => (
            <div key={row.id} className="flex flex-wrap items-center gap-2 rounded-md border p-3 text-sm">
              <span className="font-medium">{row.title}</span>
              <span className="text-muted-foreground">{row.is_public ? 'Public' : 'Hidden'} · {row.status}</span>
              <span className="ml-auto flex flex-wrap gap-2">
                <a className="underline" href={`/careers/jobs/${row.slug}`} target="_blank" rel="noreferrer">Preview</a>
                <button type="button" className="underline" onClick={() => void navigator.clipboard.writeText(`${window.location.origin}/careers/jobs/${row.slug}`)}>Copy link</button>
                {row.is_public ? <button type="button" className="underline" disabled={form.pending} onClick={() => void form.send(`/api/v1/recruitment/openings/${row.id}/unpublish`, null, 'Opening hidden from Careers.')}>Unpublish</button> : null}
                {row.status === 'open' ? <button type="button" className="underline" disabled={form.pending} onClick={() => void form.send(`/api/v1/recruitment/openings/${row.id}/close`, { status: 'closed' }, 'Opening closed.')}>Close</button> : null}
                {row.status !== 'archived' ? <button type="button" className="underline" disabled={form.pending} onClick={() => void form.send(`/api/v1/recruitment/openings/${row.id}/close`, { status: 'archived' }, 'Opening archived.')}>Archive</button> : null}
              </span>
            </div>
          ))}
        </div>
      ) : null}
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
        <h2 className="text-lg font-semibold sm:col-span-2">Candidate</h2>
        <Field label="Given name" htmlFor="cand-given"><Input id="cand-given" name="given_name" required /></Field>
        <Field label="Family name" htmlFor="cand-family"><Input id="cand-family" name="family_name" required /></Field>
        <Field label="Email" htmlFor="cand-email"><Input id="cand-email" name="email" type="email" /></Field>
        <Field label="Source" htmlFor="cand-source"><Input id="cand-source" name="source" /></Field>
        <div className="w-fit sm:col-span-2"><Button type="submit" disabled={form.pending}>Save candidate</Button></div>
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
        <div className="w-fit sm:col-span-2"><Button type="submit" disabled={form.pending}>Upload resume</Button></div>
      </form>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
        event.preventDefault()
        const body = new FormData(event.currentTarget)
        void form.send('/api/v1/recruitment/applications', {
          job_opening_id: Number(body.get('job_opening_id')),
          candidate_id: Number(body.get('candidate_id')),
        }, 'Application added to the pipeline.')
      }}>
        <h2 className="text-lg font-semibold sm:col-span-2">Application</h2>
        <Field label="Opening" htmlFor="app-opening"><Select id="app-opening" name="job_opening_id" options={openings.map((row) => ({ value: String(row.id), label: row.title }))} /></Field>
        <Field label="Candidate" htmlFor="app-candidate"><Select id="app-candidate" name="candidate_id" options={candidates.map((row) => ({ value: String(row.id), label: row.full_name }))} /></Field>
        <div className="w-fit sm:col-span-2"><Button type="submit" disabled={form.pending}>Add application</Button></div>
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
        <h2 className="text-lg font-semibold sm:col-span-2">Interview</h2>
        <Field label="Application" htmlFor="int-app"><Select id="int-app" name="application_id" options={applications.map((row) => ({ value: String(row.id), label: row.candidate_name ?? String(row.id) }))} /></Field>
        <Field label="When" htmlFor="int-when"><Input id="int-when" name="scheduled_at" type="datetime-local" required /></Field>
        <Field label="Interviewer" htmlFor="int-name"><Input id="int-name" name="interviewer" required /></Field>
        <Field label="Mode" htmlFor="int-mode"><Select id="int-mode" name="mode" defaultValue="video" options={[{ value: 'video', label: 'Video' }, { value: 'phone', label: 'Phone' }, { value: 'in_person', label: 'In person' }]} /></Field>
        <div className="w-fit sm:col-span-2"><Button type="submit" disabled={form.pending}>Schedule</Button></div>
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
        <h2 className="text-lg font-semibold sm:col-span-2">Offer</h2>
        <Field label="Application" htmlFor="offer-app"><Select id="offer-app" name="job_application_id" options={applications.map((row) => ({ value: String(row.id), label: row.candidate_name ?? String(row.id) }))} /></Field>
        <Field label="Legal employer" htmlFor="offer-entity"><Select id="offer-entity" name="legal_entity_id" options={entities.map((entity) => ({ value: String(entity.id), label: entity.legal_name }))} /></Field>
        <Field label="Designation" htmlFor="offer-role"><Input id="offer-role" name="designation" required /></Field>
        <Field label="Compensation note" htmlFor="offer-pay"><Input id="offer-pay" name="compensation_note" required /></Field>
        <div className="w-fit sm:col-span-2"><Button type="submit" disabled={form.pending}>Save draft offer</Button></div>
      </form>
    </div>
  )
}
