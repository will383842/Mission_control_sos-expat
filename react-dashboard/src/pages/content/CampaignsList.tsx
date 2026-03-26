import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useContentCampaigns } from '../../hooks/useContentEngine';
import type { CampaignStatus, CampaignType } from '../../types/content';

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

// ── Component ───────────────────────────────────────────────
export default function CampaignsList() {
  const navigate = useNavigate();
  const { campaigns, loading, error, pagination, load, start, pause, resume, cancel, remove } = useContentCampaigns();

  useEffect(() => {
    load();
  }, [load]);

  const handleAction = async (action: string, id: number) => {
    try {
      switch (action) {
        case 'start': await start(id); break;
        case 'pause': await pause(id); break;
        case 'resume': await resume(id); break;
        case 'cancel':
          if (confirm('Annuler cette campagne ?')) await cancel(id);
          break;
        case 'delete':
          if (confirm('Supprimer cette campagne ?')) await remove(id);
          break;
      }
    } catch { /* ignore */ }
  };

  const getActions = (status: CampaignStatus) => {
    switch (status) {
      case 'draft': return [{ key: 'start', label: 'Demarrer', cls: 'text-success' }, { key: 'delete', label: 'Supprimer', cls: 'text-danger' }];
      case 'running': return [{ key: 'pause', label: 'Pause', cls: 'text-amber' }, { key: 'cancel', label: 'Annuler', cls: 'text-danger' }];
      case 'paused': return [{ key: 'resume', label: 'Reprendre', cls: 'text-success' }, { key: 'cancel', label: 'Annuler', cls: 'text-danger' }];
      case 'completed': return [{ key: 'delete', label: 'Supprimer', cls: 'text-danger' }];
      case 'cancelled': return [{ key: 'delete', label: 'Supprimer', cls: 'text-danger' }];
      default: return [];
    }
  };

  return (
    <div className="p-4 md:p-6 space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between gap-3">
        <h2 className="font-title text-2xl font-bold text-white">Campagnes</h2>
        <button
          onClick={() => navigate('/content/campaigns/new')}
          className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Nouvelle campagne
        </button>
      </div>

      {/* Error */}
      {error && (
        <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-lg">{error}</div>
      )}

      {/* Table */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        {loading ? (
          <div className="p-8 text-center text-muted text-sm">Chargement...</div>
        ) : campaigns.length === 0 ? (
          <div className="p-10 text-center">
            <p className="text-muted text-sm mb-3">Aucune campagne creee</p>
            <button
              onClick={() => navigate('/content/campaigns/new')}
              className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
            >
              Lancer votre premiere campagne
            </button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pl-4 pr-4">Nom</th>
                  <th className="pb-3 pr-4">Type</th>
                  <th className="pb-3 pr-4">Statut</th>
                  <th className="pb-3 pr-4">Progression</th>
                  <th className="pb-3 pr-4">Cout</th>
                  <th className="pb-3 pr-4">Debut</th>
                  <th className="pb-3 pr-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                {campaigns.map(campaign => {
                  const pct = campaign.total_items > 0
                    ? Math.round((campaign.completed_items / campaign.total_items) * 100)
                    : 0;

                  return (
                    <tr
                      key={campaign.id}
                      className="border-b border-border/50 hover:bg-surface2/50 transition-colors cursor-pointer"
                      onClick={() => navigate(`/content/campaigns/${campaign.id}`)}
                    >
                      <td className="py-3 pl-4 pr-4">
                        <span className="text-white font-medium truncate block max-w-[200px]">{campaign.name}</span>
                      </td>
                      <td className="py-3 pr-4 text-muted text-xs">
                        {TYPE_LABELS[campaign.campaign_type] || campaign.campaign_type}
                      </td>
                      <td className="py-3 pr-4">
                        <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[campaign.status]}`}>
                          {campaign.status === 'running' && (
                            <span className="w-1.5 h-1.5 bg-cyan rounded-full animate-pulse" />
                          )}
                          {STATUS_LABELS[campaign.status]}
                        </span>
                      </td>
                      <td className="py-3 pr-4">
                        <div className="flex items-center gap-2 min-w-[160px]">
                          <div className="flex-1 h-1.5 bg-surface2 rounded-full overflow-hidden">
                            <div
                              className={`h-full rounded-full transition-all ${
                                campaign.status === 'running' ? 'bg-cyan' :
                                campaign.status === 'completed' ? 'bg-success' : 'bg-muted'
                              }`}
                              style={{ width: `${pct}%` }}
                            />
                          </div>
                          <span className="text-xs text-muted whitespace-nowrap">
                            {campaign.completed_items}/{campaign.total_items}
                          </span>
                        </div>
                        {campaign.failed_items > 0 && (
                          <span className="text-[10px] text-danger">{campaign.failed_items} echec(s)</span>
                        )}
                      </td>
                      <td className="py-3 pr-4 text-muted">
                        ${(campaign.total_cost_cents / 100).toFixed(2)}
                      </td>
                      <td className="py-3 pr-4 text-muted">
                        {campaign.started_at
                          ? new Date(campaign.started_at).toLocaleDateString('fr-FR')
                          : '-'
                        }
                      </td>
                      <td className="py-3 pr-4" onClick={e => e.stopPropagation()}>
                        <div className="flex items-center gap-2">
                          <button
                            onClick={() => navigate(`/content/campaigns/${campaign.id}`)}
                            className="text-xs text-violet hover:text-violet-light transition-colors"
                          >
                            Voir
                          </button>
                          {getActions(campaign.status).map(action => (
                            <button
                              key={action.key}
                              onClick={() => handleAction(action.key, campaign.id)}
                              className={`text-xs ${action.cls} hover:opacity-80 transition-colors`}
                            >
                              {action.label}
                            </button>
                          ))}
                        </div>
                      </td>
                    </tr>
                  );
                })}
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
            onClick={() => load({ page: pagination.current_page - 1 })}
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
                  onClick={() => load({ page })}
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
            onClick={() => load({ page: pagination.current_page + 1 })}
            className="px-3 py-1.5 bg-surface2 text-muted hover:text-white text-xs rounded-lg border border-border transition-colors disabled:opacity-30"
          >
            Suivant
          </button>
        </div>
      )}
    </div>
  );
}
