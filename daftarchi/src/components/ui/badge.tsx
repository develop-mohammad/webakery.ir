import * as React from 'react'
import { cn } from '@/lib/utils'

const Badge = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement> & { variant?: 'default' | 'muted' | 'warning' | 'soon' }
>(({ className, variant = 'default', ...props }, ref) => (
  <div
    ref={ref}
    className={cn(
      'inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-medium leading-none',
      variant === 'default' && 'bg-primary/10 text-primary',
      variant === 'muted' && 'bg-muted text-muted-foreground',
      variant === 'warning' && 'bg-warning/15 text-warning',
      variant === 'soon' && 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
      className,
    )}
    {...props}
  />
))
Badge.displayName = 'Badge'

export { Badge }
