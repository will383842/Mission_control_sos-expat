import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Button } from './Button';

describe('Button', () => {
  it('renders children', () => {
    render(<Button>Click me</Button>);
    expect(screen.getByRole('button', { name: 'Click me' })).toBeInTheDocument();
  });

  it('defaults to type="button"', () => {
    render(<Button>Submit</Button>);
    expect(screen.getByRole('button')).toHaveAttribute('type', 'button');
  });

  it('fires onClick', async () => {
    const onClick = vi.fn();
    const user = userEvent.setup();
    render(<Button onClick={onClick}>Go</Button>);
    await user.click(screen.getByRole('button'));
    expect(onClick).toHaveBeenCalledTimes(1);
  });

  it('is disabled and aria-busy when loading', () => {
    render(<Button loading>Saving</Button>);
    const btn = screen.getByRole('button');
    expect(btn).toBeDisabled();
    expect(btn).toHaveAttribute('aria-busy', 'true');
  });

  it('shows spinner instead of children when loading', () => {
    render(<Button loading>Saving</Button>);
    expect(screen.queryByText('Saving')).not.toBeInTheDocument();
    expect(screen.getByRole('status', { name: /chargement/i })).toBeInTheDocument();
  });

  it('does not fire onClick when disabled', async () => {
    const onClick = vi.fn();
    const user = userEvent.setup();
    render(<Button disabled onClick={onClick}>Go</Button>);
    await user.click(screen.getByRole('button'));
    expect(onClick).not.toHaveBeenCalled();
  });

  it('applies danger variant classes', () => {
    render(<Button variant="danger">Delete</Button>);
    expect(screen.getByRole('button').className).toMatch(/bg-danger/);
  });

  it('applies fullWidth when requested', () => {
    render(<Button fullWidth>Wide</Button>);
    expect(screen.getByRole('button').className).toMatch(/w-full/);
  });
});
