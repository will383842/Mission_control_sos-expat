import React, { useEffect, useState } from 'react';
import api from '../api/client';

interface ScraperType {
  value: string;
  label: string;
  icon: string;
  scraper_enabled: boolean;
}

interface ScraperConfig {
  global_enabled: boolean;
  types: ScraperType[];
}

interface ScraperRun {
  id: number;
  scraper_name: string;
  status: 'ok' | 'skipped_no_ia' | 'rate_limited' | 'circuit_broken' | 'error' | 'running';
  country: string | null;
  contacts_found: number;
  contacts_new: number;
  started_at: string;
  ended_at: string | null;
  error_message: string | null;
  requires_perplexity: boolean;
}

interface Stat24h { status: string; count: number; contacts_new: number; }

interface ScraperStatus {
  latest_runs: ScraperRun[];
  stats_24h: Stat24h[];
  rotation_state: Array<{ scraper_name: string; last_country: string | null; last_ran_at: string | null; }>;
  circuit_breakers: Record<string, number>;
  generated_at: string;
}

interface SyncTable {
  key: string;
  label: string;
  total: number;
  synced: number;
}

interface SyncState {
  tables: SyncTable[];
  generated_at: string;
}

const SCRAPER_ACTIONS: Array<{ key: string; label: string; description: string; requiresAi?: boolean }> = [
  { key: 'lawyers',             label: '⚖️ Avocats',          description: 'Scrape legal500 + abogados + enrichement sites web' },
  { key: 'press',               label: '📰 Journalistes',     description: 'Scrape press publications + inference emails' },
  { key: 'instagram',           label: '📸 Instagrammeurs',    description: '1 pays via rotation (Perplexity)', requiresAi: true },
  { key: 'youtube',             label: '▶️ YouTubeurs',        description: '1 pays via rotation (Perplexity)', requiresAi: true },
  { key: 'businesses',          label: '🏢 Entreprises',       description: 'expat.com/entreprises (~4h)' },
  { key: 'femmexpat',           label: '👠 FemmExpat',         description: 'femmexpat.com contacts + partenaires' },
  { key: 'francaisaletranger',  label: '🇫🇷 Français à l\'étranger', description: 'francaisaletranger.fr auteurs + articles' },
  { key: 'discover-press',      label: '🔍 Découverte presse', description: 'Trouve nouveaux médias (Perplexity)', requiresAi: true },
  // Option D (2026-04-22) — scraping blogueurs zero-ban
  { key: 'bloggers-rss',        label: '📝 Bloggers RSS',     description: 'Scrape feeds RSS blogs (XML public, zéro ban)' },
  { key: 'bloggers-ai',         label: '📝 Blogueurs IA',     description: '1 pays via rotation (Perplexity)', requiresAi: true },
  { key: 'podcasters-ai',       label: '🎙️ Podcasters IA',   description: '1 pays via rotation (Perplexity)', requiresAi: true },
  { key: 'influencers-ai',      label: '👑 Influenceurs IA',  description: '1 pays via rotation (Perplexity)', requiresAi: true },
  { key: 'daily-report',        label: '📊 Rapport Telegram',  description: 'Envoie un rapport 24h maintenant' },
];

const STATUS_BADGE: Record<string, { label: string; cls: string }> = {
  ok:             { label: 'OK',          cls: 'bg-emerald-500/20 text-emerald-400' },
  running:        { label: 'En cours',    cls: 'bg-blue-500/20 text-blue-400' },
  skipped_no_ia:  { label: 'Skip (IA KO)',cls: 'bg-amber/20 text-amber' },
  rate_limited:   { label: 'Rate-limited',cls: 'bg-orange-500/20 text-orange-400' },
  circuit_broken: { label: 'Circuit coupé', cls: 'bg-rose-500/20 text-rose-400' },
  error:          { label: 'Erreur',      cls: 'bg-red-500/20 text-red-400' },
};

