import React, { useEffect, useState, useCallback } from 'react';
import api from '../api/client';
import { COUNTRIES } from '../lib/constants';

// ============================================================
// Types
// ============================================================
interface Campaign {
  id: number;
  name: string;
  status: 'pending' | 'running' | 'paused' | 'completed' | 'cancelled';
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
  running: 'bg-blue-500/20 text-blue-400',
  paused: 'bg-amber/20 text-amber',
  completed: 'bg-green-500/20 text-green-400',
  cancelled: 'bg-red-500/20 text-red-400',
  failed: 'bg-red-500/20 text-red-400',
  skipped: 'bg-white/10 text-muted',
};

const REGION_LABELS: Record<string, string> = {
  europe: 'Europe',
  afrique: 'Afrique',
  ameriques: 'Amériques',
  asie_oceanie: 'Asie & Océanie',
};

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

  // Auto-refresh running campaign every 30s
  // Use ref to avoid re-creating interval on every campaigns state change
  const runningIdRef = React.useRef<number | null>(null);
  useEffect(() => {
    const running = campaigns.find(c => c.status === 'running');
    const newId = running?.id ?? null;

    // Only reset interval if the running campaign ID changed
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
    }, 30000);
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
  const estimatedCost = (taskCombos * 0.035).toFixed(2); // ~$0.035 per task (Perplexity + Claude)

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

          {/* Countries: presets by region + all countries */}
          <div>
            <label className="block text-sm text-muted mb-2">Pays ({formCountries.length} sélectionnés)</label>

            {/* Quick actions */}
            <div className="flex flex-wrap gap-2 mb-3">
              <button
                onClick={() => setFormCountries(COUNTRIES.map(c => c.name))}
                className="px-3 py-1 rounded-lg text-xs font-medium bg-violet/20 border border-violet text-violet-light hover:bg-violet/30 transition-colors"
              >
                Tous les pays ({COUNTRIES.length})
              </button>
              <button
                onClick={() => setFormCountries([])}
                className="px-3 py-1 rounded-lg text-xs font-medium bg-surface2 border border-border text-muted hover:text-white transition-colors"
              >
                Tout désélectionner
              </button>
            </div>

            {/* Region presets */}
            {Object.entries(config.country_presets).map(([region, countries]) => {
              const allSelected = countries.every((c: string) => formCountries.includes(c));
              return (
                <div key={region} className="mb-3">
                  <button
                    onClick={() => selectRegion(region)}
                    className={`text-sm font-medium mb-1 transition-colors ${allSelected ? 'text-green-400' : 'text-violet hover:underline'}`}
                  >
                    {allSelected ? '✓ ' : ''}{REGION_LABELS[region] || region} ({countries.length})
                  </button>
                  <div className="flex flex-wrap gap-1.5 ml-2">
                    {countries.map((c: string) => (
                      <button
                        key={c}
                        onClick={() => toggleArrayItem(formCountries, c, setFormCountries)}
                        className={`px-2 py-0.5 rounded text-xs border transition-colors ${
                          formCountries.includes(c)
                            ? 'bg-violet/20 border-violet text-white'
                            : 'bg-surface2 border-border text-muted hover:text-white'
                        }`}
                      >
                        {c}
                      </button>
                    ))}
                  </div>
                </div>
              );
            })}

            {/* Show count if many selected */}
            {formCountries.length > 0 && (
              <p className="text-xs text-muted mt-2">
                {formCountries.length} pays sélectionnés
                {formCountries.length > 10 && (
                  <button onClick={() => setFormCountries([])} className="text-red-400 hover:underline ml-2">Réinitialiser</button>
                )}
              </p>
            )}
          </div>

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
          <div className="flex items-center gap-3">
            <button
              onClick={handleCreate}
              disabled={creating || !formName || formTypes.length === 0 || formCountries.length === 0}
              className="px-6 py-2.5 bg-violet hover:bg-violet/80 text-white rounded-lg font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {creating ? 'Lancement...' : `Lancer la campagne (${taskCombos} tâches)`}
            </button>
            <button
              onClick={() => setShowForm(false)}
              className="px-4 py-2 text-muted hover:text-white transition-colors"
            >
              Annuler
            </button>
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
                <span className={`px-2 py-0.5 text-xs rounded-full font-mono ${STATUS_COLORS[c.status]}`}>
                  {c.status}
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
                {['running', 'paused'].includes(c.status) && (
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
                    c.status === 'paused' ? 'bg-amber' : 'bg-white/20'
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

          {/* Task status breakdown */}
          <div>
            <h3 className="text-sm font-medium text-muted mb-2">Status des tâches</h3>
            <div className="flex flex-wrap gap-3">
              {Object.entries(selectedCampaign.status_counts).map(([status, count]) => (
                <div key={status} className={`px-3 py-1.5 rounded-lg text-sm ${STATUS_COLORS[status]}`}>
                  {status}: {count as number}
                </div>
              ))}
            </div>
          </div>

          {/* Tasks table */}
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-muted text-left text-xs uppercase border-b border-border">
                  <th className="px-3 py-2">Type</th>
                  <th className="px-3 py-2">Pays</th>
                  <th className="px-3 py-2">Langue</th>
                  <th className="px-3 py-2">Status</th>
                  <th className="px-3 py-2 text-right">Tentative</th>
                  <th className="px-3 py-2 text-right">Trouvés</th>
                  <th className="px-3 py-2 text-right">Importés</th>
                  <th className="px-3 py-2">Erreur</th>
                </tr>
              </thead>
              <tbody>
                {selectedCampaign.campaign.tasks.map(t => (
                  <tr key={t.id} className="border-b border-border/50 hover:bg-white/5">
                    <td className="px-3 py-2 text-white">{t.contact_type}</td>
                    <td className="px-3 py-2 text-white">{t.country}</td>
                    <td className="px-3 py-2 text-muted">{t.language}</td>
                    <td className="px-3 py-2">
                      <span className={`px-1.5 py-0.5 text-xs rounded ${STATUS_COLORS[t.status]}`}>{t.status}</span>
                    </td>
                    <td className="px-3 py-2 text-right text-muted">{t.attempt}</td>
                    <td className="px-3 py-2 text-right text-white">{t.contacts_found}</td>
                    <td className="px-3 py-2 text-right text-green-400">{t.contacts_imported}</td>
                    <td className="px-3 py-2 text-red-400 text-xs max-w-xs truncate">{t.error_message || ''}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
