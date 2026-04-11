import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  fetchQaEntries,
  deleteQaEntry,
  publishQaEntry,
  generateQaFromArticle,
  generateQaFromPaa,
} from '../../api/contentApi';
import type { QaEntry, QaSourceType, ContentStatus, PaginatedResponse } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { Modal } from '../../ui/Modal';
import { Button } from '../../ui/Button';
import { errMsg } from './helpers';

// ── Constants ───────────────────────────────────────────────
const STATUS_COLORS: Record<ContentStatus, string> = {
  draft: 'bg-muted/20 text-muted',
  generating: 'bg-amber/20 text-amber animate-pulse',
  review: 'bg-blue-500/20 text-blue-400',
  scheduled: 'bg-violet/20 text-violet-light',
  published: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted line-through',
};

const STATUS_LABELS: Record<ContentStatus, string> = {
  draft: 'Brouillon',
  generating: 'Generation...',
  review: 'A relire',
  scheduled: 'Planifie',
  published: 'Publie',
  archived: 'Archive',
};

const SOURCE_COLORS: Record<QaSourceType, string> = {
  article_faq: 'bg-violet/20 text-violet-light',
  paa: 'bg-blue-500/20 text-blue-400',
  scraped: 'bg-amber/20 text-amber',
  manual: 'bg-success/20 text-success',
  ai_suggested: 'bg-pink-500/20 text-pink-400',
};

const SOURCE_LABELS: Record<QaSourceType, string> = {
  article_faq: 'FAQ Article',
  paa: 'PAA',
  scraped: 'Scraped',
  manual: 'Manuel',
  ai_suggested: 'IA suggere',
};

const STATUS_OPTIONS = [
  { value: '', label: 'Tous les statuts' },
  { value: 'draft', label: 'Brouillon' },
  { value: 'generating', label: 'Generation' },
  { value: 'review', label: 'A relire' },
  { value: 'scheduled', label: 'Planifie' },
  { value: 'published', label: 'Publie' },
  { value: 'archived', label: 'Archive' },
];

const SOURCE_OPTIONS = [
  { value: '', label: 'Toutes les sources' },
  { value: 'article_faq', label: 'FAQ Article' },
  { value: 'paa', label: 'PAA' },
  { value: 'scraped', label: 'Scraped' },
  { value: 'manual', label: 'Manuel' },
  { value: 'ai_suggested', label: 'IA suggere' },
];

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

