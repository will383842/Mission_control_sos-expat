import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { fetchClusters, autoCluster, generateClusterBrief, generateFromCluster, deleteCluster } from '../../api/contentApi';
import type { TopicCluster, ClusterStatus, PaginatedResponse } from '../../types/content';

// ── Constants ───────────────────────────────────────────────
const CLUSTER_STATUS_COLORS: Record<ClusterStatus, string> = {
  pending: 'bg-muted/20 text-muted',
  ready: 'bg-blue-500/20 text-blue-400',
  generating: 'bg-amber/20 text-amber animate-pulse',
  generated: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted',
};

const CLUSTER_STATUS_LABELS: Record<ClusterStatus, string> = {
  pending: 'En attente',
  ready: 'Pret',
  generating: 'Generation...',
  generated: 'Genere',
  archived: 'Archive',
};

const COUNTRY_OPTIONS = [
  { value: '', label: 'Tous les pays' },
  { value: 'france', label: 'France' },
  { value: 'belgique', label: 'Belgique' },
  { value: 'suisse', label: 'Suisse' },
  { value: 'canada', label: 'Canada' },
  { value: 'maroc', label: 'Maroc' },
  { value: 'tunisie', label: 'Tunisie' },
  { value: 'senegal', label: 'Senegal' },
  { value: 'cote-ivoire', label: "Cote d'Ivoire" },
];

const CATEGORY_OPTIONS = [
  { value: '', label: 'Toutes les categories' },
  { value: 'juridique', label: 'Juridique' },
  { value: 'fiscalite', label: 'Fiscalite' },
  { value: 'immigration', label: 'Immigration' },
  { value: 'sante', label: 'Sante' },
  { value: 'logement', label: 'Logement' },
  { value: 'emploi', label: 'Emploi' },
  { value: 'banque', label: 'Banque' },
  { value: 'education', label: 'Education' },
];

