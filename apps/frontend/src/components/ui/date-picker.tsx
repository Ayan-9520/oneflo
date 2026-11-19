import { ChevronLeft, ChevronRight } from 'lucide-react'
import { useMemo, useState } from 'react'

import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

const weekdays = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']

function parseIso(value: string) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)
  if (!match) {
    return null
  }
  return { year: Number(match[1]), month: Number(match[2]) - 1, day: Number(match[3]) }
}

function toIso(year: number, month: number, day: number) {
  return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

export function DatePicker({
  id,
  value,
  onChange,
  disabled,
}: {
  id: string
  value: string
  onChange: (value: string) => void
  disabled?: boolean
}) {
  const selected = parseIso(value)
  const [open, setOpen] = useState(false)
  const [cursor, setCursor] = useState(() => {
    const today = new Date()
    return {
      year: selected?.year ?? today.getFullYear(),
      month: selected?.month ?? today.getMonth(),
    }
  })

  const cells = useMemo(() => {
    const first = new Date(cursor.year, cursor.month, 1)
    const offset = (first.getDay() + 6) % 7
    const count = new Date(cursor.year, cursor.month + 1, 0).getDate()
    return Array.from({ length: offset + count }, (_, index) => (index < offset ? null : index - offset + 1))
  }, [cursor.month, cursor.year])

  const label = new Intl.DateTimeFormat('en', { month: 'long', year: 'numeric' }).format(
    new Date(cursor.year, cursor.month, 1),
  )

  return (
    <div className="relative">
      <Button
        id={id}
        variant="outline"
        className="w-full justify-start font-normal"
        disabled={disabled}
        aria-expanded={open}
        aria-haspopup="dialog"
        onClick={() => setOpen((current) => !current)}
      >
        {value || 'Choose a date'}
      </Button>
      {open ? (
        <div className="absolute z-30 mt-2 w-72 rounded-lg border border-border bg-card p-3 shadow-[var(--shadow-card)]">
          <div className="mb-3 flex items-center justify-between">
            <Button
              size="icon"
              variant="ghost"
              aria-label="Previous month"
              onClick={() =>
                setCursor((current) =>
                  current.month === 0
                    ? { year: current.year - 1, month: 11 }
                    : { year: current.year, month: current.month - 1 },
                )
              }
            >
              <ChevronLeft className="size-4" />
            </Button>
            <p className="text-sm font-medium">{label}</p>
            <Button
              size="icon"
              variant="ghost"
              aria-label="Next month"
              onClick={() =>
                setCursor((current) =>
                  current.month === 11
                    ? { year: current.year + 1, month: 0 }
                    : { year: current.year, month: current.month + 1 },
                )
              }
            >
              <ChevronRight className="size-4" />
            </Button>
          </div>
          <div className="grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
            {weekdays.map((day) => (
              <span key={day} className="py-1">
                {day}
              </span>
            ))}
            {cells.map((day, index) =>
              day === null ? (
                <span key={`empty-${index}`} />
              ) : (
                <button
                  key={day}
                  type="button"
                  className={cn(
                    'h-8 rounded-md text-sm text-foreground hover:bg-muted',
                    value === toIso(cursor.year, cursor.month, day) && 'bg-primary text-primary-foreground hover:bg-primary',
                  )}
                  onClick={() => {
                    onChange(toIso(cursor.year, cursor.month, day))
                    setOpen(false)
                  }}
                >
                  {day}
                </button>
              ),
            )}
          </div>
        </div>
      ) : null}
    </div>
  )
}
