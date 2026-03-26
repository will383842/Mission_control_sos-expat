import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { fetchComparatives, deleteComparative } from '../../api/contentApi';
import type { Comparative, ContentStatus, PaginatedResponse } from '../../types/content';

// ── Constants ───────────────────────────────────────────────
const STATUS_COLORS: Record<ContentStatus, string> = {
  draft: 'bg-muted/20 text-muted',
  generating: 'bg-amber/20 text-amber',
  review: 'bg-orange-500/20 text-orange-400',
  scheduled: 'bg-cyan/20 text-cyan',
  published: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted',
};

const STATUS_LABELS: Record<ContentStatus, string> = {
  draft: 'Brouillon',
  generating: 'Generation',
  review: 'A relire',
  scheduled: 'Planifie',
  published: 'Publie',
  archived: 'Archive',
};

const STATUS_OPTIONS = [
  { value: '', label: 'Tous les statuts' },
  { value: 'draft', label: 'Brouillon' },
  { value: 'generating', label: 'Generation' },
  { value: 'review', label: 'A relire' },
  { value: 'published', label: 'Publie' },
  { value: 'archived', label: 'Archive' },
];

const LANGUAGE_OPTIONS = [
  { value: '', label: 'Toutes les langues' },
  { value: 'fr', label: 'Francais' },
  { value: 'en', label: 'English' },
  { value: 'de', label: 'Deutsch' },
  { value: 'es', label: 'Espanol' },
  { value: 'pt', label: 'Portugues' },
  { value: 'ru', label: 'Russe' },
  { value: 'zh', label: 'Chinois' },
  { value: 'ar', label: 'Arabe' },
  { value: 'hi', label: 'Hindi' },
];

function seoBgColor(score: number) {
  if (score >= 80) return 'bg-success/20 text-success';
  if (score >= 60) return 'bg-amber/20 text-amber';
  return 'bg-danger/20 text-danger';
}

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

