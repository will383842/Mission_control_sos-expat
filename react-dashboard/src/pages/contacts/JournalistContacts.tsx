import { useEffect, useState, useCallback, useRef } from 'react';
import type { FormEvent } from 'react';
import api from '../../api/client';

// ─── Types ───────────────────────────────────────────────────────────────────

interface JournalistContact {
  id: number;
  full_name: string;
  first_name: string | null;
  last_name: string | null;
  email: string | null;
  phone: string | null;
  publication: string;
  role: string | null;
  beat: string | null;
  media_type: string;
  country: string;
  twitter: string | null;
  linkedin: string | null;
  contact_status: string;
  source_url: string | null;
  topics: string[] | null;
  notes: string | null;
}

interface Publication {
  id: number;
  name: string;
  slug: string;
  base_url: string;
  team_url: string | null;
  media_type: string;
  topics: string[];
  country: string;
  contacts_count: number;
  status: string;
  last_scraped_at: string | null;
  last_error: string | null;
}

interface Stats {
  total_contacts: number;
  with_email: number;
  with_phone: number;
  total_publications: number;
  by_media_type: Record<string, number>;
  by_contact_status: Record<string, number>;
  top_publications: Record<string, number>;
  pub_stats: { scraped: number; pending: number; failed: number };
}

// ─── Constants ───────────────────────────────────────────────────────────────

const MEDIA_TYPES: Record<string, { label: string; color: string }> = {
  presse_ecrite: { label: 'Presse écrite', color: 'bg-blue-900/40 text-blue-300' },
  web:           { label: 'Web',           color: 'bg-green-900/40 text-green-300' },
  tv:            { label: 'TV',            color: 'bg-red-900/40 text-red-300' },
  radio:         { label: 'Radio',         color: 'bg-amber-900/40 text-amber-300' },
};

const STATUS_COLORS: Record<string, string> = {
  new:        'bg-surface2 text-muted',
  contacted:  'bg-blue-900/40 text-blue-300',
  replied:    'bg-amber-900/40 text-amber-300',
  won:        'bg-green-900/40 text-green-300',
  lost:       'bg-red-900/40 text-red-400',
};

const TOPICS = ['entrepreneuriat', 'voyage', 'expatriation', 'international', 'business', 'tech', 'lifestyle', 'startup'];

// ─── Component ───────────────────────────────────────────────────────────────

