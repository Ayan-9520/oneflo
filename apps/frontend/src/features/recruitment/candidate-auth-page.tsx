import { useQuery, useQueryClient } from '@tanstack/react-query'
import { BadgeCheck, Briefcase, Eye, EyeOff, GraduationCap, KeyRound, Lock, Mail, ShieldCheck, Smartphone, Sparkles, UserRound } from 'lucide-react'
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'

import { CareersShell } from '@/features/recruitment/careers-page'
import { apiFetch } from '@/lib/api'

type Session = { token: string }
type Errors = Record<string, string>

const tokenKey = 'oneflo.candidate_token'
const returnKey = 'oneflo.candidate_return'

async function post<T>(path: string, body: unknown): Promise<T> {
  const response = await apiFetch(path, { method: 'POST', body: JSON.stringify(body) })
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) {
    const fields: Errors = {}
    for (const [key, messages] of Object.entries((payload.errors ?? {}) as Record<string, string[]>)) fields[key] = messages[0]
    throw Object.assign(new Error(response.status === 429 ? 'Too many attempts. Please wait a minute and try again.' : Object.values(fields)[0] ?? payload.message ?? 'Something went wrong. Please try again.'), { fields })
  }
  return payload.data as T
}

function fieldsOf(caught: unknown): Errors {
  return (caught as { fields?: Errors }).fields ?? {}
}

function strength(password: string) {
  let score = 0
  if (password.length >= 8) score++
  if (/[a-z]/i.test(password) && /\d/.test(password)) score++
  if (/[^a-z\d]/i.test(password) || password.length >= 12) score++
  return score
}

function Field({ label, hint, error, icon, children }: { label: string; hint?: string; error?: string; icon: ReactNode; children: ReactNode }) {
  return (
    <label className="block text-sm">
      <span className="font-semibold text-slate-800">{label}</span>
      <span className={`mt-1.5 flex items-center gap-2 rounded-xl border bg-white px-3 transition focus-within:ring-4 ${error ? 'border-red-300 focus-within:ring-red-100' : 'border-slate-200 focus-within:border-[#3a35e0] focus-within:ring-[#3a35e0]/10'}`}>
        <span className="text-slate-400">{icon}</span>
        {children}
      </span>
      {error ? <span className="mt-1 block text-xs text-red-600">{error}</span> : hint ? <span className="mt-1 block text-xs text-slate-500">{hint}</span> : null}
    </label>
  )
}

const input = 'h-12 w-full bg-transparent text-sm outline-none placeholder:text-slate-400'

function CodeBoxes({ value, onChange }: { value: string; onChange: (value: string) => void }) {
  const refs = useRef<Array<HTMLInputElement | null>>([])
  return (
    <div className="flex justify-between gap-2" onPaste={(event) => { const digits = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6); if (digits) { event.preventDefault(); onChange(digits); refs.current[Math.min(digits.length, 5)]?.focus() } }}>
      {Array.from({ length: 6 }, (_, index) => (
        <input
          key={index}
          ref={(element) => { refs.current[index] = element }}
          value={value[index] ?? ''}
          inputMode="numeric"
          autoComplete={index === 0 ? 'one-time-code' : 'off'}
          aria-label={`Digit ${index + 1}`}
          autoFocus={index === 0}
          maxLength={1}
          onChange={(event) => {
            const digit = event.target.value.replace(/\D/g, '').slice(-1)
            const next = (value.slice(0, index) + digit + value.slice(index + 1)).slice(0, 6)
            onChange(next)
            if (digit) refs.current[index + 1]?.focus()
          }}
          onKeyDown={(event) => { if (event.key === 'Backspace' && !value[index]) refs.current[index - 1]?.focus() }}
          className="h-14 w-full rounded-xl border border-slate-200 bg-white text-center text-xl font-bold text-slate-900 outline-none transition focus:border-[#3a35e0] focus:ring-4 focus:ring-[#3a35e0]/10"
        />
      ))}
    </div>
  )
}

