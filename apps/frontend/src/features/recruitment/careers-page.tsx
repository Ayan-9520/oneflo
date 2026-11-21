import { useQuery } from '@tanstack/react-query'
import {
  ArrowRight,
  BadgeIndianRupee,
  Briefcase,
  Building2,
  CalendarDays,
  Car,
  CheckCircle2,
  Clock,
  Code2,
  Copy,
  FileText,
  Gift,
  GraduationCap,
  Handshake,
  Headphones,
  Landmark,
  LayoutGrid,
  ListChecks,
  MapPin,
  Megaphone,
  Plane,
  Search,
  Settings2,
  Share2,
  ShieldCheck,
  Sparkles,
  TrendingUp,
  UserRound,
  Users,
  X,
  type LucideIcon,
} from 'lucide-react'
import { useState, type FormEvent, type ReactNode } from 'react'
import { Link, useParams, useSearchParams } from 'react-router'

import { TeamsSection, type Team } from '@/features/home/home-page'
import { PublicShell, useScrollToHash } from '@/features/home/public-layout'
import { candidateHeaders, useCandidate } from '@/features/recruitment/candidate-account'
import { ApiError, apiData, apiFetch } from '@/lib/api'

type Company = {
  name: string
  slug: string
  description: string | null
  industry: string | null
  logo_url: string | null
  website: string | null
  contact_email: string | null
  contact_phone: string | null
  jobs: number
}
type Category = { name: string; jobs: number }
type JobCard = {
  slug: string
  title: string
  company: string
  company_slug: string
  company_logo: string | null
  department: string | null
  designation: string | null
  location: string | null
  employment_type: string | null
  category: string | null
  experience_min: number | null
  experience_max: number | null
  salary_note: string | null
  headcount: number | null
  posted_on: string | null
  deadline_on: string | null
  summary?: string | null
  description?: string | null
  responsibilities?: string | null
  qualifications?: string | null
  skills?: string | null
  benefits?: string | null
}
type Listing = {
  companies: Company[]
  categories: Category[]
  facets: { locations: string[]; departments: string[]; designations: string[] }
  teams?: Team[]
  jobs: { data: JobCard[]; page: number; last_page: number; total: number }
}

