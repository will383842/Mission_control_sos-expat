import { useEffect, useState, useCallback, useRef } from 'react';
import api from '../../api/client';
import { toast } from '../../components/Toast';
import { getLanguageLabel, getLanguageFlag, getCountryFlag } from '../../lib/constants';

interface LawyerRecord {
  id: number;
  full_name: string;
  first_name: string | null;
  last_name: string | null;
  firm_name: string | null;
  email: string;
  phone: string | null;
  website: string | null;
  country: string | null;
  country_code: string | null;
  city: string | null;
  region: string | null;
  specialty: string | null;
  bar_association: string | null;
  language: string | null;
  is_immigration_lawyer: boolean;
  source_slug: string;
  scraped_at: string | null;
}

interface Stats {
  total: number;
  with_email: number;
  with_phone: number;
  immigration: number;
  by_country: { country: string; country_code: string; count: number }[];
  by_language: { language: string; count: number }[];
  by_specialty: { specialty: string; count: number }[];
  by_source: { source_slug: string; count: number }[];
}

interface DirectorySource {
  id: number;
  slug: string;
  name: string;
  status: string;
  total_lawyers: number;
  total_with_email: number;
  last_scraped_at: string | null;
}


export default function LawyerDirectory() {
  const [lawyers, setLawyers] = useState<LawyerRecord[]>([]);
  const [stats, setStats] = useState<Stats | null>(null);
  const [sources, setSources] = useState<DirectorySource[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  // Filters
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [filterCountry, setFilterCountry] = useState('');
  const [filterLanguage, setFilterLanguage] = useState('');
  const [filterSpecialty, setFilterSpecialty] = useState('');
  const [filterSource, setFilterSource] = useState('');
  const [filterImmigration, setFilterImmigration] = useState(false);
  const [tab, setTab] = useState<'list' | 'countries' | 'languages' | 'stats'>('list');
  const [exporting, setExporting] = useState(false);
  const [scraping, setScraping] = useState<string | null>(null);

  const abortRef = useRef<AbortController | null>(null);

  // Debounce search
  useEffect(() => {
    const timer = setTimeout(() => { setSearch(searchInput); setPage(1); }, 400);
    return () => clearTimeout(timer);
  }, [searchInput]);

  const fetchLawyers = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    setLoading(true);
    try {
      const params: Record<string, string> = { page: String(page), per_page: '50' };
      if (search) params.search = search;
      if (filterCountry) params.country = filterCountry;
      if (filterLanguage) params.language = filterLanguage;
      if (filterSpecialty) params.specialty = filterSpecialty;
      if (filterSource) params.source = filterSource;
      if (filterImmigration) params.immigration_only = '1';
      const res = await api.get('/lawyers', { params, signal: controller.signal });
      if (!controller.signal.aborted) {
        setLawyers(res.data.data);
        setLastPage(res.data.last_page);
        setTotal(res.data.total);
      }
    } catch (err: unknown) {
      if (err instanceof Error && err.name === 'CanceledError') return;
    } finally {
      if (!controller.signal.aborted) setLoading(false);
    }
  }, [page, search, filterCountry, filterLanguage, filterSpecialty, filterSource, filterImmigration]);

  useEffect(() => { fetchLawyers(); }, [fetchLawyers]);

  useEffect(() => {
    Promise.all([
      api.get('/lawyers/stats'),
      api.get('/lawyers/sources'),
    ]).then(([statsRes, sourcesRes]) => {
      setStats(statsRes.data);
      setSources(sourcesRes.data);
    }).catch(() => {});
  }, []);

  const handleScrape = async (slug: string) => {
    setScraping(slug);
    try {
      await api.post(`/lawyers/scrape/${slug}`);
      const res = await api.get('/lawyers/sources');
      setSources(res.data);
    } catch {
      toast('error', 'Erreur lors du lancement du scraping');
    } finally {
      setScraping(null);
    }
  };

  const handleScrapeAll = async () => {
    setScraping('all');
    try {
      await api.post('/lawyers/scrape-all');
      const res = await api.get('/lawyers/sources');
      setSources(res.data);
    } catch {
      toast('error', 'Erreur lors du lancement du scraping');
    } finally {
      setScraping(null);
    }
  };

  const handleExport = async () => {
    setExporting(true);
    try {
      const params: Record<string, string> = {};
      if (filterCountry) params.country = filterCountry;
      if (filterLanguage) params.language = filterLanguage;
      if (filterSpecialty) params.specialty = filterSpecialty;
      if (filterSource) params.source = filterSource;
      if (filterImmigration) params.immigration_only = '1';

      const res = await api.get('/lawyers/export', { params });
      const data = res.data;

      const bom = '\uFEFF';
      const headers = ['Nom', 'Email', 'Telephone', 'Cabinet', 'Site Web', 'Pays', 'Code Pays', 'Ville', 'Region', 'Adresse', 'Specialite', 'Barreau', 'Langue', 'Source'];
      const rows = data.map((l: Record<string, string>) =>
        [l.full_name, l.email, l.phone, l.firm_name, l.website, l.country, l.country_code, l.city, l.region, l.address, l.specialty, l.bar_association, l.language, l.source_slug]
          .map(v => `"${(v || '').replace(/"/g, '""')}"`)
          .join(',')
      );
      const csv = bom + [headers.join(','), ...rows].join('\n');
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url; a.download = `avocats-export-${new Date().toISOString().slice(0, 10)}.csv`;
      a.click(); URL.revokeObjectURL(url);
    } catch {
      toast('error', 'Erreur lors de l\'export');
    } finally {
      setExporting(false);
    }
  };

  const statusBadge = (status: string) => {
    const colors: Record<string, string> = {
      pending: 'bg-gray-600', scraping: 'bg-yellow-600 animate-pulse', completed: 'bg-green-600', failed: 'bg-red-600',
    };
    return <span className={`px-2 py-0.5 rounded text-xs font-medium ${colors[status] || 'bg-gray-600'}`}>{status}</span>;
  };

  return (
    <div className="p-4 md:p-6 space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">Annuaire Avocats Mondial</h2>
          <p className="text-muted text-sm mt-0.5">
            {stats ? `${stats.total.toLocaleString()} avocats dans ${stats.by_country.length} pays` : 'Chargement...'}
          </p>
        </div>
        <div className="flex gap-2">
          <button onClick={handleExport} disabled={exporting}
            className="px-4 py-1.5 bg-surface2 border border-border text-muted hover:text-white text-sm rounded-lg disabled:opacity-50">
            {exporting ? 'Export...' : 'Exporter CSV'}
          </button>
          <button onClick={handleScrapeAll} disabled={!!scraping}
            className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg font-medium disabled:opacity-50">
            {scraping === 'all' ? 'Lancement...' : 'Scraper Tout'}
          </button>
        </div>
      </div>

      {/* Stats pills */}
      {stats && (
        <div className="flex flex-wrap gap-2">
          <span className="px-3 py-1 bg-surface border border-border rounded-lg text-xs">
            <span className="text-white font-semibold">{stats.total.toLocaleString()}</span>
            <span className="text-muted ml-1">avocats</span>
          </span>
          {stats.total > 0 && (
            <span className="px-3 py-1 bg-surface border border-border rounded-lg text-xs">
              <span className="text-green-400 font-semibold">{Math.round(stats.with_email / stats.total * 100)}%</span>
              <span className="text-muted ml-1">avec email</span>
            </span>
          )}
          <span className="px-3 py-1 bg-surface border border-border rounded-lg text-xs">
            <span className="text-blue-400 font-semibold">{stats.by_country.length}</span>
            <span className="text-muted ml-1">pays</span>
          </span>
          <span className="px-3 py-1 bg-surface border border-border rounded-lg text-xs">
            <span className="text-orange-400 font-semibold">{stats.immigration.toLocaleString()}</span>
            <span className="text-muted ml-1">immigration</span>
          </span>
          {stats.by_language.slice(0, 4).map((l) => (
            <button key={l.language}
              onClick={() => { setFilterLanguage(filterLanguage === l.language ? '' : l.language); setTab('list'); setPage(1); }}
              className={`px-3 py-1 border rounded-lg text-xs transition-colors ${filterLanguage === l.language ? 'bg-violet/20 border-violet/50 text-white' : 'bg-surface border-border text-muted hover:text-white'}`}>
              {getLanguageFlag(l.language)} <span className="font-semibold text-white ml-1">{l.count.toLocaleString()}</span>
            </button>
          ))}
          {stats.by_country.slice(0, 4).map((c) => (
            <button key={c.country_code}
              onClick={() => { setFilterCountry(filterCountry === c.country_code ? '' : c.country_code); setTab('list'); setPage(1); }}
              className={`px-3 py-1 border rounded-lg text-xs transition-colors ${filterCountry === c.country_code ? 'bg-violet/20 border-violet/50 text-white' : 'bg-surface border-border text-muted hover:text-white'}`}>
              {getCountryFlag(c.country)} {c.country} <span className="font-semibold text-white ml-1">{c.count.toLocaleString()}</span>
            </button>
          ))}
        </div>
      )}

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        {(['list', 'countries', 'languages', 'stats'] as const).map(t => (
          <button key={t} onClick={() => setTab(t)}
            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
              tab === t ? 'border-violet text-white' : 'border-transparent text-muted hover:text-white'
            }`}>
            {t === 'list' ? 'Liste' : t === 'countries' ? 'Par Pays' : t === 'languages' ? 'Par Langue' : 'Sources'}
          </button>
        ))}
      </div>

      {/* Tab: List */}
      {tab === 'list' && (
        <>
          {/* Filters */}
          <div className="flex flex-wrap gap-3 items-center">
            <input type="text" placeholder="Rechercher nom, email, cabinet, ville..."
              value={searchInput} onChange={e => setSearchInput(e.target.value)}
              className="flex-1 min-w-[200px] px-3 py-2 bg-surface border border-border rounded-lg text-sm text-white placeholder-gray-500 focus:border-violet-500 focus:outline-none" />

            <select value={filterCountry} onChange={e => { setFilterCountry(e.target.value); setPage(1); }}
              className="px-3 py-2 bg-surface border border-border rounded-lg text-sm text-white">
              <option value="">Tous les pays</option>
              {stats?.by_country.map(c => (
                <option key={c.country_code} value={c.country_code}>{c.country} ({c.count})</option>
              ))}
            </select>

            <select value={filterLanguage} onChange={e => { setFilterLanguage(e.target.value); setPage(1); }}
              className="px-3 py-2 bg-surface border border-border rounded-lg text-sm text-white">
              <option value="">Toutes les langues</option>
              {stats?.by_language.map(l => (
                <option key={l.language} value={l.language}>{getLanguageLabel(l.language)} ({l.count})</option>
              ))}
            </select>

            <select value={filterSpecialty} onChange={e => { setFilterSpecialty(e.target.value); setPage(1); }}
              className="px-3 py-2 bg-surface border border-border rounded-lg text-sm text-white">
              <option value="">Toutes specialites</option>
              {stats?.by_specialty.map(s => (
                <option key={s.specialty} value={s.specialty}>{s.specialty} ({s.count})</option>
              ))}
            </select>

            <select value={filterSource} onChange={e => { setFilterSource(e.target.value); setPage(1); }}
              className="px-3 py-2 bg-surface border border-border rounded-lg text-sm text-white">
              <option value="">Toutes sources</option>
              {sources.map(s => (
                <option key={s.slug} value={s.slug}>{s.name}</option>
              ))}
            </select>

            <label className="flex items-center gap-2 text-sm text-muted cursor-pointer">
              <input type="checkbox" checked={filterImmigration}
                onChange={e => { setFilterImmigration(e.target.checked); setPage(1); }}
                className="rounded border-border" />
              Immigration
            </label>
          </div>

          {/* Results count */}
          <div className="text-sm text-muted">{total.toLocaleString()} résultats</div>

          {/* Table */}
          <div className="overflow-x-auto bg-surface border border-border rounded-xl">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-muted">
                  <th className="px-4 py-3 font-medium">Nom</th>
                  <th className="px-4 py-3 font-medium">Email</th>
                  <th className="px-4 py-3 font-medium">Pays</th>
                  <th className="px-4 py-3 font-medium">Ville</th>
                  <th className="px-4 py-3 font-medium">Langue</th>
                  <th className="px-4 py-3 font-medium">Specialite</th>
                  <th className="px-4 py-3 font-medium">Cabinet</th>
                  <th className="px-4 py-3 font-medium">Source</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={8} className="px-4 py-12 text-center">
                    <div className="flex justify-center"><div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>
                  </td></tr>
                ) : lawyers.length === 0 ? (
                  <tr>
                    <td colSpan={8} className="px-4 py-16 text-center">
                      <div className="text-4xl mb-3">⚖️</div>
                      <div className="text-white font-medium">Aucun avocat trouvé</div>
                      <div className="text-muted text-sm mt-1">Modifiez vos filtres ou lancez un scraping</div>
                    </td>
                  </tr>
                ) : lawyers.map(l => (
                  <tr key={l.id} className="border-b border-border/50 hover:bg-surface2/50 transition-colors">
                    <td className="px-4 py-3 text-white font-medium">{l.full_name}</td>
                    <td className="px-4 py-3">
                      <a href={`mailto:${l.email}`} className="text-violet-light hover:underline">{l.email}</a>
                    </td>
                    <td className="px-4 py-3 text-white/80">{l.country || '-'}</td>
                    <td className="px-4 py-3 text-white/80">{l.city || '-'}</td>
                    <td className="px-4 py-3">
                      <span className="text-xs">{getLanguageLabel(l.language || '')}</span>
                    </td>
                    <td className="px-4 py-3 text-white/80">
                      {l.is_immigration_lawyer && <span className="inline-block mr-1 px-1.5 py-0.5 bg-orange-600/20 text-orange-400 text-xs rounded">Immigration</span>}
                      {l.specialty || '-'}
                    </td>
                    <td className="px-4 py-3 text-muted text-xs">{l.firm_name || '-'}</td>
                    <td className="px-4 py-3 text-muted text-xs">{l.source_slug}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {lastPage > 1 && (
            <div className="flex items-center justify-center gap-2">
              <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1}
                className="px-3 py-1 bg-surface border border-border rounded text-sm text-white disabled:opacity-30">← Préc.</button>
              <span className="text-sm text-muted">Page {page} / {lastPage}</span>
              <button onClick={() => setPage(p => Math.min(lastPage, p + 1))} disabled={page === lastPage}
                className="px-3 py-1 bg-surface border border-border rounded text-sm text-white disabled:opacity-30">Suiv. →</button>
            </div>
          )}
        </>
      )}

      {/* Tab: Countries */}
      {tab === 'countries' && stats && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          {stats.by_country.map(c => (
            <button key={c.country_code} onClick={() => { setFilterCountry(c.country_code); setTab('list'); setPage(1); }}
              className="flex items-center justify-between p-4 bg-surface border border-border rounded-lg hover:border-violet/50 transition-colors">
              <div>
                <div className="text-white font-medium">{c.country}</div>
                <div className="text-xs text-muted">{c.country_code}</div>
              </div>
              <div className="text-xl font-bold text-violet-light">{c.count}</div>
            </button>
          ))}
        </div>
      )}

      {/* Tab: Languages */}
      {tab === 'languages' && stats && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          {stats.by_language.map(l => (
            <button key={l.language} onClick={() => { setFilterLanguage(l.language); setTab('list'); setPage(1); }}
              className="flex items-center justify-between p-4 bg-surface border border-border rounded-lg hover:border-violet/50 transition-colors">
              <div className="text-white font-medium">{getLanguageLabel(l.language)}</div>
              <div className="text-xl font-bold text-violet-light">{l.count}</div>
            </button>
          ))}
        </div>
      )}

      {/* Tab: Sources */}
      {tab === 'stats' && (
        <div className="space-y-4">
          <h2 className="text-lg font-semibold text-white">Sources de scraping</h2>
          <div className="grid gap-4">
            {sources.map(s => (
              <div key={s.slug} className="bg-surface border border-border rounded-lg p-4 flex items-center justify-between">
                <div>
                  <div className="text-white font-medium">{s.name}</div>
                  <div className="text-xs text-muted mt-1">
                    {s.total_lawyers} avocats / {s.total_with_email} avec email
                    {s.last_scraped_at && ` / Dernier: ${new Date(s.last_scraped_at).toLocaleDateString()}`}
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  {statusBadge(s.status)}
                  <button onClick={() => handleScrape(s.slug)}
                    disabled={!!scraping || s.status === 'scraping'}
                    className="px-3 py-1.5 bg-violet hover:bg-violet/90 text-white rounded text-xs font-medium disabled:opacity-50">
                    {scraping === s.slug ? 'Lancement...' : 'Scraper'}
                  </button>
                </div>
              </div>
            ))}
          </div>

          {/* Specialty breakdown */}
          {stats && stats.by_specialty.length > 0 && (
            <>
              <h2 className="text-lg font-semibold text-white mt-8">Top specialites</h2>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                {stats.by_specialty.map(s => (
                  <button key={s.specialty} onClick={() => { setFilterSpecialty(s.specialty); setTab('list'); setPage(1); }}
                    className="flex items-center justify-between p-3 bg-surface border border-border rounded-lg hover:border-violet/50 transition-colors">
                    <span className="text-white/80 text-sm">{s.specialty}</span>
                    <span className="text-violet-light font-bold">{s.count}</span>
                  </button>
                ))}
              </div>
            </>
          )}
        </div>
      )}
    </div>
  );
}
