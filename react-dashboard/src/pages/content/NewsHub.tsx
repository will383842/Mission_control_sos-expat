import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
  getRssFeeds,
  createRssFeed,
  updateRssFeed,
  deleteRssFeed,
  fetchFeedNow,
  getNewsSettings,
  updateNewsSettings,
  getNewsItems,
  generateItem,
  skipItem,
  unpublishItem,
  generateBatch,
  getNewsStats,
  getNewsProgress,
  type RssFeed,
  type RssFeedItem,
  type NewsStats,
  type NewsQuotaSettings,
  type CreateRssFeedData,
  type PaginatedResponse,
} from '../../api/news';
import { toast } from '../../components/Toast';
import { inputClass, errMsg, formatDate } from './helpers';
import { Modal } from '../../ui/Modal';
import { Button } from '../../ui/Button';

// ── Helpers locaux ──────────────────────────────────────────

function timeAgo(iso: string | null): string {
  if (!iso) return '—';
  const diff = Date.now() - new Date(iso).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'à l\'instant';
  if (mins < 60) return `il y a ${mins}min`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `il y a ${hrs}h`;
  return `il y a ${Math.floor(hrs / 24)}j`;
}

function scoreBadge(score: number | null): string {
  if (score === null) return 'bg-muted/20 text-muted';
  if (score >= 65) return 'bg-emerald-500/20 text-emerald-400';
  if (score >= 40) return 'bg-amber-500/20 text-amber-400';
  return 'bg-red-500/20 text-red-400';
}

function similarityBadge(score: number | null): string {
  if (score === null) return 'bg-muted/20 text-muted';
  const pct = score * 100;
  if (pct < 20) return 'bg-emerald-500/20 text-emerald-400';
  if (pct < 30) return 'bg-amber-500/20 text-amber-400';
  return 'bg-red-500/20 text-red-400';
}

const STATUS_COLORS: Record<string, string> = {
  pending: 'bg-blue-500/20 text-blue-400',
  generating: 'bg-amber-500/20 text-amber-400 animate-pulse',
  published: 'bg-emerald-500/20 text-emerald-400',
  skipped: 'bg-muted/20 text-muted line-through',
  irrelevant: 'bg-muted/20 text-muted',
  failed: 'bg-red-500/20 text-red-400',
};

const STATUS_LABELS: Record<string, string> = {
  pending: 'En attente',
  generating: 'Génération...',
  published: 'Publié',
  skipped: 'Ignoré',
  irrelevant: 'Non pertinent',
  failed: 'Échoué',
};

const CATEGORY_OPTIONS = [
  { value: '', label: 'Toutes catégories' },
  { value: 'visa', label: 'Visa' },
  { value: 'logement', label: 'Logement' },
  { value: 'sante', label: 'Santé' },
  { value: 'fiscalite', label: 'Fiscalité' },
  { value: 'administratif', label: 'Administratif' },
  { value: 'securite', label: 'Sécurité' },
  { value: 'emploi', label: 'Emploi' },
  { value: 'retraite', label: 'Retraite' },
  { value: 'quotidien', label: 'Quotidien' },
  { value: 'transport', label: 'Transport' },
  { value: 'culture', label: 'Culture' },
  { value: 'autre', label: 'Autre' },
];

const LANGUAGE_OPTIONS = [
  { value: 'fr', label: 'Français' },
  { value: 'en', label: 'English' },
  { value: 'es', label: 'Español' },
  { value: 'de', label: 'Deutsch' },
  { value: 'pt', label: 'Português' },
  { value: 'ru', label: 'Русский' },
  { value: 'zh', label: '中文' },
  { value: 'hi', label: 'हिन्दी' },
  { value: 'ar', label: 'العربية' },
];

// ── Confirm Modal simple (wrapper around <Modal>) ──────────────
function ConfirmModal({ title, message, onConfirm, onCancel }: {
  title: string;
  message: string;
  onConfirm: () => void;
  onCancel: () => void;
}) {
  return (
    <Modal
      open={true}
      onClose={onCancel}
      title={title}
      size="sm"
      footer={
        <>
          <Button variant="ghost" onClick={onCancel}>Annuler</Button>
          <Button variant="danger" onClick={onConfirm}>Confirmer</Button>
        </>
      }
    >
      <p className="text-sm text-muted">{message}</p>
    </Modal>
  );
}

