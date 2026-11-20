import { useQuery, useQueryClient } from '@tanstack/react-query'
import { BadgeCheck, Download, FileText } from 'lucide-react'
import { useState } from 'react'
import { useParams } from 'react-router'

import { CareersShell } from '@/features/recruitment/careers-page'
import { apiFetch } from '@/lib/api'

type PublicOffer = { designation: string; compensation_note: string; status: 'sent' | 'accepted' | 'declined'; company: string | null; candidate: string | null }

async function publicRequest<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await apiFetch(path, init)
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(payload.errors?.status?.[0] ?? payload.message ?? 'This offer link is not valid.')
  return payload.data as T
}

export function OfferResponsePage() {
  const { token } = useParams()
  const queryClient = useQueryClient()
  const offer = useQuery({ queryKey: ['public-offer', token], queryFn: () => publicRequest<PublicOffer>(`/api/v1/careers/offer-response/${token}`) })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function respond(decision: 'accept' | 'decline') {
    if (decision === 'decline' && !window.confirm('Decline this offer? This cannot be undone.')) return
    setBusy(true)
    setError(null)
    try {
      await publicRequest(`/api/v1/careers/offer-response/${token}/${decision}`, { method: 'POST' })
      await queryClient.invalidateQueries({ queryKey: ['public-offer', token] })
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Your answer could not be saved.')
    } finally {
      setBusy(false)
    }
  }

  async function letter() {
    const response = await apiFetch(`/api/v1/careers/offer-response/${token}/letter`)
    if (!response.ok) { setError('The offer letter could not be downloaded.'); return }
    const url = URL.createObjectURL(await response.blob())
    const link = document.createElement('a')
    link.href = url
    link.download = 'offer-letter.pdf'
    link.click()
    URL.revokeObjectURL(url)
  }

  const data = offer.data

  return (
    <CareersShell>
      <main className="mx-auto max-w-xl px-4 py-16">
        {offer.isPending ? <div className="h-48 animate-pulse rounded-3xl bg-white" /> : offer.isError || !data ? (
          <div className="rounded-3xl bg-white p-8 text-center shadow-sm"><p className="text-lg font-bold text-slate-900">This offer link is not valid</p><p className="mt-2 text-sm text-slate-500">It may have expired or the address is incomplete. Contact the HR team that sent it.</p></div>
        ) : (
          <div className="rounded-3xl border border-[#ffd1e8] bg-gradient-to-br from-[#fff3f9] via-white to-[#eef4ff] p-8 shadow-[0_18px_50px_rgb(226_61_154_/_0.12)]">
            <p className="text-xs font-semibold uppercase tracking-wide text-[#6d38d6]">{data.company}</p>
            <h1 className="mt-1 text-2xl font-extrabold text-slate-950">Offer for {data.designation}</h1>
            <p className="mt-1 text-sm text-slate-500">{data.candidate ? `Dear ${data.candidate}, ` : ''}your offer letter is ready. No account is needed.</p>
            <p className="mt-4 flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-700"><FileText className="size-4 text-[#6d38d6]" />{data.compensation_note}</p>
            {error ? <p role="alert" className="mt-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-700">{error}</p> : null}
            {data.status === 'sent' ? (
              <div className="mt-5 flex flex-wrap gap-2">
                <button type="button" onClick={() => void letter()} className="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700"><Download className="size-4" />Offer letter</button>
                <button type="button" disabled={busy} onClick={() => void respond('accept')} className="inline-flex h-11 items-center gap-2 rounded-xl bg-gradient-to-r from-[#10b981] to-[#06b6d4] px-5 text-sm font-semibold text-white disabled:opacity-60"><BadgeCheck className="size-4" />Accept offer</button>
                <button type="button" disabled={busy} onClick={() => void respond('decline')} className="inline-flex h-11 items-center rounded-xl border border-rose-200 bg-white px-4 text-sm font-semibold text-rose-700 disabled:opacity-60">Decline</button>
              </div>
            ) : (
              <p className={`mt-5 rounded-xl p-4 text-sm font-semibold ${data.status === 'accepted' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>{data.status === 'accepted' ? 'You accepted this offer. HR will share your joining date and appointment letter by email.' : 'You declined this offer. Thank you for letting us know.'}</p>
            )}
          </div>
        )}
      </main>
    </CareersShell>
  )
}
