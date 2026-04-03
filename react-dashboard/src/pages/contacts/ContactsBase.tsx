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
  language: string | null;
  type: string;
  category: string | null;
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

interface DuplicateFlag {
  id: number;
  match_type: string;
  confidence: number;
  status: 'pending' | 'resolved' | 'dismissed';
  influenceur_a: {
    id: number;
    name: string;
    email: string | null;
    website: string | null;
    country: string | null;
    contact_type: string | null;
    category: string | null;
  };
  influenceur_b: {
    id: number;
    name: string;
    email: string | null;
    website: string | null;
    country: string | null;
    contact_type: string | null;
    category: string | null;
  };
}

// ─── Constants ───────────────────────────────────────────────────────────────

const CATEGORIES: Record<string, { label: string; icon: string; color: string }> = {
  institutionnel:    { label: 'Institutionnel',      icon: '🏛️', color: 'bg-slate-700 text-slate-300' },
  presse:            { label: 'Presse & Médias',     icon: '🗞️', color: 'bg-blue-900/40 text-blue-300' },
  influenceurs:      { label: 'Influenceurs',        icon: '✨', color: 'bg-pink-900/40 text-pink-300' },
  services_b2b:      { label: 'Services B2B',        icon: '💼', color: 'bg-yellow-900/40 text-yellow-300' },
  communautes:       { label: 'Communautés',         icon: '🌍', color: 'bg-green-900/40 text-green-300' },
  digital:           { label: 'Digital & SEO',       icon: '🔗', color: 'bg-violet/20 text-violet-light' },
  autre:             { label: 'Autre',               icon: '•',  color: 'bg-surface2 text-muted' },
};

const TIERS = [
  { id: 0, label: 'Tous',              color: 'text-white',       bg: 'bg-surface2',          desc: 'Toutes les sources' },
  { id: 1, label: '✅ Email vérifié',  color: 'text-green-300',   bg: 'bg-green-900/30',      desc: 'Email confirmé valide (SMTP)' },
  { id: 2, label: '📧 Email présent',  color: 'text-blue-300',    bg: 'bg-blue-900/30',       desc: 'Email présent, non vérifié' },
  { id: 3, label: '🌐 Site web seul',  color: 'text-amber-300',   bg: 'bg-amber-900/30',      desc: 'Pas d\'email, mais un site/formulaire' },
  { id: 4, label: '❌ Aucun contact',  color: 'text-red-400',     bg: 'bg-red-900/20',        desc: 'Ni email ni site web' },
];

const SOURCES = [
  { id: 'all',               label: 'Toutes sources' },
  { id: 'influenceurs',      label: 'CRM principal (25 types)' },
  { id: 'press',             label: 'Journalistes & Presse' },
  { id: 'lawyers',           label: 'Avocats' },
  { id: 'businesses',        label: 'Entreprises expat' },
  { id: 'content_contacts',  label: 'Contacts web (scraping)' },
  { id: 'country_directory', label: 'Annuaire pays (consulats...)' },
];

const LANGUAGES: Record<string, { label: string; flag: string }> = {
  fr: { label: 'Français',   flag: '🇫🇷' },
  en: { label: 'English',    flag: '🇬🇧' },
  de: { label: 'Deutsch',    flag: '🇩🇪' },
  es: { label: 'Español',    flag: '🇪🇸' },
  pt: { label: 'Português',  flag: '🇵🇹' },
  ar: { label: 'العربية',     flag: '🇸🇦' },
  ru: { label: 'Русский',    flag: '🇷🇺' },
  zh: { label: '中文',         flag: '🇨🇳' },
  hi: { label: 'हिन्दी',       flag: '🇮🇳' },
  lt: { label: 'Lietuvių',   flag: '🇱🇹' },
  pl: { label: 'Polski',     flag: '🇵🇱' },
  it: { label: 'Italiano',   flag: '🇮🇹' },
  nl: { label: 'Nederlands', flag: '🇳🇱' },
};

const SOURCE_COLORS: Record<string, string> = {
  influenceurs:       'bg-violet/20 text-violet-light',
  lawyers:            'bg-yellow-900/40 text-yellow-300',
  press_contacts:     'bg-blue-900/40 text-blue-300',
  content_businesses: 'bg-green-900/40 text-green-300',
  content_contacts:   'bg-slate-700 text-slate-300',
  country_directory:  'bg-cyan-900/40 text-cyan-300',
};

