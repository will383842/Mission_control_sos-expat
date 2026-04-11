import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  fetchClusters,
  autoCluster,
  generateClusterBrief,
  generateFromCluster,
  deleteCluster,
} from '../../api/contentApi';
import type { TopicCluster, ClusterStatus, PaginatedResponse } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { Modal } from '../../ui/Modal';
import { Button } from '../../ui/Button';
import { errMsg } from './helpers';

// ── Constants ───────────────────────────────────────────────
const CLUSTER_STATUS_COLORS: Record<ClusterStatus, string> = {
  pending: 'bg-muted/20 text-muted',
  ready: 'bg-blue-500/20 text-blue-400',
  generating: 'bg-amber/20 text-amber animate-pulse',
  generated: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted line-through',
};

const CLUSTER_STATUS_LABELS: Record<ClusterStatus, string> = {
  pending: 'En attente',
  ready: 'Pret',
  generating: 'Generation...',
  generated: 'Genere',
  archived: 'Archive',
};

const STATUS_OPTIONS = [
  { value: '', label: 'Tous les statuts' },
  { value: 'pending', label: 'En attente' },
  { value: 'ready', label: 'Pret' },
  { value: 'generating', label: 'Generation' },
  { value: 'generated', label: 'Genere' },
  { value: 'archived', label: 'Archive' },
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
export default function ClustersList() {
  const navigate = useNavigate();
  const [clusters, setClusters] = useState<TopicCluster[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [filterCountry, setFilterCountry] = useState('');
  const [filterCategory, setFilterCategory] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [showAutoCluster, setShowAutoCluster] = useState(false);
  const [autoCountry, setAutoCountry] = useState('');
  const [autoCategory, setAutoCategory] = useState('');
  const [autoLoading, setAutoLoading] = useState(false);
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; action: () => void } | null>(null);

  const loadClusters = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string | number> = { page };
      if (filterCountry) params.country = filterCountry;
      if (filterCategory) params.category = filterCategory;
      if (filterStatus) params.status = filterStatus;
      const res = await fetchClusters(params);
      const data = res.data as unknown as PaginatedResponse<TopicCluster>;
      setClusters(data.data);
      setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur de chargement';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [filterCountry, filterCategory, filterStatus]);

  useEffect(() => { loadClusters(1); }, [loadClusters]);

  const handleAutoCluster = async () => {
    if (!autoCountry) return;
    setAutoLoading(true);
    try {
      const data: { country: string; category?: string } = { country: autoCountry };
      if (autoCategory) data.category = autoCategory;
      await autoCluster(data);
      toast('success', 'Auto-cluster lance.');
      setShowAutoCluster(false);
      setAutoCountry('');
      setAutoCategory('');
      loadClusters(1);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setAutoLoading(false);
    }
  };

  const handleBrief = async (id: number) => {
    setActionLoading(id);
    try {
      await generateClusterBrief(id);
      toast('success', 'Brief genere.');
      loadClusters(pagination.current_page);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleGenerate = async (id: number) => {
    setActionLoading(id);
    try {
      await generateFromCluster(id);
      toast('success', 'Generation lancee.');
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
      message: 'Cette action est irreversible. Confirmer la suppression ?',
      action: async () => {
        try {
          await deleteCluster(id);
          toast('success', 'Cluster supprime.');
          loadClusters(pagination.current_page);
        } catch (err) {
          toast('error', errMsg(err));
        }
      },
    });
  };

  // Stats
  const totalClusters = pagination.total;
  const generatedCount = clusters.filter(c => c.status === 'generated').length;
  const readyCount = clusters.filter(c => c.status === 'ready' || c.status === 'pending').length;
  const totalArticles = clusters.reduce((s, c) => s + c.source_articles_count, 0);

  const statCards = [
    { label: 'Total clusters', value: formatNumber(totalClusters), color: 'text-violet bg-violet/20' },
    { label: 'Generes', value: generatedCount, color: 'text-success bg-success/20' },
    { label: 'En attente', value: readyCount, color: 'text-blue-400 bg-blue-500/20' },
    { label: 'Articles sources', value: formatNumber(totalArticles), color: 'text-amber bg-amber/20' },
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
          <h2 className="font-title text-2xl font-bold text-white">Topic Clusters</h2>
        </div>
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
        title="Auto-clustering articles"
        description="Regrouper automatiquement les articles scraped en clusters thematiques."
        size="md"
        footer={
          <>
            <Button variant="ghost" onClick={() => setShowAutoCluster(false)}>Annuler</Button>
            <Button variant="primary" onClick={handleAutoCluster} disabled={!autoCountry} loading={autoLoading}>
              {autoLoading ? 'Clustering...' : 'Lancer'}
            </Button>
          </>
        }
      >
        <div className="space-y-3">
          <input value={autoCountry} onChange={e => setAutoCountry(e.target.value)} placeholder="Pays (obligatoire)" className={inputClass + ' w-full'} />
          <input value={autoCategory} onChange={e => setAutoCategory(e.target.value)} placeholder="Categorie (optionnel)" className={inputClass + ' w-full'} />
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
        <input
          type="text"
          value={filterCountry}
          onChange={e => setFilterCountry(e.target.value)}
          placeholder="Pays..."
          className={inputClass + ' w-36'}
        />
        <input
          type="text"
          value={filterCategory}
          onChange={e => setFilterCategory(e.target.value)}
          placeholder="Categorie..."
          className={inputClass + ' w-36'}
        />
        <select value={filterStatus} onChange={e => setFilterStatus(e.target.value)} className={inputClass}>
          {STATUS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
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
            <p className="text-muted text-sm mb-3">Aucun cluster. Lancez l'auto-clustering pour regrouper les articles.</p>
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
                  <th className="pb-3 pr-4">Langue</th>
                  <th className="pb-3 pr-4">Articles</th>
                  <th className="pb-3 pr-4">Statut</th>
                  <th className="pb-3 pr-4">Date</th>
                  <th className="pb-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                {clusters.map(cluster => (
                  <tr
                    key={cluster.id}
                    className="border-b border-border/50 hover:bg-surface2/50 transition-colors cursor-pointer"
                    onClick={() => navigate(`/content/clusters/${cluster.id}`)}
                  >
                    <td className="py-3 pr-4">
                      <span className="text-white font-medium truncate block max-w-[250px]">{cluster.name}</span>
                      {cluster.description && <p className="text-xs text-muted truncate max-w-[250px]">{cluster.description}</p>}
                    </td>
                    <td className="py-3 pr-4 text-muted capitalize">{cluster.country}</td>
                    <td className="py-3 pr-4">
                      {cluster.category ? (
                        <span className="inline-block px-1.5 py-0.5 rounded text-[10px] bg-violet/20 text-violet-light capitalize">{cluster.category}</span>
                      ) : (
                        <span className="text-muted">-</span>
                      )}
                    </td>
                    <td className="py-3 pr-4 text-muted uppercase">{cluster.language}</td>
                    <td className="py-3 pr-4 text-white">{cluster.source_articles_count}</td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${CLUSTER_STATUS_COLORS[cluster.status]}`}>
                        {CLUSTER_STATUS_LABELS[cluster.status]}
                      </span>
                    </td>
                    <td className="py-3 pr-4 text-muted text-xs">{formatDate(cluster.created_at)}</td>
                    <td className="py-3">
                      <div className="flex items-center gap-2" onClick={e => e.stopPropagation()}>
                        <button onClick={() => navigate(`/content/clusters/${cluster.id}`)} className="text-xs text-violet hover:text-violet-light transition-colors">
                          Voir
                        </button>
                        {(cluster.status === 'pending' || cluster.status === 'ready') && (
                          <>
                            <button
                              onClick={() => handleBrief(cluster.id)}
                              disabled={actionLoading === cluster.id}
                              className="text-xs text-blue-400 hover:text-blue-300 transition-colors disabled:opacity-50"
                            >
                              Brief
                            </button>
                            <button
                              onClick={() => handleGenerate(cluster.id)}
                              disabled={actionLoading === cluster.id}
                              className="text-xs text-success hover:text-green-300 transition-colors disabled:opacity-50"
                            >
                              Generer
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
