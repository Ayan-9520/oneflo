import { X } from 'lucide-react'
import { useEffect, useId, useRef, type ReactNode } from 'react'

import { Button } from '@/components/ui/button'

export function Dialog({
  open,
  title,
  description,
  onClose,
  children,
}: {
  open: boolean
  title: string
  description?: string
  onClose: () => void
  children: ReactNode
}) {
  const ref = useRef<HTMLDialogElement>(null)
  const titleId = useId()

  useEffect(() => {
    const dialog = ref.current
    if (!dialog) {
      return
    }
    if (open && !dialog.open) {
      dialog.showModal()
    }
    if (!open && dialog.open) {
      dialog.close()
    }
  }, [open])

  return (
    <dialog
      ref={ref}
      aria-labelledby={titleId}
      className="w-[min(32rem,calc(100%-2rem))] rounded-xl border border-border bg-card p-0 text-foreground shadow-[var(--shadow-card)] backdrop:bg-[#1c1433]/40"
      onClose={onClose}
      onClick={(event) => {
        if (event.target === ref.current) {
          onClose()
        }
      }}
    >
      <div className="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
        <div>
          <h2 id={titleId} className="text-base font-semibold">
            {title}
          </h2>
          {description ? <p className="mt-1 text-sm text-muted-foreground">{description}</p> : null}
        </div>
        <Button size="icon" variant="ghost" aria-label="Close dialog" onClick={onClose}>
          <X className="size-4" />
        </Button>
      </div>
      <div className="px-5 py-4">{children}</div>
    </dialog>
  )
}
