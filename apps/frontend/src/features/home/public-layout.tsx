import { LogIn, Menu, UserRound, X } from 'lucide-react'
import { useEffect, useState, type ReactNode } from 'react'
import { Link, NavLink, useLocation } from 'react-router'

import { useCandidate } from '@/features/recruitment/candidate-account'

export type PublicCompany = { name: string; slug: string; contact_email?: string | null; contact_phone?: string | null; website?: string | null }

const navItems = [
  { label: 'Home', to: '/home' },
  { label: 'Find jobs', to: '/careers' },
  { label: 'Categories', to: '/categories' },
  { label: 'Companies', to: '/companies' },
  { label: 'How it works', to: '/how-it-works' },
  { label: 'For employers', to: '/login' },
]

export function useScrollToHash() {
  const { hash, pathname } = useLocation()
  useEffect(() => {
    if (!hash) {
      window.scrollTo({ top: 0 })
      return
    }
    let tries = 0
    const timer = window.setInterval(() => {
      const target = document.getElementById(decodeURIComponent(hash.slice(1)))
      tries += 1
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' })
        window.clearInterval(timer)
        window.setTimeout(() => {
          if (Math.abs(target.getBoundingClientRect().top) > 120) target.scrollIntoView({ block: 'start' })
        }, 900)
      } else if (tries > 60) {
        window.clearInterval(timer)
      }
    }, 100)
    return () => window.clearInterval(timer)
  }, [hash, pathname])
}

export function PublicHeader({ overlay = false }: { overlay?: boolean }) {
  const [open, setOpen] = useState(false)
  const candidate = useCandidate().data
  const { pathname, hash } = useLocation()
  const light = overlay && !open
  const isActive = (to: string) => {
    const [path, anchor] = to.split('#')
    if (anchor) return pathname === path && hash === `#${anchor}`
    if (path === '/careers') return pathname.startsWith('/careers') && !pathname.startsWith('/careers/login') && !pathname.startsWith('/careers/profile')
    return pathname === path && !hash
  }
  const linkClass = (to: string) => `rounded-lg px-1 py-1 text-sm font-medium transition ${isActive(to) ? (light ? 'text-white' : 'text-[#3a35e0]') : light ? 'text-white/80 hover:text-white' : 'text-slate-600 hover:text-[#3a35e0]'}`

  return (
    <header className={overlay ? 'absolute inset-x-0 top-0 z-40' : 'sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 backdrop-blur'}>
      {!overlay ? <div className="h-1 bg-gradient-to-r from-[#2a1878] via-[#7a2cff] to-[#ff2d87]" /> : null}
      <div className={`mx-auto flex h-[72px] max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 ${open ? 'bg-white' : ''}`}>
        <Link to="/home" className="flex items-center gap-2" onClick={() => setOpen(false)}>
          <span className={`flex size-10 items-center justify-center rounded-xl text-sm font-extrabold ${light ? 'bg-white text-[#3a35e0]' : 'bg-gradient-to-br from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] text-white'}`}>1F</span>
          <span className={`text-xl font-extrabold tracking-tight ${light ? 'text-white' : ''}`}>{light ? 'OneFlo' : <><span className="text-[#3a35e0]">One</span><span className="text-[#e23d9a]">Flo</span></>}</span>
        </Link>
        <nav aria-label="Main" className="hidden items-center gap-6 lg:flex">
          {navItems.map((item) => <NavLink key={item.label} to={item.to} className={linkClass(item.to)}>{item.label}</NavLink>)}
        </nav>
        <div className="hidden items-center gap-2 sm:flex">
          {candidate ? (
            <Link to="/careers/profile" className={`inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ${light ? 'bg-white text-[#3a35e0]' : 'bg-[#3a35e0] text-white hover:bg-[#2f2ab8]'}`}><UserRound className="size-4" />{candidate.full_name.split(' ')[0]}</Link>
          ) : (
            <>
              <Link to="/careers/login" className={`inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold ${light ? 'text-white' : 'text-slate-700 hover:text-[#3a35e0]'}`}><LogIn className="size-4" /> Sign in</Link>
              <Link to="/careers/login?mode=register" className={`inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ${light ? 'bg-white text-[#3a35e0] hover:bg-white/90' : 'bg-gradient-to-r from-[#3a35e0] to-[#6d38d6] text-white hover:opacity-95'}`}><UserRound className="size-4" /> Register today</Link>
            </>
          )}
        </div>
        <button type="button" className={`rounded-lg p-2 lg:hidden ${light ? 'text-white' : ''}`} aria-label={open ? 'Close menu' : 'Open menu'} aria-expanded={open} onClick={() => setOpen(!open)}>
          {open ? <X className="size-6" /> : <Menu className="size-6" />}
        </button>
      </div>
      {open ? (
        <nav aria-label="Mobile" className="border-t border-slate-100 bg-white px-4 pb-5 pt-2 shadow-lg lg:hidden">
          {navItems.map((item) => <Link key={item.label} to={item.to} onClick={() => setOpen(false)} className="block rounded-lg px-3 py-3 text-sm font-medium text-slate-700 hover:bg-[#f6f5fd]">{item.label}</Link>)}
          <div className="mt-3 grid gap-2 border-t border-slate-100 pt-4">
            {candidate
              ? <Link to="/careers/profile" onClick={() => setOpen(false)} className="rounded-xl bg-[#3a35e0] py-3 text-center text-sm font-semibold text-white">My profile</Link>
              : (
                <>
                  <Link to="/careers/login" onClick={() => setOpen(false)} className="rounded-xl border border-slate-300 py-3 text-center text-sm font-semibold">Sign in</Link>
                  <Link to="/careers/login?mode=register" onClick={() => setOpen(false)} className="rounded-xl bg-gradient-to-r from-[#3a35e0] to-[#6d38d6] py-3 text-center text-sm font-semibold text-white">Register today</Link>
                </>
              )}
          </div>
        </nav>
      ) : null}
    </header>
  )
}

