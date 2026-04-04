import React, { useState, useEffect, useRef } from 'react';
import api from '../api/client';
import type { ContactType, ContactCategory, ContactKind, InfluenceurFilters, PipelineStatus, TeamMember } from '../types/influenceur';
import { CONTACT_TYPES, CONTACT_CATEGORIES, PIPELINE_STATUSES, COUNTRIES, LANGUAGES } from '../lib/constants';

interface Props {
  onFilterChange: (filters: InfluenceurFilters) => void;
  total?: number;
  summary?: { with_email: number; with_phone: number; verified: number } | null;
  initialFilters?: InfluenceurFilters;
  /** Si défini, la catégorie est verrouillée (affichée en badge, non modifiable) */
  lockedCategory?: ContactCategory;
}

interface CoverageCountry { country: string; total: number }
interface CoverageLanguage { language: string; total: number }

// Sources connues
const SOURCES = [
  { value: 'scraping',     label: '🕷️ Scraping auto' },
  { value: 'ai_research',  label: '🤖 Recherche IA' },
  { value: 'manual',       label: '✏️ Ajout manuel' },
  { value: 'import',       label: '📥 Import CSV/batch' },
  { value: 'directory',    label: '📚 Annuaire' },
];

// Scores de complétude
const COMPLETENESS_OPTIONS = [
  { value: 0,  label: 'Tous' },
  { value: 50, label: '≥ 50%' },
  { value: 75, label: '≥ 75%' },
  { value: 90, label: '≥ 90%' },
];

type SearchField = 'all' | 'name' | 'email' | 'phone' | 'company' | 'url';

const SEARCH_FIELDS: { value: SearchField; label: string }[] = [
  { value: 'all',     label: 'Tout' },
  { value: 'name',    label: 'Nom' },
  { value: 'email',   label: 'Email' },
  { value: 'phone',   label: 'Tél.' },
  { value: 'company', label: 'Société' },
  { value: 'url',     label: 'URL' },
];

