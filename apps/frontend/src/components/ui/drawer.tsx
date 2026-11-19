import { X } from 'lucide-react'
import { useEffect, useId, useRef, type ReactNode } from 'react'

import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

export function Drawer({
  open,
  title,
  onClose,
  children,
  side = 'right',
}: {
  open: boolean
  title: string
  onClose: () => void
  children: ReactNode
  side?: 'left' | 'right'
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
      className={cn(
        'h-dvh max-h-none w-[min(22rem,100%)] border-0 bg-card p-0 text-foreground shadow-[var(--shadow-card)] backdrop:bg-secondary/50',
        side === 'right' ? 'mr-0 ml-auto' : 'mr-auto ml-0',
      )}
      onClose={onClose}
    >
      <div className="flex h-full flex-col">
        <div className="flex items-center justify-between border-b border-border px-4 py-3">
          <h2 id={titleId} className="text-base font-semibold">
            {title}
          </h2>
          <Button size="icon" variant="ghost" aria-label="Close panel" onClick={onClose}>
            <X className="size-4" />
          </Button>
        </div>
        <div className="flex-1 overflow-y-auto px-4 py-4">{children}</div>
      </div>
    </dialog>
  )
}
