import { useQueryClient } from '@tanstack/react-query'
import { CalendarClock, CheckCircle2, Download, FileText, FlaskConical, Mail, MapPin, Phone, Send, UserCheck, X, XCircle } from 'lucide-react'
import { useState } from 'react'

import { Button } from '@/components/ui/button'
import { Field, Input, Textarea } from '@/components/ui/input'
import { Select } from '@/components/ui/select'
import { useToast } from '@/components/ui/toast'
import { ApiError, apiData, apiFetch } from '@/lib/api'

export type PipelineInterview = { id: number; scheduled_at: string; interviewer: string; mode: string; status: string; rating: number | null; recommendation: string | null; comments: string | null }
export type PipelineOffer = { id: number; designation: string; compensation_note: string; status: string; decision_note: string | null }
export type PipelineApplication = {
  id: number
  job_opening_id: number
  job_title: string | null
  candidate_name: string | null
  email: string | null
  phone: string | null
  location: string | null
  experience_years: number | null
  skills: string | null
  is_test_data: boolean
  source: string | null
  expected_salary: string | number | null
  stage: string
  applied_on: string | null
  resume_id: number | null
  letters: Array<{ id: number; type: string; number: string; issued_on: string }>
  interviews: PipelineInterview[]
  offer: PipelineOffer | null
}

const stages = [
  { id: 'applied', label: 'Applied', tone: 'from-[#e8f0ff] to-[#f5f8ff] text-[#2453c7]', dot: 'bg-[#3b6cf0]' },
  { id: 'screening', label: 'Screening', tone: 'from-[#fff4e0] to-[#fffaf0] text-[#a45f00]', dot: 'bg-[#f0a020]' },
  { id: 'interview', label: 'Interview', tone: 'from-[#f1ebff] to-[#f9f6ff] text-[#6d38d6]', dot: 'bg-[#8b5cf6]' },
  { id: 'offer', label: 'Offer', tone: 'from-[#ffe9f3] to-[#fff6fa] text-[#c0306f]', dot: 'bg-[#ec4899]' },
  { id: 'hired', label: 'Hired', tone: 'from-[#e3f8ee] to-[#f3fcf7] text-[#11804f]', dot: 'bg-[#16a34a]' },
  { id: 'rejected', label: 'Rejected', tone: 'from-[#f2f3f5] to-[#fafafa] text-[#5b6170]', dot: 'bg-[#9aa0ab]' },
] as const

const offerLabels: Record<string, string> = {
  draft: 'Draft offer',
  pending_approval: 'Waiting for approval',
  approved: 'Approved, not sent',
  rejected: 'Offer rejected internally',
  sent: 'Sent, waiting for candidate',
  accepted: 'Accepted by candidate',
  declined: 'Declined by candidate',
}

const letterLabels: Record<string, string> = {
  offer_letter: 'Offer letter',
  appointment_letter: 'Appointment letter',
  joining_letter: 'Joining letter',
}

async function download(path: string, name: string) {
  const response = await apiFetch(path)
  if (!response.ok) throw new ApiError('The file could not be downloaded.', response.status)
  const url = URL.createObjectURL(await response.blob())
  const link = document.createElement('a')
  link.href = url
  link.download = name
  link.click()
  URL.revokeObjectURL(url)
}

