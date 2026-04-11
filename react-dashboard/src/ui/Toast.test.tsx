import { describe, it, expect } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import { ToastProvider } from './Toast';
import { useToast } from './toast-context';

function Trigger({ onReady }: { onReady: (api: ReturnType<typeof useToast>) => void }) {
  const api = useToast();
  onReady(api);
  return null;
}

function OutsideProvider() {
  // eslint-disable-next-line react-hooks/rules-of-hooks -- intentional: we test the error throw
  useToast();
  return null;
}

describe('Toast', () => {
  it('throws when useToast is called outside provider', () => {
    // React logs the error to console.error; swallow it for cleaner output
    const originalError = console.error;
    console.error = () => {};
    expect(() => render(<OutsideProvider />)).toThrow(/useToast must be used inside/);
    console.error = originalError;
  });

  it('pushes and renders a toast', () => {
    let push: ReturnType<typeof useToast>['push'] | null = null;
    render(
      <ToastProvider>
        <Trigger onReady={(api) => { push = api.push; }} />
      </ToastProvider>,
    );
    act(() => {
      push!({ variant: 'success', message: 'Saved!', duration: 0 });
    });
    expect(screen.getByRole('status')).toHaveTextContent('Saved!');
  });

  it('renders error toasts with role="alert"', () => {
    let push: ReturnType<typeof useToast>['push'] | null = null;
    render(
      <ToastProvider>
        <Trigger onReady={(api) => { push = api.push; }} />
      </ToastProvider>,
    );
    act(() => {
      push!({ variant: 'error', message: 'Oops', duration: 0 });
    });
    expect(screen.getByRole('alert')).toHaveTextContent('Oops');
  });
});
