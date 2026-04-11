import React, { useEffect, useState, useCallback, useRef } from 'react';
import {
  fetchQrBlogStats, fetchQrBlogProgress,
  launchQrBlogGeneration, resetQrBlogWriting,
  fetchQrSources, addQrSource, updateQrSource, deleteQrSource,
  fetchQrSchedule, saveQrSchedule,
  fetchQrGenerated,
} from '../../api/contentApi';
import type { QrBlogStats, QrBlogProgress, QrSource, QrSchedule } from '../../api/contentApi';
import { toast } from '../../components/Toast';
import { inputClass, errMsg } from './helpers';

// ─────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────
const STATUS_BADGE: Record<string, string> = {
  opportunity: 'bg-blue-500/20 text-blue-400',
  writing:     'bg-amber/20 text-amber animate-pulse',
  published:   'bg-success/20 text-success',
  skipped:     'bg-muted/20 text-muted',
  covered:     'bg-gray-500/20 text-gray-400',
};
const STATUS_LABEL: Record<string, string> = {
  opportunity: 'Disponible',
  writing:     'En cours…',
  published:   'Publiée',
  skipped:     'Ignorée',
  covered:     'Traitée',
};

const CATEGORIES = [
  { value: '', label: 'Toutes catégories' },
  { value: 'visa', label: 'Visa & Séjour' },
  { value: 'logement', label: 'Logement' },
  { value: 'sante', label: 'Santé' },
  { value: 'fiscalite', label: 'Fiscalité' },
  { value: 'administratif', label: 'Démarches admin.' },
  { value: 'urgence', label: 'Urgence' },
  { value: 'quotidien', label: 'Vie quotidienne' },
  { value: 'travail', label: 'Travail' },
  { value: 'etudes', label: 'Études' },
  { value: 'retraite', label: 'Retraite' },
];

const TABS = [
  { id: 'sources',    label: 'Sources',           icon: '📋' },
  { id: 'generation', label: 'Génération',         icon: '⚡' },
  { id: 'generated',  label: 'Contenus générés',   icon: '✅' },
] as const;
type TabId = typeof TABS[number]['id'];

