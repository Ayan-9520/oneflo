import { useQuery } from '@tanstack/react-query'
import {
  ArrowRight,
  BadgeCheck,
  BarChart3,
  Briefcase,
  Building2,
  Car,
  ClipboardCheck,
  Code2,
  FileSearch,
  Handshake,
  Headphones,
  Landmark,
  LayoutGrid,
  LogIn,
  MapPin,
  Megaphone,
  Palette,
  Plane,
  Scale,
  Search,
  Send,
  Settings2,
  ShieldCheck,
  TrendingUp,
  Truck,
  UserPlus,
  UserRound,
  Users,
  Wallet,
  Wrench,
  type LucideIcon,
} from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router'

import { PublicFooter, PublicHeader, useScrollToHash } from '@/features/home/public-layout'
import { apiData } from '@/lib/api'

type Job = {
  slug: string
  title: string
  company: string
  company_logo: string | null
  location: string | null
  employment_type: string | null
  category: string | null
  department: string | null
  experience_min: number | null
  experience_max: number | null
  salary_note: string | null
  headcount: number | null
  posted_on: string | null
}
export type Listing = {
  companies: Array<{ name: string; slug: string; logo_url: string | null; jobs: number }>
  categories: Array<{ name: string; jobs: number }>
  teams?: Team[]
  jobs: { data: Job[]; total: number }
}
export type Team = {
  slug: string
  name: string
  logo_url: string | null
  departments: Array<{ name: string; roles: Array<{ name: string; job_slug: string | null }> }>
}

const PREVIEW_DEPARTMENTS = 6

const DEPARTMENT_PALETTES = [
  { from: 'from-[#ff3d9a]', to: 'to-[#ff8a5c]', soft: 'bg-[#fff0f6]', ring: 'hover:border-[#ffb3d1]', chip: 'hover:border-[#ff8fc0] hover:text-[#d61f7a]', glow: 'bg-[#ff3d9a]' },
  { from: 'from-[#3a35e0]', to: 'to-[#5fa8ff]', soft: 'bg-[#eef3ff]', ring: 'hover:border-[#b3c8ff]', chip: 'hover:border-[#8fb0ff] hover:text-[#2f2ab8]', glow: 'bg-[#3a35e0]' },
  { from: 'from-[#10b981]', to: 'to-[#06b6d4]', soft: 'bg-[#ecfdf7]', ring: 'hover:border-[#9be6c9]', chip: 'hover:border-[#6fd8b0] hover:text-[#047857]', glow: 'bg-[#10b981]' },
  { from: 'from-[#8b5cf6]', to: 'to-[#d946ef]', soft: 'bg-[#f6f0ff]', ring: 'hover:border-[#d4bfff]', chip: 'hover:border-[#c4a5ff] hover:text-[#6d28d9]', glow: 'bg-[#8b5cf6]' },
  { from: 'from-[#f59e0b]', to: 'to-[#ef4444]', soft: 'bg-[#fff8eb]', ring: 'hover:border-[#fcd38a]', chip: 'hover:border-[#f8c060] hover:text-[#b45309]', glow: 'bg-[#f59e0b]' },
  { from: 'from-[#0ea5e9]', to: 'to-[#6366f1]', soft: 'bg-[#eef9ff]', ring: 'hover:border-[#9ed8f7]', chip: 'hover:border-[#7cc8f0] hover:text-[#0369a1]', glow: 'bg-[#0ea5e9]' },
] as const

const DEPARTMENT_ICONS: Array<[RegExp, LucideIcon]> = [
  [/after-sales|maintenance|repair|workshop/i, Wrench],
  [/human|\bhr\b|people|talent/i, Users],
  [/financ|account|credit|underwrit|collection|treasury/i, Wallet],
  [/\bit\b|tech|engineer|develop|software|product/i, Code2],
  [/market|brand|content|digital/i, Megaphone],
  [/support|service|customer|care/i, Headphones],
  [/sales|business develop|dealer|partner|relationship/i, Handshake],
  [/legal|compliance|risk|audit/i, Scale],
  [/design|creative/i, Palette],
  [/data|analytic|insight/i, BarChart3],
  [/logistic|supply|procure|purchase|deliver/i, Truck],
  [/vehicle|auction|inspection|automotive/i, Car],
  [/travel|tour|ticket|visa|holiday/i, Plane],
  [/property|real estate|site|construction/i, Building2],
  [/admin|operation|back office/i, Settings2],
  [/quality|process/i, ClipboardCheck],
]