export function PipelineBoard({ applications, entities, canManage, canApproveOffer, canConfirmJoining }: {
  applications: PipelineApplication[]
  entities: Array<{ id: number; legal_name: string }>
  canManage: boolean
  canApproveOffer: boolean
  canConfirmJoining: boolean
}) {
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [job, setJob] = useState('all')
  const jobs = [...new Map(applications.map((row) => [row.job_opening_id, row.job_title ?? `Opening ${row.job_opening_id}`])).entries()]
  const visible = job === 'all' ? applications : applications.filter((row) => String(row.job_opening_id) === job)
  const selected = applications.find((row) => row.id === selectedId) ?? null

  return (
    <section className="flex flex-col gap-4 border-t border-border pt-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold">Hiring pipeline</h2>
          <p className="text-sm text-muted-foreground">Every careers-page application lands in Applied. Open a card to move it to the next step.</p>
        </div>
        <div className="w-64"><Select aria-label="Filter by job" value={job} onChange={(event) => setJob(event.target.value)} options={[{ value: 'all', label: `All jobs (${applications.length})` }, ...jobs.map(([id, title]) => ({ value: String(id), label: title }))]} /></div>
      </div>
      <div className="-mx-1 flex gap-3 overflow-x-auto px-1 pb-2">
        {stages.map((stage) => {
          const rows = visible.filter((row) => row.stage === stage.id)
          return (
            <div key={stage.id} className="flex w-60 shrink-0 flex-col rounded-2xl border border-border bg-muted/30">
              <div className={`flex items-center justify-between rounded-t-2xl bg-gradient-to-r px-3 py-2.5 text-sm font-semibold ${stage.tone}`}>
                <span className="inline-flex items-center gap-2"><span className={`size-2 rounded-full ${stage.dot}`} />{stage.label}</span>
                <span className="rounded-full bg-white/80 px-2 text-xs">{rows.length}</span>
              </div>
              <div className="flex max-h-[26rem] flex-col gap-2 overflow-y-auto p-2">
                {rows.length === 0 ? <p className="px-1 py-4 text-center text-xs text-muted-foreground">Nobody here</p> : rows.map((row) => (
                  <button key={row.id} type="button" onClick={() => setSelectedId(row.id)}
                    className={`rounded-xl border bg-background p-3 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md ${selectedId === row.id ? 'border-primary ring-2 ring-primary/20' : 'border-border'}`}>
                    <p className="truncate text-sm font-semibold">{row.candidate_name ?? 'Candidate'}</p>
                    <p className="truncate text-xs text-muted-foreground">{row.job_title}</p>
                    <div className="mt-2 flex flex-wrap items-center gap-1.5 text-[10px]">
                      {row.applied_on ? <span className="rounded-full bg-muted px-2 py-0.5">{row.applied_on}</span> : null}
                      {row.source ? <span className="rounded-full bg-muted px-2 py-0.5 capitalize">{row.source.replace(/_/g, ' ')}</span> : null}
                      {row.is_test_data ? <span className="inline-flex items-center gap-1 rounded-full bg-[#f3efff] px-2 py-0.5 font-semibold text-[#6d38d6]"><FlaskConical className="size-3" />Test</span> : null}
                      {row.offer ? <span className="rounded-full bg-[#ffe9f3] px-2 py-0.5 font-semibold text-[#c0306f]">{offerLabels[row.offer.status] ?? row.offer.status}</span> : null}
                    </div>
                  </button>
                ))}
              </div>
            </div>
          )
        })}
      </div>
      {selected ? <ApplicationPanel key={selected.id} application={selected} entities={entities} canManage={canManage} canApproveOffer={canApproveOffer} canConfirmJoining={canConfirmJoining} onClose={() => setSelectedId(null)} /> : null}
    </section>
  )
}

