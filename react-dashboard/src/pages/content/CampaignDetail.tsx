import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchCampaign,
  fetchCampaignItems,
  startCampaign,
  pauseCampaign,
  resumeCampaign,
  cancelCampaign,
} from '../../api/contentApi';
import type {
  ContentCampaign,
  ContentCampaignItem,
  CampaignStatus,
  CampaignType,
} from '../../types/content';

// ── Constants ───────────────────────────────────────────────
const STATUS_COLORS: Record<CampaignStatus, string> = {
  draft: 'bg-muted/20 text-muted',
  running: 'bg-cyan/20 text-cyan',
  paused: 'bg-amber/20 text-amber',
  completed: 'bg-success/20 text-success',
  cancelled: 'bg-danger/20 text-danger',
};

const STATUS_LABELS: Record<CampaignStatus, string> = {
  draft: 'Brouillon',
  running: 'En cours',
  paused: 'En pause',
  completed: 'Termine',
  cancelled: 'Annule',
};

const TYPE_LABELS: Record<CampaignType, string> = {
  country_coverage: 'Couverture pays',
  thematic: 'Thematique',
  pillar_cluster: 'Pilier + clusters',
  comparative_series: 'Serie comparatifs',
  custom: 'Personnalise',
};

const ITEM_STATUS_COLORS: Record<string, string> = {
  pending: 'bg-muted/20 text-muted',
  generating: 'bg-amber/20 text-amber',
  completed: 'bg-success/20 text-success',
  failed: 'bg-danger/20 text-danger',
  skipped: 'bg-muted/20 text-muted',
};

const ITEM_STATUS_LABELS: Record<string, string> = {
  pending: 'En attente',
  generating: 'Generation...',
  completed: 'Termine',
  failed: 'Echec',
  skipped: 'Ignore',
};

