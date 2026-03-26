import { useEffect, useState, type FormEvent } from 'react';
import { usePublishing } from '../../hooks/useContentEngine';
import * as contentApi from '../../api/contentApi';
import type {
  PublishingEndpoint,
  PublicationSchedule,
  PublicationStatus,
  EndpointType,
} from '../../types/content';

const ENDPOINT_TYPES: EndpointType[] = ['firestore', 'wordpress', 'webhook', 'export'];
const STATUS_OPTIONS: PublicationStatus[] = ['pending', 'publishing', 'published', 'failed', 'cancelled'];
const DAY_LABELS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
const DAY_VALUES = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

function typeBadge(type: EndpointType) {
  const colors: Record<string, string> = {
    firestore: 'bg-amber/20 text-amber',
    wordpress: 'bg-blue-500/20 text-blue-400',
    webhook: 'bg-violet/20 text-violet-light',
    export: 'bg-muted/20 text-muted',
  };
  return (
    <span className={`px-2 py-0.5 rounded text-xs font-medium ${colors[type] ?? colors.export}`}>
      {type}
    </span>
  );
}

function statusBadge(status: PublicationStatus) {
  const colors: Record<string, string> = {
    pending: 'bg-muted/20 text-muted',
    publishing: 'bg-blue-500/20 text-blue-400 animate-pulse',
    published: 'bg-success/20 text-success',
    failed: 'bg-danger/20 text-danger',
    cancelled: 'bg-muted/20 text-muted',
  };
  return (
    <span className={`px-2 py-0.5 rounded text-xs font-medium ${colors[status] ?? colors.pending}`}>
      {status}
    </span>
  );
}

function priorityBadge(priority: string) {
  const colors: Record<string, string> = {
    high: 'bg-danger/20 text-danger',
    default: 'bg-muted/20 text-muted',
    low: 'bg-muted/20 text-muted',
  };
  return (
    <span className={`px-2 py-0.5 rounded text-xs font-medium ${colors[priority] ?? colors.default}`}>
      {priority}
    </span>
  );
}

interface EndpointForm {
  name: string;
  type: EndpointType;
  is_active: boolean;
  // Type-specific fields
  project_id: string;
  collection: string;
  wp_url: string;
  wp_username: string;
  wp_app_password: string;
  webhook_url: string;
  webhook_method: 'POST' | 'PUT';
  webhook_headers: string;
}

const emptyEndpointForm: EndpointForm = {
  name: '',
  type: 'firestore',
  is_active: true,
  project_id: '',
  collection: '',
  wp_url: '',
  wp_username: '',
  wp_app_password: '',
  webhook_url: '',
  webhook_method: 'POST',
  webhook_headers: '{}',
};

