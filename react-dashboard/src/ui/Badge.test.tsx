import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Badge } from './Badge';

describe('Badge', () => {
  it('renders children', () => {
    render(<Badge>Active</Badge>);
    expect(screen.getByText('Active')).toBeInTheDocument();
  });

  it('applies success variant classes', () => {
    render(<Badge variant="success">OK</Badge>);
    expect(screen.getByText('OK').className).toMatch(/text-green/);
  });

  it('applies danger variant classes', () => {
    render(<Badge variant="danger">Fail</Badge>);
    expect(screen.getByText('Fail').className).toMatch(/text-danger/);
  });

  it('renders dot when dot prop is true', () => {
    const { container } = render(<Badge dot variant="info">Info</Badge>);
    expect(container.querySelector('.rounded-full')).not.toBeNull();
  });
});
