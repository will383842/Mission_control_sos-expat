import { useEffect, useState, useCallback } from 'react';
import api from '../../api/client';

// ─── Types ───────────────────────────────────────────────────────────────────

interface Contact {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  website: string | null;
  country: string | null;
  type: string;
  status: string | null;
  email_verified_status: string | null;
  score: number | null;
  source_table: string;
  tier: 1 | 2 | 3 | 4;
}

interface SourceStats {
  total: number;
  tier1: number;
  tier2: number;
  tier3: number;
  tier4: number;
}

interface Stats {
  sources: Record<string, SourceStats>;
  totals: { all: number; tier1: number; tier2: number; tier3: number; tier4: number };
  duplicates: Record<string, number>;
  inf_by_type: { contact_type: string; n: number; with_email: number }[];
}

// ─── Constants ───────────────────────────────────────────────────────────────

const TIERS = [
  { id: 0, label: 'Tous',              color: 'text-white',       bg: 'bg-surface2',          desc: 'Toutes les sources' },
  { id: 1, label: '✅ Email vérifié',  color: 'text-green-300',   bg: 'bg-green-900/30',      desc: 'Email confirmé valide (SMTP)' },
  { id: 2, label: '📧 Email présent',  color: 'text-blue-300',    bg: 'bg-blue-900/30',       desc: 'Email présent, non vérifié' },
  { id: 3, label: '🌐 Site web seul',  color: 'text-amber-300',   bg: 'bg-amber-900/30',      desc: 'Pas d\'email, mais un site/formulaire' },
  { id: 4, label: '❌ Aucun contact',  color: 'text-red-400',     bg: 'bg-red-900/20',        desc: 'Ni email ni site web' },
];

const SOURCES = [
  { id: 'all',           label: 'Toutes sources' },
  { id: 'influenceurs',  label: 'CRM principal (contacts unifiés)' },
  { id: 'lawyers',       label: 'Avocats' },
  { id: 'press',         label: 'Journalistes' },
  { id: 'businesses',    label: 'Entreprises expat' },
];

const SOURCE_COLORS: Record<string, string> = {
  influenceurs:       'bg-violet/20 text-violet-light',
  lawyers:            'bg-yellow-900/40 text-yellow-300',
  press_contacts:     'bg-blue-900/40 text-blue-300',
  content_businesses: 'bg-green-900/40 text-green-300',
  content_contacts:   'bg-slate-700 text-slate-300',
};

const SOURCE_LABELS: Record<string, string> = {
  influenceurs:       'CRM',
  lawyers:            'Avocat',
  press_contacts:     'Journaliste',
  content_businesses: 'Entreprise',
  content_contacts:   'Contact web',
};

// ─── Component ───────────────────────────────────────────────────────────────