// ── FeedsTab ────────────────────────────────────────────────
function FeedsTab({ onStatsRefresh }: { onStatsRefresh: () => void }) {
  const [feeds, setFeeds] = useState<RssFeed[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [showAddModal, setShowAddModal] = useState(false);
  const [editFeed, setEditFeed] = useState<RssFeed | null>(null);
  const [confirmDelete, setConfirmDelete] = useState<{ id: number; name: string } | null>(null);

  const loadFeeds = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getRssFeeds();
      setFeeds((res.data as unknown as { data: RssFeed[] }).data ?? []);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadFeeds(); }, [loadFeeds]);

  const handleToggleActive = async (feed: RssFeed) => {
    setActionLoading(feed.id);
    try {
      await updateRssFeed(feed.id, { active: !feed.active });
      toast('success', feed.active ? 'Flux désactivé' : 'Flux activé');
      loadFeeds();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleFetchNow = async (id: number) => {
    setActionLoading(id);
    try {
      await fetchFeedNow(id);
      toast('success', 'Collecte lancée');
      setTimeout(loadFeeds, 2000);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = async (id: number) => {
    setActionLoading(id);
    setConfirmDelete(null);
    try {
      await deleteRssFeed(id);
      toast('success', 'Flux supprimé');
      loadFeeds();
      onStatsRefresh();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <p className="text-sm text-muted">{feeds.length} flux configurés</p>
        <button
          onClick={() => setShowAddModal(true)}
          className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Ajouter un flux
        </button>
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-12">
          <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
        </div>
      ) : feeds.length === 0 ? (
        <div className="text-center py-12 text-muted">Aucun flux RSS configuré</div>
      ) : (
        <div className="space-y-2">
          {feeds.map(feed => (
            <div key={feed.id} className="bg-surface border border-border rounded-lg p-4">
              <div className="flex items-start gap-3 flex-wrap">
                {/* Toggle actif */}
                <button
                  onClick={() => handleToggleActive(feed)}
                  disabled={actionLoading === feed.id}
                  className={`mt-0.5 w-9 h-5 rounded-full transition-colors flex-shrink-0 relative ${
                    feed.active ? 'bg-emerald-500' : 'bg-gray-600'
                  }`}
                >
                  <span className={`absolute top-0.5 w-4 h-4 bg-white rounded-full transition-transform ${
                    feed.active ? 'translate-x-4' : 'translate-x-0.5'
                  }`} />
                </button>

                {/* Infos */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-medium text-white text-sm">{feed.name}</span>
                    <span className="text-[11px] bg-blue-500/20 text-blue-400 px-1.5 py-0.5 rounded">
                      {feed.language.toUpperCase()}
                    </span>
                    {feed.category && (
                      <span className="text-[11px] bg-violet/20 text-violet-light px-1.5 py-0.5 rounded">
                        {feed.category}
                      </span>
                    )}
                    <span className="text-[11px] text-muted">
                      Seuil: {feed.relevance_threshold}% · Toutes les {feed.fetch_interval_hours}h
                    </span>
                  </div>
                  <p className="text-xs text-muted truncate mt-0.5">{feed.url}</p>
                  <div className="flex items-center gap-3 mt-1.5 flex-wrap">
                    <span className="text-[11px] text-muted">
                      Dernière collecte: {timeAgo(feed.last_fetched_at)}
                    </span>
                    {feed.items_pending_count !== undefined && (
                      <span className="text-[11px] text-blue-400">{feed.items_pending_count} en attente</span>
                    )}
                    {feed.items_published_count !== undefined && (
                      <span className="text-[11px] text-emerald-400">{feed.items_published_count} publiés</span>
                    )}
                    {feed.items_total_count !== undefined && (
                      <span className="text-[11px] text-muted">{feed.items_total_count} total</span>
                    )}
                  </div>
                </div>

                {/* Actions */}
                <div className="flex items-center gap-1.5 flex-shrink-0">
                  <button
                    onClick={() => handleFetchNow(feed.id)}
                    disabled={actionLoading === feed.id}
                    className="px-2.5 py-1 bg-blue-600/80 hover:bg-blue-600 text-white text-xs rounded transition-colors disabled:opacity-50"
                  >
                    {actionLoading === feed.id ? '...' : '↻ Fetch'}
                  </button>
                  <button
                    onClick={() => setEditFeed(feed)}
                    disabled={actionLoading === feed.id}
                    className="px-2.5 py-1 bg-surface border border-border hover:border-violet/60 text-muted hover:text-white text-xs rounded transition-colors disabled:opacity-50"
                  >
                    Éditer
                  </button>
                  <button
                    onClick={() => setConfirmDelete({ id: feed.id, name: feed.name })}
                    disabled={actionLoading === feed.id}
                    className="px-2.5 py-1 bg-red-600/30 hover:bg-red-600/60 text-red-400 text-xs rounded transition-colors disabled:opacity-50"
                  >
                    Suppr.
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {confirmDelete && (
        <ConfirmModal
          title="Supprimer ce flux"
          message={`Supprimer "${confirmDelete.name}" et tous ses items ? Cette action est irréversible.`}
          onConfirm={() => handleDelete(confirmDelete.id)}
          onCancel={() => setConfirmDelete(null)}
        />
      )}

      {showAddModal && (
        <AddFeedModal
          onClose={() => setShowAddModal(false)}
          onSaved={() => { setShowAddModal(false); loadFeeds(); onStatsRefresh(); }}
        />
      )}

      {editFeed && (
        <EditFeedModal
          feed={editFeed}
          onClose={() => setEditFeed(null)}
          onSaved={() => { setEditFeed(null); loadFeeds(); }}
        />
      )}
    </div>
  );
}

// ── AddFeedModal ────────────────────────────────────────────
function AddFeedModal({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
  const [form, setForm] = useState<CreateRssFeedData>({
    name: '',
    url: '',
    language: 'fr',
    country: '',
    category: '',
    active: true,
    fetch_interval_hours: 6,
    relevance_threshold: 65,
    notes: '',
  });
  const [saving, setSaving] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      const payload: CreateRssFeedData = { ...form };
      if (!payload.country) delete payload.country;
      if (!payload.category) delete payload.category;
      if (!payload.notes) delete payload.notes;
      await createRssFeed(payload);
      toast('success', 'Flux ajouté avec succès');
      onSaved();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      open={true}
      onClose={onClose}
      title="Ajouter un flux RSS"
      size="md"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Annuler</Button>
          <Button variant="primary" onClick={() => handleSubmit({ preventDefault: () => {} } as React.FormEvent)} loading={saving}>
            Ajouter le flux
          </Button>
        </>
      }
    >
      <form onSubmit={handleSubmit} className="space-y-3">
          <div>
            <label className="block text-xs text-muted mb-1">Nom *</label>
            <input
              className={inputClass + ' w-full'}
              value={form.name}
              onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
              placeholder="Ex: Le Figaro International"
              required
            />
          </div>
          <div>
            <label className="block text-xs text-muted mb-1">URL du flux RSS *</label>
            <input
              className={inputClass + ' w-full'}
              value={form.url}
              onChange={e => setForm(f => ({ ...f, url: e.target.value }))}
              placeholder="https://example.com/feed.xml"
              required
              type="url"
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs text-muted mb-1">Langue *</label>
              <select
                className={inputClass + ' w-full'}
                value={form.language}
                onChange={e => setForm(f => ({ ...f, language: e.target.value }))}
              >
                {LANGUAGE_OPTIONS.map(l => (
                  <option key={l.value} value={l.value}>{l.label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Pays (optionnel)</label>
              <input
                className={inputClass + ' w-full'}
                value={form.country || ''}
                onChange={e => setForm(f => ({ ...f, country: e.target.value }))}
                placeholder="fr, us, de..."
                maxLength={5}
              />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs text-muted mb-1">Catégorie</label>
              <select
                className={inputClass + ' w-full'}
                value={form.category || ''}
                onChange={e => setForm(f => ({ ...f, category: e.target.value || undefined }))}
              >
                {CATEGORY_OPTIONS.map(c => (
                  <option key={c.value} value={c.value}>{c.label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Intervalle de collecte</label>
              <select
                className={inputClass + ' w-full'}
                value={form.fetch_interval_hours}
                onChange={e => setForm(f => ({ ...f, fetch_interval_hours: Number(e.target.value) }))}
              >
                {[1, 2, 4, 6, 12, 24].map(h => (
                  <option key={h} value={h}>Toutes les {h}h</option>
                ))}
              </select>
            </div>
          </div>
          <div>
            <label className="block text-xs text-muted mb-1">
              Seuil de pertinence : <span className="text-white font-medium">{form.relevance_threshold}%</span>
            </label>
            <input
              type="range"
              min={0}
              max={100}
              value={form.relevance_threshold}
              onChange={e => setForm(f => ({ ...f, relevance_threshold: Number(e.target.value) }))}
              className="w-full accent-violet"
            />
            <div className="flex justify-between text-[10px] text-muted mt-0.5">
              <span>0% (tout)</span>
              <span>100% (strict)</span>
            </div>
          </div>
          <div>
            <label className="block text-xs text-muted mb-1">Notes (optionnel)</label>
            <textarea
              className={inputClass + ' w-full resize-none'}
              rows={2}
              value={form.notes || ''}
              onChange={e => setForm(f => ({ ...f, notes: e.target.value }))}
              placeholder="Commentaires sur ce flux..."
            />
          </div>
          <div className="flex items-center gap-2">
            <input
              type="checkbox"
              id="feed_active"
              checked={form.active}
              onChange={e => setForm(f => ({ ...f, active: e.target.checked }))}
              className="accent-violet"
            />
            <label htmlFor="feed_active" className="text-sm text-muted">Activer immédiatement</label>
          </div>
          {/* Submit via footer button */}
          <button type="submit" hidden aria-hidden="true" />
      </form>
    </Modal>
  );
}

// ── EditFeedModal ───────────────────────────────────────────
function EditFeedModal({ feed, onClose, onSaved }: { feed: RssFeed; onClose: () => void; onSaved: () => void }) {
  const [form, setForm] = useState({
    name: feed.name,
    url: feed.url,
    language: feed.language,
    country: feed.country ?? '',
    category: feed.category ?? '',
    active: feed.active,
    fetch_interval_hours: feed.fetch_interval_hours ?? 6,
    relevance_threshold: feed.relevance_threshold ?? 65,
    notes: feed.notes ?? '',
  });
  const [saving, setSaving] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      const payload: Partial<typeof form> = { ...form };
      if (!payload.country) delete payload.country;
      if (!payload.category) delete payload.category;
      if (!payload.notes) delete payload.notes;
      await updateRssFeed(feed.id, payload);
      toast('success', 'Flux mis à jour');
      onSaved();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      open={true}
      onClose={onClose}
      title="Éditer le flux RSS"
      size="md"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Annuler</Button>
          <Button variant="primary" onClick={() => handleSubmit({ preventDefault: () => {} } as React.FormEvent)} loading={saving}>
            Enregistrer
          </Button>
        </>
      }
    >
      <form onSubmit={handleSubmit} className="space-y-3">
          <div>
            <label className="block text-xs text-muted mb-1">Nom *</label>
            <input
              className={inputClass + ' w-full'}
              value={form.name}
              onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
              required
            />
          </div>
          <div>
            <label className="block text-xs text-muted mb-1">URL du flux RSS *</label>
            <input
              className={inputClass + ' w-full'}
              value={form.url}
              onChange={e => setForm(f => ({ ...f, url: e.target.value }))}
              required
              type="url"
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs text-muted mb-1">Langue *</label>
              <select
                className={inputClass + ' w-full'}
                value={form.language}
                onChange={e => setForm(f => ({ ...f, language: e.target.value }))}
              >
                {LANGUAGE_OPTIONS.map(l => (
                  <option key={l.value} value={l.value}>{l.label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Pays (optionnel)</label>
              <input
                className={inputClass + ' w-full'}
                value={form.country}
                onChange={e => setForm(f => ({ ...f, country: e.target.value }))}
                placeholder="fr, us, de..."
                maxLength={5}
              />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs text-muted mb-1">Catégorie</label>
              <select
                className={inputClass + ' w-full'}
                value={form.category}
                onChange={e => setForm(f => ({ ...f, category: e.target.value }))}
              >
                {CATEGORY_OPTIONS.map(c => (
                  <option key={c.value} value={c.value}>{c.label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Intervalle de collecte</label>
              <select
                className={inputClass + ' w-full'}
                value={form.fetch_interval_hours}
                onChange={e => setForm(f => ({ ...f, fetch_interval_hours: Number(e.target.value) }))}
              >
                {[1, 2, 4, 6, 12, 24].map(h => (
                  <option key={h} value={h}>Toutes les {h}h</option>
                ))}
              </select>
            </div>
          </div>
          <div>
            <label className="block text-xs text-muted mb-1">
              Seuil de pertinence : <span className="text-white font-medium">{form.relevance_threshold}%</span>
            </label>
            <input
              type="range"
              min={0}
              max={100}
              value={form.relevance_threshold}
              onChange={e => setForm(f => ({ ...f, relevance_threshold: Number(e.target.value) }))}
              className="w-full accent-violet"
            />
            <div className="flex justify-between text-[10px] text-muted mt-0.5">
              <span>0% (tout)</span>
              <span>100% (strict)</span>
            </div>
          </div>
          <div>
            <label className="block text-xs text-muted mb-1">Notes (optionnel)</label>
            <textarea
              className={inputClass + ' w-full resize-none'}
              rows={2}
              value={form.notes}
              onChange={e => setForm(f => ({ ...f, notes: e.target.value }))}
              placeholder="Commentaires sur ce flux..."
            />
          </div>
          <div className="flex items-center gap-2">
            <input
              type="checkbox"
              id="edit_feed_active"
              checked={form.active}
              onChange={e => setForm(f => ({ ...f, active: e.target.checked }))}
              className="accent-violet"
            />
            <label htmlFor="edit_feed_active" className="text-sm text-muted">Flux actif</label>
          </div>
          <button type="submit" hidden aria-hidden="true" />
      </form>
    </Modal>
  );
}

// ── ItemsTab ────────────────────────────────────────────────
function ItemsTab({ stats, onStatsRefresh }: { stats: NewsStats | null; onStatsRefresh: () => void }) {
  const [items, setItems] = useState<RssFeedItem[]>([]);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [filterFeedId, setFilterFeedId] = useState<number | ''>('');
  const [filterMinScore, setFilterMinScore] = useState('');
  const [sortBy, setSortBy] = useState('score_desc');
  const [feeds, setFeeds] = useState<RssFeed[]>([]);
  const [showBatchModal, setShowBatchModal] = useState(false);
  const [quota, setQuota] = useState<NewsQuotaSettings | null>(null);
  const [editingQuota, setEditingQuota] = useState(false);
  const [quotaInput, setQuotaInput] = useState('');
  const [progress, setProgress] = useState<{ status?: string; completed?: number; total?: number; current_title?: string } | null>(null);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const loadFeeds = useCallback(async () => {
    try {
      const res = await getRssFeeds();
      setFeeds((res.data as unknown as { data: RssFeed[] }).data ?? []);
    } catch { /* ignore */ }
  }, []);

  const loadQuota = useCallback(async () => {
    try {
      const res = await getNewsSettings();
      setQuota((res.data as unknown as { data: NewsQuotaSettings }).data);
    } catch { /* ignore */ }
  }, []);

  const loadItems = useCallback(async (page = 1) => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = { status: 'pending', page };
      if (filterFeedId !== '') params.feed_id = filterFeedId;
      if (filterMinScore) params.relevance_score_min = Number(filterMinScore);
      if (sortBy === 'score_desc') params.sort = 'relevance_score_desc';
      else params.sort = 'published_at_desc';
      const res = await getNewsItems(params as Parameters<typeof getNewsItems>[0]);
      const data = res.data as unknown as PaginatedResponse<RssFeedItem>;
      setItems(data.data ?? []);
      setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setLoading(false);
    }
  }, [filterFeedId, filterMinScore, sortBy]);

  useEffect(() => {
    loadFeeds();
    loadQuota();
  }, [loadFeeds, loadQuota]);

  useEffect(() => { loadItems(1); }, [loadItems]);

  // Polling progression
  const startPolling = useCallback(() => {
    if (pollRef.current) return;
    pollRef.current = setInterval(async () => {
      try {
        const res = await getNewsProgress();
        const prog = (res.data as unknown as { data: typeof progress }).data;
        setProgress(prog);
        if (prog?.status !== 'running') {
          clearInterval(pollRef.current!);
          pollRef.current = null;
          loadItems(1);
          loadQuota();
          onStatsRefresh();
        }
      } catch { /* ignore */ }
    }, 3000);
  }, [loadItems, loadQuota, onStatsRefresh]);

  useEffect(() => {
    return () => {
      if (pollRef.current) clearInterval(pollRef.current);
    };
  }, []);

  const handleGenerate = async (id: number) => {
    setActionLoading(id);
    try {
      await generateItem(id);
      toast('success', 'Génération lancée');
      setTimeout(() => { loadItems(pagination.current_page); onStatsRefresh(); }, 2000);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleSkip = async (id: number) => {
    setActionLoading(id);
    try {
      await skipItem(id);
      toast('info', 'Article ignoré');
      loadItems(pagination.current_page);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleSaveQuota = async () => {
    const val = Number(quotaInput);
    if (!val || val < 1) return;
    try {
      await updateNewsSettings(val);
      toast('success', 'Quota mis à jour');
      setEditingQuota(false);
      loadQuota();
    } catch (err) {
      toast('error', errMsg(err));
    }
  };

  const quotaUsed = quota?.generated_today ?? stats?.quota?.generated_today ?? 0;
  const quotaLimit = quota?.quota ?? stats?.quota?.daily_limit ?? 15;
  const quotaPct = quotaLimit > 0 ? Math.min(100, Math.round((quotaUsed / quotaLimit) * 100)) : 0;

  return (
    <div className="space-y-4">
      {/* Quota bar */}
      <div className="bg-surface border border-border rounded-lg p-4 space-y-2">
        <div className="flex items-center justify-between gap-3 flex-wrap">
          <div className="flex items-center gap-2">
            <span className="text-sm text-white font-medium">Quota du jour</span>
            {editingQuota ? (
              <div className="flex items-center gap-1.5">
                <input
                  type="number"
                  min={1}
                  max={200}
                  className={inputClass + ' w-20 py-0.5 text-xs'}
                  value={quotaInput}
                  onChange={e => setQuotaInput(e.target.value)}
                  autoFocus
                />
                <button onClick={handleSaveQuota} className="text-emerald-400 hover:text-emerald-300 text-xs">✓</button>
                <button onClick={() => setEditingQuota(false)} className="text-muted hover:text-white text-xs">✕</button>
              </div>
            ) : (
              <button
                onClick={() => { setEditingQuota(true); setQuotaInput(String(quotaLimit)); }}
                className="text-xs text-violet hover:text-violet/80 transition-colors"
              >
                Modifier
              </button>
            )}
          </div>
          <span className="text-sm text-white font-medium tabular-nums">{quotaUsed} / {quotaLimit}</span>
        </div>
        <div className="h-3 bg-bg rounded-full overflow-hidden">
          <div
            className={`h-full rounded-full transition-all ${
              quotaPct >= 90 ? 'bg-red-500' : quotaPct >= 70 ? 'bg-amber-500' : 'bg-emerald-500'
            }`}
            style={{ width: `${quotaPct}%` }}
          />
        </div>
        {quota && (
          <p className="text-[11px] text-muted">Remise à zéro : {formatDate(quota.last_reset_date)}</p>
        )}
      </div>

      {/* Progression */}
      {progress?.status === 'running' && (
        <div className="bg-amber-500/10 border border-amber-500/30 rounded-lg p-4 space-y-2">
          <div className="flex items-center gap-2">
            <div className="w-4 h-4 border-2 border-amber-400 border-t-transparent rounded-full animate-spin" />
            <span className="text-sm text-amber-400 font-medium">Génération en cours...</span>
            {progress.total && progress.completed !== undefined && (
              <span className="text-xs text-muted ml-auto">{progress.completed} / {progress.total}</span>
            )}
          </div>
          {progress.current_title && (
            <p className="text-xs text-muted truncate">↳ {progress.current_title}</p>
          )}
          {progress.total && progress.completed !== undefined && progress.total > 0 && (
            <div className="h-1.5 bg-bg rounded-full overflow-hidden">
              <div
                className="h-full bg-amber-400 transition-all"
                style={{ width: `${Math.round((progress.completed / progress.total) * 100)}%` }}
              />
            </div>
          )}
        </div>
      )}

      {/* Filtres + actions */}
      <div className="flex items-center gap-2 flex-wrap">
        <select
          className={inputClass + ' text-xs py-1.5'}
          value={filterFeedId}
          onChange={e => setFilterFeedId(e.target.value === '' ? '' : Number(e.target.value))}
        >
          <option value="">Tous les flux</option>
          {feeds.map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
        </select>
        <input
          type="number"
          className={inputClass + ' text-xs py-1.5 w-28'}
          placeholder="Score min"
          value={filterMinScore}
          onChange={e => setFilterMinScore(e.target.value)}
          min={0}
          max={100}
        />
        <select
          className={inputClass + ' text-xs py-1.5'}
          value={sortBy}
          onChange={e => setSortBy(e.target.value)}
        >
          <option value="score_desc">Score décroissant</option>
          <option value="date_desc">Date décroissante</option>
        </select>
        <div className="ml-auto flex items-center gap-2">
          <button
            onClick={() => setShowBatchModal(true)}
            className="px-3 py-1.5 bg-violet hover:bg-violet/90 text-white text-xs rounded-lg transition-colors"
          >
            ⚡ Générer le batch
          </button>
        </div>
      </div>

      {/* Table */}
      {loading ? (
        <div className="flex items-center justify-center py-12">
          <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
        </div>
      ) : items.length === 0 ? (
        <div className="text-center py-12 text-muted">Aucun article en attente</div>
      ) : (
        <>
          <div className="overflow-x-auto rounded-lg border border-border">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border bg-surface/50">
                  <th className="text-left px-3 py-2.5 text-xs font-semibold text-muted">Titre</th>
                  <th className="text-left px-3 py-2.5 text-xs font-semibold text-muted">Source</th>
                  <th className="text-left px-3 py-2.5 text-xs font-semibold text-muted w-20">Score</th>
                  <th className="text-left px-3 py-2.5 text-xs font-semibold text-muted w-24">Catégorie</th>
                  <th className="text-left px-3 py-2.5 text-xs font-semibold text-muted w-24">Publiée</th>
                  <th className="text-right px-3 py-2.5 text-xs font-semibold text-muted w-28">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {items.map(item => (
                  <tr key={item.id} className="hover:bg-surface/50 transition-colors">
                    <td className="px-3 py-2.5">
                      <a
                        href={item.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-white hover:text-violet transition-colors line-clamp-2 text-xs leading-snug"
                      >
                        {item.title}
                      </a>
                      {item.relevance_reason && (
                        <p className="text-[11px] text-muted mt-0.5 line-clamp-1">{item.relevance_reason}</p>
                      )}
                    </td>
                    <td className="px-3 py-2.5">
                      <span className="text-xs text-muted">{item.source_name ?? item.feed?.name ?? '—'}</span>
                    </td>
                    <td className="px-3 py-2.5">
                      <span className={`text-xs px-1.5 py-0.5 rounded font-medium ${scoreBadge(item.relevance_score)}`}>
                        {item.relevance_score !== null ? item.relevance_score + '%' : '—'}
                      </span>
                    </td>
                    <td className="px-3 py-2.5">
                      <span className="text-xs text-muted">{item.relevance_category ?? '—'}</span>
                    </td>
                    <td className="px-3 py-2.5">
                      <span className="text-xs text-muted">{timeAgo(item.published_at)}</span>
                    </td>
                    <td className="px-3 py-2.5">
                      <div className="flex items-center gap-1 justify-end">
                        <button
                          onClick={() => handleGenerate(item.id)}
                          disabled={actionLoading === item.id}
                          className="px-2 py-0.5 bg-violet/80 hover:bg-violet text-white text-xs rounded transition-colors disabled:opacity-50"
                        >
                          {actionLoading === item.id ? '...' : 'Générer'}
                        </button>
                        <button
                          onClick={() => handleSkip(item.id)}
                          disabled={actionLoading === item.id}
                          className="px-2 py-0.5 bg-muted/20 hover:bg-muted/40 text-muted text-xs rounded transition-colors disabled:opacity-50"
                        >
                          Ignorer
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {pagination.last_page > 1 && (
            <div className="flex items-center justify-between text-xs text-muted">
              <span>{pagination.total} articles au total</span>
              <div className="flex items-center gap-1">
                <button
                  onClick={() => loadItems(pagination.current_page - 1)}
                  disabled={pagination.current_page <= 1}
                  className="px-2.5 py-1 rounded bg-surface border border-border hover:border-violet/50 disabled:opacity-40 transition-colors"
                >
                  ← Préc.
                </button>
                <span className="px-2">{pagination.current_page} / {pagination.last_page}</span>
                <button
                  onClick={() => loadItems(pagination.current_page + 1)}
                  disabled={pagination.current_page >= pagination.last_page}
                  className="px-2.5 py-1 rounded bg-surface border border-border hover:border-violet/50 disabled:opacity-40 transition-colors"
                >
                  Suiv. →
                </button>
              </div>
            </div>
          )}
        </>
      )}

      {showBatchModal && (
        <BatchModal
          feeds={feeds}
          onClose={() => setShowBatchModal(false)}
          onLaunched={() => {
            setShowBatchModal(false);
            startPolling();
            onStatsRefresh();
          }}
        />
      )}
    </div>
  );
}

// ── BatchModal ──────────────────────────────────────────────
function BatchModal({ feeds, onClose, onLaunched }: {
  feeds: RssFeed[];
  onClose: () => void;
  onLaunched: () => void;
}) {
  const [limit, setLimit] = useState('10');
  const [feedId, setFeedId] = useState('');
  const [minRelevance, setMinRelevance] = useState('65');
  const [launching, setLaunching] = useState(false);

  const handleLaunch = async () => {
    setLaunching(true);
    try {
      const params: { limit?: number; feed_id?: number; min_relevance?: number } = {};
      if (limit) params.limit = Number(limit);
      if (feedId) params.feed_id = Number(feedId);
      if (minRelevance) params.min_relevance = Number(minRelevance);
      const res = await generateBatch(params);
      const data = res.data as unknown as { dispatched: number; remaining_quota: number };
      toast('success', `${data.dispatched} articles envoyés en génération (quota restant: ${data.remaining_quota})`);
      onLaunched();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setLaunching(false);
    }
  };

  return (
    <Modal
      open={true}
      onClose={onClose}
      title="Générer un batch"
      size="sm"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Annuler</Button>
          <Button variant="primary" onClick={handleLaunch} loading={launching}>⚡ Lancer</Button>
        </>
      }
    >
      <div className="space-y-3">
        <div>
          <label className="block text-xs text-muted mb-1">Nombre max à générer</label>
          <input
            type="number"
            min={1}
            max={50}
            className={inputClass + ' w-full'}
            value={limit}
            onChange={e => setLimit(e.target.value)}
          />
        </div>
        <div>
          <label className="block text-xs text-muted mb-1">Flux spécifique (optionnel)</label>
          <select
            className={inputClass + ' w-full'}
            value={feedId}
            onChange={e => setFeedId(e.target.value)}
          >
            <option value="">Tous les flux</option>
            {feeds.map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
          </select>
        </div>
        <div>
          <label className="block text-xs text-muted mb-1">Score minimum : <span className="text-white">{minRelevance}%</span></label>
          <input
            type="range"
            min={0}
            max={100}
            value={minRelevance}
            onChange={e => setMinRelevance(e.target.value)}
            className="w-full accent-violet"
          />
        </div>
      </div>
    </Modal>
  );
}

// ── GeneratedTab ─────────────────────────────────────────────
function GeneratedTab() {
  const [items, setItems] = useState<RssFeedItem[]>([]);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [confirmUnpublish, setConfirmUnpublish] = useState<{ id: number; title: string } | null>(null);
  const [filterStatus, setFilterStatus] = useState('');
  const [filterFeedId, setFilterFeedId] = useState<number | ''>('');
  const [filterDateFrom, setFilterDateFrom] = useState('');
  const [feeds, setFeeds] = useState<RssFeed[]>([]);

  const loadFeeds = useCallback(async () => {
    try {
      const res = await getRssFeeds();
      setFeeds((res.data as unknown as { data: RssFeed[] }).data ?? []);
    } catch { /* ignore */ }
  }, []);

  const loadItems = useCallback(async (page = 1) => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = { page };
      // On exclut pending et irrelevant — si pas de filtre statut
      if (filterStatus) {
        params.status = filterStatus;
      } else {
        // On récupère published, failed, skipped, generating
        params.status = 'published,failed,skipped,generating';
      }
      if (filterFeedId !== '') params.feed_id = filterFeedId;
      if (filterDateFrom) params.date_from = filterDateFrom;
      const res = await getNewsItems(params as Parameters<typeof getNewsItems>[0]);
      const data = res.data as unknown as PaginatedResponse<RssFeedItem>;
      setItems(data.data ?? []);
      setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setLoading(false);
    }
  }, [filterStatus, filterFeedId, filterDateFrom]);

  useEffect(() => { loadFeeds(); }, [loadFeeds]);
  useEffect(() => { loadItems(1); }, [loadItems]);

  const handleUnpublish = async (id: number) => {
    setConfirmUnpublish(null);
    setActionLoading(id);
    try {
      await unpublishItem(id);
      toast('success', 'Article dépublié — retiré de sos-expat.com');
      loadItems(pagination.current_page);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  return (
    <div className="space-y-4">
      {/* Modale confirmation dépublication */}
      {confirmUnpublish && (
        <ConfirmModal
          title="Dépublier cet article ?"
          message={`"${confirmUnpublish.title}" sera retiré de sos-expat.com immédiatement. L'action est réversible depuis le Blog admin.`}
          onConfirm={() => handleUnpublish(confirmUnpublish.id)}
          onCancel={() => setConfirmUnpublish(null)}
        />
      )}
      {/* Filtres */}
      <div className="flex items-center gap-2 flex-wrap">
        <select
          className={inputClass + ' text-xs py-1.5'}
          value={filterStatus}
          onChange={e => setFilterStatus(e.target.value)}
        >
          <option value="">Tous statuts</option>
          <option value="published">Publiés</option>
          <option value="failed">Échoués</option>
          <option value="skipped">Ignorés</option>
        </select>
        <select
          className={inputClass + ' text-xs py-1.5'}
          value={filterFeedId}
          onChange={e => setFilterFeedId(e.target.value === '' ? '' : Number(e.target.value))}
        >
          <option value="">Tous les flux</option>
          {feeds.map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
        </select>
        <input
          type="date"
          className={inputClass + ' text-xs py-1.5'}
          value={filterDateFrom}
          onChange={e => setFilterDateFrom(e.target.value)}
        />
      </div>

      {/* Table */}
      {loading ? (
        <div className="flex items-center justify-center py-12">
          <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
        </div>
      ) : items.length === 0 ? (
        <div className="text-center py-12 text-muted">Aucun article généré</div>
      ) : (
        <>
          <div className="overflow-x-auto rounded-lg border border-border">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border bg-surface/50">
                  <th className="text-left px-3 py-2.5 text-xs font-semibold text-muted">Titre</th>
                  <th className="text-left px-3 py-2.5 text-xs font-semibold text-muted">Source</th>
                  <th className="text-left px-3 py-2.5 text-xs font-semibold text-muted w-24">Similarité</th>
                  <th className="text-left px-3 py-2.5 text-xs font-semibold text-muted w-24">Statut</th>
                  <th className="text-left px-3 py-2.5 text-xs font-semibold text-muted w-24">Généré</th>
                  <th className="text-right px-3 py-2.5 text-xs font-semibold text-muted w-16">Lien</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {items.map(item => {
                  const simPct = item.similarity_score !== null ? item.similarity_score * 100 : null;
                  const needsReview = simPct !== null && simPct >= 20 && simPct < 30;
                  return (
                    <tr key={item.id} className="hover:bg-surface/50 transition-colors">
                      <td className="px-3 py-2.5">
                        <div className="flex items-start gap-1.5">
                          {needsReview && (
                            <span title="Similarité à vérifier" className="text-amber-400 text-xs flex-shrink-0 mt-0.5">⚠️</span>
                          )}
                          <a
                            href={item.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-white hover:text-violet transition-colors line-clamp-2 text-xs leading-snug"
                          >
                            {item.title}
                          </a>
                        </div>
                        {item.error_message && (
                          <p className="text-[11px] text-red-400 mt-0.5 line-clamp-1">{item.error_message}</p>
                        )}
                      </td>
                      <td className="px-3 py-2.5">
                        <span className="text-xs text-muted">{item.source_name ?? item.feed?.name ?? '—'}</span>
                      </td>
                      <td className="px-3 py-2.5">
                        {simPct !== null ? (
                          <span className={`text-xs px-1.5 py-0.5 rounded font-medium ${similarityBadge(item.similarity_score)}`}>
                            {simPct.toFixed(0)}%
                          </span>
                        ) : (
                          <span className="text-xs text-muted">—</span>
                        )}
                      </td>
                      <td className="px-3 py-2.5">
                        <span className={`text-xs px-1.5 py-0.5 rounded font-medium ${STATUS_COLORS[item.status] ?? 'bg-muted/20 text-muted'}`}>
                          {STATUS_LABELS[item.status] ?? item.status}
                        </span>
                      </td>
                      <td className="px-3 py-2.5">
                        <span className="text-xs text-muted">{timeAgo(item.generated_at)}</span>
                      </td>
                      <td className="px-3 py-2.5 text-right">
                        <div className="flex items-center gap-1 justify-end">
                          {item.blog_article_uuid && (
                            <a
                              href={`https://sos-expat.com/${item.language ?? 'fr'}-fr/actualites-expats`}
                              target="_blank"
                              rel="noopener noreferrer"
                              title={`UUID: ${item.blog_article_uuid}`}
                              className="text-xs text-violet hover:text-violet/80 transition-colors"
                            >
                              Voir →
                            </a>
                          )}
                          {item.status === 'published' && (
                            <button
                              onClick={() => setConfirmUnpublish({ id: item.id, title: item.title })}
                              disabled={actionLoading === item.id}
                              title="Retirer de sos-expat.com"
                              className="px-2 py-0.5 bg-red-600/20 hover:bg-red-600/50 text-red-400 text-xs rounded transition-colors disabled:opacity-50"
                            >
                              {actionLoading === item.id ? '...' : 'Dépublier'}
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {pagination.last_page > 1 && (
            <div className="flex items-center justify-between text-xs text-muted">
              <span>{pagination.total} articles au total</span>
              <div className="flex items-center gap-1">
                <button
                  onClick={() => loadItems(pagination.current_page - 1)}
                  disabled={pagination.current_page <= 1}
                  className="px-2.5 py-1 rounded bg-surface border border-border hover:border-violet/50 disabled:opacity-40 transition-colors"
                >
                  ← Préc.
                </button>
                <span className="px-2">{pagination.current_page} / {pagination.last_page}</span>
                <button
                  onClick={() => loadItems(pagination.current_page + 1)}
                  disabled={pagination.current_page >= pagination.last_page}
                  className="px-2.5 py-1 rounded bg-surface border border-border hover:border-violet/50 disabled:opacity-40 transition-colors"
                >
                  Suiv. →
                </button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

// ── NewsHub (page principale) ───────────────────────────────
export default function NewsHub() {
  const [activeTab, setActiveTab] = useState<'sources' | 'generation' | 'generated'>('sources');
  const [stats, setStats] = useState<NewsStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

  const loadStats = useCallback(async () => {
    setStatsLoading(true);
    try {
      const res = await getNewsStats();
      setStats(res.data as unknown as NewsStats);
    } catch { /* ignore */ }
    finally { setStatsLoading(false); }
  }, []);

  useEffect(() => { loadStats(); }, [loadStats]);

  const pending = stats?.items_by_status?.pending ?? 0;
  const published = stats?.items_by_status?.published ?? 0;
  const quotaUsed = stats?.quota?.generated_today ?? 0;
  const quotaLimit = stats?.quota?.daily_limit ?? 0;

  const tabs: { key: 'sources' | 'generation' | 'generated'; label: string; emoji: string }[] = [
    { key: 'sources', label: 'Sources', emoji: '📋' },
    { key: 'generation', label: 'Génération', emoji: '⚡' },
    { key: 'generated', label: 'Contenus générés', emoji: '✅' },
  ];

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="space-y-3">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">📰 News RSS</h2>
          <p className="text-sm text-muted mt-0.5">Collecte et génération d'articles depuis des flux RSS</p>
        </div>

        {/* Stats bar */}
        <div className="flex items-center gap-2 flex-wrap">
          <div className="flex items-center gap-1.5 bg-surface border border-border rounded-lg px-3 py-1.5">
            <span className="w-2 h-2 rounded-full bg-blue-400 flex-shrink-0" />
            <span className="text-xs text-muted">À traiter :</span>
            {statsLoading ? (
              <span className="w-8 h-3 bg-surface2 rounded animate-pulse" />
            ) : (
              <span className="text-xs font-semibold text-white">{pending}</span>
            )}
          </div>
          <div className="flex items-center gap-1.5 bg-surface border border-border rounded-lg px-3 py-1.5">
            <span className="w-2 h-2 rounded-full bg-emerald-400 flex-shrink-0" />
            <span className="text-xs text-muted">Publiés auj. :</span>
            {statsLoading ? (
              <span className="w-8 h-3 bg-surface2 rounded animate-pulse" />
            ) : (
              <span className="text-xs font-semibold text-white">{published}</span>
            )}
          </div>
          <div className="flex items-center gap-1.5 bg-surface border border-border rounded-lg px-3 py-1.5">
            <span className="text-xs text-muted">Quota :</span>
            {statsLoading ? (
              <span className="w-12 h-3 bg-surface2 rounded animate-pulse" />
            ) : (
              <span className="text-xs font-semibold text-white">{quotaUsed}/{quotaLimit}</span>
            )}
          </div>
          {stats && (
            <div className="flex items-center gap-1.5 bg-surface border border-border rounded-lg px-3 py-1.5">
              <span className="text-xs text-muted">Flux actifs :</span>
              <span className="text-xs font-semibold text-white">{stats.active_feeds}</span>
            </div>
          )}
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-border">
        <div className="flex gap-1">
          {tabs.map(tab => (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key)}
              className={`px-4 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px ${
                activeTab === tab.key
                  ? 'border-violet text-white'
                  : 'border-transparent text-muted hover:text-gray-300'
              }`}
            >
              <span>{tab.emoji}</span> {tab.label}
              {tab.key === 'generation' && pending > 0 && (
                <span className="ml-1.5 bg-blue-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">
                  {pending}
                </span>
              )}
            </button>
          ))}
        </div>
      </div>

      {/* Tab content */}
      <div>
        {activeTab === 'sources' && <FeedsTab onStatsRefresh={loadStats} />}
        {activeTab === 'generation' && <ItemsTab stats={stats} onStatsRefresh={loadStats} />}
        {activeTab === 'generated' && <GeneratedTab />}
      </div>
    </div>
  );
}
