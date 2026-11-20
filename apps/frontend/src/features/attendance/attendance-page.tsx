import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router'

import { useSession } from '@/app/session'
import { Button } from '@/components/ui/button'
import { DataTable } from '@/components/ui/data-table'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { Field, Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/page-header'
import { Select } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { useToast } from '@/components/ui/toast'
import { ApiError, apiData } from '@/lib/api'

type DayRow = {
  work_date: string
  status: string
  work_mode: string | null
  late_minutes: number
  early_minutes: number
  overtime_minutes: number
  break_minutes: number
  worked_minutes: number
  note?: string | null
}

type Tone = 'green' | 'orange' | 'red' | 'blue'

type Summary = {
  employees: number
  present: number
  half_day: number
  absent: number
  late: number
  missing_punch: number
  overtime_minutes: number
}

const modes = [
  { value: 'office', label: 'Office' },
  { value: 'remote', label: 'Remote' },
  { value: 'hybrid', label: 'Hybrid' },
  { value: 'field', label: 'Field' },
]

export function AttendancePage() {
  const { organizationId, permissions } = useSession()
  const canManage = permissions.has('attendance.approve') || permissions.has('organization.manage')
  const [search] = useSearchParams()
  const focus = search.get('view')
  const today = new Date().toISOString().slice(0, 10)
  const [month, setMonth] = useState(today.slice(0, 7))
  const [employeeId, setEmployeeId] = useState('')
  const [picked, setPicked] = useState(today)
  const employees = useQuery({
    queryKey: ['employees', organizationId, 'attendance'],
    enabled: organizationId !== null,
    queryFn: () => apiData<Array<{ id: number; full_name: string; employee_code: string }>>('/api/v1/employees'),
  })
  const summary = useQuery({
    queryKey: ['attendance-summary', organizationId, today],
    enabled: organizationId !== null,
    queryFn: () => apiData<Summary>(`/api/v1/attendance/summary?date=${today}`),
  })
  const selected = employeeId || (employees.data?.[0] ? String(employees.data[0].id) : '')
  const [year, monthNumber] = month.split('-')
  const days = useQuery({
    queryKey: ['attendance-month', organizationId, selected, month],
    enabled: organizationId !== null && selected !== '',
    queryFn: () => apiData<DayRow[]>(`/api/v1/attendance/month?employee_id=${selected}&year=${year}&month=${Number(monthNumber)}`),
  })

  useEffect(() => {
    const target = focus === 'punch' ? 'attendance-punch' : focus === 'month' ? 'attendance-month' : focus === 'shifts' ? 'attendance-shifts' : focus === 'holidays' ? 'attendance-holidays' : null
    if (target) document.getElementById(target)?.scrollIntoView({ block: 'start' })
  }, [focus, canManage, days.data])

  if (organizationId === null) {
    return <EmptyState title="Select a business" description="Attendance stays inside the active business." />
  }

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-8">
      <PageHeader
        title="Attendance"
        description="Today’s count, a punch for a chosen date, and the month marked in colour. A note can be saved on any day. Location is not collected."
        crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Attendance' }]}
      />
      <section className="flex flex-col gap-3 border-t border-border pt-6">
        <h2 className="text-lg font-semibold">Today</h2>
        <p className="mt-1 text-sm text-muted-foreground">Counts for {dayLabel(today)} across this business.</p>
        {summary.isPending ? (
          <Skeleton className="mt-4 h-20 w-full" />
        ) : summary.isError ? (
          <ErrorState title="Attendance could not be loaded" description={summary.error instanceof ApiError ? summary.error.message : 'Try again.'} />
        ) : (
          <dl className="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Metric tone="green" label="Present" value={summary.data.present} />
            <Metric tone="orange" label="Half day" value={summary.data.half_day} />
            <Metric tone="orange" label="Late" value={summary.data.late} />
            <Metric tone="red" label="Missing punch" value={summary.data.missing_punch} />
          </dl>
        )}
      </section>
      <section id="attendance-punch" className="flex flex-col gap-3 border-t border-border pt-6">
        <h2 className="text-lg font-semibold">Record a punch</h2>
        <p className="mt-1 text-sm text-muted-foreground">This punch is saved on the employee linked to your login. Choose the date, time, punch type, and work mode.</p>
        <div className="mt-4">
          <PunchForm date={picked} onDate={setPicked} />
        </div>
      </section>
      <section id="attendance-month" className="flex flex-col gap-3 border-t border-border pt-6">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h2 className="text-lg font-semibold">Month</h2>
            <p className="mt-1 max-w-xl text-sm text-muted-foreground">These boxes are for the employee selected here. Click a day to put that date in the punch and to write a note.</p>
          </div>
          <div className="grid w-full gap-3 sm:w-auto sm:grid-cols-2">
            <Field label="Employee" htmlFor="attendance-employee">
              <Select
                id="attendance-employee"
                value={selected}
                placeholder="No employee yet"
                options={(employees.data ?? []).map((employee) => ({ value: String(employee.id), label: `${employee.employee_code} ${employee.full_name}` }))}
                onChange={(event) => setEmployeeId(event.target.value)}
              />
            </Field>
            <Field label="Month" htmlFor="attendance-month">
              <Input id="attendance-month" type="month" value={month} onChange={(event) => setMonth(event.target.value)} />
            </Field>
          </div>
        </div>
        <Legend />
        {selected === '' ? (
          <EmptyState title="No employee in this business" description="The month boxes appear after a person is on the payroll record." />
        ) : days.isPending ? (
          <Skeleton className="mt-4 h-40 w-full" />
        ) : days.isError ? (
          <ErrorState title="The month could not be loaded" description={days.error instanceof ApiError ? days.error.message : 'Try again.'} />
        ) : (
          <MonthCalendar rows={days.data} employeeId={Number(selected)} picked={picked} onPick={setPicked} />
        )}
      </section>
      {canManage ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <section id="attendance-shifts" className="border-t border-border pt-6"><ShiftForm /></section>
          <section id="attendance-holidays" className="border-t border-border pt-6"><HolidayForm /></section>
        </div>
      ) : null}
    </div>
  )
}