function departmentIcon(name: string): LucideIcon {
  return DEPARTMENT_ICONS.find(([pattern]) => pattern.test(name))?.[1] ?? Briefcase
}

export function TeamsSection({ teams, preview = false }: { teams: Team[]; preview?: boolean }) {
  const [active, setActive] = useState(0)
  const team = teams[Math.min(active, teams.length - 1)]
  if (!team) return null
  const hiring = team.departments.flatMap((row) => row.roles).filter((role) => role.job_slug).length
  const departments = preview ? team.departments.slice(0, PREVIEW_DEPARTMENTS) : team.departments
  const hidden = team.departments.length - departments.length
  return (
    <section id="teams" className="relative scroll-mt-20 overflow-hidden bg-gradient-to-b from-white via-[#f6f5fd] to-white py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6">
        {preview
          ? <SectionTitle title="Teams & Roles" text="A glimpse of the teams at each business. Open a business to see all its departments and roles." />
          : <SectionTitle title={`Teams & roles at ${team.name}`} text="Roles marked Hiring have a live opening. For the rest, create your profile so HR can reach you when one opens." />}
        {teams.length > 1 ? (
          <div role="tablist" aria-label="Businesses" className="mt-10 flex flex-wrap justify-center gap-3">
            {teams.map((row, index) => (
              <button key={row.slug} type="button" role="tab" aria-selected={index === active} title={row.name} onClick={() => setActive(index)}
                className={`rounded-2xl border-2 bg-white p-1 transition ${index === active ? 'border-[#6d38d6] shadow-[0_12px_30px_rgb(109_56_214_/_0.2)]' : 'border-transparent opacity-70 hover:opacity-100'}`}>
                <Logo name={row.name} src={row.logo_url} size="size-12" />
              </button>
            ))}
          </div>
        ) : null}
        <p className="mt-6 text-center text-sm text-slate-600">
          <span className="font-bold text-slate-900">{team.name}</span> · {team.departments.length} departments · {team.departments.reduce((sum, row) => sum + row.roles.length, 0)} roles{hiring ? <> · <span className="font-semibold text-emerald-700">{hiring} hiring now</span></> : null}
        </p>
        <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {departments.map((department, index) => {
            const palette = DEPARTMENT_PALETTES[index % DEPARTMENT_PALETTES.length]
            const Icon = departmentIcon(department.name)
            const open = department.roles.filter((role) => role.job_slug).length
            return (
              <div key={department.name} className={`group relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_10px_30px_rgb(15_23_42_/_0.05)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_22px_45px_rgb(15_23_42_/_0.10)] ${palette.ring}`}>
                <div className={`absolute inset-x-0 top-0 h-1 bg-gradient-to-r ${palette.from} ${palette.to}`} />
                <div className={`pointer-events-none absolute -right-10 -top-10 size-32 rounded-full ${palette.glow} opacity-[0.07] blur-2xl transition group-hover:opacity-20`} />
                <Icon aria-hidden="true" className="pointer-events-none absolute -bottom-4 -right-4 size-28 text-slate-900 opacity-[0.03] transition group-hover:rotate-6 group-hover:opacity-[0.06]" />
                <div className="relative flex items-start gap-3">
                  <span className={`flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br ${palette.from} ${palette.to} text-white shadow-lg transition group-hover:scale-110 group-hover:rotate-3`}><Icon className="size-6" /></span>
                  <div className="min-w-0">
                    <h3 className="text-base font-bold leading-tight text-slate-900">{department.name}</h3>
                    <p className="mt-1 text-xs font-medium text-slate-500">
                      {department.roles.length} {department.roles.length === 1 ? 'role' : 'roles'}{open ? <> · <span className="font-semibold text-emerald-600">{open} hiring</span></> : null}
                    </p>
                  </div>
                </div>
                <div className={`relative mt-5 flex flex-wrap gap-2 rounded-2xl ${palette.soft} p-3`}>
                  {department.roles.map((role) => role.job_slug ? (
                    <Link key={role.name} to={`/careers/jobs/${role.job_slug}`} className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">
                      <span className="relative flex size-2"><span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75" /><span className="relative inline-flex size-2 rounded-full bg-emerald-500" /></span>
                      {role.name}
                    </Link>
                  ) : (
                    <Link key={role.name} to="/careers/login?mode=register" title="No opening right now. Create your profile to be considered." className={`rounded-full border border-white bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition ${palette.chip}`}>
                      {role.name}
                    </Link>
                  ))}
                </div>
              </div>
            )
          })}
        </div>
        <div className="mt-10 flex flex-wrap justify-center gap-3">
          {preview ? (
            <Link to={`/careers/${team.slug}#teams`} className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-[#6d38d6]/25">
              {hidden > 0 ? `View all ${team.departments.length} departments at ${team.name}` : `View ${team.name} page`} <ArrowRight className="size-4" />
            </Link>
          ) : (
            <a href="#jobs" className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-[#6d38d6]/25">See {team.name} openings <ArrowRight className="size-4" /></a>
          )}
          <Link to="/careers/login?mode=register" className="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold hover:border-[#3a35e0] hover:text-[#3a35e0]">Create your profile</Link>
        </div>
      </div>
    </section>
  )
}

export const categoryIcons: Record<string, LucideIcon> = {
  'Sales & Telecalling': Headphones,
  Management: Briefcase,
  Insurance: ShieldCheck,
  'Loans & Finance': Landmark,
  Automotive: Car,
  'Digital Marketing': TrendingUp,
  Marketing: Megaphone,
  Operations: Settings2,
  'Customer Support': Users,
  'Travel & Tourism': Plane,
  'Technology & IT': Code2,
  'Human Resources': UserRound,
  'Business Development': Handshake,
  Other: LayoutGrid,
}
const cardAccents = ['from-[#3a35e0] to-[#6d38d6]', 'from-[#0f766e] to-[#2dd4bf]', 'from-[#c2410c] to-[#fb923c]', 'from-[#be185d] to-[#e879f9]']
const typeLabels: Record<string, string> = { full_time: 'Full time', part_time: 'Part time', contract: 'Contract', intern: 'Internship' }
const typeStyles: Record<string, string> = { full_time: 'bg-emerald-50 text-emerald-700', part_time: 'bg-violet-50 text-violet-700', contract: 'bg-sky-50 text-sky-700', intern: 'bg-rose-50 text-rose-700' }

export const steps: Array<{ icon: LucideIcon; title: string; text: string }> = [
  { icon: UserPlus, title: 'Create your profile', text: 'Sign in with your email code and save your details once.' },
  { icon: Search, title: 'Search jobs', text: 'Filter by company, category, location, and experience.' },
  { icon: FileSearch, title: 'Read the details', text: 'See responsibilities, skills, salary when shared, and deadlines.' },
  { icon: Send, title: 'Apply in a few clicks', text: 'Your profile fills the form. Upload a resume and submit.' },
  { icon: BadgeCheck, title: 'Track your status', text: 'Follow every application from My profile.' },
  { icon: Building2, title: 'Join the team', text: 'Shortlisted candidates hear from the hiring company directly.' },
]

function experience(job: Job) {
  if (job.experience_min == null && job.experience_max == null) return null
  if (job.experience_max == null) return `${job.experience_min}+ yrs`
  return `${job.experience_min ?? 0}–${job.experience_max} yrs`
}

const logoHeights: Record<string, string> = { 'size-12': 'h-12', 'size-14': 'h-14', 'size-16': 'h-16', 'size-20': 'h-20' }

export function Logo({ name, src, size = 'size-14' }: { name: string; src: string | null; size?: string }) {
  return src
    ? <img src={src} alt={`${name} logo`} className={`${logoHeights[size] ?? 'h-14'} aspect-[5/2] shrink-0 rounded-2xl border border-slate-200 bg-white object-contain px-3 py-2`} />
    : <span className={`${size} flex items-center justify-center rounded-2xl bg-gradient-to-br from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] text-lg font-extrabold text-white`}>{name.slice(0, 2).toUpperCase()}</span>
}

export function SectionTitle({ title, text }: { title: string; text: string }) {
  return (
    <div className="mx-auto max-w-2xl text-center">
      <h2 className="text-3xl font-extrabold tracking-tight sm:text-4xl">{title}</h2>
      <p className="mt-3 text-slate-600">{text}</p>
    </div>
  )
}

function HomeIntro() {
  const [phase, setPhase] = useState<'show' | 'leave' | 'done'>('show')
  useEffect(() => {
    const leave = window.setTimeout(() => setPhase('leave'), 2600)
    const done = window.setTimeout(() => setPhase('done'), 3200)
    return () => { window.clearTimeout(leave); window.clearTimeout(done) }
  }, [])
  if (phase === 'done') return null

  return (
    <div role="status" aria-live="polite" className={`fixed inset-0 z-[80] flex items-center justify-center overflow-hidden bg-[#120b2e] transition-opacity duration-500 ${phase === 'leave' ? 'opacity-0' : 'opacity-100'}`}>
      <div className="pointer-events-none absolute -left-24 top-0 size-[28rem] rounded-full bg-[#e23d9a]/30 blur-3xl" />
      <div className="pointer-events-none absolute -right-24 bottom-0 size-[28rem] rounded-full bg-[#3a35e0]/40 blur-3xl" />
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(255_255_255_/_0.08)_1px,transparent_1px)] [background-size:24px_24px]" />
      <div className="relative flex flex-col items-center text-center text-white">
        <div className="relative flex size-28 items-center justify-center">
          <span className="absolute inset-0 animate-spin rounded-full border border-white/15 [animation-duration:3s]" />
          <span className="absolute inset-2 animate-spin rounded-full border-2 border-transparent border-t-[#ff8fc7] border-r-[#8fb8ff] [animation-duration:1.4s]" />
          <span className="flex size-16 items-center justify-center rounded-2xl bg-gradient-to-br from-[#ff3d9a] via-[#6d38d6] to-[#3a35e0] text-xl font-extrabold shadow-[0_16px_40px_rgb(226_61_154_/_0.45)]">1F</span>
        </div>
        <p className="mt-8 text-xs font-semibold uppercase tracking-[0.35em] text-white/60">OneFlo</p>
        <h1 className="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Real jobs. Real people.</h1>
        <div className="mt-8 h-1 w-52 overflow-hidden rounded-full bg-white/15">
          <div className="h-full origin-left animate-[oneflo-intro_2.6s_ease-out_forwards] rounded-full bg-gradient-to-r from-[#ff8fc7] via-[#c9a8ff] to-[#8fb8ff]" />
        </div>
      </div>
      <style>{'@keyframes oneflo-intro{from{transform:scaleX(0)}to{transform:scaleX(1)}}'}</style>
    </div>
  )
}

