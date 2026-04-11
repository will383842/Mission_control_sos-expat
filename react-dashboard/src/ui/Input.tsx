import { forwardRef, useId, type InputHTMLAttributes, type ReactNode } from 'react';
import { cn } from '../lib/cn';

export interface InputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'size'> {
  label?: string;
  error?: string;
  help?: string;
  leftIcon?: ReactNode;
  rightIcon?: ReactNode;
  size?: 'sm' | 'md' | 'lg';
}

const sizeStyles = {
  sm: 'h-9 px-3 text-sm',
  md: 'h-11 px-3.5 text-sm min-h-touch',
  lg: 'h-12 px-4 text-base min-h-touch',
};

/**
 * Input — accessible input with label, error and help text.
 * - Auto-generates an id if none provided
 * - Associates label, error and help via aria-describedby + aria-invalid
 * - WCAG 44px min height on md/lg
 */
export const Input = forwardRef<HTMLInputElement, InputProps>(
  function Input(
    { label, error, help, leftIcon, rightIcon, size = 'md', className, id: providedId, required, ...rest },
    ref,
  ) {
    const generatedId = useId();
    const id = providedId ?? generatedId;
    const errorId = `${id}-error`;
    const helpId = `${id}-help`;

    const describedBy = [error ? errorId : null, help ? helpId : null].filter(Boolean).join(' ') || undefined;

    return (
      <div className="flex flex-col gap-1.5">
        {label && (
          <label htmlFor={id} className="text-sm font-medium text-text">
            {label}
            {required && <span className="ml-0.5 text-danger" aria-label="requis">*</span>}
          </label>
        )}
        <div className="relative">
          {leftIcon && (
            <span
              className="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none"
              aria-hidden="true"
            >
              {leftIcon}
            </span>
          )}
          <input
            ref={ref}
            id={id}
            required={required}
            aria-invalid={!!error}
            aria-describedby={describedBy}
            className={cn(
              'w-full rounded-lg border bg-surface2 text-text',
              'placeholder:text-text-muted',
              'transition-colors duration-150',
              'focus-visible:outline-2 focus-visible:outline-violet focus-visible:outline-offset-0',
              'disabled:opacity-50 disabled:cursor-not-allowed',
              sizeStyles[size],
              leftIcon && 'pl-10',
              rightIcon && 'pr-10',
              error
                ? 'border-danger focus:border-danger'
                : 'border-border focus:border-violet hover:border-text-muted',
              className,
            )}
            {...rest}
          />
          {rightIcon && (
            <span
              className="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none"
              aria-hidden="true"
            >
              {rightIcon}
            </span>
          )}
        </div>
        {error && (
          <p id={errorId} role="alert" className="text-xs text-danger">
            {error}
          </p>
        )}
        {help && !error && (
          <p id={helpId} className="text-xs text-text-muted">
            {help}
          </p>
        )}
      </div>
    );
  },
);
