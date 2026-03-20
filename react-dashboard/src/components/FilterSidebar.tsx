import React, { useState, useEffect } from 'react';
import api from '../api/client';
import type { InfluenceurFilters, Platform, Status, TeamMember } from '../types/influenceur';

interface Props {
  onFilterChange: (filters: InfluenceurFilters) => void;
}

const STATUSES: { value: Status; label: string }[] = [
  { value: 'prospect', label: 'Prospect' },
  { value: 'contacted', label: 'Contact\u00e9' },
  { value: 'negotiating', label: 'N\u00e9gociation' },
  { value: 'active', label: 'Actif' },
  { value: 'refused', label: 'Refus\u00e9' },
  { value: 'inactive', label: 'Inactif' },
];

const PLATFORMS: { value: Platform; label: string }[] = [
  { value: 'instagram', label: 'Instagram' },
  { value: 'tiktok', label: 'TikTok' },
  { value: 'youtube', label: 'YouTube' },
  { value: 'linkedin', label: 'LinkedIn' },
  { value: 'x', label: 'X' },
  { value: 'facebook', label: 'Facebook' },
  { value: 'pinterest', label: 'Pinterest' },
  { value: 'podcast', label: 'Podcast' },
  { value: 'blog', label: 'Blog' },
  { value: 'newsletter', label: 'Newsletter' },
];

export default function FilterSidebar({ onFilterChange }: Props) {
  const [filters, setFilters] = useState<InfluenceurFilters>({});
  const [search, setSearch] = useState('');
  const [team, setTeam] = useState<TeamMember[]>([]);

  useEffect(() => {
    api.get<TeamMember[]>('/team')
      .then(({ data }) => setTeam(data))
      .catch(() => setTeam([]));
  }, []);

  const update = (newFilters: InfluenceurFilters) => {
    setFilters(newFilters);
    onFilterChange({ ...newFilters, search: search || undefined });
  };

  const handleSearch = (value: string) => {
    setSearch(value);
  };

  // Debounce search input by 400ms
  useEffect(() => {
    const timer = setTimeout(() => {
      onFilterChange({ ...filters, search: search || undefined });
    }, 400);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

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

      {/* Assign\u00e9 \u00e0 */}
      {team.length > 0 && (
        <div className="mb-4">
          <p className="text-xs text-muted mb-2 font-medium uppercase tracking-wide">Assigné à</p>
          <div className="space-y-1">
            {team.map(m => (
              <button
                key={m.id}
                onClick={() => update({ ...filters, assigned_to: filters.assigned_to === m.id ? undefined : m.id })}
                className={`w-full text-left text-sm px-3 py-1.5 rounded-lg transition-colors ${
                  filters.assigned_to === m.id ? 'bg-violet/20 text-violet-light' : 'text-muted hover:bg-surface2 hover:text-white'
                }`}
              >
                {m.name}
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Rappels actifs */}
      <div>
        <p className="text-xs text-muted mb-2 font-medium uppercase tracking-wide">Rappels</p>
        <button
          onClick={() => update({ ...filters, has_reminder: filters.has_reminder ? undefined : true })}
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