export default function PublishingDashboard() {
  const { endpoints, queue, loading, loadEndpoints, loadQueue, executeItem, cancelItem } = usePublishing();

  const [showAddEndpoint, setShowAddEndpoint] = useState(false);
  const [editingEndpoint, setEditingEndpoint] = useState<PublishingEndpoint | null>(null);
  const [endpointForm, setEndpointForm] = useState<EndpointForm>(emptyEndpointForm);
  const [savingEndpoint, setSavingEndpoint] = useState(false);
  const [endpointError, setEndpointError] = useState<string | null>(null);

  const [queueStatus, setQueueStatus] = useState<string>('');
  const [queueEndpoint, setQueueEndpoint] = useState<string>('');
  const [selectedQueueIds, setSelectedQueueIds] = useState<number[]>([]);
  const [bulkExecuting, setBulkExecuting] = useState(false);

  const [scheduleMap, setScheduleMap] = useState<Record<number, PublicationSchedule>>({});
  const [expandedSchedule, setExpandedSchedule] = useState<number | null>(null);
  const [savingSchedule, setSavingSchedule] = useState(false);

  useEffect(() => {
    loadEndpoints();
    loadQueue();
  }, []);

  useEffect(() => {
    const params: Record<string, unknown> = {};
    if (queueStatus) params.status = queueStatus;
    if (queueEndpoint) params.endpoint_id = queueEndpoint;
    loadQueue(params);
  }, [queueStatus, queueEndpoint]);

  // Load schedule for an endpoint
  const loadSchedule = async (endpointId: number) => {
    try {
      const { data } = await contentApi.fetchSchedule(endpointId);
      setScheduleMap(prev => ({ ...prev, [endpointId]: data }));
    } catch { /* silent */ }
  };

  const toggleSchedule = (endpointId: number) => {
    if (expandedSchedule === endpointId) {
      setExpandedSchedule(null);
    } else {
      setExpandedSchedule(endpointId);
      if (!scheduleMap[endpointId]) {
        loadSchedule(endpointId);
      }
    }
  };

  // Build config from form
  const buildConfig = (form: EndpointForm): Record<string, unknown> => {
    switch (form.type) {
      case 'firestore':
        return { project_id: form.project_id, collection: form.collection };
      case 'wordpress':
        return { url: form.wp_url, username: form.wp_username, app_password: form.wp_app_password };
      case 'webhook': {
        let headers = {};
        try { headers = JSON.parse(form.webhook_headers); } catch { /* keep empty */ }
        return { url: form.webhook_url, method: form.webhook_method, headers };
      }
      default:
        return {};
    }
  };

  const handleSaveEndpoint = async (e: FormEvent) => {
    e.preventDefault();
    setSavingEndpoint(true);
    setEndpointError(null);
    try {
      const payload = {
        name: endpointForm.name,
        type: endpointForm.type,
        config: buildConfig(endpointForm),
        is_active: endpointForm.is_active,
      };
      if (editingEndpoint) {
        await contentApi.updateEndpoint(editingEndpoint.id, payload);
      } else {
        await contentApi.createEndpoint(payload);
      }
      setShowAddEndpoint(false);
      setEditingEndpoint(null);
      setEndpointForm(emptyEndpointForm);
      await loadEndpoints();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Erreur lors de la sauvegarde';
      setEndpointError(message);
    } finally {
      setSavingEndpoint(false);
    }
  };

  const handleDeleteEndpoint = async (id: number) => {
    if (!confirm('Supprimer ce endpoint ?')) return;
    try {
      await contentApi.deleteEndpoint(id);
      await loadEndpoints();
    } catch { /* silent */ }
  };

  const handleEditEndpoint = (ep: PublishingEndpoint) => {
    const cfg = ep.config as Record<string, unknown>;
    setEditingEndpoint(ep);
    setEndpointForm({
      name: ep.name,
      type: ep.type,
      is_active: ep.is_active,
      project_id: (cfg.project_id as string) ?? '',
      collection: (cfg.collection as string) ?? '',
      wp_url: (cfg.url as string) ?? '',
      wp_username: (cfg.username as string) ?? '',
      wp_app_password: (cfg.app_password as string) ?? '',
      webhook_url: (cfg.url as string) ?? '',
      webhook_method: ((cfg.method as string) ?? 'POST') as 'POST' | 'PUT',
      webhook_headers: cfg.headers ? JSON.stringify(cfg.headers, null, 2) : '{}',
    });
    setShowAddEndpoint(true);
  };

  const handleExecute = async (id: number) => {
    try { await executeItem(id); } catch { /* silent */ }
  };

  const handleCancel = async (id: number) => {
    try { await cancelItem(id); } catch { /* silent */ }
  };

  const handleBulkExecute = async () => {
    setBulkExecuting(true);
    try {
      for (const id of selectedQueueIds) {
        await executeItem(id);
      }
      setSelectedQueueIds([]);
    } catch { /* silent */ }
    finally { setBulkExecuting(false); }
  };

  const handleBulkCancel = async () => {
    setBulkExecuting(true);
    try {
      for (const id of selectedQueueIds) {
        await cancelItem(id);
      }
      setSelectedQueueIds([]);
    } catch { /* silent */ }
    finally { setBulkExecuting(false); }
  };

  const toggleQueueSelect = (id: number) => {
    setSelectedQueueIds(prev =>
      prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
    );
  };

  const selectAllQueue = () => {
    if (selectedQueueIds.length === queue.length) {
      setSelectedQueueIds([]);
    } else {
      setSelectedQueueIds(queue.map(q => q.id));
    }
  };

  const handleSaveSchedule = async (endpointId: number) => {
    const sched = scheduleMap[endpointId];
    if (!sched) return;
    setSavingSchedule(true);
    try {
      const { data } = await contentApi.updateSchedule(endpointId, sched);
      setScheduleMap(prev => ({ ...prev, [endpointId]: data }));
    } catch { /* silent */ }
    finally { setSavingSchedule(false); }
  };

  const updateScheduleField = (endpointId: number, field: string, value: unknown) => {
    setScheduleMap(prev => ({
      ...prev,
      [endpointId]: { ...prev[endpointId], [field]: value } as PublicationSchedule,
    }));
  };

  const toggleScheduleDay = (endpointId: number, day: string) => {
    const sched = scheduleMap[endpointId];
    if (!sched) return;
    const days = sched.active_days.includes(day)
      ? sched.active_days.filter(d => d !== day)
      : [...sched.active_days, day];
    updateScheduleField(endpointId, 'active_days', days);
  };

  if (loading && endpoints.length === 0) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-violet" />
      </div>
    );
  }

  return (
    <div className="space-y-8">
      <h1 className="font-title text-2xl font-bold text-white">Publication</h1>

      {/* ===== SECTION 1: ENDPOINTS ===== */}
      <section>
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold text-white">Endpoints</h2>
          <button
            onClick={() => { setShowAddEndpoint(true); setEditingEndpoint(null); setEndpointForm(emptyEndpointForm); }}
            className="px-4 py-2 bg-violet hover:bg-violet/90 text-white rounded-lg text-sm font-medium transition"
          >
            + Ajouter endpoint
          </button>
        </div>

        {/* Endpoint cards grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {endpoints.map(ep => (
            <div key={ep.id} className="bg-surface rounded-xl p-5 border border-border">
              <div className="flex items-start justify-between mb-3">
                <div>
                  <h3 className="text-white font-medium">{ep.name}</h3>
                  <div className="mt-1">{typeBadge(ep.type)}</div>
                </div>
                <span className={`w-3 h-3 rounded-full ${ep.is_active ? 'bg-success' : 'bg-muted'}`} title={ep.is_active ? 'Actif' : 'Inactif'} />
              </div>
              {ep.schedule && (
                <p className="text-muted text-xs mb-3">
                  {ep.schedule.max_per_hour} articles/h, {ep.schedule.active_hours_start}-{ep.schedule.active_hours_end},{' '}
                  {ep.schedule.active_days.length} jours
                </p>
              )}
              <div className="flex gap-2">
                <button
                  onClick={() => handleEditEndpoint(ep)}
                  className="px-3 py-1 text-xs bg-surface2 hover:bg-surface2/80 text-white rounded transition"
                >
                  Modifier
                </button>
                <button
                  onClick={() => toggleSchedule(ep.id)}
                  className="px-3 py-1 text-xs bg-surface2 hover:bg-surface2/80 text-white rounded transition"
                >
                  Planning
                </button>
                <button
                  onClick={() => handleDeleteEndpoint(ep.id)}
                  className="px-3 py-1 text-xs bg-danger/20 hover:bg-danger/40 text-danger rounded transition"
                >
                  Supprimer
                </button>
              </div>

              {/* Schedule editor (collapsible) */}
              {expandedSchedule === ep.id && scheduleMap[ep.id] && (
                <div className="mt-4 pt-4 border-t border-border space-y-3">
                  <div className="grid grid-cols-3 gap-3">
                    <div>
                      <label className="text-xs text-muted block mb-1">Max/jour</label>
                      <input
                        type="number"
                        value={scheduleMap[ep.id].max_per_day}
                        onChange={e => updateScheduleField(ep.id, 'max_per_day', Number(e.target.value))}
                        className="w-full px-2 py-1.5 bg-surface2 border border-border rounded text-sm text-white focus:outline-none focus:border-violet"
                      />
                    </div>
                    <div>
                      <label className="text-xs text-muted block mb-1">Max/heure</label>
                      <input
                        type="number"
                        value={scheduleMap[ep.id].max_per_hour}
                        onChange={e => updateScheduleField(ep.id, 'max_per_hour', Number(e.target.value))}
                        className="w-full px-2 py-1.5 bg-surface2 border border-border rounded text-sm text-white focus:outline-none focus:border-violet"
                      />
                    </div>
                    <div>
                      <label className="text-xs text-muted block mb-1">Intervalle (min)</label>
                      <input
                        type="number"
                        value={scheduleMap[ep.id].min_interval_minutes}
                        onChange={e => updateScheduleField(ep.id, 'min_interval_minutes', Number(e.target.value))}
                        className="w-full px-2 py-1.5 bg-surface2 border border-border rounded text-sm text-white focus:outline-none focus:border-violet"
                      />
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="text-xs text-muted block mb-1">Heure debut</label>
                      <input
                        type="time"
                        value={scheduleMap[ep.id].active_hours_start}
                        onChange={e => updateScheduleField(ep.id, 'active_hours_start', e.target.value)}
                        className="w-full px-2 py-1.5 bg-surface2 border border-border rounded text-sm text-white focus:outline-none focus:border-violet"
                      />
                    </div>
                    <div>
                      <label className="text-xs text-muted block mb-1">Heure fin</label>
                      <input
                        type="time"
                        value={scheduleMap[ep.id].active_hours_end}
                        onChange={e => updateScheduleField(ep.id, 'active_hours_end', e.target.value)}
                        className="w-full px-2 py-1.5 bg-surface2 border border-border rounded text-sm text-white focus:outline-none focus:border-violet"
                      />
                    </div>
                  </div>
                  <div>
                    <label className="text-xs text-muted block mb-1">Jours actifs</label>
                    <div className="flex gap-1.5">
                      {DAY_LABELS.map((label, i) => (
                        <button
                          key={i}
                          onClick={() => toggleScheduleDay(ep.id, DAY_VALUES[i])}
                          className={`px-2 py-1 text-xs rounded transition ${
                            scheduleMap[ep.id].active_days.includes(DAY_VALUES[i])
                              ? 'bg-violet text-white'
                              : 'bg-surface2 text-muted hover:bg-surface2/80'
                          }`}
                        >
                          {label}
                        </button>
                      ))}
                    </div>
                  </div>
                  <div>
                    <label className="text-xs text-muted block mb-1">Pause apres N erreurs</label>
                    <input
                      type="number"
                      value={scheduleMap[ep.id].auto_pause_on_errors}
                      onChange={e => updateScheduleField(ep.id, 'auto_pause_on_errors', Number(e.target.value))}
                      className="w-24 px-2 py-1.5 bg-surface2 border border-border rounded text-sm text-white focus:outline-none focus:border-violet"
                    />
                  </div>
                  <button
                    onClick={() => handleSaveSchedule(ep.id)}
                    disabled={savingSchedule}
                    className="px-4 py-1.5 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white rounded text-sm font-medium transition"
                  >
                    {savingSchedule ? 'Sauvegarde...' : 'Sauvegarder planning'}
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>

        {/* Add/Edit endpoint form */}
        {showAddEndpoint && (
          <div className="mt-4 bg-surface rounded-xl p-6 border border-border">
            <h3 className="text-white font-medium mb-4">
              {editingEndpoint ? 'Modifier endpoint' : 'Nouvel endpoint'}
            </h3>
            <form onSubmit={handleSaveEndpoint} className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="text-xs text-muted block mb-1">Nom</label>
                  <input
                    type="text"
                    value={endpointForm.name}
                    onChange={e => setEndpointForm(f => ({ ...f, name: e.target.value }))}
                    required
                    className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                  />
                </div>
                <div>
                  <label className="text-xs text-muted block mb-1">Type</label>
                  <select
                    value={endpointForm.type}
                    onChange={e => setEndpointForm(f => ({ ...f, type: e.target.value as EndpointType }))}
                    className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                  >
                    {ENDPOINT_TYPES.map(t => (
                      <option key={t} value={t}>{t}</option>
                    ))}
                  </select>
                </div>
                <div className="flex items-end">
                  <label className="flex items-center gap-2 text-sm text-white">
                    <input
                      type="checkbox"
                      checked={endpointForm.is_active}
                      onChange={e => setEndpointForm(f => ({ ...f, is_active: e.target.checked }))}
                      className="rounded border-border bg-surface2 text-violet focus:ring-violet"
                    />
                    Actif
                  </label>
                </div>
              </div>

              {/* Type-specific fields */}
              {endpointForm.type === 'firestore' && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs text-muted block mb-1">Project ID</label>
                    <input
                      type="text"
                      value={endpointForm.project_id}
                      onChange={e => setEndpointForm(f => ({ ...f, project_id: e.target.value }))}
                      className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                    />
                  </div>
                  <div>
                    <label className="text-xs text-muted block mb-1">Collection</label>
                    <input
                      type="text"
                      value={endpointForm.collection}
                      onChange={e => setEndpointForm(f => ({ ...f, collection: e.target.value }))}
                      className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                    />
                  </div>
                </div>
              )}

              {endpointForm.type === 'wordpress' && (
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label className="text-xs text-muted block mb-1">URL WordPress</label>
                    <input
                      type="url"
                      value={endpointForm.wp_url}
                      onChange={e => setEndpointForm(f => ({ ...f, wp_url: e.target.value }))}
                      className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                    />
                  </div>
                  <div>
                    <label className="text-xs text-muted block mb-1">Username</label>
                    <input
                      type="text"
                      value={endpointForm.wp_username}
                      onChange={e => setEndpointForm(f => ({ ...f, wp_username: e.target.value }))}
                      className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                    />
                  </div>
                  <div>
                    <label className="text-xs text-muted block mb-1">App Password</label>
                    <input
                      type="password"
                      value={endpointForm.wp_app_password}
                      onChange={e => setEndpointForm(f => ({ ...f, wp_app_password: e.target.value }))}
                      className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                    />
                  </div>
                </div>
              )}

              {endpointForm.type === 'webhook' && (
                <div className="space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="text-xs text-muted block mb-1">URL Webhook</label>
                      <input
                        type="url"
                        value={endpointForm.webhook_url}
                        onChange={e => setEndpointForm(f => ({ ...f, webhook_url: e.target.value }))}
                        className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                      />
                    </div>
                    <div>
                      <label className="text-xs text-muted block mb-1">Methode</label>
                      <select
                        value={endpointForm.webhook_method}
                        onChange={e => setEndpointForm(f => ({ ...f, webhook_method: e.target.value as 'POST' | 'PUT' }))}
                        className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                      >
                        <option value="POST">POST</option>
                        <option value="PUT">PUT</option>
                      </select>
                    </div>
                  </div>
                  <div>
                    <label className="text-xs text-muted block mb-1">Headers (JSON)</label>
                    <textarea
                      value={endpointForm.webhook_headers}
                      onChange={e => setEndpointForm(f => ({ ...f, webhook_headers: e.target.value }))}
                      rows={3}
                      className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white font-mono focus:outline-none focus:border-violet"
                    />
                  </div>
                </div>
              )}

              {endpointError && (
                <p className="text-danger text-sm">{endpointError}</p>
              )}

              <div className="flex gap-3">
                <button
                  type="submit"
                  disabled={savingEndpoint}
                  className="px-4 py-2 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition"
                >
                  {savingEndpoint ? 'Sauvegarde...' : (editingEndpoint ? 'Mettre a jour' : 'Creer')}
                </button>
                <button
                  type="button"
                  onClick={() => { setShowAddEndpoint(false); setEditingEndpoint(null); }}
                  className="px-4 py-2 bg-surface2 hover:bg-surface2/80 text-white rounded-lg text-sm transition"
                >
                  Annuler
                </button>
              </div>
            </form>
          </div>
        )}
      </section>

      {/* ===== SECTION 2: PUBLICATION QUEUE ===== */}
      <section>
        <h2 className="text-lg font-semibold text-white mb-4">File de publication</h2>

        {/* Filters */}
        <div className="flex flex-wrap gap-3 mb-4">
          <select
            value={queueStatus}
            onChange={e => setQueueStatus(e.target.value)}
            className="px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
          >
            <option value="">Tous les statuts</option>
            {STATUS_OPTIONS.map(s => (
              <option key={s} value={s}>{s}</option>
            ))}
          </select>
          <select
            value={queueEndpoint}
            onChange={e => setQueueEndpoint(e.target.value)}
            className="px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
          >
            <option value="">Tous les endpoints</option>
            {endpoints.map(ep => (
              <option key={ep.id} value={String(ep.id)}>{ep.name}</option>
            ))}
          </select>
          {selectedQueueIds.length > 0 && (
            <div className="flex gap-2 ml-auto">
              <button
                onClick={handleBulkExecute}
                disabled={bulkExecuting}
                className="px-3 py-2 bg-success hover:bg-success/90 disabled:opacity-50 text-white rounded-lg text-xs font-medium transition"
              >
                Executer ({selectedQueueIds.length})
              </button>
              <button
                onClick={handleBulkCancel}
                disabled={bulkExecuting}
                className="px-3 py-2 bg-danger hover:bg-danger disabled:opacity-50 text-white rounded-lg text-xs font-medium transition"
              >
                Annuler ({selectedQueueIds.length})
              </button>
            </div>
          )}
        </div>

        {/* Queue table */}
        <div className="bg-surface rounded-xl border border-border overflow-x-auto">
          {queue.length === 0 ? (
            <p className="text-muted text-sm p-6 text-center">File vide.</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="text-muted border-b border-border">
                  <th className="py-2 px-3 text-left">
                    <input
                      type="checkbox"
                      checked={selectedQueueIds.length === queue.length && queue.length > 0}
                      onChange={selectAllQueue}
                      className="rounded border-border bg-surface2 text-violet focus:ring-violet"
                    />
                  </th>
                  <th className="py-2 px-3 text-left">Contenu</th>
                  <th className="py-2 px-3 text-left">Endpoint</th>
                  <th className="py-2 px-3 text-center">Statut</th>
                  <th className="py-2 px-3 text-center">Priorite</th>
                  <th className="py-2 px-3 text-left">Planifie</th>
                  <th className="py-2 px-3 text-left">Publie</th>
                  <th className="py-2 px-3 text-center">Tentatives</th>
                  <th className="py-2 px-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {queue.map(item => {
                  const title = (item.publishable as any)?.title ?? `#${item.publishable_id}`;
                  const epName = item.endpoint?.name ?? endpoints.find(e => e.id === item.endpoint_id)?.name ?? `#${item.endpoint_id}`;
                  return (
                    <tr key={item.id} className="border-b border-border/50 hover:bg-surface2/30">
                      <td className="py-2 px-3">
                        <input
                          type="checkbox"
                          checked={selectedQueueIds.includes(item.id)}
                          onChange={() => toggleQueueSelect(item.id)}
                          className="rounded border-border bg-surface2 text-violet focus:ring-violet"
                        />
                      </td>
                      <td className="py-2 px-3 text-white max-w-[200px] truncate">{title}</td>
                      <td className="py-2 px-3 text-muted text-xs">{epName}</td>
                      <td className="py-2 px-3 text-center">{statusBadge(item.status)}</td>
                      <td className="py-2 px-3 text-center">{priorityBadge(item.priority)}</td>
                      <td className="py-2 px-3 text-muted text-xs">
                        {item.scheduled_at ? new Date(item.scheduled_at).toLocaleString('fr-FR') : '-'}
                      </td>
                      <td className="py-2 px-3 text-muted text-xs">
                        {item.published_at ? new Date(item.published_at).toLocaleString('fr-FR') : '-'}
                      </td>
                      <td className="py-2 px-3 text-center text-muted text-xs">
                        {item.attempts}/{item.max_attempts}
                      </td>
                      <td className="py-2 px-3 text-right">
                        <div className="flex gap-1 justify-end">
                          {item.status === 'pending' && (
                            <>
                              <button onClick={() => handleExecute(item.id)} className="px-2 py-0.5 text-xs bg-success/20 text-success hover:bg-success/40 rounded transition">
                                Executer
                              </button>
                              <button onClick={() => handleCancel(item.id)} className="px-2 py-0.5 text-xs bg-danger/20 text-danger hover:bg-danger/40 rounded transition">
                                Annuler
                              </button>
                            </>
                          )}
                          {item.status === 'failed' && (
                            <button onClick={() => handleExecute(item.id)} className="px-2 py-0.5 text-xs bg-amber/20 text-amber hover:bg-amber/40 rounded transition">
                              Retenter
                            </button>
                          )}
                          {item.status === 'published' && item.external_url && (
                            <a
                              href={item.external_url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="px-2 py-0.5 text-xs bg-violet/20 text-violet-light hover:bg-violet/40 rounded transition"
                            >
                              Voir
                            </a>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          )}
        </div>
      </section>
    </div>
  );
}
