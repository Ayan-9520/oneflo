import { useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, CheckCircle2, Copy, Download, FileSpreadsheet, FlaskConical, Loader2, RotateCcw, Upload, X, XCircle } from 'lucide-react'
import { useRef, useState, type DragEvent } from 'react'

import { Button } from '@/components/ui/button'
import { useToast } from '@/components/ui/toast'
import { ApiError, apiData, apiFetch } from '@/lib/api'

type RowStatus = 'valid' | 'invalid' | 'duplicate' | 'imported' | 'failed'
export type ImportPreview = {
  id: number
  file_name: string
  status: 'previewed' | 'importing' | 'imported' | 'cancelled'
  headers: string[]
  mapping: Record<string, string>
  fields: Array<{ key: string; label: string }>
  summary: { total: number; valid: number; invalid: number; duplicate: number; importable: number; imported: number; skipped: number; failed: number; test_data: number }
  rows: Array<{ row: number; status: RowStatus; errors: string[]; name: string; email: string | null; phone: string | null; is_test_data: boolean }>
}

const steps = ['Download template', 'Upload file', 'Preview & validate', 'Confirm import', 'Results']
const accepted = '.xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv'
const maxBytes = 5 * 1024 * 1024
const statusStyles: Record<RowStatus, string> = {
  valid: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  imported: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  invalid: 'bg-rose-50 text-rose-700 ring-rose-200',
  failed: 'bg-rose-50 text-rose-700 ring-rose-200',
  duplicate: 'bg-amber-50 text-amber-800 ring-amber-200',
}
const filters: Array<{ key: 'all' | 'problems' | RowStatus; label: string }> = [
  { key: 'all', label: 'All rows' },
  { key: 'problems', label: 'Needs attention' },
  { key: 'valid', label: 'Ready' },
]

async function download(path: string, fileName: string) {
  const response = await apiFetch(path)
  if (!response.ok) throw new ApiError('The file could not be downloaded.', response.status)
  const url = URL.createObjectURL(await response.blob())
  const link = Object.assign(document.createElement('a'), { href: url, download: fileName })
  link.click()
  URL.revokeObjectURL(url)
}

