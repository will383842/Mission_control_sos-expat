/* eslint-disable react-refresh/only-export-components -- legacy global-state toast, see ui/Toast for the React Query-friendly replacement */
import React, { useState, useCallback, useEffect } from 'react';

type ToastType = 'success' | 'error' | 'warning' | 'info';

interface ToastMessage {
  id: number;
  type: ToastType;
  message: string;
}

let addToastFn: ((type: ToastType, message: string) => void) | null = null;

export function toast(type: ToastType, message: string) {
  addToastFn?.(type, message);
}

export function ToastContainer() {
  const [toasts, setToasts] = useState<ToastMessage[]>([]);

  const addToast = useCallback((type: ToastType, message: string) => {
    const id = Date.now();
    setToasts(prev => [...prev, { id, type, message }]);
    setTimeout(() => setToasts(prev => prev.filter(t => t.id !== id)), 4000);
  }, []);

  useEffect(() => {
    addToastFn = addToast;
    return () => { addToastFn = null; };
  }, [addToast]);

  if (toasts.length === 0) return null;

  const colors: Record<ToastType, string> = {
    success: 'bg-emerald-600 border-emerald-500',
    error: 'bg-red-600 border-red-500',
    warning: 'bg-amber-600 border-amber-500',
    info: 'bg-blue-600 border-blue-500',
  };

  const icons: Record<ToastType, string> = {
    success: '\u2713',
    error: '\u2715',
    warning: '\u26A0',
    info: '\u2139',
  };

  return (
    <div className="fixed top-4 right-4 z-50 flex flex-col gap-2" style={{ maxWidth: 400 }}>
      {toasts.map(t => (
        <div
          key={t.id}
          className={`${colors[t.type]} border-l-4 text-white px-4 py-3 rounded shadow-lg flex items-center gap-2 animate-slide-in`}
          role="alert"
        >
          <span className="text-lg font-bold">{icons[t.type]}</span>
          <span className="text-sm">{t.message}</span>
          <button
            className="ml-auto text-white/60 hover:text-white"
            onClick={() => setToasts(prev => prev.filter(x => x.id !== t.id))}
          >
            {'\u2715'}
          </button>
        </div>
      ))}
    </div>
  );
}
