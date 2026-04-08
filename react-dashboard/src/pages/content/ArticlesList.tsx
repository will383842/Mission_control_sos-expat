import React, { useEffect, useState, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { fetchArticles, deleteArticle, bulkDeleteArticles, duplicateArticle } from '../../api/contentApi';
import type { GeneratedArticle, ContentStatus, ContentType, PaginatedResponse } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { inputClass, seoBarColor, errMsg } from './helpers';

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
  { value: 'generating', label: 'En generation' },
  { value: 'review', label: 'En revue' },
  { value: 'scheduled', label: 'Planifie' },
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
  { value: 'ru', label: 'Russe' },
  { value: 'zh', label: 'Chinois' },
  { value: 'ar', label: 'Arabe' },
  { value: 'hi', label: 'Hindi' },
];

const TYPE_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'Tous les types' },
  { value: 'article', label: 'Article' },
  { value: 'guide', label: 'Guide' },
  { value: 'news', label: 'Actualite' },
  { value: 'tutorial', label: 'Tutoriel' },
];

const TYPE_COLORS: Record<ContentType, string> = {
  article: 'bg-violet/20 text-violet-light',
  guide: 'bg-blue-500/20 text-blue-400',
  news: 'bg-amber/20 text-amber',
  tutorial: 'bg-success/20 text-success',
};

type SortField = 'title' | 'seo_score' | 'quality_score' | 'word_count' | 'created_at';

type ArticlesTab = 'sources' | 'generation' | 'generated';
const ARTICLES_TABS: { key: ArticlesTab; label: string; emoji: string }[] = [
  { key: 'sources', label: 'Sources', emoji: '📋' },
  { key: 'generation', label: 'Génération', emoji: '⚡' },
  { key: 'generated', label: 'Contenus générés', emoji: '✅' },
];

