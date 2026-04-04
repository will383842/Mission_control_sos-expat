import { useEffect, useState, useCallback, useRef } from 'react';
import api from '../../api/client';
import { getCountryFlag } from '../../lib/constants';

interface Contact {
  id: number;
  name: string;
  role: string | null;
  email: string | null;
  phone: string | null;
  company: string | null;
  company_url: string | null;
  sector: string | null;
  country: string | null;
  city: string | null;
  source?: { id: number; name: string; slug: string };
}

interface ContactStats {
  total: number;
  with_email: number;
  by_country: Record<string, number>;
}

export default function ContentContacts() {
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [filterSector, setFilterSector] = useState('');
  const [filterCountry, setFilterCountry] = useState('');
  const [filterEmail, setFilterEmail] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [stats, setStats] = useState<ContactStats | null>(null);

  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    api.get('/content-contacts/stats').then(({ data }) => setStats(data)).catch(() => {});
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => { setSearch(searchInput); setPage(1); }, 400);
    return () => clearTimeout(timer);
  }, [searchInput]);

  const fetchContacts = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    setLoading(true);
    try {
      const params: Record<string, string> = { page: String(page), per_page: '50' };
      if (search) params.search = search;
      if (filterSector) params.sector = filterSector;
      if (filterCountry) params.country = filterCountry;
      if (filterEmail) params.has_email = '1';
      const res = await api.get('/content-contacts', { params, signal: controller.signal });
      if (!controller.signal.aborted) {
        setContacts(res.data.data);
        setLastPage(res.data.last_page);
        setTotal(res.data.total);
      }
    } catch (err: unknown) {
      if (err instanceof Error && err.name === 'CanceledError') return;
    } finally {
      if (!controller.signal.aborted) setLoading(false);
    }
  }, [page, search, filterSector, filterCountry, filterEmail]);

  useEffect(() => { fetchContacts(); }, [fetchContacts]);

  const handleExport = async () => {
    setExporting(true);
    try {
      const params: Record<string, string> = {};
      if (filterSector) params.sector = filterSector;
      if (filterCountry) params.country = filterCountry;
      if (search) params.search = search;
      if (filterEmail) params.has_email = '1';
      const res = await api.get('/content-contacts/export', { params, responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement('a');
      a.href = url;
      a.download = `contacts-${new Date().toISOString().slice(0, 10)}.csv`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch { /* */ }
    finally { setExporting(false); }
  };

  const emailPct = stats && stats.total > 0 ? Math.round(stats.with_email / stats.total * 100) : null;

  return (
    <div className="p-4 md:p-6 space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">Contacts</h2>
          <p className="text-muted text-sm mt-0.5">{total.toLocaleString()} contacts identifiés</p>
        </div>
        <button onClick={handleExport} disabled={exporting}
          className="px-4 py-1.5 bg-surface2 border border-border text-muted hover:text-white text-sm rounded-lg disabled:opacity-50">
          {exporting ? 'Export...' : 'Export CSV'}
        </button>
      </div>

      {/* Stats pills */}
      {stats && (
        <div className="flex flex-wrap gap-2">
          <span className="px-3 py-1 bg-surface border border-border rounded-lg text-xs">
            <span className="text-white font-semibold">{stats.total.toLocaleString()}</span>
            <span className="text-muted ml-1">contacts</span>
          </span>
          {emailPct !== null && (
            <span className="px-3 py-1 bg-surface border border-border rounded-lg text-xs">
              <span className="text-green-400 font-semibold">{emailPct}%</span>
              <span className="text-muted ml-1">avec email</span>
            </span>
          )}
          {Object.entries(stats.by_country).slice(0, 5).map(([country, count]) => (
            <button key={country}
              onClick={() => { setFilterCountry(filterCountry === country ? '' : country); setPage(1); }}
              className={`px-3 py-1 border rounded-lg text-xs transition-colors ${filterCountry === country ? 'bg-violet/20 border-violet/50 text-white' : 'bg-surface border-border text-muted hover:text-white'}`}>
              {getCountryFlag(country)} {country} <span className="font-semibold text-white ml-1">{(count as number).toLocaleString()}</span>
            </button>
          ))}
        </div>
      )}

      {/* Filters */}
      <div className="flex flex-wrap gap-3 items-center">
        <input type="text" value={searchInput} onChange={(e) => setSearchInput(e.target.value)}
          placeholder="Rechercher nom, email, entreprise..."
          className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-64" />
        <select value={filterSector} onChange={(e) => { setFilterSector(e.target.value); setPage(1); }}
          className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
          <option value="">Tous secteurs</option>
          {['media', 'assurance', 'education', 'emploi', 'sante', 'fiscalite', 'social'].map((s) => (
            <option key={s} value={s}>{s}</option>
          ))}
        </select>
        {stats && Object.keys(stats.by_country).length > 0 && (
          <select value={filterCountry} onChange={(e) => { setFilterCountry(e.target.value); setPage(1); }}
            className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
            <option value="">Tous pays</option>
            {Object.keys(stats.by_country).map((c) => (
              <option key={c} value={c}>{getCountryFlag(c)} {c}</option>
            ))}
          </select>
        )}
        <button onClick={() => { setFilterEmail(!filterEmail); setPage(1); }}
          className={`px-3 py-2 rounded-lg text-xs font-medium transition-colors ${filterEmail ? 'bg-green-900/40 text-green-300' : 'bg-surface2 text-muted hover:text-white'}`}>
          Avec email
        </button>
      </div>

      {/* Table */}
      <div className="bg-surface border border-border rounded-xl overflow-x-auto">
        {loading ? (
          <div className="flex items-center justify-center h-32" role="status">
            <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-muted">
                <th className="px-4 py-3 font-medium">Nom</th>
                <th className="px-4 py-3 font-medium">Rôle</th>
                <th className="px-4 py-3 font-medium">Email</th>
                <th className="px-4 py-3 font-medium">Téléphone</th>
                <th className="px-4 py-3 font-medium">Entreprise</th>
                <th className="px-4 py-3 font-medium">Secteur</th>
                <th className="px-4 py-3 font-medium">Source</th>
              </tr>
            </thead>
            <tbody>
              {contacts.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-4 py-16 text-center">
                    <div className="text-4xl mb-3">📭</div>
                    <div className="text-white font-medium">Aucun contact trouvé</div>
                    <div className="text-muted text-sm mt-1">Modifiez vos filtres pour voir les contacts</div>
                  </td>
                </tr>
              ) : contacts.map((c) => (
                <tr key={c.id} className="border-b border-border/50 hover:bg-surface2 transition-colors">
                  <td className="px-4 py-2 text-white font-medium">{c.name}</td>
                  <td className="px-4 py-2 text-muted text-xs">{c.role || '-'}</td>
                  <td className="px-4 py-2">
                    {c.email ? <a href={`mailto:${c.email}`} className="text-green-400 text-xs hover:underline">{c.email}</a> : <span className="text-muted/30 text-xs">-</span>}
                  </td>
                  <td className="px-4 py-2">
                    {c.phone ? <a href={`tel:${c.phone}`} className="text-cyan text-xs hover:underline">{c.phone}</a> : <span className="text-muted/30 text-xs">-</span>}
                  </td>
                  <td className="px-4 py-2 text-xs">
                    {c.company_url ? (
                      <a href={c.company_url} target="_blank" rel="noopener noreferrer" className="text-violet-light hover:underline">{c.company}</a>
                    ) : <span className="text-muted">{c.company || '-'}</span>}
                  </td>
                  <td className="px-4 py-2">
                    {c.sector && <span className="px-2 py-0.5 bg-surface2 rounded text-xs text-muted">{c.sector}</span>}
                  </td>
                  <td className="px-4 py-2 text-muted text-xs">{c.source?.name || '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Pagination */}
      {lastPage > 1 && (
        <div className="flex items-center justify-center gap-2">
          <button onClick={() => setPage(Math.max(1, page - 1))} disabled={page === 1}
            className="px-3 py-1 bg-surface border border-border rounded text-sm text-white disabled:opacity-30">← Préc.</button>
          <span className="text-sm text-muted">Page {page} / {lastPage}</span>
          <button onClick={() => setPage(Math.min(lastPage, page + 1))} disabled={page === lastPage}
            className="px-3 py-1 bg-surface border border-border rounded text-sm text-white disabled:opacity-30">Suiv. →</button>
        </div>
      )}
    </div>
  );
}
