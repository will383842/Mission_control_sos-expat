import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  fetchQaEntries,
  deleteQaEntry,
  publishQaEntry,
  bulkPublishQa,
  generateQaFromArticle,
  generateQaFromPaa,
  fetchArticles,
} from '../../api/contentApi';
import type { QaEntry, QaSourceType, ContentStatus, PaginatedResponse, GeneratedArticle } from '../../types/content';

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

const SOURCE_TYPE_COLORS: Record<QaSourceType, string> = {
  article_faq: 'bg-violet/20 text-violet-light',
  paa: 'bg-amber/20 text-amber',
  scraped: 'bg-blue-500/20 text-blue-400',
  manual: 'bg-muted/20 text-muted',
  ai_suggested: 'bg-cyan/20 text-cyan',
};

const SOURCE_TYPE_LABELS: Record<QaSourceType, string> = {
  article_faq: 'FAQ Article',
  paa: 'PAA',
  scraped: 'Scrape',
  manual: 'Manuel',
  ai_suggested: 'IA',
};

const LANGUAGE_OPTIONS = [
  { value: '', label: 'Toutes les langues' },
  { value: 'fr', label: 'Francais' },
  { value: 'en', label: 'English' },
  { value: 'de', label: 'Deutsch' },
  { value: 'es', label: 'Espanol' },
  { value: 'pt', label: 'Portugues' },
];

const STATUS_OPTIONS = [
  { value: '', label: 'Tous les statuts' },
  { value: 'draft', label: 'Brouillon' },
  { value: 'review', label: 'A relire' },
  { value: 'published', label: 'Publie' },
  { value: 'archived', label: 'Archive' },
];

const SOURCE_OPTIONS = [
  { value: '', label: 'Toutes les sources' },
  { value: 'article_faq', label: 'FAQ Article' },
  { value: 'paa', label: 'PAA' },
  { value: 'scraped', label: 'Scrape' },
  { value: 'manual', label: 'Manuel' },
  { value: 'ai_suggested', label: 'IA' },
];

function seoBgColor(score: number) {
  if (score >= 80) return 'bg-success/20 text-success';
  if (score >= 60) return 'bg-amber/20 text-amber';
  return 'bg-danger/20 text-danger';
}

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

