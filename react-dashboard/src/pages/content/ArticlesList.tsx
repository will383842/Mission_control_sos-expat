import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useContentArticles } from '../../hooks/useContentEngine';
import type { ContentStatus } from '../../types/content';

// ── Constants ───────────────────────────────────────────────
const STATUS_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'Tous les statuts' },
  { value: 'draft', label: 'Brouillon' },
  { value: 'generating', label: 'Generation' },
  { value: 'review', label: 'A relire' },
  { value: 'scheduled', label: 'Planifie' },
  { value: 'published', label: 'Publie' },
  { value: 'archived', label: 'Archive' },
];

const LANGUAGE_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'Toutes les langues' },
  { value: 'fr', label: 'Francais' },
  { value: 'en', label: 'English' },
  { value: 'de', label: 'Deutsch' },
  { value: 'es', label: 'Espanol' },
  { value: 'pt', label: 'Portugues' },
  { value: 'ru', label: 'Russkiy' },
  { value: 'zh', label: 'Zhongwen' },
  { value: 'ar', label: 'Arabiya' },
  { value: 'hi', label: 'Hindi' },
];

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

const TYPE_LABELS: Record<string, string> = {
  article: 'Article',
  guide: 'Guide',
  news: 'Actualite',
  tutorial: 'Tutoriel',
};

function seoBgColor(score: number) {
  if (score >= 80) return 'bg-success/20 text-success';
  if (score >= 60) return 'bg-amber/20 text-amber';
  return 'bg-danger/20 text-danger';
}

// ── Sort helpers ────────────────────────────────────────────
type SortField = 'title' | 'content_type' | 'language' | 'country' | 'status' | 'seo_score' | 'quality_score' | 'word_count' | 'published_at' | 'created_at';
type SortDir = 'asc' | 'desc';

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

