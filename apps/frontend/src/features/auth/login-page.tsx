import { Building2, CalendarCheck, Eye, EyeOff, KeyRound, Lock, Mail, ShieldCheck, Wallet } from 'lucide-react'
import { useEffect, useState, type FormEvent, type ReactNode } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router'

import { useSession } from '@/app/session'
import { PublicShell } from '@/features/home/public-layout'
import { ApiError, ensureCsrfCookie } from '@/lib/api'

export function LoginPage() {
  const { account, status, signIn } = useSession()
  const navigate = useNavigate()
  const location = useLocation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [oneTimePassword, setOneTimePassword] = useState('')
  const [needsCode, setNeedsCode] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)

  useEffect(() => {
    void ensureCsrfCookie().catch(() => undefined)
  }, [])
  const destination = typeof location.state === 'object' && location.state && 'from' in location.state && typeof location.state.from === 'string'
    ? location.state.from
    : '/'

  if (status === 'ready' && account) {
    return <Navigate to={destination} replace />
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setPending(true)
    setError(null)
    try {
      await signIn(email, password, needsCode ? oneTimePassword : undefined)
      void navigate(destination, { replace: true })
    } catch (caught) {
      if (caught instanceof ApiError) {
        if (caught.errors.one_time_password) {
          setNeedsCode(true)
        }
        setError(caught.errors.email?.[0] ?? caught.errors.one_time_password?.[0] ?? caught.message)
      } else {
        setError('Sign-in could not be completed.')
      }
    } finally {
      setPending(false)
    }
  }

  return (
    <AuthFrame title="Sign in" description="Use the work account issued by your organization.">
      <form className="grid gap-5" onSubmit={(event) => void onSubmit(event)}>
        <AuthInput label="Work email" icon={<Mail className="size-4" />}>
          <input id="email" type="email" autoComplete="username" value={email} onChange={(event) => setEmail(event.target.value)} required placeholder="name@company.com" className={authInputClass} />
        </AuthInput>
        <AuthInput label="Password" icon={<Lock className="size-4" />} action={<Link className="text-xs font-semibold text-[#3a35e0] hover:underline" to="/forgot-password">Forgot password?</Link>}>
          <input id="password" type={showPassword ? 'text' : 'password'} autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} required placeholder="Your password" className={authInputClass} />
          <button type="button" onClick={() => setShowPassword(!showPassword)} aria-label={showPassword ? 'Hide password' : 'Show password'} className="text-slate-400 hover:text-slate-700">{showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}</button>
        </AuthInput>
        {needsCode ? (
          <AuthInput label="Authentication code" icon={<KeyRound className="size-4" />}>
            <input id="otp" inputMode="numeric" autoComplete="one-time-code" value={oneTimePassword} onChange={(event) => setOneTimePassword(event.target.value)} required placeholder="6-digit code from your app" className={authInputClass} />
          </AuthInput>
        ) : null}
        {error ? <p className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p> : null}
        <button type="submit" disabled={pending || status === 'loading'} className={authButtonClass}>
          {pending ? 'Signing in…' : 'Sign in to workspace'}
        </button>
        <p className="text-center text-sm text-slate-600">Looking for a job? <Link to="/careers/login" className="font-semibold text-[#3a35e0]">Candidate sign in</Link></p>
      </form>
    </AuthFrame>
  )
}

export const authInputClass = 'h-12 w-full bg-transparent text-sm outline-none placeholder:text-slate-400'
export const authButtonClass = 'h-12 w-full rounded-xl bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] text-sm font-bold text-white shadow-lg shadow-[#6d38d6]/30 transition hover:shadow-xl disabled:opacity-60'

export function AuthInput({ label, icon, action, children }: { label: string; icon: ReactNode; action?: ReactNode; children: ReactNode }) {
  return (
    <label className="block text-sm">
      <span className="flex items-center justify-between font-semibold text-slate-800">{label}{action}</span>
      <span className="mt-1.5 flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 transition focus-within:border-[#3a35e0] focus-within:ring-4 focus-within:ring-[#3a35e0]/10">
        <span className="text-slate-400">{icon}</span>
        {children}
      </span>
    </label>
  )
}

export function AuthFrame({
  title,
  description,
  children,
}: {
  title: string
  description: string
  children: ReactNode
}) {
  return (
    <PublicShell>
      <main className="px-4 py-10 sm:py-14">
        <div className="mx-auto grid max-w-5xl overflow-hidden rounded-[2rem] bg-white shadow-[0_40px_100px_rgb(58_53_224_/_0.15)] ring-1 ring-slate-100 lg:grid-cols-[1fr_1fr]">
          <div className="relative hidden overflow-hidden bg-[#140f45] p-10 text-white lg:flex lg:flex-col">
            <div className="pointer-events-none absolute -right-24 -top-24 size-80 rounded-full bg-[#6d38d6] opacity-70 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-28 -left-16 size-96 rounded-full bg-[#3a35e0] opacity-60 blur-3xl" />
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(255_255_255_/_0.08)_1px,transparent_1px)] [background-size:20px_20px]" />
            <div className="relative">
              <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold backdrop-blur"><Building2 className="size-3.5 text-[#ffb3d9]" /> Employee workspace</span>
              <h2 className="mt-6 text-4xl font-extrabold leading-tight">Your work day,<br /><span className="bg-gradient-to-r from-[#a5a2f2] to-[#ffb3d9] bg-clip-text text-transparent">all in one place.</span></h2>
              <p className="mt-4 max-w-sm text-white/70">For employees, managers, HR, and admins of businesses on OneFlo.</p>
            </div>
            <ul className="relative mt-auto grid gap-3 pt-10">
              {[
                { icon: CalendarCheck, title: 'Attendance & leave', text: 'Check in, apply leave, view balances' },
                { icon: Wallet, title: 'Payslips & expenses', text: 'Locked payroll and reimbursements' },
                { icon: ShieldCheck, title: 'Secure by business', text: 'You only see businesses assigned to you' },
              ].map(({ icon: Icon, title, text }) => (
                <li key={title} className="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                  <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#3a35e0] to-[#e23d9a]"><Icon className="size-5" /></span>
                  <span><span className="block text-sm font-bold">{title}</span><span className="text-xs text-white/65">{text}</span></span>
                </li>
              ))}
            </ul>
          </div>
          <section className="p-6 sm:p-10 lg:p-12">
            <span className="inline-flex items-center gap-2 rounded-full bg-[#eeedfc] px-3 py-1 text-xs font-semibold text-[#3a35e0]"><Building2 className="size-3.5" /> Employee login</span>
            <h1 className="mt-4 text-2xl font-extrabold tracking-tight sm:text-3xl">
              {title === 'Sign in' ? <>Sign in to <span className="bg-gradient-to-r from-[#3a35e0] to-[#e23d9a] bg-clip-text text-transparent">OneFlo</span></> : title}
            </h1>
            <p className="mb-7 mt-2 text-sm text-slate-600">{description}</p>
            {children}
          </section>
        </div>
      </main>
    </PublicShell>
  )
}