export function AttendanceHistory({ employeeId }: { employeeId: number }) {
  const { organizationId } = useSession()
  const today = new Date()
  const days = useQuery({
    queryKey: ['attendance-month', organizationId, employeeId, today.getFullYear(), today.getMonth() + 1],
    enabled: organizationId !== null,
    queryFn: () => apiData<DayRow[]>(`/api/v1/attendance/month?employee_id=${employeeId}&year=${today.getFullYear()}&month=${today.getMonth() + 1}`),
  })
  if (days.isPending) return <Skeleton className="h-24 w-full" />
  if (days.isError) return <ErrorState title="Attendance could not be loaded" description={days.error instanceof ApiError ? days.error.message : 'Try again.'} />
  const recorded = days.data.filter((day) => day.status !== 'absent' && day.status !== 'weekly_off')
  if (recorded.length === 0) return <EmptyState title="No attendance this month" description="Punches and approved leave appear in this history." />
  return <DayTable rows={recorded} />
}

function PunchForm({ date, onDate }: { date: string; onDate: (value: string) => void }) {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setPending(true)
    setError(null)
    try {
      await apiData('/api/v1/attendance/punches', {
        method: 'POST',
        body: JSON.stringify({
          kind: String(form.get('kind')),
          work_mode: String(form.get('work_mode')),
          work_date: String(form.get('work_date')),
          punched_at: `${form.get('work_date')} ${form.get('time')}:00`,
        }),
      })
      notify('Attendance recorded.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['attendance-month'] })
      await queryClient.invalidateQueries({ queryKey: ['attendance-summary'] })
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The punch could not be saved.')
    } finally {
      setPending(false)
    }
  }

  return (
    <form className="grid gap-3 sm:grid-cols-4" onSubmit={(event) => void submit(event)}>
      <Field label="Date" htmlFor="punch-date">
        <Input id="punch-date" name="work_date" type="date" required value={date} onChange={(event) => onDate(event.target.value)} />
      </Field>
      <Field label="Time" htmlFor="punch-time">
        <Input id="punch-time" name="time" type="time" required />
      </Field>
      <Field label="Punch" htmlFor="punch-kind">
        <Select id="punch-kind" name="kind" options={[{ value: 'in', label: 'Check in' }, { value: 'out', label: 'Check out' }, { value: 'break_start', label: 'Break start' }, { value: 'break_end', label: 'Break end' }]} />
      </Field>
      <Field label="Work mode" htmlFor="punch-mode">
        <Select id="punch-mode" name="work_mode" options={modes} />
      </Field>
      {error ? <p className="text-sm text-destructive sm:col-span-4">{error}</p> : null}
      <div className="w-fit sm:col-span-4">
        <Button type="submit" disabled={pending}>{pending ? 'Saving…' : 'Record punch'}</Button>
      </div>
    </form>
  )
}