const categoryIcons: Record<string, LucideIcon> = {
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

const employmentLabels: Record<string, string> = { full_time: 'Full time', part_time: 'Part time', contract: 'Contract', intern: 'Internship' }
const filterKeys = ['q', 'location', 'company', 'category', 'department', 'designation', 'experience', 'employment_type'] as const

function experience(job: Pick<JobCard, 'experience_min' | 'experience_max'>) {
  if (job.experience_min == null && job.experience_max == null) return null
  if (job.experience_max == null) return `${job.experience_min}+ years`
  return `${job.experience_min ?? 0}–${job.experience_max} years`
}

function formatDate(value: string | null) {
  if (!value) return null
  return new Date(value).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
}

export function CareersShell({ children, companies = [] }: { children: ReactNode; companies?: Company[] }) {
  return <PublicShell companies={companies}>{children}</PublicShell>
}

function JobCardView({ job }: { job: JobCard }) {
  const facts: Array<[LucideIcon, string, string | null]> = [
    [MapPin, 'Location', job.location ? job.location.replace(/\b\w/g, (letter) => letter.toUpperCase()) : null],
    [Clock, 'Job type', job.employment_type ? employmentLabels[job.employment_type] ?? job.employment_type : null],
    [Briefcase, 'Experience', experience(job) ?? 'Any'],
    [BadgeIndianRupee, 'Salary', job.salary_note ?? 'Not disclosed'],
    [Building2, 'Department', job.department],
    [Users, 'Openings', job.headcount ? String(job.headcount) : null],
  ]
  const skills = (job.skills ?? '').split(/[,\n]/).map((skill) => skill.trim()).filter(Boolean)
  return (
    <article className="group relative flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_8px_24px_rgb(15_23_42_/_0.04)] transition duration-300 hover:-translate-y-0.5 hover:border-[#d9c8ff] hover:shadow-[0_16px_36px_rgb(109_56_214_/_0.12)]">
      <div className="h-1 bg-gradient-to-r from-[#ff3d9a] via-[#a855f7] to-[#3a35e0]" />
      <div className="relative flex flex-1 flex-col p-4">
        <div className="flex items-start gap-3">
          <div className="flex h-11 w-20 shrink-0 items-center justify-center rounded-xl border border-slate-100 bg-white px-1.5 shadow-sm">
            {job.company_logo ? <img src={job.company_logo} alt="" className="max-h-8 w-full object-contain" /> : <span className="text-sm font-extrabold text-[#3a35e0]">{job.company.slice(0, 2).toUpperCase()}</span>}
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-1.5">
              <Link to={`/careers/${job.company_slug}`} className="text-xs font-semibold text-slate-500 hover:text-[#3a35e0]">{job.company}</Link>
              <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700"><span className="size-1.5 rounded-full bg-emerald-500" />Hiring</span>
            </div>
            <h3 className="mt-0.5 text-base font-bold leading-snug text-slate-900"><Link to={`/careers/jobs/${job.slug}`} className="hover:text-[#3a35e0]">{job.title}</Link></h3>
            {job.designation || job.category ? <p className="text-xs text-slate-500">{[job.designation, job.category].filter(Boolean).join(' · ')}</p> : null}
          </div>
        </div>

        <dl className="mt-3 flex flex-wrap gap-1.5">
          {facts.filter(([, , value]) => value).map(([Icon, label, value]) => (
            <div key={label} title={label} className="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-1 text-xs font-medium text-slate-700">
              <Icon className="size-3.5 shrink-0 text-[#6d38d6]" />
              <dd>{value}</dd>
            </div>
          ))}
        </dl>

        {job.summary ? <p className="mt-3 line-clamp-2 text-xs leading-5 text-slate-600">{job.summary}</p> : null}
        {skills.length ? (
          <div className="mt-2.5 flex flex-wrap gap-1">
            {skills.slice(0, 4).map((skill) => <span key={skill} className="rounded-full border border-[#e4dcff] bg-[#f7f3ff] px-2 py-0.5 text-[11px] font-medium text-[#5b2fc9]">{skill}</span>)}
            {skills.length > 4 ? <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">+{skills.length - 4}</span> : null}
          </div>
        ) : null}

        <div className="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
          <div className="text-[11px] text-slate-500">
            <p className="flex items-center gap-1"><CalendarDays className="size-3" />{formatDate(job.posted_on) ?? 'Recently'}</p>
            {job.deadline_on ? <p className="mt-0.5 flex items-center gap-1 font-semibold text-[#d61f7a]"><Clock className="size-3" />By {formatDate(job.deadline_on)}</p> : null}
          </div>
          <div className="flex items-center gap-1.5">
            <Link to={`/careers/jobs/${job.slug}`} className="inline-flex h-8 items-center rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:border-[#3a35e0] hover:text-[#3a35e0]">Details</Link>
            <Link to={`/careers/jobs/${job.slug}#apply`} className="inline-flex h-8 items-center gap-1 rounded-lg bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] px-3 text-xs font-semibold text-white shadow-md shadow-[#6d38d6]/20 hover:opacity-95">Apply <ArrowRight className="size-3.5" /></Link>
          </div>
        </div>
      </div>
    </article>
  )
}

function EmptyOpenings({ filtered, onClear }: { filtered: boolean; onClear: () => void }) {
  return (
    <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
      <span className="mx-auto flex size-14 items-center justify-center rounded-full bg-[#eeedfc] text-[#3a35e0]"><Briefcase className="size-6" /></span>
      <h3 className="mt-4 text-lg font-bold">{filtered ? 'No openings match these filters' : 'No Current Openings'}</h3>
      <p className="mx-auto mt-2 max-w-md text-sm text-slate-600">{filtered ? 'Try a different keyword, location, or company.' : 'New opportunities are coming soon. Please check back later.'}</p>
      {filtered ? <button type="button" onClick={onClear} className="mt-5 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Clear filters</button> : null}
    </div>
  )
}

function FilterSelect({ label, name, value, options, onChange }: { label: string; name: string; value: string; options: Array<{ value: string; label: string }>; onChange: (name: string, value: string) => void }) {
  return (
    <label className="block text-sm">
      <span className="font-medium text-slate-700">{label}</span>
      <select value={value} onChange={(event) => onChange(name, event.target.value)} className="mt-1.5 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-[#3a35e0] focus:outline-none focus:ring-2 focus:ring-[#3a35e0]/20">
        <option value="">Any</option>
        {options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
      </select>
    </label>
  )
}

export function CareersPage() {
  const params = useParams()
  const companySlug = params.company
  const [search, setSearch] = useSearchParams()
  const query = new URLSearchParams(search)
  if (companySlug) query.set('company', companySlug)
  const listing = useQuery({ queryKey: ['careers', query.toString()], queryFn: () => apiData<Listing>(`/api/v1/careers?${query.toString()}`) })
  const [keyword, setKeyword] = useState(search.get('q') ?? '')
  const [location, setLocation] = useState(search.get('location') ?? '')
  const [heroCompany, setHeroCompany] = useState(search.get('company') ?? '')
  const [filtersOpen, setFiltersOpen] = useState(false)

  const companies = listing.data?.companies ?? []
  const company = companySlug ? companies.find((row) => row.slug === companySlug) : undefined
  const team = companySlug ? listing.data?.teams?.find((row) => row.slug === companySlug) : undefined
  useScrollToHash()
  const jobs = listing.data?.jobs
  const filtered = filterKeys.some((key) => search.get(key))

  function update(name: string, value: string) {
    const next = new URLSearchParams(search)
    value ? next.set(name, value) : next.delete(name)
    next.delete('page')
    setSearch(next)
  }

  function clear() {
    setKeyword('')
    setLocation('')
    setHeroCompany('')
    setSearch(new URLSearchParams())
  }

  function submit(event: FormEvent) {
    event.preventDefault()
    const next = new URLSearchParams(search)
    for (const [key, value] of [['q', keyword], ['location', location], ['company', companySlug ? '' : heroCompany]] as const) {
      value ? next.set(key, value) : next.delete(key)
    }
    next.delete('page')
    setSearch(next)
    document.getElementById('jobs')?.scrollIntoView({ behavior: 'smooth' })
  }

  function goToPage(page: number) {
    const next = new URLSearchParams(search)
    next.set('page', String(page))
    setSearch(next)
    document.getElementById('jobs')?.scrollIntoView({ behavior: 'smooth' })
  }

  const filters = (
    <div className="grid gap-4">
      {!companySlug ? <FilterSelect label="Company" name="company" value={search.get('company') ?? ''} onChange={update} options={companies.map((row) => ({ value: row.slug, label: row.name }))} /> : null}
      <FilterSelect label="Category" name="category" value={search.get('category') ?? ''} onChange={update} options={(listing.data?.categories ?? []).filter((row) => row.jobs > 0).map((row) => ({ value: row.name, label: `${row.name} (${row.jobs})` }))} />
      <FilterSelect label="Department" name="department" value={search.get('department') ?? ''} onChange={update} options={(listing.data?.facets.departments ?? []).map((row) => ({ value: row, label: row }))} />
      <FilterSelect label="Designation" name="designation" value={search.get('designation') ?? ''} onChange={update} options={(listing.data?.facets.designations ?? []).map((row) => ({ value: row, label: row }))} />
      <FilterSelect label="Location" name="location" value={search.get('location') ?? ''} onChange={update} options={(listing.data?.facets.locations ?? []).map((row) => ({ value: row, label: row }))} />
      <FilterSelect label="Your experience" name="experience" value={search.get('experience') ?? ''} onChange={update} options={['0', '1', '2', '3', '5', '8', '10'].map((row) => ({ value: row, label: row === '0' ? 'Fresher' : `${row} years` }))} />
      <FilterSelect label="Employment type" name="employment_type" value={search.get('employment_type') ?? ''} onChange={update} options={Object.entries(employmentLabels).map(([value, label]) => ({ value, label }))} />
      {filtered ? <button type="button" onClick={clear} className="h-10 rounded-lg border border-slate-300 text-sm font-semibold hover:bg-slate-50">Clear filters</button> : null}
    </div>
  )

  return (
    <CareersShell companies={companies}>
      <section className="relative overflow-hidden border-b border-slate-200 bg-gradient-to-r from-[#ffe4f2] via-[#f3ecff] to-[#e3edff]">
        <div className="pointer-events-none absolute -left-24 -top-20 size-96 rounded-full bg-[#ff9ed2]/40 blur-3xl" />
        <div className="pointer-events-none absolute -right-24 -bottom-24 size-[28rem] rounded-full bg-[#9cc4ff]/45 blur-3xl" />
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(58_53_224_/_0.08)_1px,transparent_1px)] [background-size:22px_22px] [mask-image:linear-gradient(to_bottom,black,transparent_85%)]" />
        <div className="relative mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-20">
          {company ? (
            <div className="flex flex-col gap-8 sm:flex-row sm:items-center">
              <div className="relative shrink-0">
                <div className="pointer-events-none absolute -inset-2 rounded-[30px] bg-gradient-to-br from-[#ff8fc7] via-[#c9a8ff] to-[#8fb8ff] opacity-60 blur-lg" />
                <div className="relative flex h-32 w-64 items-center justify-center rounded-3xl border border-white bg-white px-6 shadow-[0_20px_50px_rgb(109_56_214_/_0.18)]">
                  {company.logo_url
                    ? <img src={company.logo_url} alt={`${company.name} logo`} className="max-h-20 w-full object-contain" />
                    : <span className="text-4xl font-extrabold text-[#3a35e0]">{company.name.slice(0, 2).toUpperCase()}</span>}
                </div>
              </div>
              <div>
                <Link to="/careers" className="inline-flex items-center gap-1.5 rounded-full border border-white/80 bg-white/70 px-3 py-1 text-xs font-semibold text-[#2f2ab8] shadow-sm backdrop-blur hover:bg-white"><ArrowRight className="size-3.5 rotate-180" />All companies</Link>
                <h1 className="mt-4 text-4xl font-extrabold leading-[1.1] tracking-tight text-slate-950 sm:text-5xl">Careers at <span className="bg-gradient-to-r from-[#ff3d9a] via-[#d946ef] to-[#6d5cff] bg-clip-text text-transparent">{company.name}</span></h1>
                {company.description ? <p className="mt-3 max-w-2xl text-slate-600">{company.description}</p> : null}
                <div className="mt-4 flex flex-wrap gap-2">
                  <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${company.jobs > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-white/80 text-slate-600'}`}>
                    <span className={`size-1.5 rounded-full ${company.jobs > 0 ? 'bg-emerald-500' : 'bg-slate-400'}`} />{company.jobs} {company.jobs === 1 ? 'open role' : 'open roles'}
                  </span>
                  {company.industry ? <span className="rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-slate-600">{company.industry}</span> : null}
                </div>
              </div>
            </div>
          ) : (
            <div className="max-w-3xl">
              <p className="inline-flex items-center gap-2 rounded-full border border-white/80 bg-white/70 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-[#2f2ab8] shadow-sm backdrop-blur"><span className="size-2 rounded-full bg-[#ff3d9a]" />Hiring across our businesses</p>
              <h1 className="mt-5 text-4xl font-extrabold leading-[1.1] tracking-tight text-slate-950 sm:text-6xl">Find Your Next <span className="bg-gradient-to-r from-[#ff3d9a] via-[#d946ef] to-[#6d5cff] bg-clip-text text-transparent">Career Opportunity</span></h1>
              <p className="mt-5 text-lg leading-8 text-slate-600">Explore exciting career opportunities across our growing businesses. Discover the right role, showcase your skills and take the next step in your career.</p>
            </div>
          )}
          <form onSubmit={submit} className={`mt-8 grid gap-3 rounded-2xl border border-white/80 bg-white/85 p-3 shadow-[0_24px_60px_rgb(109_56_214_/_0.15)] backdrop-blur ${companySlug ? 'md:grid-cols-[1.4fr_1fr_auto]' : 'md:grid-cols-[1.4fr_1fr_1fr_auto]'}`}>
            <label className="flex items-center gap-2 rounded-xl bg-slate-50 px-3"><Search className="size-4 text-slate-400" /><span className="sr-only">Job title or keyword</span><input value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Job title or keyword" className="h-12 w-full bg-transparent text-sm outline-none" /></label>
            <label className="flex items-center gap-2 rounded-xl bg-slate-50 px-3"><MapPin className="size-4 text-slate-400" /><span className="sr-only">Location</span><input value={location} onChange={(event) => setLocation(event.target.value)} placeholder="Location" className="h-12 w-full bg-transparent text-sm outline-none" /></label>
            {companySlug ? null : (
              <label className="flex items-center gap-2 rounded-xl bg-slate-50 px-3"><Building2 className="size-4 text-slate-400" /><span className="sr-only">Company</span>
                <select value={heroCompany} onChange={(event) => setHeroCompany(event.target.value)} className="h-12 w-full bg-transparent text-sm outline-none">
                  <option value="">All companies</option>
                  {companies.map((row) => <option key={row.slug} value={row.slug}>{row.name}</option>)}
                </select>
              </label>
            )}
            <button type="submit" className="h-12 rounded-xl bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] px-6 text-sm font-semibold text-white shadow-lg shadow-[#6d38d6]/25 hover:opacity-95">Search jobs</button>
          </form>
        </div>
      </section>

      {team ? <TeamsSection teams={[team]} /> : null}

      <main className="mx-auto max-w-7xl px-4 sm:px-6">
        {!companySlug ? (
          <section id="companies" className="pt-16">
            <div className="flex items-end justify-between gap-4">
              <div><h2 className="text-2xl font-bold tracking-tight">Our companies</h2><p className="mt-1 text-sm text-slate-600">Each business hires under its own name.</p></div>
            </div>
            {listing.isPending ? <div className="mt-6 grid gap-5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">{[1, 2, 3, 4, 5].map((key) => <div key={key} className="h-40 animate-pulse rounded-2xl bg-white" />)}</div> : companies.length === 0 ? <p className="mt-6 text-sm text-slate-600">Companies appear here once they are set up in OneFlo.</p> : (
              <>
              <div className="mt-6 grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">
                {companies.map((row, index) => {
                  const accent = ['from-[#3a35e0] to-[#6d38d6]', 'from-[#0f766e] to-[#2dd4bf]', 'from-[#c2410c] to-[#fb923c]', 'from-[#be185d] to-[#e879f9]', 'from-[#1d4ed8] to-[#38bdf8]'][index % 5]
                  return (
                    <Link key={row.slug} to={`/careers/${row.slug}`} style={{ animationDelay: `${index * 60}ms` }} className="group relative flex animate-[oneflo-card_0.55s_ease_both] flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-[0_8px_24px_rgb(28_20_51_/_0.05)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_16px_36px_rgb(58_53_224_/_0.14)]">
                      <span className={`absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r ${accent}`} />
                      <span className={`pointer-events-none absolute -right-8 -top-10 size-24 rounded-full bg-gradient-to-br ${accent} opacity-20 blur-2xl transition duration-500 group-hover:opacity-40`} />
                      {row.logo_url
                        ? <img src={row.logo_url} alt={`${row.name} logo`} className="h-10 w-auto max-w-[8.5rem] object-contain object-left" />
                        : <span className={`flex size-10 items-center justify-center rounded-xl bg-gradient-to-br ${accent} text-xs font-extrabold text-white`}>{row.name.slice(0, 2).toUpperCase()}</span>}
                      <h3 className="mt-3 text-sm font-bold text-slate-900">{row.name}</h3>
                      {row.industry ? <p className="mt-0.5 text-[11px] font-medium text-slate-500">{row.industry}</p> : null}
                      <span className={`mt-2 inline-flex w-fit items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ${row.jobs > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                        <span className={`size-1.5 rounded-full ${row.jobs > 0 ? 'bg-emerald-500' : 'bg-slate-400'}`} />{row.jobs} {row.jobs === 1 ? 'open role' : 'open roles'}
                      </span>
                      <span className={`mt-3 inline-flex h-8 items-center justify-center gap-1 rounded-lg bg-gradient-to-r ${accent} text-xs font-semibold text-white`}>
                        View jobs <ArrowRight className="size-3.5 transition group-hover:translate-x-0.5" />
                      </span>
                    </Link>
                  )
                })}
              </div>
              <style>{'@keyframes oneflo-card{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}'}</style>
              </>
            )}
          </section>
        ) : null}

        <section id="categories" className="pt-16">
          <h2 className="text-2xl font-bold tracking-tight">Job categories</h2>
          <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-7">
            {(listing.data?.categories ?? []).map((row) => {
              const Icon = categoryIcons[row.name] ?? LayoutGrid
              const active = search.get('category') === row.name
              return (
                <button key={row.name} type="button" onClick={() => { update('category', active ? '' : row.name); document.getElementById('jobs')?.scrollIntoView({ behavior: 'smooth' }) }} className={`flex flex-col items-start rounded-2xl border p-4 text-left transition ${active ? 'border-[#3a35e0] bg-[#eeedfc]' : 'border-slate-200 bg-white hover:border-[#ff8cc6]'}`}>
                  <span className="flex size-10 items-center justify-center rounded-xl bg-[#eeedfc] text-[#3a35e0]"><Icon className="size-5" /></span>
                  <span className="mt-3 text-sm font-semibold leading-tight">{row.name}</span>
                  <span className="mt-1 text-xs text-slate-500">{row.jobs} {row.jobs === 1 ? 'job' : 'jobs'}</span>
                </button>
              )
            })}
          </div>
        </section>

        <section id="jobs" className="scroll-mt-20 pt-16">
          <div className="flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 className="text-2xl font-bold tracking-tight">{filtered ? 'Matching jobs' : 'Latest jobs'}</h2>
              <p className="mt-1 text-sm text-slate-600">{jobs ? `${jobs.total} ${jobs.total === 1 ? 'opening' : 'openings'}` : 'Loading openings'}</p>
            </div>
            <button type="button" onClick={() => setFiltersOpen(true)} className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold lg:hidden"><Settings2 className="size-4" /> Filters</button>
          </div>
          <div className="mt-6 grid gap-6 lg:grid-cols-[260px_1fr]">
            <aside className="hidden h-fit rounded-2xl border border-slate-200 bg-white p-5 lg:block"><p className="mb-4 text-sm font-bold">Filter jobs</p>{filters}</aside>
            <div>
              {listing.isPending ? <div className="grid gap-4">{[1, 2, 3].map((key) => <div key={key} className="h-40 animate-pulse rounded-2xl bg-white" />)}</div> : listing.isError ? <p className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{listing.error instanceof ApiError ? listing.error.message : 'Jobs could not be loaded.'}</p> : !jobs || jobs.data.length === 0 ? <EmptyOpenings filtered={filtered} onClear={clear} /> : (
                <>
                  <div className="grid gap-4 xl:grid-cols-2">{jobs.data.map((job) => <JobCardView key={job.slug} job={job} />)}</div>
                  {jobs.last_page > 1 ? (
                    <nav aria-label="Pages" className="mt-8 flex items-center justify-center gap-2">
                      <button type="button" disabled={jobs.page <= 1} onClick={() => goToPage(jobs.page - 1)} className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold disabled:opacity-40">Previous</button>
                      <span className="px-3 text-sm text-slate-600">Page {jobs.page} of {jobs.last_page}</span>
                      <button type="button" disabled={jobs.page >= jobs.last_page} onClick={() => goToPage(jobs.page + 1)} className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold disabled:opacity-40">Next</button>
                    </nav>
                  ) : null}
                </>
              )}
            </div>
          </div>
        </section>

        <section id="referral" className="mt-16 grid gap-6 rounded-3xl bg-gradient-to-br from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] p-8 text-white sm:p-10 md:grid-cols-[1.4fr_1fr]">
          <div>
            <h2 className="text-2xl font-bold tracking-tight">Know someone who works with us?</h2>
            <p className="mt-3 max-w-xl text-[#eeedfc]/85">Employees can share a personal referral link for any open role. When you apply through that link, the referral stays attached to your application automatically.</p>
          </div>
          <ul className="space-y-3 text-sm text-[#eeedfc]">
            {['Ask the employee for the job link they shared', 'Apply through that link with your own details', 'The recruiting team sees who referred you'].map((step) => <li key={step} className="flex gap-2"><CheckCircle2 className="size-5 shrink-0 text-[#ff8cc6]" />{step}</li>)}
          </ul>
        </section>

        <section id="privacy" className="mt-16 grid gap-6 text-sm text-slate-600 md:grid-cols-2">
          <div><h2 className="text-base font-bold text-slate-900">Privacy</h2><p className="mt-2">Your application, contact details, and resume are shared only with the recruiting team of the company you apply to. Resumes are stored privately and are never published.</p></div>
          <div id="terms"><h2 className="text-base font-bold text-slate-900">Terms</h2><p className="mt-2">Submitting an application does not guarantee an interview or an offer. Please provide accurate information.</p></div>
        </section>
      </main>

      {filtersOpen ? (
        <div className="fixed inset-0 z-40 flex justify-end bg-slate-950/40 lg:hidden" role="dialog" aria-modal="true" aria-label="Filters">
          <div className="h-full w-full max-w-sm overflow-y-auto bg-white p-5">
            <div className="mb-4 flex items-center justify-between"><p className="font-bold">Filter jobs</p><button type="button" aria-label="Close filters" onClick={() => setFiltersOpen(false)}><X className="size-5" /></button></div>
            {filters}
            <button type="button" onClick={() => setFiltersOpen(false)} className="mt-6 h-11 w-full rounded-lg bg-[#3a35e0] text-sm font-semibold text-white">Show {jobs?.total ?? 0} jobs</button>
          </div>
        </div>
      ) : null}
    </CareersShell>
  )
}

function FormField({ label, name, type = 'text', required = false, hint, accept, min, defaultValue }: { label: string; name: string; type?: string; required?: boolean; hint?: string; accept?: string; min?: number; defaultValue?: string | number | null }) {
  return (
    <label className="block text-sm">
      <span className="font-semibold text-slate-700">{label}{required ? <span className="text-[#e23d9a]"> *</span> : <span className="font-normal text-slate-400"> (optional)</span>}</span>
      <input name={name} type={type} required={required} accept={accept} min={min} defaultValue={defaultValue ?? undefined} className={`mt-1 w-full rounded-lg border border-slate-200 bg-slate-50/70 px-3 text-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-[#8b5cf6] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#8b5cf6]/20 ${type === 'file' ? 'cursor-pointer border-dashed bg-[#faf7ff] py-1.5 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-gradient-to-r file:from-[#3a35e0] file:to-[#e23d9a] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white' : 'h-10'}`} />
      {hint ? <span className="mt-1 block text-xs text-slate-500">{hint}</span> : null}
    </label>
  )
}

export function CareerJobPage() {
  const { slug = '' } = useParams()
  const [search] = useSearchParams()
  const job = useQuery({ queryKey: ['career-job', slug], enabled: slug !== '', queryFn: () => apiData<JobCard>(`/api/v1/careers/jobs/${slug}`), retry: false })
  const [done, setDone] = useState<{ reference: number; emailed: boolean } | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [pending, setPending] = useState(false)
  const [copied, setCopied] = useState(false)
  const me = useCandidate().data
  const shareUrl = typeof window === 'undefined' ? '' : `${window.location.origin}/careers/jobs/${slug}${search.get('ref') ? `?ref=${search.get('ref')}` : ''}`

  async function apply(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setFieldErrors({})
    setPending(true)
    const body = new FormData(event.currentTarget)
    body.set('consent', body.get('consent') ? '1' : '')
    body.set('accurate', body.get('consent') ? '1' : '')
    const ref = search.get('ref')
    if (ref) body.set('ref', ref)
    for (const key of ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content']) {
      const value = search.get(key)
      if (value) body.set(key, value)
    }
    const source = search.get('utm_source')?.toLowerCase()
    const known: Record<string, string> = { facebook: 'Facebook', linkedin: 'LinkedIn', whatsapp: 'WhatsApp', instagram: 'Instagram' }
    if (source && known[source]) body.set('source', known[source])
    try {
      const response = await apiFetch(`/api/v1/careers/jobs/${slug}/apply`, { method: 'POST', body, headers: candidateHeaders() })
      const payload = await response.json().catch(() => ({}))
      if (!response.ok) {
        setError(response.status === 429 ? 'Too many attempts from this connection. Please wait a minute and try again.' : payload.message ?? 'The application could not be saved.')
        setFieldErrors(payload.errors ?? {})
        return
      }
      setDone({ reference: payload.data.application_id, emailed: payload.data.acknowledgement === 'sent' })
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch {
      setError('The application could not be sent. Check your connection and try again.')
    } finally {
      setPending(false)
    }
  }

  if (job.isPending) return <CareersShell><div className="mx-auto max-w-5xl px-4 py-16"><div className="h-64 animate-pulse rounded-2xl bg-white" /></div></CareersShell>
  if (job.isError || !job.data) {
    return (
      <CareersShell>
        <div className="mx-auto max-w-xl px-4 py-24 text-center">
          <h1 className="text-2xl font-bold">This opening is no longer available</h1>
          <p className="mt-2 text-slate-600">It may have been filled or closed.</p>
          <Link to="/careers" className="mt-6 inline-flex rounded-lg bg-[#3a35e0] px-5 py-2.5 text-sm font-semibold text-white">Browse open roles</Link>
        </div>
      </CareersShell>
    )
  }

  const row = job.data
  const facts: Array<[string, string | null]> = [
    ['Department', row.department],
    ['Designation', row.designation],
    ['Location', row.location],
    ['Experience', experience(row)],
    ['Employment type', row.employment_type ? employmentLabels[row.employment_type] ?? row.employment_type : null],
    ['Salary', row.salary_note],
    ['Openings', row.headcount != null ? String(row.headcount) : null],
    ['Apply by', formatDate(row.deadline_on)],
  ]
  const sections: Array<[string, string | null | undefined, LucideIcon, string]> = [
    ['About the role', row.description, FileText, 'from-[#3a35e0] to-[#5fa8ff]'],
    ['Responsibilities', row.responsibilities, ListChecks, 'from-[#ff3d9a] to-[#ff8a5c]'],
    ['Qualifications', row.qualifications, GraduationCap, 'from-[#10b981] to-[#06b6d4]'],
    ['Skills', row.skills, Sparkles, 'from-[#8b5cf6] to-[#d946ef]'],
    ['Benefits', row.benefits, Gift, 'from-[#f59e0b] to-[#ef4444]'],
  ]
  const overviewIcons: Record<string, LucideIcon> = { Department: Building2, Designation: UserRound, Location: MapPin, Experience: Briefcase, 'Employment type': Clock, Salary: BadgeIndianRupee, Openings: Users, 'Apply by': CalendarDays }
  const heroChips = [
    [MapPin, row.location ? row.location.replace(/\b\w/g, (letter) => letter.toUpperCase()) : null],
    [Clock, row.employment_type ? employmentLabels[row.employment_type] ?? row.employment_type : null],
    [Briefcase, experience(row)],
    [BadgeIndianRupee, row.salary_note],
  ].filter(([, value]) => value) as Array<[LucideIcon, string]>
  const fieldError = (name: string) => fieldErrors[name]?.[0]

  return (
    <CareersShell>
      <section className="relative overflow-hidden border-b border-slate-200 bg-gradient-to-r from-[#ffe4f2] via-[#f3ecff] to-[#e3edff]">
        <div className="pointer-events-none absolute -left-24 -top-20 size-96 rounded-full bg-[#ff9ed2]/40 blur-3xl" />
        <div className="pointer-events-none absolute -bottom-24 -right-24 size-[28rem] rounded-full bg-[#9cc4ff]/45 blur-3xl" />
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(58_53_224_/_0.08)_1px,transparent_1px)] [background-size:22px_22px] [mask-image:linear-gradient(to_bottom,black,transparent_85%)]" />
        <div className="relative mx-auto max-w-6xl px-4 py-12 sm:px-6">
          <Link to="/careers#jobs" className="inline-flex items-center gap-1.5 rounded-full border border-white/80 bg-white/70 px-3 py-1 text-xs font-semibold text-[#2f2ab8] shadow-sm backdrop-blur hover:bg-white"><ArrowRight className="size-3.5 rotate-180" />All jobs</Link>
          <div className="relative mt-5 overflow-hidden rounded-3xl border border-white/80 bg-white/80 p-5 shadow-[0_18px_50px_rgb(58_53_224_/_0.10)] backdrop-blur sm:p-6">
            <span className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-[#ff3d9a] via-[#8b5cf6] to-[#3a35e0]" />
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
              {row.company_logo ? <img src={row.company_logo} alt={`${row.company} logo`} className="h-11 w-auto max-w-[9.5rem] shrink-0 object-contain object-left" /> : <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#3a35e0] to-[#e23d9a] text-sm font-extrabold text-white">{row.company.slice(0, 2).toUpperCase()}</span>}
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <Link to={`/careers/${row.company_slug}`} className="text-xs font-semibold uppercase tracking-wide text-slate-500 hover:text-[#3a35e0]">{row.company}</Link>
                  <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"><span className="size-1.5 rounded-full bg-emerald-500" />Actively hiring</span>
                </div>
                <h1 className="mt-1 text-2xl font-extrabold leading-tight tracking-tight text-slate-950 sm:text-3xl">{row.title}</h1>
                <div className="mt-3 flex flex-wrap gap-1.5">
                  {heroChips.map(([Icon, value]) => <span key={value} className="inline-flex items-center gap-1 rounded-full bg-[#f6f5fd] px-2.5 py-1 text-[11px] font-semibold text-slate-700"><Icon className="size-3.5 text-[#6d38d6]" />{value}</span>)}
                  <span className="inline-flex items-center gap-1 rounded-full bg-[#f6f5fd] px-2.5 py-1 text-[11px] font-semibold text-slate-700"><CalendarDays className="size-3.5 text-[#6d38d6]" />Posted {formatDate(row.posted_on) ?? 'recently'}</span>
                </div>
              </div>
              <a href="#apply" className="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] px-5 text-sm font-semibold text-white shadow-lg shadow-[#6d38d6]/25 transition hover:-translate-y-0.5 hover:opacity-95">Apply now <ArrowRight className="size-4" /></a>
            </div>
          </div>
        </div>
      </section>

      <main className="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[1fr_320px]">
        <div className="space-y-6">
          {sections.filter(([, text]) => text).map(([title, text, Icon, gradient]) => (
            <section key={title} className="rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_10px_30px_rgb(15_23_42_/_0.04)]">
              <h2 className="flex items-center gap-3 text-lg font-bold"><span className={`flex size-10 items-center justify-center rounded-xl bg-gradient-to-br ${gradient} text-white shadow-md`}><Icon className="size-5" /></span>{title}</h2>
              {title === 'Skills'
                ? <div className="mt-4 flex flex-wrap gap-2">{(text ?? '').split(/[,\n]/).map((skill) => skill.trim()).filter(Boolean).map((skill) => <span key={skill} className="rounded-full border border-[#e4dcff] bg-[#f7f3ff] px-3 py-1.5 text-sm font-medium text-[#5b2fc9]">{skill}</span>)}</div>
                : <p className="mt-4 whitespace-pre-wrap leading-7 text-slate-700">{text}</p>}
            </section>
          ))}

          <section id="apply" className="scroll-mt-24 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_20px_50px_rgb(109_56_214_/_0.10)]">
            {done ? (
              <div className="p-8 text-center sm:p-10">
                <span className="mx-auto flex size-14 items-center justify-center rounded-full bg-[#eeedfc] text-[#3a35e0]"><CheckCircle2 className="size-7" /></span>
                <h2 className="mt-4 text-2xl font-bold">Application received</h2>
                <p className="mt-2 text-slate-600">Thank you for applying for {row.title} at {row.company}. Your reference number is <span className="font-semibold text-slate-900">{done.reference}</span>.</p>
                <p className="mt-2 text-sm text-slate-500">{done.emailed ? 'A confirmation email is on its way.' : 'Keep this reference. A confirmation email was not sent.'} The recruiting team will contact you if your profile is shortlisted.</p>
                <Link to="/careers#jobs" className="mt-6 inline-flex rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold hover:bg-slate-50">See more jobs</Link>
              </div>
            ) : (
              <form key={me?.id ?? 'guest'} onSubmit={(event) => void apply(event)} noValidate={false}>
                <div className="relative overflow-hidden bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] px-5 py-4 text-white sm:px-6">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                      <p className="text-[11px] font-semibold uppercase tracking-widest text-white/75">Apply · about 3 minutes</p>
                      <h2 className="text-lg font-extrabold leading-tight">{row.title}</h2>
                      <p className="text-xs text-white/85">{row.company}{search.get('ref') ? ' · Referred by an employee' : ''}</p>
                    </div>
                    <div className="flex gap-1.5 text-[11px] font-semibold">
                      {['Personal', 'Work', 'Resume'].map((step, index) => <span key={step} className="inline-flex items-center gap-1 rounded-full bg-white/15 px-2 py-1"><span className="flex size-4 items-center justify-center rounded-full bg-white text-[10px] text-[#6d38d6]">{index + 1}</span>{step}</span>)}
                    </div>
                  </div>
                </div>
                <div className="p-4 sm:p-5">
                <p className="rounded-xl border border-[#e4dcff] bg-[#f7f3ff] px-3 py-2 text-xs text-[#3b2b8f]">
                  {me ? <>Signed in as <span className="font-semibold">{me.full_name}</span>. Your details are filled in.</> : <>Have a profile? <Link to={`/careers/login?next=/careers/jobs/${slug}%23apply`} className="font-semibold underline">Sign in</Link> to fill this automatically, or continue as a guest.</>}
                </p>
                <fieldset className="mt-4 grid gap-3 sm:grid-cols-2">
                  <legend className="mb-2 text-sm font-bold text-slate-900">1 · Personal</legend>
                  <FormField label="Full name" name="full_name" required defaultValue={me?.full_name} />
                  <FormField label="Email" name="email" type="email" required defaultValue={me?.email} />
                  <FormField label="Mobile number" name="phone" type="tel" required defaultValue={me?.phone} />
                  <FormField label="Current location" name="location" required defaultValue={me?.location} />
                </fieldset>
                <fieldset className="mt-5 grid gap-3 sm:grid-cols-2">
                  <legend className="mb-2 text-sm font-bold text-slate-900">2 · Professional</legend>
                  <FormField label="Total experience (years)" name="experience_years" type="number" min={0} required defaultValue={me?.experience_years} />
                  <FormField label="Notice period" name="notice_period" required hint="For example: Immediate, 30 days" defaultValue={me?.notice_period} />
                  <FormField label="Current company" name="current_company" defaultValue={me?.current_company} />
                  <FormField label="Current designation" name="current_designation" defaultValue={me?.current_designation} />
                  <FormField label="Current salary" name="current_salary" defaultValue={me?.current_salary} />
                  <FormField label="Expected salary" name="expected_salary" defaultValue={me?.expected_salary} />
                  <FormField label="Highest qualification" name="qualification" required defaultValue={me?.qualification} />
                  <FormField label="Skills" name="skills" required hint="Separate skills with commas" defaultValue={me?.skills} />
                </fieldset>
                <fieldset className="mt-5 grid gap-3">
                  <legend className="mb-2 text-sm font-bold text-slate-900">3 · Resume</legend>
                  <FormField label="Resume" name="resume" type="file" accept=".pdf,.doc,.docx" required hint="PDF, DOC or DOCX, up to 5 MB" />
                  <label className="block text-sm sm:col-span-2"><span className="font-medium text-slate-700">Message <span className="font-normal text-slate-400">(optional)</span></span><textarea name="message" rows={2} maxLength={2000} placeholder="Why you are a good fit (optional)" className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-[#8b5cf6] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#8b5cf6]/20" /></label>
                  <label className="flex cursor-pointer items-start gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-700"><input type="checkbox" name="consent" value="1" required className="mt-0.5 size-3.5 accent-[#3a35e0]" /><span>I agree that {row.company} may use my details for this application under the <a href="/careers#privacy" className="font-semibold text-[#3a35e0]">privacy notice</a>, and the information is accurate.</span></label>
                </fieldset>
                {Object.keys(fieldErrors).length > 0 ? <ul className="mt-4 list-disc space-y-1 pl-5 text-sm text-red-700">{Object.keys(fieldErrors).map((name) => <li key={name}>{fieldError(name)}</li>)}</ul> : null}
                {error && Object.keys(fieldErrors).length === 0 ? <p className="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p> : null}
                <div className="mt-4 flex flex-col gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                  <p className="flex items-center gap-1.5 text-[11px] text-slate-500"><ShieldCheck className="size-3.5 text-emerald-600" />Resume stays private and is shared only with {row.company}.</p>
                  <button type="submit" disabled={pending} className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] text-sm font-semibold text-white shadow-md shadow-[#6d38d6]/25 hover:opacity-95 disabled:opacity-60 sm:w-auto sm:px-6">{pending ? 'Sending…' : <>Submit application <ArrowRight className="size-4" /></>}</button>
                </div>
                </div>
              </form>
            )}
          </section>
        </div>

        <aside className="space-y-5 lg:sticky lg:top-24 lg:h-fit">
          <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_10px_30px_rgb(15_23_42_/_0.05)]">
            <div className="h-1.5 bg-gradient-to-r from-[#ff3d9a] via-[#a855f7] to-[#3a35e0]" />
            <div className="p-5">
              <p className="text-base font-bold">Job overview</p>
              <dl className="mt-4 space-y-2.5">
                {facts.filter(([, value]) => value).map(([label, value]) => {
                  const Icon = overviewIcons[label] ?? Briefcase
                  return (
                    <div key={label} className="flex items-center gap-3 rounded-xl bg-slate-50 px-3 py-2.5">
                      <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white text-[#6d38d6] shadow-sm"><Icon className="size-4" /></span>
                      <div className="min-w-0"><dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{label}</dt><dd className="text-sm font-semibold capitalize text-slate-800">{value}</dd></div>
                    </div>
                  )
                })}
              </dl>
              <a href="#apply" className="mt-4 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] text-sm font-semibold text-white shadow-lg shadow-[#6d38d6]/25 hover:opacity-95">Apply now <ArrowRight className="size-4" /></a>
            </div>
          </div>
          <div className="rounded-3xl border border-slate-200 bg-white p-5 shadow-[0_10px_30px_rgb(15_23_42_/_0.05)]">
            <p className="flex items-center gap-2 text-base font-bold"><Share2 className="size-4 text-[#6d38d6]" />Share this job</p>
            <div className="mt-4 grid grid-cols-2 gap-2 text-sm font-semibold">
              <a className="rounded-xl bg-[#e8f9ef] px-3 py-2.5 text-center text-[#128c4b] transition hover:bg-[#25d366] hover:text-white" target="_blank" rel="noreferrer" href={`https://wa.me/?text=${encodeURIComponent(`${row.title} at ${row.company} ${shareUrl}`)}`}>WhatsApp</a>
              <a className="rounded-xl bg-[#e8f1fb] px-3 py-2.5 text-center text-[#0a66c2] transition hover:bg-[#0a66c2] hover:text-white" target="_blank" rel="noreferrer" href={`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(shareUrl)}`}>LinkedIn</a>
              <a className="rounded-xl bg-[#ebf2fe] px-3 py-2.5 text-center text-[#1877f2] transition hover:bg-[#1877f2] hover:text-white" target="_blank" rel="noreferrer" href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`}>Facebook</a>
              <button type="button" className="inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-100 px-3 py-2.5 text-slate-700 transition hover:bg-slate-800 hover:text-white" onClick={() => void navigator.clipboard.writeText(shareUrl).then(() => { setCopied(true); setTimeout(() => setCopied(false), 2000) })}><Copy className="size-4" />{copied ? 'Copied' : 'Copy link'}</button>
            </div>
          </div>
          <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#fff4e5] to-[#ffe4f2] p-5 text-sm text-[#7c2d12]">
            <ShieldCheck className="pointer-events-none absolute -bottom-3 -right-3 size-20 text-[#f97316] opacity-10" />
            <p className="flex items-center gap-2 font-bold"><BadgeIndianRupee className="size-5 text-[#ea580c]" />Stay safe</p>
            <p className="mt-2 leading-6">OneFlo never asks candidates for money to apply or to be interviewed.</p>
          </div>
        </aside>
      </main>
    </CareersShell>
  )
}
