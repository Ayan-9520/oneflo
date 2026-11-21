import { useEffect, useId, useRef, useState, type ReactNode } from 'react'

import { cn } from '@/lib/utils'

export type MenuItem = {
  id: string
  label: string
  hint?: string
  tone?: 'default' | 'danger'
  onSelect: () => void
  disabled?: boolean
}

export function DropdownMenu({
  label,
  trigger,
  items,
  align = 'right',
  header,
}: {
  label: string
  trigger: ReactNode
  items: readonly MenuItem[]
  align?: 'left' | 'right'
  header?: ReactNode
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
            'absolute z-40 mt-2 min-w-60 overflow-hidden rounded-2xl border border-border bg-card p-1.5 shadow-[0_18px_50px_rgb(28_20_51_/_0.14)]',
            align === 'right' ? 'right-0' : 'left-0',
          )}
        >
          {header}
          {items.length === 0 ? <p className="px-3 py-2 text-sm text-muted-foreground">Nothing to show.</p> : null}
          {items.map((item) => (
            <button
              key={item.id}
              type="button"
              role="menuitem"
              disabled={item.disabled}
              className={cn(
                'flex w-full flex-col rounded-xl px-3 py-2 text-left disabled:opacity-50',
                item.tone === 'danger' ? 'mt-1 border-t border-border text-rose-600 hover:bg-rose-50' : 'hover:bg-muted',
              )}
              onClick={() => {
                item.onSelect()
                setOpen(false)
              }}
            >
              <span className="text-sm font-semibold">{item.label}</span>
              {item.hint ? <span className={cn('text-xs', item.tone === 'danger' ? 'text-rose-500' : 'text-muted-foreground')}>{item.hint}</span> : null}
            </button>
          ))}
        </div>
      ) : null}
    </div>
  )
}
