import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '../lib/cn';

type Variant = 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'violet';
type Size = 'sm' | 'md';

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: Variant;
  size?: Size;
  icon?: ReactNode;
  dot?: boolean;
  children: ReactNode;
}

const variantStyles: Record<Variant, string> = {
  neutral: 'bg-surface2 text-text-muted border-border',
  success: 'bg-green-500/10 text-green-400 border-green-500/20',
  warning: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
  danger:  'bg-danger/10 text-danger border-danger/20',
  info:    'bg-blue-500/10 text-blue-400 border-blue-500/20',
  violet:  'bg-violet/10 text-violet-light border-violet/20',
};

const dotStyles: Record<Variant, string> = {
  neutral: 'bg-text-muted',
  success: 'bg-green-400',
  warning: 'bg-amber-400',
  danger:  'bg-danger',
  info:    'bg-blue-400',
  violet:  'bg-violet-light',
};

const sizeStyles: Record<Size, string> = {
  sm: 'text-[11px] px-2 py-0.5 gap-1',
  md: 'text-xs px-2.5 py-1 gap-1.5',
};

/**
 * Badge — compact status indicator.
 * - 6 variants (neutral, success, warning, danger, info, violet)
 * - Optional dot or icon prefix
 */
export function Badge({
  variant = 'neutral',
  size = 'md',
  icon,
  dot = false,
  className,
  children,
  ...rest
}: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center font-medium rounded-full border',
        variantStyles[variant],
        sizeStyles[size],
        className,
      )}
      {...rest}
    >
      {dot && (
        <span
          aria-hidden="true"
          className={cn('inline-block h-1.5 w-1.5 rounded-full', dotStyles[variant])}
        />
      )}
      {icon && <span aria-hidden="true" className="shrink-0">{icon}</span>}
      {children}
    </span>
  );
}