// ── Component ───────────────────────────────────────────────
export default function ArticlesList() {
  const navigate = useNavigate();
  const { articles, loading, error, pagination, load, remove, bulkRemove } = useContentArticles();

  const [filterStatus, setFilterStatus] = useState('');
  const [filterLanguage, setFilterLanguage] = useState('');
  const [filterCountry, setFilterCountry] = useState('');
  const [filterSearch, setFilterSearch] = useState('');
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [sortField, setSortField] = useState<SortField>('created_at');
  const [sortDir, setSortDir] = useState<SortDir>('desc');
  const [actionMenuId, setActionMenuId] = useState<number | null>(null);

  const fetchArticles = useCallback(
    (page = 1) => {
      load({
        status: filterStatus || undefined,
        language: filterLanguage || undefined,
        country: filterCountry || undefined,
        search: filterSearch || undefined,
        page,
      });
      setSelected(new Set());
    },
    [load, filterStatus, filterLanguage, filterCountry, filterSearch]
  );

  useEffect(() => {
    fetchArticles(1);
  }, [fetchArticles]);

  // Sort client-side
  const sortedArticles = [...articles].sort((a, b) => {
    let aVal: string | number = '';
    let bVal: string | number = '';
    switch (sortField) {
      case 'title': aVal = a.title.toLowerCase(); bVal = b.title.toLowerCase(); break;
      case 'content_type': aVal = a.content_type; bVal = b.content_type; break;
      case 'language': aVal = a.language; bVal = b.language; break;
      case 'country': aVal = a.country || ''; bVal = b.country || ''; break;
      case 'status': aVal = a.status; bVal = b.status; break;
      case 'seo_score': aVal = a.seo_score; bVal = b.seo_score; break;
      case 'quality_score': aVal = a.quality_score; bVal = b.quality_score; break;
      case 'word_count': aVal = a.word_count; bVal = b.word_count; break;
      case 'published_at': aVal = a.published_at || ''; bVal = b.published_at || ''; break;
      case 'created_at': aVal = a.created_at; bVal = b.created_at; break;
    }
    if (aVal < bVal) return sortDir === 'asc' ? -1 : 1;
    if (aVal > bVal) return sortDir === 'asc' ? 1 : -1;
    return 0;
  });

  const toggleSort = (field: SortField) => {
    if (sortField === field) {
      setSortDir(d => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortField(field);
      setSortDir('asc');
    }
  };

  const toggleSelect = (id: number) => {
    setSelected(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const toggleAll = () => {
    if (selected.size === sortedArticles.length) {
      setSelected(new Set());
    } else {
      setSelected(new Set(sortedArticles.map(a => a.id)));
    }
  };

  const handleBulkDelete = async () => {
    if (selected.size === 0) return;
    if (!confirm(`Supprimer ${selected.size} article(s) ?`)) return;
    try {
      await bulkRemove(Array.from(selected));
      setSelected(new Set());
    } catch { /* ignore */ }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Supprimer cet article ?')) return;
    try {
      await remove(id);
      setActionMenuId(null);
    } catch { /* ignore */ }
  };

  const SortHeader = ({ field, label }: { field: SortField; label: string }) => (
    <th
      className="pb-3 pr-4 cursor-pointer select-none hover:text-white transition-colors"
      onClick={() => toggleSort(field)}
    >
      <span className="inline-flex items-center gap-1">
        {label}
        {sortField === field && (
          <span className="text-violet">{sortDir === 'asc' ? '\u2191' : '\u2193'}</span>
        )}
      </span>
    </th>
  );

  return (
    <div className="p-4 md:p-6 space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between gap-3">
        <h2 className="font-title text-2xl font-bold text-white">Articles</h2>
        <button
          onClick={() => navigate('/content/articles/new')}
          className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Nouvel article
        </button>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3">
        <select
          value={filterStatus}
          onChange={e => setFilterStatus(e.target.value)}
          className={inputClass}
        >
          {STATUS_OPTIONS.map(o => (
            <option key={o.value} value={o.value}>{o.label}</option>
          ))}
        </select>
        <select
          value={filterLanguage}
          onChange={e => setFilterLanguage(e.target.value)}
          className={inputClass}
        >
          {LANGUAGE_OPTIONS.map(o => (
            <option key={o.value} value={o.value}>{o.label}</option>
          ))}
        </select>
        <input
          type="text"
          placeholder="Pays..."
          value={filterCountry}
          onChange={e => setFilterCountry(e.target.value)}
          className={`${inputClass} w-32`}
        />
        <input
          type="text"
          placeholder="Rechercher..."
          value={filterSearch}
          onChange={e => setFilterSearch(e.target.value)}
          className={`${inputClass} w-48`}
        />
        <span className="text-xs text-muted ml-auto">
          {pagination.total} article{pagination.total !== 1 ? 's' : ''}
        </span>
      </div>

      {/* Bulk actions */}
      {selected.size > 0 && (
        <div className="flex items-center gap-3 bg-surface border border-border rounded-lg px-4 py-2">
          <span className="text-sm text-white">{selected.size} selectionne(s)</span>
          <button
            onClick={handleBulkDelete}
            className="px-3 py-1 bg-danger/20 text-danger text-xs rounded-lg hover:bg-danger/30 transition-colors"
          >
            Supprimer
          </button>
        </div>
      )}

      {/* Error */}
      {error && (
        <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-lg">
          {error}
        </div>
      )}

      {/* Table */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        {loading ? (
          <div className="p-8 text-center text-muted text-sm">Chargement...</div>
        ) : sortedArticles.length === 0 ? (
          <div className="p-10 text-center">
            <p className="text-muted text-sm mb-3">Aucun article trouve</p>
            <button
              onClick={() => navigate('/content/articles/new')}
              className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
            >
              Generer votre premier article
            </button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pl-4 pr-2">
                    <input
                      type="checkbox"
                      checked={selected.size === sortedArticles.length && sortedArticles.length > 0}
                      onChange={toggleAll}
                      className="accent-violet"
                    />
                  </th>
                  <SortHeader field="title" label="Titre" />
                  <SortHeader field="content_type" label="Type" />
                  <SortHeader field="language" label="Langue" />
                  <SortHeader field="country" label="Pays" />
                  <SortHeader field="status" label="Statut" />
                  <SortHeader field="seo_score" label="SEO" />
                  <SortHeader field="quality_score" label="Qualite" />
                  <SortHeader field="word_count" label="Mots" />
                  <SortHeader field="created_at" label="Cree le" />
                  <th className="pb-3 pr-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                {sortedArticles.map(article => (
                  <tr
                    key={article.id}
                    className="border-b border-border/50 hover:bg-surface2/50 transition-colors"
                  >
                    <td className="py-3 pl-4 pr-2">
                      <input
                        type="checkbox"
                        checked={selected.has(article.id)}
                        onChange={() => toggleSelect(article.id)}
                        className="accent-violet"
                      />
                    </td>
                    <td
                      className="py-3 pr-4 cursor-pointer"
                      onClick={() => navigate(`/content/articles/${article.id}`)}
                    >
                      <span className="text-white font-medium hover:text-violet transition-colors truncate block max-w-[220px]">
                        {article.title}
                      </span>
                    </td>
                    <td className="py-3 pr-4 text-muted">{TYPE_LABELS[article.content_type] || article.content_type}</td>
                    <td className="py-3 pr-4 text-muted uppercase">{article.language}</td>
                    <td className="py-3 pr-4 text-muted">{article.country || '-'}</td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[article.status]}`}>
                        {STATUS_LABELS[article.status]}
                      </span>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${seoBgColor(article.seo_score)}`}>
                        {article.seo_score}
                      </span>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${seoBgColor(article.quality_score)}`}>
                        {article.quality_score}
                      </span>
                    </td>
                    <td className="py-3 pr-4 text-muted">{article.word_count.toLocaleString()}</td>
                    <td className="py-3 pr-4 text-muted">
                      {new Date(article.created_at).toLocaleDateString('fr-FR')}
                    </td>
                    <td className="py-3 pr-4 relative">
                      <button
                        onClick={() => setActionMenuId(actionMenuId === article.id ? null : article.id)}
                        className="text-muted hover:text-white transition-colors p-1"
                      >
                        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                          <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                        </svg>
                      </button>
                      {actionMenuId === article.id && (
                        <div className="absolute right-0 top-full mt-1 z-20 bg-surface2 border border-border rounded-lg shadow-xl py-1 min-w-[140px]">
                          <button
                            onClick={() => { navigate(`/content/articles/${article.id}`); setActionMenuId(null); }}
                            className="w-full text-left px-4 py-2 text-sm text-white hover:bg-surface transition-colors"
                          >
                            Voir
                          </button>
                          <button
                            onClick={() => { navigate(`/content/articles/${article.id}?edit=1`); setActionMenuId(null); }}
                            className="w-full text-left px-4 py-2 text-sm text-white hover:bg-surface transition-colors"
                          >
                            Modifier
                          </button>
                          <button
                            onClick={async () => {
                              try {
                                await (await import('../../api/contentApi')).duplicateArticle(article.id);
                                fetchArticles(pagination.current_page);
                              } catch { /* ignore */ }
                              setActionMenuId(null);
                            }}
                            className="w-full text-left px-4 py-2 text-sm text-white hover:bg-surface transition-colors"
                          >
                            Dupliquer
                          </button>
                          <button
                            onClick={() => handleDelete(article.id)}
                            className="w-full text-left px-4 py-2 text-sm text-danger hover:bg-surface transition-colors"
                          >
                            Supprimer
                          </button>
                        </div>
                      )}
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
            onClick={() => fetchArticles(pagination.current_page - 1)}
            className="px-3 py-1.5 bg-surface2 text-muted hover:text-white text-xs rounded-lg border border-border transition-colors disabled:opacity-30"
          >
            Precedent
          </button>
          {Array.from({ length: pagination.last_page }, (_, i) => i + 1)
            .filter(p => p === 1 || p === pagination.last_page || Math.abs(p - pagination.current_page) <= 2)
            .map((page, idx, arr) => (
              <React.Fragment key={page}>
                {idx > 0 && arr[idx - 1] !== page - 1 && (
                  <span className="text-muted text-xs">...</span>
                )}
                <button
                  onClick={() => fetchArticles(page)}
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
            onClick={() => fetchArticles(pagination.current_page + 1)}
            className="px-3 py-1.5 bg-surface2 text-muted hover:text-white text-xs rounded-lg border border-border transition-colors disabled:opacity-30"
          >
            Suivant
          </button>
        </div>
      )}
    </div>
  );
}
