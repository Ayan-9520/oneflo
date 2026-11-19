import type { SelectHTMLAttributes } from 'react'

import { cn } from '@/lib/utils'

export type SelectOption = {
  value: string
  label: string
  disabled?: boolean
}

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
  options: readonly SelectOption[]
  placeholder?: string
}

export function Select({ className, options, placeholder, ...props }: SelectProps) {
  return (
    <select
      className={cn(
        'h-10 w-full rounded-xl border border-border bg-card px-3.5 text-sm text-foreground shadow-sm disabled:cursor-not-allowed disabled:bg-muted',
        className,
      )}
      {...props}
    >
      {placeholder ? (
        <option value="" disabled>
          {placeholder}
        </option>
      ) : null}
      {options.map((option) => (
        <option key={option.value} value={option.value} disabled={option.disabled}>
          {option.label}
        </option>
      ))}
    </select>
  )
}