const SOURCE_LABELS: Record<string, string> = {
  influenceurs:       'CRM',
  lawyers:            'Avocat',
  press_contacts:     'Journaliste',
  content_businesses: 'Entreprise',
  content_contacts:   'Contact web',
  country_directory:  'Annuaire pays',
};

const MATCH_TYPE_LABELS: Record<string, string> = {
  same_email:        'Email identique',
  same_url:          'URL identique',
  same_name_country: 'Nom + Pays identiques',
  cross_type:        'Cross-type (email)',
};

const MATCH_TYPE_COLORS: Record<string, string> = {
  same_email:        'bg-red-900/40 text-red-300',
  same_url:          'bg-amber-900/40 text-amber-300',
  same_name_country: 'bg-blue-900/40 text-blue-300',
  cross_type:        'bg-violet/30 text-violet-light',
};

// ─── Component ───────────────────────────────────────────────────────────────

export default function ContactsBase() {
  const [tab, setTab] = useState<'unified' | 'pipeline' | 'triage' | 'dedup'>('unified');

  // — Stats
  const [stats, setStats] = useState<Stats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

  // — Contacts list
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [loading, setLoading] = useState(false);
  const [total, setTotal] = useState(0);
  const [lastPage, setLastPage] = useState(1);
  const [page, setPage] = useState(1);

  // — Unified view state
  const [unified, setUnified] = useState<Contact[]>([]);
  const [unifiedTotal, setUnifiedTotal] = useState(0);
  const [unifiedLastPage, setUnifiedLastPage] = useState(1);
  const [unifiedPage, setUnifiedPage] = useState(1);
  const [unifiedLoading, setUnifiedLoading] = useState(false);
  const [uSearch, setUSearch] = useState('');
  const [uSearchInput, setUSearchInput] = useState('');
  const [uLanguage, setULanguage] = useState('');
  const [uCountry, setUCountry] = useState('');
  const [uType, setUType] = useState('');
  const [uCategory, setUCategory] = useState('');
  const [uSource, setUSource] = useState('');
  const [uExporting, setUExporting] = useState(false);

  // — Filters (triage tab)
  const [tier, setTier] = useState(0);
  const [source, setSource] = useState('all');
  const [filterLanguage, setFilterLanguage] = useState('');
  const [filterCountry, setFilterCountry] = useState('');
  const [emailOnly, setEmailOnly] = useState(true);
  const [search, setSearch] = useState('');
  const [searchInput, setSearchInput] = useState('');

  // — Dedup email-based
  const [dedupMode, setDedupMode] = useState<'email' | 'ai'>('email');
  const [dedupSource, setDedupSource] = useState('influenceurs');
  const [dupGroups, setDupGroups] = useState<any[]>([]);
  const [dedupLoading, setDedupLoading] = useState(false);
  const [deduping, setDeduping] = useState(false);

  // — Dedup AI flags
  const [flags, setFlags] = useState<DuplicateFlag[]>([]);
  const [flagsLoading, setFlagsLoading] = useState(false);
  const [resolvingFlag, setResolvingFlag] = useState<number | null>(null);
  const [flagStatusFilter, setFlagStatusFilter] = useState<'pending' | 'all'>('pending');

  // — Pipeline state
  const [pipelineStats, setPipelineStats] = useState<Record<string, { total: number; to_import: number; already: number }> | null>(null);
  const [pipelineLoading, setPipelineLoading] = useState(false);
  const [importing, setImporting] = useState<string | null>(null);
  const [importResult, setImportResult] = useState<string | null>(null);

  const [notif, setNotif] = useState<string | null>(null);

  const notify = (msg: string) => { setNotif(msg); setTimeout(() => setNotif(null), 6000); };

  const fetchPipelineStats = async () => {
    setPipelineLoading(true);
    try {
      const res = await api.get('/content-gen/import-pipeline/stats');
      setPipelineStats(res.data);
    } finally {
      setPipelineLoading(false);
    }
  };

  const handleImport = async (source: string) => {
    if (!confirm(`Importer les contacts de "${source}" dans le CRM ?`)) return;
    setImporting(source);
    setImportResult(null);
    try {
      const res = await api.post(`/content-gen/import-pipeline/import/${source}`);
      setImportResult(res.data.message);
      fetchPipelineStats();
    } catch (e: any) {
      setImportResult('Erreur : ' + (e.response?.data?.message || 'inconnue'));
    } finally {
      setImporting(null);
    }
  };

  const handleImportAll = async () => {
    if (!confirm('Importer TOUTES les sources dans le CRM ? (peut prendre quelques minutes)')) return;
    setImporting('all');
    setImportResult(null);
    try {
      const res = await api.post('/content-gen/import-pipeline/import-all');
      setImportResult(res.data.message);
      fetchPipelineStats();
    } catch (e: any) {
      setImportResult('Erreur : ' + (e.response?.data?.message || 'inconnue'));
    } finally {
      setImporting(null);
    }
  };

  useEffect(() => { if (tab === 'pipeline') fetchPipelineStats(); }, [tab]);

  // Debounce unified search
  useEffect(() => {
    const t = setTimeout(() => { setUSearch(uSearchInput); setUnifiedPage(1); }, 400);
    return () => clearTimeout(t);
  }, [uSearchInput]);

  // Load unified (deduplicated) contacts
  const fetchUnified = useCallback(async () => {
    setUnifiedLoading(true);
    try {
      const params: Record<string, string> = { page: String(unifiedPage), per_page: '50' };
      if (uSearch) params.search = uSearch;
      if (uLanguage) params.language = uLanguage;
      if (uCountry) params.country = uCountry;
      if (uType) params.type = uType;
      if (uCategory) params.category = uCategory;
      if (uSource) params.source = uSource;
      const res = await api.get('/content-gen/contacts-base/unified', { params });
      setUnified(res.data.data);
      setUnifiedTotal(res.data.total);
      setUnifiedLastPage(res.data.last_page);
    } finally {
      setUnifiedLoading(false);
    }
  }, [unifiedPage, uSearch, uLanguage, uCountry, uType, uCategory, uSource]);

  useEffect(() => { if (tab === 'unified') fetchUnified(); }, [fetchUnified, tab]);

  const handleUnifiedExport = () => {
    const p: Record<string, string> = {};
    if (uSearch) p.search = uSearch;
    if (uLanguage) p.language = uLanguage;
    if (uCountry) p.country = uCountry;
    if (uType) p.type = uType;
    if (uCategory) p.category = uCategory;
    if (uSource) p.source = uSource;
    window.open(`/api/content-gen/contacts-base/unified/export?${new URLSearchParams(p)}`, '_blank');
  };

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
      const params: Record<string, string> = { page: String(page), per_page: '50', tier: String(tier), source };
      if (search) params.search = search;
      if (filterLanguage) params.language = filterLanguage;
      if (filterCountry) params.country = filterCountry;
      if (emailOnly) params.email_only = '1';
      const res = await api.get('/content-gen/contacts-base/contacts', { params });
      setContacts(res.data.data);
      setTotal(res.data.total);
      setLastPage(res.data.last_page);
    } finally {
      setLoading(false);
    }
  }, [tier, source, search, filterLanguage, filterCountry, emailOnly, page]);

  useEffect(() => { if (tab === 'triage') fetchContacts(); }, [fetchContacts, tab]);

  // Load email-based duplicates
  const fetchDuplicates = useCallback(async () => {
    setDedupLoading(true);
    try {
      const res = await api.get('/content-gen/contacts-base/duplicates', { params: { source: dedupSource } });
      setDupGroups(res.data.groups || []);
    } finally {
      setDedupLoading(false);
    }
  }, [dedupSource]);

  // Load AI flags
  const fetchFlags = useCallback(async () => {
    setFlagsLoading(true);
    try {
      const params: Record<string, string> = {};
      if (flagStatusFilter === 'pending') params.status = 'pending';
      const res = await api.get('/quality/duplicates', { params });
      setFlags(res.data.data || res.data || []);
    } finally {
      setFlagsLoading(false);
    }
  }, [flagStatusFilter]);

  useEffect(() => {
    if (tab === 'dedup') {
      if (dedupMode === 'email') fetchDuplicates();
      else fetchFlags();
    }
  }, [tab, dedupMode, fetchDuplicates, fetchFlags]);

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

  const resolveFlag = async (flagId: number, action: 'merge_a' | 'merge_b' | 'dismiss' | 'keep_both') => {
    setResolvingFlag(flagId);
    try {
      await api.post(`/quality/duplicates/${flagId}/resolve`, { action });
      setFlags(prev => prev.filter(f => f.id !== flagId));
      notify(action === 'merge_a' ? 'Fusionné vers A' : action === 'merge_b' ? 'Fusionné vers B' : action === 'dismiss' ? 'Pas un doublon' : 'Conservés séparément');
    } catch {
      notify('Erreur lors de la résolution.');
    } finally {
      setResolvingFlag(null);
    }
  };

  const handleExport = async () => {
    const p: Record<string, string> = { tier: String(tier), source, search };
    if (filterLanguage) p.language = filterLanguage;
    if (filterCountry) p.country = filterCountry;
    if (emailOnly) p.email_only = '1';
    const params = new URLSearchParams(p);
    window.open(`/api/content-gen/contacts-base/contacts/export?${params}`, '_blank');
  };

  const totalDupes = stats ? Object.values(stats.duplicates).reduce((s, n) => s + n, 0) : 0;
  const pendingFlags = flags.filter(f => f.status === 'pending').length;

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-title text-2xl font-bold text-white">Base Contacts Unifiée</h1>
          <p className="text-muted text-sm mt-1">Toutes sources fusionnées · Filtre par langue, pays, type · Export CSV</p>
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
                        {(stats.duplicates[src] || 0) > 0
                          ? <span className="text-red-400 font-medium">{stats.duplicates[src]}</span>
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
          { id: 'unified',  label: `✅ Liste propre (${unifiedTotal > 0 ? unifiedTotal.toLocaleString() + ' uniques' : '…'})` },
          { id: 'pipeline', label: '⬆ Import → CRM' },
          { id: 'triage',   label: `Triage sources (${total.toLocaleString()})` },
          { id: 'dedup',    label: `Déduplication${totalDupes > 0 ? ` (${totalDupes})` : ''}` },
        ].map(t => (
          <button key={t.id} onClick={() => setTab(t.id as any)}
            className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${tab === t.id ? 'bg-violet text-white' : 'text-muted hover:text-white'}`}>
            {t.label}
          </button>
        ))}
      </div>

      {/* ─── TAB : LISTE UNIFIÉE (sans doublons) ─────────────────────────────── */}
      {tab === 'unified' && (
        <>
          {/* Filtres */}
          <div className="flex flex-wrap gap-3 items-center">
            <input type="text" value={uSearchInput} onChange={e => setUSearchInput(e.target.value)}
              placeholder="Nom, email, pays..."
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-64" />

            <select value={uLanguage} onChange={e => { setULanguage(e.target.value); setUnifiedPage(1); }}
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="">Toutes les langues</option>
              {Object.entries(LANGUAGES).map(([code, { label, flag }]) => (
                <option key={code} value={code}>{flag} {label}</option>
              ))}
            </select>

            <input type="text" value={uCountry} onChange={e => { setUCountry(e.target.value); setUnifiedPage(1); }}
              placeholder="Pays..."
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-32" />

            <select value={uCategory} onChange={e => { setUCategory(e.target.value); setUnifiedPage(1); }}
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="">Toutes catégories</option>
              {Object.entries(CATEGORIES).filter(([k]) => k !== 'autre').map(([key, { label, icon }]) => (
                <option key={key} value={key}>{icon} {label}</option>
              ))}
            </select>

            <select value={uSource} onChange={e => { setUSource(e.target.value); setUnifiedPage(1); }}
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="">Toutes les sources</option>
              {SOURCES.filter(s => s.id !== 'all').map(s => (
                <option key={s.id} value={s.id === 'press' ? 'press_contacts' : s.id === 'businesses' ? 'content_businesses' : s.id}>{s.label}</option>
              ))}
            </select>

            <button onClick={handleUnifiedExport} disabled={uExporting}
              className="ml-auto px-3 py-1.5 bg-green-900/30 text-green-300 border border-green-500/30 rounded-lg text-xs font-medium hover:bg-green-900/50 transition-colors disabled:opacity-50">
              {uExporting ? '...' : '⬇ Exporter CSV'}
            </button>

            <span className="text-muted text-sm font-medium">
              <span className="text-white">{unifiedTotal.toLocaleString()}</span> contacts uniques
            </span>
          </div>

          {/* Table */}
          <div className="bg-surface border border-border rounded-xl overflow-x-auto">
            {unifiedLoading ? (
              <div className="flex justify-center py-12"><div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left">
                    <th className="px-4 py-3 text-muted font-medium text-xs">Nom</th>
                    <th className="px-4 py-3 text-muted font-medium text-xs">Email</th>
                    <th className="px-4 py-3 text-muted font-medium text-xs">Catégorie</th>
                    <th className="px-4 py-3 text-muted font-medium text-xs">Type</th>
                    <th className="px-4 py-3 text-muted font-medium text-xs">Source</th>
                    <th className="px-4 py-3 text-muted font-medium text-xs">Langue</th>
                    <th className="px-4 py-3 text-muted font-medium text-xs">Pays</th>
                    <th className="px-4 py-3 text-muted font-medium text-xs">Tel</th>
                  </tr>
                </thead>
                <tbody>
                  {unified.map((c, i) => (
                    <tr key={`${c.source_table}-${i}`} className="border-b border-border/50 hover:bg-surface2 transition-colors">
                      <td className="px-4 py-2.5">
                        <div className="text-white font-medium text-sm">{c.name || '—'}</div>
                      </td>
                      <td className="px-4 py-2.5">
                        <a href={`mailto:${c.email}`} className="text-green-400 text-xs hover:underline">
                          {c.email}
                        </a>
                      </td>
                      <td className="px-4 py-2.5">
                        {c.category && CATEGORIES[c.category] ? (
                          <span className={`px-1.5 py-0.5 rounded text-[10px] font-medium ${CATEGORIES[c.category].color}`}>
                            {CATEGORIES[c.category].icon} {CATEGORIES[c.category].label}
                          </span>
                        ) : <span className="text-muted/40 text-xs">—</span>}
                      </td>
                      <td className="px-4 py-2.5">
                        <span className="px-1.5 py-0.5 bg-surface2 text-muted rounded text-[10px]">{c.type || '—'}</span>
                      </td>
                      <td className="px-4 py-2.5">
                        <span className={`px-1.5 py-0.5 rounded text-[10px] font-medium ${SOURCE_COLORS[c.source_table] || 'bg-surface2 text-muted'}`}>
                          {SOURCE_LABELS[c.source_table] || c.source_table}
                        </span>
                      </td>
                      <td className="px-4 py-2.5">
                        {c.language && LANGUAGES[c.language] ? (
                          <span title={LANGUAGES[c.language].label}>{LANGUAGES[c.language].flag} <span className="text-xs text-muted">{c.language.toUpperCase()}</span></span>
                        ) : c.language ? (
                          <span className="text-xs text-muted">{c.language.toUpperCase()}</span>
                        ) : <span className="text-muted/40 text-xs">—</span>}
                      </td>
                      <td className="px-4 py-2.5 text-muted text-xs">{c.country || '—'}</td>
                      <td className="px-4 py-2.5 text-muted text-xs">{c.phone || '—'}</td>
                    </tr>
                  ))}
                  {unified.length === 0 && !unifiedLoading && (
                    <tr><td colSpan={8} className="px-4 py-12 text-center text-muted">Aucun contact</td></tr>
                  )}
                </tbody>
              </table>
            )}
          </div>

          {/* Pagination */}
          {unifiedLastPage > 1 && (
            <div className="flex justify-center gap-2">
              <button onClick={() => setUnifiedPage(p => Math.max(1, p - 1))} disabled={unifiedPage === 1}
                className="px-3 py-1.5 bg-surface2 text-muted rounded-lg text-xs disabled:opacity-50 hover:text-white">← Préc.</button>
              <span className="px-3 py-1.5 text-muted text-xs self-center">{unifiedPage} / {unifiedLastPage}</span>
              <button onClick={() => setUnifiedPage(p => Math.min(unifiedLastPage, p + 1))} disabled={unifiedPage === unifiedLastPage}
                className="px-3 py-1.5 bg-surface2 text-muted rounded-lg text-xs disabled:opacity-50 hover:text-white">Suiv. →</button>
            </div>
          )}
        </>
      )}

      {/* ─── TAB : PIPELINE D'IMPORT ─────────────────────────────────────────── */}
      {tab === 'pipeline' && (
        <div className="space-y-4">
          <div className="bg-blue-900/20 border border-blue-500/30 rounded-xl p-4 text-sm text-blue-300">
            Le pipeline importe les contacts des tables scraping dans le CRM (<code>influenceurs</code>).
            Les emails déjà présents dans le CRM sont ignorés automatiquement.
          </div>

          {importResult && (
            <div className="bg-green-900/20 border border-green-500/30 text-green-300 p-3 rounded-xl text-sm flex justify-between">
              <span>{importResult}</span>
              <button onClick={() => setImportResult(null)}>×</button>
            </div>
          )}

          {pipelineLoading ? (
            <div className="flex justify-center py-12"><div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>
          ) : pipelineStats && (
            <>
              <div className="bg-surface border border-border rounded-xl overflow-hidden">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-border">
                      <th className="px-4 py-3 text-left text-muted font-medium text-xs">Source</th>
                      <th className="px-4 py-3 text-right text-muted font-medium text-xs">Total emails</th>
                      <th className="px-4 py-3 text-right text-green-400/70 font-medium text-xs">Déjà dans CRM</th>
                      <th className="px-4 py-3 text-right text-violet-light/70 font-medium text-xs">À importer</th>
                      <th className="px-4 py-3 text-center text-muted font-medium text-xs">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {[
                      { id: 'press',      label: '🗞️ Journalistes & Presse',    color: 'bg-blue-900/40 text-blue-300' },
                      { id: 'lawyers',    label: '⚖️ Avocats',                  color: 'bg-yellow-900/40 text-yellow-300' },
                      { id: 'businesses', label: '🏢 Entreprises expat',         color: 'bg-green-900/40 text-green-300' },
                      { id: 'contacts',   label: '🌐 Contacts web',              color: 'bg-slate-700 text-slate-300' },
                      { id: 'directory',  label: '🗺️ Annuaire pays',             color: 'bg-cyan-900/40 text-cyan-300' },
                    ].map(src => {
                      const s = pipelineStats[src.id];
                      if (!s) return null;
                      return (
                        <tr key={src.id} className="border-b border-border/50 hover:bg-surface2">
                          <td className="px-4 py-3">
                            <span className={`px-2 py-0.5 rounded text-[10px] font-medium ${src.color}`}>{src.label}</span>
                          </td>
                          <td className="px-4 py-3 text-right text-white font-medium">{s.total.toLocaleString()}</td>
                          <td className="px-4 py-3 text-right text-green-400">{s.already.toLocaleString()}</td>
                          <td className="px-4 py-3 text-right">
                            {s.to_import > 0
                              ? <span className="text-violet-light font-bold">{s.to_import.toLocaleString()}</span>
                              : <span className="text-muted">—</span>}
                          </td>
                          <td className="px-4 py-3 text-center">
                            {s.to_import > 0 ? (
                              <button onClick={() => handleImport(src.id)} disabled={importing !== null}
                                className="px-3 py-1.5 bg-violet hover:bg-violet/80 text-white rounded-lg text-xs font-medium disabled:opacity-50 transition-colors">
                                {importing === src.id ? 'Import...' : `Importer ${s.to_import.toLocaleString()}`}
                              </button>
                            ) : (
                              <span className="text-green-400 text-xs">✓ Tout importé</span>
                            )}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>

              {Object.values(pipelineStats).some(s => s.to_import > 0) && (
                <div className="flex justify-end">
                  <button onClick={handleImportAll} disabled={importing !== null}
                    className="px-5 py-2.5 bg-violet hover:bg-violet/80 text-white rounded-xl text-sm font-bold disabled:opacity-50 transition-colors">
                    {importing === 'all' ? 'Import en cours...' : `⬆ Tout importer dans le CRM`}
                  </button>
                </div>
              )}
            </>
          )}
        </div>
      )}

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

            <select value={filterLanguage} onChange={e => { setFilterLanguage(e.target.value); setPage(1); }}
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="">Toutes les langues</option>
              {Object.entries(LANGUAGES).map(([code, { label, flag }]) => (
                <option key={code} value={code}>{flag} {label}</option>
              ))}
            </select>

            <input type="text" value={filterCountry} onChange={e => { setFilterCountry(e.target.value); setPage(1); }}
              placeholder="Pays..."
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-32" />

            <button onClick={() => { setEmailOnly(!emailOnly); setPage(1); }}
              className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${emailOnly ? 'bg-green-900/40 text-green-300 border border-green-500/30' : 'bg-surface2 text-muted hover:text-white'}`}>
              ✉ Avec email
            </button>

            <div className="flex gap-1">
              {TIERS.map(t => (
                <button key={t.id} onClick={() => { setTier(t.id); setEmailOnly(false); setPage(1); }}
                  className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${tier === t.id && !emailOnly ? t.bg + ' ' + t.color + ' border border-white/20' : 'bg-surface2 text-muted hover:text-white'}`}>
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
                    <th className="px-4 py-3 text-muted font-medium text-xs">Langue</th>
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
                        <td className="px-4 py-2.5">
                          {c.language && LANGUAGES[c.language] ? (
                            <span className="text-sm" title={LANGUAGES[c.language].label}>
                              {LANGUAGES[c.language].flag} <span className="text-xs text-muted">{c.language.toUpperCase()}</span>
                            </span>
                          ) : c.language ? (
                            <span className="text-xs text-muted">{c.language.toUpperCase()}</span>
                          ) : (
                            <span className="text-muted/40 text-xs">—</span>
                          )}
                        </td>
                        <td className="px-4 py-2.5 text-muted text-xs">{c.country || '—'}</td>
                      </tr>
                    );
                  })}
                  {contacts.length === 0 && !loading && (
                    <tr><td colSpan={7} className="px-4 py-12 text-center text-muted">Aucun contact pour ce filtre</td></tr>
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

          {/* Mode switcher */}
          <div className="flex gap-1 bg-surface p-1 rounded-lg w-fit">
            <button onClick={() => setDedupMode('email')}
              className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${dedupMode === 'email' ? 'bg-surface2 text-white' : 'text-muted hover:text-white'}`}>
              Doublons email
            </button>
            <button onClick={() => setDedupMode('ai')}
              className={`px-4 py-2 rounded-md text-sm font-medium transition-colors flex items-center gap-2 ${dedupMode === 'ai' ? 'bg-surface2 text-white' : 'text-muted hover:text-white'}`}>
              Flags IA
              {pendingFlags > 0 && (
                <span className="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{pendingFlags}</span>
              )}
            </button>
          </div>

          {/* ── Mode email ── */}
          {dedupMode === 'email' && (
            <>
              <div className="flex gap-3 items-center">
                <select value={dedupSource} onChange={e => setDedupSource(e.target.value)}
                  className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
                  <option value="influenceurs">CRM principal (contacts unifiés)</option>
                  <option value="lawyers">Avocats</option>
                  <option value="press">Journalistes</option>
                  <option value="businesses">Entreprises expat</option>
                  <option value="content_contacts">Contacts web</option>
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
                  <div className="text-green-300 font-medium">Aucun doublon email dans {dedupSource}</div>
                </div>
              ) : (
                <div className="space-y-2">
                  <div className="text-muted text-sm">{dupGroups.length} groupes · {dupGroups.reduce((s, g) => s + (g.count - 1), 0)} à supprimer</div>
                  <div className="bg-surface border border-border rounded-xl overflow-hidden">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b border-border">
                          <th className="px-4 py-2 text-left text-muted font-medium text-xs">Email</th>
                          <th className="px-4 py-2 text-left text-muted font-medium text-xs">Occurrences</th>
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
            </>
          )}

          {/* ── Mode Flags IA ── */}
          {dedupMode === 'ai' && (
            <>
              <div className="flex gap-3 items-center">
                <div className="flex gap-1 bg-surface p-1 rounded-lg">
                  <button onClick={() => setFlagStatusFilter('pending')}
                    className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${flagStatusFilter === 'pending' ? 'bg-violet text-white' : 'text-muted hover:text-white'}`}>
                    À traiter
                  </button>
                  <button onClick={() => setFlagStatusFilter('all')}
                    className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${flagStatusFilter === 'all' ? 'bg-violet text-white' : 'text-muted hover:text-white'}`}>
                    Tous
                  </button>
                </div>
                <button onClick={fetchFlags} disabled={flagsLoading}
                  className="px-3 py-2 bg-surface2 text-muted rounded-lg text-xs hover:text-white transition-colors">
                  Actualiser
                </button>
                <p className="text-muted text-xs">
                  L'IA détecte les doublons potentiels (email, URL, nom+pays) même entre types différents.
                </p>
              </div>

              {flagsLoading ? (
                <div className="flex justify-center py-12"><div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>
              ) : flags.length === 0 ? (
                <div className="bg-green-900/20 border border-green-500/30 rounded-xl p-8 text-center">
                  <div className="text-green-400 text-2xl mb-2">✅</div>
                  <div className="text-green-300 font-medium">
                    {flagStatusFilter === 'pending' ? 'Aucun doublon en attente de traitement' : 'Aucun flag détecté'}
                  </div>
                </div>
              ) : (
                <div className="space-y-3">
                  <div className="text-muted text-sm">{flags.length} paire{flags.length > 1 ? 's' : ''} à traiter</div>
                  {flags.map(flag => (
                    <div key={flag.id} className="bg-surface border border-border rounded-xl overflow-hidden">
                      {/* Flag header */}
                      <div className="flex items-center gap-3 px-4 py-3 border-b border-border bg-surface2/50">
                        <span className={`px-2 py-0.5 rounded text-[10px] font-medium ${MATCH_TYPE_COLORS[flag.match_type] || 'bg-surface2 text-muted'}`}>
                          {MATCH_TYPE_LABELS[flag.match_type] || flag.match_type}
                        </span>
                        <div className="flex items-center gap-1.5">
                          <div className="w-20 h-1.5 bg-surface rounded-full overflow-hidden">
                            <div
                              className={`h-full rounded-full ${flag.confidence >= 90 ? 'bg-red-400' : flag.confidence >= 80 ? 'bg-amber-400' : 'bg-blue-400'}`}
                              style={{ width: `${flag.confidence}%` }}
                            />
                          </div>
                          <span className={`text-xs font-bold ${flag.confidence >= 90 ? 'text-red-400' : flag.confidence >= 80 ? 'text-amber-400' : 'text-blue-400'}`}>
                            {flag.confidence}%
                          </span>
                        </div>
                        {flag.status !== 'pending' && (
                          <span className="ml-auto text-[10px] text-muted bg-surface px-2 py-0.5 rounded">{flag.status}</span>
                        )}
                      </div>

                      {/* Side-by-side cards */}
                      <div className="grid grid-cols-2 divide-x divide-border">
                        {[{ contact: flag.influenceur_a, label: 'A' }, { contact: flag.influenceur_b, label: 'B' }].map(({ contact, label }) => (
                          <div key={label} className="p-4 space-y-1.5">
                            <div className="flex items-center gap-2 mb-2">
                              <span className="text-[10px] font-bold bg-surface2 text-muted px-2 py-0.5 rounded">Contact {label}</span>
                              {contact.category && (
                                <span className="text-[10px] bg-violet/20 text-violet-light px-2 py-0.5 rounded">{contact.category}</span>
                              )}
                            </div>
                            <div className="text-white font-medium text-sm">{contact.name || '—'}</div>
                            {contact.email && (
                              <div className="text-green-400 text-xs font-mono">{contact.email}</div>
                            )}
                            {contact.website && (
                              <div className="text-amber-300 text-xs truncate">{contact.website.replace(/^https?:\/\//, '')}</div>
                            )}
                            <div className="flex gap-2 flex-wrap mt-1">
                              {contact.contact_type && (
                                <span className="text-[10px] bg-surface2 text-muted px-1.5 py-0.5 rounded">{contact.contact_type}</span>
                              )}
                              {contact.country && (
                                <span className="text-[10px] text-muted">{contact.country}</span>
                              )}
                            </div>
                          </div>
                        ))}
                      </div>

                      {/* Actions */}
                      {flag.status === 'pending' && (
                        <div className="flex items-center gap-2 px-4 py-3 border-t border-border bg-surface2/30">
                          <button
                            onClick={() => resolveFlag(flag.id, 'merge_a')}
                            disabled={resolvingFlag === flag.id}
                            className="px-3 py-1.5 bg-blue-900/40 text-blue-300 border border-blue-500/30 rounded-lg text-xs hover:bg-blue-900/60 disabled:opacity-50 transition-colors">
                            ← Garder A
                          </button>
                          <button
                            onClick={() => resolveFlag(flag.id, 'merge_b')}
                            disabled={resolvingFlag === flag.id}
                            className="px-3 py-1.5 bg-blue-900/40 text-blue-300 border border-blue-500/30 rounded-lg text-xs hover:bg-blue-900/60 disabled:opacity-50 transition-colors">
                            Garder B →
                          </button>
                          <button
                            onClick={() => resolveFlag(flag.id, 'dismiss')}
                            disabled={resolvingFlag === flag.id}
                            className="px-3 py-1.5 bg-surface2 text-muted border border-border rounded-lg text-xs hover:text-white disabled:opacity-50 transition-colors">
                            Pas un doublon
                          </button>
                          <button
                            onClick={() => resolveFlag(flag.id, 'keep_both')}
                            disabled={resolvingFlag === flag.id}
                            className="px-3 py-1.5 bg-surface2 text-muted border border-border rounded-lg text-xs hover:text-white disabled:opacity-50 transition-colors">
                            Conserver séparément
                          </button>
                          {resolvingFlag === flag.id && (
                            <div className="ml-2 w-4 h-4 border-2 border-violet border-t-transparent rounded-full animate-spin" />
                          )}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </>
          )}
        </div>
      )}
    </div>
  );
}