export function PublicFooter({ companies = [] }: { companies?: PublicCompany[] }) {
  const contact = companies.find((company) => company.contact_email || company.contact_phone)
  return (
    <footer id="contact" className="relative overflow-hidden bg-[#0c0824] text-slate-400">
      <div className="h-1 bg-gradient-to-r from-[#ff3d9a] via-[#8b5cf6] to-[#3a35e0]" />
      <div className="pointer-events-none absolute -left-20 top-10 size-64 rounded-full bg-[#6d38d6]/20 blur-3xl" />
      <div className="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-5">
        <div className="md:col-span-2">
          <div className="flex items-center gap-2.5">
            <span className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-[#ff3d9a] via-[#6d38d6] to-[#3a35e0] text-xs font-extrabold text-white shadow-lg shadow-[#6d38d6]/30">1F</span>
            <p className="text-lg font-extrabold text-white">OneFlo</p>
          </div>
          <p className="mt-4 max-w-xs text-sm leading-6">Work, people, and careers for every business. Find openings across our businesses in one place.</p>
          {contact ? (
            <ul className="mt-4 space-y-1 text-sm">
              {contact.contact_email ? <li><a href={`mailto:${contact.contact_email}`} className="hover:text-white">{contact.contact_email}</a></li> : null}
              {contact.contact_phone ? <li>{contact.contact_phone}</li> : null}
            </ul>
          ) : null}
        </div>
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-white">For candidates</p>
          <ul className="mt-3 space-y-2 text-sm">
            <li><Link to="/careers" className="hover:text-white">Browse jobs</Link></li>
            <li><Link to="/careers/login?mode=register" className="hover:text-white">Create profile</Link></li>
            <li><Link to="/careers/profile" className="hover:text-white">My applications</Link></li>
          </ul>
        </div>
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-white">Businesses</p>
          <ul className="mt-3 space-y-2 text-sm">{companies.map((company) => <li key={company.slug}><Link to={`/careers/${company.slug}`} className="hover:text-white">{company.name}</Link></li>)}</ul>
        </div>
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-white">For employers</p>
          <ul className="mt-3 space-y-2 text-sm">
            <li><Link to="/login" className="hover:text-white">Employee login</Link></li>
            <li><Link to="/careers#privacy" className="hover:text-white">Privacy policy</Link></li>
            <li><Link to="/careers#terms" className="hover:text-white">Terms and conditions</Link></li>
          </ul>
        </div>
      </div>
      <div className="border-t border-white/10">
        <div className="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-xs sm:flex-row sm:items-center sm:justify-between sm:px-6">
          <p>© {new Date().getFullYear()} OneFlo</p>
          <p className="text-white/50">Careers, people, and work in one place</p>
        </div>
      </div>
    </footer>
  )
}

export function PublicShell({ children, companies }: { children: ReactNode; companies?: PublicCompany[] }) {
  useScrollToHash()
  return (
    <div className="flex min-h-svh flex-col bg-[#f6f5fd] text-slate-900">
      <PublicHeader />
      <div className="flex-1">{children}</div>
      <PublicFooter companies={companies} />
    </div>
  )
}
