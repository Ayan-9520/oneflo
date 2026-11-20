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
      className="hidden max-h-[min(44rem,calc(100%-2rem))] w-[min(40rem,calc(100%-2rem))] open:flex open:flex-col rounded-3xl border border-[#e4dfef] bg-white p-0 text-foreground shadow-[0_24px_80px_rgb(28_20_51/0.22)] backdrop:bg-[#1c1433]/45"
      style={{ position: 'fixed', inset: 'auto', top: '50%', left: '50%', transform: 'translate(-50%, -50%)', margin: 0 }}
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
      <div className="overflow-y-auto px-5 py-4">{children}</div>
    </dialog>
  )
}
