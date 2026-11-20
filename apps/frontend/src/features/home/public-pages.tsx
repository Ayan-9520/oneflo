import { useQuery } from '@tanstack/react-query'
import { ArrowRight, Briefcase, CheckCircle2, LayoutGrid, LogIn } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router'

import { categoryIcons, Logo, steps, type Listing } from '@/features/home/home-page'
import { PublicShell } from '@/features/home/public-layout'
import { apiData } from '@/lib/api'

function useListing() {
  return useQuery({ queryKey: ['careers', 'home'], queryFn: () => apiData<Listing>('/api/v1/careers'), retry: false })
}

function PageHero({ eyebrow, title, text, children }: { eyebrow: string; title: string; text: string; children?: ReactNode }) {
  return (
    <section className="relative overflow-hidden bg-gradient-to-br from-[#1d1660] via-[#3a35e0] to-[#6d38d6] pb-24 pt-16 text-white">
      <div className="pointer-events-none absolute -right-20 -top-10 size-80 rounded-full bg-[#ff2d87]/30 blur-3xl" />
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(255_255_255_/_0.08)_1px,transparent_1px)] [background-size:22px_22px]" />
      <div className="relative mx-auto max-w-7xl px-4 sm:px-6">
        <p className="text-sm text-white/70"><Link to="/home" className="hover:text-white">Home</Link> / {eyebrow}</p>
        <h1 className="mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl">{title}</h1>
        <p className="mt-4 max-w-2xl text-lg text-white/80">{text}</p>
        {children}
      </div>
      <svg aria-hidden="true" viewBox="0 0 1440 120" preserveAspectRatio="none" className="absolute inset-x-0 bottom-0 h-14 w-full text-[#f6f5fd]"><path fill="currentColor" d="M0 80c240 40 480 40 720 10s480-60 720-20v50H0z" /></svg>
    </section>
  )
}

function Skeletons({ count, className }: { count: number; className: string }) {
  return <>{Array.from({ length: count }, (_, index) => <div key={index} className={`animate-pulse rounded-2xl bg-white ${className}`} />)}</>
}

export function CategoriesPage() {
  const listing = useListing()
  const categories = listing.data?.categories ?? []
  const total = categories.reduce((sum, row) => sum + row.jobs, 0)

  return (
    <PublicShell companies={listing.data?.companies}>
      <PageHero eyebrow="Categories" title="Job categories" text={`Browse openings by the kind of work you do. ${total} live ${total === 1 ? 'opening' : 'openings'} right now.`} />
      <main className="mx-auto max-w-7xl px-4 py-14 sm:px-6">
        <div className="grid grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-4">
          {listing.isPending ? <Skeletons count={8} className="h-48" /> : categories.map((row) => {
            const Icon = categoryIcons[row.name] ?? LayoutGrid
            const empty = row.jobs === 0
            return (
              <Link key={row.name} to={`/careers?category=${encodeURIComponent(row.name)}#jobs`} className={`group flex flex-col items-center rounded-2xl bg-white p-7 text-center shadow-[0_8px_24px_rgb(58_53_224_/_0.05)] transition hover:-translate-y-1 hover:shadow-[0_20px_40px_rgb(58_53_224_/_0.12)] ${empty ? 'opacity-70' : ''}`}>
                <span className="flex size-14 items-center justify-center rounded-2xl bg-[#eeedfc] text-[#3a35e0] transition group-hover:bg-gradient-to-br group-hover:from-[#3a35e0] group-hover:to-[#e23d9a] group-hover:text-white"><Icon className="size-7" /></span>
                <h2 className="mt-4 font-bold">{row.name}</h2>
                <span className={`mt-3 rounded-full px-3 py-1 text-xs font-medium ${empty ? 'bg-slate-100 text-slate-500' : 'bg-[#eeedfc] text-[#3a35e0]'}`}>{empty ? 'No openings yet' : `${row.jobs} active ${row.jobs === 1 ? 'job' : 'jobs'}`}</span>
              </Link>
            )
          })}
        </div>
        <CallToAction />
      </main>
    </PublicShell>
  )
}

