import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react'

import { cn } from '@/lib/utils'

type ToastTone = 'info' | 'success' | 'warning' | 'danger'

type ToastItem = {
  id: number
  message: string
  tone: ToastTone
}

type ToastContextValue = {
  notify: (message: string, tone?: ToastTone) => void
}

const ToastContext = createContext<ToastContextValue | null>(null)

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<ToastItem[]>([])

  const notify = useCallback((message: string, tone: ToastTone = 'info') => {
    const id = Date.now()
    setToasts((current) => [...current, { id, message, tone }])
    window.setTimeout(() => {
      setToasts((current) => current.filter((toast) => toast.id !== id))
    }, 3200)
  }, [])

  const value = useMemo(() => ({ notify }), [notify])

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div aria-live="polite" className="pointer-events-none fixed right-4 bottom-4 z-50 flex w-[min(22rem,calc(100%-2rem))] flex-col gap-2">
        {toasts.map((toast) => (
          <p
            key={toast.id}
            className={cn(
              'rounded-lg border bg-card px-4 py-3 text-sm shadow-[var(--shadow-card)]',
              toast.tone === 'success' && 'border-success/30',
              toast.tone === 'warning' && 'border-warning/40',
              toast.tone === 'danger' && 'border-destructive/30',
              toast.tone === 'info' && 'border-border',
            )}
          >
            {toast.message}
          </p>
        ))}
      </div>
    </ToastContext.Provider>
  )
}

export function useToast() {
  const context = useContext(ToastContext)
  if (!context) {
    throw new Error('useToast must be used within ToastProvider')
  }
  return context
}