// ─────────────────────────────────────────────────────────────
// Main component
// ─────────────────────────────────────────────────────────────
export default function GenerateQr() {
  const [tab, setTab]           = useState<TabId>('sources');
  const [stats, setStats]       = useState<QrBlogStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);
  const [progress, setProgress] = useState<QrBlogProgress | null>(null);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // ── Stats ──────────────────────────────────────────────────
  const loadStats = useCallback(async () => {
    try {
      const res = await fetchQrBlogStats();
      const data = res.data as unknown as QrBlogStats;
      setStats(data);
      if (data.progress) setProgress(data.progress as unknown as QrBlogProgress);
    } catch { /* silent */ }
  }, []);

  const stopPolling = useCallback(() => {
    if (pollRef.current) { clearInterval(pollRef.current); pollRef.current = null; }
  }, []);

  const loadProgress = useCallback(async () => {
    try {
      const res = await fetchQrBlogProgress();
      const data = res.data as unknown as QrBlogProgress;
      setProgress(data);
      if (data.status === 'completed' || data.status === 'failed') {
        stopPolling();
        loadStats();
      }
    } catch { /* silent */ }
  }, [stopPolling, loadStats]);

  const startPolling = useCallback(() => {
    stopPolling();
    pollRef.current = setInterval(loadProgress, 3000);
  }, [stopPolling, loadProgress]);

  useEffect(() => { loadStats().finally(() => setStatsLoading(false)); return () => stopPolling(); }, []);
  useEffect(() => { if (progress?.status === 'running') startPolling(); }, []);

  const isRunning = progress?.status === 'running';

  if (statsLoading) return (
    <div className="p-6 space-y-4">
      {[1,2,3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-20" />)}
    </div>
  );

  return (
    <div className="p-4 md:p-6 space-y-5">

      {/* ── Header + stats rapides ── */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="font-title text-2xl font-bold text-white flex items-center gap-2">
            ❓ Générateur Q/R
          </h2>
          <p className="text-muted text-sm mt-0.5">
            Pages Questions/Réponses SEO générées par Claude · 9 langues automatiques
          </p>
        </div>
        {stats && (
          <div className="flex gap-3 shrink-0">
            <StatPill n={stats.available}  label="Disponibles" color="text-blue-400" />
            <StatPill n={stats.writing}    label="En cours"    color="text-amber" />
            <StatPill n={stats.published}  label="Publiées"    color="text-success" />
          </div>
        )}
      </div>

      {/* ── Bandeau progression (toujours visible si génération en cours) ── */}
      {progress && progress.status !== 'idle' && (
        <ProgressBanner progress={progress} />
      )}

      {/* ── Onglets ── */}
      <div className="flex gap-1 border-b border-border">
        {TABS.map(t => (
          <button key={t.id} onClick={() => setTab(t.id)}
            className={`px-4 py-2.5 text-sm font-medium rounded-t transition-colors ${
              tab === t.id
                ? 'bg-surface border border-b-surface border-border text-white -mb-px'
                : 'text-muted hover:text-white'
            }`}>
            {t.icon} {t.label}
          </button>
        ))}
      </div>

      {/* ── Contenu de l'onglet ── */}
      {tab === 'sources'    && <TabSources    stats={stats} reloadStats={loadStats} />}
      {tab === 'generation' && <TabGeneration stats={stats} progress={progress} isRunning={isRunning}
                                              reloadStats={loadStats} onProgressStart={startPolling}
                                              setProgress={setProgress} />}
      {tab === 'generated'  && <TabGenerated />}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// StatPill
// ─────────────────────────────────────────────────────────────
function StatPill({ n, label, color }: { n: number; label: string; color: string }) {
  return (
    <div className="bg-surface border border-border rounded-xl px-4 py-2 text-center">
      <div className={`text-xl font-bold ${color}`}>{n.toLocaleString('fr-FR')}</div>
      <div className="text-[10px] text-muted mt-0.5">{label}</div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// ProgressBanner
// ─────────────────────────────────────────────────────────────
function ProgressBanner({ progress }: { progress: QrBlogProgress }) {
  const isRunning = progress.status === 'running';
  const isDone    = progress.status === 'completed';
  const isFailed  = progress.status === 'failed';
  const pct = progress.total > 0
    ? Math.round(((progress.completed + progress.skipped + progress.errors) / progress.total) * 100)
    : 0;

  return (
    <div className={`border rounded-xl p-4 ${
      isRunning ? 'bg-surface border-blue-500/30' :
      isDone    ? 'bg-success/5 border-success/30' :
                  'bg-danger/5 border-danger/30'
    }`}>
      <div className="flex items-center justify-between mb-2">
        <span className="text-sm font-semibold text-white flex items-center gap-2">
          {isRunning && <span className="w-2 h-2 bg-blue-400 rounded-full animate-pulse" />}
          {isDone    && <span className="text-success">✓</span>}
          {isFailed  && <span className="text-danger">✗</span>}
          {isRunning ? 'Génération en cours…' : isDone ? 'Génération terminée' : 'Génération échouée'}
          {progress.triggered_by === 'scheduler' && (
            <span className="text-[10px] bg-violet/20 text-violet px-2 py-0.5 rounded">auto</span>
          )}
        </span>
        <span className="text-xs text-muted">{progress.completed + progress.skipped + progress.errors} / {progress.total}</span>
      </div>
      <div className="w-full h-1.5 bg-surface2 rounded-full overflow-hidden mb-2">
        <div className={`h-full rounded-full transition-all duration-500 ${
          isDone ? 'bg-success' : isFailed ? 'bg-danger' : 'bg-blue-500'
        }`} style={{ width: `${pct}%` }} />
      </div>
      <div className="flex gap-4 text-xs">
        <span className="text-success">✓ {progress.completed} publiées</span>
        <span className="text-muted">– {progress.skipped} ignorées</span>
        {progress.errors > 0 && <span className="text-danger">✗ {progress.errors} erreurs</span>}
        {progress.current_title && <span className="text-blue-400 truncate flex-1">⟳ {progress.current_title}</span>}
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// Tab Sources
// ─────────────────────────────────────────────────────────────
function TabSources({ stats, reloadStats }: { stats: QrBlogStats | null; reloadStats: () => void }) {
  const [sources, setSources]   = useState<QrSource[]>([]);
  const [loading, setLoading]   = useState(false);
  const [search, setSearch]     = useState('');
  const [status, setStatus]     = useState('opportunity');
  const [country, setCountry]   = useState('');
  const [page, setPage]         = useState(1);
  const [total, setTotal]       = useState(0);

  // Édition inline
  const [editId, setEditId]     = useState<number | null>(null);
  const [editTitle, setEditTitle] = useState('');
  const [savingId, setSavingId] = useState<number | null>(null);

  // Ajout manuel
  const [showAdd, setShowAdd]   = useState(false);
  const [addTitle, setAddTitle] = useState('');
  const [addCountry, setAddCountry] = useState('');
  const [addLang, setAddLang]   = useState('fr');
  const [addNotes, setAddNotes] = useState('');
  const [adding, setAdding]     = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = { per_page: 20, page, sort: 'views', direction: 'desc' };
      if (status)  params.status  = status;
      if (search)  params.search  = search;
      if (country) params.country = country;
      const res  = await fetchQrSources(params);
      const data = res.data as { data: QrSource[]; total: number };
      setSources(data.data ?? []);
      setTotal(data.total ?? 0);
    } catch { toast('error', 'Erreur chargement sources'); }
    finally { setLoading(false); }
  }, [page, status, search, country]);

  useEffect(() => { load(); }, [load]);

  const saveEdit = async (id: number) => {
    if (!editTitle.trim()) return;
    setSavingId(id);
    try {
      await updateQrSource(id, { title: editTitle.trim() });
      setSources(ss => ss.map(s => s.id === id ? { ...s, title: editTitle.trim() } : s));
      toast('success', 'Titre mis à jour');
      setEditId(null);
    } catch (e) { toast('error', errMsg(e)); }
    finally { setSavingId(null); }
  };

  const doUpdateStatus = async (id: number, st: string) => {
    try {
      await updateQrSource(id, { article_status: st });
      setSources(ss => ss.map(s => s.id === id ? { ...s, article_status: st } : s));
      reloadStats();
    } catch (e) { toast('error', errMsg(e)); }
  };

  const doDelete = async (id: number) => {
    if (!confirm('Supprimer cette question ?')) return;
    try {
      await deleteQrSource(id);
      setSources(ss => ss.filter(s => s.id !== id));
      setTotal(t => t - 1);
      reloadStats();
      toast('success', 'Question supprimée.');
    } catch (e) { toast('error', errMsg(e)); }
  };

  const doAdd = async () => {
    if (!addTitle.trim()) { toast('error', 'Titre requis'); return; }
    setAdding(true);
    try {
      const res = await addQrSource({ title: addTitle.trim(), country: addCountry || undefined, language: addLang, notes: addNotes || undefined });
      setSources(ss => [res.data as unknown as QrSource, ...ss]);
      setTotal(t => t + 1);
      setAddTitle(''); setAddCountry(''); setAddNotes(''); setShowAdd(false);
      reloadStats();
      toast('success', 'Question ajoutée.');
    } catch (e) { toast('error', errMsg(e)); }
    finally { setAdding(false); }
  };

  return (
    <div className="bg-surface border border-border rounded-xl overflow-hidden">

      {/* Toolbar */}
      <div className="p-4 border-b border-border flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <div className="flex items-center gap-2 flex-wrap">
          <span className="text-sm font-semibold text-white">
            Sources
            <span className="ml-1.5 text-muted font-normal text-xs">({total.toLocaleString('fr-FR')})</span>
          </span>
          <select value={status} onChange={e => { setStatus(e.target.value); setPage(1); }} className={inputClass + ' text-xs'}>
            <option value="">Tous statuts</option>
            <option value="opportunity">Disponibles</option>
            <option value="writing">En cours</option>
            <option value="published">Publiées</option>
            <option value="skipped">Ignorées</option>
            <option value="covered">Traitées</option>
          </select>
          <input value={search} placeholder="Rechercher…"
            onChange={e => { setSearch(e.target.value); setPage(1); }}
            className={inputClass + ' w-44 text-xs'} />
          <input value={country} placeholder="Pays…"
            onChange={e => { setCountry(e.target.value); setPage(1); }}
            className={inputClass + ' w-32 text-xs'} />
        </div>
        <button onClick={() => setShowAdd(v => !v)}
          className="px-3 py-1.5 text-xs bg-blue-600 hover:bg-blue-500 text-white rounded font-medium transition shrink-0">
          + Ajouter manuellement
        </button>
      </div>

      {/* Formulaire d'ajout manuel */}
      {showAdd && (
        <div className="p-4 bg-blue-500/5 border-b border-blue-500/20 grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div className="sm:col-span-3">
            <label className="block text-xs text-muted mb-1">Question / Titre <span className="text-danger">*</span></label>
            <input value={addTitle} onChange={e => setAddTitle(e.target.value)}
              placeholder="Ex: Comment ouvrir un compte bancaire en Allemagne ?"
              className={inputClass + ' w-full'} />
          </div>
          <div>
            <label className="block text-xs text-muted mb-1">Pays</label>
            <input value={addCountry} onChange={e => setAddCountry(e.target.value)}
              placeholder="Ex: Allemagne" className={inputClass + ' w-full'} />
          </div>
          <div>
            <label className="block text-xs text-muted mb-1">Langue source</label>
            <select value={addLang} onChange={e => setAddLang(e.target.value)} className={inputClass + ' w-full'}>
              <option value="fr">Français</option>
              <option value="en">English</option>
              <option value="es">Español</option>
              <option value="de">Deutsch</option>
            </select>
          </div>
          <div>
            <label className="block text-xs text-muted mb-1">Notes (optionnel)</label>
            <input value={addNotes} onChange={e => setAddNotes(e.target.value)}
              placeholder="Notes internes…" className={inputClass + ' w-full'} />
          </div>
          <div className="sm:col-span-3 flex gap-2">
            <button onClick={doAdd} disabled={adding}
              className="px-4 py-2 text-xs bg-success/20 text-success rounded hover:bg-success/30 font-medium transition disabled:opacity-50">
              {adding ? 'Ajout…' : '✓ Ajouter'}
            </button>
            <button onClick={() => setShowAdd(false)}
              className="px-4 py-2 text-xs bg-surface2 text-muted rounded hover:text-white transition">
              Annuler
            </button>
          </div>
        </div>
      )}

      {/* Table */}
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border text-left bg-surface2/30">
              <th className="px-4 py-2.5 text-xs text-muted font-medium">Question</th>
              <th className="px-4 py-2.5 text-xs text-muted font-medium w-28">Pays</th>
              <th className="px-4 py-2.5 text-xs text-muted font-medium w-20 text-right">Vues</th>
              <th className="px-4 py-2.5 text-xs text-muted font-medium w-28">Statut</th>
              <th className="px-4 py-2.5 text-xs text-muted font-medium w-28">Actions</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              [...Array(8)].map((_, i) => (
                <tr key={i} className="border-b border-border/40">
                  <td colSpan={5} className="px-4 py-2.5">
                    <div className="animate-pulse bg-surface2 h-3.5 rounded w-full" />
                  </td>
                </tr>
              ))
            ) : sources.length === 0 ? (
              <tr><td colSpan={5} className="px-4 py-10 text-center text-muted text-sm">Aucune question trouvée</td></tr>
            ) : sources.map(s => (
              <tr key={s.id} className="border-b border-border/40 hover:bg-surface2/40 transition-colors">
                <td className="px-4 py-2.5">
                  {editId === s.id ? (
                    <div className="flex items-center gap-2">
                      <input value={editTitle} onChange={e => setEditTitle(e.target.value)}
                        onKeyDown={e => { if (e.key === 'Enter') saveEdit(s.id); if (e.key === 'Escape') setEditId(null); }}
                        className={inputClass + ' flex-1 text-xs'} autoFocus />
                      <button onClick={() => saveEdit(s.id)} disabled={savingId === s.id}
                        className="text-xs px-2 py-1 bg-success/20 text-success rounded">
                        {savingId === s.id ? '…' : '✓'}
                      </button>
                      <button onClick={() => setEditId(null)}
                        className="text-xs px-2 py-1 bg-surface2 text-muted rounded">✗</button>
                    </div>
                  ) : (
                    <div>
                      <span className="text-white text-xs leading-snug line-clamp-2">{s.title}</span>
                      {s.article_notes && (
                        <span className="text-[10px] text-muted italic">{s.article_notes}</span>
                      )}
                      {!s.url && (
                        <span className="ml-1.5 text-[10px] bg-violet/20 text-violet px-1.5 py-0.5 rounded">manuel</span>
                      )}
                    </div>
                  )}
                </td>
                <td className="px-4 py-2.5">
                  <span className="text-xs text-muted">{s.country ?? '—'}</span>
                </td>
                <td className="px-4 py-2.5 text-right">
                  <span className="text-xs text-muted">{(s.views || 0).toLocaleString('fr-FR')}</span>
                </td>
                <td className="px-4 py-2.5">
                  <span className={`inline-block px-2 py-0.5 rounded text-[10px] font-medium ${STATUS_BADGE[s.article_status] ?? 'bg-muted/20 text-muted'}`}>
                    {STATUS_LABEL[s.article_status] ?? s.article_status}
                  </span>
                </td>
                <td className="px-4 py-2.5">
                  <div className="flex items-center gap-1">
                    {s.article_status === 'opportunity' && editId !== s.id && (
                      <ActionBtn title="Modifier le titre" onClick={() => { setEditId(s.id); setEditTitle(s.title); }}>✏</ActionBtn>
                    )}
                    {s.article_status === 'opportunity' && (
                      <ActionBtn title="Ignorer" onClick={() => doUpdateStatus(s.id, 'skipped')} hover="hover:text-amber">—</ActionBtn>
                    )}
                    {(s.article_status === 'skipped' || s.article_status === 'covered') && (
                      <ActionBtn title="Remettre en file" onClick={() => doUpdateStatus(s.id, 'opportunity')} hover="hover:text-blue-400">↺</ActionBtn>
                    )}
                    {!s.url && (
                      <ActionBtn title="Supprimer" onClick={() => doDelete(s.id)} hover="hover:text-danger">🗑</ActionBtn>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {total > 20 && (
        <div className="p-3 border-t border-border flex items-center justify-between">
          <span className="text-xs text-muted">Page {page} · {total.toLocaleString('fr-FR')} questions</span>
          <div className="flex gap-2">
            <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1}
              className="px-3 py-1.5 text-xs bg-surface2 text-muted rounded hover:text-white disabled:opacity-40">← Préc.</button>
            <button onClick={() => setPage(p => p + 1)} disabled={page * 20 >= total}
              className="px-3 py-1.5 text-xs bg-surface2 text-muted rounded hover:text-white disabled:opacity-40">Suiv. →</button>
          </div>
        </div>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// Tab Génération
// ─────────────────────────────────────────────────────────────
function TabGeneration({
  stats, progress, isRunning, reloadStats, onProgressStart, setProgress,
}: {
  stats: QrBlogStats | null;
  progress: QrBlogProgress | null;
  isRunning: boolean;
  reloadStats: () => void;
  onProgressStart: () => void;
  setProgress: React.Dispatch<React.SetStateAction<QrBlogProgress | null>>;
}) {
  // Génération manuelle
  const [limit, setLimit]       = useState(20);
  const [country, setCountry]   = useState('');
  const [category, setCategory] = useState('');
  const [launching, setLaunching] = useState(false);
  const [resetting, setResetting] = useState(false);

  // Programmation
  const [schedule, setSchedule]         = useState<QrSchedule | null>(null);
  const [schedLoading, setSchedLoading] = useState(true);
  const [schedSaving, setSchedSaving]   = useState(false);
  const [schedActive, setSchedActive]   = useState(false);
  const [schedLimit, setSchedLimit]     = useState(20);
  const [schedCountry, setSchedCountry] = useState('');
  const [schedCategory, setSchedCategory] = useState('');
  const [schedDurationType, setSchedDurationType] = useState<'unlimited'|'days'|'total'>('unlimited');
  const [schedMaxDays, setSchedMaxDays]   = useState(30);
  const [schedTotalGoal, setSchedTotalGoal] = useState(500);

  useEffect(() => {
    fetchQrSchedule().then(res => {
      const d = res.data as unknown as QrSchedule;
      setSchedule(d);
      setSchedActive(d.active);
      setSchedLimit(d.daily_limit);
      setSchedCountry(d.country ?? '');
      setSchedCategory(d.category ?? '');
      setSchedDurationType(d.duration_type ?? 'unlimited');
      setSchedMaxDays(d.max_days ?? 30);
      setSchedTotalGoal(d.total_goal ?? 500);
    }).catch(() => {/* silent */}).finally(() => setSchedLoading(false));
  }, []);

  const handleGenerate = async () => {
    setLaunching(true);
    try {
      const res = await launchQrBlogGeneration({ limit, country: country || undefined, category: category || undefined });
      const data = res.data as { message: string; total: number };
      toast('success', data.message);
      onProgressStart();
      reloadStats();
    } catch (e) {
      toast('error', errMsg(e));
      setLaunching(false);
    }
  };

  const handleReset = async () => {
    setResetting(true);
    try {
      const res = await resetQrBlogWriting();
      const data = res.data as { message: string };
      toast('success', data.message);
      reloadStats();
    } catch (e) { toast('error', errMsg(e)); }
    finally { setResetting(false); }
  };

  const handleSaveSchedule = async () => {
    setSchedSaving(true);
    try {
      const payload: QrSchedule = {
        active:          schedActive,
        daily_limit:     schedLimit,
        country:         schedCountry,
        category:        schedCategory,
        duration_type:   schedDurationType,
        max_days:        schedDurationType === 'days'  ? schedMaxDays   : null,
        total_goal:      schedDurationType === 'total' ? schedTotalGoal : null,
        start_date:      schedule?.start_date ?? null,
        total_generated: schedule?.total_generated ?? 0,
        last_run_at:     schedule?.last_run_at ?? null,
      };
      const res = await saveQrSchedule(payload);
      setSchedule((res.data as { config: QrSchedule }).config ?? payload);
      toast('success', 'Programmation enregistrée.');
    } catch (e) { toast('error', errMsg(e)); }
    finally { setSchedSaving(false); }
  };

  return (
    <div className="space-y-5">

      {/* ── Lancer maintenant ── */}
      <div className="bg-gradient-to-r from-blue-500/10 to-violet/10 border border-blue-500/30 rounded-xl p-5">
        <h3 className="text-base font-bold text-white mb-1">Lancer une génération maintenant</h3>
        <p className="text-xs text-muted mb-4">
          Claude optimise le titre, génère 600+ mots (H2/H3, 5-7 sous-questions) et traduit en 9 langues.
          ~${(limit * 0.023).toFixed(2)} USD estimé.
        </p>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
          <div>
            <label className="block text-xs text-muted mb-1">Nombre de Q/R <span className="text-blue-400">(1-200)</span></label>
            <input type="number" min={1} max={200} value={limit}
              onChange={e => setLimit(Math.min(200, Math.max(1, +e.target.value)))}
              disabled={isRunning} className={inputClass + ' w-full'} />
          </div>
          <div>
            <label className="block text-xs text-muted mb-1">Pays (optionnel)</label>
            <input value={country} placeholder="Ex: france, allemagne…"
              onChange={e => setCountry(e.target.value)}
              disabled={isRunning} className={inputClass + ' w-full'} />
          </div>
          <div>
            <label className="block text-xs text-muted mb-1">Catégorie (optionnel)</label>
            <select value={category} onChange={e => setCategory(e.target.value)}
              disabled={isRunning} className={inputClass + ' w-full'}>
              {CATEGORIES.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
            </select>
          </div>
        </div>

        <div className="flex items-center gap-3 flex-wrap">
          <button onClick={handleGenerate}
            disabled={isRunning || launching || (stats?.available ?? 0) === 0}
            className="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
            {isRunning ? (
              <><span className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />En cours…</>
            ) : (<>⚡ Générer {limit} Q/R</>)}
          </button>

          {(stats?.available ?? 0) === 0 && !isRunning && (
            <span className="text-xs text-amber">⚠ Aucune question disponible dans les Sources</span>
          )}

          {(stats?.writing ?? 0) > 0 && !isRunning && (
            <button onClick={handleReset} disabled={resetting}
              className="px-3 py-2 text-sm rounded-lg border border-amber/30 text-amber hover:bg-amber/10 transition">
              {resetting ? 'Réinitialisation…' : `Débloquer ${stats!.writing} en cours`}
            </button>
          )}
        </div>
      </div>

      {/* ── Progression détaillée ── */}
      {progress && progress.status !== 'idle' && progress.log && progress.log.length > 0 && (
        <div className="bg-surface border border-border rounded-xl p-4">
          <h4 className="text-sm font-semibold text-white mb-3">Dernières générations</h4>
          <div className="space-y-1 max-h-48 overflow-y-auto">
            {[...progress.log].reverse().slice(0, 20).map((entry, i) => (
              <div key={i} className={`text-xs flex items-start gap-2 ${
                entry.type === 'success' ? 'text-success' :
                entry.type === 'skip'   ? 'text-muted' : 'text-danger'
              }`}>
                <span className="shrink-0 mt-0.5">{entry.type === 'success' ? '✓' : entry.type === 'skip' ? '–' : '✗'}</span>
                <span className="flex-1 min-w-0">
                  {entry.type === 'success' && entry.optimized_title && entry.optimized_title !== entry.title ? (
                    <><span className="line-through opacity-40">{entry.title}</span> → <span className="text-white">{entry.optimized_title}</span></>
                  ) : entry.title}
                  {entry.reason && <span className="opacity-50 ml-1">({entry.reason})</span>}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ── Programmation quotidienne ── */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h3 className="text-base font-bold text-white">Programmation automatique</h3>
            <p className="text-xs text-muted mt-0.5">Lance automatiquement chaque jour à 07:00 UTC</p>
          </div>
          <div className="text-right text-xs text-muted space-y-0.5">
            {schedule?.last_run_at && (
              <div>Dernier run : {new Date(schedule.last_run_at).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}</div>
            )}
            {(schedule?.total_generated ?? 0) > 0 && (
              <div className="text-success">{(schedule!.total_generated).toLocaleString('fr-FR')} Q/R générées au total</div>
            )}
          </div>
        </div>

        {schedLoading ? (
          <div className="animate-pulse bg-surface2 h-24 rounded-lg" />
        ) : (
          <div className="space-y-5">

            {/* Toggle + rythme */}
            <div className="flex flex-col sm:flex-row gap-4">
              <label className="flex items-center gap-3 cursor-pointer">
                <div className="relative shrink-0">
                  <input type="checkbox" checked={schedActive} onChange={e => setSchedActive(e.target.checked)} className="sr-only" />
                  <div className={`w-11 h-6 rounded-full transition-colors ${schedActive ? 'bg-success' : 'bg-surface2'}`} />
                  <div className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${schedActive ? 'translate-x-5' : 'translate-x-0'}`} />
                </div>
                <span className="text-sm text-white font-medium whitespace-nowrap">
                  {schedActive ? 'Active' : 'Désactivée'}
                </span>
              </label>

              <div className="flex gap-3 flex-1 flex-wrap">
                <div>
                  <label className="block text-xs text-muted mb-1">Q/R par jour</label>
                  <input type="number" min={1} max={200} value={schedLimit}
                    onChange={e => setSchedLimit(Math.min(200, Math.max(1, +e.target.value)))}
                    disabled={!schedActive} className={inputClass + ' w-24'} />
                </div>
                <div>
                  <label className="block text-xs text-muted mb-1">Pays</label>
                  <input value={schedCountry} placeholder="Tous…"
                    onChange={e => setSchedCountry(e.target.value)}
                    disabled={!schedActive} className={inputClass + ' w-32'} />
                </div>
                <div>
                  <label className="block text-xs text-muted mb-1">Catégorie</label>
                  <select value={schedCategory} onChange={e => setSchedCategory(e.target.value)}
                    disabled={!schedActive} className={inputClass}>
                    {CATEGORIES.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
                  </select>
                </div>
              </div>
            </div>

            {/* Durée */}
            <div>
              <p className="text-xs text-muted mb-2 font-medium">Durée de la programmation</p>
              <div className="flex gap-2 flex-wrap">
                {([
                  { v: 'unlimited', label: '♾ Illimité' },
                  { v: 'days',      label: '📅 N jours' },
                  { v: 'total',     label: '🎯 Objectif total' },
                ] as const).map(opt => (
                  <button key={opt.v} onClick={() => setSchedDurationType(opt.v)}
                    disabled={!schedActive}
                    className={`px-3 py-1.5 text-xs rounded border transition ${
                      schedDurationType === opt.v
                        ? 'bg-blue-600 border-blue-500 text-white'
                        : 'bg-surface2 border-border text-muted hover:text-white'
                    } disabled:opacity-50`}>
                    {opt.label}
                  </button>
                ))}
              </div>

              {schedActive && schedDurationType === 'days' && (
                <div className="mt-3 flex items-center gap-3">
                  <div>
                    <label className="block text-xs text-muted mb-1">Nombre de jours</label>
                    <input type="number" min={1} max={3650} value={schedMaxDays}
                      onChange={e => setSchedMaxDays(Math.max(1, +e.target.value))}
                      className={inputClass + ' w-28'} />
                  </div>
                  <div className="text-xs text-muted mt-4">
                    = {(schedLimit * schedMaxDays).toLocaleString('fr-FR')} Q/R max · ~${(schedLimit * schedMaxDays * 0.023).toFixed(0)} USD
                  </div>
                </div>
              )}

              {schedActive && schedDurationType === 'total' && (
                <div className="mt-3 flex items-center gap-3">
                  <div>
                    <label className="block text-xs text-muted mb-1">Objectif Q/R au total</label>
                    <input type="number" min={1} max={100000} value={schedTotalGoal}
                      onChange={e => setSchedTotalGoal(Math.max(1, +e.target.value))}
                      className={inputClass + ' w-32'} />
                  </div>
                  <div className="text-xs text-muted mt-4">
                    = ~{Math.ceil(schedTotalGoal / schedLimit)} jours · ~${(schedTotalGoal * 0.023).toFixed(0)} USD
                  </div>
                </div>
              )}
            </div>

            {/* Projection */}
            {schedActive && (() => {
              const available = schedule?.sources_available ?? stats?.available ?? 0;
              const daysOfStock = schedLimit > 0 ? Math.floor(available / schedLimit) : 0;
              const costPerDay  = schedLimit * 0.023;
              const costPerMonth = costPerDay * 30;
              const remaining = schedDurationType === 'total'
                ? Math.max(0, schedTotalGoal - (schedule?.total_generated ?? 0))
                : null;
              return (
                <div className="bg-blue-500/5 border border-blue-500/20 rounded-lg p-3 text-xs space-y-1">
                  <p className="font-semibold text-white text-xs mb-1.5">📊 Projection</p>
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div>
                      <span className="text-muted block">Stock disponible</span>
                      <span className="text-blue-400 font-bold">{available.toLocaleString('fr-FR')} Q/R</span>
                    </div>
                    <div>
                      <span className="text-muted block">Durée du stock</span>
                      <span className={`font-bold ${daysOfStock > 60 ? 'text-success' : daysOfStock > 14 ? 'text-amber' : 'text-danger'}`}>
                        {daysOfStock > 0 ? `${daysOfStock} jours` : '< 1 jour'}
                      </span>
                    </div>
                    <div>
                      <span className="text-muted block">Coût / jour</span>
                      <span className="text-white font-bold">~${costPerDay.toFixed(2)}</span>
                    </div>
                    <div>
                      <span className="text-muted block">Coût / mois</span>
                      <span className="text-white font-bold">~${costPerMonth.toFixed(0)}</span>
                    </div>
                  </div>
                  {schedDurationType === 'total' && remaining !== null && (
                    <div className="mt-1 pt-1 border-t border-blue-500/20">
                      <span className="text-muted">Progression objectif : </span>
                      <span className="text-white font-bold">{(schedule?.total_generated ?? 0).toLocaleString('fr-FR')} / {schedTotalGoal.toLocaleString('fr-FR')}</span>
                      <span className="text-muted ml-1">({remaining.toLocaleString('fr-FR')} restantes · ~{Math.ceil(remaining / schedLimit)} jours)</span>
                    </div>
                  )}
                  {schedDurationType === 'days' && schedule?.start_date && (
                    <div className="mt-1 pt-1 border-t border-blue-500/20">
                      <span className="text-muted">Démarrage : </span>
                      <span className="text-white">{new Date(schedule.start_date).toLocaleDateString('fr-FR')}</span>
                      <span className="text-muted ml-2">Fin prévue : </span>
                      <span className="text-white">{new Date(new Date(schedule.start_date).getTime() + schedMaxDays * 86400000).toLocaleDateString('fr-FR')}</span>
                    </div>
                  )}
                  {daysOfStock < schedLimit && (
                    <p className="text-amber mt-1">⚠ Stock insuffisant pour {schedLimit} Q/R/jour — ajoutez des sources ou réduisez le rythme.</p>
                  )}
                </div>
              );
            })()}

            <button onClick={handleSaveSchedule} disabled={schedSaving}
              className="px-4 py-2 text-sm bg-surface2 hover:bg-surface text-white rounded transition font-medium disabled:opacity-50">
              {schedSaving ? 'Enregistrement…' : '💾 Enregistrer la programmation'}
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// Tab Contenus générés
// ─────────────────────────────────────────────────────────────
function TabGenerated() {
  const [articles, setArticles] = useState<Array<Record<string, unknown>>>([]);
  const [loading, setLoading]   = useState(false);
  const [search, setSearch]     = useState('');
  const [lang, setLang]         = useState('fr');
  const [page, setPage]         = useState(1);
  const [total, setTotal]       = useState(0);
  const [error, setError]       = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const params: Record<string, unknown> = { per_page: 20, page, language: lang };
      if (search) params.search = search;
      const res  = await fetchQrGenerated(params);
      const data = res.data as { data: Array<Record<string, unknown>>; total: number; current_page?: number };
      setArticles(data.data ?? []);
      setTotal(data.total ?? 0);
    } catch (e) {
      setError(errMsg(e));
    }
    finally { setLoading(false); }
  }, [page, lang, search]);

  useEffect(() => { load(); }, [load]);

  const LANGS = ['fr','en','es','de','pt','ru','zh','hi','ar'];

  return (
    <div className="bg-surface border border-border rounded-xl overflow-hidden">

      {/* Toolbar */}
      <div className="p-4 border-b border-border flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <span className="text-sm font-semibold text-white">
          Contenus générés
          <span className="ml-1.5 text-muted font-normal text-xs">({total.toLocaleString('fr-FR')} articles Q/R)</span>
        </span>
        <div className="flex gap-2 flex-wrap">
          <select value={lang} onChange={e => { setLang(e.target.value); setPage(1); }} className={inputClass + ' text-xs'}>
            {LANGS.map(l => <option key={l} value={l}>{l.toUpperCase()}</option>)}
          </select>
          <input value={search} placeholder="Rechercher…"
            onChange={e => { setSearch(e.target.value); setPage(1); }}
            className={inputClass + ' w-44 text-xs'} />
        </div>
      </div>

      {error && (
        <div className="p-4 bg-danger/10 text-danger text-sm">{error}</div>
      )}

      {/* Table */}
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border text-left bg-surface2/30">
              <th className="px-4 py-2.5 text-xs text-muted font-medium">Titre</th>
              <th className="px-4 py-2.5 text-xs text-muted font-medium w-20">Langue</th>
              <th className="px-4 py-2.5 text-xs text-muted font-medium w-32">Publié le</th>
              <th className="px-4 py-2.5 text-xs text-muted font-medium w-24">Actions</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              [...Array(8)].map((_, i) => (
                <tr key={i} className="border-b border-border/40">
                  <td colSpan={4} className="px-4 py-2.5"><div className="animate-pulse bg-surface2 h-3.5 rounded w-full" /></td>
                </tr>
              ))
            ) : articles.length === 0 ? (
              <tr><td colSpan={4} className="px-4 py-10 text-center text-muted text-sm">
                {error ? 'Erreur de chargement' : 'Aucun contenu Q/R publié pour l\'instant'}
              </td></tr>
            ) : articles.map(a => {
              // Extraire la traduction dans la langue sélectionnée
              const translations = (a.translations as Array<Record<string, unknown>>) ?? [];
              const tr = translations.find(t => t.language_code === lang) ?? translations[0];
              const title     = String(tr?.title ?? a.title ?? '—');
              const slug      = String(tr?.slug ?? '');
              const pubDate   = String(a.published_at ?? tr?.created_at ?? '');
              const extId     = String(a.external_article_id ?? '');
              return (
                <tr key={String(a.id)} className="border-b border-border/40 hover:bg-surface2/40 transition-colors">
                  <td className="px-4 py-2.5">
                    <span className="text-white text-xs line-clamp-2">{title}</span>
                    {extId && extId !== 'undefined' && (
                      <span className="block text-[10px] text-muted">{extId}</span>
                    )}
                  </td>
                  <td className="px-4 py-2.5">
                    <span className="text-xs font-mono uppercase text-muted">{String(tr?.language_code ?? lang)}</span>
                  </td>
                  <td className="px-4 py-2.5">
                    <span className="text-xs text-muted">
                      {pubDate ? new Date(pubDate).toLocaleDateString('fr-FR') : '—'}
                    </span>
                  </td>
                  <td className="px-4 py-2.5">
                    {slug && (
                      <a href={`https://sos-expat.com/${{ fr:'fr-fr',en:'en-us',es:'es-es',de:'de-de',ru:'ru-ru',pt:'pt-pt',zh:'zh-cn',hi:'hi-in',ar:'ar-sa' }[lang]??`${lang}-${lang}`}/vie-a-letranger/${slug}`}
                        target="_blank" rel="noopener noreferrer"
                        className="text-xs text-blue-400 hover:text-blue-300 transition">
                        Voir ↗
                      </a>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {total > 20 && (
        <div className="p-3 border-t border-border flex items-center justify-between">
          <span className="text-xs text-muted">Page {page} · {total.toLocaleString('fr-FR')} articles</span>
          <div className="flex gap-2">
            <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1}
              className="px-3 py-1.5 text-xs bg-surface2 text-muted rounded hover:text-white disabled:opacity-40">← Préc.</button>
            <button onClick={() => setPage(p => p + 1)} disabled={page * 20 >= total}
              className="px-3 py-1.5 text-xs bg-surface2 text-muted rounded hover:text-white disabled:opacity-40">Suiv. →</button>
          </div>
        </div>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// ActionBtn helper
// ─────────────────────────────────────────────────────────────
function ActionBtn({ children, title, onClick, hover = 'hover:text-white' }: {
  children: React.ReactNode; title: string; onClick: () => void; hover?: string;
}) {
  return (
    <button onClick={onClick} title={title}
      className={`text-xs px-1.5 py-1 bg-surface2 text-muted rounded transition-colors ${hover} hover:bg-surface`}>
      {children}
    </button>
  );
}
