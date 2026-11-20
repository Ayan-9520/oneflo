import { useQuery, useQueryClient } from '@tanstack/react-query'
import {
  ArrowRight,
  Award,
  BadgeCheck,
  BadgeIndianRupee,
  Briefcase,
  Building2,
  CalendarCheck,
  CheckCircle2,
  Circle,
  Clock,
  Download,
  FileText,
  Gauge,
  GraduationCap,
  Hourglass,
  Link2,
  LogOut,
  Mail,
  MapPin,
  Phone,
  Search,
  Send,
  ShieldCheck,
  Sparkles,
  TrendingUp,
  UserRound,
  Wallet,
  type LucideIcon,
} from 'lucide-react'
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'

import { CareersShell } from '@/features/recruitment/careers-page'
import { apiFetch } from '@/lib/api'

export type CandidateProfile = {
  id: number
  full_name: string
  email: string | null
  phone: string | null
  avatar_url: string | null
  headline: string | null
  location: string | null
  experience_years: number | null
  current_company: string | null
  current_designation: string | null
  current_salary: string | null
  expected_salary: string | null
  notice_period: string | null
  qualification: string | null
  skills: string | null
  email_verified: boolean
  phone_verified: boolean
  linked: { google: boolean; linkedin: boolean }
}
type MyOffer = { id: number; designation: string; compensation_note: string; status: 'sent' | 'accepted' | 'declined'; job_title: string | null; company: string | null; sent_on: string | null; answered_on: string | null }
type MyLetter = { id: number; type: string; label: string; number: string; company: string | null; issued_on: string }
type MyApplication = { id: number; job_title: string | null; job_slug: string | null; company: string | null; stage: string; applied_on: string | null }

const tokenKey = 'oneflo.candidate_token'
const returnKey = 'oneflo.candidate_return'

export function candidateToken() {
  return typeof window === 'undefined' ? null : window.localStorage.getItem(tokenKey)
}

export function candidateHeaders(): Record<string, string> {
  const token = candidateToken()
  return token ? { Authorization: `Bearer ${token}` } : {}
}

async function candidateRequest<T>(path: string, init: RequestInit = {}): Promise<T> {
  const response = await apiFetch(path, { ...init, headers: { ...candidateHeaders(), ...(init.headers ?? {}) } })
  const payload = await response.json().catch(() => ({}))
  if (response.status === 401) window.localStorage.removeItem(tokenKey)
  if (!response.ok) {
    const fieldMessage = payload.errors ? Object.values(payload.errors as Record<string, string[]>)[0]?.[0] : null
    throw new Error(response.status === 429 ? 'Too many attempts. Please wait a minute and try again.' : fieldMessage ?? payload.message ?? 'Something went wrong. Please try again.')
  }
  return payload.data as T
}

export function useCandidate() {
  const token = candidateToken()
  return useQuery({
    queryKey: ['candidate-me', token],
    enabled: token !== null,
    retry: false,
    queryFn: () => candidateRequest<CandidateProfile>('/api/v1/candidate/me'),
  })
}

function takeReturn() {
  const path = window.sessionStorage.getItem(returnKey)
  window.sessionStorage.removeItem(returnKey)
  return path && path.startsWith('/careers') ? path : '/careers/profile'
}

function Card({ children }: { children: ReactNode }) {
  return <div className="mx-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_16px_40px_rgb(15_23_42_/_0.06)] sm:p-8">{children}</div>
}

export function CandidateOAuthCallback() {
  const { provider = '' } = useParams()
  const [search] = useSearchParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [error, setError] = useState<string | null>(null)
  const started = useRef(false)

  useEffect(() => {
    if (started.current) return
    started.current = true
    const code = search.get('code')
    const state = search.get('state')
    if (!code || !state) {
      setError(search.get('error_description') ?? 'Sign-in was cancelled.')
      return
    }
    candidateRequest<{ token: string }>(`/api/v1/candidate/auth/${provider}/callback`, { method: 'POST', body: JSON.stringify({ code, state }) })
      .then(async (session) => {
        window.localStorage.setItem(tokenKey, session.token)
        await queryClient.invalidateQueries({ queryKey: ['candidate-me'] })
        navigate(takeReturn(), { replace: true })
      })
      .catch((caught: unknown) => setError(caught instanceof Error ? caught.message : 'Sign-in failed.'))
  }, [navigate, provider, queryClient, search])

  return (
    <CareersShell>
      <main className="px-4 py-20">
        <Card>
          {error ? (
            <>
              <h1 className="text-xl font-bold">Sign-in did not complete</h1>
              <p className="mt-2 text-sm text-slate-600">{error}</p>
              <Link to="/careers/login" className="mt-5 inline-flex rounded-lg bg-[#3a35e0] px-4 py-2 text-sm font-semibold text-white">Try again</Link>
            </>
          ) : <p className="text-sm text-slate-600">Signing you in…</p>}
        </Card>
      </main>
    </CareersShell>
  )
}

