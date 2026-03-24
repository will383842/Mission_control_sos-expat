import React, { useState, useEffect } from 'react';
import api from '../api/client';
import type { ContactType, InfluenceurFilters, PipelineStatus, TeamMember } from '../types/influenceur';
import { CONTACT_TYPES, PIPELINE_STATUSES, COUNTRIES, LANGUAGES } from '../lib/constants';

interface Props {
  onFilterChange: (filters: InfluenceurFilters) => void;
  total?: number;
}

interface CoverageCountry { country: string; total: number }
interface CoverageLanguage { language: string; total: number }

// Group types by sort_order ranges (category)
const TYPE_CATEGORIES = [
  { label: 'Institutionnel', min: 1, max: 9 },
  { label: 'Medias & Influence', min: 10, max: 19 },
  { label: 'Services B2B', min: 20, max: 29 },
  { label: 'Communautes & Lieux', min: 30, max: 39 },
  { label: 'Digital & Technique', min: 40, max: 49 },
];

function getTypeCategory(value: string): string {
  const ct = CONTACT_TYPES.find(t => t.value === value);
  if (!ct) return 'Autre';
  // Use index position as proxy for sort_order
  const idx = CONTACT_TYPES.indexOf(ct);
  if (idx < 5) return 'Institutionnel';
  if (idx < 9) return 'Medias & Influence';
  if (idx < 16) return 'Services B2B';
  if (idx < 21) return 'Communautes & Lieux';
  return 'Digital & Technique';
}

const groupedTypes = TYPE_CATEGORIES.map(cat => ({
  ...cat,
  types: CONTACT_TYPES.filter(t => getTypeCategory(t.value) === cat.label),
})).filter(g => g.types.length > 0);

export default function FilterBar({ onFilterChange, total }: Props) {
  const [filters, setFilters] = useState<InfluenceurFilters>({});
  const [search, setSearch] = useState('');
  const [expanded, setExpanded] = useState(false);
  const [team, setTeam] = useState<TeamMember[]>([]);
  const [countries, setCountries] = useState<CoverageCountry[]>([]);
  const [languages, setLanguages] = useState<CoverageLanguage[]>([]);

  useEffect(() => {
    api.get<TeamMember[]>('/team').then(({ data }) => setTeam(data)).catch(() => {});
    api.get<{ by_country: CoverageCountry[]; by_language: CoverageLanguage[] }>('/stats/coverage')
      .then(({ data }) => {
        setCountries(data.by_country ?? []);
        setLanguages(data.by_language ?? []);
      })
      .catch(() => {});
  }, []);

  const update = (newFilters: InfluenceurFilters) => {
    setFilters(newFilters);
    onFilterChange({ ...newFilters, search: search || undefined });
  };

  // Debounce search
  useEffect(() => {
    const timer = setTimeout(() => {
      onFilterChange({ ...filters, search: search || undefined });
    }, 400);
    return () => clearTimeout(timer);
  }, [search]);

  const reset = () => {
    setFilters({});
    setSearch('');
    onFilterChange({});
  };

  const activeCount = Object.values(filters).filter(v => v !== undefined).length + (search ? 1 : 0);
  const selectClass = 'bg-bg border border-border rounded-lg px-3 py-1.5 text-white text-sm focus:outline-none focus:border-violet transition-colors';

  return (
    <div className="space-y-3">
      {/* Row 1: Search + quick filters */}
      <div className="flex items-center gap-3 flex-wrap">
        {/* Search */}
        <div className="relative flex-1 min-w-[200px] max-w-sm">
          <input
            type="text"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="Rechercher un contact..."
            className="w-full bg-bg border border-border rounded-lg pl-9 pr-3 py-2 text-white text-sm placeholder-muted focus:outline-none focus:border-violet transition-colors"
          />
          <svg className="absolute left-3 top-2.5 w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        {/* Type */}
        <select
          value={filters.contact_type ?? ''}
          onChange={e => update({ ...filters, contact_type: e.target.value ? e.target.value as ContactType : undefined })}
          className={selectClass}
        >
          <option value="">Tous les types</option>
          {groupedTypes.map(group => (
            <optgroup key={group.label} label={`--- ${group.label} ---`}>
              {group.types.map(t => (
                <option key={t.value} value={t.value}>{t.icon} {t.label}</option>
              ))}
            </optgroup>
          ))}
        </select>

        {/* Country */}
        <select
          value={filters.country ?? ''}
          onChange={e => update({ ...filters, country: e.target.value || undefined })}
          className={selectClass}
        >
          <option value="">Tous les pays</option>
          {COUNTRIES.filter(c => c.code !== 'SEPARATOR' && !(c as any).disabled).map(c => {
            const coverage = countries.find(cv => cv.country === c.name);
            return (
              <option key={c.code} value={c.name}>
                {c.flag} {c.name}{coverage ? ` (${coverage.total})` : ''}
              </option>
            );
          })}
        </select>

        {/* Status */}
        <select
          value={filters.status ?? ''}
          onChange={e => update({ ...filters, status: e.target.value ? e.target.value as PipelineStatus : undefined })}
          className={selectClass}
        >
          <option value="">Tous statuts</option>
          {PIPELINE_STATUSES.map(s => (
            <option key={s.value} value={s.value}>{s.label}</option>
          ))}
        </select>

        {/* More filters toggle */}
        <button
          onClick={() => setExpanded(!expanded)}
          className={`px-3 py-1.5 text-sm rounded-lg border transition-colors ${
            expanded || activeCount > 3
              ? 'bg-violet/10 border-violet/30 text-violet-light'
              : 'bg-bg border-border text-muted hover:text-white'
          }`}
        >
          + Filtres{activeCount > 0 ? ` (${activeCount})` : ''}
        </button>

        {/* Reset */}
        {activeCount > 0 && (
          <button onClick={reset} className="text-xs text-muted hover:text-white transition-colors">
            Reinitialiser
          </button>
        )}

        {/* Count */}
        {total !== undefined && (
          <span className="text-xs text-muted ml-auto">{total} contact{total !== 1 ? 's' : ''}</span>
        )}
      </div>

      {/* Row 2: Expanded filters */}
      {expanded && (
        <div className="flex items-center gap-3 flex-wrap bg-surface/50 border border-border rounded-lg px-4 py-3">
          {/* Language */}
          <div className="flex items-center gap-2">
            <span className="text-xs text-muted">Langue</span>
            <select
              value={filters.language ?? ''}
              onChange={e => update({ ...filters, language: e.target.value || undefined })}
              className={selectClass}
            >
              <option value="">Toutes</option>
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

          {/* Assigned to */}
          {team.length > 0 && (
            <div className="flex items-center gap-2">
              <span className="text-xs text-muted">Assigne</span>
              <select
                value={filters.assigned_to ?? ''}
                onChange={e => update({ ...filters, assigned_to: e.target.value ? Number(e.target.value) : undefined })}
                className={selectClass}
              >
                <option value="">Tous</option>
                {team.map(m => (
                  <option key={m.id} value={m.id}>{m.name}</option>
                ))}
              </select>
            </div>
          )}

          {/* Reminder */}
          <label className="flex items-center gap-2 cursor-pointer text-sm">
            <input
              type="checkbox"
              checked={!!filters.has_reminder}
              onChange={() => update({ ...filters, has_reminder: filters.has_reminder ? undefined : true })}
              className="rounded border-gray-600 bg-bg text-violet focus:ring-violet"
            />
            <span className={filters.has_reminder ? 'text-amber' : 'text-muted'}>A relancer</span>
          </label>
        </div>
      )}
    </div>
  );
}
