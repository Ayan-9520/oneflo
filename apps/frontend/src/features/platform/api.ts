import { ApiError, readJson } from '@/lib/api'

export type HealthCheck = {
  name: string
  status: 'ok' | 'failed'
  detail: string | null
}

export type PlatformHealth = {
  status: 'ok' | 'degraded'
  checks: HealthCheck[]
}

function isHealthCheck(value: unknown): value is HealthCheck {
  if (typeof value !== 'object' || value === null) {
    return false
  }

  const check = value as Record<string, unknown>
  return (
    typeof check.name === 'string' &&
    (check.status === 'ok' || check.status === 'failed') &&
    (check.detail === null || typeof check.detail === 'string')
  )
}

function isPlatformHealth(value: unknown): value is PlatformHealth {
  if (typeof value !== 'object' || value === null) {
    return false
  }

  const health = value as Record<string, unknown>
  return (
    (health.status === 'ok' || health.status === 'degraded') &&
    Array.isArray(health.checks) &&
    health.checks.every(isHealthCheck)
  )
}

export async function fetchPlatformHealth(): Promise<PlatformHealth> {
  let response: Response

  try {
    response = await fetch('/api/v1/ready', {
      headers: { Accept: 'application/json' },
    })
  } catch {
    throw new ApiError('The OneFlo API could not be reached.', 0)
  }

  const body = await readJson(response)
  const data =
    typeof body === 'object' && body !== null && 'data' in body
      ? (body as { data: unknown }).data
      : null

  if (!isPlatformHealth(data)) {
    throw new ApiError('The platform health check returned an unexpected response.', response.status)
  }

  return data
}
