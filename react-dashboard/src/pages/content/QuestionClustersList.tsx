import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  fetchQuestionClusters,
  fetchQuestionClusterStats,
  autoClusterQuestions,
  generateQaFromQuestionCluster,
  generateArticleFromQuestionCluster,
  generateBothFromQuestionCluster,
  skipQuestionCluster,
  deleteQuestionCluster,
} from '../../api/contentApi';
import type { QuestionCluster, QuestionClusterStats, QuestionClusterStatus, PaginatedResponse } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { Modal } from '../../ui/Modal';
import { Button } from '../../ui/Button';
import { errMsg } from './helpers';

// ── Constants ───────────────────────────────────────────────
const QC_STATUS_COLORS: Record<QuestionClusterStatus, string> = {
  pending: 'bg-muted/20 text-muted',
  ready: 'bg-blue-500/20 text-blue-400',
  generating_qa: 'bg-amber/20 text-amber animate-pulse',
  generating_article: 'bg-amber/20 text-amber animate-pulse',
  completed: 'bg-success/20 text-success',
  skipped: 'bg-muted/20 text-muted line-through',
};

const QC_STATUS_LABELS: Record<QuestionClusterStatus, string> = {
  pending: 'En attente',
  ready: 'Pret',
  generating_qa: 'Generation Q&A...',
  generating_article: 'Generation article...',
  completed: 'Termine',
  skipped: 'Ignore',
};

const STATUS_OPTIONS = [
  { value: '', label: 'Tous les statuts' },
  { value: 'pending', label: 'En attente' },
  { value: 'ready', label: 'Pret' },
  { value: 'generating_qa', label: 'Generation Q&A' },
  { value: 'generating_article', label: 'Generation article' },
  { value: 'completed', label: 'Termine' },
  { value: 'skipped', label: 'Ignore' },
];

const SORT_OPTIONS = [
  { value: 'popularity', label: 'Popularite' },
  { value: 'views', label: 'Vues' },
  { value: 'questions', label: 'Questions' },
  { value: 'date', label: 'Date' },
];

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

function formatNumber(n: number): string {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
  if (n >= 1_000) return (n / 1_000).toFixed(1) + 'K';
  return String(n);
}

