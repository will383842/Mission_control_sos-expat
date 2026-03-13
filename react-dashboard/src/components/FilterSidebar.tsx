import React, { useState } from 'react';
import type { InfluenceurFilters, Platform, Status } from '../types/influenceur';

interface Props {
  onFilterChange: (filters: InfluenceurFilters) => void;
}

const STATUSES: { value: Status; label: string }[] = [
  { value: 'prospect', label: 'Prospect' },
  { value: 'contacted', label: 'Contacté' },
  { value: 'negotiating', label: 'Négociation' },
  { value: 'active', label: 'Actif' },
  { value: 'refused', label: 'Refusé' },
  { value: 'inactive', label: 'Inactif' },
];

const PLATFORMS: { value: Platform; label: string }[] = [
  { value: 'instagram', label: 'Instagram' },
  { value: 'tiktok', label: 'TikTok' },
  { value: 'youtube', label: 'YouTube' },
  { value: 'linkedin', label: 'LinkedIn' },
  { value: 'x', label: 'X' },
  { value: 'facebook', label: 'Facebook' },
  { value: 'podcast', label: 'Podcast' },
  { value: 'blog', label: 'Blog' },
  { value: 'newsletter', label: 'Newsletter' },
];

export default function FilterSidebar({ onFilterChange }: Props) {
  const [filters, setFilters] = useState<InfluenceurFilters>({});
  const [search, setSearch] = useState('');

  const update = (newFilters: InfluenceurFilters) => {
    setFilters(newFilters);
    onFilterChange({ ...newFilters, search: search || undefined });
  };

  const handleSearch = (value: string) => {
    setSearch(value);
    onFilterChange({ ...filters, search: value || undefined });
  };

  const reset = () => {
    setFilters({});
    setSearch('');
    onFilterChange({});
  };

  const hasFilters = Object.keys(filters).length > 0 || search;

  return (
    <aside className="w-56 flex-shrink-0 bg-surface border-r border-border p-4 overflow-auto">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-title text-sm font-semibold text-white">Filtres</h3>
        {hasFilters && (
          <button onClick={reset} className="text-xs text-muted hover:text-white transition-colors">
            Réinitialiser
          </button>
        )}
      </div>

      {/* Recherche */}
      <div className="mb-4">
        <input
          type="text"
          value={search}
          onChange={e => handleSearch(e.target.value)}
          placeholder="Nom, handle..."
          className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm placeholder-muted focus:outline-none focus:border-violet transition-colors"
        />
      </div>

      {/* Statut */}
      <div className="mb-4">
        <p className="text-xs text-muted mb-2 font-medium uppercase tracking-wide">Statut</p>
        <div className="space-y-1">
          {STATUSES.map(s => (
            <button
              key={s.value}
              onClick={() => update({ ...filters, status: filters.status === s.value ? undefined : s.value })}
              className={`w-full text-left text-sm px-3 py-1.5 rounded-lg transition-colors ${
                filters.status === s.value ? 'bg-violet/20 text-violet-light' : 'text-muted hover:bg-surface2 hover:text-white'
              }`}
            >
              {s.label}
            </button>
          ))}
        </div>
      </div>

      {/* Plateforme */}
      <div className="mb-4">
        <p className="text-xs text-muted mb-2 font-medium uppercase tracking-wide">Plateforme</p>
        <div className="space-y-1">
          {PLATFORMS.map(p => (
            <button
              key={p.value}
              onClick={() => update({ ...filters, platform: filters.platform === p.value ? undefined : p.value })}
              className={`w-full text-left text-sm px-3 py-1.5 rounded-lg transition-colors ${
                filters.platform === p.value ? 'bg-violet/20 text-violet-light' : 'text-muted hover:bg-surface2 hover:text-white'
              }`}
            >
              {p.label}
            </button>
          ))}
        </div>
      </div>

      {/* Rappels actifs */}
      <div>
        <p className="text-xs text-muted mb-2 font-medium uppercase tracking-wide">Rappels</p>
        <button
          onClick={() => update({ ...filters, has_reminder: !filters.has_reminder })}
          className={`w-full text-left text-sm px-3 py-1.5 rounded-lg transition-colors ${
            filters.has_reminder ? 'bg-amber/20 text-amber' : 'text-muted hover:bg-surface2 hover:text-white'
          }`}
        >
          À relancer uniquement
        </button>
      </div>
    </aside>
  );
}
