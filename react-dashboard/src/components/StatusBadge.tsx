import React from 'react';
import type { Status } from '../types/influenceur';

const STATUS_CONFIG: Record<string, { label: string; color: string }> = {
  prospect:    { label: 'Prospect',       color: 'bg-gray-500/20 text-gray-400' },
  contacted:   { label: 'Contacté',       color: 'bg-cyan/20 text-cyan' },
  negotiating: { label: 'Négociation',    color: 'bg-amber/20 text-amber' },
  active:      { label: 'Actif',          color: 'bg-green-500/20 text-green-400' },
  refused:     { label: 'Refusé',         color: 'bg-red-500/20 text-red-400' },
  inactive:    { label: 'Inactif',        color: 'bg-gray-600/20 text-gray-500' },
};

interface Props {
  status: string | undefined;
  size?: 'sm' | 'md';
}

export default function StatusBadge({ status, size = 'sm' }: Props) {
  if (!status) return null;
  const config = STATUS_CONFIG[status] ?? { label: status, color: 'bg-gray-500/20 text-gray-400' };
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full font-mono ${size === 'sm' ? 'text-xs' : 'text-sm'} ${config.color}`}>
      {config.label}
    </span>
  );
}
