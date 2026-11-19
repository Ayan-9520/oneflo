import { cva, type VariantProps } from 'class-variance-authority'
import type { HTMLAttributes } from 'react'

import { cn } from '@/lib/utils'

const badgeVariants = cva('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', {
  variants: {
    tone: {
      neutral: 'bg-muted text-muted-foreground',
      info: 'bg-primary/10 text-primary-dark',
      success: 'bg-success/10 text-success',
      warning: 'bg-warning/15 text-warning-foreground',
      danger: 'bg-destructive/10 text-destructive',
    },
  },
  defaultVariants: {
    tone: 'neutral',
  },
})

export function Badge({
  className,
  tone,
  ...props
}: HTMLAttributes<HTMLSpanElement> & VariantProps<typeof badgeVariants>) {
  return <span className={cn(badgeVariants({ tone }), className)} {...props} />
}
