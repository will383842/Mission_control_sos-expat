/* eslint-disable react-refresh/only-export-components -- co-located CONTACT_TYPE_OPTIONS export is used across the codebase */
import React from 'react';
import type { ContactType } from '../types/influenceur';
import { CONTACT_TYPES, CONTACT_TYPE_MAP } from '../lib/constants';

interface Props {
  type: ContactType;
  size?: 'sm' | 'md';
  showIcon?: boolean;
}

export default function ContactTypeBadge({ type, size = 'sm', showIcon = false }: Props) {
  const config = CONTACT_TYPE_MAP[type] ?? { value: type, label: type ?? 'Autre', icon: '📁', color: '#6B7280', bg: 'bg-gray-500/20', text: 'text-gray-400' };
  const sizeClass = size === 'sm' ? 'text-[10px] px-1.5 py-0.5' : 'text-xs px-2 py-1';

  return (
    <span className={`${config.bg} ${config.text} ${sizeClass} rounded-full font-medium whitespace-nowrap inline-flex items-center gap-1`}>
      {showIcon && <span>{config.icon}</span>}
      {config.label}
    </span>
  );
}

// Re-export for backward compatibility
export const CONTACT_TYPE_OPTIONS = CONTACT_TYPES.map(t => ({
  value: t.value,
  label: t.label,
  icon: t.icon,
}));
