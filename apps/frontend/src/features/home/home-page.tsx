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
import { useState, type FormEvent } from 'react'
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
            <h1 className="mt-6 text-4xl font-extrabold leading-[1.08] tracking-tight sm:text-6xl">Real Jobs, Real People, <span className="bg-gradient-to-r from-[#ff3d9a] via-[#d946ef] to-[#6d5cff] bg-clip-text text-transparent [text-shadow:none] [filter:drop-shadow(0_2px_8px_rgb(255_255_255_/_0.6))]">Real Success</span></h1>
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
          <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {jobs.map((job) => (
              <Link key={job.slug} to={`/careers/jobs/${job.slug}`} className="group flex flex-col rounded-2xl border border-slate-200 bg-white p-5 text-center transition hover:-translate-y-1 hover:border-[#a5a2f2] hover:shadow-[0_20px_40px_rgb(58_53_224_/_0.12)]">
                <div className="flex items-start justify-between gap-2 text-left">
                  {job.category ? <span className="rounded-md bg-[#eeedfc] px-2 py-1 text-[11px] font-semibold text-[#3a35e0]">{job.category}</span> : <span />}
                  {job.employment_type ? <span className={`rounded-md px-2 py-1 text-[11px] font-semibold ${typeStyles[job.employment_type] ?? 'bg-slate-100 text-slate-700'}`}>{typeLabels[job.employment_type] ?? job.employment_type}</span> : null}
                </div>
                <div className="mx-auto mt-5"><Logo name={job.company} src={job.company_logo} size="size-16" /></div>
                <h3 className="mt-4 text-lg font-bold group-hover:text-[#3a35e0]">{job.title}</h3>
                <p className="mt-1 text-sm text-slate-500">{job.company}</p>
                {job.department ? <span className="mx-auto mt-2 inline-flex items-center gap-1 rounded-full bg-[#f6f5fd] px-2.5 py-1 text-[11px] font-semibold text-[#6d38d6]"><Briefcase className="size-3" />{job.department}</span> : null}
                <p className="mt-2 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs text-slate-500">
                  {job.location ? <span className="inline-flex items-center gap-1"><MapPin className="size-3.5" />{job.location}</span> : null}
                  {experience(job) ? <span>{experience(job)}</span> : null}
                </p>
                <div className="mt-auto flex items-center justify-between border-t border-slate-100 pt-4 text-left">
                  <span className="text-sm font-bold">{job.salary_note ?? 'Salary on discussion'}</span>
                  <span className="rounded-full bg-[#3a35e0] px-3 py-1 text-xs font-semibold text-white">{job.headcount ? `${job.headcount} open` : 'Apply'}</span>
                </div>
              </Link>
            ))}
          </div>
        )}
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
