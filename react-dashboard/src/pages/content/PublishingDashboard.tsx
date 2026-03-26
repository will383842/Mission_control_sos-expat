import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchEndpoints,
  createEndpoint,
  updateEndpoint,
  deleteEndpoint,
  fetchPublicationQueue,
  executeQueueItem,
  cancelQueueItem,
  fetchSchedule,
  updateSchedule,
} from '../../api/contentApi';
import type {
  PublishingEndpoint,
  PublicationQueueItem,
  PublicationSchedule,
  PaginatedResponse,
  EndpointType,
} from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { errMsg } from './helpers';

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

const ENDPOINT_TYPE_COLORS: Record<EndpointType, string> = {
  firestore: 'bg-amber/20 text-amber',
  wordpress: 'bg-blue-500/20 text-blue-400',
  webhook: 'bg-violet/20 text-violet-light',
  export: 'bg-success/20 text-success',
  blog: 'bg-pink-500/20 text-pink-400',
};

const QUEUE_STATUS_COLORS: Record<string, string> = {
  pending: 'bg-muted/20 text-muted',
  publishing: 'bg-amber/20 text-amber animate-pulse',
  published: 'bg-success/20 text-success',
  failed: 'bg-danger/20 text-danger',
  cancelled: 'bg-muted/20 text-muted line-through',
};

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

interface EndpointFormData {
  name: string;
  type: EndpointType;
  is_active: boolean;
}