function ShiftForm() {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)
  const shifts = useQuery({ queryKey: ['shifts'], queryFn: () => apiData<Array<{ id: number; name: string }>>('/api/v1/shifts') })
  const employees = useQuery({ queryKey: ['employees', 'shift-assign'], queryFn: () => apiData<Array<{ id: number; full_name: string }>>('/api/v1/employees') })

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    const body = new FormData(form)
    const offs = body.getAll('weekly_offs').map((value) => Number(value))
    setPending(true)
    try {
      await apiData('/api/v1/shifts', {
        method: 'POST',
        body: JSON.stringify({
          name: String(body.get('name')),
          starts_at: String(body.get('starts_at')),
          ends_at: String(body.get('ends_at')),
          grace_minutes: Number(body.get('grace_minutes') || 0),
          early_grace_minutes: Number(body.get('early_grace_minutes') || 0),
          overtime_threshold_minutes: Number(body.get('overtime_threshold_minutes') || 0),
          weekly_offs: offs,
        }),
      })
      form.reset()
      notify('Shift saved.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['shifts'] })
    } catch (error) {
      notify(error instanceof ApiError ? error.message : 'The shift could not be saved.', 'danger')
    } finally {
      setPending(false)
    }
  }

  const assign = useMutation({
    mutationFn: (input: { employee_id: number; shift_id: number; effective_on: string }) =>
      apiData('/api/v1/shift-assignments', { method: 'POST', body: JSON.stringify(input) }),
    onSuccess: async () => {
      notify('Shift assigned.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['attendance-month'] })
    },
    onError: (error) => notify(error instanceof ApiError ? error.message : 'The assignment failed.', 'danger'),
  })

  return (
    <section className="flex flex-col gap-3">
      <h2 className="text-base font-semibold">Shifts</h2>
      <p className="text-sm text-muted-foreground">{(shifts.data ?? []).map((shift) => shift.name).join(', ') || 'No shifts yet.'}</p>
      <form className="grid gap-3 sm:grid-cols-3" onSubmit={(event) => void create(event)}>
        <Field label="Shift name" htmlFor="shift-name"><Input id="shift-name" name="name" required /></Field>
        <Field label="Starts" htmlFor="shift-start"><Input id="shift-start" name="starts_at" type="time" required /></Field>
        <Field label="Ends" htmlFor="shift-end"><Input id="shift-end" name="ends_at" type="time" required /></Field>
        <Field label="Grace minutes" htmlFor="shift-grace"><Input id="shift-grace" name="grace_minutes" type="number" min={0} defaultValue={10} /></Field>
        <Field label="Early grace" htmlFor="shift-early"><Input id="shift-early" name="early_grace_minutes" type="number" min={0} defaultValue={10} /></Field>
        <Field label="Overtime after minutes" htmlFor="shift-ot"><Input id="shift-ot" name="overtime_threshold_minutes" type="number" min={0} defaultValue={0} /></Field>
        <fieldset className="flex flex-wrap gap-3 text-sm sm:col-span-3">
          <legend className="mb-1 font-medium">Weekly offs</legend>
          {[['1', 'Mon'], ['2', 'Tue'], ['3', 'Wed'], ['4', 'Thu'], ['5', 'Fri'], ['6', 'Sat'], ['7', 'Sun']].map(([value, label]) => (
            <label key={value} className="flex items-center gap-1"><input type="checkbox" name="weekly_offs" value={value} defaultChecked={value === '7'} /> {label}</label>
          ))}
        </fieldset>
        <div className="w-fit sm:col-span-3"><Button type="submit" disabled={pending}>{pending ? 'Saving…' : 'Create shift'}</Button></div>
      </form>
      <form
        className="grid gap-3 sm:grid-cols-3"
        onSubmit={(event) => {
          event.preventDefault()
          const body = new FormData(event.currentTarget)
          assign.mutate({ employee_id: Number(body.get('employee_id')), shift_id: Number(body.get('shift_id')), effective_on: String(body.get('effective_on')) })
        }}
      >
        <Field label="Assign to" htmlFor="assign-employee">
          <Select id="assign-employee" name="employee_id" placeholder="Employee" options={(employees.data ?? []).map((employee) => ({ value: String(employee.id), label: employee.full_name }))} />
        </Field>
        <Field label="Shift" htmlFor="assign-shift">
          <Select id="assign-shift" name="shift_id" placeholder="Shift" options={(shifts.data ?? []).map((shift) => ({ value: String(shift.id), label: shift.name }))} />
        </Field>
        <Field label="Effective" htmlFor="assign-date"><Input id="assign-date" name="effective_on" type="date" required /></Field>
        <div className="w-fit sm:col-span-3"><Button type="submit" variant="outline" disabled={assign.isPending}>Assign shift</Button></div>
      </form>
    </section>
  )
}