export default function JournalistContacts() {
  const [tab, setTab] = useState<'contacts' | 'publications'>('contacts');

  // — Contacts state
  const [contacts, setContacts] = useState<JournalistContact[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [filterMedia, setFilterMedia] = useState('');
  const [filterTopic, setFilterTopic] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [withEmail, setWithEmail] = useState(false);
  const [exporting, setExporting] = useState(false);

  // — Publications state
  const [publications, setPublications] = useState<Publication[]>([]);
  const [stats, setStats] = useState<Stats | null>(null);
  const [pubLoading, setPubLoading] = useState(false);
  const [scraping, setScraping] = useState(false);
  const [showAddPub, setShowAddPub] = useState(false);
  const [showAddContact, setShowAddContact] = useState(false);

  // — New contact form
  const [newContact, setNewContact] = useState({
    full_name: '', first_name: '', last_name: '', email: '', phone: '',
    publication: '', role: '', beat: '', media_type: 'web', country: 'France',
    topics: [] as string[], linkedin: '', twitter: '', notes: '',
  });

  // — New publication form
  const [newPub, setNewPub] = useState({
    name: '', base_url: '', team_url: '', contact_url: '',
    media_type: 'web', topics: [] as string[], country: 'France',
  });

  const abortRef = useRef<AbortController | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Debounce search
  useEffect(() => {
    const t = setTimeout(() => { setSearch(searchInput); setPage(1); }, 400);
    return () => clearTimeout(t);
  }, [searchInput]);

  const fetchContacts = useCallback(async () => {
    abortRef.current?.abort();
    const ctrl = new AbortController();
    abortRef.current = ctrl;
    setLoading(true);
    try {
      const params: Record<string, string> = { page: String(page), per_page: '50' };
      if (search) params.search = search;
      if (filterMedia) params.media_type = filterMedia;
      if (filterTopic) params.topic = filterTopic;
      if (filterStatus) params.contact_status = filterStatus;
      if (withEmail) params.with_email = '1';
      const res = await api.get('/content-gen/journalists/contacts', { params, signal: ctrl.signal });
      if (!ctrl.signal.aborted) {
        setContacts(res.data.data);
        setLastPage(res.data.last_page);
        setTotal(res.data.total);
      }
    } catch (e: any) {
      if (!e?.name?.includes('Cancel')) setError('Erreur de chargement');
    } finally {
      if (!ctrl.signal.aborted) setLoading(false);
    }
  }, [page, search, filterMedia, filterTopic, filterStatus, withEmail]);

  const fetchPublications = useCallback(async () => {
    setPubLoading(true);
    try {
      const [pubRes, statsRes] = await Promise.all([
        api.get('/content-gen/journalists/publications'),
        api.get('/content-gen/journalists/stats'),
      ]);
      setPublications(pubRes.data.publications);
      setStats(statsRes.data);
    } catch {
      setError('Erreur de chargement');
    } finally {
      setPubLoading(false);
    }
  }, []);

  useEffect(() => { fetchContacts(); }, [fetchContacts]);
  useEffect(() => { if (tab === 'publications') fetchPublications(); }, [tab, fetchPublications]);

  const handleExport = async () => {
    setExporting(true);
    try {
      const params: Record<string, string> = {};
      if (filterMedia) params.media_type = filterMedia;
      if (filterTopic) params.topic = filterTopic;
      if (withEmail) params.with_email = '1';
      const qs = new URLSearchParams(params).toString();
      const res = await api.get(`/content-gen/journalists/contacts/export?${qs}`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a   = document.createElement('a');
      a.href    = url;
      a.download = `journalistes-${new Date().toISOString().slice(0, 10)}.csv`;
      a.click();
      window.URL.revokeObjectURL(url);
    } finally {
      setExporting(false);
    }
  };

  const handleScrapeAll = async () => {
    setScraping(true);
    setError(null);
    try {
      const res = await api.post('/content-gen/journalists/publications/scrape');
      setError(`${res.data.queued} publications envoyées en queue — résultats dans quelques minutes`);
      setTimeout(() => setError(null), 8000);
      setTimeout(fetchPublications, 3000);
    } catch {
      setError('Erreur lancement scraping');
    } finally {
      setScraping(false);
    }
  };

  const handleScrapeSingle = async (pubId: number, pubName: string) => {
    try {
      await api.post('/content-gen/journalists/publications/scrape', { publication_id: pubId });
      setError(`Scraping lancé pour ${pubName}`);
      setTimeout(() => { setError(null); fetchPublications(); }, 5000);
    } catch {
      setError('Erreur scraping');
    }
  };

  const handleAddContact = async (e: FormEvent) => {
    e.preventDefault();
    try {
      await api.post('/content-gen/journalists/contacts', newContact);
      setShowAddContact(false);
      setNewContact({ full_name: '', first_name: '', last_name: '', email: '', phone: '', publication: '', role: '', beat: '', media_type: 'web', country: 'France', topics: [], linkedin: '', twitter: '', notes: '' });
      fetchContacts();
    } catch (err: any) {
      setError(err.response?.data?.message || 'Erreur ajout contact');
    }
  };

  const handleAddPublication = async (e: FormEvent) => {
    e.preventDefault();
    try {
      await api.post('/content-gen/journalists/publications', newPub);
      setShowAddPub(false);
      setNewPub({ name: '', base_url: '', team_url: '', contact_url: '', media_type: 'web', topics: [], country: 'France' });
      fetchPublications();
    } catch (err: any) {
      setError(err.response?.data?.message || 'Erreur ajout publication');
    }
  };

  const toggleTopic = (arr: string[], t: string, setter: (v: string[]) => void) => {
    setter(arr.includes(t) ? arr.filter(x => x !== t) : [...arr, t]);
  };

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-title text-2xl font-bold text-white">Journalistes & Presse</h1>
          <p className="text-muted text-sm mt-1">
            Presse francophone — entrepreneuriat, voyage, expatriation, international
          </p>
        </div>
        <div className="flex gap-2">
          {tab === 'contacts' && (
            <>
              <button onClick={() => setShowAddContact(!showAddContact)}
                className="px-3 py-2 bg-violet/20 text-violet-light rounded-lg text-xs font-medium hover:bg-violet/30 transition-colors">
                + Ajouter
              </button>
              <button onClick={handleExport} disabled={exporting}
                className="px-3 py-2 bg-surface2 text-muted rounded-lg text-xs font-medium hover:text-white transition-colors disabled:opacity-50">
                {exporting ? '...' : 'Exporter CSV'}
              </button>
            </>
          )}
          {tab === 'publications' && (
            <>
              <button onClick={() => setShowAddPub(!showAddPub)}
                className="px-3 py-2 bg-surface2 text-muted rounded-lg text-xs font-medium hover:text-white transition-colors">
                + Publication
              </button>
              <button onClick={handleScrapeAll} disabled={scraping}
                className="px-3 py-2 bg-violet hover:bg-violet/80 text-white rounded-lg text-xs font-medium disabled:opacity-50 transition-colors">
                {scraping ? 'Lancement...' : 'Scraper toutes les publications'}
              </button>
            </>
          )}
        </div>
      </div>

      {/* Error / notification */}
      {error && (
        <div className="bg-blue-900/20 border border-blue-500/30 text-blue-300 p-3 rounded-xl text-sm flex justify-between">
          <span>{error}</span>
          <button onClick={() => setError(null)} className="ml-3 opacity-60 hover:opacity-100">×</button>
        </div>
      )}

      {/* Stats */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div className="bg-surface border border-border rounded-xl p-3 text-center">
            <div className="text-white font-bold text-xl">{stats.total_contacts.toLocaleString()}</div>
            <div className="text-xs text-muted">Journalistes</div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-3 text-center">
            <div className="text-green-400 font-bold text-xl">{stats.with_email.toLocaleString()}</div>
            <div className="text-xs text-muted">Avec email</div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-3 text-center">
            <div className="text-blue-300 font-bold text-xl">{stats.total_publications}</div>
            <div className="text-xs text-muted">Publications</div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-3 text-center">
            <div className="text-amber-400 font-bold text-xl">{stats.pub_stats?.scraped ?? 0}</div>
            <div className="text-xs text-muted">Scrapées</div>
          </div>
        </div>
      )}

      {/* By media type */}
      {stats?.by_media_type && Object.keys(stats.by_media_type).length > 0 && (
        <div className="flex flex-wrap gap-2">
          {Object.entries(stats.by_media_type).map(([type, count]) => (
            <div key={type} className={`px-3 py-1.5 rounded-lg text-xs font-medium ${MEDIA_TYPES[type]?.color || 'bg-surface2 text-muted'}`}>
              {MEDIA_TYPES[type]?.label || type}: <span className="font-bold">{count}</span>
            </div>
          ))}
        </div>
      )}

      {/* Tabs */}
      <div className="flex gap-1 bg-surface p-1 rounded-lg w-fit">
        {(['contacts', 'publications'] as const).map((t) => (
          <button key={t} onClick={() => setTab(t)}
            className={`px-4 py-2 rounded-md text-sm font-medium transition-colors capitalize ${tab === t ? 'bg-violet text-white' : 'text-muted hover:text-white'}`}>
            {t === 'contacts' ? `Journalistes (${total})` : `Publications (${publications.length})`}
          </button>
        ))}
      </div>

      {/* ─── TAB: CONTACTS ─────────────────────────────────────────────────── */}
      {tab === 'contacts' && (
        <>
          {/* Add contact form */}
          {showAddContact && (
            <form onSubmit={handleAddContact} className="bg-surface border border-border rounded-xl p-4 space-y-3">
              <h3 className="text-white font-medium text-sm">Ajouter un journaliste</h3>
              <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                {[
                  { key: 'full_name', label: 'Nom complet*', required: true },
                  { key: 'first_name', label: 'Prénom' },
                  { key: 'last_name', label: 'Nom de famille' },
                  { key: 'email', label: 'Email', type: 'email' },
                  { key: 'phone', label: 'Téléphone' },
                  { key: 'publication', label: 'Publication*', required: true },
                  { key: 'role', label: 'Rôle / Titre' },
                  { key: 'beat', label: 'Rubrique / Spécialité' },
                  { key: 'linkedin', label: 'LinkedIn URL', type: 'url' },
                  { key: 'twitter', label: 'Twitter / X' },
                ].map(({ key, label, required, type }) => (
                  <div key={key}>
                    <label className="text-xs text-muted block mb-1">{label}</label>
                    <input
                      type={type || 'text'}
                      required={required}
                      value={(newContact as any)[key]}
                      onChange={(e) => setNewContact(prev => ({ ...prev, [key]: e.target.value }))}
                      className="w-full bg-surface2 border border-border rounded-lg px-2 py-1.5 text-white text-xs"
                    />
                  </div>
                ))}
                <div>
                  <label className="text-xs text-muted block mb-1">Type de média</label>
                  <select value={newContact.media_type} onChange={(e) => setNewContact(prev => ({ ...prev, media_type: e.target.value }))}
                    className="w-full bg-surface2 border border-border rounded-lg px-2 py-1.5 text-white text-xs">
                    {Object.entries(MEDIA_TYPES).map(([v, { label }]) => <option key={v} value={v}>{label}</option>)}
                  </select>
                </div>
              </div>
              <div>
                <label className="text-xs text-muted block mb-2">Sujets couverts</label>
                <div className="flex flex-wrap gap-1">
                  {TOPICS.map(t => (
                    <button key={t} type="button"
                      onClick={() => toggleTopic(newContact.topics, t, (v) => setNewContact(prev => ({ ...prev, topics: v })))}
                      className={`px-2 py-0.5 rounded text-xs font-medium transition-colors ${newContact.topics.includes(t) ? 'bg-violet text-white' : 'bg-surface2 text-muted hover:text-white'}`}>
                      {t}
                    </button>
                  ))}
                </div>
              </div>
              <div className="flex gap-2">
                <button type="submit" className="px-4 py-2 bg-violet hover:bg-violet/80 text-white rounded-lg text-xs font-medium">
                  Ajouter
                </button>
                <button type="button" onClick={() => setShowAddContact(false)} className="px-4 py-2 bg-surface2 text-muted rounded-lg text-xs">
                  Annuler
                </button>
              </div>
            </form>
          )}

          {/* Filters */}
          <div className="flex flex-wrap gap-3 items-center">
            <input type="text" value={searchInput} onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Rechercher journaliste, email, publication..."
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-72" />

            <select value={filterMedia} onChange={(e) => { setFilterMedia(e.target.value); setPage(1); }}
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="">Tous les médias</option>
              {Object.entries(MEDIA_TYPES).map(([v, { label }]) => <option key={v} value={v}>{label}</option>)}
            </select>

            <select value={filterTopic} onChange={(e) => { setFilterTopic(e.target.value); setPage(1); }}
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="">Tous les sujets</option>
              {TOPICS.map(t => <option key={t} value={t}>{t}</option>)}
            </select>

            <select value={filterStatus} onChange={(e) => { setFilterStatus(e.target.value); setPage(1); }}
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="">Tous les statuts</option>
              {Object.keys(STATUS_COLORS).map(s => <option key={s} value={s}>{s}</option>)}
            </select>

            <button onClick={() => { setWithEmail(!withEmail); setPage(1); }}
              className={`px-3 py-2 rounded-lg text-xs font-medium transition-colors ${withEmail ? 'bg-green-900/40 text-green-300' : 'bg-surface2 text-muted hover:text-white'}`}>
              Avec email
            </button>

            <span className="text-muted text-sm ml-auto">{total.toLocaleString()} journalistes</span>
          </div>

          {/* Table */}
          <div className="bg-surface border border-border rounded-xl overflow-x-auto">
            {loading ? (
              <div className="flex justify-center py-12">
                <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
              </div>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left">
                    <th className="px-4 py-3 text-muted font-medium">Journaliste</th>
                    <th className="px-4 py-3 text-muted font-medium">Publication</th>
                    <th className="px-4 py-3 text-muted font-medium">Rôle / Rubrique</th>
                    <th className="px-4 py-3 text-muted font-medium">Email / Tel</th>
                    <th className="px-4 py-3 text-muted font-medium">Type</th>
                    <th className="px-4 py-3 text-muted font-medium">Statut</th>
                    <th className="px-4 py-3 text-muted font-medium">Liens</th>
                  </tr>
                </thead>
                <tbody>
                  {contacts.map((c) => (
                    <tr key={c.id} className="border-b border-border/50 hover:bg-surface2 transition-colors">
                      <td className="px-4 py-3">
                        <div className="text-white font-medium">{c.full_name}</div>
                        {c.country && c.country !== 'France' && (
                          <div className="text-xs text-muted">{c.country}</div>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <div className="text-white text-sm">{c.publication}</div>
                      </td>
                      <td className="px-4 py-3">
                        {c.role && <div className="text-muted text-xs">{c.role}</div>}
                        {c.beat && <div className="text-violet-light text-xs font-medium">{c.beat}</div>}
                      </td>
                      <td className="px-4 py-3">
                        {c.email && (
                          <a href={`mailto:${c.email}`} className="text-green-400 text-xs hover:underline block">
                            {c.email}
                          </a>
                        )}
                        {c.phone && <div className="text-muted text-xs">{c.phone}</div>}
                        {!c.email && !c.phone && <span className="text-muted/40 text-xs">—</span>}
                      </td>
                      <td className="px-4 py-3">
                        <span className={`px-2 py-0.5 rounded text-[10px] font-medium ${MEDIA_TYPES[c.media_type]?.color || 'bg-surface2 text-muted'}`}>
                          {MEDIA_TYPES[c.media_type]?.label || c.media_type}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <span className={`px-2 py-0.5 rounded text-[10px] font-medium capitalize ${STATUS_COLORS[c.contact_status] || 'bg-surface2 text-muted'}`}>
                          {c.contact_status}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex gap-2">
                          {c.twitter && (
                            <a href={c.twitter.startsWith('http') ? c.twitter : `https://x.com/${c.twitter.replace('@', '')}`}
                              target="_blank" rel="noopener noreferrer"
                              className="text-muted hover:text-white text-xs">X</a>
                          )}
                          {c.linkedin && (
                            <a href={c.linkedin} target="_blank" rel="noopener noreferrer"
                              className="text-muted hover:text-blue-300 text-xs">in</a>
                          )}
                          {c.source_url && (
                            <a href={c.source_url} target="_blank" rel="noopener noreferrer"
                              className="text-muted hover:text-white text-xs">↗</a>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                  {contacts.length === 0 && !loading && (
                    <tr>
                      <td colSpan={7} className="px-4 py-12 text-center text-muted">
                        Aucun journaliste — lancez le scraping depuis l'onglet Publications
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            )}
          </div>

          {/* Pagination */}
          {lastPage > 1 && (
            <div className="flex justify-center gap-2">
              <button onClick={() => setPage(Math.max(1, page - 1))} disabled={page === 1}
                className="px-3 py-1.5 bg-surface2 text-muted rounded-lg text-xs disabled:opacity-50 hover:text-white">
                ← Préc.
              </button>
              <span className="px-3 py-1.5 text-muted text-xs self-center">
                {page} / {lastPage}
              </span>
              <button onClick={() => setPage(Math.min(lastPage, page + 1))} disabled={page === lastPage}
                className="px-3 py-1.5 bg-surface2 text-muted rounded-lg text-xs disabled:opacity-50 hover:text-white">
                Suiv. →
              </button>
            </div>
          )}
        </>
      )}

      {/* ─── TAB: PUBLICATIONS ─────────────────────────────────────────────── */}
      {tab === 'publications' && (
        <>
          {/* Add publication form */}
          {showAddPub && (
            <form onSubmit={handleAddPublication} className="bg-surface border border-border rounded-xl p-4 space-y-3">
              <h3 className="text-white font-medium text-sm">Ajouter une publication</h3>
              <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                {[
                  { key: 'name', label: 'Nom*', required: true },
                  { key: 'base_url', label: 'URL de base*', required: true, type: 'url' },
                  { key: 'team_url', label: 'URL Équipe/Rédaction', type: 'url' },
                  { key: 'contact_url', label: 'URL Contact', type: 'url' },
                  { key: 'country', label: 'Pays' },
                ].map(({ key, label, required, type }) => (
                  <div key={key}>
                    <label className="text-xs text-muted block mb-1">{label}</label>
                    <input type={type || 'text'} required={required}
                      value={(newPub as any)[key]}
                      onChange={(e) => setNewPub(prev => ({ ...prev, [key]: e.target.value }))}
                      className="w-full bg-surface2 border border-border rounded-lg px-2 py-1.5 text-white text-xs" />
                  </div>
                ))}
                <div>
                  <label className="text-xs text-muted block mb-1">Type de média</label>
                  <select value={newPub.media_type} onChange={(e) => setNewPub(prev => ({ ...prev, media_type: e.target.value }))}
                    className="w-full bg-surface2 border border-border rounded-lg px-2 py-1.5 text-white text-xs">
                    {Object.entries(MEDIA_TYPES).map(([v, { label }]) => <option key={v} value={v}>{label}</option>)}
                  </select>
                </div>
              </div>
              <div>
                <label className="text-xs text-muted block mb-2">Sujets couverts</label>
                <div className="flex flex-wrap gap-1">
                  {TOPICS.map(t => (
                    <button key={t} type="button"
                      onClick={() => toggleTopic(newPub.topics, t, (v) => setNewPub(prev => ({ ...prev, topics: v })))}
                      className={`px-2 py-0.5 rounded text-xs font-medium transition-colors ${newPub.topics.includes(t) ? 'bg-violet text-white' : 'bg-surface2 text-muted hover:text-white'}`}>
                      {t}
                    </button>
                  ))}
                </div>
              </div>
              <div className="flex gap-2">
                <button type="submit" className="px-4 py-2 bg-violet hover:bg-violet/80 text-white rounded-lg text-xs font-medium">
                  Ajouter
                </button>
                <button type="button" onClick={() => setShowAddPub(false)} className="px-4 py-2 bg-surface2 text-muted rounded-lg text-xs">
                  Annuler
                </button>
              </div>
            </form>
          )}

          {pubLoading ? (
            <div className="flex justify-center py-12">
              <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
            </div>
          ) : (
            <div className="space-y-2">
              {(['presse_ecrite', 'web', 'tv', 'radio'] as const).map((mediaType) => {
                const group = publications.filter(p => p.media_type === mediaType);
                if (group.length === 0) return null;
                return (
                  <div key={mediaType} className="space-y-1">
                    <h3 className="text-white font-title font-bold text-sm border-b border-border pb-1.5">
                      {MEDIA_TYPES[mediaType].label}
                      <span className="text-muted text-xs font-normal ml-2">
                        {group.length} publications · {group.reduce((s, p) => s + p.contacts_count, 0)} journalistes
                      </span>
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                      {group.map((pub) => (
                        <div key={pub.id} className="bg-surface border border-border rounded-xl p-3 hover:bg-surface2 transition-colors">
                          <div className="flex items-start justify-between mb-1">
                            <div>
                              <div className="text-white font-medium text-sm">{pub.name}</div>
                              <a href={pub.base_url} target="_blank" rel="noopener noreferrer"
                                className="text-xs text-muted hover:text-cyan truncate block max-w-[200px]">
                                {pub.base_url.replace(/^https?:\/\//, '')}
                              </a>
                            </div>
                            <div className="flex items-center gap-1 flex-shrink-0 ml-2">
                              {pub.status === 'scraped' && (
                                <span className="w-2 h-2 rounded-full bg-green-400" title="Scrapée" />
                              )}
                              {pub.status === 'pending' && (
                                <span className="w-2 h-2 rounded-full bg-amber-400" title="En attente" />
                              )}
                              {pub.status === 'failed' && (
                                <span className="w-2 h-2 rounded-full bg-red-400" title="Échec" />
                              )}
                            </div>
                          </div>

                          {/* Topics */}
                          <div className="flex flex-wrap gap-1 mb-2">
                            {(pub.topics || []).map(t => (
                              <span key={t} className="px-1.5 py-0.5 bg-violet/20 text-violet-light rounded text-[9px]">{t}</span>
                            ))}
                          </div>

                          <div className="flex items-center justify-between">
                            <div className="text-sm">
                              <span className="text-green-400 font-bold">{pub.contacts_count}</span>
                              <span className="text-muted text-xs ml-1">contacts</span>
                            </div>
                            <button
                              onClick={() => handleScrapeSingle(pub.id, pub.name)}
                              className="px-2 py-1 bg-violet/20 text-violet-light rounded text-xs hover:bg-violet/30 transition-colors"
                            >
                              {pub.status === 'scraped' ? 'Re-scraper' : 'Scraper'}
                            </button>
                          </div>

                          {pub.last_scraped_at && (
                            <div className="text-[10px] text-muted mt-1">
                              Dernier scraping : {new Date(pub.last_scraped_at).toLocaleDateString('fr-FR')}
                            </div>
                          )}
                          {pub.last_error && pub.status === 'failed' && (
                            <div className="text-[10px] text-red-400/70 mt-1 truncate" title={pub.last_error}>
                              ⚠ {pub.last_error}
                            </div>
                          )}
                        </div>
                      ))}
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </>
      )}
    </div>
  );
}
