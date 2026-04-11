import { describe, it, expect } from 'vitest';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useFocusTrap } from './useFocusTrap';

function Harness({ active }: { active: boolean }) {
  const ref = useFocusTrap<HTMLDivElement>(active);
  return (
    <div>
      <button type="button">outside-before</button>
      <div ref={ref} data-testid="container">
        <button type="button">first</button>
        <button type="button">middle</button>
        <button type="button">last</button>
      </div>
      <button type="button">outside-after</button>
    </div>
  );
}

describe('useFocusTrap', () => {
  it('focuses the first focusable element when activated', () => {
    render(<Harness active={true} />);
    expect(document.activeElement?.textContent).toBe('first');
  });

  it('does not trap when inactive', async () => {
    const user = userEvent.setup();
    render(<Harness active={false} />);
    await user.tab();
    // Focus starts from body, goes to outside-before — no trap
    expect(document.activeElement?.textContent).toBe('outside-before');
  });

  it('cycles focus with Tab from last to first', async () => {
    const user = userEvent.setup();
    render(<Harness active={true} />);
    // Currently on "first". Tab twice to reach "last"
    await user.tab();
    expect(document.activeElement?.textContent).toBe('middle');
    await user.tab();
    expect(document.activeElement?.textContent).toBe('last');
    // Tab again should wrap to "first"
    await user.tab();
    expect(document.activeElement?.textContent).toBe('first');
  });

  it('cycles focus with Shift+Tab from first to last', async () => {
    const user = userEvent.setup();
    render(<Harness active={true} />);
    // Currently on "first". Shift+Tab should wrap to "last"
    await user.tab({ shift: true });
    expect(document.activeElement?.textContent).toBe('last');
  });
});
