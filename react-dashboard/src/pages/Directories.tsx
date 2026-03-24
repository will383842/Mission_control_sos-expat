import React, { useEffect, useState, useCallback } from 'react';
import api from '../api/client';
import { useAuth } from '../hooks/useAuth';

interface DirectoryEntry {
  id: number;
  name: string;
  url: string;
  domain: string;
  category: string;
  country: string | null;
  language: string | null;
  status: string;
  contacts_extracted: number;
  contacts_created: number;
  pages_scraped: number;
  last_scraped_at: string | null;
  cooldown_until: string | null;
  metadata: Record<string, any> | null;
  notes: string | null;
  created_by: number | null;
  created_by_user?: { id: number; name: string } | null;
  created_at: string;
}

interface DirectoryStats {
  total_directories: number;
  total_contacts_created: number;
  by_status: Record<string, number>;
  by_category: { category: string; count: number; total_contacts: number }[];
}

const STATUS_COLORS: Record<string, { bg: string; text: string; label: string }> = {
  pending:   { bg: 'bg-gray-600/20', text: 'text-gray-400', label: 'En attente' },
  scraping:  { bg: 'bg-blue-500/20', text: 'text-blue-400', label: 'En cours...' },
  completed: { bg: 'bg-emerald-500/20', text: 'text-emerald-400', label: 'Terminé' },
  failed:    { bg: 'bg-red-500/20', text: 'text-red-400', label: 'Erreur' },
};

const CATEGORY_ICONS: Record<string, string> = {
  consulat: '🏛️', association: '🤝', ecole: '🏫', institut_culturel: '🎭', chambre_commerce: '🏢',
  presse: '📺', blog: '📝', podcast_radio: '🎙️', influenceur: '✨',
  avocat: '⚖️', immobilier: '🏠', assurance: '🛡️', banque_fintech: '🏦',
  traducteur: '🌐', agence_voyage: '✈️', emploi: '💼',
  communaute_expat: '🌍', groupe_whatsapp_telegram: '💬', coworking_coliving: '🏡',
  logement: '🔑', lieu_communautaire: '☕',
  backlink: '🔗', annuaire: '📚', plateforme_nomad: '🧭', partenaire: '🤝',
  // Legacy
  school: '🏫', press: '📺', blogger: '📝', consulats: '🏛️',
  lawyer: '⚖️', travel_agency: '✈️', real_estate: '🏠',
};

const CATEGORY_LABELS: Record<string, string> = {
  consulat: 'Consulats', association: 'Associations', ecole: 'Écoles', institut_culturel: 'Instituts culturels',
  chambre_commerce: 'Chambres commerce', presse: 'Presse', blog: 'Blogs', podcast_radio: 'Podcasts/Radios',
  influenceur: 'Influenceurs', avocat: 'Avocats', immobilier: 'Immobilier', assurance: 'Assurances',
  banque_fintech: 'Banques', traducteur: 'Traducteurs', agence_voyage: 'Agences voyage', emploi: 'Emploi',
  communaute_expat: 'Communautés', groupe_whatsapp_telegram: 'Groupes WA/TG',
  coworking_coliving: 'Coworkings', logement: 'Logement', lieu_communautaire: 'Lieux',
  backlink: 'Backlinks', annuaire: 'Annuaires', plateforme_nomad: 'Plateformes nomad', partenaire: 'Partenaires',
  school: 'Écoles', press: 'Presse', blogger: 'Blogs', consulats: 'Consulats',
  lawyer: 'Avocats', travel_agency: 'Agences voyage', real_estate: 'Immobilier',
};