export function CompaniesPage() {
  const listing = useListing()
  const companies = listing.data?.companies ?? []

  return (
    <PublicShell companies={companies}>
      <PageHero eyebrow="Companies" title="Our businesses" text="Each business hires under its own name. Open a company to see only its openings." />
      <main className="mx-auto max-w-7xl px-4 py-14 sm:px-6">
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {listing.isPending ? <Skeletons count={4} className="h-64" /> : companies.map((company) => (
            <article key={company.slug} className="flex flex-col items-center rounded-2xl bg-white p-7 text-center shadow-[0_8px_24px_rgb(58_53_224_/_0.05)] transition hover:-translate-y-1 hover:shadow-[0_20px_40px_rgb(58_53_224_/_0.12)]">
              <Logo name={company.name} src={company.logo_url} size="size-20" />
              <h2 className="mt-5 text-xl font-bold">{company.name}</h2>
              <p className="mt-2 inline-flex items-center gap-1.5 text-sm text-slate-600"><Briefcase className="size-4 text-[#3a35e0]" />{company.jobs} open {company.jobs === 1 ? 'role' : 'roles'}</p>
              <Link to={`/careers/${company.slug}`} className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#3a35e0] px-4 py-3 text-sm font-semibold text-white hover:bg-[#2f2ab8]">View jobs <ArrowRight className="size-4" /></Link>
            </article>
          ))}
        </div>
        {!listing.isPending && companies.length === 0 ? <p className="text-center text-slate-600">Companies appear here once they are set up in OneFlo.</p> : null}
        <CallToAction />
      </main>
    </PublicShell>
  )
}

export function HowItWorksPage() {
  const listing = useListing()

  return (
    <PublicShell companies={listing.data?.companies}>
      <PageHero eyebrow="How it works" title="From profile to joining" text="Every step happens in one place. No passwords, no repeated forms." />
      <main className="mx-auto max-w-5xl px-4 py-14 sm:px-6">
        <ol className="relative grid gap-6 before:absolute before:bottom-6 before:left-7 before:top-6 before:w-0.5 before:bg-gradient-to-b before:from-[#3a35e0] before:to-[#e23d9a] sm:before:left-8">
          {steps.map(({ icon: Icon, title, text }, index) => (
            <li key={title} className="relative flex gap-5 rounded-2xl bg-white p-6 shadow-[0_8px_24px_rgb(58_53_224_/_0.05)]">
              <span className="relative z-10 flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] text-white sm:size-16"><Icon className="size-7" /></span>
              <div>
                <p className="text-xs font-bold uppercase tracking-wider text-[#e23d9a]">Step {index + 1}</p>
                <h2 className="mt-1 text-xl font-bold">{title}</h2>
                <p className="mt-1 text-slate-600">{text}</p>
              </div>
            </li>
          ))}
        </ol>

        <section className="mt-14 grid gap-6 md:grid-cols-2">
          <div className="rounded-2xl bg-white p-7 shadow-[0_8px_24px_rgb(58_53_224_/_0.05)]">
            <h2 className="text-lg font-bold">For candidates</h2>
            <ul className="mt-4 space-y-3 text-sm text-slate-700">
              {['Sign in with a one-time code sent to your email', 'Save your profile and reuse it for every application', 'Track the status of each application', 'Referral links from employees are credited automatically'].map((item) => <li key={item} className="flex gap-2"><CheckCircle2 className="size-5 shrink-0 text-[#3a35e0]" />{item}</li>)}
            </ul>
            <Link to="/careers/login?mode=register" className="mt-6 inline-flex items-center gap-2 rounded-xl bg-[#3a35e0] px-5 py-3 text-sm font-semibold text-white hover:bg-[#2f2ab8]">Create your profile <ArrowRight className="size-4" /></Link>
          </div>
          <div className="rounded-2xl bg-white p-7 shadow-[0_8px_24px_rgb(58_53_224_/_0.05)]">
            <h2 className="text-lg font-bold">Privacy</h2>
            <ul className="mt-4 space-y-3 text-sm text-slate-700">
              {['Only the company you apply to sees your application', 'Resumes are stored privately and never published', 'Applying never creates an employee record by itself', 'OneFlo never asks candidates for money'].map((item) => <li key={item} className="flex gap-2"><CheckCircle2 className="size-5 shrink-0 text-[#e23d9a]" />{item}</li>)}
            </ul>
          </div>
        </section>
        <CallToAction />
      </main>
    </PublicShell>
  )
}

function CallToAction() {
  return (
    <section className="relative mt-16 overflow-hidden rounded-3xl bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] p-8 text-white sm:p-12">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(255_255_255_/_0.08)_1px,transparent_1px)] [background-size:22px_22px]" />
      <div className="relative flex flex-col items-start justify-between gap-6 md:flex-row md:items-center">
        <div>
          <h2 className="text-2xl font-extrabold sm:text-3xl">Ready for your next role?</h2>
          <p className="mt-2 text-white/85">Browse every live opening, or sign in if you already work with us.</p>
        </div>
        <div className="flex flex-wrap gap-3">
          <Link to="/careers" className="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-semibold text-[#3a35e0] hover:bg-white/90">Find jobs <ArrowRight className="size-4" /></Link>
          <Link to="/login" className="inline-flex items-center gap-2 rounded-xl border border-white/40 px-5 py-3 text-sm font-semibold hover:bg-white/10"><LogIn className="size-4" /> Employee login</Link>
        </div>
      </div>
    </section>
  )
}
