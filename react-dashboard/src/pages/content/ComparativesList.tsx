import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { fetchComparatives, deleteComparative } from '../../api/contentApi';
import type { Comparative, ContentStatus, PaginatedResponse } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
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

const STATUS_OPTIONS = [
  { value: '', label: 'Tous les statuts' },
  { value: 'draft', label: 'Brouillon' },
  { value: 'generating', label: 'Generation' },
  { value: 'review', label: 'A relire' },
  { value: 'published', label: 'Publie' },
  { value: 'archived', label: 'Archive' },
];

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

type CompTab = 'sources' | 'generation' | 'generated';
const COMP_TABS: { key: CompTab; label: string; emoji: string }[] = [
  { key: 'sources', label: 'Sources', emoji: '📋' },
  { key: 'generation', label: 'Génération', emoji: '⚡' },
  { key: 'generated', label: 'Contenus générés', emoji: '✅' },
];

// ── Component ───────────────────────────────────────────────
export default function ComparativesList() {
  const navigate = useNavigate();
  const [tab, setTab] = useState<CompTab>('generated');
  const [comparatives, setComparatives] = useState<Comparative[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [search, setSearch] = useState('');
  const [filterLanguage, setFilterLanguage] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; action: () => void } | null>(null);

  const loadComparatives = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string | number> = { page };
      if (search) params.search = search;
      if (filterLanguage) params.language = filterLanguage;
      if (filterStatus) params.status = filterStatus;
      const res = await fetchComparatives(params);
      const data = res.data as unknown as PaginatedResponse<Comparative>;
      setComparatives(data.data);
      setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur de chargement';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [search, filterLanguage, filterStatus]);

  useEffect(() => { loadComparatives(1); }, [loadComparatives]);

  const handleDelete = (id: number) => {
    setConfirmAction({
      title: 'Supprimer ce comparatif',
      message: 'Cette action est irreversible. Confirmer la suppression ?',
      action: async () => {
        try {
          await deleteComparative(id);
          toast('success', 'Comparatif supprime.');
          loadComparatives(pagination.current_page);
        } catch (err) {
          toast('error', errMsg(err));
        }
      },
    });
  };

  // Stats
  const totalComparatives = pagination.total;
  const publishedCount = comparatives.filter(c => c.status === 'published').length;
  const avgSeo = comparatives.length > 0 ? Math.round(comparatives.reduce((s, c) => s + c.seo_score, 0) / comparatives.length) : 0;
  const avgEntities = comparatives.length > 0 ? Math.round(comparatives.reduce((s, c) => s + c.entities.length, 0) / comparatives.length) : 0;

  const statCards = [
    { label: 'Total comparatifs', value: totalComparatives },
    { label: 'Publies', value: publishedCount },
    { label: 'SEO moyen', value: avgSeo + '/100' },
    { label: 'Entites moy.', value: avgEntities },
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
          <h2 className="font-title text-2xl font-bold text-white">Comparatifs</h2>
        </div>
        <button
          onClick={() => navigate('/content/comparatives/new')}
          className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Nouveau comparatif
        </button>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 bg-surface/40 backdrop-blur rounded-xl p-1 border border-border/20">
        {COMP_TABS.map(t => (
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
        <div className="space-y-6">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {statCards.map(card => (
              <div key={card.label} className="bg-surface border border-border rounded-xl p-5">
                <span className="text-xs text-muted uppercase tracking-wide">{card.label}</span>
                <p className="text-2xl font-bold text-white mt-2">{card.value}</p>
              </div>
            ))}
          </div>
          <div className="bg-surface border border-border rounded-xl p-6">
            <h3 className="text-lg font-semibold text-white mb-2">Source des comparatifs</h3>
            <p className="text-sm text-muted">Les comparatifs SEO comparent des entités (pays, services, villes) pour créer du contenu optimisé. Chaque comparatif est créé manuellement.</p>
          </div>
        </div>
      )}

      {/* ⚡ Génération */}
      {tab === 'generation' && (
        <div className="bg-surface border border-border rounded-xl p-6 space-y-4">
          <h3 className="text-lg font-semibold text-white">Créer un comparatif</h3>
          <p className="text-sm text-muted">Créez un comparatif SEO en sélectionnant les entités à comparer.</p>
          <button
            onClick={() => navigate('/content/comparatives/new')}
            className="px-6 py-3 rounded-xl bg-violet text-white font-semibold hover:bg-violet/80 transition-all"
          >
            + Nouveau comparatif
          </button>
        </div>
      )}

      {/* ✅ Contenus générés */}
      {tab === 'generated' && (<>
      {/* Filters */}
      <div className="flex items-center gap-3 flex-wrap">
        <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Rechercher..." className={inputClass + ' w-48'} />
        <select value={filterLanguage} onChange={e => setFilterLanguage(e.target.value)} className={inputClass}>
          <option value="">Toutes les langues</option>
          <option value="fr">Francais</option>
          <option value="en">English</option>
          <option value="es">Espanol</option>
          <option value="de">Deutsch</option>
          <option value="pt">Portugues</option>
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
        ) : comparatives.length === 0 ? (
          <div className="text-center py-10">
            <p className="text-muted text-sm mb-3">Aucun comparatif. Creez-en un pour commencer.</p>
            <button onClick={() => navigate('/content/comparatives/new')} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
              Nouveau comparatif
            </button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pr-4">Titre</th>
                  <th className="pb-3 pr-4">Entites</th>
                  <th className="pb-3 pr-4">Langue</th>
                  <th className="pb-3 pr-4">Statut</th>
                  <th className="pb-3 pr-4">SEO</th>
                  <th className="pb-3 pr-4">Date</th>
                  <th className="pb-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                {comparatives.map(comp => (
                  <tr
                    key={comp.id}
                    className="border-b border-border/50 hover:bg-surface2/50 transition-colors cursor-pointer"
                    onClick={() => navigate(`/content/comparatives/${comp.id}`)}
                  >
                    <td className="py-3 pr-4">
                      <span className="text-white font-medium truncate block max-w-[300px]">{comp.title}</span>
                    </td>
                    <td className="py-3 pr-4">
                      <div className="flex flex-wrap gap-1 max-w-[200px]">
                        {comp.entities.slice(0, 3).map((e, i) => (
                          <span key={i} className="inline-block px-1.5 py-0.5 rounded text-[10px] bg-violet/20 text-violet-light">{e.name}</span>
                        ))}
                        {comp.entities.length > 3 && (
                          <span className="text-xs text-muted">+{comp.entities.length - 3}</span>
                        )}
                      </div>
                    </td>
                    <td className="py-3 pr-4 text-muted uppercase">{comp.language}</td>
                    <td className="py-3 pr-4">
                      <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[comp.status]}`}>
                        {STATUS_LABELS[comp.status]}
                      </span>
                    </td>
                    <td className="py-3 pr-4">
                      <div className="flex items-center gap-2">
                        <div className="w-16 h-1.5 bg-surface2 rounded-full overflow-hidden">
                          <div className="h-full bg-violet rounded-full" style={{ width: `${Math.min(comp.seo_score, 100)}%` }} />
                        </div>
                        <span className="text-xs text-muted">{comp.seo_score}</span>
                      </div>
                    </td>
                    <td className="py-3 pr-4 text-muted text-xs">{formatDate(comp.created_at)}</td>
                    <td className="py-3">
                      <div className="flex items-center gap-2" onClick={e => e.stopPropagation()}>
                        <button onClick={() => navigate(`/content/comparatives/${comp.id}`)} className="text-xs text-violet hover:text-violet-light transition-colors">
                          Voir
                        </button>
                        <button onClick={() => handleDelete(comp.id)} className="text-xs text-danger hover:text-red-300 transition-colors">
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
            <span className="text-xs text-muted">{pagination.total} comparatifs</span>
            <div className="flex gap-2">
              <button
                onClick={() => loadComparatives(pagination.current_page - 1)}
                disabled={pagination.current_page <= 1}
                className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40"
              >
                Precedent
              </button>
              <span className="px-3 py-1 text-xs text-muted">
                {pagination.current_page} / {pagination.last_page}
              </span>
              <button
                onClick={() => loadComparatives(pagination.current_page + 1)}
                disabled={pagination.current_page >= pagination.last_page}
                className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40"
              >
                Suivant
              </button>
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
