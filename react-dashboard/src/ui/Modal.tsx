import { useEffect, useId, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { useFocusTrap } from '../hooks/useFocusTrap';
import { cn } from '../lib/cn';

export interface ModalProps {
  open: boolean;
  onClose: () => void;
  title?: string;
  description?: string;
  size?: 'sm' | 'md' | 'lg' | 'xl' | 'full';
  /** 'center' = dialog centered; 'right' = side drawer sliding from the right */
  placement?: 'center' | 'right';
  closeOnOverlayClick?: boolean;
  closeOnEscape?: boolean;
  footer?: ReactNode;
  children: ReactNode;
  className?: string;
}

const centerSizeStyles = {
  sm: 'max-w-sm',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl',
  full: 'max-w-[95vw] max-h-[95vh]',
};

const rightSizeStyles = {
  sm: 'w-full max-w-sm',
  md: 'w-full max-w-md',
  lg: 'w-full max-w-xl',
  xl: 'w-full max-w-2xl',
  full: 'w-full',
};

/**
 * Modal — accessible dialog with focus trap, Escape to close, portal-based.
 * - role="dialog" aria-modal="true" aria-labelledby aria-describedby
 * - Traps focus inside and restores it on close
 * - Locks body scroll while open
 */
export function Modal({
  open,
  onClose,
  title,
  description,
  size = 'md',
  placement = 'center',
  closeOnOverlayClick = true,
  closeOnEscape = true,
  footer,
  children,
  className,
}: ModalProps) {
  const titleId = useId();
  const descId = useId();
  const containerRef = useFocusTrap<HTMLDivElement>(open);

  // Escape key to close
  useEffect(() => {
    if (!open || !closeOnEscape) return;
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.stopPropagation();
        onClose();
      }
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [open, closeOnEscape, onClose]);

  // Lock body scroll
  useEffect(() => {
    if (!open) return;
    const original = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = original;
    };
  }, [open]);

  if (!open) return null;

  const isDrawer = placement === 'right';
  const containerLayout = isDrawer
    ? 'fixed inset-0 z-50 flex justify-end'
    : 'fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6';
  const dialogShape = isDrawer
    ? cn(
        'relative h-full border-l border-border bg-surface shadow-2xl',
        'flex flex-col animate-slide-in-right',
        rightSizeStyles[size],
      )
    : cn(
        'relative w-full rounded-2xl border border-border bg-surface shadow-xl',
        'flex flex-col max-h-[90vh] animate-scale-in',
        centerSizeStyles[size],
      );

  return createPortal(
    <div className={containerLayout} aria-hidden={false}>
      {/* Overlay */}
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-sm animate-fade-in"
        onClick={closeOnOverlayClick ? onClose : undefined}
        aria-hidden="true"
      />

      {/* Dialog */}
      <div
        ref={containerRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={title ? titleId : undefined}
        aria-describedby={description ? descId : undefined}
        tabIndex={-1}
        className={cn(dialogShape, className)}
      >
        {(title || description) && (
          <div className="flex items-start justify-between gap-4 p-6 border-b border-border">
            <div className="min-w-0 flex-1">
              {title && (
                <h2 id={titleId} className="text-xl font-semibold text-text font-title">
                  {title}
                </h2>
              )}
              {description && (
                <p id={descId} className="text-sm text-text-muted mt-1">
                  {description}
                </p>
              )}
            </div>
            <button
              type="button"
              onClick={onClose}
              aria-label="Fermer"
              className={cn(
                'shrink-0 inline-flex items-center justify-center rounded-lg',
                'h-9 w-9 text-text-muted hover:text-text hover:bg-surface2',
                'transition-colors focus-visible:outline-2 focus-visible:outline-violet',
              )}
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
          </div>
        )}

        <div className="flex-1 overflow-y-auto p-6">{children}</div>

        {footer && (
          <div className="flex items-center justify-end gap-3 p-6 border-t border-border">
            {footer}
          </div>
        )}
      </div>
    </div>,
    document.body,
  );
}
