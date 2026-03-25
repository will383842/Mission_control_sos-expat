import React, { useEffect, useState, useCallback, useMemo } from 'react';
import api from '../api/client';
import { COUNTRIES } from '../lib/constants';
import { countriesData } from '../data/countries-full';

// ============================================================
// Types
// ============================================================
interface Campaign {
  id: number;
  name: string;
  status: 'pending' | 'queued' | 'running' | 'paused' | 'completed' | 'cancelled';
  contact_types: string[];
  countries: string[];
  languages: string[];
  delay_between_tasks_seconds: number;
  max_retries: number;
  tasks_total: number;
  tasks_completed: number;
  tasks_failed: number;
  tasks_skipped: number;
  contacts_found_total: number;
  contacts_imported_total: number;
  total_cost_cents: number;
  consecutive_failures: number;
  max_consecutive_failures: number;
  started_at: string | null;
  completed_at: string | null;
  progress?: number;
}

interface CampaignDetail {
  campaign: Campaign & { tasks: Task[] };
  status_counts: Record<string, number>;
  country_counts: Record<string, Record<string, number>>;
  alerts: Alert[];
  progress: number;
}

interface Task {
  id: number;
  contact_type: string;
  country: string;
  language: string;
  status: string;
  attempt: number;
  contacts_found: number;
  contacts_imported: number;
  error_message: string | null;
  completed_at: string | null;
  updated_at: string | null;
}

interface Alert {
  id: number;
  details: { alert_type: string; message: string; country: string };
  created_at: string;
}

interface Config {
  country_presets: Record<string, string[]>;
  contact_types: { value: string; label: string; icon: string }[];
  languages: { value: string; label: string }[];
}

// ============================================================
// Status colors
// ============================================================
const STATUS_COLORS: Record<string, string> = {
  pending: 'bg-white/10 text-muted',
  queued: 'bg-violet/20 text-violet-light',
  running: 'bg-blue-500/20 text-blue-400',
  paused: 'bg-amber/20 text-amber',
  completed: 'bg-green-500/20 text-green-400',
  cancelled: 'bg-red-500/20 text-red-400',
  failed: 'bg-red-500/20 text-red-400',
  skipped: 'bg-white/10 text-muted',
};

const STATUS_LABELS: Record<string, string> = {
  pending: 'En attente',
  queued: 'En file d\'attente',
  running: 'En cours',
  paused: 'En pause',
  completed: 'Terminée',
  cancelled: 'Annulée',
  failed: 'Échouée',
};

const REGION_LABELS: Record<string, string> = {
  europe: 'Europe',
  afrique: 'Afrique',
  ameriques: 'Amériques',
  asie_oceanie: 'Asie & Océanie',
};

// ============================================================
// Country selector with all 197 countries grouped by continent
// ============================================================
const CONTINENT_LABELS: Record<string, string> = {
  Europe: 'Europe',
  Africa: 'Afrique',
  Americas: 'Amériques',
  Asia: 'Asie',
  Oceania: 'Océanie',
  'Middle East': 'Moyen-Orient',
};