function VisitorPopup() {
  const [open, setOpen] = useState(false)
  const [pending, setPending] = useState(false)
  useEffect(() => {
    if (window.sessionStorage.getItem('oneflo.visitor')) return
    const timer = window.setTimeout(() => setOpen(true), 3200)
    return () => window.clearTimeout(timer)
  }, [])

  function close() {
    window.sessionStorage.setItem('oneflo.visitor', '1')
    setOpen(false)
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const body = Object.fromEntries([...new FormData(event.currentTarget).entries()].map(([key, value]) => [key, String(value).trim()]))
    setPending(true)
    try {
      await apiData('/api/v1/careers/visitors', { method: 'POST', body: JSON.stringify(body) })
    } catch {
      // The visitor can continue even if the details could not be saved.
    }
    close()
  }

  if (!open) return null

  const field = 'h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm focus:border-[#6d38d6] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#6d38d6]/20'

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-[#120b2e]/45 px-4 backdrop-blur-sm">
      <form onSubmit={(event) => void save(event)} className="w-full max-w-md rounded-3xl bg-white p-6 shadow-[0_24px_60px_rgb(18_11_46_/_0.28)]">
        <p className="text-xs font-semibold uppercase tracking-widest text-[#6d38d6]">Welcome to OneFlo</p>
        <h2 className="mt-1 text-xl font-extrabold text-slate-950">Tell us a little about you</h2>
        <p className="mt-1 text-sm text-slate-500">All fields are optional. Skip if you only want to browse jobs.</p>
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          <label className="block text-sm sm:col-span-2"><span className="font-medium text-slate-700">Name</span><input name="name" className={`mt-1 ${field}`} /></label>
          <label className="block text-sm"><span className="font-medium text-slate-700">Mobile</span><input name="phone" type="tel" className={`mt-1 ${field}`} /></label>
          <label className="block text-sm"><span className="font-medium text-slate-700">Location</span><input name="location" className={`mt-1 ${field}`} /></label>
          <label className="block text-sm sm:col-span-2"><span className="font-medium text-slate-700">Email</span><input name="email" type="email" className={`mt-1 ${field}`} /></label>
        </div>
        <div className="mt-5 flex items-center justify-between gap-3">
          <button type="button" onClick={close} className="text-sm font-semibold text-slate-500 hover:text-slate-800">Skip</button>
          <button type="submit" disabled={pending} className="inline-flex h-10 items-center rounded-lg bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] px-5 text-sm font-semibold text-white shadow-md disabled:opacity-60">{pending ? 'Saving…' : 'Continue'}</button>
        </div>
      </form>
    </div>
  )
}

