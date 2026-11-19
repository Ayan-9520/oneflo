import { useEffect, useState, type FormEvent, type ReactNode } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router'

import { useSession } from '@/app/session'
import { BrandMark } from '@/components/brand-mark'
import { Button } from '@/components/ui/button'
import { Field, Input } from '@/components/ui/input'
import { ApiError, ensureCsrfCookie } from '@/lib/api'

export function LoginPage() {
  const { account, status, signIn } = useSession()
  const navigate = useNavigate()
  const location = useLocation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
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
    <AuthFrame title="Sign in" description="Use the account issued for your OneFlo organization.">
      <form className="flex flex-col gap-4" onSubmit={(event) => void onSubmit(event)}>
        <Field label="Email" htmlFor="email" error={undefined}>
          <Input id="email" type="email" autoComplete="username" value={email} onChange={(event) => setEmail(event.target.value)} required />
        </Field>
        <Field label="Password" htmlFor="password">
          <Input id="password" type="password" autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} required />
        </Field>
        {needsCode ? (
          <Field label="Authentication code" htmlFor="otp">
            <Input id="otp" inputMode="numeric" autoComplete="one-time-code" value={oneTimePassword} onChange={(event) => setOneTimePassword(event.target.value)} required />
          </Field>
        ) : null}
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <Button type="submit" disabled={pending || status === 'loading'}>
          {pending ? 'Signing in…' : 'Sign in'}
        </Button>
        <Link className="text-sm text-primary" to="/forgot-password">
          Forgot password
        </Link>
      </form>
    </AuthFrame>
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
    <main className="grid min-h-svh place-items-center bg-white px-4 py-10">
      <div className="pointer-events-none fixed inset-x-0 top-0 h-1.5 bg-gradient-to-r from-[#2a1878] via-[#7a2cff] to-[#ff2d87]" />
      <section className="w-full max-w-md rounded-3xl border border-border bg-card p-7 shadow-[var(--shadow-card)]">
        <BrandMark />
        <h1 className="mt-4 text-3xl font-extrabold tracking-tight">
          {title === 'Sign in' ? (
            <>
              Sign in to <span className="text-[#ff2d87]">OneFlo</span>
            </>
          ) : (
            title
          )}
        </h1>
        <p className="mt-1 mb-6 text-sm text-muted-foreground">{description}</p>
        {children}
      </section>
    </main>
  )
}
