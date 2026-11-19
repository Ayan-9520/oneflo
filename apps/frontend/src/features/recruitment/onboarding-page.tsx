import { useQuery, useQueryClient } from '@tanstack/react-query'
import { type FormEvent, useState } from 'react'

import { useSession } from '@/app/session'
import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { Field, Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/page-header'
import { Select } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { useToast } from '@/components/ui/toast'
import { ApiError, apiData, apiFetch } from '@/lib/api'

type EmployeeRow = { id: number; full_name: string; employee_code: string }
type Onboarding = {
  profile: { probation_ends_on: string | null; probation_status: string; identity_status: string } | null
  tasks: Array<{ code: string; title: string; completed: boolean }>
  bank_accounts: Array<{ bank_name: string; account: string }>
}
type Category = { code: string; name: string; sensitive: boolean; requires_retention: boolean }
type EmployeeFileRow = { id: number; category: string; label: string; sensitive: boolean; reference: string | null; retention_until: string | null }
type Issued = { id: number; document_type: string; number: string; version: number; oneflo_attribution: boolean; voided_at: string | null }

export function OnboardingPage() {
  const { organizationId } = useSession()
  const employees = useQuery({ queryKey: ['employees', organizationId, 'onboarding'], enabled: organizationId !== null, queryFn: () => apiData<EmployeeRow[]>('/api/v1/employees') })
  const [employeeId, setEmployeeId] = useState('')
  if (organizationId === null) return <EmptyState title="Select a business" description="Onboarding belongs to the active business." />
  const selected = employeeId || (employees.data?.[0] ? String(employees.data[0].id) : '')

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Onboarding" description="Joining checklist, identity tracking, and employee records for the legal employer. Account passwords are not shown after creation." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Onboarding' }]} />
      {employees.isPending ? <Skeleton className="h-20 w-full" /> : employees.isError ? <ErrorState title="Employees could not be loaded" description={employees.error instanceof ApiError ? employees.error.message : 'Try again.'} /> : (
        <Field label="Employee" htmlFor="onboard-employee">
          <Select id="onboard-employee" value={selected} onChange={(event) => setEmployeeId(event.target.value)} options={(employees.data ?? []).map((employee) => ({ value: String(employee.id), label: `${employee.employee_code} ${employee.full_name}` }))} />
        </Field>
      )}
      {selected ? <OnboardingDetail employeeId={Number(selected)} /> : null}
    </div>
  )
}

export function OnboardingDetail({ employeeId }: { employeeId: number }) {
  const { organizationId, permissions } = useSession()
  const canManage = permissions.has('onboarding.manage')
  const record = useQuery({
    queryKey: ['onboarding', organizationId, employeeId],
    enabled: organizationId !== null,
    queryFn: () => apiData<Onboarding>(`/api/v1/employees/${employeeId}/onboarding`),
  })
  if (record.isPending) return <Skeleton className="h-24 w-full" />
  if (record.isError) return <ErrorState title="Onboarding could not be loaded" description={record.error instanceof ApiError ? record.error.message : 'Try again.'} />
  return (
    <div className="flex flex-col gap-4">
      {record.data.profile === null ? <EmptyState title="Onboarding has not started" description="Start the checklist to track joining tasks and probation." /> : (
        <p className="text-sm">Probation {record.data.profile.probation_status}{record.data.profile.probation_ends_on ? ` until ${record.data.profile.probation_ends_on}` : ''}. Identity {record.data.profile.identity_status}.</p>
      )}
      {record.data.tasks.length > 0 ? <ul className="text-sm">{record.data.tasks.map((task) => <li key={task.code}>{task.completed ? 'Done' : 'Open'} — {task.title}</li>)}</ul> : null}
      {record.data.bank_accounts.map((account) => <p key={account.account} className="text-sm">{account.bank_name} {account.account}</p>)}
      {canManage ? <OnboardingForms employeeId={employeeId} /> : null}
      <EmployeeDocuments employeeId={employeeId} />
    </div>
  )
}