export default function AdminScraper() {
  const [config, setConfig] = useState<ScraperConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState('');
  const [status, setStatus] = useState<ScraperStatus | null>(null);
  const [runs, setRuns] = useState<ScraperRun[]>([]);
  const [syncState, setSyncState] = useState<SyncState | null>(null);
  const [actionInFlight, setActionInFlight] = useState<string | null>(null);
  const [actionToast, setActionToast] = useState<{ kind: 'ok' | 'err'; msg: string } | null>(null);

  const load = async () => {
    try {
      const { data } = await api.get('/settings/scraper');
      setConfig(data);
    } catch { /* ignore */ }
    finally { setLoading(false); }
  };

  const loadStatus = async () => {
    try {
      const [{ data: s }, { data: r }, { data: sync }] = await Promise.all([
        api.get<ScraperStatus>('/scrapers/status'),
        api.get<{ runs: ScraperRun[] }>('/scrapers/runs?limit=30'),
        api.get<SyncState>('/scrapers/sync-state'),
      ]);
      setStatus(s);
      setRuns(r.runs);
      setSyncState(sync);
    } catch { /* ignore — table may not exist yet */ }
  };

  const triggerResync = async (table: string) => {
    setActionInFlight(`resync-${table}`);
    setActionToast(null);
    try {
      const { data } = await api.post<{ queued: boolean; message: string }>(`/scrapers/resync/${table}`);
      setActionToast({ kind: 'ok', msg: data.message ?? 'Resync dispatché.' });
      loadStatus();
    } catch (e: unknown) {
      setActionToast({ kind: 'err', msg: (e as { response?: { data?: { error?: string } } }).response?.data?.error ?? 'Erreur resync.' });
    } finally {
      setActionInFlight(null);
    }
  };

  const triggerRun = async (scraper: string) => {
    setActionInFlight(`run-${scraper}`);
    setActionToast(null);
    try {
      const { data } = await api.post<{ dispatched: boolean; message: string }>(`/scrapers/run/${scraper}`);
      setActionToast({ kind: 'ok', msg: data.message ?? 'Scraper lancé.' });
      loadStatus();
    } catch (e: unknown) {
      setActionToast({ kind: 'err', msg: (e as { response?: { data?: { error?: string } } }).response?.data?.error ?? 'Erreur dispatch.' });
    } finally {
      setActionInFlight(null);
    }
  };

  useEffect(() => { load(); }, []);
  useEffect(() => {
    loadStatus();
    const id = setInterval(loadStatus, 30_000);
    return () => clearInterval(id);
  }, []);

  const toggleGlobal = async () => {
    if (!config) return;
    setSaving(true);
    setSuccess('');
    try {
      const { data } = await api.put('/settings/scraper', {
        global_enabled: !config.global_enabled,
      });
      setConfig(data);
      setSuccess(data.global_enabled ? 'Scraper activé' : 'Scraper désactivé');
    } catch { /* ignore */ }
    finally { setSaving(false); }
  };

  const toggleType = async (typeValue: string, currentState: boolean) => {
    if (!config) return;
    setSaving(true);
    setSuccess('');
    try {
      const { data } = await api.put('/settings/scraper', {
        types: { [typeValue]: !currentState },
      });
      setConfig(data);
    } catch { /* ignore */ }
    finally { setSaving(false); }
  };

  if (loading || !config) return (
    <div className="flex items-center justify-center h-32">
      <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  const enabledCount = config.types.filter(t => t.scraper_enabled).length;
  const disabledCount = config.types.length - enabledCount;

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h2 className="font-title text-2xl font-bold text-white">🕷️ Configuration Scraper</h2>
        <p className="text-muted text-sm mt-1">
          Le scraper visite les sites web des contacts pour extraire emails et téléphones automatiquement.
        </p>
      </div>

      {success && (
        <div className="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-3 text-emerald-400 text-sm">{success}</div>
      )}

      {/* Global toggle */}
      <div className={`bg-surface border rounded-xl p-5 ${config.global_enabled ? 'border-emerald-500/30' : 'border-border'}`}>
        <div className="flex items-center justify-between">
          <div>
            <h3 className="font-title font-semibold text-white text-lg">
              {config.global_enabled ? '🟢 Scraper ACTIF' : '🔴 Scraper DÉSACTIVÉ'}
            </h3>
            <p className="text-xs text-muted mt-1">
              {config.global_enabled
                ? `Le scraper analyse automatiquement les sites web des nouveaux contacts (${enabledCount} types activés)`
                : 'Le scraper est en pause — aucun site ne sera visité'
              }
            </p>
          </div>
          <button onClick={toggleGlobal} disabled={saving}
            style={{ width: 56, height: 28, borderRadius: 14, backgroundColor: config.global_enabled ? '#10b981' : '#4b5563', position: 'relative', border: 'none', cursor: 'pointer', transition: 'background-color 0.2s' }}>
            <span style={{ position: 'absolute', top: 2, left: config.global_enabled ? 30 : 2, width: 24, height: 24, borderRadius: 12, backgroundColor: 'white', transition: 'left 0.2s', boxShadow: '0 1px 3px rgba(0,0,0,0.3)' }} />
          </button>
        </div>
      </div>

      {/* Info box */}
      <div className="bg-amber/5 border border-amber/20 rounded-xl p-4 text-sm text-amber">
        <p className="font-semibold">Comment ça marche :</p>
        <ul className="mt-2 space-y-1 text-xs text-amber/80">
          <li>1. La recherche IA importe des contacts avec des URLs de sites web</li>
          <li>2. Le scraper visite chaque site et cherche les pages Contact, About, footer</li>
          <li>3. Il extrait les emails et téléphones trouvés</li>
          <li>4. Il met à jour automatiquement la fiche contact</li>
          <li className="text-amber font-medium mt-2">⚠️ Désactive le scraper pour YouTube, TikTok, Instagram — ces sites bloquent le scraping</li>
        </ul>
      </div>

      {/* ── Toast action ── */}
      {actionToast && (
        <div className={`rounded-xl p-3 text-sm border ${actionToast.kind === 'ok' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-rose-500/10 border-rose-500/20 text-rose-400'}`}>
          {actionToast.msg}
        </div>
      )}

      {/* ══════════════════════════════════════════════════════════ */}
      {/* SYNC BACKLINK ENGINE — 5 cards + bouton rattrapage par table */}
      {/* ══════════════════════════════════════════════════════════ */}
      {syncState && syncState.tables.length > 0 && (
        <div className="bg-surface border border-border rounded-xl overflow-hidden">
          <div className="p-4 border-b border-border flex items-center justify-between">
            <div>
              <h3 className="font-title font-semibold text-white">🔗 Sync Backlink Engine</h3>
              <p className="text-[10px] text-muted mt-0.5">
                Contacts envoyés automatiquement à <code>backlinks.life-expat.com</code> dès qu'ils sont scrapés
              </p>
            </div>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 p-4">
            {syncState.tables.map(t => {
              const pct = t.total === 0 ? 100 : Math.round((t.synced / t.total) * 100);
              const missing = t.total - t.synced;
              const color = pct >= 95 ? 'emerald' : pct >= 50 ? 'amber' : 'rose';
              return (
                <div key={t.key} className="bg-surface2 border border-border rounded-lg p-3">
                  <div className="text-sm font-medium text-white truncate">{t.label}</div>
                  <div className="text-2xl font-bold text-white mt-1">{t.synced.toLocaleString()} <span className="text-xs text-muted">/ {t.total.toLocaleString()}</span></div>
                  <div className="mt-2 w-full bg-white/5 rounded-full h-1.5">
                    <div className={`h-1.5 rounded-full bg-${color}-500`} style={{ width: `${pct}%` }} />
                  </div>
                  <div className="text-[11px] text-muted mt-1">{pct}%{missing > 0 ? ` • ${missing.toLocaleString()} manquants` : ''}</div>
                  {missing > 0 && (
                    <button
                      onClick={() => triggerResync(t.key)}
                      disabled={actionInFlight !== null}
                      className="mt-2 w-full text-[10px] px-2 py-1.5 rounded bg-violet hover:bg-violet/80 text-white disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {actionInFlight === `resync-${t.key}` ? 'En cours…' : `Rattraper ${missing.toLocaleString()}`}
                    </button>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* ══════════════════════════════════════════════════════════ */}
      {/* LANCER UN SCRAPER MAINTENANT — boutons action rapide      */}
      {/* ══════════════════════════════════════════════════════════ */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <div className="p-4 border-b border-border">
          <h3 className="font-title font-semibold text-white">🚀 Lancer un scraper maintenant</h3>
          <p className="text-[10px] text-muted mt-0.5">
            Dispatche un job en queue (arrière-plan). Pas besoin d'attendre le cron.
          </p>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 p-4">
          {SCRAPER_ACTIONS.map(a => (
            <button
              key={a.key}
              onClick={() => triggerRun(a.key)}
              disabled={actionInFlight !== null}
              className="text-left bg-surface2 hover:bg-white/5 border border-border hover:border-violet rounded-lg p-3 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <div className="text-sm font-medium text-white flex items-center justify-between">
                <span>{a.label}</span>
                {a.requiresAi && <span className="text-[9px] text-amber bg-amber/10 px-1.5 py-0.5 rounded">IA</span>}
              </div>
              <div className="text-[11px] text-muted mt-1">{a.description}</div>
              {actionInFlight === `run-${a.key}` && (
                <div className="text-[10px] text-violet mt-1">→ Dispatch en cours…</div>
              )}
            </button>
          ))}
        </div>
      </div>

{/* KPIs 24h + circuit breakers */}
      {status && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          {['ok', 'skipped_no_ia', 'rate_limited', 'error'].map(st => {
            const s = status.stats_24h.find(x => x.status === st);
            const meta = STATUS_BADGE[st] || { label: st, cls: '' };
            return (
              <div key={st} className="bg-surface border border-border rounded-xl p-3">
                <div className={`text-xs ${meta.cls} inline-block px-2 py-0.5 rounded-full`}>{meta.label}</div>
                <div className="text-2xl font-bold text-white mt-1">{s?.count ?? 0}</div>
                {st === 'ok' && s && (
                  <div className="text-[11px] text-muted">+{s.contacts_new} contacts</div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {status && Object.keys(status.circuit_breakers).length > 0 && (
        <div className="bg-rose-500/5 border border-rose-500/20 rounded-xl p-3 text-sm text-rose-300">
          <span className="font-semibold">🛑 Circuit coupé :</span>{' '}
          {Object.keys(status.circuit_breakers).slice(0, 8).join(', ')}
        </div>
      )}

      {/* Historique runs */}
      {runs.length > 0 && (
        <div className="bg-surface border border-border rounded-xl overflow-hidden">
          <div className="p-4 border-b border-border">
            <h3 className="font-title font-semibold text-white">Historique des runs</h3>
            <p className="text-[10px] text-muted mt-0.5">
              Rafraîchi toutes les 30s · {runs.length} derniers runs
            </p>
          </div>
          <div className="overflow-x-auto max-h-96">
            <table className="w-full text-xs">
              <thead className="bg-surface2 sticky top-0">
                <tr className="text-left text-muted">
                  <th className="p-2 font-medium">Date</th>
                  <th className="p-2 font-medium">Scraper</th>
                  <th className="p-2 font-medium">Pays</th>
                  <th className="p-2 font-medium">Statut</th>
                  <th className="p-2 font-medium text-right">Nouveaux</th>
                  <th className="p-2 font-medium">Détails</th>
                </tr>
              </thead>
              <tbody>
                {runs.map(r => {
                  const badge = STATUS_BADGE[r.status] || { label: r.status, cls: 'bg-gray-600/20 text-gray-300' };
                  return (
                    <tr key={r.id} className="border-t border-border/40 hover:bg-white/5">
                      <td className="p-2 text-muted whitespace-nowrap">
                        {new Date(r.started_at).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
                      </td>
                      <td className="p-2 text-white font-mono">{r.scraper_name}</td>
                      <td className="p-2 text-muted">{r.country ?? '—'}</td>
                      <td className="p-2">
                        <span className={`text-[10px] px-2 py-0.5 rounded-full ${badge.cls}`}>{badge.label}</span>
                      </td>
                      <td className="p-2 text-right text-white">{r.contacts_new}</td>
                      <td className="p-2 text-muted text-[11px] truncate max-w-xs" title={r.error_message || ''}>
                        {r.error_message ?? '—'}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Per-type toggles */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <div className="p-4 border-b border-border">
          <h3 className="font-title font-semibold text-white">Scraper par type de contact</h3>
          <p className="text-[10px] text-muted mt-0.5">
            {enabledCount} activés • {disabledCount} désactivés
          </p>
        </div>
        <table className="w-full text-sm">
          <tbody>
            {config.types.map(type => (
              <tr key={type.value} className="border-b border-border/50">
                <td className="p-3">
                  <span className="text-lg mr-2">{type.icon}</span>
                  <span className="text-white font-medium">{type.label}</span>
                  <span className="text-xs text-muted ml-2 font-mono">({type.value})</span>
                </td>
                <td className="p-3 text-right">
                  <button
                    onClick={() => toggleType(type.value, type.scraper_enabled)}
                    disabled={saving}
                    style={{ width: 44, height: 24, borderRadius: 12, backgroundColor: type.scraper_enabled ? '#10b981' : '#4b5563', position: 'relative', border: 'none', cursor: 'pointer', transition: 'background-color 0.2s' }}>
                    <span style={{ position: 'absolute', top: 2, left: type.scraper_enabled ? 22 : 2, width: 20, height: 20, borderRadius: 10, backgroundColor: 'white', transition: 'left 0.2s', boxShadow: '0 1px 2px rgba(0,0,0,0.3)' }} />
                  </button>
                </td>
                <td className="p-3 w-24">
                  {type.scraper_enabled ? (
                    <span className="text-[10px] bg-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded-full">Actif</span>
                  ) : (
                    <span className="text-[10px] bg-gray-600/20 text-gray-500 px-2 py-0.5 rounded-full">Off</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