export function HomePage() {
  const navigate = useNavigate()
  useScrollToHash()
  const listing = useQuery({ queryKey: ['careers', 'home'], queryFn: () => apiData<Listing>('/api/v1/careers'), retry: false })
  const [form, setForm] = useState({ q: '', category: '', employment_type: '', experience: '', location: '' })

  const companies = listing.data?.companies ?? []
  const categories = listing.data?.categories ?? []
  const jobs = listing.data?.jobs.data ?? []
  const stats = [
    { value: listing.data?.jobs.total ?? 0, label: 'Open jobs' },
    { value: companies.length, label: 'Businesses' },
    { value: categories.filter((row) => row.jobs > 0).length, label: 'Hiring categories' },
  ]

  function search(event: FormEvent) {
    event.preventDefault()
    const params = new URLSearchParams(Object.entries(form).filter(([, value]) => value !== ''))
    navigate(`/careers${params.size ? `?${params}` : ''}#jobs`)
  }

  const select = 'mt-1.5 h-12 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm focus:border-[#3a35e0] focus:outline-none focus:ring-2 focus:ring-[#3a35e0]/20'

  return (
    <div className="min-h-svh bg-white text-slate-900">
      <HomeIntro />
      <VisitorPopup />
      <PublicHeader />

      <section className="relative overflow-hidden bg-[#f3d9ee] pb-32 [text-shadow:0_2px_12px_rgb(20_15_69_/_0.45)] pt-16 text-white sm:pt-24">
        <img src="/images/hero-office.jpg" alt="" aria-hidden="true" className="pointer-events-none absolute inset-0 size-full scale-105 object-cover" />
        <div className="pointer-events-none absolute inset-0 bg-gradient-to-r from-[#ff8fc7]/45 via-[#c9a8ff]/20 to-[#8fb8ff]/40" />
        <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#1d1660]/25 via-transparent to-transparent" />
        <div className="pointer-events-none absolute -left-24 top-24 size-80 rounded-full bg-[#ff9ed2]/35 blur-3xl" />
        <div className="pointer-events-none absolute -right-20 bottom-0 size-96 rounded-full bg-[#9cc4ff]/35 blur-3xl" />
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(255_255_255_/_0.07)_1px,transparent_1px)] [background-size:22px_22px] [mask-image:linear-gradient(to_right,black,transparent_70%)]" />
        <div className="relative mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-[1.1fr_1fr]">
          <div>
            <p className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-sm font-semibold text-white/90 backdrop-blur">
              <span className="relative flex size-2"><span className="absolute inline-flex size-full animate-ping rounded-full bg-[#ff8cc6] opacity-75" /><span className="relative inline-flex size-2 rounded-full bg-[#ff2d87]" /></span>
              Hiring now across our businesses
            </p>
            <h1 className="mt-6 text-4xl font-extrabold leading-[1.08] tracking-tight text-white [text-shadow:0_2px_16px_rgb(20_15_69_/_0.7)] sm:text-6xl"><span className="bg-gradient-to-r from-[#2a1b8f] via-[#c026d3] to-[#ff2d87] bg-clip-text text-transparent [text-shadow:none] [filter:drop-shadow(0_1px_0_rgb(255_255_255_/_0.85))]">Real Jobs, Real People,</span> Real Success</h1>
            <p className="mt-6 max-w-xl text-lg leading-8 text-white/80">Explore exciting career opportunities across our growing businesses. Discover the right role, showcase your skills and take the next step in your career.</p>
            <dl className="mt-10 flex flex-wrap gap-4">
              {stats.map((stat) => (
                <div key={stat.label} className="min-w-32 rounded-2xl border border-white/15 bg-white/10 px-5 py-4 backdrop-blur-md">
                  <dt className="sr-only">{stat.label}</dt>
                  <dd className="text-3xl font-extrabold">{stat.value}<span className="text-[#ff8cc6]">+</span></dd>
                  <p className="mt-1 text-sm text-white/75">{stat.label}</p>
                </div>
              ))}
            </dl>
          </div>

          <div className="relative [text-shadow:none]">
          <div className="pointer-events-none absolute -inset-1 rounded-[28px] bg-gradient-to-br from-[#ff8fc7] via-[#c9a8ff] to-[#8fb8ff] opacity-60 blur-lg" />
          <form onSubmit={search} className="relative rounded-3xl bg-white/95 p-6 text-slate-900 shadow-[0_30px_80px_rgb(15_10_60_/_0.45)] backdrop-blur sm:p-8">
            <h2 className="text-2xl font-extrabold sm:text-3xl">Grow your career with <span className="bg-gradient-to-r from-[#3a35e0] to-[#e23d9a] bg-clip-text text-transparent">OneFlo</span></h2>
            <label className="mt-6 flex items-center gap-2 rounded-xl border border-slate-200 px-3 focus-within:border-[#3a35e0]">
              <Search className="size-4 text-slate-400" /><span className="sr-only">Job keywords</span>
              <input value={form.q} onChange={(event) => setForm({ ...form, q: event.target.value })} placeholder="Search job keywords" className="h-12 w-full bg-transparent text-sm outline-none" />
            </label>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <label className="text-sm font-medium text-slate-700">Job category
                <select value={form.category} onChange={(event) => setForm({ ...form, category: event.target.value })} className={select}>
                  <option value="">All categories</option>
                  {categories.map((row) => <option key={row.name} value={row.name}>{row.name}</option>)}
                </select>
              </label>
              <label className="text-sm font-medium text-slate-700">Job type
                <select value={form.employment_type} onChange={(event) => setForm({ ...form, employment_type: event.target.value })} className={select}>
                  <option value="">All types</option>
                  {Object.entries(typeLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
              </label>
              <label className="text-sm font-medium text-slate-700">Experience
                <select value={form.experience} onChange={(event) => setForm({ ...form, experience: event.target.value })} className={select}>
                  <option value="">Any</option>
                  {['0', '1', '2', '3', '5', '8', '10'].map((value) => <option key={value} value={value}>{value === '0' ? 'Fresher' : `${value} years`}</option>)}
                </select>
              </label>
              <label className="text-sm font-medium text-slate-700">Location
                <input value={form.location} onChange={(event) => setForm({ ...form, location: event.target.value })} placeholder="City" className={select} />
              </label>
            </div>
            <button type="submit" className="mt-6 h-12 w-full rounded-xl bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] text-sm font-semibold text-white shadow-lg shadow-[#6d38d6]/25 hover:opacity-95">Search result</button>
          </form>
          </div>
        </div>
        <svg aria-hidden="true" viewBox="0 0 1440 120" preserveAspectRatio="none" className="absolute inset-x-0 bottom-0 h-20 w-full text-white"><path fill="currentColor" d="M0 80c240 40 480 40 720 10s480-60 720-20v50H0z" /></svg>
      </section>

      <section id="companies" className="mx-auto max-w-7xl scroll-mt-6 px-4 py-14 sm:px-6">
      {companies.length === 0 ? <div className="mx-auto h-20 max-w-3xl animate-pulse rounded-2xl bg-slate-100" /> : (
        <>
          <p className="text-center text-lg font-medium text-slate-700">Hiring across <span className="font-bold text-[#3a35e0]">{companies.length} businesses</span> on OneFlo</p>
          <div className="mt-8 flex flex-wrap items-center justify-center gap-x-14 gap-y-6">
            {companies.map((company) => (
              <Link key={company.slug} to={`/careers/${company.slug}`} title={company.name} className="transition hover:-translate-y-0.5 hover:drop-shadow-lg">
                <Logo name={company.name} src={company.logo_url} size="size-16" />
              </Link>
            ))}
          </div>
          <div className="mt-8 text-center"><Link to="/companies" className="inline-flex items-center gap-1 text-sm font-semibold text-[#3a35e0] hover:underline">View all companies <ArrowRight className="size-4" /></Link></div>
        </>
      )}
      </section>

      <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <SectionTitle title="Featured Jobs" text="The latest openings published by our businesses. Every role here is live and accepting applications." />
        {listing.isPending ? (
          <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{[1, 2, 3, 4].map((key) => <div key={key} className="h-72 animate-pulse rounded-2xl bg-slate-100" />)}</div>
        ) : jobs.length === 0 ? (
          <div className="mx-auto mt-10 max-w-md rounded-2xl border border-dashed border-slate-300 p-10 text-center">
            <h3 className="text-lg font-bold">No Current Openings</h3>
            <p className="mt-2 text-sm text-slate-600">New opportunities are coming soon. Please check back later.</p>
          </div>
        ) : (
          <>
          <div className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {jobs.map((job, index) => {
              const accent = cardAccents[index % cardAccents.length]
              return (
                <Link key={job.slug} to={`/careers/jobs/${job.slug}`} style={{ animationDelay: `${index * 60}ms` }} className="group relative flex animate-[oneflo-card_0.55s_ease_both] flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-3 shadow-[0_8px_24px_rgb(28_20_51_/_0.05)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_16px_36px_rgb(58_53_224_/_0.14)]">
                  <span className={`absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r ${accent}`} />
                  <span className={`pointer-events-none absolute -right-8 -top-10 size-24 rounded-full bg-gradient-to-br ${accent} opacity-20 blur-2xl transition duration-500 group-hover:opacity-40`} />
                  <div className="flex items-center gap-2">
                    {job.company_logo ? <img src={job.company_logo} alt="" className="h-8 w-14 shrink-0 object-contain" /> : <span className={`flex size-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br ${accent} text-[10px] font-extrabold text-white`}>{job.company.slice(0, 2).toUpperCase()}</span>}
                    <p className="min-w-0 flex-1 truncate text-xs font-semibold text-slate-500">{job.company}</p>
                    {job.employment_type ? <span className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold ${typeStyles[job.employment_type] ?? 'bg-slate-100 text-slate-700'}`}>{typeLabels[job.employment_type] ?? job.employment_type}</span> : null}
                  </div>
                  <h3 className="mt-2 line-clamp-2 text-sm font-bold leading-snug group-hover:text-[#3a35e0]">{job.title}</h3>
                  <p className="mt-1.5 flex flex-wrap gap-1 text-[11px] text-slate-500">
                    {job.category ? <span className="rounded-full bg-[#f6f5fd] px-2 py-0.5 font-medium text-[#6d38d6]">{job.category}</span> : null}
                    {job.location ? <span className="inline-flex items-center gap-0.5"><MapPin className="size-3" />{job.location}</span> : null}
                    {experience(job) ? <span>{experience(job)}</span> : null}
                  </p>
                  <div className="mt-3 flex items-center justify-between border-t border-slate-100 pt-2">
                    <span className="text-xs font-bold text-slate-800">{job.salary_note ?? 'Salary on discussion'}</span>
                    <span className={`rounded-full bg-gradient-to-r ${accent} px-2 py-0.5 text-[10px] font-semibold text-white`}>{job.headcount ? `${job.headcount} open` : 'Apply'}</span>
                  </div>
                </Link>
              )
            })}
          </div>
          </>
        )}
        <style>{'@keyframes oneflo-card{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}'}</style>
        <div className="mt-10 text-center"><Link to="/careers#jobs" className="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-6 py-3 text-sm font-semibold hover:border-[#3a35e0] hover:text-[#3a35e0]">View all jobs <ArrowRight className="size-4" /></Link></div>
      </section>

      <section id="categories" className="bg-[#f6f5fd] py-20">
        <div className="mx-auto max-w-7xl px-4 sm:px-6">
          <SectionTitle title="Explore Top Categories" text="Pick an area you are good at. Counts show live openings in each category." />
          <div className="mt-10 grid grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-4">
            {[...categories].sort((a, b) => b.jobs - a.jobs).slice(0, 8).map((row) => {
              const Icon = categoryIcons[row.name] ?? LayoutGrid
              return (
                <Link key={row.name} to={`/careers?category=${encodeURIComponent(row.name)}#jobs`} className="group flex flex-col items-center rounded-2xl bg-white p-7 text-center shadow-[0_8px_24px_rgb(58_53_224_/_0.05)] transition hover:-translate-y-1 hover:shadow-[0_20px_40px_rgb(58_53_224_/_0.12)]">
                  <span className="flex size-14 items-center justify-center rounded-2xl bg-[#eeedfc] text-[#3a35e0] transition group-hover:bg-gradient-to-br group-hover:from-[#3a35e0] group-hover:to-[#e23d9a] group-hover:text-white"><Icon className="size-7" /></span>
                  <h3 className="mt-4 font-bold">{row.name}</h3>
                  <span className="mt-3 rounded-full bg-[#f6f5fd] px-3 py-1 text-xs font-medium text-slate-600">{row.jobs} active {row.jobs === 1 ? 'job' : 'jobs'}</span>
                </Link>
              )
            })}
          </div>
          <div className="mt-10 text-center"><Link to="/categories" className="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold hover:border-[#3a35e0] hover:text-[#3a35e0]">View all categories <ArrowRight className="size-4" /></Link></div>
        </div>
      </section>

      <section id="how" className="mx-auto max-w-7xl px-4 py-20 sm:px-6">
        <SectionTitle title="Features & Process" text="From profile to joining, every step happens in one place." />
        <div className="mt-14 grid items-center gap-10 lg:grid-cols-[1fr_320px_1fr]">
          <div className="grid gap-10">
            {steps.slice(0, 3).map(({ icon: Icon, title, text }) => (
              <div key={title}><Icon className="size-8 text-[#3a35e0]" /><h3 className="mt-3 font-bold uppercase tracking-wide">{title}</h3><p className="mt-2 text-sm leading-6 text-slate-600">{text}</p></div>
            ))}
          </div>
          <div className="mx-auto w-full max-w-[300px] rounded-[2.5rem] border-[10px] border-slate-100 bg-white p-4 shadow-[0_30px_60px_rgb(58_53_224_/_0.15)]">
            <div className="mx-auto mb-4 h-1.5 w-16 rounded-full bg-slate-200" />
            <div className="rounded-2xl bg-[#f6f5fd] p-5">
              <span className="mx-auto flex size-16 rotate-45 items-center justify-center rounded-2xl bg-gradient-to-br from-[#3a35e0] via-[#6d38d6] to-[#e23d9a]"><span className="-rotate-45 text-lg font-extrabold text-white">1F</span></span>
              <p className="mt-6 text-center text-sm font-bold">Candidate sign in</p>
              <div className="mt-4 h-10 rounded-lg border border-slate-200 bg-white px-3 text-xs leading-10 text-slate-400">you@gmail.com</div>
              <div className="mt-3 h-10 rounded-lg border border-slate-200 bg-white px-3 text-xs leading-10 tracking-[0.4em] text-slate-400">••••••</div>
              <Link to="/careers/login" className="mt-4 block rounded-lg bg-gradient-to-r from-[#3a35e0] to-[#e23d9a] py-2.5 text-center text-sm font-semibold text-white">Send code & sign in</Link>
              <p className="mt-4 text-center text-[11px] text-slate-500">Profiles are private and shared only with companies you apply to.</p>
            </div>
          </div>
          <div className="grid gap-10">
            {steps.slice(3).map(({ icon: Icon, title, text }) => (
              <div key={title}><Icon className="size-8 text-[#e23d9a]" /><h3 className="mt-3 font-bold uppercase tracking-wide">{title}</h3><p className="mt-2 text-sm leading-6 text-slate-600">{text}</p></div>
            ))}
          </div>
        </div>
        <div className="mt-12 text-center"><Link to="/how-it-works" className="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-6 py-3 text-sm font-semibold hover:border-[#3a35e0] hover:text-[#3a35e0]">See how it works <ArrowRight className="size-4" /></Link></div>
      </section>

      <section className="relative overflow-hidden bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] py-16 text-white">
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(255_255_255_/_0.08)_1px,transparent_1px)] [background-size:22px_22px]" />
        <div className="relative mx-auto max-w-4xl px-4 text-center sm:px-6">
          <h2 className="text-3xl font-extrabold tracking-tight sm:text-4xl">Are You Already Working With Us?</h2>
          <p className="mx-auto mt-3 max-w-2xl text-white/85">Employees, managers, and HR sign in to OneFlo for attendance, leave, payslips, approvals, and employee referrals.</p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <Link to="/login" className="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-semibold text-[#3a35e0] hover:bg-white/90"><LogIn className="size-4" /> Employee login</Link>
            <Link to="/careers" className="inline-flex items-center gap-2 rounded-xl border border-white/40 px-6 py-3 text-sm font-semibold hover:bg-white/10">Browse jobs <ArrowRight className="size-4" /></Link>
          </div>
        </div>
      </section>

      <PublicFooter companies={companies} />
    </div>
  )
}