// ── Component ───────────────────────────────────────────────
export default function CampaignDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const campaignId = Number(id);

  const [campaign, setCampaign] = useState<ContentCampaign | null>(null);
  const [items, setItems] = useState<ContentCampaignItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    try {
      const [campRes, itemsRes] = await Promise.all([
        fetchCampaign(campaignId),
        fetchCampaignItems(campaignId),
      ]);
      setCampaign(campRes.data);
      setItems(itemsRes.data);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  }, [campaignId]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // Poll if running
  useEffect(() => {
    if (!campaign || campaign.status !== 'running') return;
    const interval = setInterval(loadData, 5000);
    return () => clearInterval(interval);
  }, [campaign?.status, loadData]);

  const handleAction = async (action: string) => {
    if (!campaign) return;
    try {
      let result;
      switch (action) {
        case 'start': result = await startCampaign(campaign.id); break;
        case 'pause': result = await pauseCampaign(campaign.id); break;
        case 'resume': result = await resumeCampaign(campaign.id); break;
        case 'cancel':
          if (!confirm('Annuler cette campagne ?')) return;
          result = await cancelCampaign(campaign.id);
          break;
        default: return;
      }
      if (result) setCampaign(result.data);
    } catch { /* ignore */ }
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6">
        <div className="text-muted text-sm">Chargement...</div>
      </div>
    );
  }

  if (error || !campaign) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-lg">
          {error || 'Campagne introuvable'}
        </div>
      </div>
    );
  }

  const pct = campaign.total_items > 0
    ? Math.round((campaign.completed_items / campaign.total_items) * 100)
    : 0;

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm text-muted">
        <button onClick={() => navigate('/content/overview')} className="hover:text-white transition-colors">Contenu</button>
        <span>/</span>
        <button onClick={() => navigate('/content/campaigns')} className="hover:text-white transition-colors">Campagnes</button>
        <span>/</span>
        <span className="text-white truncate max-w-[200px]">{campaign.name}</span>
      </div>

      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">{campaign.name}</h2>
          <div className="flex items-center gap-3 mt-2">
            <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[campaign.status]}`}>
              {campaign.status === 'running' && (
                <span className="w-1.5 h-1.5 bg-cyan rounded-full animate-pulse" />
              )}
              {STATUS_LABELS[campaign.status]}
            </span>
            <span className="text-xs text-muted">{TYPE_LABELS[campaign.campaign_type]}</span>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {campaign.status === 'draft' && (
            <button onClick={() => handleAction('start')} className="px-4 py-1.5 bg-success hover:bg-success/90 text-white text-sm rounded-lg transition-colors">
              Demarrer
            </button>
          )}
          {campaign.status === 'running' && (
            <button onClick={() => handleAction('pause')} className="px-4 py-1.5 bg-amber hover:bg-amber/90 text-black text-sm rounded-lg transition-colors">
              Pause
            </button>
          )}
          {campaign.status === 'paused' && (
            <button onClick={() => handleAction('resume')} className="px-4 py-1.5 bg-success hover:bg-success/90 text-white text-sm rounded-lg transition-colors">
              Reprendre
            </button>
          )}
          {(campaign.status === 'running' || campaign.status === 'paused') && (
            <button onClick={() => handleAction('cancel')} className="px-4 py-1.5 bg-surface2 text-danger hover:bg-danger/20 text-sm rounded-lg border border-border transition-colors">
              Annuler
            </button>
          )}
        </div>
      </div>

      {/* Progress */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
          <div>
            <p className="text-xs text-muted">Progression</p>
            <p className="text-xl font-bold text-white">{pct}%</p>
          </div>
          <div>
            <p className="text-xs text-muted">Termines</p>
            <p className="text-xl font-bold text-success">{campaign.completed_items}</p>
          </div>
          <div>
            <p className="text-xs text-muted">Echecs</p>
            <p className={`text-xl font-bold ${campaign.failed_items > 0 ? 'text-danger' : 'text-white'}`}>
              {campaign.failed_items}
            </p>
          </div>
          <div>
            <p className="text-xs text-muted">Cout total</p>
            <p className="text-xl font-bold text-white">${(campaign.total_cost_cents / 100).toFixed(2)}</p>
          </div>
        </div>
        <div>
          <div className="flex justify-between text-xs text-muted mb-1">
            <span>{campaign.completed_items} / {campaign.total_items} articles</span>
            <span>{pct}%</span>
          </div>
          <div className="h-3 bg-surface2 rounded-full overflow-hidden">
            <div
              className={`h-full rounded-full transition-all ${
                campaign.status === 'running' ? 'bg-cyan' :
                campaign.status === 'completed' ? 'bg-success' : 'bg-muted'
              }`}
              style={{ width: `${pct}%` }}
            />
          </div>
        </div>
      </div>

      {/* Items table */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <div className="px-5 py-3 border-b border-border">
          <h3 className="font-title font-semibold text-white text-sm">{items.length} element(s)</h3>
        </div>

        {items.length === 0 ? (
          <div className="p-8 text-center text-muted text-sm">Aucun element dans cette campagne.</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pl-4 pr-4 pt-3">#</th>
                  <th className="pb-3 pr-4 pt-3">Titre / Sujet</th>
                  <th className="pb-3 pr-4 pt-3">Statut</th>
                  <th className="pb-3 pr-4 pt-3">Planifie</th>
                  <th className="pb-3 pr-4 pt-3">Termine</th>
                  <th className="pb-3 pr-4 pt-3">Erreur</th>
                  <th className="pb-3 pr-4 pt-3">Article</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr
                    key={item.id}
                    className="border-b border-border/50 hover:bg-surface2/50 transition-colors"
                  >
                    <td className="py-3 pl-4 pr-4 text-muted">{item.sort_order + 1}</td>
                    <td className="py-3 pr-4">
                      <span className="text-white truncate block max-w-[300px]">{item.title_hint}</span>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium ${ITEM_STATUS_COLORS[item.status]}`}>
                        {item.status === 'generating' && (
                          <span className="w-1.5 h-1.5 bg-amber rounded-full animate-pulse" />
                        )}
                        {ITEM_STATUS_LABELS[item.status]}
                      </span>
                    </td>
                    <td className="py-3 pr-4 text-muted text-xs">
                      {item.scheduled_at ? new Date(item.scheduled_at).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '-'}
                    </td>
                    <td className="py-3 pr-4 text-muted text-xs">
                      {item.completed_at ? new Date(item.completed_at).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '-'}
                    </td>
                    <td className="py-3 pr-4">
                      {item.error_message ? (
                        <span className="text-xs text-danger truncate block max-w-[200px]" title={item.error_message}>
                          {item.error_message}
                        </span>
                      ) : (
                        <span className="text-muted">-</span>
                      )}
                    </td>
                    <td className="py-3 pr-4">
                      {item.itemable ? (
                        <button
                          onClick={() => {
                            const itemId = (item.itemable as { id: number }).id;
                            if (campaign.campaign_type === 'comparative_series') {
                              navigate(`/content/comparatives/${itemId}`);
                            } else {
                              navigate(`/content/articles/${itemId}`);
                            }
                          }}
                          className="text-xs text-violet hover:text-violet-light transition-colors"
                        >
                          {campaign.campaign_type === 'comparative_series' ? 'Voir le comparatif' : 'Voir l\'article'}
                        </button>
                      ) : (
                        <span className="text-muted text-xs">-</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Campaign config info */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <h3 className="font-title font-semibold text-white text-sm mb-3">Configuration</h3>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
          {campaign.config.country && (
            <div>
              <span className="text-muted block">Pays</span>
              <span className="text-white">{campaign.config.country}</span>
            </div>
          )}
          {campaign.config.languages && campaign.config.languages.length > 0 && (
            <div>
              <span className="text-muted block">Langues</span>
              <span className="text-white">{campaign.config.languages.join(', ').toUpperCase()}</span>
            </div>
          )}
          {campaign.config.themes && campaign.config.themes.length > 0 && (
            <div>
              <span className="text-muted block">Themes</span>
              <span className="text-white">{campaign.config.themes.join(', ')}</span>
            </div>
          )}
          {campaign.config.articles_per_day && (
            <div>
              <span className="text-muted block">Articles/jour</span>
              <span className="text-white">{campaign.config.articles_per_day}</span>
            </div>
          )}
          {campaign.started_at && (
            <div>
              <span className="text-muted block">Debut</span>
              <span className="text-white">{new Date(campaign.started_at).toLocaleDateString('fr-FR')}</span>
            </div>
          )}
          {campaign.completed_at && (
            <div>
              <span className="text-muted block">Fin</span>
              <span className="text-white">{new Date(campaign.completed_at).toLocaleDateString('fr-FR')}</span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
