import { AlertTriangle } from 'lucide-react'
import type { ReactNode } from 'react'

export function ErrorState({
  title,
  description,
  action,
}: {
  title: string
  description?: string
  action?: ReactNode
}) {
  return (
    <div role="alert" className="rounded-xl border border-destructive/30 bg-card px-6 py-8 shadow-[var(--shadow-card)]">
      <div className="flex items-start gap-3">
        <AlertTriangle className="mt-0.5 size-5 shrink-0 text-destructive" aria-hidden="true" />
        <div>
          <h2 className="text-base font-semibold text-destructive">{title}</h2>
          {description ? <p className="mt-1 text-sm text-muted-foreground">{description}</p> : null}
          {action ? <div className="mt-4">{action}</div> : null}
        </div>
      </div>
    </div>
  )
}
