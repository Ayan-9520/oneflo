import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router'

import { useSession } from '@/app/session'
import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/ui/empty-state'
import { Field, Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/page-header'
import { Select } from '@/components/ui/select'
import { useToast } from '@/components/ui/toast'
import { ApiError, apiData, apiFetch } from '@/lib/api'

type Entity = { id: number; brand_id: number | null; legal_name: string }
type Profile = {
  legal_company_name: string
  registered_address: string | null
  contact_email: string | null
  contact_phone: string | null
  website: string | null
  offices: Array<{ line_1: string | null; line_2: string | null; city: string | null; state: string | null; postal_code: string | null }>
  signatories: Array<{ name: string; designation: string; is_primary: boolean }>
}

function companyAddress(profile: Profile | null | undefined): string {
  if (!profile) return ''
  if (profile.registered_address) return profile.registered_address
  const office = profile.offices?.[0]
  if (!office) return ''
  return [office.line_1, office.line_2, office.city, office.state, office.postal_code].filter(Boolean).join(', ')
}

const letters = [
  { id: 'offer_letter', label: 'Offer letter' },
  { id: 'appointment_letter', label: 'Appointment letter' },
  { id: 'joining_letter', label: 'Joining letter' },
  { id: 'confirmation_letter', label: 'Confirmation letter' },
  { id: 'internship_offer', label: 'Internship offer' },
  { id: 'interview_call_letter', label: 'Interview call letter' },
  { id: 'employment_agreement', label: 'Employment agreement' },
  { id: 'increment_letter', label: 'Increment letter' },
  { id: 'promotion_letter', label: 'Promotion letter' },
  { id: 'experience_certificate', label: 'Experience certificate' },
  { id: 'salary_certificate', label: 'Salary certificate' },
  { id: 'relieving_letter', label: 'Relieving letter' },
  { id: 'warning_letter', label: 'Warning letter' },
  { id: 'resignation_acceptance', label: 'Resignation acceptance' },
  { id: 'no_objection_certificate', label: 'No-objection certificate' },
  { id: 'internship_completion', label: 'Internship completion' },
  { id: 'transfer_letter', label: 'Transfer letter' },
  { id: 'authorization_letter', label: 'Authorization letter' },
  { id: 'employment_verification', label: 'Employment verification' },
  { id: 'general_letter', label: 'General letter' },
] as const

type LetterId = (typeof letters)[number]['id']

const hireLetters = new Set<LetterId>(['offer_letter', 'appointment_letter', 'joining_letter'])
const payLetters = new Set<LetterId>(['offer_letter', 'appointment_letter', 'joining_letter', 'confirmation_letter', 'increment_letter', 'salary_certificate', 'employment_agreement', 'promotion_letter', 'employment_verification'])
const endDateLetters = new Set<LetterId>(['experience_certificate', 'relieving_letter', 'internship_offer', 'internship_completion', 'resignation_acceptance', 'confirmation_letter', 'transfer_letter'])

function rupees(value: string): number | null {
  const raw = value.toLowerCase().replace(/[, ₹]/g, '').replace(/^rs\.?/, '').trim()
  if (raw === '') return null
  const match = /^(\d+(?:\.\d+)?)(lakh|lac|l|crore|cr)?$/.exec(raw)
  if (!match) return Number.NaN
  const amount = Number(match[1])
  if (match[2] === 'lakh' || match[2] === 'lac' || match[2] === 'l') return amount * 100000
  if (match[2] === 'crore' || match[2] === 'cr') return amount * 10000000
  return amount
}

function money(value: string): string {
  const amount = rupees(value)
  if (amount === null) return 'not entered'
  if (Number.isNaN(amount)) return 'not a number'
  return amount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function amountPayload(value: string): string | null {
  const amount = rupees(value)
  if (amount === null || Number.isNaN(amount)) return null
  return String(amount)
}

function displayDate(value: string): string {
  if (!value) return 'not entered'
  const [year, month, day] = value.split('-')
  return year && month && day ? `${day}-${month}-${year}` : value
}

function mark(company: string): string {
  const parts = company.split(/\s+/).filter(Boolean)
  if (parts.length === 0) return 'CO'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return parts.slice(0, 3).map((part) => part[0]?.toUpperCase() ?? '').join('')
}

function hueOf(red: number, green: number, blue: number): number {
  const max = Math.max(red, green, blue)
  const min = Math.min(red, green, blue)
  if (max === min) return 0
  const delta = max - min
  const hue = max === red ? ((green - blue) / delta) % 6 : max === green ? (blue - red) / delta + 2 : (red - green) / delta + 4
  return (hue * 60 + 360) % 360
}

function paletteFrom(image: HTMLImageElement): { primary: string; secondary: string } {
  const canvas = document.createElement('canvas')
  const size = 40
  canvas.width = size
  canvas.height = size
  const context = canvas.getContext('2d', { willReadFrequently: true })
  if (!context) return { primary: '#036F3A', secondary: '#312563' }
  context.drawImage(image, 0, 0, size, size)
  const pixels = context.getImageData(0, 0, size, size).data
  const buckets = new Map<number, { count: number; red: number; green: number; blue: number }>()
  for (let index = 0; index < pixels.length; index += 16) {
    const red = pixels[index]
    const green = pixels[index + 1]
    const blue = pixels[index + 2]
    const alpha = pixels[index + 3]
    const max = Math.max(red, green, blue)
    const min = Math.min(red, green, blue)
    const saturation = max === 0 ? 0 : (max - min) / max
    const light = (max + min) / 2 / 255
    if (alpha < 200 || saturation < 0.35 || light < 0.18 || light > 0.9) continue
    const hue = Math.round(hueOf(red, green, blue) / 20) * 20
    const bucket = buckets.get(hue) ?? { count: 0, red: 0, green: 0, blue: 0 }
    bucket.count += 1
    bucket.red += red
    bucket.green += green
    bucket.blue += blue
    buckets.set(hue, bucket)
  }
  const ranked = [...buckets.entries()].sort((left, right) => right[1].count - left[1].count)
  const hex = (bucket: { count: number; red: number; green: number; blue: number }) =>
    `#${[bucket.red, bucket.green, bucket.blue].map((channel) => Math.round(channel / bucket.count).toString(16).padStart(2, '0')).join('')}`
  if (ranked.length === 0) return { primary: '#036F3A', secondary: '#312563' }
  const second = ranked.find(([hue]) => {
    const distance = Math.abs(hue - ranked[0][0])
    return Math.min(distance, 360 - distance) >= 40
  })
  return { primary: hex(ranked[0][1]), secondary: second ? hex(second[1]) : '#312563' }
}

type IssuedLetter = { id: number; document_type: string; person: string; number: string; issued_on: string | null; voided_at: string | null }

export function LetterStudioPage() {
  const { organizationId, account } = useSession()
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const entities = useQuery({ queryKey: ['legal-entities', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Entity[]>('/api/v1/legal-entities') })
  const [letter, setLetter] = useState<LetterId>('offer_letter')
  const [candidateId, setCandidateId] = useState<number | null>(null)
  const [appointmentIssued, setAppointmentIssued] = useState(false)
  const [employeeId, setEmployeeId] = useState<number | null>(null)
  const [issued, setIssued] = useState<string | null>(null)
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [logoUrl, setLogoUrl] = useState<string | null>(null)
  const [, setTheme] = useState({ primary: '#3a35e0', secondary: '#6d38d6' })
  const [savedFile, setSavedFile] = useState<string | null>(null)
  const [letterSearch, setLetterSearch] = useState('')
  const [form, setForm] = useState({
    employee_name: '',
    address: '',
    email: '',
    phone: '',
    department: '',
    designation: '',
    employee_code: '',
    joining_date: '',
    end_date: '',
    letter_date: new Date().toISOString().slice(0, 10),
    ctc: '',
    variable: '',
  })
  const selectedEntity = entities.data?.[0] ? String(entities.data[0].id) : ''
  const entity = entities.data?.find((item) => String(item.id) === selectedEntity)
  const profile = useQuery({
    queryKey: ['company-profile', organizationId, selectedEntity],
    enabled: organizationId !== null && selectedEntity !== '',
    queryFn: () => apiData<Profile | null>(`/api/v1/legal-entities/${selectedEntity}/company-profile`),
  })
  const issuedLetters = useQuery({
    queryKey: ['issued-documents', organizationId],
    enabled: organizationId !== null,
    queryFn: () => apiData<IssuedLetter[]>('/api/v1/issued-documents'),
  })
  const company = profile.data?.legal_company_name || entity?.legal_name || 'Company'
  const address = companyAddress(profile.data)
  const signatory = profile.data?.signatories.find((row) => row.is_primary) ?? profile.data?.signatories[0]
  const person = form.employee_name.trim()
  const label = letters.find((item) => item.id === letter)?.label ?? 'Letter'
  const role = form.designation.trim() === '' ? 'the role entered on this letter' : form.designation.trim()
  const who = person || 'the person named on this letter'
  const start = displayDate(form.joining_date)
  const finish = displayDate(form.end_date)
  const codeSentence = form.employee_code.trim() === '' ? '' : ` Employee code: ${form.employee_code.trim()}.`

  useEffect(() => {
    const brandId = entity?.brand_id
    if (!brandId) {
      setLogoUrl(null)
      return
    }
    let cancelled = false
    let objectUrl: string | null = null
    setLogoUrl(null)
    apiFetch(`/api/v1/brands/${brandId}/identity/files/logo`)
      .then(async (response) => {
        if (cancelled) return
        if (!response.ok) {
          setLogoUrl(null)
          return
        }
        objectUrl = URL.createObjectURL(await response.blob())
        if (!cancelled) setLogoUrl(objectUrl)
      })
      .catch(() => {
        if (!cancelled) setLogoUrl(null)
      })
    return () => {
      cancelled = true
      if (objectUrl) URL.revokeObjectURL(objectUrl)
    }
  }, [entity?.brand_id])

  useEffect(() => {
    if (!logoUrl) {
      setTheme({ primary: '#3a35e0', secondary: '#6d38d6' })
      return
    }
    let cancelled = false
    const image = new Image()
    image.onload = () => {
      if (!cancelled) setTheme(paletteFrom(image))
    }
    image.src = logoUrl
    return () => {
      cancelled = true
    }
  }, [logoUrl])

  const preview = useMemo((): { paragraphs: string[]; compensation: string | null; compensationTitle?: string; dates: string | null; datesTitle?: string; requirements: string | null; documents: string | null } => {
    const payParts = [
      form.ctc.trim() !== '' ? `Your total compensation (CTC) for the year will be Rs. ${money(form.ctc)}.` : '',
      form.variable.trim() !== '' ? `The variable amount is Rs. ${money(form.variable)} per annum and depends on performance.` : '',
    ].filter(Boolean)
    const compensation = payParts.length === 0 ? null : payParts.join(' ')
    const offerCompensation = [
      form.ctc.trim() !== '' ? `Your total compensation (CTC) for the year will be Rs. ${money(form.ctc)}.` : 'Your total compensation (CTC) is the yearly amount recorded for this offer.',
      form.variable.trim() !== '' ? `The variable amount is Rs. ${money(form.variable)} per annum and depends on performance.` : 'A variable amount, where it applies, depends on performance.',
    ].join(' ')
    if (letter === 'offer_letter') {
      return {
        paragraphs: [`${company} offers you the position of ${role}.`, 'Any other benefit follows the policy for this role.'],
        compensation: offerCompensation,
        compensationTitle: 'Your compensation package',
        dates: form.joining_date ? `Your expected joining date is on or before ${start}. If you do not join by this date, this offer can be withdrawn.` : 'The joining date is agreed before you join. If you do not join by that date, this offer can be withdrawn.',
        datesTitle: 'Joining date',
        requirements: 'Before joining, you are required to sign a non-disclosure agreement.',
        documents: 'Please bring copies of your educational certificates when you join.',
      }
    }
    if (letter === 'appointment_letter') {
      return {
        paragraphs: [`${company} appoints ${who} to the position of ${role}.${codeSentence}`, 'This appointment is issued after the candidate accepts the offer. The salary structure is the pay entered on this form.'],
        compensation,
        compensationTitle: 'Salary structure',
        dates: form.joining_date ? `Employment starts on ${start}.` : null,
        datesTitle: 'Dates',
        requirements: null,
        documents: null,
      }
    }
    if (letter === 'joining_letter') {
      return {
        paragraphs: [`${company} records that ${who} is joining as ${role} after accepting the offer.${codeSentence}`, 'This joining step creates the employee record. The salary structure below is the pay entered on this form.'],
        compensation,
        compensationTitle: 'Salary structure',
        dates: form.joining_date ? `The joining date on this letter is ${start}.` : null,
        datesTitle: 'Dates',
        requirements: null,
        documents: 'Please bring copies of your educational certificates when you join.',
      }
    }
    if (letter === 'confirmation_letter') {
      return {
        paragraphs: [`${company} confirms the employment of ${who} as ${role} after the offer was accepted and the person joined.${codeSentence}`, 'The salary structure below is the pay entered for this person.'],
        compensation,
        compensationTitle: 'Salary structure',
        dates: form.joining_date ? `The joining date on this letter is ${start}.` : null,
        datesTitle: 'Dates',
        requirements: null,
        documents: null,
      }
    }
    if (letter === 'internship_offer') {
      return {
        paragraphs: [`${company} offers ${who} an internship for ${role}.${codeSentence}`, 'This internship is not a confirmation of employment after it ends.'],
        compensation: `The internship stipend entered on this letter is Rs. ${money(form.ctc)}.`,
        dates: `The internship start date is ${start} and the end date is ${finish}.`,
        requirements: null,
        documents: null,
      }
    }
    if (letter === 'experience_certificate') {
      return { paragraphs: [`${company} confirms that ${who} was associated with the organisation as ${role}.${codeSentence}`, `The saved record runs from ${start} to ${finish}.`], compensation: null, dates: null, requirements: null, documents: null }
    }
    if (letter === 'relieving_letter') {
      return { paragraphs: [`${company} records that ${who}, ${role}, is relieved from employment.${codeSentence}`, `The employment on this letter runs from ${start}. The last working date is ${finish}.`], compensation: null, dates: null, requirements: null, documents: null }
    }
    if (letter === 'interview_call_letter') {
      return { paragraphs: [`${company} invites ${who} to an interview for ${role}.`, 'The interview date, time, and place are not entered on this letter. Share those separately once they are fixed.'], compensation: null, dates: null, requirements: null, documents: null }
    }
    if (letter === 'warning_letter') {
      return { paragraphs: [`${company} issues this warning to ${who} for the role of ${role}.${codeSentence}`, 'The specific incident is not entered here. Add it in a saved template before this letter is used for a real warning.'], compensation: null, dates: null, requirements: null, documents: null }
    }
    return {
      paragraphs: [`${company} issues this ${label.toLowerCase()} to ${who} for the role of ${role}.${codeSentence}`, 'It uses only the details entered on this form. Details that were left blank are not invented.'],
      compensation: payLetters.has(letter) ? compensation : null,
      dates: endDateLetters.has(letter) ? `The date entered on this letter is ${start}${form.end_date ? `, and the closing date is ${finish}.` : '.'}` : null,
      requirements: null,
      documents: null,
    }
  }, [codeSentence, company, finish, form.ctc, form.end_date, form.joining_date, form.variable, label, letter, role, start, who])

  function update(field: keyof typeof form, value: string) {
    setForm((current) => ({ ...current, [field]: value }))
  }

  function payload() {
    const parts = person.split(/\s+/).filter(Boolean)
    const family = parts.length > 1 ? parts[parts.length - 1] : person
    const given = parts.length > 1 ? parts.slice(0, -1).join(' ') : person
    return {
      document_type: letter,
      legal_entity_id: Number(selectedEntity),
      candidate_id: candidateId,
      given_name: given,
      family_name: family,
      address: form.address || null,
      email: form.email || null,
      phone: form.phone || null,
      department: form.department || null,
      letter_date: form.letter_date || null,
      designation: form.designation,
      employee_code: form.employee_code || null,
      joining_date: form.joining_date || null,
      end_date: form.end_date || null,
      ctc: amountPayload(form.ctc),
      variable: amountPayload(form.variable),
    }
  }

  function payErrors(): string | null {
    const checks: Array<[string, string]> = [['CTC total', form.ctc], ['CTC variable', form.variable]]
    const messages = checks.flatMap(([label, value]) => (rupees(value) !== null && Number.isNaN(rupees(value)) ? [`${label} must be a number, such as 200000 or 2 Lac.`] : []))
    return messages[0] ?? null
  }

  async function downloadPreview() {
    const payError = payErrors()
    if (payError) {
      setError(payError)
      return
    }
    setError(null)
    const response = await apiFetch('/api/v1/letter-studio/preview', { method: 'POST', body: JSON.stringify(payload()) })
    if (!response.ok) {
      const body = await response.json().catch(() => null) as { message?: string } | null
      setError(body?.message || 'The preview PDF could not be downloaded.')
      return
    }
    const fileName = `${(person || 'letter').replace(/\s+/g, '-')}-${letter}.pdf`
    const url = URL.createObjectURL(await response.blob())
    const link = document.createElement('a')
    link.href = url
    link.download = fileName
    link.click()
    URL.revokeObjectURL(url)
    setSavedFile(fileName)
    notify(`${fileName} is in this computer’s Downloads folder. The letter kept in OneFlo is under Saved letters.`, 'success')
  }

  async function downloadIssued(item: IssuedLetter) {
    const response = await apiFetch(`/api/v1/issued-documents/${item.id}/pdf`)
    if (!response.ok) {
      setError('This saved letter could not be downloaded.')
      return
    }
    const url = URL.createObjectURL(await response.blob())
    const link = document.createElement('a')
    link.href = url
    link.download = `${item.number}.pdf`
    link.click()
    URL.revokeObjectURL(url)
  }

  async function issue(allowPipeline = false) {
    const payError = payErrors()
    if (payError) {
      setError(payError)
      return
    }
    setPending(true)
    setError(null)
    try {
      const result = await apiData<{ candidate_id: number | null; employee_id: number | null; number: string }>('/api/v1/letter-studio', { method: 'POST', body: JSON.stringify({ ...payload(), allow_pipeline: allowPipeline }) })
      if (result.candidate_id) setCandidateId(result.candidate_id)
      setEmployeeId(result.employee_id)
      setIssued(result.number)
      notify(`${label} issued as ${result.number}. It stays in Saved letters on this page.`, 'success')
      await queryClient.invalidateQueries({ queryKey: ['issued-documents', organizationId] })
      if (letter === 'offer_letter') setLetter('appointment_letter')
      else if (letter === 'appointment_letter') {
        setAppointmentIssued(true)
        setLetter('joining_letter')
      } else if (letter === 'joining_letter') setLetter('confirmation_letter')
    } catch (caught) {
      if (caught instanceof ApiError && caught.errors.pipeline && !allowPipeline) {
        setPending(false)
        if (window.confirm(`${caught.errors.pipeline[0]}\n\nIssue it here anyway? This can create a second candidate or employee record.`)) await issue(true)
        else setError(caught.errors.pipeline[0])
        return
      }
      setError(caught instanceof ApiError ? caught.message : 'The letter could not be issued.')
    } finally {
      setPending(false)
    }
  }

  if (organizationId === null) return <EmptyState title="Select a business" description="Letters stay inside the active business." />

  const stepLocked = (letter === 'appointment_letter' && candidateId === null) || (letter === 'joining_letter' && !appointmentIssued) || (letter === 'confirmation_letter' && employeeId === null)
  const showStart = letter !== 'interview_call_letter'
  const showPay = payLetters.has(letter) || letter === 'internship_offer'
  const showEnd = endDateLetters.has(letter)

  return (
    <div className="flex flex-col gap-5">
      <PageHeader title="Letters" description="Enter the details first. Issue the offer to the candidate. After the candidate accepts, issue the appointment with the salary structure, then the joining letter. Joining creates the employee. Confirmation comes after joining." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Letters' }]} />
      <div className="flex flex-wrap gap-2 text-sm">
        {([
          ['offer_letter', '1. Offer'],
          ['appointment_letter', '2. Appointment'],
          ['joining_letter', '3. Joining'],
          ['confirmation_letter', '4. Confirmation'],
        ] as const).map(([id, text]) => (
          <button key={id} type="button" className={`rounded-full px-3 py-1 ${letter === id ? 'bg-[#3a35e0] text-white' : 'bg-white text-[#1c1433]'}`} onClick={() => setLetter(id)}>{text}</button>
        ))}
      </div>
      <div className="grid items-start gap-6 lg:grid-cols-[minmax(16rem,22rem)_minmax(0,1fr)]">
        <form className="flex flex-col gap-3 rounded-2xl border border-[#eadfd4] bg-[#f7f1e8] p-4 lg:sticky lg:top-4 lg:max-h-[calc(100svh-6rem)] lg:overflow-y-auto" onSubmit={(event) => { event.preventDefault(); void issue() }}>
          <Field label="Letter" htmlFor="letter-type">
            <Select id="letter-type" value={letter} onChange={(event) => setLetter(event.target.value as LetterId)} options={letters.map((item) => ({ value: item.id, label: item.label }))} />
          </Field>
          <p className="text-xs leading-5 text-[#6b728a]">Company name, address, phone, website, and email come from <Link className="font-medium text-[#3a35e0]" to="/documents">Documents → Company profile</Link>.</p>
          {letter !== 'offer_letter' ? <Field label="Letter date" htmlFor="letter-date"><Input id="letter-date" type="date" value={form.letter_date} onChange={(event) => update('letter_date', event.target.value)} /></Field> : null}
          <Field label="Employee name" htmlFor="letter-name"><Input id="letter-name" value={form.employee_name} onChange={(event) => update('employee_name', event.target.value)} required /></Field>
          <Field label="Employee address" htmlFor="letter-address"><Input id="letter-address" value={form.address} onChange={(event) => update('address', event.target.value)} required={letter === 'offer_letter'} /></Field>
          {letter !== 'offer_letter' ? <Field label="Email" htmlFor="letter-email"><Input id="letter-email" type="email" value={form.email} onChange={(event) => update('email', event.target.value)} /></Field> : null}
          {letter !== 'offer_letter' ? <Field label="Phone" htmlFor="letter-phone"><Input id="letter-phone" value={form.phone} onChange={(event) => update('phone', event.target.value)} /></Field> : null}
          {letter !== 'offer_letter' ? <Field label="Department" htmlFor="letter-department"><Input id="letter-department" value={form.department} onChange={(event) => update('department', event.target.value)} /></Field> : null}
          <Field label="Employee designation" htmlFor="letter-role"><Input id="letter-role" value={form.designation} onChange={(event) => update('designation', event.target.value)} required /></Field>
          {letter !== 'offer_letter' && letter !== 'interview_call_letter' ? <Field label="Employee code" htmlFor="letter-code"><Input id="letter-code" value={form.employee_code} onChange={(event) => update('employee_code', event.target.value)} /></Field> : null}
          {showStart ? <Field label={letter === 'relieving_letter' || letter === 'experience_certificate' ? 'Start date' : 'Joining date'} htmlFor="letter-join"><Input id="letter-join" type="date" value={form.joining_date} onChange={(event) => update('joining_date', event.target.value)} required={hireLetters.has(letter)} /></Field> : null}
          {showEnd ? <Field label={letter === 'relieving_letter' ? 'Last working date' : 'End date'} htmlFor="letter-end"><Input id="letter-end" type="date" value={form.end_date} onChange={(event) => update('end_date', event.target.value)} /></Field> : null}
          {showPay ? <Field label={letter === 'internship_offer' ? 'Stipend' : 'CTC (total)'} htmlFor="letter-ctc"><Input id="letter-ctc" inputMode="decimal" placeholder="200000 or 2 Lac" value={form.ctc} onChange={(event) => update('ctc', event.target.value)} /></Field> : null}
          {payLetters.has(letter) ? <Field label="CTC (variable)" htmlFor="letter-variable"><Input id="letter-variable" inputMode="decimal" placeholder="50000 or 3 Lac" value={form.variable} onChange={(event) => update('variable', event.target.value)} /></Field> : null}
          {error ? <p className="text-sm text-destructive">{error}</p> : null}
          {issued ? <p className="text-sm">Latest number: {issued}{employeeId ? ` · employee ${employeeId}` : ''}</p> : null}
          {savedFile ? <p className="text-sm">A copy was saved on this computer in Downloads as {savedFile}. The letter you keep is under Saved letters.</p> : null}
          <div className="flex flex-col gap-2">
            <Button type="button" variant="outline" onClick={() => void downloadPreview()}>Download PDF</Button>
            {stepLocked ? <p className="text-sm text-[#6b728a]">{letter === 'confirmation_letter' ? 'Confirmation is available after the joining letter.' : letter === 'joining_letter' ? 'Joining is available after the appointment letter.' : 'Appointment is available after the offer letter is accepted and issued.'}</p> : null}
            <Button type="submit" disabled={pending || stepLocked}>{pending ? 'Issuing…' : `Issue ${label}`}</Button>
          </div>
          <div className="mt-2 border-t border-[#eadfd4] pt-3">
            <p className="text-sm font-semibold">Saved letters</p>
            <p className="mt-1 text-xs text-[#6b728a]">Each issued letter stays here with the person’s name, so you can find it later.</p>
            <Input className="mt-2" value={letterSearch} placeholder="Search by name or letter" onChange={(event) => setLetterSearch(event.target.value)} />
            <div className="mt-2 flex flex-col gap-2">
              {(issuedLetters.data ?? []).length === 0 ? <p className="text-sm text-[#6b728a]">No letter has been issued yet.</p> : null}
              {(issuedLetters.data ?? []).filter((item) => {
                const kind = letters.find((entry) => entry.id === item.document_type)?.label ?? item.document_type
                return `${item.person} ${kind} ${item.number}`.toLowerCase().includes(letterSearch.trim().toLowerCase())
              }).map((item) => (
                <div key={item.id} className="rounded-lg bg-white px-2 py-2 text-sm">
                  <p className="font-semibold">{item.voided_at ? 'Cancelled' : (item.person || 'Letter')}</p>
                  <p className="text-xs text-[#6b728a]">{letters.find((entry) => entry.id === item.document_type)?.label ?? item.document_type}{item.issued_on ? ` · ${displayDate(item.issued_on)}` : ''}</p>
                  <p className="text-xs text-[#6b728a]">{item.number}</p>
                  <Button className="mt-2" type="button" variant="outline" disabled={item.voided_at !== null} onClick={() => void downloadIssued(item)}>Download</Button>
                </div>
              ))}
            </div>
          </div>
        </form>
        <article className="rounded-sm border border-[#eadfd4] bg-white px-6 pb-10 pt-6 font-serif text-[#1c1433] shadow-[0_16px_40px_rgba(28,20,51,0.06)] sm:px-10">
          <div className="flex items-center justify-between gap-6">
            {logoUrl ? <img src={logoUrl} alt="" className="max-h-14 w-auto max-w-[12.5rem] object-contain object-left" /> : <div className="inline-flex border border-[#1c1433] px-3 py-2 text-lg font-bold tracking-[0.2em] text-[#1c1433]">{mark(company)}</div>}
            <div className="text-right font-sans text-xs leading-5 text-[#4b4f63]">
              <p className="text-sm font-bold text-[#3a35e0]">{company}</p>
              {address ? <p>{address}</p> : null}
              {[profile.data?.contact_phone, profile.data?.contact_email, profile.data?.website].some(Boolean) ? <p>{[profile.data?.contact_phone, profile.data?.contact_email, profile.data?.website].filter(Boolean).join('  |  ')}</p> : null}
            </div>
          </div>
          <div className="mt-3 h-[3px] bg-[#3a35e0]" />
          <div className="mt-0.5 h-px bg-[#6d38d6]" />
          <div className="mt-5 flex items-end justify-between gap-4">
            <h2 className="font-sans text-lg font-semibold uppercase tracking-wider text-[#1c1433]">{label}</h2>
            <p className="font-sans text-xs">Date: {displayDate(form.letter_date)}</p>
          </div>
          {person ? <p className="mt-6 text-[15px] font-semibold leading-7">{person}</p> : null}
          {form.address ? <p className="text-[15px] leading-7">{form.address}</p> : null}
          {form.designation ? <p className="text-[15px] leading-7">Designation: {form.designation}</p> : null}
          {form.department ? <p className="text-[15px] leading-7">Department: {form.department}</p> : null}
          {form.email || form.phone ? <p className="text-[15px] leading-7">{[form.email, form.phone].filter(Boolean).join(' · ')}</p> : null}
          {form.employee_code ? <p className="text-[15px] leading-7">Employee code: {form.employee_code}</p> : null}
          <p className="mt-6 text-[15px] font-semibold leading-7">Re: {letter === 'offer_letter' ? 'Offer of employment' : label}</p>
          <p className="mt-4 text-[15px] leading-7">Dear {person || 'Sir or Madam'},</p>
          {preview.paragraphs.map((paragraph) => <p key={paragraph} className="mt-4 text-[15px] leading-7">{paragraph}</p>)}
          {preview.compensation ? <><h3 className="mt-6 text-base font-semibold">{preview.compensationTitle ?? 'Compensation'}</h3><p className="mt-2 text-[15px] leading-7">{preview.compensation}</p></> : null}
          {preview.dates ? <><h3 className="mt-6 text-base font-semibold">{preview.datesTitle ?? 'Dates'}</h3><p className="mt-2 text-[15px] leading-7">{preview.dates}</p></> : null}
          {preview.requirements ? <><h3 className="mt-6 text-base font-semibold">Pre-employment requirements</h3><p className="mt-2 text-[15px] leading-7">{preview.requirements}</p></> : null}
          {preview.documents ? <><h3 className="mt-6 text-base font-semibold">Documents required</h3><p className="mt-2 text-[15px] leading-7">{preview.documents}</p></> : null}
          <p className="mt-8 text-[15px] leading-7">Yours truly,</p>
          <p className="mt-6 text-[15px] font-semibold leading-7">{signatory?.name || account?.name}</p>
          {signatory?.designation ? <p className="text-[15px] leading-7">{signatory.designation}</p> : null}
          {profile.data?.contact_email || profile.data?.contact_phone ? <p className="text-[15px] leading-7">{profile.data.contact_email || profile.data.contact_phone}</p> : null}
        </article>
      </div>
    </div>
  )
}