type ProfileField = { name: keyof CandidateProfile; label: string; type?: string; placeholder?: string; icon: LucideIcon }

const profileSections: Array<{ title: string; text: string; icon: LucideIcon; gradient: string; fields: ProfileField[] }> = [
  {
    title: 'Basic details', text: 'How recruiters see you at a glance.', icon: UserRound, gradient: 'from-[#3a35e0] to-[#5fa8ff]',
    fields: [
      { name: 'full_name', label: 'Full name', icon: UserRound },
      { name: 'headline', label: 'Headline', placeholder: 'e.g. Sales executive with 3 years in fintech', icon: Sparkles },
      { name: 'location', label: 'Current location', placeholder: 'City', icon: MapPin },
      { name: 'qualification', label: 'Highest qualification', placeholder: 'e.g. B.Com, MBA', icon: GraduationCap },
    ],
  },
  {
    title: 'Work experience', text: 'Your current role and availability.', icon: Briefcase, gradient: 'from-[#8b5cf6] to-[#d946ef]',
    fields: [
      { name: 'experience_years', label: 'Total experience (years)', type: 'number', placeholder: '0', icon: Briefcase },
      { name: 'notice_period', label: 'Notice period', placeholder: 'e.g. Immediate, 30 days', icon: Clock },
      { name: 'current_company', label: 'Current company', icon: Building2 },
      { name: 'current_designation', label: 'Current designation', icon: BadgeCheck },
    ],
  },
  {
    title: 'Salary', text: 'Shared only with companies you apply to.', icon: BadgeIndianRupee, gradient: 'from-[#10b981] to-[#06b6d4]',
    fields: [
      { name: 'current_salary', label: 'Current salary', placeholder: 'e.g. 3.5 LPA', icon: Wallet },
      { name: 'expected_salary', label: 'Expected salary', placeholder: 'e.g. 4.5 LPA', icon: TrendingUp },
    ],
  },
]

const strengthFields: Array<[keyof CandidateProfile, string]> = [
  ['headline', 'Add a headline'], ['location', 'Add your location'], ['experience_years', 'Add total experience'], ['current_designation', 'Add current designation'],
  ['current_company', 'Add current company'], ['notice_period', 'Add notice period'], ['qualification', 'Add qualification'], ['expected_salary', 'Add expected salary'], ['skills', 'Add your skills'],
]

const stageLabels: Record<string, string> = { applied: 'Applied', screening: 'Under review', interview: 'Interview', offer: 'Offer', hired: 'Hired', rejected: 'Not selected', withdrawn: 'Withdrawn' }
const stageStyles: Record<string, string> = {
  applied: 'bg-blue-50 text-blue-700', screening: 'bg-amber-50 text-amber-700', interview: 'bg-violet-50 text-violet-700',
  offer: 'bg-emerald-50 text-emerald-700', hired: 'bg-emerald-100 text-emerald-800', rejected: 'bg-rose-50 text-rose-700', withdrawn: 'bg-slate-100 text-slate-600',
}
const stageOrder = ['applied', 'screening', 'interview', 'offer', 'hired']

const fieldClass = 'h-12 w-full rounded-xl border border-slate-200 bg-slate-50/70 pl-11 pr-4 text-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-[#8b5cf6] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#8b5cf6]/15'

