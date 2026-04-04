import React, { useEffect, useState, useCallback, useRef } from 'react';
import {
  fetchQrBlogStats, fetchQrBlogProgress,
  launchQrBlogGeneration, resetQrBlogWriting,
} from '../../api/contentApi';
import type { QrBlogStats, QrBlogProgress } from '../../api/contentApi';
import { toast } from '../../components/Toast';
import { inputClass, errMsg } from './helpers';
import api from '../../api/client';

// ─── Types locaux ────────────────────────────────────────────
interface Question {
  id: number;
  title: string;
  country: string | null;
  country_slug: string | null;
  language: string;
  views: number;
  replies: number;
  article_status: string;
}

const STATUS_BADGE: Record<string, string> = {
  opportunity: 'bg-blue-500/20 text-blue-400',
  writing:     'bg-amber/20 text-amber animate-pulse',
  published:   'bg-success/20 text-success',
  skipped:     'bg-muted/20 text-muted',
  covered:     'bg-gray-500/20 text-gray-400',
};

const STATUS_LABEL: Record<string, string> = {
  opportunity: 'Disponible', writing: 'En cours…', published: 'Publiée', skipped: 'Ignorée', covered: 'Traitée',
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

// ─── Component ────────────────────────────────────────────────
export default function GenerateQr() {
  const [stats, setStats]       = useState<QrBlogStats | null>(null);
  const [progress, setProgress] = useState<QrBlogProgress | null>(null);
  const [loading, setLoading]   = useState(true);
  const [launching, setLaunching] = useState(false);
  const [resetting, setResetting] = useState(false);

  // Options de génération
  const [limit, setLimit]       = useState(50);
  const [country, setCountry]   = useState('');
  const [category, setCategory] = useState('');

  // Base de questions (liste visible + éditable)
  const [questions, setQuestions]     = useState<Question[]>([]);
  const [qLoading, setQLoading]       = useState(false);
  const [qSearch, setQSearch]         = useState('');
  const [qStatus, setQStatus]         = useState('opportunity');
  const [qPage, setQPage]             = useState(1);
  const [qTotal, setQTotal]           = useState(0);
  const [editingId, setEditingId]     = useState<number | null>(null);
  const [editTitle, setEditTitle]     = useState('');
  const [savingId, setSavingId]       = useState<number | null>(null);

  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // ── Chargement stats ────────────────────────────────────────
  const loadStats = useCallback(async () => {
    try {
      const res = await fetchQrBlogStats();
      const data = res.data as unknown as QrBlogStats;
      setStats(data);
      if (data.progress) setProgress(data.progress);
    } catch { /* silent */ }
  }, []);

  const loadProgress = useCallback(async () => {
    try {
      const res = await fetchQrBlogProgress();
      const data = res.data as unknown as QrBlogProgress;
      setProgress(data);
      if (data.status === 'completed' || data.status === 'failed') {
        stopPolling();
        setLaunching(false);
        loadStats();
        loadQuestions();
      }
    } catch { /* silent */ }
  }, []);

  const stopPolling = () => {
    if (pollRef.current) { clearInterval(pollRef.current); pollRef.current = null; }
  };
  const startPolling = () => {
    stopPolling();
    pollRef.current = setInterval(loadProgress, 3000);
  };

  useEffect(() => {
    loadStats().finally(() => setLoading(false));
    return () => stopPolling();
  }, []);

  // Reprendre polling si génération déjà en cours au chargement
  useEffect(() => {
    if (progress?.status === 'running') {
      setLaunching(true);
      startPolling();
    }
  }, []);

  // ── Questions DB ─────────────────────────────────────────────
  const loadQuestions = useCallback(async () => {
    setQLoading(true);
    try {
      const params: Record<string, unknown> = { per_page: 20, page: qPage, sort: 'views', direction: 'desc' };
      if (qStatus) params.status = qStatus;
      if (qSearch) params.search = qSearch;
      if (country) params.country = country;
      const res = await api.get('/questions', { params });
      const data = res.data as { data: Question[]; total: number };
      setQuestions(data.data ?? []);
      setQTotal(data.total ?? 0);
    } catch { toast('error', 'Erreur chargement questions'); }
    finally { setQLoading(false); }
  }, [qPage, qStatus, qSearch, country]);

  useEffect(() => { loadQuestions(); }, [loadQuestions]);

  // ── Lancer génération ─────────────────────────────────────────
  const handleGenerate = async () => {
    setLaunching(true);
    try {
      const res = await launchQrBlogGeneration({ limit, country: country || undefined, category: category || undefined });
      const data = res.data as { message: string; total: number };
      toast('success', data.message);
      startPolling();
      loadStats();
    } catch (e) {
      toast('error', errMsg(e));
      setLaunching(false);
    }
  };

  // ── Réinitialiser bloquées ────────────────────────────────────
  const handleReset = async () => {
    setResetting(true);
    try {
      const res = await resetQrBlogWriting();
      const data = res.data as { message: string };
      toast('success', data.message);
      loadStats();
      loadQuestions();
    } catch (e) { toast('error', errMsg(e)); }
    finally { setResetting(false); }
  };

  // ── Édition titre inline ──────────────────────────────────────
  const startEdit = (q: Question) => {
    setEditingId(q.id);
    setEditTitle(q.title);
  };
  const saveEdit = async (id: number) => {
    if (!editTitle.trim()) return;
    setSavingId(id);
    try {
      await api.put(`/questions/${id}/status`, { title: editTitle.trim() });
      setQuestions(qs => qs.map(q => q.id === id ? { ...q, title: editTitle.trim() } : q));
      toast('success', 'Titre mis à jour');
      setEditingId(null);
    } catch (e) { toast('error', errMsg(e)); }
    finally { setSavingId(null); }
  };

  // ── Ignorer / Remettre une question ──────────────────────────
  const updateStatus = async (id: number, status: string) => {
    try {
      await api.put(`/questions/${id}/status`, { article_status: status });
      setQuestions(qs => qs.map(q => q.id === id ? { ...q, article_status: status } : q));
    } catch (e) { toast('error', errMsg(e)); }
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-4">
        {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-20" />)}
      </div>
    );
  }

  const isRunning  = progress?.status === 'running';
  const isDone     = progress?.status === 'completed';
  const isFailed   = progress?.status === 'failed';
  const pct = progress && progress.total > 0
    ? Math.round(((progress.completed + progress.skipped + progress.errors) / progress.total) * 100)
    : 0;

  return (
    <div className="p-4 md:p-6 space-y-6">

      {/* ── Header ── */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="font-title text-2xl font-bold text-white flex items-center gap-2">
            ❓ Générer Q/R
          </h2>
          <p className="text-muted text-sm mt-1">
            Génère des pages Questions/Réponses SEO pour le Blog à partir des questions de forum scrapées.
            Titres, méta et contenu optimisés par Claude. 9 langues automatiques.
          </p>
        </div>
        {stats && (
          <div className="text-right">
            <span className="text-3xl font-bold text-blue-400">{stats.available}</span>
            <p className="text-xs text-muted">disponibles</p>
          </div>
        )}
      </div>

      {/* ── Stats rapides ── */}
      {stats && (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div className="bg-surface border border-border rounded-xl p-4 text-center">
            <div className="text-2xl font-bold text-blue-400">{stats.available}</div>
            <div className="text-xs text-muted mt-1">Disponibles</div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4 text-center">
            <div className="text-2xl font-bold text-amber">{stats.writing}</div>
            <div className="text-xs text-muted mt-1">En cours</div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4 text-center">
            <div className="text-2xl font-bold text-success">{stats.published}</div>
            <div className="text-xs text-muted mt-1">Publiées</div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4 text-center">
            <div className="text-2xl font-bold text-muted">{stats.skipped}</div>
            <div className="text-xs text-muted mt-1">Ignorées</div>
          </div>
        </div>
      )}

      {/* ── Panel de génération ── */}
      <div className="bg-gradient-to-r from-blue-500/10 to-violet/10 border border-blue-500/30 rounded-xl p-6">
        <h3 className="text-lg font-bold text-white mb-1">Lancer une génération Q/R</h3>
        <p className="text-sm text-muted mb-4">
          Claude analyse chaque question, optimise le titre pour Google, génère une page riche (600+ mots,
          H2/H3, 5-7 sous-questions) et traduit automatiquement en 9 langues.
        </p>

        {/* Options */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
          <div>
            <label className="block text-xs text-muted mb-1">Nombre de Q/R <span className="text-blue-400">(1-200)</span></label>
            <input type="number" min={1} max={200} value={limit}
              onChange={e => setLimit(Math.min(200, Math.max(1, +e.target.value)))}
              disabled={isRunning}
              className={inputClass + ' w-full'} />
          </div>
          <div>
            <label className="block text-xs text-muted mb-1">Pays (optionnel)</label>
            <input type="text" value={country} placeholder="Ex: france, allemagne…"
              onChange={e => setCountry(e.target.value)}
              disabled={isRunning}
              className={inputClass + ' w-full'} />
          </div>
          <div>
            <label className="block text-xs text-muted mb-1">Catégorie (optionnel)</label>
            <select value={category} onChange={e => setCategory(e.target.value)}
              disabled={isRunning}
              className={inputClass + ' w-full'}>
              {CATEGORIES.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
            </select>
          </div>
        </div>

        {/* Coût estimé */}
        <p className="text-xs text-muted mb-4">
          Coût estimé : ~${(limit * 0.023).toFixed(2)} USD (Sonnet génération + Haiku traductions × 8 langues)
        </p>

        {/* Bouton lancer */}
        <div className="flex items-center gap-3">
          <button
            onClick={handleGenerate}
            disabled={isRunning || launching || (stats?.available ?? 0) === 0}
            className="px-6 py-3 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            {isRunning ? (
              <><span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />Génération en cours…</>
            ) : (
              <>❓ Générer {limit} Q/R</>
            )}
          </button>

          {stats && stats.writing > 0 && !isRunning && (
            <button onClick={handleReset} disabled={resetting}
              className="px-4 py-2 rounded-lg border border-amber/30 text-amber text-sm hover:bg-amber/10 transition">
              {resetting ? 'Réinitialisation…' : `Débloquer ${stats.writing} en cours`}
            </button>
          )}
        </div>
      </div>

      {/* ── Barre de progression ── */}
      {progress && progress.status !== 'idle' && (
        <div className={`border rounded-xl p-5 ${
          isRunning ? 'bg-surface border-blue-500/30' :
          isDone    ? 'bg-success/5 border-success/30' :
          isFailed  ? 'bg-danger/5 border-danger/30' :
                      'bg-surface border-border'
        }`}>
          <div className="flex items-center justify-between mb-3">
            <h4 className="font-semibold text-white flex items-center gap-2">
              {isRunning && <span className="w-2 h-2 bg-blue-400 rounded-full animate-pulse" />}
              {isDone && <span className="text-success">✓</span>}
              {isFailed && <span className="text-danger">✗</span>}
              {isRunning ? 'Génération en cours…' : isDone ? 'Génération terminée' : 'Génération échouée'}
            </h4>
            <span className="text-sm text-muted">
              {progress.completed + progress.skipped + progress.errors} / {progress.total}
            </span>
          </div>

          {/* Barre de progression */}
          <div className="w-full h-2 bg-surface2 rounded-full overflow-hidden mb-3">
            <div className={`h-full rounded-full transition-all duration-500 ${
              isDone ? 'bg-success' : isFailed ? 'bg-danger' : 'bg-blue-500'
            }`} style={{ width: `${pct}%` }} />
          </div>

          {/* Stats progression */}
          <div className="grid grid-cols-3 gap-3 mb-3">
            <div className="text-center">
              <div className="text-xl font-bold text-success">{progress.completed}</div>
              <div className="text-xs text-muted">Publiées</div>
            </div>
            <div className="text-center">
              <div className="text-xl font-bold text-muted">{progress.skipped}</div>
              <div className="text-xs text-muted">Ignorées</div>
            </div>
            <div className="text-center">
              <div className="text-xl font-bold text-danger">{progress.errors}</div>
              <div className="text-xs text-muted">Erreurs</div>
            </div>
          </div>

          {/* Question en cours */}
          {progress.current_title && (
            <p className="text-xs text-muted truncate">
              ⟳ <span className="text-blue-400">{progress.current_title}</span>
            </p>
          )}

          {/* Log (10 derniers) */}
          {progress.log && progress.log.length > 0 && (
            <div className="mt-3 max-h-40 overflow-y-auto space-y-1">
              {[...progress.log].reverse().slice(0, 10).map((entry, i) => (
                <div key={i} className={`text-xs flex items-start gap-2 ${
                  entry.type === 'success' ? 'text-success' :
                  entry.type === 'skip'    ? 'text-muted' : 'text-danger'
                }`}>
                  <span className="shrink-0">{entry.type === 'success' ? '✓' : entry.type === 'skip' ? '–' : '✗'}</span>
                  <span className="truncate">
                    {entry.type === 'success' && entry.optimized_title
                      ? <><span className="line-through opacity-50">{entry.title}</span> → {entry.optimized_title}</>
                      : entry.title}
                    {entry.reason && <span className="opacity-60"> ({entry.reason})</span>}
                  </span>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* ── Base de questions ── */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <div className="p-4 border-b border-border flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
          <h3 className="font-semibold text-white">
            Base de questions forum
            <span className="ml-2 text-sm text-muted font-normal">({qTotal.toLocaleString('fr-FR')} total)</span>
          </h3>
          <div className="flex gap-2 flex-wrap">
            {/* Recherche */}
            <input type="text" value={qSearch} placeholder="Rechercher…"
              onChange={e => { setQSearch(e.target.value); setQPage(1); }}
              className={inputClass + ' w-48'} />
            {/* Filtre statut */}
            <select value={qStatus} onChange={e => { setQStatus(e.target.value); setQPage(1); }}
              className={inputClass}>
              <option value="">Tous</option>
              <option value="opportunity">Disponibles</option>
              <option value="writing">En cours</option>
              <option value="published">Publiées</option>
              <option value="skipped">Ignorées</option>
              <option value="covered">Traitées</option>
            </select>
          </div>
        </div>

        {/* Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left">
                <th className="px-4 py-3 text-xs text-muted font-medium">Question source</th>
                <th className="px-4 py-3 text-xs text-muted font-medium w-24">Pays</th>
                <th className="px-4 py-3 text-xs text-muted font-medium w-20 text-right">Vues</th>
                <th className="px-4 py-3 text-xs text-muted font-medium w-28">Statut</th>
                <th className="px-4 py-3 text-xs text-muted font-medium w-24">Actions</th>
              </tr>
            </thead>
            <tbody>
              {qLoading ? (
                [...Array(5)].map((_, i) => (
                  <tr key={i} className="border-b border-border/50">
                    <td colSpan={5} className="px-4 py-3">
                      <div className="animate-pulse bg-surface2 h-4 rounded w-full" />
                    </td>
                  </tr>
                ))
              ) : questions.length === 0 ? (
                <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">Aucune question trouvée</td></tr>
              ) : (
                questions.map(q => (
                  <tr key={q.id} className="border-b border-border/50 hover:bg-surface2/50 transition-colors">
                    <td className="px-4 py-3">
                      {editingId === q.id ? (
                        <div className="flex items-center gap-2">
                          <input value={editTitle} onChange={e => setEditTitle(e.target.value)}
                            onKeyDown={e => { if (e.key === 'Enter') saveEdit(q.id); if (e.key === 'Escape') setEditingId(null); }}
                            className={inputClass + ' flex-1 text-xs'} autoFocus />
                          <button onClick={() => saveEdit(q.id)} disabled={savingId === q.id}
                            className="text-xs px-2 py-1 bg-success/20 text-success rounded hover:bg-success/30">
                            {savingId === q.id ? '…' : '✓'}
                          </button>
                          <button onClick={() => setEditingId(null)}
                            className="text-xs px-2 py-1 bg-surface2 text-muted rounded hover:bg-surface">
                            ✗
                          </button>
                        </div>
                      ) : (
                        <span className="text-white text-xs leading-snug line-clamp-2">{q.title}</span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <span className="text-xs text-muted uppercase">{q.country ?? '—'}</span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <span className="text-xs text-muted">{q.views?.toLocaleString('fr-FR')}</span>
                    </td>
                    <td className="px-4 py-3">
                      <span className={`inline-block px-2 py-0.5 rounded text-[10px] font-medium ${STATUS_BADGE[q.article_status] ?? 'bg-muted/20 text-muted'}`}>
                        {STATUS_LABEL[q.article_status] ?? q.article_status}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-1">
                        {/* Modifier le titre */}
                        {q.article_status === 'opportunity' && editingId !== q.id && (
                          <button onClick={() => startEdit(q)}
                            title="Modifier le titre avant génération"
                            className="text-xs px-1.5 py-1 bg-surface2 text-muted rounded hover:text-white hover:bg-surface transition-colors">
                            ✏
                          </button>
                        )}
                        {/* Ignorer */}
                        {q.article_status === 'opportunity' && (
                          <button onClick={() => updateStatus(q.id, 'skipped')}
                            title="Ignorer cette question"
                            className="text-xs px-1.5 py-1 bg-surface2 text-muted rounded hover:text-amber hover:bg-amber/10 transition-colors">
                            —
                          </button>
                        )}
                        {/* Remettre en file d'attente */}
                        {(q.article_status === 'skipped' || q.article_status === 'published') && (
                          <button onClick={() => updateStatus(q.id, 'opportunity')}
                            title="Remettre en file d'attente"
                            className="text-xs px-1.5 py-1 bg-surface2 text-muted rounded hover:text-blue-400 hover:bg-blue-500/10 transition-colors">
                            ↺
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {qTotal > 20 && (
          <div className="p-4 border-t border-border flex items-center justify-between">
            <span className="text-xs text-muted">Page {qPage} · {qTotal.toLocaleString('fr-FR')} questions</span>
            <div className="flex gap-2">
              <button onClick={() => setQPage(p => Math.max(1, p - 1))} disabled={qPage === 1}
                className="px-3 py-1.5 text-xs bg-surface2 text-muted rounded hover:text-white disabled:opacity-40">
                ← Préc.
              </button>
              <button onClick={() => setQPage(p => p + 1)} disabled={qPage * 20 >= qTotal}
                className="px-3 py-1.5 text-xs bg-surface2 text-muted rounded hover:text-white disabled:opacity-40">
                Suiv. →
              </button>
            </div>
          </div>
        )}
      </div>

    </div>
  );
}
