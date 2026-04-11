import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react';
import { cn } from '../lib/cn';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'outline';
type Size = 'sm' | 'md' | 'lg' | 'icon';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
  loading?: boolean;
  leftIcon?: ReactNode;
  rightIcon?: ReactNode;
  fullWidth?: boolean;
}

const variantStyles: Record<Variant, string> = {
  primary:   'bg-violet text-white hover:bg-violet-dark active:bg-violet-dark shadow-sm hover:shadow-glow-violet',
  secondary: 'bg-surface2 text-text hover:bg-border border border-border',
  ghost:     'bg-transparent text-text hover:bg-surface2',
  danger:    'bg-danger text-white hover:bg-red-600 active:bg-red-700 shadow-sm',
  outline:   'bg-transparent text-text border border-border hover:bg-surface2 hover:border-violet',
};

const sizeStyles: Record<Size, string> = {
  sm:   'h-9 px-3 text-sm gap-1.5 min-h-touch',
  md:   'h-11 px-4 text-sm gap-2 min-h-touch',
  lg:   'h-12 px-6 text-base gap-2.5 min-h-touch',
  icon: 'h-11 w-11 min-h-touch min-w-touch',
};

/**
 * Button — accessible primitive with 5 variants and 4 sizes.
 * - WCAG: min 44x44px touch target
 * - Loading state with spinner
 * - Keyboard focus visible via focus-visible ring (from index.css)
 */
export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  function Button(
    {
      variant = 'primary',
      size = 'md',
      loading = false,
      disabled,
      leftIcon,
      rightIcon,
      fullWidth,
      className,
      type = 'button',
      children,
      'aria-busy': ariaBusy,
      ...rest
    },
    ref,
  ) {
    const isDisabled = disabled || loading;

    return (
      <button
        ref={ref}
        type={type}
        disabled={isDisabled}
        aria-busy={ariaBusy ?? loading}
        className={cn(
          'inline-flex items-center justify-center rounded-lg font-medium',
          'transition-all duration-150',
          'disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none',
          'focus-visible:outline-2 focus-visible:outline-violet focus-visible:outline-offset-2',
          variantStyles[variant],
          sizeStyles[size],
          fullWidth && 'w-full',
          className,
        )}
        {...rest}
      >
        {loading ? (
          <Spinner />
        ) : (
          <>
            {leftIcon && <span className="shrink-0" aria-hidden="true">{leftIcon}</span>}
            {children}
            {rightIcon && <span className="shrink-0" aria-hidden="true">{rightIcon}</span>}
          </>
        )}
      </button>
    );
  },
);

function Spinner() {
  return (
    <span
      role="status"
      aria-label="Chargement"
      className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
    />
  );
}