export function CandidateImportPanel({ businessName, onClose }: { businessName: string; onClose: () => void }) {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const input = useRef<HTMLInputElement>(null)
  const [preview, setPreview] = useState<ImportPreview | null>(null)
  const [busy, setBusy] = useState<'template' | 'upload' | 'mapping' | 'confirm' | 'report' | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [dragging, setDragging] = useState(false)
  const [confirming, setConfirming] = useState(false)
  const [filter, setFilter] = useState<(typeof filters)[number]['key']>('problems')

  const done = preview?.status === 'imported'
  const step = done ? 4 : confirming ? 3 : preview ? 2 : 1
  const problems = preview ? preview.summary.invalid + preview.summary.duplicate + preview.summary.failed : 0
  const visible = (preview?.rows ?? []).filter((row) => filter === 'all' || (filter === 'problems' ? !['valid', 'imported'].includes(row.status) : row.status === filter))

  async function run<T>(kind: NonNullable<typeof busy>, task: () => Promise<T>): Promise<T | null> {
    if (busy) return null
    setBusy(kind)
    setError(null)
    try {
      return await task()
    } catch (caught) {
      setError(caught instanceof ApiError ? (Object.values(caught.errors)[0]?.[0] ?? caught.message) : 'Something went wrong. Please try again.')
      return null
    } finally {
      setBusy(null)
    }
  }

  async function upload(file: File) {
    if (!/\.(xlsx|csv)$/i.test(file.name)) {
      setError(/\.xls$/i.test(file.name) ? 'Older .xls files are not supported. Save the file as Excel Workbook (.xlsx) or CSV and upload again.' : 'Upload an Excel (.xlsx) or CSV file.')
      return
    }
    if (file.size > maxBytes) {
      setError('The file is larger than 5 MB. Split it into smaller files.')
      return
    }
    const body = new FormData()
    body.append('file', file)
    const result = await run('upload', () => apiData<ImportPreview>('/api/v1/recruitment/candidate-imports', { method: 'POST', body }))
    if (result) {
      setPreview(result)
      setConfirming(false)
      setFilter(result.summary.invalid + result.summary.duplicate > 0 ? 'problems' : 'all')
    }
  }

  async function remap(column: number, field: string) {
    if (!preview) return
    const mapping = Object.fromEntries(Object.entries(preview.mapping).filter(([key, value]) => key !== String(column) && value !== field))
    if (field) mapping[String(column)] = field
    const result = await run('mapping', () => apiData<ImportPreview>(`/api/v1/recruitment/candidate-imports/${preview.id}/mapping`, { method: 'PUT', body: JSON.stringify({ mapping }) }))
    if (result) setPreview(result)
  }

  async function confirm() {
    if (!preview) return
    const result = await run('confirm', () => apiData<ImportPreview>(`/api/v1/recruitment/candidate-imports/${preview.id}/confirm`, { method: 'POST' }))
    if (result) {
      setPreview(result)
      setConfirming(false)
      setFilter('all')
      await queryClient.invalidateQueries({ queryKey: ['candidates'] })
      notify(`${result.summary.imported} candidates imported.`, 'success')
    }
  }

  function reset() {
    if (preview && preview.status === 'previewed') void apiFetch(`/api/v1/recruitment/candidate-imports/${preview.id}/cancel`, { method: 'POST' })
    setPreview(null)
    setConfirming(false)
    setError(null)
    if (input.current) input.current.value = ''
  }

  function drop(event: DragEvent<HTMLLabelElement>) {
    event.preventDefault()
    setDragging(false)
    const file = event.dataTransfer.files[0]
    if (file) void upload(file)
  }

  return (
    <section aria-labelledby="import-title" className="overflow-hidden rounded-3xl border border-[#e4dfef] bg-white shadow-[0_24px_60px_rgb(28_20_51/0.08)]">
      <div className="relative bg-gradient-to-r from-[#2a1878] via-[#5b2bd6] to-[#c2338f] px-6 py-5 text-white">
        <div className="flex items-start justify-between gap-4">
          <div className="flex items-start gap-3">
            <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25"><FileSpreadsheet className="size-5" /></span>
            <div>
              <h2 id="import-title" className="text-lg font-semibold">Import candidates</h2>
              <p className="text-sm text-white/80">Into <span className="font-semibold text-white">{businessName}</span>. Creates candidate records only, never employees or applications.</p>
            </div>
          </div>
          <button type="button" onClick={() => { reset(); onClose() }} aria-label="Close import" className="rounded-lg p-1.5 text-white/80 hover:bg-white/15 hover:text-white"><X className="size-5" /></button>
        </div>
        <ol className="mt-5 grid grid-cols-5 gap-2">
          {steps.map((label, index) => (
            <li key={label} className="flex flex-col gap-1.5">
              <span className={`h-1.5 rounded-full ${index <= step ? 'bg-white' : 'bg-white/25'}`} />
              <span className={`hidden text-xs sm:block ${index === step ? 'font-semibold text-white' : 'text-white/70'}`}>{index + 1}. {label}</span>
            </li>
          ))}
        </ol>
      </div>

      <div className="grid gap-6 p-6">
        {error ? (
          <div role="alert" className="flex items-start gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><XCircle className="mt-0.5 size-4 shrink-0" />{error}</div>
        ) : null}

        {!preview ? (
          <div className="grid gap-4 md:grid-cols-[1fr_1.4fr]">
            <div className="flex flex-col rounded-2xl border border-[#e4dfef] bg-[#faf9fe] p-5">
              <p className="text-xs font-semibold uppercase tracking-wide text-[#6d38d6]">Step 1</p>
              <h3 className="mt-1 font-semibold">Download the template</h3>
              <p className="mt-1 text-sm text-muted-foreground">Fill one candidate per row. Full name plus an email or mobile number is required. Your own headers also work, you can map them in the preview.</p>
              <ul className="mt-3 grid gap-1 text-xs text-muted-foreground">
                <li>Mark fictional rows with <code className="rounded bg-white px-1">is_test_data = YES</code> or <code className="rounded bg-white px-1">candidate_type = DUMMY</code>.</li>
                <li>Up to 2,000 rows and 5 MB per file. .xlsx or .csv.</li>
              </ul>
              <Button className="mt-4 w-fit" variant="outline" disabled={busy !== null} onClick={() => void run('template', () => download('/api/v1/recruitment/candidate-imports/template', 'oneflo-candidate-import-template.xlsx'))}>
                {busy === 'template' ? <Loader2 className="size-4 animate-spin" /> : <Download className="size-4" />} Download template
              </Button>
            </div>
            <label onDragOver={(event) => { event.preventDefault(); setDragging(true) }} onDragLeave={() => setDragging(false)} onDrop={drop}
              className={`flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed p-8 text-center transition ${dragging ? 'border-[#6d38d6] bg-[#f3efff]' : 'border-[#d9d3ea] hover:border-[#a58df0] hover:bg-[#faf9fe]'} ${busy === 'upload' ? 'pointer-events-none opacity-70' : ''}`}>
              <input ref={input} type="file" accept={accepted} className="sr-only" disabled={busy !== null} onChange={(event) => { const file = event.target.files?.[0]; if (file) void upload(file) }} />
              <span className="flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#3a35e0] to-[#e23d9a] text-white shadow-lg shadow-[#6d38d6]/25">
                {busy === 'upload' ? <Loader2 className="size-6 animate-spin" /> : <Upload className="size-6" />}
              </span>
              <p className="mt-4 font-semibold">{busy === 'upload' ? 'Reading and validating your file…' : 'Step 2: Drop your Excel or CSV here'}</p>
              <p className="mt-1 text-sm text-muted-foreground">{busy === 'upload' ? 'Nothing is saved until you confirm.' : 'or click to choose a file'}</p>
            </label>
          </div>
        ) : (
          <>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
              {(done
                ? [['Total rows', preview.summary.total, 'text-foreground'], ['Imported', preview.summary.imported, 'text-emerald-700'], ['Skipped (invalid)', preview.summary.skipped, 'text-rose-700'], ['Duplicates', preview.summary.duplicate, 'text-amber-700'], ['Failed', preview.summary.failed, 'text-rose-700'], ['Test data', preview.rows.filter((row) => row.status === 'imported' && row.is_test_data).length, 'text-[#6d38d6]']]
                : [['Total rows', preview.summary.total, 'text-foreground'], ['Valid', preview.summary.valid, 'text-emerald-700'], ['Invalid', preview.summary.invalid, 'text-rose-700'], ['Duplicates', preview.summary.duplicate, 'text-amber-700'], ['Can be imported', preview.summary.importable, 'text-[#3a35e0]'], ['Test data', preview.summary.test_data, 'text-[#6d38d6]']]
              ).map(([label, value, tone]) => (
                <div key={String(label)} className="rounded-2xl border border-[#e4dfef] bg-[#faf9fe] px-4 py-3">
                  <p className={`text-2xl font-bold ${tone}`}>{value}</p>
                  <p className="text-xs text-muted-foreground">{label}</p>
                </div>
              ))}
            </div>

            {done ? (
              <div className="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                <CheckCircle2 className="mt-0.5 size-5 shrink-0" />
                <div><p className="font-semibold">Import complete for {preview.file_name}</p><p>{preview.summary.imported} candidates were added to {businessName} with source "Excel Import". No employees or job applications were created.</p></div>
              </div>
            ) : (
              <details className="rounded-2xl border border-[#e4dfef] open:bg-[#faf9fe]" open={Object.keys(preview.mapping).length < 2 || preview.summary.invalid === preview.summary.total}>
                <summary className="cursor-pointer px-4 py-3 text-sm font-semibold">Column mapping <span className="font-normal text-muted-foreground">· {Object.keys(preview.mapping).length} of {preview.headers.length} columns matched</span></summary>
                <div className="grid gap-3 px-4 pb-4 sm:grid-cols-2 lg:grid-cols-3">
                  {preview.headers.map((header, column) => (
                    <label key={`${header}-${column}`} className="grid gap-1 text-xs">
                      <span className="truncate font-medium text-foreground" title={header}>{header || `Column ${column + 1}`}</span>
                      <select value={preview.mapping[String(column)] ?? ''} disabled={busy !== null} onChange={(event) => void remap(column, event.target.value)} className="h-9 rounded-lg border border-[#d9d3ea] bg-white px-2 text-sm focus:border-[#6d38d6] focus:outline-none">
                        <option value="">Ignore this column</option>
                        {preview.fields.map((field) => <option key={field.key} value={field.key}>{field.label}</option>)}
                      </select>
                    </label>
                  ))}
                </div>
              </details>
            )}

            <div className="rounded-2xl border border-[#e4dfef]">
              <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[#e4dfef] px-4 py-3">
                <div className="flex flex-wrap gap-1.5" role="tablist" aria-label="Filter rows">
                  {filters.map((item) => (
                    <button key={item.key} type="button" role="tab" aria-selected={filter === item.key} onClick={() => setFilter(item.key)}
                      className={`rounded-full px-3 py-1 text-xs font-semibold ${filter === item.key ? 'bg-[#3a35e0] text-white' : 'bg-[#f3f1fa] text-foreground hover:bg-[#e8e4f7]'}`}>
                      {item.label}{item.key === 'problems' ? ` (${problems})` : ''}
                    </button>
                  ))}
                </div>
                {problems > 0 ? (
                  <Button size="sm" variant="outline" disabled={busy !== null} onClick={() => void run('report', () => download(`/api/v1/recruitment/candidate-imports/${preview.id}/errors`, `candidate-import-${preview.id}-errors.csv`))}>
                    {busy === 'report' ? <Loader2 className="size-4 animate-spin" /> : <Download className="size-4" />} Error report
                  </Button>
                ) : null}
              </div>
              <div className="max-h-[22rem] overflow-auto">
                {visible.length === 0 ? (
                  <p className="px-4 py-10 text-center text-sm text-muted-foreground">{filter === 'problems' ? 'No problems found. Every row is ready.' : 'No rows to show.'}</p>
                ) : (
                  <table className="w-full text-left text-sm">
                    <thead className="sticky top-0 bg-white text-xs uppercase tracking-wide text-muted-foreground"><tr><th className="px-4 py-2">Excel row</th><th className="px-4 py-2">Candidate</th><th className="px-4 py-2">Contact</th><th className="px-4 py-2">Status</th><th className="px-4 py-2">Details</th></tr></thead>
                    <tbody>
                      {visible.slice(0, 500).map((row) => (
                        <tr key={row.row} className="border-t border-[#f0edf7] align-top">
                          <td className="px-4 py-2 font-mono text-xs">{row.row}</td>
                          <td className="px-4 py-2"><span className="font-medium">{row.name || '—'}</span>{row.is_test_data ? <span className="ml-2 inline-flex items-center gap-1 rounded-full bg-[#f3efff] px-2 py-0.5 text-[10px] font-semibold text-[#6d38d6]"><FlaskConical className="size-3" />Test data</span> : null}</td>
                          <td className="px-4 py-2 text-xs text-muted-foreground">{row.email ?? '—'}<br />{row.phone ?? ''}</td>
                          <td className="px-4 py-2"><span className={`rounded-full px-2 py-0.5 text-xs font-semibold capitalize ring-1 ${statusStyles[row.status]}`}>{row.status}</span></td>
                          <td className="px-4 py-2 text-xs text-rose-700">{row.errors.join(' ')}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </div>

            {confirming && !done ? (
              <div className="flex flex-col gap-3 rounded-2xl border border-[#c9bdf5] bg-[#f6f3ff] p-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="flex items-start gap-2 text-sm"><AlertTriangle className="mt-0.5 size-4 shrink-0 text-[#6d38d6]" />Import <strong>{preview.summary.importable}</strong> candidates into <strong>{businessName}</strong>? {problems > 0 ? `${problems} rows with problems will be skipped.` : ''}</p>
                <div className="flex gap-2">
                  <Button variant="outline" disabled={busy !== null} onClick={() => setConfirming(false)}>Back</Button>
                  <Button disabled={busy !== null} onClick={() => void confirm()}>{busy === 'confirm' ? <><Loader2 className="size-4 animate-spin" /> Importing…</> : 'Yes, import now'}</Button>
                </div>
              </div>
            ) : null}

            <div className="flex flex-wrap items-center justify-between gap-3">
              <p className="text-xs text-muted-foreground"><Copy className="mr-1 inline size-3" />{preview.file_name}{done ? '' : ' · Fix rows in Excel and upload again at any time.'}</p>
              <div className="flex flex-wrap gap-2">
                <Button variant="outline" disabled={busy !== null} onClick={reset}><RotateCcw className="size-4" />{done ? 'Import another file' : 'Upload a different file'}</Button>
                {done ? <Button onClick={() => { reset(); onClose() }}>Done</Button> : !confirming ? (
                  <Button disabled={busy !== null || preview.summary.importable === 0} onClick={() => setConfirming(true)}>{busy === 'mapping' ? <Loader2 className="size-4 animate-spin" /> : null}Continue to confirm ({preview.summary.importable})</Button>
                ) : null}
              </div>
            </div>
          </>
        )}
      </div>
    </section>
  )
}
