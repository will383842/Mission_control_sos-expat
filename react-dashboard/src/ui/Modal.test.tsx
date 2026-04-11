import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Modal } from './Modal';

describe('Modal', () => {
  it('does not render when closed', () => {
    render(
      <Modal open={false} onClose={() => {}} title="Hidden">
        <p>content</p>
      </Modal>,
    );
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('renders dialog with aria-modal when open', () => {
    render(
      <Modal open={true} onClose={() => {}} title="My dialog">
        <p>content</p>
      </Modal>,
    );
    const dialog = screen.getByRole('dialog');
    expect(dialog).toBeInTheDocument();
    expect(dialog).toHaveAttribute('aria-modal', 'true');
  });

  it('labels dialog with title via aria-labelledby', () => {
    render(
      <Modal open={true} onClose={() => {}} title="My dialog">
        <p>content</p>
      </Modal>,
    );
    expect(screen.getByRole('dialog', { name: 'My dialog' })).toBeInTheDocument();
  });

  it('calls onClose when Escape is pressed', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();
    render(
      <Modal open={true} onClose={onClose} title="Test">
        <p>content</p>
      </Modal>,
    );
    await user.keyboard('{Escape}');
    expect(onClose).toHaveBeenCalled();
  });

  it('calls onClose when close button is clicked', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();
    render(
      <Modal open={true} onClose={onClose} title="Test">
        <p>content</p>
      </Modal>,
    );
    await user.click(screen.getByRole('button', { name: /fermer/i }));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('renders footer when provided', () => {
    render(
      <Modal
        open={true}
        onClose={() => {}}
        title="Test"
        footer={<button type="button">Save</button>}
      >
        <p>content</p>
      </Modal>,
    );
    expect(screen.getByRole('button', { name: 'Save' })).toBeInTheDocument();
  });

  it('locks body scroll when open', () => {
    const { rerender } = render(
      <Modal open={false} onClose={() => {}} title="Test">
        <p>content</p>
      </Modal>,
    );
    expect(document.body.style.overflow).not.toBe('hidden');

    rerender(
      <Modal open={true} onClose={() => {}} title="Test">
        <p>content</p>
      </Modal>,
    );
    expect(document.body.style.overflow).toBe('hidden');
  });

  it('supports drawer placement', () => {
    render(
      <Modal open={true} onClose={() => {}} title="Drawer" placement="right">
        <p>content</p>
      </Modal>,
    );
    const dialog = screen.getByRole('dialog');
    expect(dialog.className).toMatch(/slide-in-right/);
  });
});
