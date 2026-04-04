import { useEffect, useState, useCallback, useRef } from 'react';
import api from '../../api/client';

interface Business {
  id: number;
  name: string;
  contact_name: string | null;
  contact_email: string | null;
  contact_phone: string | null;
  website: string | null;
  address: string | null;
  country: string | null;
  city: string | null;
  continent: string | null;
  category: string | null;
  subcategory: string | null;
  is_premium: boolean;
  recommendations: number;
  description: string | null;
  language: string;
  source?: { id: number; name: string; slug: string };
}

interface Stats {
  total: number;
  with_email: number;
  with_phone: number;
  with_website: number;
  premium: number;
  by_country: { country: string; country_slug: string; count: number }[];
  by_category: { category: string; category_slug: string; count: number }[];
}

interface CountryData {
  country: string;
  country_slug: string;
  continent: string;
  count: number;
  with_email: number;
}

export default function BusinessDirectory() {
  const [businesses, setBusinesses] = useState<Business[]>([]);
  const [stats, setStats] = useState<Stats | null>(null);
  const [countries, setCountries] = useState<CountryData[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [scraping, setScraping] = useState(false);

  // Filters
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [filterCountry, setFilterCountry] = useState('');
  const [filterCategory, setFilterCategory] = useState('');
  const [filterEmail, setFilterEmail] = useState('');
  const [tab, setTab] = useState<'list' | 'countries' | 'stats'>('list');
  const [exporting, setExporting] = useState(false);

  const abortRef = useRef<AbortController | null>(null);

  // Debounce search
  useEffect(() => {
    const timer = setTimeout(() => { setSearch(searchInput); setPage(1); }, 400);
    return () => clearTimeout(timer);
  }, [searchInput]);

  const fetchBusinesses = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    setLoading(true);
    try {
      const params: Record<string, string> = { page: String(page), per_page: '50', source: 'expat-com' };
      if (search) params.search = search;
      if (filterCountry) params.country = filterCountry;
      if (filterCategory) params.category = filterCategory;
      if (filterEmail) params.has_email = filterEmail;
      const res = await api.get('/businesses', { params, signal: controller.signal });
      if (!controller.signal.aborted) {
        setBusinesses(res.data.data);
        setLastPage(res.data.last_page);
        setTotal(res.data.total);
      }
    } catch (err: unknown) {
      if (err instanceof Error && err.name === 'CanceledError') return;
      setError('Erreur de chargement');
    } finally {
      if (!controller.signal.aborted) setLoading(false);
    }
  }, [page, search, filterCountry, filterCategory, filterEmail]);

  useEffect(() => { fetchBusinesses(); }, [fetchBusinesses]);

  useEffect(() => {
    Promise.all([
      api.get('/businesses/stats', { params: { source: 'expat-com' } }),
      api.get('/businesses/countries'),
    ]).then(([statsRes, countriesRes]) => {
      setStats(statsRes.data);
      setCountries(countriesRes.data);
    }).catch(() => {});
  }, []);

  const handleScrape = async () => {
    setScraping(true);
    try {
      await api.post('/businesses/scrape/expat-com');
    } catch {
      setError('Erreur lors du lancement');
    } finally {
      setScraping(false);
    }
  };

  const handleExport = async () => {
    setExporting(true);
    try {
      const params: Record<string, string> = {};
      if (filterCountry) params.country = filterCountry;
      if (filterCategory) params.category = filterCategory;
      if (filterEmail) params.has_email = '1';
      if (search) params.search = search;
      const res = await api.get('/businesses/export', { params, responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement('a');
      a.href = url;
      a.download = `businesses-${new Date().toISOString().slice(0, 10)}.csv`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch { setError('Erreur export'); }
    finally { setExporting(false); }
  };

  // Group countries by continent for the countries tab
  const groupedCountries = countries.reduce((acc, c) => {
    const key = c.continent || 'Autre';
    if (!acc[key]) acc[key] = [];
    acc[key].push(c);
    return acc;
  }, {} as Record<string, CountryData[]>);

  return (
    <div className="p-4 md:p-6 space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">Annuaire Entreprises</h2>
          <p className="text-muted text-sm mt-0.5">
            {stats ? `${stats.total.toLocaleString()} entreprises dont ${stats.with_email.toLocaleString()} avec email` : 'Chargement...'}
          </p>
        </div>
        <div className="flex gap-2">
          <button onClick={handleExport} disabled={exporting}
            className="px-4 py-1.5 bg-surface2 border border-border text-muted hover:text-white text-sm rounded-lg disabled:opacity-50">
            {exporting ? 'Export...' : 'Export CSV'}
          </button>
          <button onClick={handleScrape} disabled={scraping}
            className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg font-medium disabled:opacity-50">
            {scraping ? 'Lancement...' : 'Scraper l\'annuaire'}
          </button>
        </div>
      </div>

      {error && (
        <div className="bg-red-900/20 border border-red-500/30 text-red-400 p-3 rounded-xl text-sm flex justify-between">
          <span>{error}</span>
          <button onClick={() => setError(null)} className="text-red-300 hover:text-white ml-3">x</button>
        </div>
      )}

      {/* KPIs */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
          {[
            { label: 'Total', value: stats.total.toLocaleString() },
            { label: 'Avec email', value: stats.with_email.toLocaleString(), color: 'text-green-400' },
            { label: 'Avec telephone', value: stats.with_phone.toLocaleString() },
            { label: 'Avec site web', value: stats.with_website.toLocaleString() },
            { label: 'Premium', value: stats.premium.toLocaleString(), color: 'text-amber' },
          ].map((k) => (
            <div key={k.label} className="bg-surface border border-border rounded-xl p-3 text-center">
              <div className={`text-lg font-bold ${k.color || 'text-white'}`}>{k.value}</div>
              <div className="text-xs text-muted">{k.label}</div>
            </div>
          ))}
        </div>
      )}

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        {(['list', 'countries', 'stats'] as const).map((t) => (
          <button key={t} onClick={() => setTab(t)}
            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
              tab === t ? 'border-violet text-white' : 'border-transparent text-muted hover:text-white'
            }`}>
            {t === 'list' ? 'Entreprises' : t === 'countries' ? 'Par pays' : 'Statistiques'}
          </button>
        ))}
      </div>

      {/* TAB: List */}
      {tab === 'list' && (
        <>
          <div className="flex flex-wrap gap-3 items-center">
            <input type="text" value={searchInput} onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Rechercher nom, email, description..."
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-64" />
            <select value={filterCountry} onChange={(e) => { setFilterCountry(e.target.value); setPage(1); }}
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="">Tous pays</option>
              {stats?.by_country.map((c) => (
                <option key={c.country_slug} value={c.country_slug}>{c.country} ({c.count})</option>
              ))}
            </select>
            <select value={filterCategory} onChange={(e) => { setFilterCategory(e.target.value); setPage(1); }}
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="">Toutes categories</option>
              {stats?.by_category.map((c) => (
                <option key={c.category_slug} value={c.category_slug}>{c.category} ({c.count})</option>
              ))}
            </select>
            <select value={filterEmail} onChange={(e) => { setFilterEmail(e.target.value); setPage(1); }}
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="">Tous</option>
              <option value="1">Avec email</option>
            </select>
          </div>

          <div className="bg-surface border border-border rounded-xl overflow-x-auto">
            {loading ? (
              <div className="flex items-center justify-center h-32" role="status">
                <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
              </div>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left text-muted">
                    <th className="px-4 py-3 font-medium">Entreprise</th>
                    <th className="px-4 py-3 font-medium">Contact</th>
                    <th className="px-4 py-3 font-medium">Email</th>
                    <th className="px-4 py-3 font-medium">Telephone</th>
                    <th className="px-4 py-3 font-medium">Pays / Ville</th>
                    <th className="px-4 py-3 font-medium">Categorie</th>
                    <th className="px-4 py-3 font-medium">Langue</th>
                    <th className="px-4 py-3 font-medium">Source</th>
                    <th className="px-4 py-3 font-medium text-center">Reco.</th>
                  </tr>
                </thead>
                <tbody>
                  {businesses.length === 0 ? (
                    <tr><td colSpan={9} className="px-4 py-8 text-center text-muted">Aucune entreprise trouvee</td></tr>
                  ) : businesses.map((b) => (
                    <tr key={b.id} className="border-b border-border/50 hover:bg-surface2 transition-colors">
                      <td className="px-4 py-2">
                        <div className="flex items-center gap-2">
                          {b.is_premium && <span className="text-amber text-[10px]">PRO</span>}
                          <div>
                            <div className="text-white font-medium">{b.name}</div>
                            {b.website && (
                              <a href={b.website} target="_blank" rel="noopener noreferrer" className="text-xs text-cyan hover:underline truncate block max-w-[200px]">
                                {b.website.replace(/^https?:\/\//, '').replace(/\/$/, '')}
                              </a>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-2 text-gray-400 text-xs">{b.contact_name || '-'}</td>
                      <td className="px-4 py-2">
                        {b.contact_email ? (
                          <a href={`mailto:${b.contact_email}`} className="text-green-400 text-xs hover:underline">{b.contact_email}</a>
                        ) : <span className="text-muted/30 text-xs">-</span>}
                      </td>
                      <td className="px-4 py-2">
                        {b.contact_phone ? (
                          <a href={`tel:${b.contact_phone}`} className="text-cyan text-xs hover:underline">{b.contact_phone}</a>
                        ) : <span className="text-muted/30 text-xs">-</span>}
                      </td>
                      <td className="px-4 py-2 text-gray-400 text-xs">
                        {b.country}{b.city ? `, ${b.city}` : ''}
                      </td>
                      <td className="px-4 py-2">
                        <span className="text-xs text-gray-400">{b.category}</span>
                        {b.subcategory && <span className="text-xs text-muted block">{b.subcategory}</span>}
                      </td>
                      <td className="px-4 py-2 text-muted text-xs uppercase">{b.language || 'fr'}</td>
                      <td className="px-4 py-2 text-muted text-xs">{b.source?.name || '-'}</td>
                      <td className="px-4 py-2 text-center text-white text-xs">{b.recommendations || '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          {lastPage > 1 && (
            <div className="flex items-center justify-center gap-2">
              <button onClick={() => setPage(Math.max(1, page - 1))} disabled={page === 1}
                className="px-3 py-1 bg-surface border border-border rounded text-sm text-white disabled:opacity-30">Prec.</button>
              <span className="text-sm text-muted">Page {page} / {lastPage} ({total} resultats)</span>
              <button onClick={() => setPage(Math.min(lastPage, page + 1))} disabled={page === lastPage}
                className="px-3 py-1 bg-surface border border-border rounded text-sm text-white disabled:opacity-30">Suiv.</button>
            </div>
          )}
        </>
      )}

      {/* TAB: Countries */}
      {tab === 'countries' && (
        <div className="space-y-6">
          {Object.entries(groupedCountries).sort(([a], [b]) => a.localeCompare(b)).map(([continent, ctries]) => (
            <div key={continent}>
              <h3 className="text-white font-title font-bold mb-2">{continent}</h3>
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                {ctries.sort((a, b) => b.count - a.count).map((c) => (
                  <button key={c.country_slug} onClick={() => { setFilterCountry(c.country_slug); setTab('list'); setPage(1); }}
                    className="bg-surface border border-border rounded-lg p-3 text-left hover:bg-surface2 hover:border-violet/30 transition-all">
                    <div className="text-white text-sm font-medium">{c.country}</div>
                    <div className="text-xs text-muted">{c.count} entreprises</div>
                    {c.with_email > 0 && <div className="text-xs text-green-400">{c.with_email} avec email</div>}
                  </button>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* TAB: Stats */}
      {tab === 'stats' && stats && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="bg-surface border border-border rounded-xl p-4">
            <h3 className="text-white font-medium mb-3">Top pays</h3>
            <div className="space-y-2">
              {stats.by_country.slice(0, 15).map((c) => (
                <div key={c.country_slug} className="flex items-center justify-between text-sm">
                  <span className="text-gray-300">{c.country}</span>
                  <span className="text-muted">{c.count}</span>
                </div>
              ))}
            </div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4">
            <h3 className="text-white font-medium mb-3">Par categorie</h3>
            <div className="space-y-2">
              {stats.by_category.map((c) => (
                <div key={c.category_slug} className="flex items-center justify-between text-sm">
                  <span className="text-gray-300">{c.category}</span>
                  <span className="text-muted">{c.count}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