const STATUS_OPTIONS = [
  { value: '', label: 'Tous les statuts' },
  { value: 'pending', label: 'En attente' },
  { value: 'ready', label: 'Pret' },
  { value: 'generating', label: 'Generation' },
  { value: 'generated', label: 'Genere' },
  { value: 'archived', label: 'Archive' },
];

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

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
      setShowAutoCluster(false);
      loadClusters(1);
    } catch {
      // silently handled
    } finally {
      setAutoLoading(false);
    }
  };

  const handleGenerateBrief = async (id: number) => {
    setActionLoading(id);
    try {
      await generateClusterBrief(id);
      loadClusters(pagination.current_page);
    } catch {
      // silently handled
    } finally {
      setActionLoading(null);
    }
  };

  const handleGenerateArticle = async (id: number) => {
    setActionLoading(id);
    try {
      await generateFromCluster(id);
      loadClusters(pagination.current_page);
    } catch {
      // silently handled
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = async (id: number) => {
    if (!window.confirm('Supprimer ce cluster ?')) return;
    try {
      await deleteCluster(id);
      loadClusters(pagination.current_page);
    } catch {
      // silently handled
    }
  };

  // Stats
  const statsPending = clusters.filter(c => c.status === 'pending').length;
  const statsReady = clusters.filter(c => c.status === 'ready').length;
  const statsGenerated = clusters.filter(c => c.status === 'generated').length;
  const totalArticles = clusters.reduce((s, c) => s + c.source_articles_count, 0);

  const statCards = [
    { label: 'Articles sources', value: totalArticles, color: 'text-violet bg-violet/20' },
    { label: 'Clusters en attente', value: statsPending, color: 'text-muted bg-muted/20' },
    { label: 'Clusters prets', value: statsReady, color: 'text-blue-400 bg-blue-500/20' },
    { label: 'Articles generes', value: statsGenerated, color: 'text-success bg-success/20' },
  ];

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <h2 className="font-title text-2xl font-bold text-white">Topic Clusters</h2>
        <button
          onClick={() => setShowAutoCluster(true)}
          className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Auto-cluster
        </button>
      </div>

      {/* Auto-cluster modal */}
      {showAutoCluster && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
          <div className="bg-surface border border-border rounded-xl p-6 w-full max-w-md space-y-4">
            <h3 className="font-title font-semibold text-white text-lg">Auto-clustering</h3>
            <p className="text-sm text-muted">Generer automatiquement des clusters a partir des articles sources non traites.</p>
            <div className="space-y-3">
              <select value={autoCountry} onChange={e => setAutoCountry(e.target.value)} className={inputClass + ' w-full'}>
                <option value="">Selectionner un pays *</option>
                {COUNTRY_OPTIONS.filter(o => o.value).map(o => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </select>
              <select value={autoCategory} onChange={e => setAutoCategory(e.target.value)} className={inputClass + ' w-full'}>
                <option value="">Toutes les categories</option>
                {CATEGORY_OPTIONS.filter(o => o.value).map(o => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </select>
            </div>
            <div className="flex justify-end gap-3">
              <button onClick={() => setShowAutoCluster(false)} className="px-4 py-2 text-sm text-muted hover:text-white transition-colors">
                Annuler
              </button>
              <button
                onClick={handleAutoCluster}
                disabled={!autoCountry || autoLoading}
                className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
              >
                {autoLoading ? 'Clustering...' : 'Lancer'}
              </button>
            </div>
          </div>
        </div>
      )}

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
          {COUNTRY_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select value={filterCategory} onChange={e => setFilterCategory(e.target.value)} className={inputClass}>
          {CATEGORY_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
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
            <p className="text-muted text-sm mb-3">Aucun cluster trouve</p>
            <button onClick={() => setShowAutoCluster(true)} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
              Creer des clusters
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
                  <th className="pb-3 pr-4">Sources</th>
                  <th className="pb-3 pr-4">Mots-cles</th>
                  <th className="pb-3 pr-4">Statut</th>
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
                    </td>
                    <td className="py-3 pr-4 text-muted capitalize">{cluster.country}</td>
                    <td className="py-3 pr-4 text-muted capitalize">{cluster.category}</td>
                    <td className="py-3 pr-4 text-white">{cluster.source_articles_count}</td>
                    <td className="py-3 pr-4">
                      <div className="flex flex-wrap gap-1 max-w-[200px]">
                        {(cluster.keywords_detected ?? []).slice(0, 3).map(kw => (
                          <span key={kw} className="inline-block px-1.5 py-0.5 rounded text-[10px] bg-violet/20 text-violet-light">
                            {kw}
                          </span>
                        ))}
                        {(cluster.keywords_detected ?? []).length > 3 && (
                          <span className="text-[10px] text-muted">+{cluster.keywords_detected!.length - 3}</span>
                        )}
                      </div>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${CLUSTER_STATUS_COLORS[cluster.status]}`}>
                        {CLUSTER_STATUS_LABELS[cluster.status]}
                      </span>
                    </td>
                    <td className="py-3">
                      <div className="flex items-center gap-2" onClick={e => e.stopPropagation()}>
                        <button onClick={() => navigate(`/content/clusters/${cluster.id}`)} className="text-xs text-violet hover:text-violet-light transition-colors">
                          Voir
                        </button>
                        {cluster.status === 'ready' && (
                          <button
                            onClick={() => handleGenerateBrief(cluster.id)}
                            disabled={actionLoading === cluster.id}
                            className="text-xs text-blue-400 hover:text-blue-300 transition-colors disabled:opacity-50"
                          >
                            Brief
                          </button>
                        )}
                        {(cluster.status === 'ready') && (
                          <button
                            onClick={() => handleGenerateArticle(cluster.id)}
                            disabled={actionLoading === cluster.id}
                            className="text-xs text-success hover:text-green-300 transition-colors disabled:opacity-50"
                          >
                            Generer
                          </button>
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
    </div>
  );
}
