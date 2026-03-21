import React, { useState, useEffect } from 'react';
import api from '../api/client';
import type { ContactType, InfluenceurFilters, Platform, PipelineStatus, TeamMember } from '../types/influenceur';
import { CONTACT_TYPES, PIPELINE_STATUSES, COUNTRIES, LANGUAGES } from '../lib/constants';

interface Props {
  onFilterChange: (filters: InfluenceurFilters) => void;
  onClose?: () => void;
}

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
  { value: 'website', label: 'Website' },
];

interface CoverageCountry { country: string; total: number }
interface CoverageLanguage { language: string; total: number }

export default function FilterSidebar({ onFilterChange, onClose }: Props) {
  const [filters, setFilters] = useState<InfluenceurFilters>({});
  const [search, setSearch] = useState('');
  const [team, setTeam] = useState<TeamMember[]>([]);
  const [countries, setCountries] = useState<CoverageCountry[]>([]);
  const [languages, setLanguages] = useState<CoverageLanguage[]>([]);

  useEffect(() => {
    api.get<TeamMember[]>('/team')
      .then(({ data }) => setTeam(data))
      .catch(() => setTeam([]));

    // Load available countries and languages from coverage endpoint (admin)
    // or fallback gracefully for non-admin users
    api.get<{ by_country: CoverageCountry[]; by_language: CoverageLanguage[] }>('/stats/coverage')
      .then(({ data }) => {
        setCountries(data.by_country ?? []);
        setLanguages(data.by_language ?? []);
      })
      .catch(() => {
        // Non-admin: endpoint returns 403, lists stay empty (dropdowns hidden)
      });
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
    <aside className={`${onClose ? 'fixed inset-0 z-50 bg-surface overflow-auto' : 'w-56 flex-shrink-0 bg-surface border-r border-border overflow-auto hidden md:block'} p-4`}>
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-title text-sm font-semibold text-white">Filtres</h3>
        <div className="flex items-center gap-2">
          {hasFilters && (
            <button onClick={reset} className="text-xs text-muted hover:text-white transition-colors">
              Réinitialiser
            </button>
          )}
          {onClose && (
            <button onClick={onClose} className="text-muted hover:text-white transition-colors" aria-label="Fermer les filtres">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                <line x1="4" y1="4" x2="16" y2="16" />
                <line x1="16" y1="4" x2="4" y2="16" />
              </svg>
            </button>
          )}
        </div>
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

      {/* Type de contact */}
      <div className="mb-4">
        <p className="text-xs text-muted mb-2 font-medium uppercase tracking-wide">Type</p>
        <select
          value={filters.contact_type ?? ''}
          onChange={e => update({ ...filters, contact_type: e.target.value ? e.target.value as ContactType : undefined })}
          className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
        >
          <option value="">Tous les types</option>
          {CONTACT_TYPES.map(t => (
            <option key={t.value} value={t.value}>{t.icon} {t.label}</option>
          ))}
        </select>
      </div>

      {/* Statut */}
      <div className="mb-4">
        <p className="text-xs text-muted mb-2 font-medium uppercase tracking-wide">Statut</p>
        <select
          value={filters.status ?? ''}
          onChange={e => update({ ...filters, status: e.target.value ? e.target.value as PipelineStatus : undefined })}
          className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
        >
          <option value="">Tous les statuts</option>
          {PIPELINE_STATUSES.map(s => (
            <option key={s.value} value={s.value}>{s.label}</option>
          ))}
        </select>
      </div>

      {/* Plateforme */}
      <div className="mb-4">
        <p className="text-xs text-muted mb-2 font-medium uppercase tracking-wide">Plateforme</p>
        <select
          value={filters.platform ?? ''}
          onChange={e => update({ ...filters, platform: e.target.value ? e.target.value as Platform : undefined })}
          className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
        >
          <option value="">Toutes les plateformes</option>
          {PLATFORMS.map(p => (
            <option key={p.value} value={p.value}>{p.label}</option>
          ))}
        </select>
      </div>

      {/* Pays */}
      <div className="mb-4">
        <p className="text-xs text-muted mb-2 font-medium uppercase tracking-wide">Pays</p>
        <select
          value={filters.country ?? ''}
          onChange={e => update({ ...filters, country: e.target.value || undefined })}
          className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
        >
          <option value="">Tous les pays</option>
          {COUNTRIES.map(c => {
            const coverage = countries.find(cv => cv.country === c.name);
            return (
              <option key={c.code} value={c.name}>
                {c.flag} {c.name}{coverage ? ` (${coverage.total})` : ''}
              </option>
            );
          })}
        </select>
      </div>

      {/* Langue */}
      <div className="mb-4">
        <p className="text-xs text-muted mb-2 font-medium uppercase tracking-wide">Langue</p>
        <select
          value={filters.language ?? ''}
          onChange={e => update({ ...filters, language: e.target.value || undefined })}
          className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
        >
          <option value="">Toutes les langues</option>
          {LANGUAGES.map(l => {
            const coverage = languages.find(lv => lv.language === l.code);
            return (
              <option key={l.code} value={l.code}>
                {l.flag} {l.label}{coverage ? ` (${coverage.total})` : ''}
              </option>
            );
          })}
        </select>
      </div>

      {/* Assigné à */}
      {team.length > 0 && (
        <div className="mb-4">
          <p className="text-xs text-muted mb-2 font-medium uppercase tracking-wide">Assigné à</p>
          <select
            value={filters.assigned_to ?? ''}
            onChange={e => update({ ...filters, assigned_to: e.target.value ? Number(e.target.value) : undefined })}
            className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
          >
            <option value="">Tous les membres</option>
            {team.map(m => (
              <option key={m.id} value={m.id}>{m.name}</option>
            ))}
          </select>
        </div>
      )}

      {/* Rappels actifs */}
      <div>
        <p className="text-xs text-muted mb-2 font-medium uppercase tracking-wide">Rappels</p>
        <label className="flex items-center gap-2 cursor-pointer text-sm px-1 py-1">
          <input
            type="checkbox"
            checked={!!filters.has_reminder}
            onChange={() => update({ ...filters, has_reminder: filters.has_reminder ? undefined : true })}
            className="w-4 h-4 rounded border-border bg-surface2 text-violet accent-violet"
          />
          <span className={filters.has_reminder ? 'text-amber' : 'text-muted'}>
            À relancer uniquement
          </span>
        </label>
      </div>
    </aside>
  );
}