// ── Component ───────────────────────────────────────────────
export default function ArticlesList() {
  const navigate = useNavigate();
  const [tab, setTab] = useState<ArticlesTab>('generated');
  const [articles, setArticles] = useState<GeneratedArticle[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });

  // Filters
  const [search, setSearch] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [filterLang, setFilterLang] = useState('');
  const [filterCountry, setFilterCountry] = useState('');
  const [filterType, setFilterType] = useState('');
  const [sortBy, setSortBy] = useState<SortField>('created_at');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc');
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Bulk selection
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; action: () => void } | null>(null);

  const loadArticles = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string | number> = { page, sort: sortBy, direction: sortDir };
      if (search) params.search = search;
      if (filterStatus) params.status = filterStatus;
      if (filterLang) params.language = filterLang;
      if (filterCountry) params.country = filterCountry;
      if (filterType) params.content_type = filterType;
      const res = await fetchArticles(params as Record<string, string>);
      const data = res.data as unknown as PaginatedResponse<GeneratedArticle>;
      setArticles(data.data);
      setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
      setSelectedIds(new Set());
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur de chargement';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [search, filterStatus, filterLang, filterCountry, filterType, sortBy, sortDir]);

  useEffect(() => { loadArticles(1); }, [loadArticles]);

  const handleSearchInput = (val: string) => {
    setSearch(val);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => loadArticles(1), 400);
  };

  const handleSort = (field: SortField) => {
    if (sortBy === field) {
      setSortDir(d => d === 'asc' ? 'desc' : 'asc');
    } else {
      setSortBy(field);
      setSortDir('desc');
    }
  };

  const sortIcon = (field: SortField) => {
    if (sortBy !== field) return '';
    return sortDir === 'asc' ? ' \u25B2' : ' \u25BC';
  };

  const toggleSelect = (id: number) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  };

  const toggleSelectAll = () => {
    if (selectedIds.size === articles.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(articles.map(a => a.id)));
    }
  };

  const handleDelete = (id: number) => {
    setConfirmAction({
      title: 'Supprimer cet article',
      message: 'Cette action est irreversible. Confirmer la suppression ?',
      action: async () => {
        setActionLoading(id);
        try {
          await deleteArticle(id);
          toast('success', 'Article supprime.');
          loadArticles(pagination.current_page);
        } catch (err) {
          toast('error', errMsg(err));
        } finally {
          setActionLoading(null);
        }
      },
    });
  };

  const handleDuplicate = async (id: number) => {
    setActionLoading(id);
    try {
      const res = await duplicateArticle(id);
      const newArticle = res.data as unknown as GeneratedArticle;
      toast('success', 'Article duplique.');
      navigate(`/content/articles/${newArticle.id}`);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleBulkDelete = () => {
    if (selectedIds.size === 0) return;
    setConfirmAction({
      title: 'Suppression en masse',
      message: `Supprimer ${selectedIds.size} article(s) ? Cette action est irreversible.`,
      action: async () => {
        try {
          await bulkDeleteArticles(Array.from(selectedIds));
          toast('success', `${selectedIds.size} article(s) supprime(s).`);
          setSelectedIds(new Set());
          loadArticles(pagination.current_page);
        } catch (err) {
          toast('error', errMsg(err));
        }
      },
    });
  };

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <h2 className="font-title text-2xl font-bold text-white">Articles</h2>
        <button
          onClick={() => navigate('/content/articles/new')}
          className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Nouvel article
        </button>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 bg-surface/40 backdrop-blur rounded-xl p-1 border border-border/20">
        {ARTICLES_TABS.map(t => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition-all ${
              tab === t.key
                ? 'bg-violet/20 text-violet-light border border-violet/30 shadow-lg shadow-violet/5'
                : 'text-muted hover:text-white'
            }`}
          >
            <span>{t.emoji}</span> {t.label}
          </button>
        ))}
      </div>

      {/* 📋 Sources */}
      {tab === 'sources' && (
        <div className="bg-surface border border-border rounded-xl p-6 space-y-4">
          <h3 className="text-lg font-semibold text-white">Source des articles</h3>
          <p className="text-sm text-muted">Les articles sont créés manuellement ou générés depuis les autres pages de contenu (mots-clés, longues traînes, Q/R, fiches pays, etc.).</p>
          <div className="grid grid-cols-3 gap-4">
            <div className="bg-surface2/30 border border-border/20 rounded-xl p-4 text-center">
              <div className="text-2xl font-bold text-white">{pagination.total}</div>
              <div className="text-xs text-muted">Total articles</div>
            </div>
            <div className="bg-surface2/30 border border-border/20 rounded-xl p-4 text-center">
              <div className="text-2xl font-bold text-success">{articles.filter(a => a.status === 'published').length}</div>
              <div className="text-xs text-muted">Publiés (page)</div>
            </div>
            <div className="bg-surface2/30 border border-border/20 rounded-xl p-4 text-center">
              <div className="text-2xl font-bold text-amber">{articles.filter(a => a.status === 'draft').length}</div>
              <div className="text-xs text-muted">Brouillons (page)</div>
            </div>
          </div>
        </div>
      )}

      {/* ⚡ Génération */}
      {tab === 'generation' && (
        <div className="bg-surface border border-border rounded-xl p-6 space-y-4">
          <h3 className="text-lg font-semibold text-white">Créer un article</h3>
          <p className="text-sm text-muted">Créez un article manuellement avec le formulaire ou utilisez les autres pages de contenu pour une génération automatisée.</p>
          <button
            onClick={() => navigate('/content/articles/new')}
            className="px-6 py-3 rounded-xl bg-violet text-white font-semibold hover:bg-violet/80 transition-all"
          >
            + Nouvel article
          </button>
        </div>
      )}

      {/* ✅ Contenus générés */}
      {tab === 'generated' && (<>
      {/* Filter bar */}
      <div className="flex items-center gap-3 flex-wrap">
        <input
          type="text"
          placeholder="Rechercher..."
          value={search}
          onChange={e => handleSearchInput(e.target.value)}
          className={inputClass + ' min-w-[200px]'}
        />
        <select value={filterStatus} onChange={e => setFilterStatus(e.target.value)} className={inputClass}>
          {STATUS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select value={filterLang} onChange={e => setFilterLang(e.target.value)} className={inputClass}>
          {LANG_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <input
          type="text"
          placeholder="Pays..."
          value={filterCountry}
          onChange={e => setFilterCountry(e.target.value)}
          className={inputClass + ' w-28'}
        />
        <select value={filterType} onChange={e => setFilterType(e.target.value)} className={inputClass}>
          {TYPE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {/* Bulk actions bar */}
      {selectedIds.size > 0 && (
        <div className="flex items-center gap-4 bg-surface border border-violet/30 rounded-xl px-4 py-3">
          <span className="text-sm text-white font-medium">{selectedIds.size} selectionne(s)</span>
          <button onClick={handleBulkDelete} className="px-3 py-1 text-xs bg-danger/20 text-danger hover:bg-danger/30 rounded-lg transition-colors">
            Supprimer la selection
          </button>
        </div>
      )}

      {/* Table */}
      <div className="bg-surface border border-border rounded-xl p-5">
        {error && (
          <div className="flex items-center justify-between bg-danger/10 border border-danger/30 rounded-lg p-3 mb-4">
            <p className="text-danger text-sm">{error}</p>
            <button onClick={() => loadArticles(1)} className="text-xs text-danger hover:text-red-300 transition-colors">Reessayer</button>
          </div>
        )}

        {loading ? (
          <div className="space-y-3">
            {[1, 2, 3, 4, 5, 6, 7].map(i => (
              <div key={i} className="animate-pulse bg-surface2 rounded-lg h-12" />
            ))}
          </div>
        ) : articles.length === 0 ? (
          <div className="text-center py-10">
            <p className="text-muted text-sm mb-3">Aucun article genere. Commencez par creer un article ou lancer un cluster.</p>
            <button onClick={() => navigate('/content/articles/new')} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
              Creer un article
            </button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pr-2">
                    <input type="checkbox" checked={selectedIds.size === articles.length && articles.length > 0} onChange={toggleSelectAll} className="accent-violet" />
                  </th>
                  <th className="pb-3 pr-4 cursor-pointer hover:text-white transition-colors" onClick={() => handleSort('title')}>
                    Titre{sortIcon('title')}
                  </th>
                  <th className="pb-3 pr-4">Type</th>
                  <th className="pb-3 pr-4">Langue</th>
                  <th className="pb-3 pr-4">Pays</th>
                  <th className="pb-3 pr-4">Statut</th>
                  <th className="pb-3 pr-4 cursor-pointer hover:text-white transition-colors" onClick={() => handleSort('seo_score')}>
                    SEO{sortIcon('seo_score')}
                  </th>
                  <th className="pb-3 pr-4 cursor-pointer hover:text-white transition-colors" onClick={() => handleSort('quality_score')}>
                    Qualite{sortIcon('quality_score')}
                  </th>
                  <th className="pb-3 pr-4 cursor-pointer hover:text-white transition-colors" onClick={() => handleSort('word_count')}>
                    Mots{sortIcon('word_count')}
                  </th>
                  <th className="pb-3 pr-4 cursor-pointer hover:text-white transition-colors" onClick={() => handleSort('created_at')}>
                    Date{sortIcon('created_at')}
                  </th>
                  <th className="pb-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                {articles.map(article => (
                  <tr key={article.id} className="border-b border-border/50 hover:bg-surface2/50 transition-colors">
                    <td className="py-3 pr-2">
                      <input type="checkbox" checked={selectedIds.has(article.id)} onChange={() => toggleSelect(article.id)} className="accent-violet" />
                    </td>
                    <td className="py-3 pr-4 cursor-pointer" onClick={() => navigate(`/content/articles/${article.id}`)}>
                      <span className="text-white font-medium truncate block max-w-[250px] hover:text-violet-light transition-colors">{article.title}</span>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-1.5 py-0.5 rounded text-[10px] ${TYPE_COLORS[article.content_type] ?? 'bg-muted/20 text-muted'}`}>
                        {article.content_type}
                      </span>
                    </td>
                    <td className="py-3 pr-4">
                      <span className="inline-block px-1.5 py-0.5 rounded text-[10px] bg-violet/20 text-violet-light">{article.language.toUpperCase()}</span>
                    </td>
                    <td className="py-3 pr-4 text-muted text-xs capitalize">{article.country ?? '-'}</td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[article.status]}`}>{STATUS_LABELS[article.status]}</span>
                    </td>
                    <td className="py-3 pr-4">
                      <div className="flex items-center gap-2">
                        <div className="w-12 h-1.5 bg-surface2 rounded-full overflow-hidden">
                          <div className={`h-full rounded-full ${seoBarColor(article.seo_score)}`} style={{ width: `${article.seo_score}%` }} />
                        </div>
                        <span className="text-xs text-muted">{article.seo_score}</span>
                      </div>
                    </td>
                    <td className="py-3 pr-4 text-white text-xs">{article.quality_score}</td>
                    <td className="py-3 pr-4 text-muted text-xs">{article.word_count.toLocaleString('fr-FR')}</td>
                    <td className="py-3 pr-4 text-muted text-xs">
                      {article.published_at
                        ? new Date(article.published_at).toLocaleDateString('fr-FR')
                        : new Date(article.created_at).toLocaleDateString('fr-FR')}
                    </td>
                    <td className="py-3">
                      <div className="flex items-center gap-2">
                        <button onClick={() => navigate(`/content/articles/${article.id}`)} className="text-xs text-violet hover:text-violet-light transition-colors">Voir</button>
                        <button onClick={() => handleDuplicate(article.id)} disabled={actionLoading === article.id} className="text-xs text-blue-400 hover:text-blue-300 transition-colors disabled:opacity-50">Dupliquer</button>
                        <button onClick={() => handleDelete(article.id)} disabled={actionLoading === article.id} className="text-xs text-danger hover:text-red-300 transition-colors disabled:opacity-50">Suppr</button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {pagination.last_page > 1 && (
          <div className="flex items-center justify-between mt-4 pt-4 border-t border-border">
            <span className="text-xs text-muted">{pagination.total} article(s)</span>
            <div className="flex gap-2">
              <button onClick={() => loadArticles(pagination.current_page - 1)} disabled={pagination.current_page <= 1} className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40">Precedent</button>
              <span className="px-3 py-1 text-xs text-muted">{pagination.current_page} / {pagination.last_page}</span>
              <button onClick={() => loadArticles(pagination.current_page + 1)} disabled={pagination.current_page >= pagination.last_page} className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40">Suivant</button>
            </div>
          </div>
        )}
      </div>
      </>)}

      <ConfirmModal
        open={!!confirmAction}
        title={confirmAction?.title ?? ''}
        message={confirmAction?.message ?? ''}
        variant="danger"
        confirmLabel="Supprimer"
        onConfirm={() => { confirmAction?.action(); setConfirmAction(null); }}
        onCancel={() => setConfirmAction(null)}
      />
    </div>
  );
}