// ── Component ───────────────────────────────────────────────
export default function QaList() {
  const navigate = useNavigate();
  const [entries, setEntries] = useState<QaEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [selected, setSelected] = useState<Set<number>>(new Set());

  const [filterLanguage, setFilterLanguage] = useState('');
  const [filterCountry, setFilterCountry] = useState('');
  const [filterCategory, setFilterCategory] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [filterSource, setFilterSource] = useState('');
  const [filterSearch, setFilterSearch] = useState('');

  // Generate modals
  const [showFaqModal, setShowFaqModal] = useState(false);
  const [showPaaModal, setShowPaaModal] = useState(false);
  const [articlesList, setArticlesList] = useState<GeneratedArticle[]>([]);
  const [selectedArticleId, setSelectedArticleId] = useState<number | null>(null);
  const [paaTopic, setPaaTopic] = useState('');
  const [paaCountry, setPaaCountry] = useState('');
  const [genLoading, setGenLoading] = useState(false);

  const loadEntries = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string | number> = { page };
      if (filterLanguage) params.language = filterLanguage;
      if (filterCountry) params.country = filterCountry;
      if (filterCategory) params.category = filterCategory;
      if (filterStatus) params.status = filterStatus;
      if (filterSource) params.source_type = filterSource;
      if (filterSearch) params.search = filterSearch;
      const res = await fetchQaEntries(params);
      const data = res.data as unknown as PaginatedResponse<QaEntry>;
      setEntries(data.data);
      setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur de chargement';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [filterLanguage, filterCountry, filterCategory, filterStatus, filterSource, filterSearch]);

  useEffect(() => { loadEntries(1); }, [loadEntries]);

  const handleDelete = async (id: number) => {
    if (!window.confirm('Supprimer cette Q&A ?')) return;
    try {
      await deleteQaEntry(id);
      loadEntries(pagination.current_page);
    } catch {
      // silently handled
    }
  };

  const handlePublish = async (id: number) => {
    try {
      await publishQaEntry(id);
      loadEntries(pagination.current_page);
    } catch {
      // silently handled
    }
  };

  const handleBulkPublish = async () => {
    if (selected.size === 0) return;
    try {
      await bulkPublishQa(Array.from(selected));
      setSelected(new Set());
      loadEntries(pagination.current_page);
    } catch {
      // silently handled
    }
  };

  const openFaqModal = async () => {
    setShowFaqModal(true);
    try {
      const res = await fetchArticles({ status: 'published', page: 1 });
      const data = res.data as unknown as PaginatedResponse<GeneratedArticle>;
      setArticlesList(data.data);
    } catch {
      // silently handled
    }
  };

  const handleGenerateFromFaq = async () => {
    if (!selectedArticleId) return;
    setGenLoading(true);
    try {
      await generateQaFromArticle(selectedArticleId);
      setShowFaqModal(false);
      setSelectedArticleId(null);
      loadEntries(1);
    } catch {
      // silently handled
    } finally {
      setGenLoading(false);
    }
  };

  const handleGenerateFromPaa = async () => {
    if (!paaTopic || !paaCountry) return;
    setGenLoading(true);
    try {
      await generateQaFromPaa({ topic: paaTopic, country: paaCountry, language: filterLanguage || 'fr' });
      setShowPaaModal(false);
      setPaaTopic('');
      setPaaCountry('');
      loadEntries(1);
    } catch {
      // silently handled
    } finally {
      setGenLoading(false);
    }
  };

  const toggleSelect = (id: number) => {
    setSelected(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  };

  const toggleSelectAll = () => {
    if (selected.size === entries.length) {
      setSelected(new Set());
    } else {
      setSelected(new Set(entries.map(e => e.id)));
    }
  };

  // Stats
  const totalPublished = entries.filter(e => e.status === 'published').length;
  const bySourceType = entries.reduce<Record<string, number>>((acc, e) => {
    acc[e.source_type] = (acc[e.source_type] || 0) + 1;
    return acc;
  }, {});

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <h2 className="font-title text-2xl font-bold text-white">Questions & Reponses</h2>
        <div className="flex items-center gap-2">
          <button onClick={openFaqModal} className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
            + Depuis FAQ
          </button>
          <button onClick={() => setShowPaaModal(true)} className="px-4 py-1.5 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors">
            + Depuis PAA
          </button>
          <button onClick={() => navigate('/content/qa/new')} className="px-4 py-1.5 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors">
            + Manuel
          </button>
        </div>
      </div>

      {/* FAQ Modal */}
      {showFaqModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
          <div className="bg-surface border border-border rounded-xl p-6 w-full max-w-md space-y-4">
            <h3 className="font-title font-semibold text-white text-lg">Generer Q&A depuis FAQ</h3>
            <p className="text-sm text-muted">Selectionner un article publie pour extraire les FAQ en Q&A.</p>
            <select value={selectedArticleId ?? ''} onChange={e => setSelectedArticleId(Number(e.target.value) || null)} className={inputClass + ' w-full'}>
              <option value="">Selectionner un article</option>
              {articlesList.map(a => (
                <option key={a.id} value={a.id}>{a.title}</option>
              ))}
            </select>
            <div className="flex justify-end gap-3">
              <button onClick={() => setShowFaqModal(false)} className="px-4 py-2 text-sm text-muted hover:text-white transition-colors">Annuler</button>
              <button onClick={handleGenerateFromFaq} disabled={!selectedArticleId || genLoading} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50">
                {genLoading ? 'Generation...' : 'Generer'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* PAA Modal */}
      {showPaaModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
          <div className="bg-surface border border-border rounded-xl p-6 w-full max-w-md space-y-4">
            <h3 className="font-title font-semibold text-white text-lg">Generer Q&A depuis PAA</h3>
            <p className="text-sm text-muted">Entrez un sujet et un pays pour generer des Q&A basees sur les PAA Google.</p>
            <input type="text" value={paaTopic} onChange={e => setPaaTopic(e.target.value)} placeholder="Sujet (ex: visa schengen)" className={inputClass + ' w-full'} />
            <input type="text" value={paaCountry} onChange={e => setPaaCountry(e.target.value)} placeholder="Pays (ex: france)" className={inputClass + ' w-full'} />
            <div className="flex justify-end gap-3">
              <button onClick={() => setShowPaaModal(false)} className="px-4 py-2 text-sm text-muted hover:text-white transition-colors">Annuler</button>
              <button onClick={handleGenerateFromPaa} disabled={!paaTopic || !paaCountry || genLoading} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50">
                {genLoading ? 'Generation...' : 'Generer'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Total Q&A</span>
          <p className="text-2xl font-bold text-white mt-2">{pagination.total}</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Publiees</span>
          <p className="text-2xl font-bold text-success mt-2">{totalPublished}</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Sources</span>
          <div className="flex flex-wrap gap-1 mt-2">
            {Object.entries(bySourceType).map(([type, count]) => (
              <span key={type} className={`px-1.5 py-0.5 rounded text-[10px] font-medium ${SOURCE_TYPE_COLORS[type as QaSourceType] || 'text-muted'}`}>
                {SOURCE_TYPE_LABELS[type as QaSourceType] || type}: {count}
              </span>
            ))}
          </div>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Score SEO moyen</span>
          <p className="text-2xl font-bold text-white mt-2">
            {entries.length > 0 ? Math.round(entries.reduce((s, e) => s + e.seo_score, 0) / entries.length) : '-'}/100
          </p>
        </div>
      </div>

      {/* Filters */}
      <div className="flex items-center gap-3 flex-wrap">
        <select value={filterLanguage} onChange={e => setFilterLanguage(e.target.value)} className={inputClass}>
          {LANGUAGE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <input type="text" placeholder="Pays..." value={filterCountry} onChange={e => setFilterCountry(e.target.value)} className={inputClass + ' w-32'} />
        <input type="text" placeholder="Categorie..." value={filterCategory} onChange={e => setFilterCategory(e.target.value)} className={inputClass + ' w-32'} />
        <select value={filterStatus} onChange={e => setFilterStatus(e.target.value)} className={inputClass}>
          {STATUS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select value={filterSource} onChange={e => setFilterSource(e.target.value)} className={inputClass}>
          {SOURCE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <input type="text" placeholder="Rechercher..." value={filterSearch} onChange={e => setFilterSearch(e.target.value)} className={inputClass + ' w-48'} />
      </div>

      {/* Bulk actions */}
      {selected.size > 0 && (
        <div className="flex items-center gap-3 p-3 bg-violet/10 border border-violet/30 rounded-lg">
          <span className="text-sm text-violet-light">{selected.size} selectionne(s)</span>
          <button onClick={handleBulkPublish} className="px-3 py-1 text-xs bg-success/20 text-success rounded-lg hover:bg-success/30 transition-colors">
            Publier
          </button>
          <button onClick={() => setSelected(new Set())} className="px-3 py-1 text-xs text-muted hover:text-white transition-colors">
            Deselectionner
          </button>
        </div>
      )}

      {/* Table */}
      <div className="bg-surface border border-border rounded-xl p-5">
        {error && <p className="text-danger text-sm mb-3">{error}</p>}
        {loading ? (
          <div className="space-y-3">
            {[1, 2, 3, 4, 5].map(i => (
              <div key={i} className="animate-pulse bg-surface2 rounded-lg h-12" />
            ))}
          </div>
        ) : entries.length === 0 ? (
          <div className="text-center py-10">
            <p className="text-muted text-sm mb-3">Aucune Q&A trouvee</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pr-2">
                    <input type="checkbox" checked={selected.size === entries.length && entries.length > 0} onChange={toggleSelectAll} className="rounded" />
                  </th>
                  <th className="pb-3 pr-4">Question</th>
                  <th className="pb-3 pr-4">Pays</th>
                  <th className="pb-3 pr-4">Categorie</th>
                  <th className="pb-3 pr-4">Source</th>
                  <th className="pb-3 pr-4">Statut</th>
                  <th className="pb-3 pr-4">SEO</th>
                  <th className="pb-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                {entries.map(entry => (
                  <tr
                    key={entry.id}
                    className="border-b border-border/50 hover:bg-surface2/50 transition-colors cursor-pointer"
                    onClick={() => navigate(`/content/qa/${entry.id}`)}
                  >
                    <td className="py-3 pr-2" onClick={e => e.stopPropagation()}>
                      <input type="checkbox" checked={selected.has(entry.id)} onChange={() => toggleSelect(entry.id)} className="rounded" />
                    </td>
                    <td className="py-3 pr-4">
                      <span className="text-white font-medium truncate block max-w-[300px]">{entry.question}</span>
                    </td>
                    <td className="py-3 pr-4 text-muted capitalize">{entry.country || '-'}</td>
                    <td className="py-3 pr-4 text-muted capitalize">{entry.category || '-'}</td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${SOURCE_TYPE_COLORS[entry.source_type]}`}>
                        {SOURCE_TYPE_LABELS[entry.source_type]}
                      </span>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[entry.status]}`}>
                        {STATUS_LABELS[entry.status]}
                      </span>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${seoBgColor(entry.seo_score)}`}>
                        {entry.seo_score}/100
                      </span>
                    </td>
                    <td className="py-3">
                      <div className="flex items-center gap-2" onClick={e => e.stopPropagation()}>
                        <button onClick={() => navigate(`/content/qa/${entry.id}`)} className="text-xs text-violet hover:text-violet-light transition-colors">
                          Voir
                        </button>
                        {entry.status !== 'published' && (
                          <button onClick={() => handlePublish(entry.id)} className="text-xs text-success hover:text-green-300 transition-colors">
                            Publier
                          </button>
                        )}
                        <button onClick={() => handleDelete(entry.id)} className="text-xs text-danger hover:text-red-300 transition-colors">
                          Suppr
                        </button>
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
            <span className="text-xs text-muted">{pagination.total} Q&A</span>
            <div className="flex gap-2">
              <button onClick={() => loadEntries(pagination.current_page - 1)} disabled={pagination.current_page <= 1} className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40">
                Precedent
              </button>
              <span className="px-3 py-1 text-xs text-muted">{pagination.current_page} / {pagination.last_page}</span>
              <button onClick={() => loadEntries(pagination.current_page + 1)} disabled={pagination.current_page >= pagination.last_page} className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40">
                Suivant
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
