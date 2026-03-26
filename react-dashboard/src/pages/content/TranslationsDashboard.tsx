import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchTranslationOverview,
  fetchTranslationBatches,
  startTranslationBatch,
  pauseTranslationBatch,
  resumeTranslationBatch,
  cancelTranslationBatch,
} from '../../api/contentApi';
import type { TranslationOverview, TranslationBatch, TranslationBatchStatus, PaginatedResponse } from '../../types/content';

// ── Constants ───────────────────────────────────────────────
const BATCH_STATUS_COLORS: Record<TranslationBatchStatus, string> = {
  pending: 'bg-muted/20 text-muted',
  running: 'bg-blue-500/20 text-blue-400 animate-pulse',
  paused: 'bg-amber/20 text-amber',
  completed: 'bg-success/20 text-success',
  cancelled: 'bg-muted/20 text-muted',
  failed: 'bg-danger/20 text-danger',
};

const BATCH_STATUS_LABELS: Record<TranslationBatchStatus, string> = {
  pending: 'En attente',
  running: 'En cours',
  paused: 'Pause',
  completed: 'Termine',
  cancelled: 'Annule',
  failed: 'Echoue',
};

const LANGUAGE_LABELS: Record<string, string> = {
  fr: 'Francais (source)',
  en: 'Anglais',
  de: 'Allemand',
  es: 'Espagnol',
  pt: 'Portugais',
  ru: 'Russe',
  zh: 'Chinois',
  ar: 'Arabe',
  hi: 'Hindi',
};

const LANGUAGE_FLAGS: Record<string, string> = {
  fr: 'FR',
  en: 'EN',
  de: 'DE',
  es: 'ES',
  pt: 'PT',
  ru: 'RU',
  zh: 'ZH',
  ar: 'AR',
  hi: 'HI',
};

const CONTENT_TYPE_LABELS: Record<string, string> = {
  article: 'Articles',
  qa: 'Q&A',
  all: 'Tout',
};

function progressColor(pct: number): string {
  if (pct >= 100) return 'bg-success';
  if (pct >= 50) return 'bg-blue-500';
  if (pct > 0) return 'bg-amber';
  return 'bg-muted';
}

function cardBorderColor(pct: number, isRunning: boolean): string {
  if (isRunning) return 'border-blue-500/50';
  if (pct >= 100) return 'border-success/50';
  if (pct > 0) return 'border-amber/50';
  return 'border-border';
}

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