// ── Component ───────────────────────────────────────────────
export default function ComparativesList() {
  const navigate = useNavigate();
  const [comparatives, setComparatives] = useState<Comparative[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });

  const [filterStatus, setFilterStatus] = useState('');
  const [filterLanguage, setFilterLanguage] = useState('');
  const [filterSearch, setFilterSearch] = useState('');

  const loadComparatives = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, unknown> = { page };
      if (filterStatus) params.status = filterStatus;
      if (filterLanguage) params.language = filterLanguage;
      if (filterSearch) params.search = filterSearch;
      const { data } = await fetchComparatives(params);
      const paginated = data as PaginatedResponse<Comparative>;
      setComparatives(paginated.data);
      setPagination({ current_page: paginated.current_page, last_page: paginated.last_page, total: paginated.total });
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  }, [filterStatus, filterLanguage, filterSearch]);

  useEffect(() => {
    loadComparatives(1);
  }, [loadComparatives]);

  const handleDelete = async (id: number) => {
    if (!confirm('Supprimer ce comparatif ?')) return;
    try {
      await deleteComparative(id);
      setComparatives(prev => prev.filter(c => c.id !== id));
    } catch { /* ignore */ }
  };

  return (
    <div className="p-4 md:p-6 space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between gap-3">
        <h2 className="font-title text-2xl font-bold text-white">Comparatifs</h2>
        <button
          onClick={() => navigate('/content/comparatives/new')}
          className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Nouveau comparatif
        </button>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3">
        <select value={filterStatus} onChange={e => setFilterStatus(e.target.value)} className={inputClass}>
          {STATUS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select value={filterLanguage} onChange={e => setFilterLanguage(e.target.value)} className={inputClass}>
          {LANGUAGE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <input
          type="text"
          placeholder="Rechercher..."
          value={filterSearch}
          onChange={e => setFilterSearch(e.target.value)}
          className={`${inputClass} w-48`}
        />
        <span className="text-xs text-muted ml-auto">{pagination.total} comparatif(s)</span>
      </div>

      {/* Error */}
      {error && (
        <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-lg">{error}</div>
      )}

      {/* Table */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        {loading ? (
          <div className="p-8 text-center text-muted text-sm">Chargement...</div>
        ) : comparatives.length === 0 ? (
          <div className="p-10 text-center">
            <p className="text-muted text-sm mb-3">Aucun comparatif trouve</p>
            <button
              onClick={() => navigate('/content/comparatives/new')}
              className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
            >
              Creer votre premier comparatif
            </button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pl-4 pr-4">Titre</th>
                  <th className="pb-3 pr-4">Entites</th>
                  <th className="pb-3 pr-4">Langue</th>
                  <th className="pb-3 pr-4">Statut</th>
                  <th className="pb-3 pr-4">SEO</th>
                  <th className="pb-3 pr-4">Cree le</th>
                  <th className="pb-3 pr-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                {comparatives.map(comp => (
                  <tr
                    key={comp.id}
                    className="border-b border-border/50 hover:bg-surface2/50 transition-colors cursor-pointer"
                    onClick={() => navigate(`/content/comparatives/${comp.id}`)}
                  >
                    <td className="py-3 pl-4 pr-4">
                      <span className="text-white font-medium truncate block max-w-[220px]">{comp.title}</span>
                    </td>
                    <td className="py-3 pr-4 text-muted text-xs">
                      {comp.entities.map(e => e.name).join(', ')}
                    </td>
                    <td className="py-3 pr-4 text-muted uppercase">{comp.language}</td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[comp.status]}`}>
                        {STATUS_LABELS[comp.status]}
                      </span>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${seoBgColor(comp.seo_score)}`}>
                        {comp.seo_score}
                      </span>
                    </td>
                    <td className="py-3 pr-4 text-muted">{new Date(comp.created_at).toLocaleDateString('fr-FR')}</td>
                    <td className="py-3 pr-4" onClick={e => e.stopPropagation()}>
                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => navigate(`/content/comparatives/${comp.id}`)}
                          className="text-xs text-violet hover:text-violet-light transition-colors"
                        >
                          Voir
                        </button>
                        <button
                          onClick={() => handleDelete(comp.id)}
                          className="text-xs text-danger hover:text-red-400 transition-colors"
                        >
                          Supprimer
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Pagination */}
      {pagination.last_page > 1 && (
        <div className="flex items-center justify-center gap-2">
          <button
            disabled={pagination.current_page <= 1}
            onClick={() => loadComparatives(pagination.current_page - 1)}
            className="px-3 py-1.5 bg-surface2 text-muted hover:text-white text-xs rounded-lg border border-border transition-colors disabled:opacity-30"
          >
            Precedent
          </button>
          {Array.from({ length: pagination.last_page }, (_, i) => i + 1)
            .filter(p => p === 1 || p === pagination.last_page || Math.abs(p - pagination.current_page) <= 2)
            .map((page, idx, arr) => (
              <React.Fragment key={page}>
                {idx > 0 && arr[idx - 1] !== page - 1 && <span className="text-muted text-xs">...</span>}
                <button
                  onClick={() => loadComparatives(page)}
                  className={`px-3 py-1.5 text-xs rounded-lg border transition-colors ${
                    page === pagination.current_page
                      ? 'bg-violet text-white border-violet'
                      : 'bg-surface2 text-muted hover:text-white border-border'
                  }`}
                >
                  {page}
                </button>
              </React.Fragment>
            ))}
          <button
            disabled={pagination.current_page >= pagination.last_page}
            onClick={() => loadComparatives(pagination.current_page + 1)}
            className="px-3 py-1.5 bg-surface2 text-muted hover:text-white text-xs rounded-lg border border-border transition-colors disabled:opacity-30"
          >
            Suivant
          </button>
        </div>
      )}
    </div>
  );
}