function OnboardingForms({ employeeId }: { employeeId: number }) {
  const notify = useToast().notify
  const queryClient = useQueryClient()
  const [error, setError] = useState<string | null>(null)
  async function send(path: string, body: Record<string, unknown>) {
    setError(null)
    try {
      await apiData(path, { method: 'POST', body: JSON.stringify(body) })
      await queryClient.invalidateQueries({ queryKey: ['onboarding'] })
      notify('Onboarding updated.', 'success')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The onboarding request failed.')
    }
  }
  return (
    <div className="grid gap-4">
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()
        const body = new FormData(event.currentTarget)
        void send(`/api/v1/employees/${employeeId}/onboarding`, { probation_ends_on: String(body.get('probation_ends_on') ?? '') || null })
      }}>
        <Field label="Probation ends" htmlFor={`probation-${employeeId}`}><Input id={`probation-${employeeId}`} name="probation_ends_on" type="date" /></Field>
        <div className="flex items-end"><Button type="submit">Start checklist</Button></div>
      </form>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
    </div>
  )
}

export function EmployeeDocuments({ employeeId }: { employeeId: number }) {
  const { organizationId, permissions } = useSession()
  const canGenerate = permissions.has('documents.generate')
  const categories = useQuery({ queryKey: ['document-categories', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Category[]>('/api/v1/document-categories') })
  const files = useQuery({ queryKey: ['employee-files', organizationId, employeeId], enabled: organizationId !== null, queryFn: () => apiData<EmployeeFileRow[]>(`/api/v1/employees/${employeeId}/files`) })
  const issued = useQuery({ queryKey: ['issued-documents', organizationId, employeeId], enabled: organizationId !== null, queryFn: () => apiData<Issued[]>(`/api/v1/issued-documents?employee_id=${employeeId}`) })
  const entities = useQuery({ queryKey: ['legal-entities', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; legal_name: string }>>('/api/v1/legal-entities') })
  const notify = useToast().notify
  const queryClient = useQueryClient()
  const [error, setError] = useState<string | null>(null)

  const letterLabel: Record<string, string> = {
    offer_letter: 'Offer letter',
    appointment_letter: 'Appointment letter',
    employment_agreement: 'Employment agreement',
    joining_letter: 'Joining letter',
    confirmation_letter: 'Confirmation letter',
    increment_letter: 'Increment letter',
    promotion_letter: 'Promotion letter',
    experience_certificate: 'Experience letter',
    relieving_letter: 'Relieving letter',
    warning_letter: 'Warning letter',
    resignation_acceptance: 'Resignation acceptance',
    no_objection_certificate: 'No-objection certificate',
    salary_certificate: 'Salary certificate',
    internship_offer: 'Internship offer',
    internship_completion: 'Internship completion',
    transfer_letter: 'Transfer letter',
    interview_call_letter: 'Interview call letter',
    authorization_letter: 'Authorization letter',
    employment_verification: 'Employment verification',
  }

  async function voidLetter(row: Issued) {
    setError(null)
    try {
      await apiData(`/api/v1/issued-documents/${row.id}/void`, { method: 'POST', body: JSON.stringify({}) })
      notify('Letter cancelled. The number stays on record.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['issued-documents', organizationId, employeeId] })
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The letter could not be cancelled.')
    }
  }

  async function downloadLetter(row: Issued) {
    if (row.voided_at) {
      return
    }
    setError(null)
    const response = await apiFetch(`/api/v1/issued-documents/${row.id}/pdf`)
    if (!response.ok) {
      setError('The PDF could not be downloaded.')
      return
    }
    const url = URL.createObjectURL(await response.blob())
    const link = document.createElement('a')
    link.href = url
    link.download = `${row.number}.pdf`
    link.click()
    URL.revokeObjectURL(url)
  }

  async function upload(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    try {
      const response = await apiFetch(`/api/v1/employees/${employeeId}/files`, { method: 'POST', body: new FormData(event.currentTarget) })
      if (!response.ok) {
        const payload = await response.json() as { message?: string }
        throw new ApiError(payload.message ?? 'The file could not be stored.', response.status)
      }
      notify('Document stored. Sensitive files stay encrypted and masked.', 'success')
      event.currentTarget.reset()
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The file could not be stored.')
    }
  }

  async function issue(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const body = new FormData(event.currentTarget)
    setError(null)
    try {
      await apiData('/api/v1/issued-documents', {
        method: 'POST',
        body: JSON.stringify({
          legal_entity_id: Number(body.get('legal_entity_id')),
          document_type: String(body.get('document_type') ?? ''),
          employee_id: employeeId,
        }),
      })
      notify('Document issued with a number and version.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['issued-documents', organizationId, employeeId] })
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The document could not be issued.')
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <h2 className="text-base font-semibold">Employee documents</h2>
      <p className="text-sm text-muted-foreground">Aadhaar, PAN, and bank proof are sensitive. Store only the last four characters of an identifier, set a retention date, and do not enter a full Aadhaar number.</p>
      {files.isPending ? <Skeleton className="h-16 w-full" /> : files.data?.length === 0 ? <EmptyState title="No stored files" description="Collected documents appear here with masked references." /> : (
        <ul className="text-sm">{files.data?.map((file) => <li key={file.id}>{file.label} · {file.category}{file.reference ? ` · ${file.reference}` : ''}{file.retention_until ? ` · retain until ${file.retention_until}` : ''}</li>)}</ul>
      )}
      {issued.data && issued.data.length > 0 ? (
        <div className="flex flex-col gap-2">
          <h3 className="text-sm font-semibold">Issued letters</h3>
          <ul className="flex flex-col gap-2">
            {issued.data.map((row) => (
              <li key={row.id} className="flex flex-col gap-2 rounded-xl border border-border bg-card px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                <span>{letterLabel[row.document_type] ?? row.document_type} · {row.number} · version {row.version}{row.voided_at ? ' · Cancelled' : ''}{row.oneflo_attribution ? ' · OneFlo footer' : ''}</span>
                <span className="flex gap-2">
                  {canGenerate && !row.voided_at ? <Button size="sm" variant="outline" onClick={() => void voidLetter(row)}>Void</Button> : null}
                  <Button size="sm" variant="outline" disabled={row.voided_at !== null} onClick={() => void downloadLetter(row)}>Download PDF</Button>
                </span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
      {canGenerate ? (
        <>
          <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => void upload(event)}>
            <Field label="Category" htmlFor={`file-category-${employeeId}`}><Select id={`file-category-${employeeId}`} name="category" options={(categories.data ?? []).map((category) => ({ value: category.code, label: category.name }))} /></Field>
            <Field label="Label" htmlFor={`file-label-${employeeId}`}><Input id={`file-label-${employeeId}`} name="label" required /></Field>
            <Field label="Last four" htmlFor={`file-last4-${employeeId}`}><Input id={`file-last4-${employeeId}`} name="reference_last4" maxLength={4} /></Field>
            <Field label="Retain until" htmlFor={`file-until-${employeeId}`}><Input id={`file-until-${employeeId}`} name="retention_until" type="date" /></Field>
            <Field label="File" htmlFor={`file-input-${employeeId}`}><Input id={`file-input-${employeeId}`} name="file" type="file" accept=".pdf,.jpg,.jpeg,.png" required /></Field>
            <div className="flex items-end"><Button type="submit">Store document</Button></div>
          </form>
          <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => void issue(event)}>
            <Field label="Legal employer" htmlFor={`letter-entity-${employeeId}`}><Select id={`letter-entity-${employeeId}`} name="legal_entity_id" options={(entities.data ?? []).map((entity) => ({ value: String(entity.id), label: entity.legal_name }))} /></Field>
            <Field label="Letter" htmlFor={`letter-type-${employeeId}`}>
              <Select id={`letter-type-${employeeId}`} name="document_type" defaultValue="appointment_letter" options={[
                ['offer_letter', 'Offer letter'],
                ['appointment_letter', 'Appointment letter'],
                ['employment_agreement', 'Employment agreement'],
                ['joining_letter', 'Joining letter'],
                ['confirmation_letter', 'Confirmation letter'],
                ['increment_letter', 'Increment letter'],
                ['promotion_letter', 'Promotion letter'],
                ['experience_certificate', 'Experience letter'],
                ['relieving_letter', 'Relieving letter'],
                ['warning_letter', 'Warning letter'],
                ['resignation_acceptance', 'Resignation acceptance'],
                ['no_objection_certificate', 'No-objection certificate'],
                ['salary_certificate', 'Salary certificate'],
                ['internship_offer', 'Internship offer'],
                ['internship_completion', 'Internship completion'],
                ['transfer_letter', 'Transfer letter'],
                ['interview_call_letter', 'Interview call letter'],
                ['authorization_letter', 'Authorization letter'],
                ['employment_verification', 'Employment verification'],
              ].map(([value, label]) => ({ value, label }))} />
            </Field>
            <Button type="submit">Issue PDF letter</Button>
          </form>
        </>
      ) : null}
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
    </div>
  )
}
