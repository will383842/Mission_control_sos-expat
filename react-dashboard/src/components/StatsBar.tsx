import React from 'react';

interface StatItem {
  label: string;
  value: number;
  color: string;
}

interface Props {
  stats: StatItem[];
}

export default function StatsBar({ stats }: Props) {
  return (
    <div className="flex gap-4 overflow-x-auto pb-2">
      {stats.map(item => (
        <div key={item.label} className="bg-surface border border-border rounded-xl px-5 py-3 flex-shrink-0">
          <p className={`text-2xl font-title font-bold ${item.color}`}>{item.value}</p>
          <p className="text-xs text-muted mt-0.5">{item.label}</p>
        </div>
      ))}
    </div>
  );
}