export default function FilterBar({ onFilterChange, total, summary, initialFilters, lockedCategory }: Props) {
  const [filters, setFilters] = useState<InfluenceurFilters>(initialFilters ?? {});
  const [search, setSearch] = useState('');
  const [searchField, setSearchField] = useState<SearchField>('all');
  const [showAdvanced, setShowAdvanced] = useState(false);
  const [team, setTeam] = useState<TeamMember[]>([]);
  const [countries, setCountries] = useState<CoverageCountry[]>([]);
  const [languages, setLanguages] = useState<CoverageLanguage[]>([]);
  const searchTimer = useRef<ReturnType<typeof setTimeout>>();
  const isMounted = useRef(false);

  useEffect(() => {
    api.get<TeamMember[]>('/team').then(({ data }) => setTeam(data)).catch(() => {});
    api.get<{ by_country: CoverageCountry[]; by_language: CoverageLanguage[] }>('/stats/coverage')
      .then(({ data }) => {
        setCountries(data.by_country ?? []);
        setLanguages(data.by_language ?? []);
      })
      .catch(() => {});
  }, []);

  const emit = (newFilters: InfluenceurFilters, newSearch = search) => {
    const searchVal = newSearch.trim() || undefined;
    // Préfixe le champ de recherche si un champ précis est sélectionné
    const finalSearch = searchVal && searchField !== 'all'
      ? `${searchField}:${searchVal}`
      : searchVal;
    // Si catégorie verrouillée, toujours l'inclure dans les filtres émis
    const categoryOverride = lockedCategory ? { category: lockedCategory } : {};
    onFilterChange({ ...newFilters, search: finalSearch, ...categoryOverride });
  };

  const update = (patch: Partial<InfluenceurFilters>) => {
    const next = { ...filters, ...patch };
    // Nettoie les undefined
    Object.keys(next).forEach(k => (next as Record<string, unknown>)[k] === undefined && delete (next as Record<string, unknown>)[k]);
    setFilters(next);
    emit(next);
  };

  // Debounce de la recherche texte (ne se déclenche pas au montage)
  useEffect(() => {
    if (!isMounted.current) { isMounted.current = true; return; }
    clearTimeout(searchTimer.current);
    searchTimer.current = setTimeout(() => emit(filters, search), 350);
    return () => clearTimeout(searchTimer.current);
  }, [search, searchField]);

  const reset = () => {
    setFilters({});
    setSearch('');
    setSearchField('all');
    onFilterChange({});
  };

  // Comptage des filtres actifs (hors search, hors catégorie verrouillée)
  const activeFilters = Object.entries(filters).filter(([k, v]) => v !== undefined && v !== false && !(lockedCategory && k === 'category'));
  const activeCount = activeFilters.length + (search ? 1 : 0);

  // Chip d'un filtre actif
  const chipLabel = (key: string, value: unknown): string => {
    if (key === 'contact_type') return CONTACT_TYPES.find(t => t.value === value)?.label ?? String(value);
    if (key === 'category') return CONTACT_CATEGORIES.find(c => c.value === value)?.label ?? String(value);
    if (key === 'status') return PIPELINE_STATUSES.find(s => s.value === value)?.label ?? String(value);
    if (key === 'language') return LANGUAGES.find(l => l.code === value)?.label ?? String(value);
    if (key === 'country') return String(value);
    if (key === 'has_email') return 'A un email';
    if (key === 'has_phone') return 'A un tél.';
    if (key === 'is_verified') return 'Vérifié';
    if (key === 'unsubscribed') return 'Désabonné';
    if (key === 'contact_kind') return value === 'individual' ? 'Personne' : 'Organisation';
    if (key === 'completeness_min') return `Complétude ≥ ${value}%`;
    if (key === 'source') return SOURCES.find(s => s.value === value)?.label ?? String(value);
    if (key === 'has_reminder') return 'À relancer';
    if (key === 'assigned_to') return `Assigné: ${team.find(m => m.id === value)?.name ?? value}`;
    return `${key}: ${value}`;
  };

  const removeFilter = (key: keyof InfluenceurFilters) => {
    const next = { ...filters };
    delete next[key];
    setFilters(next);
    emit(next);
  };

  const sel = 'bg-bg border border-border rounded-lg px-3 py-1.5 text-white text-sm focus:outline-none focus:border-violet transition-colors';
  const selSm = `${sel} text-xs`;

  return (
    <div className="space-y-2">

      {/* ── Ligne 1 : Recherche + Filtres rapides ── */}
      <div className="flex items-center gap-2 flex-wrap">

        {/* Champ de recherche avec sélecteur de champ */}
        <div className="relative flex items-center bg-bg border border-border rounded-lg overflow-hidden flex-1 min-w-[240px] max-w-md focus-within:border-violet transition-colors">
          <select
            value={searchField}
            onChange={e => setSearchField(e.target.value as SearchField)}
            className="bg-surface2 border-r border-border text-muted text-xs px-2 py-2 focus:outline-none"
          >
            {SEARCH_FIELDS.map(f => (
              <option key={f.value} value={f.value}>{f.label}</option>
            ))}
          </select>
          <input
            type="text"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder={
              searchField === 'email' ? 'contact@exemple.com' :
              searchField === 'phone' ? '+33 6...' :
              searchField === 'company' ? 'Nom de société...' :
              searchField === 'url' ? 'https://...' :
              'Rechercher un contact...'
            }
            className="flex-1 bg-transparent px-3 py-2 text-white text-sm placeholder-muted focus:outline-none"
          />
          {search && (
            <button onClick={() => setSearch('')} className="px-2 text-muted hover:text-white">✕</button>
          )}
          {!search && (
            <svg className="w-4 h-4 text-muted mr-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          )}
        </div>

        {/* Catégorie — verrouillée ou libre */}
        {lockedCategory ? (
          <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg border ${
            CONTACT_CATEGORIES.find(c => c.value === lockedCategory)?.bg ?? 'bg-surface'
          } ${
            CONTACT_CATEGORIES.find(c => c.value === lockedCategory)?.border ?? 'border-border'
          } ${
            CONTACT_CATEGORIES.find(c => c.value === lockedCategory)?.text ?? 'text-white'
          }`}>
            {CONTACT_CATEGORIES.find(c => c.value === lockedCategory)?.icon}{' '}
            {CONTACT_CATEGORIES.find(c => c.value === lockedCategory)?.label}
          </span>
        ) : (
          <select
            value={filters.category ?? ''}
            onChange={e => update({ category: (e.target.value as ContactCategory) || undefined, contact_type: undefined })}
            className={sel}
          >
            <option value="">Toutes catégories</option>
            {CONTACT_CATEGORIES.map(c => (
              <option key={c.value} value={c.value}>{c.icon} {c.label}</option>
            ))}
          </select>
        )}

        {/* Type (filtré par catégorie si sélectionnée) */}
        <select
          value={filters.contact_type ?? ''}
          onChange={e => update({ contact_type: e.target.value as ContactType || undefined })}
          className={sel}
        >
          <option value="">Tous les types</option>
          {(filters.category
            ? CONTACT_TYPES.filter(t => CONTACT_CATEGORIES.find(c => c.value === filters.category)?.types.includes(t.value))
            : CONTACT_TYPES
          ).map(t => (
            <option key={t.value} value={t.value}>{t.icon} {t.label}</option>
          ))}
        </select>

        {/* Pays */}
        <select
          value={filters.country ?? ''}
          onChange={e => update({ country: e.target.value || undefined })}
          className={sel}
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

        {/* Statut */}
        <select
          value={filters.status ?? ''}
          onChange={e => update({ status: e.target.value as PipelineStatus || undefined })}
          className={sel}
        >
          <option value="">Tous statuts</option>
          {PIPELINE_STATUSES.map(s => (
            <option key={s.value} value={s.value}>{s.label}</option>
          ))}
        </select>

        {/* Bouton filtres avancés */}
        <button
          onClick={() => setShowAdvanced(!showAdvanced)}
          className={`flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg border transition-colors ${
            showAdvanced || activeCount > 3
              ? 'bg-violet/10 border-violet/30 text-violet-light'
              : 'bg-bg border-border text-muted hover:text-white'
          }`}
        >
          <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4h18M6 12h12M10 20h4" />
          </svg>
          Filtres{activeCount > 0 ? ` (${activeCount})` : ''}
        </button>

        {/* Reset */}
        {activeCount > 0 && (
          <button onClick={reset} className="text-xs text-muted hover:text-red-400 transition-colors px-1">
            ✕ Réinitialiser
          </button>
        )}

        {/* Compteur */}
        {total !== undefined && (
          <span className="text-xs text-muted ml-auto tabular-nums">
            {total.toLocaleString()} contact{total !== 1 ? 's' : ''}
          </span>
        )}
      </div>

      {/* ── Ligne 2 : Filtres avancés ── */}
      {showAdvanced && (
        <div className="bg-surface/50 border border-border rounded-xl px-4 py-3 space-y-3">
          <div className="flex flex-wrap items-center gap-4">

            {/* Langue */}
            <div className="flex items-center gap-2">
              <span className="text-xs text-muted whitespace-nowrap">Langue</span>
              <select
                value={filters.language ?? ''}
                onChange={e => update({ language: e.target.value || undefined })}
                className={selSm}
              >
                <option value="">Toutes</option>
                {LANGUAGES.map(l => {
                  const cov = languages.find(lv => lv.language === l.code);
                  return (
                    <option key={l.code} value={l.code}>
                      {l.flag} {l.label}{cov ? ` (${cov.total})` : ''}
                    </option>
                  );
                })}
              </select>
            </div>

            {/* Individu vs Organisation */}
            <div className="flex items-center gap-2">
              <span className="text-xs text-muted whitespace-nowrap">Type entité</span>
              <select
                value={filters.contact_kind ?? ''}
                onChange={e => update({ contact_kind: e.target.value as ContactKind || undefined })}
                className={selSm}
              >
                <option value="">Tous</option>
                <option value="individual">👤 Personne</option>
                <option value="organization">🏢 Organisation</option>
              </select>
            </div>

            {/* Source */}
            <div className="flex items-center gap-2">
              <span className="text-xs text-muted whitespace-nowrap">Source</span>
              <select
                value={filters.source ?? ''}
                onChange={e => update({ source: e.target.value || undefined })}
                className={selSm}
              >
                <option value="">Toutes</option>
                {SOURCES.map(s => (
                  <option key={s.value} value={s.value}>{s.label}</option>
                ))}
              </select>
            </div>

            {/* Complétude */}
            <div className="flex items-center gap-2">
              <span className="text-xs text-muted whitespace-nowrap">Complétude</span>
              <select
                value={filters.completeness_min ?? 0}
                onChange={e => update({ completeness_min: Number(e.target.value) || undefined })}
                className={selSm}
              >
                {COMPLETENESS_OPTIONS.map(o => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </select>
            </div>

            {/* Assigné à */}
            {team.length > 0 && (
              <div className="flex items-center gap-2">
                <span className="text-xs text-muted whitespace-nowrap">Assigné</span>
                <select
                  value={filters.assigned_to ?? ''}
                  onChange={e => update({ assigned_to: e.target.value ? Number(e.target.value) : undefined })}
                  className={selSm}
                >
                  <option value="">Tous</option>
                  {team.map(m => (
                    <option key={m.id} value={m.id}>{m.name}</option>
                  ))}
                </select>
              </div>
            )}
          </div>

          {/* Toggles booléens */}
          <div className="flex flex-wrap items-center gap-4 pt-1 border-t border-border">
            <span className="text-xs text-muted">Filtres qualité :</span>

            {[
              { key: 'has_email' as const,   label: '✉️ A un email',       activeClass: 'bg-cyan-500/15 border-cyan-500/40 text-cyan-300' },
              { key: 'has_phone' as const,   label: '📞 A un tél.',         activeClass: 'bg-teal-500/15 border-teal-500/40 text-teal-300' },
              { key: 'is_verified' as const, label: '✅ Vérifié',           activeClass: 'bg-green-500/15 border-green-500/40 text-green-300' },
              { key: 'has_reminder' as const,label: '🔔 À relancer',        activeClass: 'bg-amber-500/15 border-amber-500/40 text-amber-300' },
              { key: 'unsubscribed' as const,label: '🚫 Désabonnés',        activeClass: 'bg-red-500/15 border-red-500/40 text-red-400' },
            ].map(({ key, label, activeClass }) => {
              const isActive = !!filters[key];
              return (
                <button
                  key={key}
                  onClick={() => update({ [key]: isActive ? undefined : true })}
                  className={`px-3 py-1 text-xs rounded-full border transition-colors ${
                    isActive ? activeClass : 'bg-bg border-border text-muted hover:border-violet/40 hover:text-white'
                  }`}
                >
                  {label}
                </button>
              );
            })}
          </div>
        </div>
      )}

      {/* ── Ligne 3 : Chips filtres actifs ── */}
      {activeFilters.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {activeFilters.map(([key, value]) => (
            <span
              key={key}
              className="inline-flex items-center gap-1 px-2.5 py-1 bg-violet/10 border border-violet/25 text-violet-light text-xs rounded-full"
            >
              {chipLabel(key, value)}
              <button
                onClick={() => removeFilter(key as keyof InfluenceurFilters)}
                className="ml-0.5 hover:text-white transition-colors leading-none"
              >
                ✕
              </button>
            </span>
          ))}
          {search && (
            <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-violet/10 border border-violet/25 text-violet-light text-xs rounded-full">
              "{search}"
              <button onClick={() => setSearch('')} className="ml-0.5 hover:text-white transition-colors">✕</button>
            </span>
          )}
        </div>
      )}
    </div>
  );
}