function MyOffers() {
  const queryClient = useQueryClient()
  const offers = useQuery({ queryKey: ['candidate-offers', candidateToken()], queryFn: () => candidateRequest<MyOffer[]>('/api/v1/candidate/me/offers') })
  const letters = useQuery({ queryKey: ['candidate-letters', candidateToken()], queryFn: () => candidateRequest<MyLetter[]>('/api/v1/candidate/me/letters') })
  const [busy, setBusy] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)

  async function respond(offer: MyOffer, decision: 'accept' | 'decline') {
    if (decision === 'decline' && !window.confirm(`Decline the ${offer.designation} offer from ${offer.company}? This cannot be undone.`)) return
    setBusy(offer.id)
    setError(null)
    try {
      await candidateRequest(`/api/v1/candidate/me/offers/${offer.id}/${decision}`, { method: 'POST' })
      await queryClient.invalidateQueries({ queryKey: ['candidate-offers'] })
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Your answer could not be saved.')
    } finally {
      setBusy(null)
    }
  }

  async function letter(offer: MyOffer) {
    setError(null)
    const response = await apiFetch(`/api/v1/candidate/me/offers/${offer.id}/letter`, { headers: candidateHeaders() })
    if (!response.ok) { setError('The offer letter could not be downloaded.'); return }
    const url = URL.createObjectURL(await response.blob())
    const link = document.createElement('a')
    link.href = url
    link.download = `offer-letter-${offer.id}.pdf`
    link.click()
    URL.revokeObjectURL(url)
  }

  async function issued(item: MyLetter) {
    setError(null)
    const response = await apiFetch(`/api/v1/candidate/me/letters/${item.id}/pdf`, { headers: candidateHeaders() })
    if (!response.ok) { setError('The letter could not be downloaded.'); return }
    const url = URL.createObjectURL(await response.blob())
    const link = document.createElement('a')
    link.href = url
    link.download = `${item.number}.pdf`
    link.click()
    URL.revokeObjectURL(url)
  }

  const rows = offers.data ?? []
  const issuedRows = letters.data ?? []
  if (rows.length === 0 && issuedRows.length === 0) return null

  return (
    <section className="mt-8 overflow-hidden rounded-3xl border border-[#ffd1e8] bg-gradient-to-br from-[#fff3f9] via-white to-[#eef4ff] p-6 shadow-[0_18px_50px_rgb(226_61_154_/_0.12)] sm:p-7">
      <div className="flex items-center gap-3">
        <span className="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-[#ff3d9a] to-[#6d38d6] text-white shadow-md"><Award className="size-5" /></span>
        <div><h2 className="text-lg font-bold text-slate-900">My offers</h2><p className="text-sm text-slate-500">Read your offer letter, then accept or decline it here.</p></div>
      </div>
      {error ? <p role="alert" className="mt-4 rounded-2xl bg-rose-50 p-3 text-sm text-rose-700">{error}</p> : null}
      <ul className="mt-5 grid gap-4 md:grid-cols-2">
        {rows.map((offer) => (
          <li key={offer.id} className="rounded-2xl border border-white bg-white/90 p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <p className="truncate text-base font-bold text-slate-900">{offer.designation}</p>
                <p className="mt-0.5 text-sm text-slate-500">{offer.company}{offer.sent_on ? ` · Offered ${new Date(offer.sent_on).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}` : ''}</p>
              </div>
              <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-bold ${offer.status === 'accepted' ? 'bg-emerald-50 text-emerald-700' : offer.status === 'declined' ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-700'}`}>{offer.status === 'sent' ? 'Awaiting your answer' : offer.status === 'accepted' ? 'Accepted' : 'Declined'}</span>
            </div>
            <p className="mt-3 flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700"><BadgeIndianRupee className="size-4 text-[#6d38d6]" />{offer.compensation_note}</p>
            {offer.status === 'accepted' ? <p className="mt-3 text-xs text-emerald-700">Congratulations! HR will contact you with your joining date and onboarding steps.</p> : null}
            <div className="mt-4 flex flex-wrap gap-2">
              <button type="button" onClick={() => void letter(offer)} className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:border-[#c9b8ff] hover:text-[#3a35e0]"><Download className="size-4" />Offer letter</button>
              {offer.status === 'sent' ? (
                <>
                  <button type="button" disabled={busy === offer.id} onClick={() => void respond(offer, 'accept')} className="inline-flex h-10 items-center gap-2 rounded-xl bg-gradient-to-r from-[#10b981] to-[#06b6d4] px-4 text-sm font-semibold text-white shadow-md hover:opacity-95 disabled:opacity-60"><CheckCircle2 className="size-4" />Accept offer</button>
                  <button type="button" disabled={busy === offer.id} onClick={() => void respond(offer, 'decline')} className="inline-flex h-10 items-center gap-2 rounded-xl border border-rose-200 bg-white px-4 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-60">Decline</button>
                </>
              ) : null}
            </div>
          </li>
        ))}
      </ul>
      {issuedRows.length > 0 ? (
        <div className="mt-6">
          <h3 className="flex items-center gap-2 text-sm font-bold text-slate-900"><FileText className="size-4 text-[#6d38d6]" />My letters</h3>
          <ul className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {issuedRows.map((item) => (
              <li key={item.id}>
                <button type="button" onClick={() => void issued(item)} className="flex w-full items-center justify-between gap-3 rounded-2xl border border-white bg-white/90 p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                  <span className="min-w-0">
                    <span className="block truncate text-sm font-bold text-slate-900">{item.label}</span>
                    <span className="block truncate text-xs text-slate-500">{item.company} · {item.number}</span>
                  </span>
                  <Download className="size-4 shrink-0 text-[#6d38d6]" />
                </button>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </section>
  )
}

export function CandidateProfilePage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const me = useCandidate()
  const applications = useQuery({ queryKey: ['candidate-applications', candidateToken()], enabled: me.isSuccess, queryFn: () => candidateRequest<MyApplication[]>('/api/v1/candidate/me/applications') })
  const [saved, setSaved] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [skillsDraft, setSkillsDraft] = useState<string | null>(null)

  useEffect(() => {
    if (!candidateToken() || me.isError) navigate('/careers/login?next=/careers/profile', { replace: true })
  }, [me.isError, navigate])

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSaved(false)
    setSaving(true)
    const body = Object.fromEntries(new FormData(event.currentTarget).entries())
    const payload = Object.fromEntries(Object.entries(body).map(([key, value]) => [key, String(value).trim() === '' ? null : key === 'experience_years' ? Number(value) : String(value).trim()]))
    try {
      const profile = await candidateRequest<CandidateProfile>('/api/v1/candidate/me', { method: 'PUT', body: JSON.stringify(payload) })
      queryClient.setQueryData(['candidate-me', candidateToken()], profile)
      setSaved(true)
      setTimeout(() => setSaved(false), 3000)
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'The profile could not be saved.')
    } finally {
      setSaving(false)
    }
  }

  async function logout() {
    await candidateRequest('/api/v1/candidate/logout', { method: 'POST' }).catch(() => null)
    window.localStorage.removeItem(tokenKey)
    queryClient.removeQueries({ queryKey: ['candidate-me'] })
    navigate('/careers', { replace: true })
  }

  const profile = me.data
  const rows = applications.data ?? []
  const missing = profile ? strengthFields.filter(([key]) => profile[key] === null || profile[key] === '') : []
  const strength = profile ? Math.round(((strengthFields.length - missing.length + 1) / (strengthFields.length + 1)) * 100) : 0
  const active = rows.filter((row) => !['rejected', 'withdrawn', 'hired'].includes(row.stage)).length
  const interviews = rows.filter((row) => ['interview', 'offer', 'hired'].includes(row.stage)).length
  const skills = (skillsDraft ?? profile?.skills ?? '').split(/[,\n]/).map((skill) => skill.trim()).filter(Boolean)
  const subtitle = profile ? [profile.current_designation, profile.current_company ? `at ${profile.current_company}` : null].filter(Boolean).join(' ') : ''

  return (
    <CareersShell>
      <section className="relative h-48 overflow-hidden bg-gradient-to-r from-[#ffd1e8] via-[#e9dcff] to-[#cfe0ff] sm:h-56">
        <div className="pointer-events-none absolute -left-20 -top-24 size-80 rounded-full bg-[#ff8fc7]/40 blur-3xl" />
        <div className="pointer-events-none absolute -bottom-32 right-0 size-96 rounded-full bg-[#8fb8ff]/50 blur-3xl" />
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(58_53_224_/_0.10)_1px,transparent_1px)] [background-size:22px_22px]" />
      </section>

      <main className="relative mx-auto -mt-28 max-w-6xl px-4 pb-16 sm:px-6">
        {!profile ? <div className="h-64 animate-pulse rounded-3xl bg-white" /> : (
          <>
            <div className="rounded-3xl border border-white bg-white/90 p-6 shadow-[0_24px_60px_rgb(109_56_214_/_0.12)] backdrop-blur sm:p-8">
              <div className="flex flex-col gap-6 md:flex-row md:items-center">
                <div className="relative shrink-0">
                  <div className="rounded-full bg-gradient-to-br from-[#ff3d9a] via-[#a855f7] to-[#3a35e0] p-1 shadow-lg">
                    {profile.avatar_url
                      ? <img src={profile.avatar_url} alt="" referrerPolicy="no-referrer" className="size-24 rounded-full border-4 border-white object-cover" />
                      : <span className="flex size-24 items-center justify-center rounded-full border-4 border-white bg-gradient-to-br from-[#eef0ff] to-[#fdeaf4] text-4xl font-extrabold text-[#3a35e0]">{profile.full_name.slice(0, 1).toUpperCase()}</span>}
                  </div>
                  {profile.email_verified || profile.phone_verified ? <span className="absolute bottom-1 right-1 flex size-7 items-center justify-center rounded-full border-2 border-white bg-emerald-500 text-white" title="Verified"><BadgeCheck className="size-4" /></span> : null}
                </div>
                <div className="min-w-0 flex-1">
                  <h1 className="text-3xl font-extrabold tracking-tight text-slate-950">{profile.full_name}</h1>
                  <p className="mt-1 text-base text-slate-600">{profile.headline || subtitle || <span className="italic text-slate-400">Add a headline so recruiters know what you do</span>}</p>
                  <div className="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                    {profile.location ? <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-slate-700"><MapPin className="size-3.5" />{profile.location}</span> : null}
                    {profile.experience_years != null ? <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-slate-700"><Briefcase className="size-3.5" />{profile.experience_years} {profile.experience_years === 1 ? 'year' : 'years'} experience</span> : null}
                    {profile.email ? <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-slate-700"><Mail className="size-3.5" />{profile.email}</span> : null}
                    {profile.phone ? <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-slate-700"><Phone className="size-3.5" />{profile.phone}</span> : null}
                  </div>
                </div>
                <div className="flex shrink-0 gap-2">
                  <Link to="/careers#jobs" className="inline-flex h-11 items-center gap-2 rounded-xl bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] px-5 text-sm font-semibold text-white shadow-lg shadow-[#6d38d6]/25 hover:opacity-95"><Search className="size-4" />Find jobs</Link>
                  <button type="button" onClick={() => void logout()} className="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700"><LogOut className="size-4" />Sign out</button>
                </div>
              </div>

              <div className="mt-6 grid grid-cols-2 gap-3 border-t border-slate-100 pt-6 md:grid-cols-4">
                {([
                  [Gauge, 'Profile strength', `${strength}%`, 'from-[#3a35e0] to-[#5fa8ff]'],
                  [Send, 'Applications', String(rows.length), 'from-[#8b5cf6] to-[#d946ef]'],
                  [Hourglass, 'In progress', String(active), 'from-[#f59e0b] to-[#ef4444]'],
                  [CalendarCheck, 'Interviews & offers', String(interviews), 'from-[#10b981] to-[#06b6d4]'],
                ] as Array<[LucideIcon, string, string, string]>).map(([Icon, label, value, gradient]) => (
                  <div key={label} className="flex items-center gap-3 rounded-2xl bg-slate-50 p-4">
                    <span className={`flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ${gradient} text-white shadow-md`}><Icon className="size-5" /></span>
                    <div><p className="text-2xl font-extrabold leading-none text-slate-900">{value}</p><p className="mt-1 text-xs font-medium text-slate-500">{label}</p></div>
                  </div>
                ))}
              </div>
            </div>

            <MyOffers />

            <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_360px]">
              <form onSubmit={(event) => void save(event)} className="space-y-6">
                {profileSections.map((section) => (
                  <section key={section.title} className="rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_10px_30px_rgb(15_23_42_/_0.04)] sm:p-7">
                    <div className="flex items-center gap-3">
                      <span className={`flex size-11 items-center justify-center rounded-xl bg-gradient-to-br ${section.gradient} text-white shadow-md`}><section.icon className="size-5" /></span>
                      <div><h2 className="text-lg font-bold text-slate-900">{section.title}</h2><p className="text-sm text-slate-500">{section.text}</p></div>
                    </div>
                    <div className="mt-6 grid gap-4 sm:grid-cols-2">
                      {section.fields.map((field) => (
                        <label key={field.name} className="block text-sm">
                          <span className="font-semibold text-slate-700">{field.label}{field.name === 'full_name' ? <span className="text-[#e23d9a]"> *</span> : null}</span>
                          <span className="relative mt-1.5 block">
                            <field.icon className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input name={field.name} type={field.type ?? 'text'} min={field.type === 'number' ? 0 : undefined} required={field.name === 'full_name'} placeholder={field.placeholder} defaultValue={(profile[field.name] as string | number | null) ?? ''} className={fieldClass} />
                          </span>
                        </label>
                      ))}
                    </div>
                  </section>
                ))}

                <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_10px_30px_rgb(15_23_42_/_0.04)] sm:p-7">
                  <div className="flex items-center gap-3">
                    <span className="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-[#ff3d9a] to-[#ff8a5c] text-white shadow-md"><Sparkles className="size-5" /></span>
                    <div><h2 className="text-lg font-bold text-slate-900">Skills</h2><p className="text-sm text-slate-500">Separate skills with commas. They appear as tags below.</p></div>
                  </div>
                  <textarea name="skills" rows={3} defaultValue={profile.skills ?? ''} onChange={(event) => setSkillsDraft(event.target.value)} placeholder="e.g. Communication, MS Excel, Sales" className="mt-6 w-full rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-[#8b5cf6] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#8b5cf6]/15" />
                  {skills.length ? <div className="mt-3 flex flex-wrap gap-2">{skills.map((skill) => <span key={skill} className="rounded-full border border-[#e4dcff] bg-[#f7f3ff] px-3 py-1 text-xs font-semibold text-[#5b2fc9]">{skill}</span>)}</div> : null}
                </section>

                <div className="sticky bottom-4 z-10 flex flex-col gap-3 rounded-2xl border border-white bg-white/90 p-4 shadow-[0_16px_40px_rgb(15_23_42_/_0.12)] backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                  <p className="flex items-center gap-2 text-sm text-slate-600"><ShieldCheck className="size-4 text-emerald-600" />These details fill your job applications automatically.</p>
                  <div className="flex items-center gap-3">
                    {saved ? <span className="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600"><CheckCircle2 className="size-4" />Saved</span> : null}
                    <button type="submit" disabled={saving} className="inline-flex h-11 items-center gap-2 rounded-xl bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] px-6 text-sm font-semibold text-white shadow-lg shadow-[#6d38d6]/25 hover:opacity-95 disabled:opacity-60">{saving ? 'Saving…' : 'Save profile'}</button>
                  </div>
                </div>
                {error ? <p className="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700">{error}</p> : null}
              </form>

              <aside className="space-y-6 lg:sticky lg:top-24 lg:h-fit">
                <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_10px_30px_rgb(15_23_42_/_0.04)]">
                  <div className="bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] p-5 text-white">
                    <div className="flex items-center justify-between"><p className="font-bold">Profile strength</p><p className="text-2xl font-extrabold">{strength}%</p></div>
                    <div className="mt-3 h-2.5 overflow-hidden rounded-full bg-white/25"><div className="h-full rounded-full bg-white transition-all" style={{ width: `${strength}%` }} /></div>
                    <p className="mt-2 text-xs text-white/85">{strength === 100 ? 'Your profile is complete. Great job!' : 'A complete profile gets noticed faster by recruiters.'}</p>
                  </div>
                  {missing.length ? (
                    <ul className="space-y-2 p-5">
                      {missing.slice(0, 5).map(([key, label]) => <li key={key} className="flex items-center gap-2.5 text-sm text-slate-600"><Circle className="size-4 text-slate-300" />{label}</li>)}
                      {missing.length > 5 ? <li className="pl-6 text-xs text-slate-400">+{missing.length - 5} more</li> : null}
                    </ul>
                  ) : null}
                </section>

                <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-[0_10px_30px_rgb(15_23_42_/_0.04)]">
                  <div className="flex items-center justify-between">
                    <h2 className="flex items-center gap-2 text-base font-bold"><Send className="size-4 text-[#6d38d6]" />My applications</h2>
                    {rows.length ? <span className="rounded-full bg-[#f3ecff] px-2.5 py-0.5 text-xs font-bold text-[#6d38d6]">{rows.length}</span> : null}
                  </div>
                  {applications.isPending ? <div className="mt-4 h-20 animate-pulse rounded-2xl bg-slate-100" /> : rows.length === 0 ? (
                    <div className="mt-4 rounded-2xl border-2 border-dashed border-slate-200 p-6 text-center">
                      <span className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-[#ffe4f2] to-[#e3edff] text-[#6d38d6]"><Briefcase className="size-6" /></span>
                      <p className="mt-3 text-sm font-semibold text-slate-800">No applications yet</p>
                      <p className="mt-1 text-xs text-slate-500">Find a role you like and apply in a few clicks.</p>
                      <Link to="/careers#jobs" className="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-[#3a35e0]">Browse jobs <ArrowRight className="size-3.5" /></Link>
                    </div>
                  ) : (
                    <ul className="mt-4 space-y-3">
                      {rows.map((row) => {
                        const step = stageOrder.indexOf(row.stage)
                        return (
                          <li key={row.id} className="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                            <div className="flex items-start justify-between gap-2">
                              <div className="min-w-0">
                                {row.job_slug ? <Link to={`/careers/jobs/${row.job_slug}`} className="block truncate text-sm font-bold hover:text-[#3a35e0]">{row.job_title}</Link> : <span className="block truncate text-sm font-bold">{row.job_title ?? 'Opening'}</span>}
                                <p className="mt-0.5 text-xs text-slate-500">{row.company}{row.applied_on ? ` · Applied ${new Date(row.applied_on).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })}` : ''}</p>
                              </div>
                              <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-bold ${stageStyles[row.stage] ?? 'bg-slate-100 text-slate-600'}`}>{stageLabels[row.stage] ?? row.stage}</span>
                            </div>
                            {step >= 0 ? <div className="mt-3 flex gap-1">{stageOrder.map((stage, index) => <span key={stage} className={`h-1.5 flex-1 rounded-full ${index <= step ? 'bg-gradient-to-r from-[#3a35e0] to-[#e23d9a]' : 'bg-slate-200'}`} />)}</div> : null}
                          </li>
                        )
                      })}
                    </ul>
                  )}
                </section>

                <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-[0_10px_30px_rgb(15_23_42_/_0.04)]">
                  <h2 className="flex items-center gap-2 text-base font-bold"><ShieldCheck className="size-4 text-[#6d38d6]" />Account & security</h2>
                  <ul className="mt-4 space-y-2.5 text-sm">
                    {([
                      [Mail, 'Email', profile.email, profile.email_verified],
                      [Phone, 'Mobile', profile.phone, profile.phone_verified],
                    ] as Array<[LucideIcon, string, string | null, boolean]>).map(([Icon, label, value, verified]) => (
                      <li key={label} className="flex items-center gap-3 rounded-xl bg-slate-50 px-3 py-2.5">
                        <Icon className="size-4 shrink-0 text-slate-400" />
                        <div className="min-w-0 flex-1"><p className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{label}</p><p className="truncate font-medium text-slate-800">{value ?? 'Not added'}</p></div>
                        {value ? <span className={`rounded-full px-2 py-0.5 text-[11px] font-bold ${verified ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>{verified ? 'Verified' : 'Not verified'}</span> : null}
                      </li>
                    ))}
                    <li className="flex items-center gap-3 rounded-xl bg-slate-50 px-3 py-2.5">
                      <Link2 className="size-4 shrink-0 text-slate-400" />
                      <div className="flex-1"><p className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Linked accounts</p><p className="font-medium text-slate-800">{[profile.linked.google ? 'Google' : null, profile.linked.linkedin ? 'LinkedIn' : null].filter(Boolean).join(', ') || 'None'}</p></div>
                    </li>
                  </ul>
                  <p className="mt-4 text-xs leading-5 text-slate-500">Your profile is private. It is shared only with the companies you apply to.</p>
                </section>
              </aside>
            </div>
          </>
        )}
      </main>
    </CareersShell>
  )
}