export default function Directories() {
  const { user } = useAuth();
  const isAdmin = user?.role === 'admin';

  const [directories, setDirectories] = useState<DirectoryEntry[]>([]);
  const [stats, setStats] = useState<DirectoryStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState({ category: '', country: '', status: '', search: '' });
  const [showForm, setShowForm] = useState(false);
  const [formData, setFormData] = useState({ name: '', url: '', category: 'school', country: '', language: '', notes: '' });
  const [saving, setSaving] = useState(false);
  const [scraping, setScraping] = useState<Set<number>>(new Set());
  const [successMsg, setSuccessMsg] = useState('');
  const [errorMsg, setErrorMsg] = useState('');
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [expandedContacts, setExpandedContacts] = useState<any[]>([]);

  const load = useCallback(async () => {
    try {
      const params: Record<string, string> = {};
      if (filter.category) params.category = filter.category;
      if (filter.country) params.country = filter.country;
      if (filter.status) params.status = filter.status;
      if (filter.search) params.search = filter.search;

      const [dirRes, statsRes] = await Promise.all([
        api.get('/directories', { params }),
        api.get('/directories/stats'),
      ]);
      setDirectories(dirRes.data);
      setStats(statsRes.data);
    } catch { /* ignore */ }
    finally { setLoading(false); }
  }, [filter]);

  useEffect(() => { load(); }, [load]);

  const flash = (msg: string, type: 'success' | 'error') => {
    if (type === 'success') { setSuccessMsg(msg); setErrorMsg(''); }
    else { setErrorMsg(msg); setSuccessMsg(''); }
    setTimeout(() => { setSuccessMsg(''); setErrorMsg(''); }, 5000);
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await api.post('/directories', formData);
      flash('Annuaire ajouté !', 'success');
      setShowForm(false);
      setFormData({ name: '', url: '', category: 'school', country: '', language: '', notes: '' });
      load();
    } catch (err: any) {
      flash(err.response?.data?.message || 'Erreur lors de la création', 'error');
    } finally { setSaving(false); }
  };

  const handleScrape = async (dir: DirectoryEntry) => {
    setScraping(prev => new Set(prev).add(dir.id));
    try {
      const { data } = await api.post(`/directories/${dir.id}/scrape`);
      flash(data.message, 'success');
      // Poll for completion
      setTimeout(() => load(), 3000);
      setTimeout(() => load(), 10000);
      setTimeout(() => load(), 30000);
    } catch (err: any) {
      flash(err.response?.data?.message || 'Erreur scraping', 'error');
    } finally {
      setScraping(prev => { const n = new Set(prev); n.delete(dir.id); return n; });
    }
  };

  const handleDelete = async (dir: DirectoryEntry) => {
    if (!confirm(`Supprimer l'annuaire "${dir.name}" ?`)) return;
    try {
      await api.delete(`/directories/${dir.id}`);
      flash('Annuaire supprimé', 'success');
      load();
    } catch { flash('Erreur suppression', 'error'); }
  };

  const toggleExpand = async (dir: DirectoryEntry) => {
    if (expandedId === dir.id) {
      setExpandedId(null);
      return;
    }
    setExpandedId(dir.id);
    try {
      const { data } = await api.get(`/directories/${dir.id}/contacts`);
      setExpandedContacts(data);
    } catch { setExpandedContacts([]); }
  };

  const isOnCooldown = (dir: DirectoryEntry) => {
    if (!dir.cooldown_until) return false;
    return new Date(dir.cooldown_until) > new Date();
  };

  // Unique categories & countries from data
  const categories = [...new Set(directories.map(d => d.category))].sort();
  const countries = [...new Set(directories.map(d => d.country).filter(Boolean))].sort() as string[];

  if (loading) return (
    <div className="flex items-center justify-center h-32">
      <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">📚 Annuaires & Répertoires</h2>
          <p className="text-muted text-sm mt-1">
            Sources de données : les annuaires sont scrapés pour extraire des contacts individuels.
          </p>
        </div>
        {isAdmin && (
          <button onClick={() => setShowForm(!showForm)}
            className="bg-violet hover:bg-violet/80 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            + Ajouter un annuaire
          </button>
        )}
      </div>

      {/* Flash messages */}
      {successMsg && <div className="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-3 text-emerald-400 text-sm">{successMsg}</div>}
      {errorMsg && <div className="bg-red-500/10 border border-red-500/20 rounded-lg p-3 text-red-400 text-sm">{errorMsg}</div>}

      {/* Stats cards */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div className="bg-surface border border-border rounded-xl p-4">
            <p className="text-2xl font-bold text-white">{stats.total_directories}</p>
            <p className="text-xs text-muted">Annuaires</p>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4">
            <p className="text-2xl font-bold text-emerald-400">{stats.total_contacts_created}</p>
            <p className="text-xs text-muted">Contacts extraits</p>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4">
            <p className="text-2xl font-bold text-blue-400">{stats.by_status?.completed || 0}</p>
            <p className="text-xs text-muted">Scrapés</p>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4">
            <p className="text-2xl font-bold text-amber">{stats.by_status?.pending || 0}</p>
            <p className="text-xs text-muted">En attente</p>
          </div>
        </div>
      )}

      {/* Category breakdown */}
      {stats && stats.by_category.length > 0 && (
        <div className="bg-surface border border-border rounded-xl p-4">
          <h3 className="font-title font-semibold text-white text-sm mb-3">Par catégorie</h3>
          <div className="flex flex-wrap gap-2">
            {stats.by_category.map(cat => (
              <button key={cat.category}
                onClick={() => setFilter(f => ({ ...f, category: f.category === cat.category ? '' : cat.category }))}
                className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors border ${
                  filter.category === cat.category
                    ? 'bg-violet/20 border-violet text-violet-light'
                    : 'bg-surface2 border-border text-gray-300 hover:border-gray-500'
                }`}>
                {CATEGORY_ICONS[cat.category] || '📁'} {CATEGORY_LABELS[cat.category] || cat.category}
                <span className="ml-1 text-muted">({cat.count})</span>
                <span className="ml-1 text-emerald-400">{cat.total_contacts} contacts</span>
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Add form */}
      {showForm && isAdmin && (
        <form onSubmit={handleCreate} className="bg-surface border border-violet/30 rounded-xl p-5 space-y-4">
          <h3 className="font-title font-semibold text-white">Ajouter un annuaire</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs text-muted mb-1">Nom *</label>
              <input value={formData.name} onChange={e => setFormData(f => ({ ...f, name: e.target.value }))}
                required className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm" placeholder="AEFE - Écoles françaises" />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">URL *</label>
              <input value={formData.url} onChange={e => setFormData(f => ({ ...f, url: e.target.value }))}
                required type="url" className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm" placeholder="https://aefe.fr/etablissements" />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Catégorie *</label>
              <select value={formData.category} onChange={e => setFormData(f => ({ ...f, category: e.target.value }))}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm">
                {Object.entries(CATEGORY_LABELS).filter(([v]) => !['school','press','blogger','consulats','lawyer','travel_agency','real_estate'].includes(v)).map(([val, label]) => (
                  <option key={val} value={val}>{CATEGORY_ICONS[val] || '📁'} {label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Pays</label>
              <input value={formData.country} onChange={e => setFormData(f => ({ ...f, country: e.target.value }))}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm" placeholder="France" />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Langue</label>
              <select value={formData.language} onChange={e => setFormData(f => ({ ...f, language: e.target.value }))}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm">
                <option value="">--</option>
                <option value="fr">Français</option>
                <option value="en">Anglais</option>
                <option value="es">Espagnol</option>
                <option value="de">Allemand</option>
                <option value="pt">Portugais</option>
                <option value="it">Italien</option>
                <option value="ar">Arabe</option>
                <option value="zh">Chinois</option>
                <option value="ja">Japonais</option>
                <option value="ko">Coréen</option>
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Notes</label>
              <input value={formData.notes} onChange={e => setFormData(f => ({ ...f, notes: e.target.value }))}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm" placeholder="Infos supplémentaires..." />
            </div>
          </div>
          <div className="flex gap-2">
            <button type="submit" disabled={saving}
              className="bg-violet hover:bg-violet/80 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
              {saving ? 'Enregistrement...' : 'Ajouter'}
            </button>
            <button type="button" onClick={() => setShowForm(false)}
              className="bg-surface2 hover:bg-surface2/80 text-gray-300 px-4 py-2 rounded-lg text-sm transition-colors">
              Annuler
            </button>
          </div>
        </form>
      )}

      {/* Filters */}
      <div className="flex flex-wrap gap-2">
        <input value={filter.search} onChange={e => setFilter(f => ({ ...f, search: e.target.value }))}
          placeholder="Rechercher..." className="bg-bg border border-border rounded-lg px-3 py-1.5 text-white text-sm w-48" />
        {categories.length > 1 && (
          <select value={filter.category} onChange={e => setFilter(f => ({ ...f, category: e.target.value }))}
            className="bg-bg border border-border rounded-lg px-3 py-1.5 text-white text-sm">
            <option value="">Toutes catégories</option>
            {categories.map(c => <option key={c} value={c}>{CATEGORY_ICONS[c] || ''} {CATEGORY_LABELS[c] || c}</option>)}
          </select>
        )}
        {countries.length > 1 && (
          <select value={filter.country} onChange={e => setFilter(f => ({ ...f, country: e.target.value }))}
            className="bg-bg border border-border rounded-lg px-3 py-1.5 text-white text-sm">
            <option value="">Tous pays</option>
            {countries.map(c => <option key={c} value={c}>{c}</option>)}
          </select>
        )}
        <select value={filter.status} onChange={e => setFilter(f => ({ ...f, status: e.target.value }))}
          className="bg-bg border border-border rounded-lg px-3 py-1.5 text-white text-sm">
          <option value="">Tous statuts</option>
          <option value="pending">En attente</option>
          <option value="completed">Scrapés</option>
          <option value="failed">Erreur</option>
        </select>
      </div>

      {/* Info box */}
      <div className="bg-blue-500/5 border border-blue-500/20 rounded-xl p-4 text-sm text-blue-300">
        <p className="font-semibold">Comment ça marche :</p>
        <ul className="mt-2 space-y-1 text-xs text-blue-300/80">
          <li>1. Ajoutez des URLs d'annuaires (AEFE, répertoires professionnels, listes...)</li>
          <li>2. Cliquez "Scraper" pour lancer l'extraction des contacts individuels</li>
          <li>3. Chaque contact extrait est créé comme fiche individuelle avec son propre site/email</li>
          <li>4. Cooldown de 24h entre chaque scraping pour éviter les bans</li>
          <li>5. Délais aléatoires (3-8s) entre les pages pour simuler une navigation humaine</li>
        </ul>
      </div>

      {/* Directories table */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border bg-surface2/50">
              <th className="p-3 text-left text-xs text-muted font-medium">Annuaire</th>
              <th className="p-3 text-left text-xs text-muted font-medium hidden md:table-cell">Catégorie</th>
              <th className="p-3 text-left text-xs text-muted font-medium hidden lg:table-cell">Pays</th>
              <th className="p-3 text-center text-xs text-muted font-medium">Contacts</th>
              <th className="p-3 text-center text-xs text-muted font-medium">Statut</th>
              <th className="p-3 text-right text-xs text-muted font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            {directories.length === 0 ? (
              <tr><td colSpan={6} className="p-8 text-center text-muted">Aucun annuaire enregistré</td></tr>
            ) : directories.map(dir => (
              <React.Fragment key={dir.id}>
                <tr className={`border-b border-border/50 hover:bg-surface2/30 transition-colors ${expandedId === dir.id ? 'bg-surface2/20' : ''}`}>
                  <td className="p-3">
                    <div className="flex items-start gap-2">
                      <button onClick={() => toggleExpand(dir)} className="text-muted hover:text-white mt-0.5" title="Voir les contacts">
                        {expandedId === dir.id ? '▼' : '▶'}
                      </button>
                      <div className="min-w-0">
                        <p className="text-white font-medium truncate">{dir.name}</p>
                        <a href={dir.url} target="_blank" rel="noopener noreferrer"
                          className="text-[10px] text-violet-light hover:underline truncate block max-w-xs">{dir.domain}</a>
                        {dir.last_scraped_at && (
                          <p className="text-[10px] text-muted mt-0.5">
                            Dernier scraping : {new Date(dir.last_scraped_at).toLocaleDateString('fr-FR')}
                          </p>
                        )}
                      </div>
                    </div>
                  </td>
                  <td className="p-3 hidden md:table-cell">
                    <span className="text-xs bg-surface2 border border-border px-2 py-0.5 rounded-full text-gray-300">
                      {CATEGORY_ICONS[dir.category] || '📁'} {CATEGORY_LABELS[dir.category] || dir.category}
                    </span>
                  </td>
                  <td className="p-3 hidden lg:table-cell text-gray-300 text-xs">{dir.country || '-'}</td>
                  <td className="p-3 text-center">
                    <span className="text-white font-semibold">{dir.contacts_created}</span>
                    {dir.contacts_extracted > 0 && (
                      <span className="text-[10px] text-muted block">/ {dir.contacts_extracted} trouvés</span>
                    )}
                  </td>
                  <td className="p-3 text-center">
                    {(() => {
                      const s = STATUS_COLORS[dir.status] || STATUS_COLORS.pending;
                      return (
                        <span className={`text-[10px] ${s.bg} ${s.text} px-2 py-0.5 rounded-full inline-flex items-center gap-1`}>
                          {dir.status === 'scraping' && <span className="w-2 h-2 border border-current border-t-transparent rounded-full animate-spin" />}
                          {s.label}
                        </span>
                      );
                    })()}
                    {isOnCooldown(dir) && (
                      <p className="text-[9px] text-amber mt-0.5">Cooldown 24h</p>
                    )}
                  </td>
                  <td className="p-3 text-right">
                    <div className="flex items-center justify-end gap-1">
                      {isAdmin && (
                        <>
                          <button onClick={() => handleScrape(dir)}
                            disabled={scraping.has(dir.id) || dir.status === 'scraping' || isOnCooldown(dir)}
                            className="bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-400 px-2 py-1 rounded text-xs font-medium transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                            title={isOnCooldown(dir) ? 'En cooldown (24h)' : 'Lancer le scraping'}>
                            {scraping.has(dir.id) ? '...' : '🕷️ Scraper'}
                          </button>
                          <button onClick={() => handleDelete(dir)}
                            className="text-red-400/50 hover:text-red-400 px-1.5 py-1 rounded text-xs transition-colors" title="Supprimer">
                            ✕
                          </button>
                        </>
                      )}
                    </div>
                  </td>
                </tr>

                {/* Expanded: contacts list */}
                {expandedId === dir.id && (
                  <tr>
                    <td colSpan={6} className="bg-bg/50 p-0">
                      <div className="p-4 max-h-64 overflow-y-auto">
                        {expandedContacts.length === 0 ? (
                          <p className="text-muted text-xs text-center py-4">Aucun contact extrait de cet annuaire</p>
                        ) : (
                          <table className="w-full text-xs">
                            <thead>
                              <tr className="text-muted">
                                <th className="text-left pb-2">Nom</th>
                                <th className="text-left pb-2">Email</th>
                                <th className="text-left pb-2">Tél</th>
                                <th className="text-left pb-2">Site web</th>
                                <th className="text-left pb-2">Statut</th>
                              </tr>
                            </thead>
                            <tbody>
                              {expandedContacts.map((c: any) => (
                                <tr key={c.id} className="border-t border-border/30">
                                  <td className="py-1.5 text-white font-medium">{c.name}</td>
                                  <td className="py-1.5 text-gray-300">{c.email || <span className="text-muted">-</span>}</td>
                                  <td className="py-1.5 text-gray-300">{c.phone || <span className="text-muted">-</span>}</td>
                                  <td className="py-1.5">
                                    {c.website_url ? (
                                      <a href={c.website_url} target="_blank" rel="noopener noreferrer"
                                        className="text-violet-light hover:underline truncate block max-w-[200px]">
                                        {new URL(c.website_url).hostname}
                                      </a>
                                    ) : <span className="text-muted">-</span>}
                                  </td>
                                  <td className="py-1.5">
                                    <span className={`px-1.5 py-0.5 rounded text-[10px] ${
                                      c.status === 'active' ? 'bg-emerald-500/20 text-emerald-400' :
                                      c.status === 'contacted' ? 'bg-blue-500/20 text-blue-400' :
                                      'bg-gray-600/20 text-gray-400'
                                    }`}>{c.status}</span>
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        )}
                      </div>
                    </td>
                  </tr>
                )}
              </React.Fragment>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