function formatNumber(n: number): string {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
  if (n >= 1_000) return (n / 1_000).toFixed(1) + 'K';
  return String(n);
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ── Component ───────────────────────────────────────────────
export default function QaList() {
  const navigate = useNavigate();
  const [entries, setEntries] = useState<QaEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [search, setSearch] = useState('');
  const [filterLanguage, setFilterLanguage] = useState('');
  const [filterCountry, setFilterCountry] = useState('');
  const [filterCategory, setFilterCategory] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [filterSource, setFilterSource] = useState('');
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [showFromArticle, setShowFromArticle] = useState(false);
  const [articleIdInput, setArticleIdInput] = useState('');
  const [showFromPaa, setShowFromPaa] = useState(false);
  const [paaTopic, setPaaTopic] = useState('');
  const [paaCountry, setPaaCountry] = useState('');
  const [paaLanguage, setPaaLanguage] = useState('');
  const [modalLoading, setModalLoading] = useState(false);
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; action: () => void } | null>(null);

  const loadEntries = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string | number> = { page };
      if (search) params.search = search;
      if (filterLanguage) params.language = filterLanguage;
      if (filterCountry) params.country = filterCountry;
      if (filterCategory) params.category = filterCategory;
      if (filterStatus) params.status = filterStatus;
      if (filterSource) params.source_type = filterSource;
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
  }, [search, filterLanguage, filterCountry, filterCategory, filterStatus, filterSource]);

  useEffect(() => { loadEntries(1); }, [loadEntries]);

  const handleDelete = (id: number) => {
    setConfirmAction({
      title: 'Supprimer cette Q&A',
      message: 'Cette action est irreversible. Confirmer la suppression ?',
      action: async () => {
        setActionLoading(id);
        try {
          await deleteQaEntry(id);
          toast('success', 'Q&A supprimee.');
          loadEntries(pagination.current_page);
        } catch (err) {
          toast('error', errMsg(err));
        } finally {
          setActionLoading(null);
        }
      },
    });
  };

  const handlePublish = async (id: number) => {
    setActionLoading(id);
    try {
      await publishQaEntry(id);
      toast('success', 'Q&A publiee.');
      loadEntries(pagination.current_page);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleFromArticle = async () => {
    if (!articleIdInput) return;
    setModalLoading(true);
    try {
      await generateQaFromArticle(Number(articleIdInput));
      toast('success', 'Generation Q&A lancee.');
      setShowFromArticle(false);
      setArticleIdInput('');
      loadEntries(1);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setModalLoading(false);
    }
  };

  const handleFromPaa = async () => {
    if (!paaTopic || !paaCountry) return;
    setModalLoading(true);
    try {
      await generateQaFromPaa({ topic: paaTopic, country: paaCountry, language: paaLanguage || undefined });
      toast('success', 'Generation Q&A PAA lancee.');
      setShowFromPaa(false);
      setPaaTopic('');
      setPaaCountry('');
      setPaaLanguage('');
      loadEntries(1);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setModalLoading(false);
    }
  };

  // Compute stats from current page
  const totalQa = pagination.total;
  const publishedCount = entries.filter(e => e.status === 'published').length;
  const avgSeo = entries.length > 0 ? Math.round(entries.reduce((s, e) => s + e.seo_score, 0) / entries.length) : 0;
  const sourceBreakdown = entries.reduce<Record<string, number>>((acc, e) => {
    acc[e.source_type] = (acc[e.source_type] || 0) + 1;
    return acc;
  }, {});

  const statCards = [
    { label: 'Total Q&A', value: formatNumber(totalQa), color: 'text-violet bg-violet/20' },
    { label: 'Publiees', value: publishedCount, color: 'text-success bg-success/20' },
    { label: 'SEO moyen', value: avgSeo + '/100', color: 'text-blue-400 bg-blue-500/20' },
    {
      label: 'Par source',
      value: Object.entries(sourceBreakdown).map(([k, v]) => `${SOURCE_LABELS[k as QaSourceType] ?? k}: ${v}`).join(', ') || '-',
      color: 'text-amber bg-amber/20',
      small: true,
    },
  ];

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <div>
          <button onClick={() => navigate('/content')} className="text-xs text-muted hover:text-white transition-colors mb-2 inline-flex items-center gap-1">
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Content Hub
          </button>
          <h2 className="font-title text-2xl font-bold text-white">Q&A</h2>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={() => setShowFromArticle(true)}
            className="px-4 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg transition-colors"
          >
            + Depuis FAQ article
          </button>
          <button
            onClick={() => setShowFromPaa(true)}
            className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
          >
            + Depuis PAA
          </button>
        </div>
      </div>

      {/* Modal: From Article */}
      <Modal
        open={showFromArticle}
        onClose={() => setShowFromArticle(false)}
        title="Generer Q&A depuis un article"
        description="Entrez l'ID de l'article dont les FAQ seront extraites."
        size="md"
        footer={
          <>
            <Button variant="ghost" onClick={() => setShowFromArticle(false)}>Annuler</Button>
            <Button variant="primary" onClick={handleFromArticle} disabled={!articleIdInput} loading={modalLoading}>
              Generer
            </Button>
          </>
        }
      >
        <input
          type="number"
          value={articleIdInput}
          onChange={e => setArticleIdInput(e.target.value)}
          placeholder="ID article"
          className={inputClass + ' w-full'}
        />
      </Modal>

      {/* Modal: From PAA */}
      <Modal
        open={showFromPaa}
        onClose={() => setShowFromPaa(false)}
        title="Generer Q&A depuis PAA"
        description="Les questions People Also Ask seront generees pour le sujet donne."
        size="md"
        footer={
          <>
            <Button variant="ghost" onClick={() => setShowFromPaa(false)}>Annuler</Button>
            <Button variant="primary" onClick={handleFromPaa} disabled={!paaTopic || !paaCountry} loading={modalLoading}>
              Generer
            </Button>
          </>
        }
      >
        <div className="space-y-3">
          <input value={paaTopic} onChange={e => setPaaTopic(e.target.value)} placeholder="Sujet (ex: visa france)" className={inputClass + ' w-full'} />
          <input value={paaCountry} onChange={e => setPaaCountry(e.target.value)} placeholder="Pays (ex: france)" className={inputClass + ' w-full'} />
          <input value={paaLanguage} onChange={e => setPaaLanguage(e.target.value)} placeholder="Langue (optionnel, ex: fr)" className={inputClass + ' w-full'} />
        </div>
      </Modal>

      {/* Stat cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {statCards.map(card => (
          <div key={card.label} className="bg-surface border border-border rounded-xl p-5">
            <span className="text-xs text-muted uppercase tracking-wide">{card.label}</span>
            <p className={`${card.small ? 'text-sm' : 'text-2xl'} font-bold text-white mt-2`}>{card.value}</p>
          </div>
        ))}
      </div>

      {/* Filters */}
      <div className="flex items-center gap-3 flex-wrap">
        <input
          type="text"
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder="Rechercher..."
          className={inputClass + ' w-48'}
        />
        <select value={filterLanguage} onChange={e => setFilterLanguage(e.target.value)} className={inputClass}>
          <option value="">Toutes les langues</option>
          <option value="fr">Francais</option>
          <option value="en">English</option>
          <option value="es">Espanol</option>
          <option value="de">Deutsch</option>
          <option value="pt">Portugues</option>
        </select>
        <input
          type="text"
          value={filterCountry}
          onChange={e => setFilterCountry(e.target.value)}
          placeholder="Pays..."
          className={inputClass + ' w-32'}
        />
        <input
          type="text"
          value={filterCategory}
          onChange={e => setFilterCategory(e.target.value)}
          placeholder="Categorie..."
          className={inputClass + ' w-32'}
        />
        <select value={filterStatus} onChange={e => setFilterStatus(e.target.value)} className={inputClass}>
          {STATUS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select value={filterSource} onChange={e => setFilterSource(e.target.value)} className={inputClass}>
          {SOURCE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {/* Table */}
      <div className="bg-surface border border-border rounded-xl p-5">
        {error && (
          <div className="flex items-center justify-between bg-danger/10 border border-danger/30 rounded-lg p-3 mb-4">
            <p className="text-danger text-sm">{error}</p>
            <button onClick={() => loadEntries(1)} className="text-xs text-danger hover:text-red-300 transition-colors">Reessayer</button>
          </div>
        )}
        {loading ? (
          <div className="space-y-3">
            {[1, 2, 3, 4, 5].map(i => (
              <div key={i} className="animate-pulse bg-surface2 rounded-lg h-12" />
            ))}
          </div>
        ) : entries.length === 0 ? (
          <div className="text-center py-10">
            <p className="text-muted text-sm mb-3">Aucune Q&A trouvee. Generez-en depuis un article ou PAA.</p>
            <div className="flex items-center justify-center gap-2">
              <button onClick={() => setShowFromArticle(true)} className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg transition-colors">
                Depuis FAQ article
              </button>
              <button onClick={() => setShowFromPaa(true)} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
                Depuis PAA
              </button>
            </div>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pr-4">Question</th>
                  <th className="pb-3 pr-4">Pays</th>
                  <th className="pb-3 pr-4">Categorie</th>
                  <th className="pb-3 pr-4">Source</th>
                  <th className="pb-3 pr-4">Statut</th>
                  <th className="pb-3 pr-4">SEO</th>
                  <th className="pb-3 pr-4">Mots</th>
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
                    <td className="py-3 pr-4">
                      <span className="text-white font-medium truncate block max-w-[300px]">
                        {entry.question.length > 80 ? entry.question.slice(0, 80) + '...' : entry.question}
                      </span>
                    </td>
                    <td className="py-3 pr-4 text-muted capitalize">{entry.country ?? '-'}</td>
                    <td className="py-3 pr-4">
                      {entry.category ? (
                        <span className="inline-block px-1.5 py-0.5 rounded text-[10px] bg-violet/20 text-violet-light capitalize">{entry.category}</span>
                      ) : (
                        <span className="text-muted">-</span>
                      )}
                    </td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${SOURCE_COLORS[entry.source_type]}`}>
                        {SOURCE_LABELS[entry.source_type]}
                      </span>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[entry.status]}`}>
                        {STATUS_LABELS[entry.status]}
                      </span>
                    </td>
                    <td className="py-3 pr-4">
                      <div className="flex items-center gap-2">
                        <div className="w-16 h-1.5 bg-surface2 rounded-full overflow-hidden">
                          <div
                            className="h-full bg-violet rounded-full"
                            style={{ width: `${Math.min(entry.seo_score, 100)}%` }}
                          />
                        </div>
                        <span className="text-xs text-muted">{entry.seo_score}</span>
                      </div>
                    </td>
                    <td className="py-3 pr-4 text-white">{formatNumber(entry.word_count)}</td>
                    <td className="py-3">
                      <div className="flex items-center gap-2" onClick={e => e.stopPropagation()}>
                        <button onClick={() => navigate(`/content/qa/${entry.id}`)} className="text-xs text-violet hover:text-violet-light transition-colors">
                          Voir
                        </button>
                        {entry.status === 'draft' && (
                          <button
                            onClick={() => handlePublish(entry.id)}
                            disabled={actionLoading === entry.id}
                            className="text-xs text-success hover:text-green-300 transition-colors disabled:opacity-50"
                          >
                            Publier
                          </button>
                        )}
                        <button
                          onClick={() => handleDelete(entry.id)}
                          disabled={actionLoading === entry.id}
                          className="text-xs text-danger hover:text-red-300 transition-colors disabled:opacity-50"
                        >
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
              <button
                onClick={() => loadEntries(pagination.current_page - 1)}
                disabled={pagination.current_page <= 1}
                className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40"
              >
                Precedent
              </button>
              <span className="px-3 py-1 text-xs text-muted">
                {pagination.current_page} / {pagination.last_page}
              </span>
              <button
                onClick={() => loadEntries(pagination.current_page + 1)}
                disabled={pagination.current_page >= pagination.last_page}
                className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40"
              >
                Suivant
              </button>
            </div>
          </div>
        )}
      </div>

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
