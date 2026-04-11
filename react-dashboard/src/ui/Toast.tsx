import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '../lib/cn';
import { ToastContext, type Toast, type ToastVariant } from './toast-context';

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);

  const dismiss = useCallback((id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  const push = useCallback((toast: Omit<Toast, 'id'>) => {
    const id = `toast-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const full: Toast = { duration: 5000, ...toast, id };
    setToasts((prev) => [...prev, full]);
    if (full.duration && full.duration > 0) {
      setTimeout(() => dismiss(id), full.duration);
    }
    return id;
  }, [dismiss]);

  const clear = useCallback(() => setToasts([]), []);

  const value = useMemo(() => ({ toasts, push, dismiss, clear }), [toasts, push, dismiss, clear]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      <ToastContainer toasts={toasts} onDismiss={dismiss} />
    </ToastContext.Provider>
  );
}

const variantStyles: Record<ToastVariant, string> = {
  success: 'border-green-500/30 bg-green-500/10',
  error:   'border-danger/30 bg-danger/10',
  warning: 'border-amber-500/30 bg-amber-500/10',
  info:    'border-blue-500/30 bg-blue-500/10',
};

const iconStyles: Record<ToastVariant, string> = {
  success: 'text-green-400',
  error:   'text-danger',
  warning: 'text-amber-400',
  info:    'text-blue-400',
};

function VariantIcon({ variant }: { variant: ToastVariant }) {
  const common = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, 'aria-hidden': true } as const;
  if (variant === 'success')
    return <svg {...common}><polyline points="20 6 9 17 4 12" /></svg>;
  if (variant === 'error')
    return <svg {...common}><circle cx="12" cy="12" r="10" /><line x1="15" y1="9" x2="9" y2="15" /><line x1="9" y1="9" x2="15" y2="15" /></svg>;
  if (variant === 'warning')
    return <svg {...common}><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" /></svg>;
  return <svg {...common}><circle cx="12" cy="12" r="10" /><line x1="12" y1="16" x2="12" y2="12" /><line x1="12" y1="8" x2="12.01" y2="8" /></svg>;
}

function ToastContainer({ toasts, onDismiss }: { toasts: Toast[]; onDismiss: (id: string) => void }) {
  useEffect(() => {
    // Ensure the live region exists for screen readers
  }, []);

  if (toasts.length === 0) return null;

  return createPortal(
    <div
      role="region"
      aria-label="Notifications"
      className="fixed bottom-4 right-4 z-[60] flex flex-col gap-2 w-full max-w-sm pointer-events-none"
    >
      <div aria-live="polite" aria-atomic="false" className="flex flex-col gap-2">
        {toasts.map((toast) => (
          <div
            key={toast.id}
            role={toast.variant === 'error' ? 'alert' : 'status'}
            className={cn(
              'pointer-events-auto rounded-lg border p-4 shadow-lg backdrop-blur',
              'animate-slide-in-right',
              variantStyles[toast.variant],
            )}
          >
            <div className="flex items-start gap-3">
              <span className={cn('shrink-0 mt-0.5', iconStyles[toast.variant])}>
                <VariantIcon variant={toast.variant} />
              </span>
              <div className="flex-1 min-w-0">
                {toast.title && (
                  <p className="text-sm font-semibold text-text">{toast.title}</p>
                )}
                <p className="text-sm text-text-muted">{toast.message}</p>
                {toast.action && (
                  <button
                    type="button"
                    onClick={() => {
                      toast.action?.onClick();
                      onDismiss(toast.id);
                    }}
                    className="mt-2 text-xs font-medium text-violet-light hover:text-violet underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-violet rounded"
                  >
                    {toast.action.label}
                  </button>
                )}
              </div>
              <button
                type="button"
                onClick={() => onDismiss(toast.id)}
                aria-label="Fermer la notification"
                className="shrink-0 text-text-muted hover:text-text focus-visible:outline-2 focus-visible:outline-violet rounded h-6 w-6 inline-flex items-center justify-center"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                  <line x1="18" y1="6" x2="6" y2="18" />
                  <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>,
    document.body,
  );
}