function HolidayForm() {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const holidays = useQuery({ queryKey: ['holidays'], queryFn: () => apiData<Array<{ id: number; name: string; observed_on: string }>>('/api/v1/holidays') })
  const [pending, setPending] = useState(false)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    const body = new FormData(form)
    setPending(true)
    try {
      await apiData('/api/v1/holidays', { method: 'POST', body: JSON.stringify({ name: String(body.get('name')), observed_on: String(body.get('observed_on')) }) })
      form.reset()
      notify('Holiday saved.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['holidays'] })
    } catch (error) {
      notify(error instanceof ApiError ? error.message : 'The holiday could not be saved.', 'danger')
    } finally {
      setPending(false)
    }
  }

  return (
    <section>
      <h2 className="mb-2 text-base font-semibold">Holiday calendar</h2>
      <ul className="mb-3 text-sm">{(holidays.data ?? []).map((holiday) => <li key={holiday.id}>{holiday.observed_on} · {holiday.name}</li>)}</ul>
      <form className="flex flex-wrap items-end gap-2" onSubmit={(event) => void submit(event)}>
        <Field label="Holiday" htmlFor="holiday-name"><Input id="holiday-name" name="name" required /></Field>
        <Field label="Date" htmlFor="holiday-date"><Input id="holiday-date" name="observed_on" type="date" required /></Field>
        <Button type="submit" disabled={pending}>{pending ? 'Saving…' : 'Add holiday'}</Button>
      </form>
    </section>
  )
}

function MonthCalendar({ rows, employeeId, picked, onPick }: { rows: DayRow[]; employeeId: number; picked: string; onPick: (date: string) => void }) {
  const first = rows[0] ? new Date(`${rows[0].work_date}T00:00:00`).getDay() : 0
  const chosen = rows.find((row) => row.work_date === picked) ?? null
  return (
    <div className="mt-4">
      <div className="grid grid-cols-7 gap-2">
        {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((label) => (
          <p key={label} className="px-1 text-center text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">{label}</p>
        ))}
        {Array.from({ length: first }, (_, index) => <span key={`pad-${index}`} />)}
        {rows.map((row) => {
          const color = toneOf(row)
          return (
            <button
              key={row.work_date}
              type="button"
              onClick={() => onPick(row.work_date)}
              className={`min-h-16 rounded-xl border px-2 py-2 text-left text-xs ${tones[color]} ${picked === row.work_date ? 'ring-2 ring-[#1c1433]' : ''}`}
            >
              <p className="font-semibold">{Number(row.work_date.slice(8))}</p>
              <p className="mt-1 capitalize">{statusLabel(row.status)}</p>
              {row.note ? <p className="mt-1 truncate">Note</p> : null}
            </button>
          )
        })}
      </div>
      <DayNote key={chosen?.work_date ?? 'none'} employeeId={employeeId} day={chosen} />
      <div className="mt-5">
        <h3 className="mb-2 text-sm font-semibold">Days with a punch, a missing punch, or leave</h3>
        <DayTable rows={rows.filter((row) => row.worked_minutes > 0 || row.status === 'missing_punch' || row.status === 'leave')} />
      </div>
    </div>
  )
}