export default function ContactsBase() {
  const [tab, setTab] = useState<'triage' | 'dedup'>('triage');

  // — Stats
  const [stats, setStats] = useState<Stats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

  // — Contacts list
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [loading, setLoading] = useState(false);
  const [total, setTotal] = useState(0);
  const [lastPage, setLastPage] = useState(1);
  const [page, setPage] = useState(1);

  // — Filters
  const [tier, setTier] = useState(0);
  const [source, setSource] = useState('all');
  const [search, setSearch] = useState('');
  const [searchInput, setSearchInput] = useState('');

  // — Dedup
  const [dedupSource, setDedupSource] = useState('influenceurs');
  const [dupGroups, setDupGroups] = useState<any[]>([]);
  const [dedupLoading, setDedupLoading] = useState(false);
  const [deduping, setDeduping] = useState(false);

  const [notif, setNotif] = useState<string | null>(null);

  const notify = (msg: string) => { setNotif(msg); setTimeout(() => setNotif(null), 6000); };

  // Debounce search
  useEffect(() => {
    const t = setTimeout(() => { setSearch(searchInput); setPage(1); }, 400);
    return () => clearTimeout(t);
  }, [searchInput]);

  // Load stats
  useEffect(() => {
    setStatsLoading(true);
    api.get('/content-gen/contacts-base/stats')
      .then(r => setStats(r.data))
      .finally(() => setStatsLoading(false));
  }, [notif]);

  // Load contacts
  const fetchContacts = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get('/content-gen/contacts-base/contacts', {
        params: { tier, source, search, page, per_page: 50 },
      });
      setContacts(res.data.data);
      setTotal(res.data.total);
      setLastPage(res.data.last_page);
    } finally {
      setLoading(false);
    }
  }, [tier, source, search, page]);

  useEffect(() => { if (tab === 'triage') fetchContacts(); }, [fetchContacts, tab]);

  // Load duplicates
  const fetchDuplicates = useCallback(async () => {
    setDedupLoading(true);
    try {
      const res = await api.get('/content-gen/contacts-base/duplicates', { params: { source: dedupSource } });
      setDupGroups(res.data.groups || []);
    } finally {
      setDedupLoading(false);
    }
  }, [dedupSource]);

  useEffect(() => { if (tab === 'dedup') fetchDuplicates(); }, [fetchDuplicates, tab]);

  const handleDeduplicateAuto = async () => {
    if (!confirm(`Supprimer automatiquement les doublons dans "${dedupSource}" (garder le plus ancien) ?`)) return;
    setDeduping(true);
    try {
      const res = await api.post('/content-gen/contacts-base/deduplicate', { source: dedupSource });
      notify(res.data.message);
      fetchDuplicates();
    } finally {
      setDeduping(false);
    }
  };

  const handleExport = async () => {
    const params = new URLSearchParams({ tier: String(tier), source, search });
    window.open(`/api/content-gen/contacts-base/contacts/export?${params}`, '_blank');
  };

  const totalDupes = stats ? Object.values(stats.duplicates).reduce((s, n) => s + n, 0) : 0;

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-title text-2xl font-bold text-white">Base Contacts Unifiée</h1>
          <p className="text-muted text-sm mt-1">Toutes sources · Triage par qualité · Déduplication</p>
        </div>
        {totalDupes > 0 && (
          <button onClick={() => setTab('dedup')}
            className="px-3 py-2 bg-red-900/30 text-red-400 border border-red-500/30 rounded-lg text-xs font-medium hover:bg-red-900/50 transition-colors">
            ⚠ {totalDupes} doublons détectés
          </button>
        )}
      </div>

      {/* Notification */}
      {notif && (
        <div className="bg-green-900/20 border border-green-500/30 text-green-300 p-3 rounded-xl text-sm flex justify-between">
          <span>{notif}</span>
          <button onClick={() => setNotif(null)}>×</button>
        </div>
      )}

      {/* Global KPIs */}
      {statsLoading ? (
        <div className="flex justify-center py-8"><div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>
      ) : stats && (
        <>
          {/* Tier overview */}
          <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
            {TIERS.map(t => (
              <button key={t.id} onClick={() => { setTier(t.id); setTab('triage'); setPage(1); }}
                className={`${t.bg} border rounded-xl p-3 text-center transition-all hover:opacity-90 ${tier === t.id ? 'border-white/30 ring-1 ring-white/20' : 'border-border'}`}>
                <div className={`font-bold text-xl ${t.color}`}>
                  {t.id === 0 ? stats.totals.all.toLocaleString()
                    : t.id === 1 ? stats.totals.tier1.toLocaleString()
                    : t.id === 2 ? stats.totals.tier2.toLocaleString()
                    : t.id === 3 ? stats.totals.tier3.toLocaleString()
                    : stats.totals.tier4.toLocaleString()}
                </div>
                <div className="text-xs text-muted mt-0.5">{t.label}</div>
                <div className="text-[9px] text-muted/60 mt-0.5">{t.desc}</div>
              </button>
            ))}
          </div>

          {/* Per-source breakdown */}
          <div className="bg-surface border border-border rounded-xl overflow-hidden">
            <div className="px-4 py-3 border-b border-border">
              <h3 className="text-white font-medium text-sm">Répartition par source</h3>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border">
                    <th className="px-4 py-2 text-left text-muted font-medium text-xs">Source</th>
                    <th className="px-4 py-2 text-right text-muted font-medium text-xs">Total</th>
                    <th className="px-4 py-2 text-right text-green-400/70 font-medium text-xs">✅ Vérifié</th>
                    <th className="px-4 py-2 text-right text-blue-400/70 font-medium text-xs">📧 Email</th>
                    <th className="px-4 py-2 text-right text-amber-400/70 font-medium text-xs">🌐 Site</th>
                    <th className="px-4 py-2 text-right text-red-400/70 font-medium text-xs">❌ Aucun</th>
                    <th className="px-4 py-2 text-right text-red-300/70 font-medium text-xs">Doublons</th>
                  </tr>
                </thead>
                <tbody>
                  {Object.entries(stats.sources).map(([src, s]) => (
                    <tr key={src} className="border-b border-border/50 hover:bg-surface2">
                      <td className="px-4 py-2">
                        <span className={`px-2 py-0.5 rounded text-[10px] font-medium ${SOURCE_COLORS[src] || 'bg-surface2 text-muted'}`}>
                          {SOURCE_LABELS[src] || src}
                        </span>
                        <span className="text-muted text-xs ml-2">{src}</span>
                      </td>
                      <td className="px-4 py-2 text-right text-white font-medium">{s.total.toLocaleString()}</td>
                      <td className="px-4 py-2 text-right text-green-400">{s.tier1 > 0 ? s.tier1.toLocaleString() : <span className="text-muted">—</span>}</td>
                      <td className="px-4 py-2 text-right text-blue-300">{s.tier2.toLocaleString()}</td>
                      <td className="px-4 py-2 text-right text-amber-300">{s.tier3 > 0 ? s.tier3.toLocaleString() : <span className="text-muted">—</span>}</td>
                      <td className="px-4 py-2 text-right text-red-400/70">{s.tier4.toLocaleString()}</td>
                      <td className="px-4 py-2 text-right">
                        {(stats.duplicates[src.replace('content_businesses', '').replace('content_contacts', '')] || 0) > 0
                          ? <span className="text-red-400 font-medium">{stats.duplicates[src] || 0}</span>
                          : <span className="text-muted">—</span>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Types breakdown */}
          {stats.inf_by_type.length > 0 && (
            <div className="bg-surface border border-border rounded-xl p-4">
              <h3 className="text-white font-medium text-sm mb-3">Types dans le CRM principal</h3>
              <div className="flex flex-wrap gap-2">
                {stats.inf_by_type.map(t => (
                  <button key={t.contact_type}
                    onClick={() => { setSource('influenceurs'); setTab('triage'); setPage(1); }}
                    className="px-2 py-1 bg-surface2 rounded-lg text-xs hover:bg-violet/20 transition-colors">
                    <span className="text-white font-medium">{t.contact_type}</span>
                    <span className="text-muted ml-1">({t.n.toLocaleString()})</span>
                    <span className="text-green-400 ml-1 text-[10px]">{t.with_email} emails</span>
                  </button>
                ))}
              </div>
            </div>
          )}
        </>
      )}

      {/* Tabs */}
      <div className="flex gap-1 bg-surface p-1 rounded-lg w-fit">
        {[
          { id: 'triage', label: `Triage contacts (${total.toLocaleString()})` },
          { id: 'dedup',  label: `Déduplication${totalDupes > 0 ? ` (${totalDupes})` : ''}` },
        ].map(t => (
          <button key={t.id} onClick={() => setTab(t.id as any)}
            className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${tab === t.id ? 'bg-violet text-white' : 'text-muted hover:text-white'}`}>
            {t.label}
          </button>
        ))}
      </div>

      {/* ─── TAB : TRIAGE ─────────────────────────────────────────────────────── */}
      {tab === 'triage' && (
        <>
          {/* Filters */}
          <div className="flex flex-wrap gap-3 items-center">
            <input type="text" value={searchInput} onChange={e => setSearchInput(e.target.value)}
              placeholder="Nom, email, pays..."
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-64" />

            <select value={source} onChange={e => { setSource(e.target.value); setPage(1); }}
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
              {SOURCES.map(s => <option key={s.id} value={s.id}>{s.label}</option>)}
            </select>

            <div className="flex gap-1">
              {TIERS.map(t => (
                <button key={t.id} onClick={() => { setTier(t.id); setPage(1); }}
                  className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${tier === t.id ? t.bg + ' ' + t.color + ' border border-white/20' : 'bg-surface2 text-muted hover:text-white'}`}>
                  {t.id === 0 ? 'Tous' : t.label}
                </button>
              ))}
            </div>

            <button onClick={handleExport}
              className="ml-auto px-3 py-1.5 bg-surface2 text-muted rounded-lg text-xs hover:text-white transition-colors">
              Exporter CSV
            </button>

            <span className="text-muted text-sm">{total.toLocaleString()} contacts</span>
          </div>

          {/* Table */}
          <div className="bg-surface border border-border rounded-xl overflow-x-auto">
            {loading ? (
              <div className="flex justify-center py-12"><div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left">
                    <th className="px-4 py-3 text-muted font-medium text-xs">Qualité</th>
                    <th className="px-4 py-3 text-muted font-medium text-xs">Nom</th>
                    <th className="px-4 py-3 text-muted font-medium text-xs">Email / Site</th>
                    <th className="px-4 py-3 text-muted font-medium text-xs">Type</th>
                    <th className="px-4 py-3 text-muted font-medium text-xs">Source</th>
                    <th className="px-4 py-3 text-muted font-medium text-xs">Pays</th>
                  </tr>
                </thead>
                <tbody>
                  {contacts.map(c => {
                    const tierInfo = TIERS[c.tier];
                    return (
                      <tr key={`${c.source_table}-${c.id}`} className="border-b border-border/50 hover:bg-surface2 transition-colors">
                        <td className="px-4 py-2.5">
                          <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${tierInfo.bg} ${tierInfo.color}`}>
                            {c.tier === 1 ? '✅' : c.tier === 2 ? '📧' : c.tier === 3 ? '🌐' : '❌'}
                          </span>
                        </td>
                        <td className="px-4 py-2.5">
                          <div className="text-white font-medium text-sm">{c.name || '—'}</div>
                          {c.phone && <div className="text-muted text-xs">{c.phone}</div>}
                        </td>
                        <td className="px-4 py-2.5">
                          {c.email && (
                            <a href={`mailto:${c.email}`} className="text-green-400 text-xs hover:underline block">
                              {c.email}
                            </a>
                          )}
                          {!c.email && c.website && (
                            <a href={c.website} target="_blank" rel="noopener noreferrer"
                              className="text-amber-300 text-xs hover:underline block truncate max-w-[200px]">
                              {c.website.replace(/^https?:\/\//, '')}
                            </a>
                          )}
                          {!c.email && !c.website && <span className="text-muted/40 text-xs">—</span>}
                        </td>
                        <td className="px-4 py-2.5">
                          <span className="px-1.5 py-0.5 bg-surface2 text-muted rounded text-[10px]">{c.type || '—'}</span>
                        </td>
                        <td className="px-4 py-2.5">
                          <span className={`px-1.5 py-0.5 rounded text-[10px] font-medium ${SOURCE_COLORS[c.source_table] || 'bg-surface2 text-muted'}`}>
                            {SOURCE_LABELS[c.source_table] || c.source_table}
                          </span>
                        </td>
                        <td className="px-4 py-2.5 text-muted text-xs">{c.country || '—'}</td>
                      </tr>
                    );
                  })}
                  {contacts.length === 0 && !loading && (
                    <tr><td colSpan={6} className="px-4 py-12 text-center text-muted">Aucun contact pour ce filtre</td></tr>
                  )}
                </tbody>
              </table>
            )}
          </div>

          {/* Pagination */}
          {lastPage > 1 && (
            <div className="flex justify-center gap-2">
              <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1}
                className="px-3 py-1.5 bg-surface2 text-muted rounded-lg text-xs disabled:opacity-50 hover:text-white">← Préc.</button>
              <span className="px-3 py-1.5 text-muted text-xs self-center">{page} / {lastPage}</span>
              <button onClick={() => setPage(p => Math.min(lastPage, p + 1))} disabled={page === lastPage}
                className="px-3 py-1.5 bg-surface2 text-muted rounded-lg text-xs disabled:opacity-50 hover:text-white">Suiv. →</button>
            </div>
          )}
        </>
      )}

      {/* ─── TAB : DÉDUPLICATION ──────────────────────────────────────────────── */}
      {tab === 'dedup' && (
        <div className="space-y-4">
          <div className="flex gap-3 items-center">
            <select value={dedupSource} onChange={e => setDedupSource(e.target.value)}
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="influenceurs">CRM principal (contacts unifiés)</option>
              <option value="lawyers">Avocats</option>
              <option value="press">Journalistes</option>
            </select>
            <button onClick={fetchDuplicates} disabled={dedupLoading}
              className="px-3 py-2 bg-surface2 text-muted rounded-lg text-xs hover:text-white transition-colors">
              Actualiser
            </button>
            {dupGroups.length > 0 && (
              <button onClick={handleDeduplicateAuto} disabled={deduping}
                className="px-4 py-2 bg-red-900/40 text-red-300 border border-red-500/30 rounded-lg text-xs font-medium hover:bg-red-900/60 disabled:opacity-50 transition-colors">
                {deduping ? 'Suppression...' : `Supprimer automatiquement les ${dupGroups.length} groupes de doublons`}
              </button>
            )}
          </div>

          {dedupLoading ? (
            <div className="flex justify-center py-12"><div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>
          ) : dupGroups.length === 0 ? (
            <div className="bg-green-900/20 border border-green-500/30 rounded-xl p-8 text-center">
              <div className="text-green-400 text-2xl mb-2">✅</div>
              <div className="text-green-300 font-medium">Aucun doublon détecté dans {dedupSource}</div>
            </div>
          ) : (
            <div className="space-y-2">
              <div className="text-muted text-sm">{dupGroups.length} groupes de doublons · {dupGroups.reduce((s, g) => s + (g.count - 1), 0)} contacts à supprimer</div>
              <div className="bg-surface border border-border rounded-xl overflow-hidden">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-border">
                      <th className="px-4 py-2 text-left text-muted font-medium text-xs">Email</th>
                      <th className="px-4 py-2 text-left text-muted font-medium text-xs">Occurences</th>
                      <th className="px-4 py-2 text-left text-muted font-medium text-xs">Noms / Types</th>
                    </tr>
                  </thead>
                  <tbody>
                    {dupGroups.slice(0, 100).map((g, i) => (
                      <tr key={i} className="border-b border-border/50 hover:bg-surface2">
                        <td className="px-4 py-2 text-red-300 text-xs font-mono">{g.email_norm}</td>
                        <td className="px-4 py-2">
                          <span className="bg-red-900/30 text-red-400 px-2 py-0.5 rounded text-xs font-bold">{g.count}×</span>
                        </td>
                        <td className="px-4 py-2 text-muted text-xs">
                          {(g.names || '').replace(/[{}]/g, '').split(',').slice(0, 3).join(' · ')}
                          {g.types && <span className="text-violet-light ml-2">({g.types.replace(/[{}]/g, '')})</span>}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