function SidePanel({ register }: { register: boolean }) {
  const jobs = useQuery({ queryKey: ['careers', 'home'], queryFn: async () => (await (await apiFetch('/api/v1/careers')).json()).data as { jobs: { total: number }; companies: unknown[] }, retry: false })
  return (
    <div className="relative hidden overflow-hidden bg-[#140f45] p-10 text-white lg:flex lg:flex-col">
      <div className="pointer-events-none absolute -left-24 -top-24 size-80 rounded-full bg-[#3a35e0] opacity-70 blur-3xl" />
      <div className="pointer-events-none absolute -bottom-28 -right-16 size-96 rounded-full bg-[#e23d9a] opacity-50 blur-3xl" />
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(255_255_255_/_0.08)_1px,transparent_1px)] [background-size:20px_20px]" />
      <div className="relative">
        <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold backdrop-blur"><Sparkles className="size-3.5 text-[#ffb3d9]" /> OneFlo Careers</span>
        <h2 className="mt-6 text-4xl font-extrabold leading-tight">{register ? <>Your next role<br />starts with <span className="bg-gradient-to-r from-[#a5a2f2] to-[#ffb3d9] bg-clip-text text-transparent">one profile.</span></> : <>Welcome<br /><span className="bg-gradient-to-r from-[#a5a2f2] to-[#ffb3d9] bg-clip-text text-transparent">back.</span></>}</h2>
        <p className="mt-4 max-w-sm text-white/70">{register ? 'Create it once. Every application fills itself.' : 'Track applications and apply faster with your saved profile.'}</p>
      </div>
      <div className="relative mt-10 grid grid-cols-2 gap-3">
        <div className="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur"><p className="text-3xl font-extrabold">{jobs.data?.jobs.total ?? '–'}</p><p className="mt-1 text-xs text-white/70">Live openings</p></div>
        <div className="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur"><p className="text-3xl font-extrabold">{jobs.data?.companies.length ?? '–'}</p><p className="mt-1 text-xs text-white/70">Hiring businesses</p></div>
      </div>
      <ul className="relative mt-auto space-y-4 pt-10 text-sm">
        {[
          { icon: BadgeCheck, text: 'Verified email keeps fake profiles out' },
          { icon: Briefcase, text: 'Apply across every business in one click' },
          { icon: ShieldCheck, text: 'Your resume is private. Only companies you apply to see it' },
        ].map(({ icon: Icon, text }) => <li key={text} className="flex items-center gap-3"><span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/10"><Icon className="size-4 text-[#ffb3d9]" /></span>{text}</li>)}
      </ul>
    </div>
  )
}

export function CandidateLoginPage() {
  const [search] = useSearchParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const register = search.get('mode') === 'register'

  const [form, setForm] = useState({ full_name: '', email: '', password: '', phone: '', work_status: '', marketing_opt_in: false })
  const [step, setStep] = useState<'form' | 'verify'>('form')
  const [codeMode, setCodeMode] = useState(false)
  const [code, setCode] = useState('')
  const [fullName, setFullName] = useState('')
  const [needsName, setNeedsName] = useState(false)
  const [devCode, setDevCode] = useState<string | null>(null)
  const [showPassword, setShowPassword] = useState(false)
  const [errors, setErrors] = useState<Errors>({})
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)
  const [cooldown, setCooldown] = useState(0)

  useEffect(() => {
    const next = search.get('next')
    if (next?.startsWith('/careers') || next === '/home' || next.startsWith('/in/')) window.sessionStorage.setItem(returnKey, next)
  }, [search])
  useEffect(() => {
    setStep('form'); setCodeMode(false); setCode(''); setErrors({}); setError(null); setNeedsName(false); setDevCode(null)
  }, [register])
  useEffect(() => {
    if (cooldown <= 0) return
    const timer = window.setTimeout(() => setCooldown(cooldown - 1), 1000)
    return () => window.clearTimeout(timer)
  }, [cooldown])

  const set = (key: keyof typeof form, value: string | boolean) => { setForm({ ...form, [key]: value }); setErrors({ ...errors, [key]: '' }) }

  async function finish(session: Session) {
    window.localStorage.setItem(tokenKey, session.token)
    await queryClient.invalidateQueries({ queryKey: ['candidate-me'] })
    const target = window.sessionStorage.getItem(returnKey)
    window.sessionStorage.removeItem(returnKey)
    navigate(target && (target.startsWith('/careers') || target === '/home' || target.startsWith('/in/')) ? target : '/careers/profile', { replace: true })
  }

  async function run(action: () => Promise<void>) {
    setError(null)
    setPending(true)
    try {
      await action()
    } catch (caught) {
      const fields = fieldsOf(caught)
      setErrors(fields)
      if (/full name/i.test(caught instanceof Error ? caught.message : '')) {
        setNeedsName(true)
        setError('No profile exists for this email yet. Enter your name to create one.')
      } else {
        setError(caught instanceof Error ? caught.message : 'Something went wrong.')
      }
    } finally {
      setPending(false)
    }
  }

  async function sendCode(purpose: 'register' | 'sign_in') {
    const result = await post<{ dev_code?: string }>('/api/v1/candidate/auth/code', { channel: 'email', destination: form.email, purpose })
    setDevCode(result.dev_code ?? null)
    setCode('')
    setStep('verify')
    setCooldown(30)
  }

  function submitRegister(event: FormEvent) {
    event.preventDefault()
    const local: Errors = {}
    if (!form.work_status) local.work_status = 'Choose your work status.'
    if (strength(form.password) < 2) local.password = 'Use at least 8 characters with letters and numbers.'
    if (!/^[6-9]\d{9}$/.test(form.phone.replace(/\D/g, '').slice(-10))) local.phone = 'Enter a valid 10-digit mobile number.'
    if (Object.keys(local).length) { setErrors(local); return }
    void run(() => sendCode('register'))
  }

  function submitVerify(event: FormEvent) {
    event.preventDefault()
    if (code.length !== 6) { setError('Enter the 6-digit code.'); return }
    void run(async () => {
      if (register) {
        await finish(await post<Session>('/api/v1/candidate/auth/register', { ...form, terms: true, code }))
      } else {
        await finish(await post<Session>('/api/v1/candidate/auth/code/verify', { channel: 'email', destination: form.email, code, full_name: fullName || null }))
      }
    })
  }

  function submitSignIn(event: FormEvent) {
    event.preventDefault()
    void run(async () => {
      if (codeMode) await sendCode('sign_in')
      else await finish(await post<Session>('/api/v1/candidate/auth/password', { email: form.email, password: form.password }))
    })
  }

  const score = strength(form.password)
  const meter = ['bg-slate-200', 'bg-red-400', 'bg-amber-400', 'bg-emerald-500']
  const banner = error ? <p className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p> : null
  const submitClass = 'h-12 w-full rounded-xl bg-gradient-to-r from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] text-sm font-bold text-white shadow-lg shadow-[#6d38d6]/30 transition hover:shadow-xl hover:shadow-[#6d38d6]/40 disabled:opacity-60'

  return (
    <CareersShell>
      <main className="px-4 py-10 sm:py-14">
        <div className="mx-auto grid max-w-6xl overflow-hidden rounded-[2rem] bg-white shadow-[0_40px_100px_rgb(58_53_224_/_0.15)] ring-1 ring-slate-100 lg:grid-cols-[0.9fr_1.1fr]">
          <SidePanel register={register} />
          <div className="p-6 sm:p-10 lg:p-12">
            <div className="mb-8 grid grid-cols-2 rounded-xl bg-slate-100 p-1 text-sm font-semibold">
              <Link to="/careers/login" className={`rounded-lg py-2.5 text-center transition ${!register ? 'bg-white text-[#3a35e0] shadow-sm' : 'text-slate-500 hover:text-slate-800'}`}>Sign in</Link>
              <Link to="/careers/login?mode=register" className={`rounded-lg py-2.5 text-center transition ${register ? 'bg-white text-[#3a35e0] shadow-sm' : 'text-slate-500 hover:text-slate-800'}`}>Register</Link>
            </div>

            {step === 'verify' ? (
              <form onSubmit={submitVerify} className="mx-auto grid max-w-md gap-5">
                <span className="flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#3a35e0] to-[#e23d9a] text-white"><Mail className="size-6" /></span>
                <div>
                  <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Check your email</h1>
                  <p className="mt-2 text-sm text-slate-600">We sent a 6-digit code to <span className="font-semibold text-slate-900">{form.email}</span>. It expires in 10 minutes.</p>
                </div>
                {devCode ? <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Local test mode: email delivery is not set up. Your code is <span className="font-bold tracking-[0.3em]">{devCode}</span></p> : null}
                <CodeBoxes value={code} onChange={setCode} />
                {needsName ? (
                  <Field label="Full name" icon={<UserRound className="size-4" />}>
                    <input value={fullName} onChange={(event) => setFullName(event.target.value)} required autoComplete="name" placeholder="What is your name?" className={input} />
                  </Field>
                ) : null}
                {banner}
                <button type="submit" disabled={pending} className={submitClass}>{pending ? 'Verifying…' : register ? 'Verify & create profile' : 'Verify & sign in'}</button>
                <div className="flex items-center justify-between text-sm">
                  <button type="button" onClick={() => { setStep('form'); setError(null) }} className="font-semibold text-slate-600 hover:text-slate-900">← Edit details</button>
                  <button type="button" disabled={cooldown > 0 || pending} onClick={() => void run(() => sendCode(register ? 'register' : 'sign_in'))} className="font-semibold text-[#3a35e0] disabled:text-slate-400">{cooldown > 0 ? `Resend in ${cooldown}s` : 'Resend code'}</button>
                </div>
              </form>
            ) : register ? (
              <form onSubmit={submitRegister} className="grid gap-5">
                <div>
                  <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Create your OneFlo profile</h1>
                  <p className="mt-2 text-sm text-slate-600">Search and apply to jobs across our growing businesses.</p>
                </div>
                <Field label="Full name" error={errors.full_name} icon={<UserRound className="size-4" />}>
                  <input value={form.full_name} onChange={(event) => set('full_name', event.target.value)} required autoComplete="name" placeholder="What is your name?" className={input} />
                </Field>
                <div className="grid gap-5 sm:grid-cols-2">
                  <Field label="Email ID" hint="We'll send relevant jobs and updates here" error={errors.email ?? errors.destination} icon={<Mail className="size-4" />}>
                    <input value={form.email} onChange={(event) => set('email', event.target.value)} required type="email" autoComplete="email" placeholder="Tell us your email ID" className={input} />
                  </Field>
                  <Field label="Mobile number" hint="Recruiters will call you on this number" error={errors.phone} icon={<span className="flex items-center gap-1.5"><Smartphone className="size-4" /><span className="text-sm font-semibold text-slate-600">+91</span></span>}>
                    <input value={form.phone} onChange={(event) => set('phone', event.target.value.replace(/[^\d ]/g, '').slice(0, 11))} required type="tel" inputMode="numeric" autoComplete="tel-national" placeholder="Enter your mobile number" className={input} />
                  </Field>
                </div>
                <div>
                  <Field label="Password" error={errors.password} icon={<Lock className="size-4" />}>
                    <input value={form.password} onChange={(event) => set('password', event.target.value)} required type={showPassword ? 'text' : 'password'} autoComplete="new-password" placeholder="Minimum 8 characters" className={input} />
                    <button type="button" onClick={() => setShowPassword(!showPassword)} aria-label={showPassword ? 'Hide password' : 'Show password'} className="text-slate-400 hover:text-slate-700">{showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}</button>
                  </Field>
                  {form.password ? (
                    <div className="mt-2 flex items-center gap-2">
                      <div className="flex flex-1 gap-1">{[1, 2, 3].map((level) => <span key={level} className={`h-1.5 flex-1 rounded-full ${score >= level ? meter[score] : 'bg-slate-200'}`} />)}</div>
                      <span className="text-xs font-medium text-slate-500">{['Too short', 'Weak', 'Good', 'Strong'][score]}</span>
                    </div>
                  ) : null}
                </div>
                <fieldset>
                  <legend className="text-sm font-semibold text-slate-800">Work status</legend>
                  <div className="mt-2 grid gap-3 sm:grid-cols-2">
                    {[
                      { value: 'experienced', title: "I'm experienced", text: 'I have work experience (excluding internships)', icon: Briefcase },
                      { value: 'fresher', title: "I'm a fresher", text: 'I am a student or have not worked after graduation', icon: GraduationCap },
                    ].map(({ value, title, text, icon: Icon }) => {
                      const active = form.work_status === value
                      return (
                        <label key={value} className={`relative flex cursor-pointer gap-3 rounded-2xl border-2 p-4 transition ${active ? 'border-[#3a35e0] bg-[#eeedfc]/60 shadow-[0_8px_24px_rgb(58_53_224_/_0.12)]' : 'border-slate-200 hover:border-[#a5a2f2]'}`}>
                          <input type="radio" name="work_status" value={value} checked={active} onChange={() => set('work_status', value)} className="sr-only" />
                          <span className={`flex size-11 shrink-0 items-center justify-center rounded-xl ${active ? 'bg-gradient-to-br from-[#3a35e0] to-[#e23d9a] text-white' : 'bg-slate-100 text-slate-500'}`}><Icon className="size-5" /></span>
                          <span><span className="block font-bold text-slate-900">{title}</span><span className="mt-0.5 block text-xs text-slate-500">{text}</span></span>
                          {active ? <BadgeCheck className="absolute right-3 top-3 size-5 text-[#3a35e0]" /> : null}
                        </label>
                      )
                    })}
                  </div>
                  {errors.work_status ? <p className="mt-1 text-xs text-red-600">{errors.work_status}</p> : null}
                </fieldset>
                <label className="flex items-start gap-3 text-sm text-slate-600">
                  <input type="checkbox" checked={form.marketing_opt_in} onChange={(event) => set('marketing_opt_in', event.target.checked)} className="mt-0.5 size-4 accent-[#3a35e0]" />
                  Send me important job updates and alerts by email
                </label>
                {banner}
                <button type="submit" disabled={pending} className={submitClass}>{pending ? 'Sending code…' : 'Register now'}</button>
                <p className="text-center text-xs text-slate-500">By clicking Register, you agree to the <Link to="/careers#terms" className="font-semibold text-[#3a35e0]">Terms and Conditions</Link> & <Link to="/careers#privacy" className="font-semibold text-[#3a35e0]">Privacy Policy</Link> of OneFlo.</p>
              </form>
            ) : (
              <form onSubmit={submitSignIn} className="mx-auto grid max-w-md gap-5">
                <div>
                  <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Sign in to your profile</h1>
                  <p className="mt-2 text-sm text-slate-600">{codeMode ? 'We will email you a one-time code.' : 'Use the email and password you registered with.'}</p>
                </div>
                <Field label="Email ID" error={errors.email ?? errors.destination} icon={<Mail className="size-4" />}>
                  <input value={form.email} onChange={(event) => set('email', event.target.value)} required type="email" autoComplete="email" placeholder="you@gmail.com" className={input} />
                </Field>
                {!codeMode ? (
                  <Field label="Password" icon={<Lock className="size-4" />}>
                    <input value={form.password} onChange={(event) => set('password', event.target.value)} required type={showPassword ? 'text' : 'password'} autoComplete="current-password" placeholder="Your password" className={input} />
                    <button type="button" onClick={() => setShowPassword(!showPassword)} aria-label={showPassword ? 'Hide password' : 'Show password'} className="text-slate-400 hover:text-slate-700">{showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}</button>
                  </Field>
                ) : null}
                {banner}
                <button type="submit" disabled={pending} className={submitClass}>{pending ? 'Please wait…' : codeMode ? 'Email me a code' : 'Sign in'}</button>
                <button type="button" onClick={() => { setCodeMode(!codeMode); setError(null) }} className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 py-3 text-sm font-semibold text-slate-700 hover:border-[#a5a2f2]">
                  <KeyRound className="size-4" />{codeMode ? 'Use password instead' : 'Forgot password? Sign in with a code'}
                </button>
                <p className="text-center text-sm text-slate-600">New to OneFlo? <Link to="/careers/login?mode=register" className="font-semibold text-[#3a35e0]">Create your profile</Link></p>
                <p className="text-center text-xs text-slate-500">Employee or HR? <Link to="/login" className="font-semibold text-[#3a35e0]">Use employee login</Link></p>
              </form>
            )}
          </div>
        </div>
      </main>
    </CareersShell>
  )
}
