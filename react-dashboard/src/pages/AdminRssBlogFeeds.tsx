import React, { useEffect, useState } from 'react';
import api from '../api/client';

interface RssBlogFeed {
  id: number;
  name: string;
  url: string;
  base_url: string | null;
  language: string;
  country: string | null;
  category: string | null;
  active: boolean;
  fetch_about: boolean;
  fetch_pattern_inference: boolean;
  fetch_interval_hours: number;
  last_scraped_at: string | null;
  last_contacts_found: number;
  total_contacts_found: number;
  about_fetched_at: string | null;
  last_error: string | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

interface FormData {
  id?: number;
  name: string;
  url: string;
  base_url: string;
  language: string;
  country: string;
  category: string;
  active: boolean;
  fetch_about: boolean;
  fetch_pattern_inference: boolean;
  fetch_interval_hours: number;
  notes: string;
}

const EMPTY_FORM: FormData = {
  name: '',
  url: '',
  base_url: '',
  language: 'fr',
  country: '',
  category: '',
  active: true,
  fetch_about: true,
  fetch_pattern_inference: false,
  fetch_interval_hours: 6,
  notes: '',
};

/**
 * Option D — P7 : Page admin CRUD des feeds RSS de blogs.
 * Route: /admin/rss-blog-feeds
 * Role: admin
 */
export default function AdminRssBlogFeeds() {
  const [feeds, setFeeds] = useState<RssBlogFeed[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<'all' | 'active' | 'inactive'>('all');
  const [search, setSearch] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState<FormData>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [toast, setToast] = useState<{ type: 'ok' | 'err'; message: string } | null>(null);

  useEffect(() => {
    loadFeeds();
  }, [filter, search]);

  const showToast = (type: 'ok' | 'err', message: string) => {
    setToast({ type, message });
    setTimeout(() => setToast(null), 4000);
  };

  const loadFeeds = async () => {
    setLoading(true);
    try {
      const params: Record<string, string> = {};
      if (filter === 'active') params.active = '1';
      if (filter === 'inactive') params.active = '0';
      if (search.trim()) params.search = search.trim();

      const { data } = await api.get('/rss-blog-feeds', { params });
      // Laravel paginate returns { data: [...], current_page, ... }
      setFeeds(Array.isArray(data) ? data : data.data ?? []);
    } catch (e) {
      console.error(e);
      showToast('err', 'Erreur chargement des feeds');
    } finally {
      setLoading(false);
    }
  };

  const openCreate = () => {
    setForm(EMPTY_FORM);
    setError('');
    setShowModal(true);
  };

  const openEdit = (feed: RssBlogFeed) => {
    setForm({
      id: feed.id,
      name: feed.name,
      url: feed.url,
      base_url: feed.base_url ?? '',
      language: feed.language,
      country: feed.country ?? '',
      category: feed.category ?? '',
      active: feed.active,
      fetch_about: feed.fetch_about,
      fetch_pattern_inference: feed.fetch_pattern_inference,
      fetch_interval_hours: feed.fetch_interval_hours,
      notes: feed.notes ?? '',
    });
    setError('');
    setShowModal(true);
  };

  const handleSave = async () => {
    setSaving(true);
    setError('');
    try {
      const payload = {
        ...form,
        base_url: form.base_url || null,
        country: form.country || null,
        category: form.category || null,
        notes: form.notes || null,
      };

      if (form.id) {
        await api.put(`/rss-blog-feeds/${form.id}`, payload);
        showToast('ok', `Feed "${form.name}" mis à jour.`);
      } else {
        await api.post('/rss-blog-feeds', payload);
        showToast('ok', `Feed "${form.name}" créé.`);
      }
      setShowModal(false);
      loadFeeds();
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      const msg = err.response?.data?.message
        ?? Object.values(err.response?.data?.errors ?? {}).flat().join(', ')
        ?? 'Erreur sauvegarde';
      setError(msg);
    } finally {
      setSaving(false);
    }
  };

  const handleToggle = async (feed: RssBlogFeed) => {
    try {
      await api.put(`/rss-blog-feeds/${feed.id}`, { active: !feed.active });
      showToast('ok', `Feed ${!feed.active ? 'activé' : 'désactivé'}.`);
      loadFeeds();
    } catch {
      showToast('err', 'Erreur toggle');
    }
  };

  const handleScrape = async (feed: RssBlogFeed) => {
    try {
      await api.post(`/rss-blog-feeds/${feed.id}/scrape`);
      showToast('ok', `Scrape dispatché pour "${feed.name}". Résultats dans quelques minutes.`);
    } catch {
      showToast('err', 'Erreur dispatch');
    }
  };

  const handleDelete = async (feed: RssBlogFeed) => {
    if (!confirm(`Désactiver le feed "${feed.name}" ?\n(Il sera conservé en base mais ne sera plus scrapé.)`)) return;
    try {
      await api.delete(`/rss-blog-feeds/${feed.id}`);
      showToast('ok', `Feed "${feed.name}" désactivé.`);
      loadFeeds();
    } catch {
      showToast('err', 'Erreur suppression');
    }
  };

  const fmtDate = (d: string | null) => d ? new Date(d).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—';

  return (
    <div className="p-6 max-w-7xl mx-auto">
      <div className="flex items-start justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">📝 RSS Blog Feeds</h1>
          <p className="text-muted text-sm mt-1">
            Flux RSS de blogs à scraper pour extraire les auteurs (zéro risque ban : XML public, UA déclaré).
          </p>
        </div>
        <button
          onClick={openCreate}
          className="px-4 py-2 bg-violet-600 hover:bg-violet-500 rounded text-white font-medium"
        >
          + Ajouter un feed
        </button>
      </div>

      {toast && (
        <div className={`mb-4 p-3 rounded ${toast.type === 'ok' ? 'bg-emerald-900/40 text-emerald-200' : 'bg-rose-900/40 text-rose-200'}`}>
          {toast.message}
        </div>
      )}

      <div className="flex gap-3 mb-4">
        <select value={filter} onChange={e => setFilter(e.target.value as 'all' | 'active' | 'inactive')} className="bg-card border border-border rounded px-3 py-1.5 text-sm">
          <option value="all">Tous</option>
          <option value="active">Actifs uniquement</option>
          <option value="inactive">Inactifs uniquement</option>
        </select>
        <input
          type="search"
          placeholder="Rechercher par nom ou URL..."
          value={search}
          onChange={e => setSearch(e.target.value)}
          className="flex-1 bg-card border border-border rounded px-3 py-1.5 text-sm"
        />
      </div>

      {loading ? (
        <p className="text-muted">Chargement...</p>
      ) : feeds.length === 0 ? (
        <p className="text-muted">Aucun feed trouvé. Ajoutez-en un pour démarrer.</p>
      ) : (
        <div className="overflow-x-auto bg-card rounded-lg border border-border">
          <table className="w-full text-sm">
            <thead className="bg-bg-secondary text-left">
              <tr>
                <th className="p-3">Nom / Catégorie</th>
                <th className="p-3">URL</th>
                <th className="p-3">Pays / Lang</th>
                <th className="p-3">Options</th>
                <th className="p-3">Dernière run</th>
                <th className="p-3 text-right">Trouvés</th>
                <th className="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {feeds.map(feed => (
                <tr key={feed.id} className={`border-t border-border ${!feed.active ? 'opacity-50' : ''}`}>
                  <td className="p-3">
                    <div className="font-medium">{feed.name}</div>
                    {feed.category && <div className="text-xs text-muted mt-0.5">{feed.category}</div>}
                    {feed.last_error && <div className="text-xs text-rose-400 mt-0.5" title={feed.last_error}>⚠️ erreur</div>}
                  </td>
                  <td className="p-3">
                    <a href={feed.url} target="_blank" rel="noopener noreferrer" className="text-violet-400 hover:underline text-xs">
                      {feed.url.length > 60 ? feed.url.slice(0, 57) + '…' : feed.url}
                    </a>
                  </td>
                  <td className="p-3 text-xs">
                    {feed.country ?? '—'}<br />
                    <span className="text-muted">{feed.language}</span>
                  </td>
                  <td className="p-3 text-xs">
                    {feed.fetch_about && <span title="Fetch page /about 1×/7j" className="inline-block px-1.5 py-0.5 bg-blue-900/40 text-blue-300 rounded mr-1">about</span>}
                    {feed.fetch_pattern_inference && <span title="Inférence pattern firstname.lastname@domain (non vérifié)" className="inline-block px-1.5 py-0.5 bg-amber-900/40 text-amber-300 rounded mr-1">pattern</span>}
                    <span className="text-muted">{feed.fetch_interval_hours}h</span>
                  </td>
                  <td className="p-3 text-xs text-muted">{fmtDate(feed.last_scraped_at)}</td>
                  <td className="p-3 text-right text-xs">
                    <span className="font-mono">{feed.last_contacts_found}</span>
                    <span className="text-muted"> / </span>
                    <span className="font-mono text-muted">{feed.total_contacts_found}</span>
                  </td>
                  <td className="p-3 text-right">
                    <div className="flex gap-1 justify-end">
                      <button onClick={() => handleScrape(feed)} title="Scrape maintenant" className="px-2 py-1 bg-emerald-900/40 hover:bg-emerald-800/60 rounded text-xs" disabled={!feed.active}>
                        🕷️
                      </button>
                      <button onClick={() => openEdit(feed)} title="Modifier" className="px-2 py-1 bg-blue-900/40 hover:bg-blue-800/60 rounded text-xs">
                        ✏️
                      </button>
                      <button onClick={() => handleToggle(feed)} title={feed.active ? 'Désactiver' : 'Activer'} className="px-2 py-1 bg-amber-900/40 hover:bg-amber-800/60 rounded text-xs">
                        {feed.active ? '⏸' : '▶'}
                      </button>
                      <button onClick={() => handleDelete(feed)} title="Supprimer" className="px-2 py-1 bg-rose-900/40 hover:bg-rose-800/60 rounded text-xs">
                        🗑️
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showModal && (
        <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4" onClick={() => setShowModal(false)}>
          <div className="bg-card rounded-lg border border-border max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <h2 className="text-xl font-bold mb-4">{form.id ? 'Modifier' : 'Nouveau'} feed RSS blog</h2>

            {error && <div className="mb-4 p-3 bg-rose-900/40 text-rose-200 rounded text-sm">{error}</div>}

            <div className="grid grid-cols-2 gap-4">
              <div className="col-span-2">
                <label className="block text-sm text-muted mb-1">Nom *</label>
                <input type="text" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} className="w-full bg-bg-secondary border border-border rounded px-3 py-2 text-sm" />
              </div>
              <div className="col-span-2">
                <label className="block text-sm text-muted mb-1">URL RSS *</label>
                <input type="url" value={form.url} onChange={e => setForm({ ...form, url: e.target.value })} placeholder="https://example.com/feed" className="w-full bg-bg-secondary border border-border rounded px-3 py-2 text-sm" />
              </div>
              <div className="col-span-2">
                <label className="block text-sm text-muted mb-1">Base URL (optionnel, pour /about)</label>
                <input type="url" value={form.base_url} onChange={e => setForm({ ...form, base_url: e.target.value })} placeholder="Auto-déduit depuis l'URL RSS si vide" className="w-full bg-bg-secondary border border-border rounded px-3 py-2 text-sm" />
              </div>
              <div>
                <label className="block text-sm text-muted mb-1">Pays</label>
                <input type="text" value={form.country} onChange={e => setForm({ ...form, country: e.target.value })} placeholder="France, Thaïlande..." className="w-full bg-bg-secondary border border-border rounded px-3 py-2 text-sm" />
              </div>
              <div>
                <label className="block text-sm text-muted mb-1">Langue</label>
                <input type="text" value={form.language} onChange={e => setForm({ ...form, language: e.target.value })} className="w-full bg-bg-secondary border border-border rounded px-3 py-2 text-sm" maxLength={5} />
              </div>
              <div>
                <label className="block text-sm text-muted mb-1">Catégorie</label>
                <input type="text" value={form.category} onChange={e => setForm({ ...form, category: e.target.value })} placeholder="expat, voyage, tech..." className="w-full bg-bg-secondary border border-border rounded px-3 py-2 text-sm" />
              </div>
              <div>
                <label className="block text-sm text-muted mb-1">Intervalle (heures)</label>
                <input type="number" value={form.fetch_interval_hours} onChange={e => setForm({ ...form, fetch_interval_hours: Number(e.target.value) })} min={1} max={168} className="w-full bg-bg-secondary border border-border rounded px-3 py-2 text-sm" />
              </div>
              <div className="col-span-2 space-y-2">
                <label className="flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={form.active} onChange={e => setForm({ ...form, active: e.target.checked })} />
                  Actif (scrapé par cron)
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={form.fetch_about} onChange={e => setForm({ ...form, fetch_about: e.target.checked })} />
                  Fetch page <code>/about</code> 1×/semaine pour enrichir emails (safe)
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={form.fetch_pattern_inference} onChange={e => setForm({ ...form, fetch_pattern_inference: e.target.checked })} />
                  <span>Inférence pattern <code>firstname.lastname@domain</code>
                    <span className="text-amber-400 text-xs ml-2">⚠️ emails non vérifiés, risque bounce</span>
                  </span>
                </label>
              </div>
              <div className="col-span-2">
                <label className="block text-sm text-muted mb-1">Notes</label>
                <textarea value={form.notes} onChange={e => setForm({ ...form, notes: e.target.value })} rows={2} className="w-full bg-bg-secondary border border-border rounded px-3 py-2 text-sm" />
              </div>
            </div>

            <div className="flex justify-end gap-2 mt-6">
              <button onClick={() => setShowModal(false)} className="px-4 py-2 bg-bg-secondary border border-border rounded text-sm">
                Annuler
              </button>
              <button onClick={handleSave} disabled={saving || !form.name || !form.url} className="px-4 py-2 bg-violet-600 hover:bg-violet-500 rounded text-white text-sm font-medium disabled:opacity-50">
                {saving ? 'Enregistrement...' : form.id ? 'Modifier' : 'Créer'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
