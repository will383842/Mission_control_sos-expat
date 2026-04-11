import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Input } from './Input';

describe('Input', () => {
  it('renders label and input', () => {
    render(<Input label="Email" />);
    expect(screen.getByLabelText('Email')).toBeInTheDocument();
  });

  it('associates label with input via htmlFor/id', () => {
    render(<Input label="Name" />);
    const input = screen.getByLabelText('Name');
    const label = document.querySelector('label')!;
    expect(label.getAttribute('for')).toBe(input.id);
  });

  it('shows error message and sets aria-invalid', () => {
    render(<Input label="Email" error="Invalid email" />);
    const input = screen.getByLabelText('Email');
    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByRole('alert')).toHaveTextContent('Invalid email');
  });

  it('shows help text and links it via aria-describedby', () => {
    render(<Input label="Email" help="We will never share it" />);
    const input = screen.getByLabelText('Email');
    const describedBy = input.getAttribute('aria-describedby');
    expect(describedBy).toBeTruthy();
    expect(document.getElementById(describedBy!)).toHaveTextContent('We will never share it');
  });

  it('renders required indicator when required', () => {
    render(<Input label="Name" required />);
    expect(screen.getByLabelText(/requis/i)).toBeInTheDocument();
  });

  it('hides help text when error is present', () => {
    render(<Input label="Email" help="Help text" error="Error text" />);
    expect(screen.queryByText('Help text')).not.toBeInTheDocument();
    expect(screen.getByText('Error text')).toBeInTheDocument();
  });
});