function ApplicationPanel({ application, entities, canManage, canApproveOffer, canConfirmJoining, onClose }: {
  application: PipelineApplication
  entities: Array<{ id: number; legal_name: string }>
  canManage: boolean
  canApproveOffer: boolean
  canConfirmJoining: boolean
  onClose: () => void
}) {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const offer = application.offer
  const openInterview = application.interviews.find((row) => row.status === 'scheduled')

  async function run(steps: Array<[string, Record<string, unknown> | null]>, success: string) {
    setPending(true)
    setError(null)
    try {
      for (const [path, body] of steps) {
        await apiData(path, body === null ? { method: 'POST' } : { method: 'POST', body: JSON.stringify(body) })
      }
      await queryClient.invalidateQueries({ queryKey: ['applications'] })
      await queryClient.invalidateQueries({ queryKey: ['offers'] })
      await queryClient.invalidateQueries({ queryKey: ['candidates'] })
      notify(success, 'success')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The request failed.')
      await queryClient.invalidateQueries({ queryKey: ['applications'] })
    } finally {
      setPending(false)
    }
  }

  const stagePath = `/api/v1/recruitment/applications/${application.id}/stage`
  const offerPath = offer ? `/api/v1/recruitment/offers/${offer.id}` : ''
  const reject = () => void run([[stagePath, { stage: 'rejected' }]], 'Application moved to Rejected.')

  return (
    <div className="grid gap-5 rounded-3xl border border-border bg-gradient-to-br from-[#fff5fa] via-background to-[#f2f7ff] p-5 shadow-sm lg:grid-cols-[1fr_1.3fr]">
      <div className="flex flex-col gap-3">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Application #{application.id}</p>
            <h3 className="text-xl font-semibold">{application.candidate_name}</h3>
            <p className="text-sm text-muted-foreground">{application.job_title}</p>
          </div>
          <button type="button" aria-label="Close" onClick={onClose} className="rounded-full p-1.5 hover:bg-muted"><X className="size-4" /></button>
        </div>
        <ul className="flex flex-col gap-1.5 text-sm">
          {application.email ? <li className="inline-flex items-center gap-2"><Mail className="size-4 text-muted-foreground" />{application.email}</li> : null}
          {application.phone ? <li className="inline-flex items-center gap-2"><Phone className="size-4 text-muted-foreground" />{application.phone}</li> : null}
          {application.location ? <li className="inline-flex items-center gap-2"><MapPin className="size-4 text-muted-foreground" />{application.location}</li> : null}
        </ul>
        <dl className="grid grid-cols-2 gap-2 text-xs">
          <div className="rounded-xl bg-background/80 p-2"><dt className="text-muted-foreground">Experience</dt><dd className="font-semibold">{application.experience_years ?? '-'} yrs</dd></div>
          <div className="rounded-xl bg-background/80 p-2"><dt className="text-muted-foreground">Expected pay</dt><dd className="font-semibold">{application.expected_salary ?? '-'}</dd></div>
          <div className="rounded-xl bg-background/80 p-2"><dt className="text-muted-foreground">Source</dt><dd className="font-semibold capitalize">{application.source?.replace(/_/g, ' ') ?? '-'}</dd></div>
          <div className="rounded-xl bg-background/80 p-2"><dt className="text-muted-foreground">Applied</dt><dd className="font-semibold">{application.applied_on ?? '-'}</dd></div>
        </dl>
        {application.skills ? <p className="text-xs text-muted-foreground">Skills: {application.skills}</p> : null}
        <div className="flex flex-wrap gap-2">
          {application.resume_id ? <Button variant="outline" onClick={() => void download(`/api/v1/recruitment/resumes/${application.resume_id}`, `resume-${application.id}`).catch(() => setError('The resume could not be downloaded.'))}><FileText className="size-4" />Resume</Button> : null}
          {offer && application.letters.length === 0 && offer.status !== 'draft' ? <Button variant="outline" onClick={() => void download(`${offerPath}/letter`, `offer-letter-${offer.id}.pdf`).catch(() => setError('The letter could not be downloaded.'))}><Download className="size-4" />Offer letter</Button> : null}
        </div>
        {application.letters.length > 0 ? (
          <div className="flex flex-col gap-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Letters issued</p>
            {application.letters.map((letter) => (
              <button key={letter.id} type="button" onClick={() => void download(`/api/v1/issued-documents/${letter.id}/pdf`, `${letter.number}.pdf`).catch(() => setError('The letter could not be downloaded.'))}
                className="flex items-center justify-between gap-2 rounded-xl border border-border bg-background/80 p-2 text-left text-xs hover:border-primary">
                <span><span className="font-semibold">{letterLabels[letter.type] ?? letter.type}</span><br /><span className="text-muted-foreground">{letter.number} · {letter.issued_on}</span></span>
                <Download className="size-4 text-muted-foreground" />
              </button>
            ))}
          </div>
        ) : null}
        {application.interviews.length > 0 ? (
          <div className="flex flex-col gap-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Interviews</p>
            {application.interviews.map((row) => (
              <div key={row.id} className="rounded-xl border border-border bg-background/80 p-2 text-xs">
                <p className="font-semibold">{row.scheduled_at} · {row.mode.replace('_', ' ')} · {row.interviewer}</p>
                <p className="text-muted-foreground">{row.status === 'completed' ? `Rating ${row.rating}/5 · ${row.recommendation} · ${row.comments}` : 'Scheduled'}</p>
              </div>
            ))}
          </div>
        ) : null}
      </div>

      <div className="flex flex-col gap-3 rounded-2xl border border-border bg-background p-4">
        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Next step</p>
        {error ? <p role="alert" className="rounded-xl bg-[#fdecec] px-3 py-2 text-sm text-[#b42318]">{error}</p> : null}
        {!canManage ? <p className="text-sm text-muted-foreground">You can view this application. Ask a recruiter to move it forward.</p> : null}

        {canManage && application.stage === 'applied' ? (
          <div className="flex flex-col gap-3">
            <p className="text-sm">Review the profile and resume. Shortlist to start screening, or reject.</p>
            <div className="flex gap-2"><Button disabled={pending} onClick={() => void run([[stagePath, { stage: 'screening' }]], 'Shortlisted for screening.')}><CheckCircle2 className="size-4" />Shortlist</Button><Button variant="outline" disabled={pending} onClick={reject}><XCircle className="size-4" />Reject</Button></div>
          </div>
        ) : null}

        {canManage && (application.stage === 'screening' || (application.stage === 'interview' && !openInterview)) ? (
          <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
            event.preventDefault()
            const body = new FormData(event.currentTarget)
            void run([
              [`/api/v1/recruitment/applications/${application.id}/interviews`, { scheduled_at: String(body.get('scheduled_at')), interviewer: String(body.get('interviewer')), mode: String(body.get('mode')) }],
              ...(application.stage === 'interview' ? [] : [[stagePath, { stage: 'interview' }]] as Array<[string, Record<string, unknown>]>),
            ], 'Interview scheduled.')
          }}>
            <p className="text-sm sm:col-span-2">{application.stage === 'screening' ? 'Screening done? Schedule the interview.' : 'Schedule another round, or make an offer below.'}</p>
            <Field label="When" htmlFor="p-when"><Input id="p-when" name="scheduled_at" type="datetime-local" required /></Field>
            <Field label="Interviewer" htmlFor="p-who"><Input id="p-who" name="interviewer" required /></Field>
            <Field label="Mode" htmlFor="p-mode"><Select id="p-mode" name="mode" defaultValue="video" options={[{ value: 'video', label: 'Video' }, { value: 'phone', label: 'Phone' }, { value: 'in_person', label: 'In person' }]} /></Field>
            <div className="flex items-end gap-2"><Button type="submit" disabled={pending}><CalendarClock className="size-4" />Schedule</Button>{application.stage === 'screening' ? <Button type="button" variant="outline" disabled={pending} onClick={reject}>Reject</Button> : null}</div>
          </form>
        ) : null}

        {canManage && application.stage === 'interview' && openInterview ? (
          <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => {
            event.preventDefault()
            const body = new FormData(event.currentTarget)
            const recommendation = String(body.get('recommendation'))
            void run([
              [`/api/v1/recruitment/interviews/${openInterview.id}/feedback`, { rating: Number(body.get('rating')), comments: String(body.get('comments')), recommendation }],
              ...(recommendation === 'reject' ? [[stagePath, { stage: 'rejected' }]] as Array<[string, Record<string, unknown>]> : []),
            ], 'Interview feedback saved.')
          }}>
            <p className="text-sm sm:col-span-2">Record the interview result from {openInterview.interviewer}.</p>
            <Field label="Rating" htmlFor="p-rating"><Select id="p-rating" name="rating" defaultValue="4" options={[5, 4, 3, 2, 1].map((value) => ({ value: String(value), label: `${value} / 5` }))} /></Field>
            <Field label="Recommendation" htmlFor="p-rec"><Select id="p-rec" name="recommendation" defaultValue="advance" options={[{ value: 'advance', label: 'Advance' }, { value: 'hold', label: 'Hold' }, { value: 'reject', label: 'Reject' }]} /></Field>
            <div className="sm:col-span-2"><Field label="Comments" htmlFor="p-comments"><Textarea id="p-comments" name="comments" rows={2} required /></Field></div>
            <div className="w-fit"><Button type="submit" disabled={pending}>Save feedback</Button></div>
          </form>
        ) : null}

        {canManage && application.stage === 'interview' && !openInterview && application.interviews.length > 0 && !offer ? (
          <form className="grid gap-3 border-t border-border pt-3 sm:grid-cols-2" onSubmit={(event) => {
            event.preventDefault()
            const body = new FormData(event.currentTarget)
            void run([
              ['/api/v1/recruitment/offers', { job_application_id: application.id, legal_entity_id: Number(body.get('legal_entity_id')), designation: String(body.get('designation')), compensation_note: String(body.get('compensation_note')) }],
              [stagePath, { stage: 'offer' }],
            ], 'Draft offer created. Submit it for approval next.')
          }}>
            <p className="text-sm font-semibold sm:col-span-2">Make an offer</p>
            <Field label="Designation" htmlFor="p-des"><Input id="p-des" name="designation" defaultValue={(application.job_title ?? '').replace('[TEST] ', '')} required /></Field>
            <Field label="Legal employer" htmlFor="p-entity"><Select id="p-entity" name="legal_entity_id" options={entities.map((row) => ({ value: String(row.id), label: row.legal_name }))} /></Field>
            <div className="sm:col-span-2"><Field label="Compensation" htmlFor="p-pay"><Input id="p-pay" name="compensation_note" placeholder="e.g. INR 4,20,000 per year CTC" required /></Field></div>
            <div className="w-fit"><Button type="submit" disabled={pending}>Create offer</Button></div>
          </form>
        ) : null}

        {canManage && application.stage === 'offer' && offer ? (
          <div className="flex flex-col gap-3">
            <div className="rounded-xl bg-[#fff6fa] p-3 text-sm">
              <p className="font-semibold">{offer.designation}</p>
              <p className="text-muted-foreground">{offer.compensation_note}</p>
              <p className="mt-1 text-xs font-semibold text-[#c0306f]">{offerLabels[offer.status] ?? offer.status}</p>
              {offer.decision_note ? <p className="mt-1 text-xs text-muted-foreground">{offer.decision_note}</p> : null}
            </div>
            {offer.status === 'draft' ? <Button className="w-fit" disabled={pending} onClick={() => void run([[`${offerPath}/submit`, null]], 'Offer sent for approval.')}>Submit for approval</Button> : null}
            {offer.status === 'pending_approval' ? (canApproveOffer ? (
              <form className="flex flex-col gap-2" onSubmit={(event) => {
                event.preventDefault()
                const comments = String(new FormData(event.currentTarget).get('comments') ?? '').trim()
                if (comments === '') { setError('Add a reason to reject the offer.'); return }
                void run([[`${offerPath}/reject`, { comments }]], 'Offer rejected.')
              }}>
                <p className="text-sm">The approver must be different from the person who submitted it.</p>
                <Input name="comments" placeholder="Reason, only needed to reject" aria-label="Rejection reason" />
                <div className="flex gap-2"><Button type="button" disabled={pending} onClick={() => void run([[`${offerPath}/approve`, null]], 'Offer approved.')}>Approve</Button><Button type="submit" variant="outline" disabled={pending}>Reject</Button></div>
              </form>
            ) : <p className="text-sm text-muted-foreground">Waiting for an offer approver.</p>) : null}
            {offer.status === 'approved' ? <Button className="w-fit" disabled={pending} onClick={() => void run([[`${offerPath}/send`, null]], 'Offer letter sent. The candidate can see it in their profile.')}><Send className="size-4" />Send offer letter</Button> : null}
            {offer.status === 'sent' ? (
              <div className="flex flex-col gap-2">
                <p className="text-sm">The candidate can accept or decline from their careers profile. If they replied by phone or email, record it here.</p>
                <div className="flex gap-2"><Button variant="outline" disabled={pending} onClick={() => void run([[`${offerPath}/accept`, null]], 'Marked as accepted.')}>Mark accepted</Button><Button variant="outline" disabled={pending} onClick={() => void run([[`${offerPath}/decline`, null]], 'Marked as declined.')}>Mark declined</Button></div>
              </div>
            ) : null}
            {offer.status === 'accepted' && !application.letters.some((letter) => letter.type === 'appointment_letter') ? (
              <form className="flex flex-wrap items-end gap-2 border-b border-border pb-3" onSubmit={(event) => {
                event.preventDefault()
                void run([[`${offerPath}/appointment-letter`, { starts_on: String(new FormData(event.currentTarget).get('starts_on')) }]], 'Appointment letter issued. The candidate can download it from their profile.')
              }}>
                <p className="w-full text-sm">Step 1: issue the appointment letter with the agreed start date.</p>
                <Field label="Start date" htmlFor="p-start"><Input id="p-start" name="starts_on" type="date" required /></Field>
                <Button type="submit" variant="outline" disabled={pending}><FileText className="size-4" />Issue appointment letter</Button>
              </form>
            ) : null}
            {offer.status === 'accepted' ? (canConfirmJoining ? (
              <form className="flex flex-wrap items-end gap-2" onSubmit={(event) => {
                event.preventDefault()
                void run([[`${offerPath}/convert`, { joined_on: String(new FormData(event.currentTarget).get('joined_on')) }]], 'Joining confirmed. The employee, joining letter and onboarding tasks were created.')
              }}>
                <p className="w-full text-sm">Step 2: on the joining day, confirm joining. This creates the employee and the joining letter.</p>
                <Field label="Joining date" htmlFor="p-join"><Input id="p-join" name="joined_on" type="date" required /></Field>
                <Button type="submit" disabled={pending}><UserCheck className="size-4" />Confirm joining</Button>
              </form>
            ) : <p className="text-sm text-muted-foreground">Accepted. Someone with joining permission must confirm the joining date.</p>) : null}
            {offer.status === 'declined' || offer.status === 'rejected' ? <Button variant="outline" className="w-fit" disabled={pending} onClick={reject}>Close as rejected</Button> : null}
          </div>
        ) : null}

        {application.stage === 'hired' ? <p className="rounded-xl bg-[#e3f8ee] p-3 text-sm text-[#11804f]">Joined. The employee record and onboarding checklist are ready in Employees and Onboarding.</p> : null}
        {application.stage === 'rejected' ? <p className="text-sm text-muted-foreground">This application is closed.</p> : null}
      </div>
    </div>
  )
}
