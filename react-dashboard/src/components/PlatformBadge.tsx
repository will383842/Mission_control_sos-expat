import React from 'react';
import type { Platform } from '../types/influenceur';

const PLATFORM_CONFIG: Record<string, { label: string; color: string }> = {
  instagram: { label: 'Instagram',   color: 'bg-pink-500/20 text-pink-400' },
  tiktok:    { label: 'TikTok',      color: 'bg-slate-500/20 text-slate-300' },
  youtube:   { label: 'YouTube',     color: 'bg-red-500/20 text-red-400' },
  linkedin:  { label: 'LinkedIn',    color: 'bg-blue-500/20 text-blue-400' },
  x:         { label: 'X',           color: 'bg-gray-500/20 text-gray-300' },
  facebook:  { label: 'Facebook',    color: 'bg-blue-600/20 text-blue-500' },
  pinterest: { label: 'Pinterest',   color: 'bg-rose-500/20 text-rose-400' },
  podcast:   { label: 'Podcast',     color: 'bg-purple-500/20 text-purple-400' },
  blog:      { label: 'Blog',        color: 'bg-emerald-500/20 text-emerald-400' },
  newsletter:{ label: 'Newsletter',  color: 'bg-amber/20 text-amber' },
};

interface Props {
  platform: string;
  size?: 'sm' | 'md';
}

export default function PlatformBadge({ platform, size = 'sm' }: Props) {
  const config = PLATFORM_CONFIG[platform] ?? { label: platform, color: 'bg-gray-500/20 text-gray-300' };
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full font-mono ${size === 'sm' ? 'text-xs' : 'text-sm'} ${config.color}`}>
      {config.label}
    </span>
  );
}