// ── Component ───────────────────────────────────────────────
export default function QuestionClustersList() {
  const navigate = useNavigate();
  const [clusters, setClusters] = useState<QuestionCluster[]>([]);
  const [stats, setStats] = useState<QuestionClusterStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [filterCountry, setFilterCountry] = useState('');
  const [filterCategory, setFilterCategory] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [sortBy, setSortBy] = useState('popularity');
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [showAutoCluster, setShowAutoCluster] = useState(false);
  const [autoCountrySlug, setAutoCountrySlug] = useState('');
  const [autoCategory, setAutoCategory] = useState('');
  const [autoLoading, setAutoLoading] = useState(false);
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; action: () => void } | null>(null);

  const loadStats = useCallback(async () => {
    try {
      const res = await fetchQuestionClusterStats();
      setStats(res.data as unknown as QuestionClusterStats);
    } catch {
      // stats are optional
    }
  }, []);

  const loadClusters = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string | number> = { page, sort: sortBy };
      if (filterCountry) params.country_slug = filterCountry;
      if (filterCategory) params.category = filterCategory;
      if (filterStatus) params.status = filterStatus;
      const res = await fetchQuestionClusters(params);
      const data = res.data as unknown as PaginatedResponse<QuestionCluster>;
      setClusters(data.data);
      setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur de chargement';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [filterCountry, filterCategory, filterStatus, sortBy]);

  useEffect(() => { loadStats(); }, [loadStats]);
  useEffect(() => { loadClusters(1); }, [loadClusters]);

  const handleAutoCluster = async () => {
    setAutoLoading(true);
    try {
      const data: { country_slug?: string; category?: string } = {};
      if (autoCountrySlug) data.country_slug = autoCountrySlug;
      if (autoCategory) data.category = autoCategory;
      await autoClusterQuestions(data);
      toast('success', 'Auto-clustering lance.');
      setShowAutoCluster(false);
      loadStats();
      loadClusters(1);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setAutoLoading(false);
    }
  };

  const handleGenerateQa = async (id: number) => {
    setActionLoading(id);
    try {
      await generateQaFromQuestionCluster(id);
      toast('success', 'Generation Q&A lancee.');
      loadClusters(pagination.current_page);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleGenerateArticle = async (id: number) => {
    setActionLoading(id);
    try {
      await generateArticleFromQuestionCluster(id);
      toast('success', 'Generation article lancee.');
      loadClusters(pagination.current_page);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleGenerateBoth = async (id: number) => {
    setActionLoading(id);
    try {
      await generateBothFromQuestionCluster(id);
      toast('success', 'Generation Q&A + article lancee.');
      loadClusters(pagination.current_page);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleSkip = async (id: number) => {
    setActionLoading(id);
    try {
      await skipQuestionCluster(id);
      toast('success', 'Cluster ignore.');
      loadClusters(pagination.current_page);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = (id: number) => {
    setConfirmAction({
      title: 'Supprimer ce cluster',
      message: 'Cette action est irreversible.',
      action: async () => {
        try {
          await deleteQuestionCluster(id);
          toast('success', 'Cluster supprime.');
          loadStats();
          loadClusters(pagination.current_page);
        } catch (err) {
          toast('error', errMsg(err));
        }
      },
    });
  };

  // Compute stat cards
  const totalClusters = stats?.total_clusters ?? clusters.length;
  const totalQuestions = clusters.reduce((s, c) => s + c.total_questions, 0);
  const totalViews = clusters.reduce((s, c) => s + c.total_views, 0);
  const avgPopularity = clusters.length > 0 ? Math.round(clusters.reduce((s, c) => s + c.popularity_score, 0) / clusters.length) : 0;

  const statCards = [
    { label: 'Total clusters', value: totalClusters, color: 'text-violet bg-violet/20' },
    { label: 'Total questions', value: totalQuestions, color: 'text-blue-400 bg-blue-500/20' },
    { label: 'Total vues', value: formatNumber(totalViews), color: 'text-amber bg-amber/20' },
    { label: 'Popularite moy.', value: avgPopularity, color: 'text-success bg-success/20' },
  ];

  // Country and category options from stats
  const countryOptions = [
    { value: '', label: 'Tous les pays' },
    ...(stats?.by_country ?? []).map(c => ({ value: c.country_slug, label: c.country })),
  ];

  const categoryOptions = [
    { value: '', label: 'Toutes les categories' },
    ...(stats?.by_category ?? []).map(c => ({ value: c.category, label: c.category })),
  ];

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <h2 className="font-title text-2xl font-bold text-white">Questions Forum — Clusters</h2>
        <button
          onClick={() => setShowAutoCluster(true)}
          className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Auto-cluster
        </button>
      </div>

      {/* Auto-cluster modal */}
      <Modal
        open={showAutoCluster}
        onClose={() => setShowAutoCluster(false)}
        title="Auto-clustering questions"
        description="Regrouper automatiquement les questions de forum non traitees en clusters thematiques."
        size="md"
        footer={
          <>
            <Button variant="ghost" onClick={() => setShowAutoCluster(false)}>Annuler</Button>
            <Button variant="primary" onClick={handleAutoCluster} loading={autoLoading}>
              {autoLoading ? 'Clustering...' : 'Lancer'}
            </Button>
          </>
        }
      >
        <div className="space-y-3">
          <select value={autoCountrySlug} onChange={e => setAutoCountrySlug(e.target.value)} className={inputClass + ' w-full'}>
            <option value="">Tous les pays (optionnel)</option>
            {countryOptions.filter(o => o.value).map(o => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </select>
          <select value={autoCategory} onChange={e => setAutoCategory(e.target.value)} className={inputClass + ' w-full'}>
            <option value="">Toutes les categories (optionnel)</option>
            {categoryOptions.filter(o => o.value).map(o => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </select>
        </div>
      </Modal>

      {/* Stat cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {statCards.map(card => (
          <div key={card.label} className="bg-surface border border-border rounded-xl p-5">
            <span className="text-xs text-muted uppercase tracking-wide">{card.label}</span>
            <p className="text-2xl font-bold text-white mt-2">{card.value}</p>
          </div>
        ))}
      </div>

      {/* Filters */}
      <div className="flex items-center gap-3 flex-wrap">
        <select value={filterCountry} onChange={e => setFilterCountry(e.target.value)} className={inputClass}>
          {countryOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select value={filterCategory} onChange={e => setFilterCategory(e.target.value)} className={inputClass}>
          {categoryOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select value={filterStatus} onChange={e => setFilterStatus(e.target.value)} className={inputClass}>
          {STATUS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select value={sortBy} onChange={e => setSortBy(e.target.value)} className={inputClass}>
          {SORT_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {/* Table */}
      <div className="bg-surface border border-border rounded-xl p-5">
        {error && <p className="text-danger text-sm mb-3">{error}</p>}
        {loading ? (
          <div className="space-y-3">
            {[1, 2, 3, 4, 5].map(i => (
              <div key={i} className="animate-pulse bg-surface2 rounded-lg h-12" />
            ))}
          </div>
        ) : clusters.length === 0 ? (
          <div className="text-center py-10">
            <p className="text-muted text-sm mb-3">Aucun cluster. Lancez l'auto-clustering pour regrouper les questions de forum.</p>
            <button onClick={() => setShowAutoCluster(true)} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
              Auto-cluster
            </button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pr-4">Nom</th>
                  <th className="pb-3 pr-4">Pays</th>
                  <th className="pb-3 pr-4">Categorie</th>
                  <th className="pb-3 pr-4">Questions</th>
                  <th className="pb-3 pr-4">Vues</th>
                  <th className="pb-3 pr-4">Reponses</th>
                  <th className="pb-3 pr-4">Popularite</th>
                  <th className="pb-3 pr-4">Statut</th>
                  <th className="pb-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                {clusters.map(cluster => (
                  <tr
                    key={cluster.id}
                    className="border-b border-border/50 hover:bg-surface2/50 transition-colors cursor-pointer"
                    onClick={() => navigate(`/content/question-clusters/${cluster.id}`)}
                  >
                    <td className="py-3 pr-4">
                      <span className="text-white font-medium truncate block max-w-[250px]">{cluster.name}</span>
                    </td>
                    <td className="py-3 pr-4 text-muted capitalize">{cluster.country}</td>
                    <td className="py-3 pr-4">
                      {cluster.category ? (
                        <span className="inline-block px-1.5 py-0.5 rounded text-[10px] bg-violet/20 text-violet-light capitalize">
                          {cluster.category}
                        </span>
                      ) : (
                        <span className="text-muted">-</span>
                      )}
                    </td>
                    <td className="py-3 pr-4 text-white">{cluster.total_questions}</td>
                    <td className="py-3 pr-4 text-white">{formatNumber(cluster.total_views)}</td>
                    <td className="py-3 pr-4 text-white">{cluster.total_replies}</td>
                    <td className="py-3 pr-4">
                      <div className="flex items-center gap-2">
                        <div className="w-16 h-1.5 bg-surface2 rounded-full overflow-hidden">
                          <div
                            className="h-full bg-violet rounded-full"
                            style={{ width: `${Math.min(cluster.popularity_score, 100)}%` }}
                          />
                        </div>
                        <span className="text-xs text-muted">{Math.round(cluster.popularity_score)}</span>
                      </div>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${QC_STATUS_COLORS[cluster.status]}`}>
                        {QC_STATUS_LABELS[cluster.status]}
                      </span>
                    </td>
                    <td className="py-3">
                      <div className="flex items-center gap-2" onClick={e => e.stopPropagation()}>
                        <button onClick={() => navigate(`/content/question-clusters/${cluster.id}`)} className="text-xs text-violet hover:text-violet-light transition-colors">
                          Voir
                        </button>
                        {(cluster.status === 'ready' || cluster.status === 'pending') && (
                          <>
                            <button
                              onClick={() => handleGenerateQa(cluster.id)}
                              disabled={actionLoading === cluster.id}
                              className="text-xs text-blue-400 hover:text-blue-300 transition-colors disabled:opacity-50"
                            >
                              Q&A
                            </button>
                            <button
                              onClick={() => handleGenerateArticle(cluster.id)}
                              disabled={actionLoading === cluster.id}
                              className="text-xs text-success hover:text-green-300 transition-colors disabled:opacity-50"
                            >
                              Article
                            </button>
                            <button
                              onClick={() => handleGenerateBoth(cluster.id)}
                              disabled={actionLoading === cluster.id}
                              className="text-xs text-amber hover:text-yellow-300 transition-colors disabled:opacity-50"
                            >
                              Les deux
                            </button>
                            <button
                              onClick={() => handleSkip(cluster.id)}
                              disabled={actionLoading === cluster.id}
                              className="text-xs text-muted hover:text-gray-300 transition-colors disabled:opacity-50"
                            >
                              Ignorer
                            </button>
                          </>
                        )}
                        <button onClick={() => handleDelete(cluster.id)} className="text-xs text-danger hover:text-red-300 transition-colors">
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
            <span className="text-xs text-muted">{pagination.total} clusters</span>
            <div className="flex gap-2">
              <button
                onClick={() => loadClusters(pagination.current_page - 1)}
                disabled={pagination.current_page <= 1}
                className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40"
              >
                Precedent
              </button>
              <span className="px-3 py-1 text-xs text-muted">
                {pagination.current_page} / {pagination.last_page}
              </span>
              <button
                onClick={() => loadClusters(pagination.current_page + 1)}
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
