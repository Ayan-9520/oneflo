import type { ReactNode } from 'react'

export function Tooltip({ label, children }: { label: string; children: ReactNode }) {
  return (
    <span className="group relative inline-flex">
      {children}
      <span
        role="tooltip"
        className="pointer-events-none absolute bottom-[calc(100%+0.4rem)] left-1/2 z-30 hidden -translate-x-1/2 rounded-md bg-secondary px-2 py-1 text-xs whitespace-nowrap text-secondary-foreground group-hover:block group-focus-within:block"
      >
        {label}
      </span>
    </span>
  )
}
