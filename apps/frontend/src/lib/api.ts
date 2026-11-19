export class ApiError extends Error {
  readonly status: number

  readonly errors: Record<string, string[]>

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

export async function readJson(response: Response): Promise<unknown> {
  const text = await response.text()
  if (text.length === 0) {
    return null
  }

  try {
    return JSON.parse(text) as unknown
  } catch {
    throw new ApiError('The server returned a response that was not valid JSON.', response.status)
  }
}

let organizationId: number | null = null
let csrfCookie: Promise<void> | null = null

export function setApiOrganizationId(id: number | null) {
  organizationId = id
}

export function ensureCsrfCookie(): Promise<void> {
  csrfCookie ??= fetch('/sanctum/csrf-cookie', {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  }).then((response) => {
    if (!response.ok) {
      throw new ApiError('The sign-in session could not be started.', response.status)
    }
  }).catch((error: unknown) => {
    csrfCookie = null
    throw error
  })

  return csrfCookie
}

function xsrfToken(): string | null {
  const entry = document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='))
  if (!entry) {
    return null
  }

  return decodeURIComponent(entry.slice('XSRF-TOKEN='.length))
}

export async function apiFetch(path: string, init: RequestInit = {}): Promise<Response> {
  const method = (init.method ?? 'GET').toUpperCase()
  if (method !== 'GET' && method !== 'HEAD') {
    await ensureCsrfCookie()
  }

  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  if (organizationId !== null) {
    headers.set('X-Organization-Id', String(organizationId))
  }

  const token = xsrfToken()
  if (token) {
    headers.set('X-XSRF-TOKEN', token)
  }

    if (init.body && !(init.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  return fetch(path, {
    ...init,
    headers,
    credentials: 'include',
  })
}

function errorFromBody(body: unknown, status: number): ApiError {
  let message = status === 401 ? 'Authentication is required.' : 'The request could not be completed.'
  let errors: Record<string, string[]> = {}

  if (typeof body === 'object' && body !== null) {
    if ('message' in body && typeof body.message === 'string' && body.message !== '') {
      message = body.message
    }
    if ('errors' in body && typeof body.errors === 'object' && body.errors !== null) {
      errors = Object.fromEntries(
        Object.entries(body.errors).flatMap(([field, messages]) => {
          if (!Array.isArray(messages)) {
            return []
          }
          return [[field, messages.filter((message): message is string => typeof message === 'string')]]
        }),
      )
    }
  }

  return new ApiError(message, status, errors)
}

export async function apiData<T>(path: string, init?: RequestInit): Promise<T> {
  let response: Response

  try {
    response = await apiFetch(path, init)
  } catch (error) {
    if (error instanceof ApiError) {
      throw error
    }
    throw new ApiError('The OneFlo API could not be reached.', 0)
  }

  const body = await readJson(response)
  if (!response.ok) {
    throw errorFromBody(body, response.status)
  }

  if (typeof body === 'object' && body !== null && 'data' in body) {
    return (body as { data: T }).data
  }

  throw new ApiError('The server returned an unexpected response.', response.status)
}
