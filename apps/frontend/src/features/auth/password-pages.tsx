import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router'

import { AuthFrame } from '@/features/auth/login-page'
import { Button } from '@/components/ui/button'
import { Field, Input } from '@/components/ui/input'
import { ApiError, apiData } from '@/lib/api'

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setPending(true)
    setError(null)
    try {
      const result = await apiData<{ message: string }>('/api/v1/auth/forgot-password', {
        method: 'POST',
        body: JSON.stringify({ email }),
      })
      setMessage(result.message)
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The reset link could not be requested.')
    } finally {
      setPending(false)
    }
  }

  return (
    <AuthFrame title="Reset your password" description="If the account exists, OneFlo sends a reset link.">
      <form className="flex flex-col gap-4" onSubmit={(event) => void onSubmit(event)}>
        <Field label="Email" htmlFor="email">
          <Input id="email" type="email" value={email} onChange={(event) => setEmail(event.target.value)} required />
        </Field>
        {message ? <p className="text-sm text-success">{message}</p> : null}
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <Button type="submit" disabled={pending}>
          {pending ? 'Sending…' : 'Send reset link'}
        </Button>
        <Link className="text-sm text-primary" to="/login">
          Back to sign in
        </Link>
      </form>
    </AuthFrame>
  )
}

export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setPending(true)
    setError(null)
    try {
      const result = await apiData<{ message: string }>('/api/v1/auth/reset-password', {
        method: 'POST',
        body: JSON.stringify({
          email: params.get('email') ?? '',
          token: params.get('token') ?? '',
          password,
          password_confirmation: confirmation,
        }),
      })
      setMessage(result.message)
    } catch (caught) {
      setError(caught instanceof ApiError ? (caught.errors.password?.[0] ?? caught.message) : 'The password could not be reset.')
    } finally {
      setPending(false)
    }
  }

  return (
    <AuthFrame title="Choose a new password" description="Use the link from the reset message.">
      <form className="flex flex-col gap-4" onSubmit={(event) => void onSubmit(event)}>
        <Field label="New password" htmlFor="password">
          <Input id="password" type="password" autoComplete="new-password" value={password} onChange={(event) => setPassword(event.target.value)} required />
        </Field>
        <Field label="Confirm password" htmlFor="confirmation">
          <Input id="confirmation" type="password" autoComplete="new-password" value={confirmation} onChange={(event) => setConfirmation(event.target.value)} required />
        </Field>
        {message ? <p className="text-sm text-success">{message}</p> : null}
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <Button type="submit" disabled={pending}>
          {pending ? 'Saving…' : 'Update password'}
        </Button>
        <Link className="text-sm text-primary" to="/login">
          Sign in
        </Link>
      </form>
    </AuthFrame>
  )
}
