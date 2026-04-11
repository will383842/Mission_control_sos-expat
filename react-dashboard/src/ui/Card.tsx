import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '../lib/cn';

interface CardProps extends HTMLAttributes<HTMLDivElement> {
  padding?: 'none' | 'sm' | 'md' | 'lg';
  elevation?: 'none' | 'sm' | 'md' | 'lg';
  interactive?: boolean;
  children: ReactNode;
}

const paddingStyles = {
  none: '',
  sm: 'p-3',
  md: 'p-5',
  lg: 'p-7',
};

const elevationStyles = {
  none: '',
  sm: 'shadow-xs',
  md: 'shadow-sm',
  lg: 'shadow-md',
};

/**
 * Card — surface container with consistent padding, elevation, and border.
 * Use as the base for all content sections.
 */
export function Card({
  padding = 'md',
  elevation = 'none',
  interactive = false,
  className,
  children,
  ...rest
}: CardProps) {
  return (
    <div
      className={cn(
        'rounded-xl border border-border bg-surface',
        paddingStyles[padding],
        elevationStyles[elevation],
        interactive && 'transition-all hover:border-violet/50 hover:shadow-md cursor-pointer',
        className,
      )}
      {...rest}
    >
      {children}
    </div>
  );
}

interface CardHeaderProps extends HTMLAttributes<HTMLDivElement> {
  title?: string;
  subtitle?: string;
  action?: ReactNode;
}

export function CardHeader({ title, subtitle, action, className, children, ...rest }: CardHeaderProps) {
  return (
    <div className={cn('flex items-start justify-between gap-4 mb-4', className)} {...rest}>
      <div className="min-w-0 flex-1">
        {title && <h3 className="text-lg font-semibold text-text font-title">{title}</h3>}
        {subtitle && <p className="text-sm text-text-muted mt-1">{subtitle}</p>}
        {children}
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  );
}

export function CardBody({ className, children, ...rest }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div className={cn('text-sm text-text', className)} {...rest}>
      {children}
    </div>
  );
}

export function CardFooter({ className, children, ...rest }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn('mt-5 pt-4 border-t border-border flex items-center justify-between gap-4', className)}
      {...rest}
    >
      {children}
    </div>
  );
}
