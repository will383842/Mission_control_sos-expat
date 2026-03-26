import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { fetchLandings, deleteLanding } from '../../api/contentApi';
import type { LandingPage, ContentStatus, PaginatedResponse } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { errMsg, seoBarColor, inputClass } from './helpers';

// ── Constants ───────────────────────────────────────────────
const STATUS_COLORS: Record<ContentStatus, string> = {
  draft: 'bg-muted/20 text-muted',
  generating: 'bg-amber/20 text-amber animate-pulse',
  review: 'bg-blue-500/20 text-blue-400',
  scheduled: 'bg-violet/20 text-violet',
  published: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted',
};

const STATUS_LABELS: Record<ContentStatus, string> = {
  draft: 'Brouillon',
  generating: 'Generation...',
  review: 'Revue',
  scheduled: 'Planifie',
  published: 'Publie',
  archived: 'Archive',
};

const STATUS_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'Tous les statuts' },
  { value: 'draft', label: 'Brouillon' },
  { value: 'review', label: 'En revue' },
  { value: 'published', label: 'Publie' },
  { value: 'archived', label: 'Archive' },
];

const LANG_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'Toutes les langues' },
  { value: 'fr', label: 'Francais' },
  { value: 'en', label: 'Anglais' },
  { value: 'de', label: 'Allemand' },
  { value: 'es', label: 'Espagnol' },
  { value: 'pt', label: 'Portugais' },
];

function formatDate(d: string | null): string {
  if (!d) return '\u2014';
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ── Component ───────────────────────────────────────────────
export default function LandingsList() {
  const navigate = useNavigate();
  const [landings, setLandings] = useState<LandingPage[]>([]);
  const [loading, setLoading] = useState(true);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  // Filters
  const [search, setSearch] = useState('');
  const [language, setLanguage] = useState('');
  const [status, setStatus] = useState('');

  // Delete
  const [confirmDelete, setConfirmDelete] = useState<LandingPage | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = { page };
      if (search.trim()) params.search = search.trim();
      if (language) params.language = language;
      if (status) params.status = status;

      const res = await fetchLandings(params);
      const data = res.data as unknown as PaginatedResponse<LandingPage>;
      setLandings(data.data);
      setTotal(data.total);
      setLastPage(data.last_page);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setLoading(false);
    }
  }, [page, search, language, status]);

  useEffect(() => { loadData(); }, [loadData]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    try {
      await deleteLanding(confirmDelete.id);
      toast('success', 'Landing supprimee.');
      setConfirmDelete(null);
      loadData();
    } catch (err) {
      toast('error', errMsg(err));
    }
  };

  // Stats
  const publishedCount = landings.filter(l => l.status === 'published').length;
  const avgSeo = landings.length > 0
    ? Math.round(landings.reduce((sum, l) => sum + l.seo_score, 0) / landings.length)
    : 0;

  if (loading && landings.length === 0) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-64" />
        <div className="grid grid-cols-3 gap-4">
          {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-24" />)}
        </div>
        <div className="animate-pulse bg-surface2 rounded-xl h-64" />
      </div>
    );
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">Landing Pages</h2>
          <p className="text-sm text-muted mt-1">{total} landing page(s) au total</p>
        </div>
        <button
          onClick={() => navigate('/content/landings/new')}
          className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Nouvelle landing
        </button>
      </div>

      {/* Stat cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-xs text-muted uppercase tracking-wide">Total</p>
          <p className="text-2xl font-bold text-white mt-1">{total}</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-xs text-muted uppercase tracking-wide">Publiees</p>
          <p className="text-2xl font-bold text-success mt-1">{publishedCount}</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-xs text-muted uppercase tracking-wide">SEO moyen</p>
          <p className="text-2xl font-bold text-white mt-1">{avgSeo}/100</p>
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3">
        <input
          type="text"
          value={search}
          onChange={e => { setSearch(e.target.value); setPage(1); }}
          placeholder="Rechercher..."
          className={inputClass + ' w-64'}
        />
        <select value={language} onChange={e => { setLanguage(e.target.value); setPage(1); }} className={inputClass}>
          {LANG_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select value={status} onChange={e => { setStatus(e.target.value); setPage(1); }} className={inputClass}>
          {STATUS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {/* Table */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-muted text-xs uppercase tracking-wide">
                <th className="text-left px-4 py-3">Titre</th>
                <th className="text-left px-4 py-3">Langue</th>
                <th className="text-left px-4 py-3">Statut</th>
                <th className="text-left px-4 py-3">SEO</th>
                <th className="text-left px-4 py-3">Sections</th>
                <th className="text-left px-4 py-3">Publie le</th>
                <th className="text-right px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              {landings.map(landing => (
                <tr key={landing.id} className="border-b border-border/50 hover:bg-surface2/30 transition-colors">
                  <td className="px-4 py-3">
                    <button
                      onClick={() => navigate(`/content/landings/${landing.id}`)}
                      className="text-white hover:text-violet-light transition-colors text-left font-medium"
                    >
                      {landing.title || 'Sans titre'}
                    </button>
                  </td>
                  <td className="px-4 py-3">
                    <span className="px-2 py-0.5 rounded text-xs bg-violet/20 text-violet-light uppercase">
                      {landing.language}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-0.5 rounded text-xs ${STATUS_COLORS[landing.status]}`}>
                      {STATUS_LABELS[landing.status]}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <div className="w-16 h-1.5 bg-surface2 rounded-full overflow-hidden">
                        <div className={`h-full rounded-full ${seoBarColor(landing.seo_score)}`} style={{ width: `${landing.seo_score}%` }} />
                      </div>
                      <span className="text-xs text-muted">{landing.seo_score}</span>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-muted">
                    {landing.sections?.length ?? 0}
                  </td>
                  <td className="px-4 py-3 text-muted text-xs">
                    {formatDate(landing.published_at)}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        onClick={() => navigate(`/content/landings/${landing.id}`)}
                        className="text-xs text-muted hover:text-white transition-colors"
                      >
                        Voir
                      </button>
                      <button
                        onClick={() => setConfirmDelete(landing)}
                        className="text-xs text-danger hover:text-red-300 transition-colors"
                      >
                        Supprimer
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {landings.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-muted">
                    Aucune landing page trouvee.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Pagination */}
      {lastPage > 1 && (
        <div className="flex items-center justify-center gap-2">
          <button
            onClick={() => setPage(p => Math.max(1, p - 1))}
            disabled={page === 1}
            className="px-3 py-1.5 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-30 transition-colors"
          >
            Precedent
          </button>
          <span className="text-xs text-muted">Page {page} / {lastPage}</span>
          <button
            onClick={() => setPage(p => Math.min(lastPage, p + 1))}
            disabled={page === lastPage}
            className="px-3 py-1.5 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-30 transition-colors"
          >
            Suivant
          </button>
        </div>
      )}

      <ConfirmModal
        open={!!confirmDelete}
        title="Supprimer la landing"
        message={`Voulez-vous vraiment supprimer "${confirmDelete?.title}" ?`}
        variant="danger"
        confirmLabel="Supprimer"
        onConfirm={handleDelete}
        onCancel={() => setConfirmDelete(null)}
      />
    </div>
  );
}