function DayNote({ employeeId, day }: { employeeId: number; day: DayRow | null }) {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [text, setText] = useState(day?.note ?? '')
  const [pending, setPending] = useState(false)

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!day) return
    setPending(true)
    try {
      await apiData('/api/v1/attendance/note', {
        method: 'PUT',
        body: JSON.stringify({ employee_id: employeeId, work_date: day.work_date, note: text }),
      })
      notify('Day note saved.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['attendance-month'] })
    } catch (error) {
      notify(error instanceof ApiError ? error.message : 'The note could not be saved.', 'danger')
    } finally {
      setPending(false)
    }
  }

  return (
    <form className="mt-4 grid gap-3 rounded-2xl bg-[#f7f6fb] p-4 sm:grid-cols-[minmax(0,1fr)_auto]" onSubmit={(event) => void save(event)}>
      <Field label={day ? `Note for ${dayLabel(day.work_date)}` : 'Note'} htmlFor="day-note">
        <Input id="day-note" value={day ? text : ''} disabled={!day} maxLength={500} placeholder={day ? 'Write a note for this date' : 'Select a day'} onChange={(event) => setText(event.target.value)} />
      </Field>
      <div className="sm:pt-6">
        <Button type="submit" variant="outline" disabled={!day || pending}>{pending ? 'Saving…' : 'Save note'}</Button>
      </div>
    </form>
  )
}

function DayTable({ rows }: { rows: DayRow[] }) {
  if (rows.length === 0) return null
  return (
    <DataTable
      caption="Attendance days"
      rows={rows}
      getRowId={(row) => row.work_date}
      columns={[
        { id: 'date', header: 'Date', cell: (row) => row.work_date },
        { id: 'status', header: 'Status', cell: (row) => row.status.replaceAll('_', ' ') },
        { id: 'mode', header: 'Mode', cell: (row) => row.work_mode ?? '' },
        { id: 'worked', header: 'Worked', cell: (row) => String(row.worked_minutes) },
        { id: 'break', header: 'Break', cell: (row) => String(row.break_minutes) },
        { id: 'late', header: 'Late', cell: (row) => String(row.late_minutes) },
        { id: 'early', header: 'Early', cell: (row) => String(row.early_minutes) },
        { id: 'ot', header: 'Overtime', cell: (row) => String(row.overtime_minutes) },
      ]}
    />
  )
}

const tones: Record<Tone, string> = {
  green: 'border-emerald-200 bg-emerald-50 text-emerald-950',
  orange: 'border-orange-200 bg-orange-50 text-orange-950',
  red: 'border-red-200 bg-red-50 text-red-950',
  blue: 'border-sky-200 bg-sky-50 text-sky-950',
}

function toneOf(row: DayRow): Tone {
  if (row.late_minutes > 0 || row.status === 'half_day' || row.status === 'missing_punch') return 'orange'
  if (row.status === 'present') return 'green'
  if (row.status === 'leave' || row.status === 'holiday' || row.status === 'weekly_off') return 'blue'
  return 'red'
}

function statusLabel(status: string): string {
  return status.replaceAll('_', ' ')
}

function dayLabel(value: string): string {
  const [year, month, day] = value.split('-')
  return year && month && day ? `${day}-${month}-${year}` : value
}

function Legend() {
  const items: Array<{ tone: Tone; label: string }> = [
    { tone: 'green', label: 'Present' },
    { tone: 'orange', label: 'Late or half day' },
    { tone: 'red', label: 'Absent' },
    { tone: 'blue', label: 'Leave, holiday, weekly off' },
  ]
  return (
    <ul className="mt-4 flex flex-wrap gap-2">
      {items.map((item) => (
        <li key={item.tone} className={`rounded-full border px-3 py-1 text-xs font-medium ${tones[item.tone]}`}>{item.label}</li>
      ))}
    </ul>
  )
}

function Metric({ label, value, tone }: { label: string; value: number; tone: Tone }) {
  return (
    <div className={`rounded-2xl border px-3 py-3 ${tones[tone]}`}>
      <dt className="text-xs">{label}</dt>
      <dd className="text-2xl font-semibold">{value}</dd>
    </div>
  )
}
