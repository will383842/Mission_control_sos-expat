import { forwardRef, useId, type SelectHTMLAttributes } from 'react';
import { cn } from '../lib/cn';

export interface SelectOption {
  value: string;
  label: string;
  disabled?: boolean;
}

export interface SelectProps extends Omit<SelectHTMLAttributes<HTMLSelectElement>, 'size'> {
  label?: string;
  error?: string;
  help?: string;
  options: SelectOption[];
  placeholder?: string;
  size?: 'sm' | 'md' | 'lg';
}

const sizeStyles = {
  sm: 'h-9 px-3 text-sm',
  md: 'h-11 px-3.5 text-sm min-h-touch',
  lg: 'h-12 px-4 text-base min-h-touch',
};

/**
 * Select — accessible native select with label, error, and help text.
 * Uses native <select> for best a11y + mobile UX (system picker).
 */
export const Select = forwardRef<HTMLSelectElement, SelectProps>(
  function Select(
    { label, error, help, options, placeholder, size = 'md', className, id: providedId, required, ...rest },
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
          <select
            ref={ref}
            id={id}
            required={required}
            aria-invalid={!!error}
            aria-describedby={describedBy}
            className={cn(
              'w-full rounded-lg border bg-surface2 text-text appearance-none pr-10',
              'transition-colors duration-150 cursor-pointer',
              'focus-visible:outline-2 focus-visible:outline-violet focus-visible:outline-offset-0',
              'disabled:opacity-50 disabled:cursor-not-allowed',
              sizeStyles[size],
              error
                ? 'border-danger focus:border-danger'
                : 'border-border focus:border-violet hover:border-text-muted',
              className,
            )}
            {...rest}
          >
            {placeholder && (
              <option value="" disabled>
                {placeholder}
              </option>
            )}
            {options.map((opt) => (
              <option key={opt.value} value={opt.value} disabled={opt.disabled}>
                {opt.label}
              </option>
            ))}
          </select>
          {/* Chevron icon */}
          <span
            aria-hidden="true"
            className="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </span>
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