// ── Component ───────────────────────────────────────────────
export default function TranslationsDashboard() {
  const [overview, setOverview] = useState<TranslationOverview[]>([]);
  const [batches, setBatches] = useState<TranslationBatch[]>([]);
  const [overviewLoading, setOverviewLoading] = useState(true);
  const [batchesLoading, setBatchesLoading] = useState(true);
  const [batchesPagination, setBatchesPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  // Launch modal
  const [showLaunch, setShowLaunch] = useState(false);
  const [launchLang, setLaunchLang] = useState('en');
  const [launchType, setLaunchType] = useState<'article' | 'qa' | 'all'>('all');
  const [launching, setLaunching] = useState(false);

  // Filter
  const [filterStatus, setFilterStatus] = useState('');

  const loadOverview = useCallback(async () => {
    setOverviewLoading(true);
    try {
      const res = await fetchTranslationOverview();
      setOverview(res.data as unknown as TranslationOverview[]);
    } catch {
      // silently handled
    } finally {
      setOverviewLoading(false);
    }
  }, []);

  const loadBatches = useCallback(async (page = 1) => {
    setBatchesLoading(true);
    try {
      const params: Record<string, string | number> = { page };
      if (filterStatus) params.status = filterStatus;
      const res = await fetchTranslationBatches(params);
      const data = res.data as unknown as PaginatedResponse<TranslationBatch>;
      setBatches(data.data);
      setBatchesPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
    } catch {
      // silently handled
    } finally {
      setBatchesLoading(false);
    }
  }, [filterStatus]);

  useEffect(() => { loadOverview(); }, [loadOverview]);
  useEffect(() => { loadBatches(1); }, [loadBatches]);

  const handleLaunch = async () => {
    setLaunching(true);
    try {
      await startTranslationBatch({ target_language: launchLang, content_type: launchType });
      setShowLaunch(false);
      loadOverview();
      loadBatches(1);
    } catch {
      // silently handled
    } finally {
      setLaunching(false);
    }
  };

  const handlePause = async (id: number) => {
    setActionLoading(id);
    try {
      await pauseTranslationBatch(id);
      loadBatches(batchesPagination.current_page);
    } catch {
      // silently handled
    } finally {
      setActionLoading(null);
    }
  };

  const handleResume = async (id: number) => {
    setActionLoading(id);
    try {
      await resumeTranslationBatch(id);
      loadBatches(batchesPagination.current_page);
    } catch {
      // silently handled
    } finally {
      setActionLoading(null);
    }
  };

  const handleCancel = async (id: number) => {
    if (!window.confirm('Annuler ce batch de traduction ?')) return;
    setActionLoading(id);
    try {
      await cancelTranslationBatch(id);
      loadBatches(batchesPagination.current_page);
    } catch {
      // silently handled
    } finally {
      setActionLoading(null);
    }
  };

  const activeBatches = batches.filter(b => b.status === 'running' || b.status === 'paused' || b.status === 'pending');
  const historyBatches = batches.filter(b => b.status === 'completed' || b.status === 'cancelled' || b.status === 'failed');

  // Check if any running batch per language
  const runningLangs = new Set(batches.filter(b => b.status === 'running').map(b => b.target_language));

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <h2 className="font-title text-2xl font-bold text-white">Traductions</h2>
        <button
          onClick={() => setShowLaunch(true)}
          className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Lancer traduction
        </button>
      </div>

      {/* Launch modal */}
      {showLaunch && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
          <div className="bg-surface border border-border rounded-xl p-6 w-full max-w-md space-y-4">
            <h3 className="font-title font-semibold text-white text-lg">Lancer un batch de traduction</h3>
            <div className="space-y-3">
              <div>
                <label className="text-xs text-muted block mb-1">Langue cible</label>
                <select value={launchLang} onChange={e => setLaunchLang(e.target.value)} className={inputClass + ' w-full'}>
                  {Object.entries(LANGUAGE_LABELS).filter(([k]) => k !== 'fr').map(([k, v]) => (
                    <option key={k} value={k}>{v}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="text-xs text-muted block mb-1">Type de contenu</label>
                <select value={launchType} onChange={e => setLaunchType(e.target.value as 'article' | 'qa' | 'all')} className={inputClass + ' w-full'}>
                  <option value="all">Tout (articles + Q&A)</option>
                  <option value="article">Articles uniquement</option>
                  <option value="qa">Q&A uniquement</option>
                </select>
              </div>
            </div>
            <div className="flex justify-end gap-3">
              <button onClick={() => setShowLaunch(false)} className="px-4 py-2 text-sm text-muted hover:text-white transition-colors">Annuler</button>
              <button onClick={handleLaunch} disabled={launching} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50">
                {launching ? 'Lancement...' : 'Lancer'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Language overview cards */}
      <div>
        <h3 className="font-title font-semibold text-white mb-3">Couverture par langue</h3>
        {overviewLoading ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {[1, 2, 3, 4].map(i => (
              <div key={i} className="bg-surface border border-border rounded-xl p-5">
                <div className="animate-pulse bg-surface2 rounded-lg h-4 w-20 mb-2" />
                <div className="animate-pulse bg-surface2 rounded-lg h-8 w-16" />
              </div>
            ))}
          </div>
        ) : overview.length === 0 ? (
          <div className="bg-surface border border-border rounded-xl p-6 text-center text-muted text-sm">
            Aucune donnee de traduction disponible
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {overview.map(lang => {
              const isRunning = runningLangs.has(lang.language);
              return (
                <div key={lang.language} className={`bg-surface border rounded-xl p-5 ${cardBorderColor(lang.percent, isRunning)}`}>
                  <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-bold text-white bg-surface2 px-1.5 py-0.5 rounded uppercase">
                        {LANGUAGE_FLAGS[lang.language] || lang.language}
                      </span>
                      <span className="text-sm text-muted">{LANGUAGE_LABELS[lang.language] || lang.language}</span>
                    </div>
                    {isRunning && (
                      <span className="w-2 h-2 rounded-full bg-blue-400 animate-pulse" />
                    )}
                  </div>
                  <div className="flex items-baseline gap-2 mb-2">
                    <span className="text-2xl font-bold text-white">{lang.translated}</span>
                    <span className="text-sm text-muted">/ {lang.total_fr}</span>
                  </div>
                  <div className="h-2 bg-surface2 rounded-full overflow-hidden mb-2">
                    <div className={`h-full rounded-full transition-all ${progressColor(lang.percent)}`} style={{ width: `${Math.min(lang.percent, 100)}%` }} />
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-xs text-muted">{Math.round(lang.percent)}%</span>
                    {lang.language !== 'fr' && !isRunning && lang.percent < 100 && (
                      <button
                        onClick={() => { setLaunchLang(lang.language); setShowLaunch(true); }}
                        className="text-[10px] text-violet hover:text-violet-light transition-colors"
                      >
                        Lancer
                      </button>
                    )}
                    {isRunning && (
                      <span className="text-[10px] text-blue-400">En cours...</span>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Active batches */}
      {activeBatches.length > 0 && (
        <div>
          <h3 className="font-title font-semibold text-white mb-3">Batches actifs</h3>
          <div className="bg-surface border border-border rounded-xl p-5">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                    <th className="pb-3 pr-4">Langue</th>
                    <th className="pb-3 pr-4">Type</th>
                    <th className="pb-3 pr-4">Statut</th>
                    <th className="pb-3 pr-4">Progression</th>
                    <th className="pb-3 pr-4">Cout</th>
                    <th className="pb-3">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {activeBatches.map(batch => {
                    const pct = batch.total_items > 0 ? Math.round((batch.completed_items / batch.total_items) * 100) : 0;
                    return (
                      <tr key={batch.id} className="border-b border-border/50 hover:bg-surface2/50 transition-colors">
                        <td className="py-3 pr-4">
                          <span className="text-white font-medium uppercase">{LANGUAGE_FLAGS[batch.target_language] || batch.target_language}</span>
                          <span className="text-muted text-xs ml-2">{LANGUAGE_LABELS[batch.target_language] || batch.target_language}</span>
                        </td>
                        <td className="py-3 pr-4 text-muted">{CONTENT_TYPE_LABELS[batch.content_type] || batch.content_type}</td>
                        <td className="py-3 pr-4">
                          <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${BATCH_STATUS_COLORS[batch.status]}`}>
                            {BATCH_STATUS_LABELS[batch.status]}
                          </span>
                        </td>
                        <td className="py-3 pr-4">
                          <div className="flex items-center gap-3">
                            <div className="flex-1 h-2 bg-surface2 rounded-full overflow-hidden min-w-[100px]">
                              <div className={`h-full rounded-full transition-all ${progressColor(pct)}`} style={{ width: `${pct}%` }} />
                            </div>
                            <span className="text-xs text-muted whitespace-nowrap">
                              {batch.completed_items}/{batch.total_items}
                              {batch.failed_items > 0 && <span className="text-danger ml-1">({batch.failed_items} err)</span>}
                            </span>
                          </div>
                        </td>
                        <td className="py-3 pr-4 text-muted text-xs">${(batch.total_cost_cents / 100).toFixed(2)}</td>
                        <td className="py-3">
                          <div className="flex items-center gap-2">
                            {batch.status === 'running' && (
                              <button
                                onClick={() => handlePause(batch.id)}
                                disabled={actionLoading === batch.id}
                                className="text-xs text-amber hover:text-yellow-300 transition-colors disabled:opacity-50"
                              >
                                Pause
                              </button>
                            )}
                            {batch.status === 'paused' && (
                              <button
                                onClick={() => handleResume(batch.id)}
                                disabled={actionLoading === batch.id}
                                className="text-xs text-success hover:text-green-300 transition-colors disabled:opacity-50"
                              >
                                Reprendre
                              </button>
                            )}
                            <button
                              onClick={() => handleCancel(batch.id)}
                              disabled={actionLoading === batch.id}
                              className="text-xs text-danger hover:text-red-300 transition-colors disabled:opacity-50"
                            >
                              Annuler
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* History */}
      <div>
        <div className="flex items-center justify-between mb-3">
          <h3 className="font-title font-semibold text-white">Historique</h3>
          <select value={filterStatus} onChange={e => setFilterStatus(e.target.value)} className={inputClass}>
            <option value="">Tous les statuts</option>
            <option value="completed">Termine</option>
            <option value="cancelled">Annule</option>
            <option value="failed">Echoue</option>
          </select>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          {batchesLoading ? (
            <div className="space-y-3">
              {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-lg h-12" />)}
            </div>
          ) : historyBatches.length === 0 ? (
            <p className="text-center py-10 text-muted text-sm">Aucun batch termine</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                    <th className="pb-3 pr-4">Langue</th>
                    <th className="pb-3 pr-4">Type</th>
                    <th className="pb-3 pr-4">Statut</th>
                    <th className="pb-3 pr-4">Items</th>
                    <th className="pb-3 pr-4">Cout</th>
                    <th className="pb-3 pr-4">Termine le</th>
                  </tr>
                </thead>
                <tbody>
                  {historyBatches.map(batch => (
                    <tr key={batch.id} className="border-b border-border/50 hover:bg-surface2/50 transition-colors">
                      <td className="py-3 pr-4 text-white uppercase">{LANGUAGE_FLAGS[batch.target_language] || batch.target_language}</td>
                      <td className="py-3 pr-4 text-muted">{CONTENT_TYPE_LABELS[batch.content_type] || batch.content_type}</td>
                      <td className="py-3 pr-4">
                        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${BATCH_STATUS_COLORS[batch.status]}`}>
                          {BATCH_STATUS_LABELS[batch.status]}
                        </span>
                      </td>
                      <td className="py-3 pr-4 text-muted text-xs">
                        {batch.completed_items} ok
                        {batch.failed_items > 0 && <span className="text-danger ml-1">/ {batch.failed_items} err</span>}
                        {batch.skipped_items > 0 && <span className="text-amber ml-1">/ {batch.skipped_items} skip</span>}
                      </td>
                      <td className="py-3 pr-4 text-muted text-xs">${(batch.total_cost_cents / 100).toFixed(2)}</td>
                      <td className="py-3 pr-4 text-muted text-xs">
                        {batch.completed_at ? new Date(batch.completed_at).toLocaleDateString('fr-FR') : '-'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {batchesPagination.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 pt-4 border-t border-border">
              <span className="text-xs text-muted">{batchesPagination.total} batches</span>
              <div className="flex gap-2">
                <button onClick={() => loadBatches(batchesPagination.current_page - 1)} disabled={batchesPagination.current_page <= 1} className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40">
                  Precedent
                </button>
                <span className="px-3 py-1 text-xs text-muted">{batchesPagination.current_page} / {batchesPagination.last_page}</span>
                <button onClick={() => loadBatches(batchesPagination.current_page + 1)} disabled={batchesPagination.current_page >= batchesPagination.last_page} className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40">
                  Suivant
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