export default function PublishingDashboard() {
  const [endpoints, setEndpoints] = useState<PublishingEndpoint[]>([]);
  const [queue, setQueue] = useState<PublicationQueueItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [queuePagination, setQueuePagination] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [queueFilter, setQueueFilter] = useState('');
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [formData, setFormData] = useState<EndpointFormData>({ name: '', type: 'firestore', is_active: true });
  const [schedules, setSchedules] = useState<Record<number, PublicationSchedule>>({});
  const [expandedSchedule, setExpandedSchedule] = useState<number | null>(null);
  const [selectedItems, setSelectedItems] = useState<Set<number>>(new Set());
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; action: () => void } | null>(null);

  const loadEndpoints = useCallback(async () => {
    try {
      const res = await fetchEndpoints();
      setEndpoints((res.data as unknown as PublishingEndpoint[]) ?? []);
    } catch (err) { toast('error', errMsg(err)); }
  }, []);

  const loadQueue = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string | number> = { page };
      if (queueFilter) params.status = queueFilter;
      const res = await fetchPublicationQueue(params);
      const data = res.data as unknown as PaginatedResponse<PublicationQueueItem>;
      setQueue(data.data);
      setQueuePagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  }, [queueFilter]);

  useEffect(() => { loadEndpoints(); }, [loadEndpoints]);
  useEffect(() => { loadQueue(1); }, [loadQueue]);

  const handleCreateEndpoint = async () => {
    try {
      await createEndpoint(formData);
      toast('success', 'Endpoint cree.');
      setShowAddForm(false);
      setFormData({ name: '', type: 'firestore', is_active: true });
      loadEndpoints();
    } catch (err) { toast('error', errMsg(err)); }
  };

  const handleUpdateEndpoint = async (id: number, data: Partial<PublishingEndpoint>) => {
    try {
      await updateEndpoint(id, data);
      loadEndpoints();
    } catch (err) { toast('error', errMsg(err)); }
  };

  const handleDeleteEndpoint = (id: number) => {
    setConfirmAction({
      title: 'Supprimer cet endpoint',
      message: 'Cette action est irreversible.',
      action: async () => {
        try {
          await deleteEndpoint(id);
          toast('success', 'Endpoint supprime.');
          loadEndpoints();
        } catch (err) { toast('error', errMsg(err)); }
      },
    });
  };

  const handleToggleActive = async (ep: PublishingEndpoint) => {
    await handleUpdateEndpoint(ep.id, { is_active: !ep.is_active });
  };

  const handleExecute = async (id: number) => {
    setActionLoading(id);
    try {
      await executeQueueItem(id);
      toast('success', 'Publication executee.');
      loadQueue(queuePagination.current_page);
    } catch (err) { toast('error', errMsg(err)); } finally {
      setActionLoading(null);
    }
  };

  const handleCancel = async (id: number) => {
    setActionLoading(id);
    try {
      await cancelQueueItem(id);
      toast('success', 'Publication annulee.');
      loadQueue(queuePagination.current_page);
    } catch (err) { toast('error', errMsg(err)); } finally {
      setActionLoading(null);
    }
  };

  const handleBulkExecute = async () => {
    let ok = 0;
    for (const id of selectedItems) {
      try { await executeQueueItem(id); ok++; } catch { /* individual failure */ }
    }
    toast('success', `${ok}/${selectedItems.size} publication(s) executee(s).`);
    setSelectedItems(new Set());
    loadQueue(queuePagination.current_page);
  };

  const handleLoadSchedule = async (endpointId: number) => {
    if (expandedSchedule === endpointId) { setExpandedSchedule(null); return; }
    try {
      const res = await fetchSchedule(endpointId);
      setSchedules(prev => ({ ...prev, [endpointId]: res.data as unknown as PublicationSchedule }));
      setExpandedSchedule(endpointId);
    } catch { setExpandedSchedule(endpointId); }
  };

  const handleUpdateSchedule = async (endpointId: number, data: Partial<PublicationSchedule>) => {
    try {
      const res = await updateSchedule(endpointId, data);
      setSchedules(prev => ({ ...prev, [endpointId]: res.data as unknown as PublicationSchedule }));
      toast('success', 'Planning mis a jour.');
    } catch (err) { toast('error', errMsg(err)); }
  };

  const toggleSelectItem = (id: number) => {
    setSelectedItems(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  };

  return (
    <div className="p-4 md:p-6 space-y-6">
      <h2 className="font-title text-2xl font-bold text-white">Gestion publication</h2>
      {error && (
        <div className="flex items-center justify-between bg-danger/10 border border-danger/30 rounded-lg p-3">
          <p className="text-danger text-sm">{error}</p>
          <button onClick={() => loadQueue(1)} className="text-xs text-danger hover:text-red-300 transition-colors">Reessayer</button>
        </div>
      )}

      {/* Endpoints */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h3 className="font-title text-lg font-semibold text-white">Endpoints</h3>
          <button
            onClick={() => setShowAddForm(true)}
            className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
          >
            + Ajouter
          </button>
        </div>

        {showAddForm && (
          <div className="bg-surface border border-border rounded-xl p-5 mb-4 space-y-3">
            <input
              type="text"
              placeholder="Nom de l'endpoint"
              value={formData.name}
              onChange={e => setFormData(d => ({ ...d, name: e.target.value }))}
              className={inputClass + ' w-full'}
            />
            <select
              value={formData.type}
              onChange={e => setFormData(d => ({ ...d, type: e.target.value as EndpointType }))}
              className={inputClass + ' w-full'}
            >
              <option value="firestore">Firestore</option>
              <option value="wordpress">WordPress</option>
              <option value="blog">Blog SOS-Expat</option>
              <option value="webhook">Webhook</option>
              <option value="export">Export</option>
            </select>
            <div className="flex gap-3">
              <button onClick={handleCreateEndpoint} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
                Creer
              </button>
              <button onClick={() => setShowAddForm(false)} className="px-4 py-2 text-sm text-muted hover:text-white transition-colors">
                Annuler
              </button>
            </div>
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {endpoints.map(ep => (
            <div key={ep.id} className="bg-surface border border-border rounded-xl p-5 space-y-3">
              <div className="flex items-center justify-between">
                <h4 className="text-white font-medium">{ep.name}</h4>
                <span className={`inline-block px-1.5 py-0.5 rounded text-[10px] font-medium ${ENDPOINT_TYPE_COLORS[ep.type]}`}>
                  {ep.type}
                </span>
              </div>
              <div className="flex items-center gap-3">
                <button
                  onClick={() => handleToggleActive(ep)}
                  className={`px-2 py-1 text-xs rounded-lg transition-colors ${ep.is_active ? 'bg-success/20 text-success' : 'bg-muted/20 text-muted'}`}
                >
                  {ep.is_active ? 'Actif' : 'Inactif'}
                </button>
                <button
                  onClick={() => handleLoadSchedule(ep.id)}
                  className="text-xs text-violet hover:text-violet-light transition-colors"
                >
                  Planning
                </button>
                <button
                  onClick={() => handleDeleteEndpoint(ep.id)}
                  className="text-xs text-danger hover:text-red-300 transition-colors"
                >
                  Suppr
                </button>
              </div>

              {expandedSchedule === ep.id && (
                <div className="border-t border-border pt-3 space-y-2">
                  <p className="text-xs text-muted uppercase tracking-wide">Planning de publication</p>
                  {schedules[ep.id] ? (
                    <>
                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="text-xs text-muted">Max/jour</label>
                          <input
                            type="number"
                            value={schedules[ep.id].max_per_day}
                            onChange={e => handleUpdateSchedule(ep.id, { max_per_day: +e.target.value })}
                            className={inputClass + ' w-full'}
                          />
                        </div>
                        <div>
                          <label className="text-xs text-muted">Max/heure</label>
                          <input
                            type="number"
                            value={schedules[ep.id].max_per_hour}
                            onChange={e => handleUpdateSchedule(ep.id, { max_per_hour: +e.target.value })}
                            className={inputClass + ' w-full'}
                          />
                        </div>
                      </div>
                      <div>
                        <label className="text-xs text-muted">Intervalle min (minutes)</label>
                        <input
                          type="number"
                          value={schedules[ep.id].min_interval_minutes}
                          onChange={e => handleUpdateSchedule(ep.id, { min_interval_minutes: +e.target.value })}
                          className={inputClass + ' w-full'}
                        />
                      </div>
                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="text-xs text-muted">Heures debut</label>
                          <input
                            type="time"
                            value={schedules[ep.id].active_hours_start}
                            onChange={e => handleUpdateSchedule(ep.id, { active_hours_start: e.target.value })}
                            className={inputClass + ' w-full'}
                          />
                        </div>
                        <div>
                          <label className="text-xs text-muted">Heures fin</label>
                          <input
                            type="time"
                            value={schedules[ep.id].active_hours_end}
                            onChange={e => handleUpdateSchedule(ep.id, { active_hours_end: e.target.value })}
                            className={inputClass + ' w-full'}
                          />
                        </div>
                      </div>
                      <div>
                        <label className="text-xs text-muted mb-1 block">Jours actifs</label>
                        <div className="flex gap-1">
                          {DAYS.map(day => {
                            const active = schedules[ep.id].active_days?.includes(day);
                            return (
                              <button
                                key={day}
                                onClick={() => {
                                  const current = schedules[ep.id].active_days ?? [];
                                  const next = active ? current.filter(d => d !== day) : [...current, day];
                                  handleUpdateSchedule(ep.id, { active_days: next });
                                }}
                                className={`px-2 py-1 text-xs rounded transition-colors ${active ? 'bg-violet text-white' : 'bg-surface2 text-muted'}`}
                              >
                                {day}
                              </button>
                            );
                          })}
                        </div>
                      </div>
                    </>
                  ) : (
                    <p className="text-xs text-muted">Aucun planning configure</p>
                  )}
                </div>
              )}
            </div>
          ))}
          {endpoints.length === 0 && !showAddForm && (
            <p className="text-muted text-sm col-span-3">Aucun endpoint configure</p>
          )}
        </div>
      </div>

      {/* Publication Queue */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h3 className="font-title text-lg font-semibold text-white">File de publication</h3>
          <div className="flex gap-2">
            {selectedItems.size > 0 && (
              <button
                onClick={handleBulkExecute}
                className="px-3 py-1.5 bg-success/20 text-success text-sm rounded-lg hover:bg-success/30 transition-colors"
              >
                Executer ({selectedItems.size})
              </button>
            )}
            <select value={queueFilter} onChange={e => setQueueFilter(e.target.value)} className={inputClass}>
              <option value="">Tous les statuts</option>
              <option value="pending">En attente</option>
              <option value="publishing">Publication...</option>
              <option value="published">Publie</option>
              <option value="failed">Echoue</option>
              <option value="cancelled">Annule</option>
            </select>
          </div>
        </div>

        <div className="bg-surface border border-border rounded-xl p-5">
          {loading ? (
            <div className="space-y-3">{[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-lg h-12" />)}</div>
          ) : queue.length === 0 ? (
            <p className="text-center py-8 text-muted text-sm">File vide</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                    <th className="pb-3 pr-2 w-8">
                      <input
                        type="checkbox"
                        onChange={e => {
                          if (e.target.checked) setSelectedItems(new Set(queue.map(q => q.id)));
                          else setSelectedItems(new Set());
                        }}
                        className="accent-violet"
                      />
                    </th>
                    <th className="pb-3 pr-4">Contenu</th>
                    <th className="pb-3 pr-4">Type</th>
                    <th className="pb-3 pr-4">Endpoint</th>
                    <th className="pb-3 pr-4">Statut</th>
                    <th className="pb-3 pr-4">Priorite</th>
                    <th className="pb-3 pr-4">Planifie</th>
                    <th className="pb-3 pr-4">Publie</th>
                    <th className="pb-3 pr-4">Essais</th>
                    <th className="pb-3">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {queue.map(item => (
                    <tr key={item.id} className="border-b border-border/50 hover:bg-surface2/50 transition-colors">
                      <td className="py-3 pr-2">
                        <input
                          type="checkbox"
                          checked={selectedItems.has(item.id)}
                          onChange={() => toggleSelectItem(item.id)}
                          className="accent-violet"
                        />
                      </td>
                      <td className="py-3 pr-4 text-white max-w-[200px] truncate">
                        {(item.publishable as { title?: string } | undefined)?.title ?? `#${item.publishable_id}`}
                      </td>
                      <td className="py-3 pr-4 text-muted text-xs">{item.publishable_type.split('\\').pop()}</td>
                      <td className="py-3 pr-4 text-muted">{item.endpoint?.name ?? `#${item.endpoint_id}`}</td>
                      <td className="py-3 pr-4">
                        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${QUEUE_STATUS_COLORS[item.status] ?? 'bg-muted/20 text-muted'}`}>
                          {item.status}
                        </span>
                      </td>
                      <td className="py-3 pr-4">
                        <span className={`text-xs ${item.priority === 'high' ? 'text-danger' : item.priority === 'low' ? 'text-muted' : 'text-white'}`}>
                          {item.priority}
                        </span>
                      </td>
                      <td className="py-3 pr-4 text-muted text-xs">{item.scheduled_at ? new Date(item.scheduled_at).toLocaleString('fr') : '-'}</td>
                      <td className="py-3 pr-4 text-muted text-xs">{item.published_at ? new Date(item.published_at).toLocaleString('fr') : '-'}</td>
                      <td className="py-3 pr-4 text-muted">{item.attempts}/{item.max_attempts}</td>
                      <td className="py-3">
                        <div className="flex items-center gap-2">
                          {(item.status === 'pending' || item.status === 'failed') && (
                            <button
                              onClick={() => handleExecute(item.id)}
                              disabled={actionLoading === item.id}
                              className="text-xs text-success hover:text-green-300 transition-colors disabled:opacity-50"
                            >
                              {item.status === 'failed' ? 'Retry' : 'Executer'}
                            </button>
                          )}
                          {(item.status === 'pending' || item.status === 'publishing') && (
                            <button
                              onClick={() => handleCancel(item.id)}
                              disabled={actionLoading === item.id}
                              className="text-xs text-danger hover:text-red-300 transition-colors disabled:opacity-50"
                            >
                              Annuler
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {queuePagination.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 pt-4 border-t border-border">
              <span className="text-xs text-muted">{queuePagination.total} items</span>
              <div className="flex gap-2">
                <button
                  onClick={() => loadQueue(queuePagination.current_page - 1)}
                  disabled={queuePagination.current_page <= 1}
                  className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40"
                >
                  Precedent
                </button>
                <span className="px-3 py-1 text-xs text-muted">{queuePagination.current_page} / {queuePagination.last_page}</span>
                <button
                  onClick={() => loadQueue(queuePagination.current_page + 1)}
                  disabled={queuePagination.current_page >= queuePagination.last_page}
                  className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40"
                >
                  Suivant
                </button>
              </div>
            </div>
          )}
        </div>
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
