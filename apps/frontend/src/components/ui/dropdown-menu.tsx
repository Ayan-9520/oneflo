import { useEffect, useId, useRef, useState, type ReactNode } from 'react'

import { cn } from '@/lib/utils'

export type MenuItem = {
  id: string
  label: string
  onSelect: () => void
  disabled?: boolean
}

export function DropdownMenu({
  label,
  trigger,
  items,
  align = 'right',
}: {
  label: string
  trigger: ReactNode
  items: readonly MenuItem[]
  align?: 'left' | 'right'
}) {
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  const menuId = useId()

  useEffect(() => {
    if (!open) {
      return
    }
    const onPointerDown = (event: MouseEvent) => {
      if (!ref.current?.contains(event.target as Node)) {
        setOpen(false)
      }
    }
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.removeEventListener('mousedown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open])

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        className="inline-flex items-center"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={menuId}
        aria-label={label}
        onClick={() => setOpen((current) => !current)}
      >
        {trigger}
      </button>
      {open ? (
        <div
          id={menuId}
          role="menu"
          className={cn(
            'absolute z-40 mt-2 min-w-56 rounded-lg border border-border bg-card p-1 shadow-[var(--shadow-card)]',
            align === 'right' ? 'right-0' : 'left-0',
          )}
        >
          {items.length === 0 ? <p className="px-3 py-2 text-sm text-muted-foreground">Nothing to show.</p> : null}
          {items.map((item) => (
            <button
              key={item.id}
              type="button"
              role="menuitem"
              disabled={item.disabled}
              className="flex w-full rounded-md px-3 py-2 text-left text-sm hover:bg-muted disabled:opacity-50"
              onClick={() => {
                item.onSelect()
                setOpen(false)
              }}
            >
              {item.label}
            </button>
          ))}
        </div>
      ) : null}
    </div>
  )
}