function CountrySelector({ selected, onChange }: { selected: string[]; onChange: (v: string[]) => void }) {
  const [search, setSearch] = useState('');
  const [collapsed, setCollapsed] = useState<Set<string>>(new Set());

  // Group countries by region
  const grouped = useMemo(() => {
    const validCountries = countriesData.filter(c => c.code !== 'SEPARATOR' && !c.disabled);
    const groups: Record<string, { name: string; flag: string }[]> = {};
    for (const c of validCountries) {
      const region = c.region || 'Other';
      if (!groups[region]) groups[region] = [];
      groups[region].push({ name: c.nameFr, flag: c.flag });
    }
    // Sort countries within each group
    for (const region of Object.keys(groups)) {
      groups[region].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
    }
    return groups;
  }, []);

  const allCountryNames = useMemo(() =>
    countriesData.filter(c => c.code !== 'SEPARATOR' && !c.disabled).map(c => c.nameFr),
  []);

  const toggleContinent = (region: string) => {
    const countries = grouped[region]?.map(c => c.name) || [];
    const allSelected = countries.every(c => selected.includes(c));
    if (allSelected) {
      onChange(selected.filter(c => !countries.includes(c)));
    } else {
      onChange([...new Set([...selected, ...countries])]);
    }
  };

  const toggleCollapse = (region: string) => {
    setCollapsed(prev => {
      const next = new Set(prev);
      next.has(region) ? next.delete(region) : next.add(region);
      return next;
    });
  };

  const toggleCountry = (name: string) => {
    onChange(selected.includes(name) ? selected.filter(c => c !== name) : [...selected, name]);
  };

  const filteredGrouped = useMemo(() => {
    if (!search) return grouped;
    const s = search.toLowerCase();
    const result: Record<string, { name: string; flag: string }[]> = {};
    for (const [region, countries] of Object.entries(grouped)) {
      const filtered = countries.filter(c => c.name.toLowerCase().includes(s));
      if (filtered.length > 0) result[region] = filtered;
    }
    return result;
  }, [grouped, search]);

  return (
    <div>
      <label className="block text-sm text-muted mb-2">Pays ({selected.length} / {allCountryNames.length} sélectionnés)</label>

      {/* Quick actions */}
      <div className="flex flex-wrap gap-2 mb-3">
        <button
          onClick={() => onChange([...allCountryNames])}
          className={`px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors ${
            selected.length === allCountryNames.length
              ? 'bg-green-500/20 border-green-500 text-green-400'
              : 'bg-violet/20 border-violet text-violet-light hover:bg-violet/30'
          }`}
        >
          {selected.length === allCountryNames.length ? '✓ ' : ''}Tous les {allCountryNames.length} pays
        </button>
        {Object.entries(grouped).map(([region, countries]) => {
          const allSel = countries.every(c => selected.includes(c.name));
          return (
            <button
              key={region}
              onClick={() => toggleContinent(region)}
              className={`px-2.5 py-1 rounded-lg text-xs border transition-colors ${
                allSel
                  ? 'bg-green-500/20 border-green-500/50 text-green-400'
                  : 'bg-surface2 border-border text-muted hover:text-white'
              }`}
            >
              {allSel ? '✓ ' : ''}{CONTINENT_LABELS[region] || region} ({countries.length})
            </button>
          );
        })}
        {selected.length > 0 && (
          <button
            onClick={() => onChange([])}
            className="px-2.5 py-1 rounded-lg text-xs border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors"
          >
            Tout désélectionner
          </button>
        )}
      </div>

      {/* Search */}
      <input
        type="text"
        value={search}
        onChange={e => setSearch(e.target.value)}
        placeholder="Rechercher un pays..."
        className="w-full bg-surface2 border border-border rounded-lg px-3 py-1.5 text-sm text-white mb-3 focus:outline-none focus:border-violet"
      />

      {/* Countries by continent */}
      <div className="max-h-64 overflow-y-auto space-y-2 border border-border rounded-lg p-2 bg-bg">
        {Object.entries(filteredGrouped).map(([region, countries]) => {
          const allSel = countries.every(c => selected.includes(c.name));
          const isCollapsed = collapsed.has(region) && !search;
          return (
            <div key={region}>
              <div className="flex items-center gap-2 mb-1">
                <button onClick={() => toggleCollapse(region)} className="text-muted text-xs">{isCollapsed ? '▶' : '▼'}</button>
                <button
                  onClick={() => toggleContinent(region)}
                  className={`text-xs font-medium transition-colors ${allSel ? 'text-green-400' : 'text-violet-light hover:underline'}`}
                >
                  {allSel ? '✓ ' : ''}{CONTINENT_LABELS[region] || region} ({countries.length})
                </button>
              </div>
              {!isCollapsed && (
                <div className="flex flex-wrap gap-1 ml-4">
                  {countries.map(c => (
                    <button
                      key={c.name}
                      onClick={() => toggleCountry(c.name)}
                      className={`px-1.5 py-0.5 rounded text-[11px] border transition-colors ${
                        selected.includes(c.name)
                          ? 'bg-violet/20 border-violet/50 text-white'
                          : 'bg-surface2 border-border/50 text-muted hover:text-white'
                      }`}
                    >
                      {c.flag} {c.name}
                    </button>
                  ))}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

export default function AutoCampaignPage() {
  const [config, setConfig] = useState<Config | null>(null);
  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [selectedCampaign, setSelectedCampaign] = useState<CampaignDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [actionLoading, setActionLoading] = useState<string | null>(null);

  // Form state
  const [formName, setFormName] = useState('');
  const [formTypes, setFormTypes] = useState<string[]>([]);
  const [formCountries, setFormCountries] = useState<string[]>([]);
  const [formLanguages, setFormLanguages] = useState<string[]>(['fr']);
  const [formDelay, setFormDelay] = useState(300);
  const [formRetries, setFormRetries] = useState(3);
  const [showForm, setShowForm] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // ============================================================
  // Data loading
  // ============================================================
  const loadData = useCallback(async () => {
    try {
      const [configRes, campaignsRes] = await Promise.all([
        api.get('/auto-campaigns/config'),
        api.get('/auto-campaigns'),
      ]);
      setConfig(configRes.data);
      setCampaigns(campaignsRes.data.data || []);
    } catch (err) {
      console.error('Failed to load campaigns', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  // Auto-refresh running campaign every 10s
  const runningIdRef = React.useRef<number | null>(null);
  useEffect(() => {
    const running = campaigns.find(c => c.status === 'running');
    const newId = running?.id ?? null;

    if (newId === runningIdRef.current) return;
    runningIdRef.current = newId;

    if (!newId) return;
    const interval = setInterval(async () => {
      try {
        const [detailRes, listRes] = await Promise.all([
          api.get(`/auto-campaigns/${newId}`),
          api.get('/auto-campaigns'),
        ]);
        setSelectedCampaign(detailRes.data);
        setCampaigns(listRes.data.data || []);
      } catch { /* ignore polling errors */ }
    }, 10000);
    return () => clearInterval(interval);
  }, [campaigns]);

  // ============================================================
  // Actions
  // ============================================================
  const handleCreate = async () => {
    if (!formName || formTypes.length === 0 || formCountries.length === 0) return;
    setCreating(true);
    try {
      await api.post('/auto-campaigns', {
        name: formName,
        contact_types: formTypes,
        countries: formCountries,
        languages: formLanguages,
        delay_between_tasks_seconds: formDelay,
        max_retries: formRetries,
      });
      setShowForm(false);
      setFormName('');
      setFormTypes([]);
      setFormCountries([]);
      loadData();
    } catch (err: any) {
      setErrorMsg(err.response?.data?.message || 'Erreur lors de la création');
    } finally {
      setCreating(false);
    }
  };

  const handleAction = async (campaignId: number, action: string) => {
    setActionLoading(`${campaignId}-${action}`);
    try {
      await api.post(`/auto-campaigns/${campaignId}/${action}`);
      loadData();
      if (selectedCampaign?.campaign?.id === campaignId) {
        const res = await api.get(`/auto-campaigns/${campaignId}`);
        setSelectedCampaign(res.data);
      }
    } catch (err: any) {
      setErrorMsg(err.response?.data?.message || 'Erreur');
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = async (campaignId: number, name: string) => {
    if (!confirm(`Supprimer la campagne "${name}" et toutes ses tâches ?`)) return;
    setActionLoading(`${campaignId}-delete`);
    try {
      await api.delete(`/auto-campaigns/${campaignId}`);
      if (selectedCampaign?.campaign?.id === campaignId) setSelectedCampaign(null);
      loadData();
    } catch (err: any) {
      setErrorMsg(err.response?.data?.message || 'Erreur lors de la suppression');
    } finally {
      setActionLoading(null);
    }
  };

  const handleMoveQueue = async (campaignId: number, direction: 'up' | 'down') => {
    const queued = campaigns.filter(c => c.status === 'queued');
    const idx = queued.findIndex(c => c.id === campaignId);
    if (idx < 0) return;
    if (direction === 'up' && idx === 0) return;
    if (direction === 'down' && idx === queued.length - 1) return;
    const swapIdx = direction === 'up' ? idx - 1 : idx + 1;
    const newOrder = [...queued];
    [newOrder[idx], newOrder[swapIdx]] = [newOrder[swapIdx], newOrder[idx]];
    try {
      await api.post('/auto-campaigns/reorder', { order: newOrder.map(c => c.id) });
      loadData();
    } catch (err: any) {
      setErrorMsg(err.response?.data?.message || 'Erreur');
    }
  };

  const handleSelectCampaign = async (id: number) => {
    try {
      const res = await api.get(`/auto-campaigns/${id}`);
      setSelectedCampaign(res.data);
    } catch { /* ignore */ }
  };

  // ============================================================
  // Helpers
  // ============================================================
  const toggleArrayItem = (arr: string[], item: string, setter: (v: string[]) => void) => {
    setter(arr.includes(item) ? arr.filter(x => x !== item) : [...arr, item]);
  };

  const selectRegion = (region: string) => {
    if (!config) return;
    const regionCountries = config.country_presets[region] || [];
    const allSelected = regionCountries.every(c => formCountries.includes(c));
    if (allSelected) {
      setFormCountries(formCountries.filter(c => !regionCountries.includes(c)));
    } else {
      setFormCountries([...new Set([...formCountries, ...regionCountries])]);
    }
  };

  const taskCombos = formTypes.length * formCountries.length * formLanguages.length;
  const estimatedDuration = Math.ceil((taskCombos * formDelay) / 3600);
  const estimatedCost = (taskCombos * 0.012).toFixed(2); // ~$0.012 per task (Perplexity direct, no Claude)

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  // ============================================================
  // RENDER
  // ============================================================
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-title font-bold text-white">Campagnes automatiques</h1>
          <p className="text-muted text-sm mt-1">Recherche IA automatisée par pays, type et langue</p>
        </div>
        <button
          onClick={() => setShowForm(!showForm)}
          className="px-4 py-2 bg-violet hover:bg-violet/80 text-white rounded-lg font-medium transition-colors"
        >
          + Nouvelle campagne
        </button>
      </div>

      {/* Error banner */}
      {errorMsg && (
        <div className="bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 flex items-center justify-between">
          <p className="text-red-400 text-sm">{errorMsg}</p>
          <button onClick={() => setErrorMsg(null)} className="text-red-400 hover:text-red-300 text-sm ml-4">Fermer</button>
        </div>
      )}

      {/* Creation form */}
      {showForm && config && (
        <div className="bg-surface border border-border rounded-xl p-6 space-y-5">
          <h2 className="font-title font-semibold text-white text-lg">Configurer la campagne</h2>

          {/* Name */}
          <div>
            <label className="block text-sm text-muted mb-1">Nom de la campagne</label>
            <input
              type="text"
              value={formName}
              onChange={e => setFormName(e.target.value)}
              placeholder="ex: Sweep Europe Mars 2026"
              className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet"
            />
          </div>

          {/* Contact Types */}
          <div>
            <label className="block text-sm text-muted mb-2">Types de contacts</label>
            <div className="flex flex-wrap gap-2">
              {config.contact_types.map(t => (
                <button
                  key={t.value}
                  onClick={() => toggleArrayItem(formTypes, t.value, setFormTypes)}
                  className={`px-3 py-1.5 rounded-lg text-sm border transition-colors ${
                    formTypes.includes(t.value)
                      ? 'bg-violet/20 border-violet text-white'
                      : 'bg-surface2 border-border text-muted hover:text-white'
                  }`}
                >
                  {t.icon} {t.label}
                </button>
              ))}
            </div>
            <button
              onClick={() => setFormTypes(formTypes.length === config.contact_types.length ? [] : config.contact_types.map(t => t.value))}
              className="mt-1.5 text-xs text-violet hover:underline"
            >
              {formTypes.length === config.contact_types.length ? 'Tout désélectionner' : 'Tout sélectionner'}
            </button>
          </div>

          {/* Countries: ALL 197 countries grouped by continent */}
          <CountrySelector
            selected={formCountries}
            onChange={setFormCountries}
          />

          {/* Languages */}
          <div>
            <label className="block text-sm text-muted mb-2">Langues</label>
            <div className="flex flex-wrap gap-2">
              {config.languages.map(l => (
                <button
                  key={l.value}
                  onClick={() => toggleArrayItem(formLanguages, l.value, setFormLanguages)}
                  className={`px-3 py-1 rounded-lg text-sm border transition-colors ${
                    formLanguages.includes(l.value)
                      ? 'bg-violet/20 border-violet text-white'
                      : 'bg-surface2 border-border text-muted hover:text-white'
                  }`}
                >
                  {l.label}
                </button>
              ))}
            </div>
          </div>

          {/* Settings */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm text-muted mb-1">Délai entre tâches (secondes)</label>
              <input
                type="number"
                value={formDelay}
                onChange={e => setFormDelay(Math.max(60, parseInt(e.target.value) || 300))}
                min={60}
                max={3600}
                className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet"
              />
              <p className="text-xs text-muted mt-0.5">Min 60s. Recommandé: 300s (5 min)</p>
            </div>
            <div>
              <label className="block text-sm text-muted mb-1">Nombre de relances max</label>
              <input
                type="number"
                value={formRetries}
                onChange={e => setFormRetries(Math.max(1, Math.min(5, parseInt(e.target.value) || 3)))}
                min={1}
                max={5}
                className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet"
              />
            </div>
          </div>

          {/* Estimation */}
          {taskCombos > 0 && (
            <div className="bg-surface2 border border-border rounded-lg p-4 text-sm space-y-1">
              <p className="text-white font-medium">Estimation</p>
              <p className="text-muted">
                {taskCombos} combinaisons ({formTypes.length} types x {formCountries.length} pays x {formLanguages.length} langues)
              </p>
              <p className="text-muted">
                Durée estimée : ~{estimatedDuration}h ({Math.ceil(estimatedDuration / 24)} jours)
              </p>
              <p className="text-muted">
                Coût API estimé : ~${estimatedCost}
              </p>
            </div>
          )}

          {/* Submit */}
          <div className="space-y-2">
            {!formName && <p className="text-amber text-xs">Entrez un nom pour la campagne</p>}
            {formTypes.length === 0 && <p className="text-amber text-xs">Sélectionnez au moins un type de contact</p>}
            {formCountries.length === 0 && <p className="text-amber text-xs">Sélectionnez au moins un pays</p>}
            <div className="flex items-center gap-3">
              <button
                onClick={handleCreate}
                disabled={creating || !formName.trim() || formTypes.length === 0 || formCountries.length === 0}
                className="px-6 py-2.5 bg-violet hover:bg-violet/80 text-white rounded-lg font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {creating ? 'Lancement...' : campaigns.some(c => c.status === 'running')
                  ? `Programmer (${taskCombos} tâches — file d'attente)`
                  : `Lancer la campagne (${taskCombos} tâches)`}
              </button>
              <button
                onClick={() => setShowForm(false)}
                className="px-4 py-2 text-muted hover:text-white transition-colors"
              >
                Annuler
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Campaign list */}
      <div className="space-y-3">
        {campaigns.length === 0 && !showForm && (
          <p className="text-muted text-center py-8">Aucune campagne. Créez-en une pour commencer.</p>
        )}
        {campaigns.map(c => (
          <div
            key={c.id}
            onClick={() => handleSelectCampaign(c.id)}
            className={`bg-surface border rounded-xl p-4 cursor-pointer transition-colors hover:border-violet/50 ${
              selectedCampaign?.campaign?.id === c.id ? 'border-violet' : 'border-border'
            }`}
          >
            <div className="flex items-center justify-between flex-wrap gap-2">
              <div className="flex items-center gap-3">
                <h3 className="font-medium text-white">{c.name}</h3>
                <span className={`px-2 py-0.5 text-xs rounded-full font-mono ${STATUS_COLORS[c.status] || 'bg-white/10 text-muted'}`}>
                  {STATUS_LABELS[c.status] || c.status}
                </span>
                {c.status === 'running' && (
                  <span className="w-2 h-2 bg-blue-400 rounded-full animate-pulse" />
                )}
              </div>
              <div className="flex items-center gap-2">
                {c.status === 'running' && (
                  <button
                    onClick={e => { e.stopPropagation(); handleAction(c.id, 'pause'); }}
                    disabled={actionLoading === `${c.id}-pause`}
                    className="px-2.5 py-1 text-xs bg-amber/20 text-amber rounded hover:bg-amber/30 transition-colors"
                  >
                    Pause
                  </button>
                )}
                {c.status === 'paused' && (
                  <button
                    onClick={e => { e.stopPropagation(); handleAction(c.id, 'resume'); }}
                    disabled={actionLoading === `${c.id}-resume`}
                    className="px-2.5 py-1 text-xs bg-green-500/20 text-green-400 rounded hover:bg-green-500/30 transition-colors"
                  >
                    Reprendre
                  </button>
                )}
                {c.status === 'queued' && (
                  <div className="flex gap-0.5">
                    <button
                      onClick={e => { e.stopPropagation(); handleMoveQueue(c.id, 'up'); }}
                      className="px-1.5 py-1 text-xs text-muted hover:text-white hover:bg-surface2 rounded transition-colors"
                      title="Monter dans la file"
                    >
                      ▲
                    </button>
                    <button
                      onClick={e => { e.stopPropagation(); handleMoveQueue(c.id, 'down'); }}
                      className="px-1.5 py-1 text-xs text-muted hover:text-white hover:bg-surface2 rounded transition-colors"
                      title="Descendre dans la file"
                    >
                      ▼
                    </button>
                  </div>
                )}
                {['running', 'paused', 'queued'].includes(c.status) && (
                  <button
                    onClick={e => { e.stopPropagation(); handleAction(c.id, 'cancel'); }}
                    disabled={actionLoading === `${c.id}-cancel`}
                    className="px-2.5 py-1 text-xs bg-red-500/20 text-red-400 rounded hover:bg-red-500/30 transition-colors"
                  >
                    Annuler
                  </button>
                )}
                {c.tasks_failed > 0 && (
                  <button
                    onClick={e => { e.stopPropagation(); handleAction(c.id, 'retry-failed'); }}
                    disabled={actionLoading === `${c.id}-retry-failed`}
                    className="px-2.5 py-1 text-xs bg-violet/20 text-violet-light rounded hover:bg-violet/30 transition-colors"
                  >
                    Relancer {c.tasks_failed} échecs
                  </button>
                )}
                {!['running'].includes(c.status) && (
                  <button
                    onClick={e => { e.stopPropagation(); handleDelete(c.id, c.name); }}
                    disabled={actionLoading === `${c.id}-delete`}
                    className="px-2.5 py-1 text-xs text-red-400/60 hover:text-red-400 hover:bg-red-500/10 rounded transition-colors"
                    title="Supprimer cette campagne"
                  >
                    Supprimer
                  </button>
                )}
              </div>
            </div>

            {/* Progress bar */}
            <div className="mt-3">
              <div className="flex items-center justify-between text-xs text-muted mb-1">
                <span>{c.tasks_completed + c.tasks_failed + c.tasks_skipped} / {c.tasks_total} tâches</span>
                <span>{c.contacts_imported_total} contacts importés</span>
              </div>
              <div className="w-full bg-surface2 rounded-full h-2">
                <div
                  className={`h-2 rounded-full transition-all ${
                    c.status === 'running' ? 'bg-blue-500' :
                    c.status === 'completed' ? 'bg-green-500' :
                    c.status === 'paused' ? 'bg-amber' :
                    c.status === 'queued' ? 'bg-violet/50' : 'bg-white/20'
                  }`}
                  style={{ width: `${c.tasks_total > 0 ? Math.round(((c.tasks_completed + c.tasks_failed + c.tasks_skipped) / c.tasks_total) * 100) : 0}%` }}
                />
              </div>
            </div>

            {/* Stats row */}
            <div className="mt-2 flex flex-wrap gap-4 text-xs text-muted">
              <span className="text-green-400">{c.tasks_completed} OK</span>
              {c.tasks_failed > 0 && <span className="text-red-400">{c.tasks_failed} échecs</span>}
              {c.tasks_skipped > 0 && <span>{c.tasks_skipped} ignorés</span>}
              <span>{c.contacts_found_total} trouvés</span>
              <span>${(c.total_cost_cents / 100).toFixed(2)} coût API</span>
              {c.consecutive_failures > 0 && (
                <span className="text-red-400">{c.consecutive_failures} échecs consécutifs</span>
              )}
            </div>
          </div>
        ))}
      </div>

      {/* Campaign detail */}
      {selectedCampaign && (
        <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
          <h2 className="font-title font-semibold text-white">
            Détail : {selectedCampaign.campaign.name}
          </h2>

          {/* Speed control — adjustable while running */}
          {['running', 'paused'].includes(selectedCampaign.campaign.status) && (
            <div className="bg-surface2 border border-border rounded-lg p-4">
              <h3 className="text-sm font-medium text-white mb-3">Vitesse de la campagne</h3>
              <div className="flex items-center gap-4 flex-wrap">
                <div className="flex items-center gap-2">
                  <label className="text-xs text-muted">Délai entre tâches :</label>
                  <select
                    value={selectedCampaign.campaign.delay_between_tasks_seconds}
                    onChange={async (e) => {
                      const delay = parseInt(e.target.value);
                      try {
                        await api.patch(`/auto-campaigns/${selectedCampaign.campaign.id}/settings`, {
                          delay_between_tasks_seconds: delay,
                        });
                        // Refresh
                        const res = await api.get(`/auto-campaigns/${selectedCampaign.campaign.id}`);
                        setSelectedCampaign(res.data);
                        loadData();
                      } catch { /* ignore */ }
                    }}
                    className="bg-bg border border-border rounded px-2 py-1 text-sm text-white"
                  >
                    <option value={30}>30s (turbo)</option>
                    <option value={60}>1 min (rapide)</option>
                    <option value={120}>2 min</option>
                    <option value={180}>3 min</option>
                    <option value={300}>5 min (normal)</option>
                    <option value={600}>10 min (lent)</option>
                  </select>
                </div>
                <span className="text-xs text-muted">
                  Estimation restante : ~{Math.ceil(((selectedCampaign.campaign.tasks_total - selectedCampaign.campaign.tasks_completed - selectedCampaign.campaign.tasks_failed - selectedCampaign.campaign.tasks_skipped) * selectedCampaign.campaign.delay_between_tasks_seconds) / 3600)}h
                </span>
              </div>
            </div>
          )}

          {/* Alerts */}
          {selectedCampaign.alerts.length > 0 && (
            <div className="space-y-2">
              <h3 className="text-sm font-medium text-red-400">Alertes</h3>
              {selectedCampaign.alerts.map(a => (
                <div key={a.id} className="bg-red-500/10 border border-red-500/20 rounded-lg px-3 py-2 text-sm text-red-300">
                  <span className="text-xs text-red-400/70">{new Date(a.created_at).toLocaleString('fr-FR')}</span>
                  <p>{a.details.message}</p>
                </div>
              ))}
            </div>
          )}

          {/* Status + dernière tâche */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Status counts */}
            <div className="bg-surface2 border border-border rounded-lg p-3">
              <h3 className="text-xs font-medium text-muted uppercase mb-2">Répartition</h3>
              <div className="flex flex-wrap gap-2">
                {Object.entries(selectedCampaign.status_counts).map(([status, count]) => (
                  <div key={status} className={`px-2.5 py-1 rounded text-xs font-mono ${STATUS_COLORS[status]}`}>
                    {status}: {count as number}
                  </div>
                ))}
              </div>
            </div>

            {/* Dernière tâche + stats */}
            {(() => {
              const lastDone = [...selectedCampaign.campaign.tasks]
                .filter(t => t.status === 'completed' && t.updated_at)
                .sort((a, b) => new Date(b.updated_at!).getTime() - new Date(a.updated_at!).getTime())[0];
              const running = selectedCampaign.campaign.tasks.find(t => t.status === 'running');
              const current = running || lastDone;
              const done = selectedCampaign.campaign.tasks_completed;
              const avgContacts = done > 0 ? (selectedCampaign.campaign.contacts_imported_total / done).toFixed(1) : '—';
              return (
                <div className="bg-surface2 border border-border rounded-lg p-3 space-y-1">
                  <h3 className="text-xs font-medium text-muted uppercase mb-2">En cours</h3>
                  {current ? (
                    <p className="text-white text-sm font-medium">
                      {running && <span className="w-2 h-2 inline-block bg-blue-400 rounded-full animate-pulse mr-2" />}
                      {current.country} — {current.contact_type}
                      {current.status === 'completed' && current.contacts_imported > 0 && (
                        <span className="ml-2 text-green-400 text-xs">+{current.contacts_imported}</span>
                      )}
                    </p>
                  ) : (
                    <p className="text-muted text-sm">—</p>
                  )}
                  <p className="text-xs text-muted">Moy. {avgContacts} contacts/tâche</p>
                </div>
              );
            })()}
          </div>

          {/* Country grid */}
          {selectedCampaign.country_counts && Object.keys(selectedCampaign.country_counts).length > 0 && (
            <div>
              <h3 className="text-sm font-medium text-muted mb-2">Avancement par pays</h3>
              <div className="flex flex-wrap gap-1.5">
                {selectedCampaign.campaign.countries?.map(country => {
                  const counts = selectedCampaign.country_counts[country] || {};
                  const done = counts['completed'] || 0;
                  const failed = counts['failed'] || 0;
                  const pending = counts['pending'] || 0;
                  const total = done + failed + pending + (counts['running'] || 0) + (counts['skipped'] || 0);
                  const isRunning = (counts['running'] || 0) > 0;
                  const allDone = total > 0 && pending === 0 && !isRunning;
                  const hasFailures = failed > 0;
                  return (
                    <div
                      key={country}
                      title={`${country}: ${done} OK, ${failed} échecs, ${pending} en attente`}
                      className={`px-2 py-0.5 rounded text-[11px] border font-mono transition-colors ${
                        isRunning
                          ? 'bg-blue-500/20 border-blue-500/50 text-blue-300'
                          : allDone && !hasFailures
                          ? 'bg-green-500/20 border-green-500/30 text-green-400'
                          : allDone && hasFailures
                          ? 'bg-amber/20 border-amber/30 text-amber'
                          : hasFailures
                          ? 'bg-red-500/10 border-red-500/20 text-red-400'
                          : total === 0
                          ? 'bg-surface2 border-border/30 text-muted/40'
                          : 'bg-surface2 border-border/50 text-muted'
                      }`}
                    >
                      {country}
                      {done > 0 && <span className="ml-1 text-green-400">·{done}</span>}
                      {failed > 0 && <span className="ml-1 text-red-400">·{failed}✗</span>}
                    </div>
                  );
                })}
              </div>
              <div className="mt-2 flex gap-4 text-xs text-muted">
                <span><span className="text-blue-400">■</span> En cours</span>
                <span><span className="text-green-400">■</span> Terminé</span>
                <span><span className="text-amber">■</span> Terminé avec erreurs</span>
                <span><span className="text-muted/50">■</span> En attente</span>
              </div>
            </div>
          )}

          {/* Tasks table — only completed/failed rows, collapsible */}
          {(() => {
            const doneTasks = selectedCampaign.campaign.tasks.filter(t => t.status !== 'pending');
            if (doneTasks.length === 0) return null;
            return (
              <div className="overflow-x-auto">
                <h3 className="text-sm font-medium text-muted mb-2">Tâches traitées ({doneTasks.length})</h3>
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-muted text-left text-xs uppercase border-b border-border">
                      <th className="px-3 py-2">Pays</th>
                      <th className="px-3 py-2">Status</th>
                      <th className="px-3 py-2 text-right">Trouvés</th>
                      <th className="px-3 py-2 text-right">Importés</th>
                      <th className="px-3 py-2">Erreur</th>
                    </tr>
                  </thead>
                  <tbody>
                    {doneTasks.map(t => (
                      <tr key={t.id} className="border-b border-border/50 hover:bg-white/5">
                        <td className="px-3 py-2 text-white">{t.country}</td>
                        <td className="px-3 py-2">
                          <span className={`px-1.5 py-0.5 text-xs rounded ${STATUS_COLORS[t.status]}`}>{t.status}</span>
                        </td>
                        <td className="px-3 py-2 text-right text-white">{t.contacts_found}</td>
                        <td className="px-3 py-2 text-right text-green-400">{t.contacts_imported}</td>
                        <td className="px-3 py-2 text-red-400 text-xs max-w-xs truncate">{t.error_message || ''}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            );
          })()}
        </div>
      )}
    </div>
  );
}
