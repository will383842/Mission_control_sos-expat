import { useEffect, useState, useMemo, useCallback } from 'react';
import api from '../../api/client';

// ── Types ────────────────────────────────────────────────────────────────────

interface CountrySummary {
  country_code: string; country_name: string; country_slug: string; continent: string;
  total_links: number; official_links: number; with_address: number; with_phone: number;
  categories_count: number; emergency_number: string | null;
}

interface NationalitySummary {
  nationality_code: string; nationality_name: string; embassy_count: number;
}

interface DirectoryStats {
  total_entries: number; countries: number; nationalities: number;
  ambassades_total: number; with_address: number; with_phone: number;
  with_email: number; with_gps: number; official: number;
  by_continent: { continent: string; countries: number; links: number }[];
  by_category: { category: string; count: number }[];
  top_nationalities: { nationality_code: string; nationality_name: string; count: number }[];
}

interface DirectoryEntry {
  id: number;
  country_code: string; country_name: string; continent: string;
  nationality_code: string | null; nationality_name: string | null;
  category: string; sub_category: string | null;
  title: string; url: string; domain: string;
  description: string | null;
  translations: Record<string, { title?: string; description?: string }> | null;
  address: string | null; city: string | null;
  phone: string | null; phone_emergency: string | null; email: string | null;
  opening_hours: string | null;
  latitude: number | null; longitude: number | null;
  trust_score: number; is_official: boolean; is_active: boolean;
  emergency_number: string | null;
  anchor_text: string | null; rel_attribute: string;
}

type Tab = 'countries' | 'embassies' | 'imports' | 'stats';

// ── Constantes ────────────────────────────────────────────────────────────────

const CATEGORY_LABELS: Record<string, string> = {
  ambassade: 'Ambassade', immigration: 'Immigration', sante: 'Santé', logement: 'Logement',
  emploi: 'Emploi', telecom: 'Telecom', transport: 'Transport', fiscalite: 'Fiscalité',
  banque: 'Banque', education: 'Éducation', urgences: 'Urgences', communaute: 'Communauté',
  juridique: 'Juridique',
};

const CONTINENT_LABELS: Record<string, string> = {
  europe: 'Europe', 'amerique-nord': 'Amér. Nord', 'amerique-sud': 'Amér. Sud',
  afrique: 'Afrique', asie: 'Asie', oceanie: 'Océanie', global: 'Global', autre: 'Autre',
};

const ALL_CATEGORIES = Object.keys(CATEGORY_LABELS);

// Les 9 langues du projet SOS Expat (codes identiques aux fichiers i18n)
const LANGUAGES = [
  { code: 'fr', label: 'Français' },
  { code: 'en', label: 'English' },
  { code: 'es', label: 'Español' },
  { code: 'ar', label: 'العربية' },
  { code: 'de', label: 'Deutsch' },
  { code: 'pt', label: 'Português' },
  { code: 'ch', label: '中文' },       // chinois — "ch" dans le projet (Wikidata: zh)
  { code: 'hi', label: 'हिन्दी' },
  { code: 'ru', label: 'Русский' },
];

const EMPTY_FORM: Partial<DirectoryEntry> = {
  country_code: '', country_name: '', country_slug: '', continent: 'europe',
  nationality_code: null, nationality_name: null,
  category: 'ambassade', sub_category: null,
  title: '', url: '', domain: '', description: null, language: 'fr',
  address: null, city: null, phone: null, email: null, opening_hours: null,
  trust_score: 90, is_official: true, is_active: true,
  anchor_text: null, rel_attribute: 'noopener',
};

function fmt(n: number) { return n.toLocaleString('fr-FR'); }

// ── Composant principal ───────────────────────────────────────────────────────

export default function CountryDirectoryPage() {
  const [tab, setTab] = useState<Tab>('countries');
  const [countries, setCountries] = useState<CountrySummary[]>([]);
  const [nationalities, setNationalities] = useState<NationalitySummary[]>([]);
  const [stats, setStats] = useState<DirectoryStats | null>(null);
  const [loading, setLoading] = useState(true);

  // Filtres onglet Pays
  const [search, setSearch] = useState('');
  const [filterContinent, setFilterContinent] = useState('');

  // Onglet Ambassades
  const [filterNationality, setFilterNationality] = useState('');
  const [filterHostCountry, setFilterHostCountry] = useState('');
  const [displayLang, setDisplayLang] = useState('fr');
  const [embassies, setEmbassies] = useState<DirectoryEntry[]>([]);
  const [embassiesLoading, setEmbassiesLoading] = useState(false);

  // Détail pays
  const [selectedCountry, setSelectedCountry] = useState<string | null>(null);
  const [countryEntries, setCountryEntries] = useState<Record<string, DirectoryEntry[]>>({});
  const [countryLoading, setCountryLoading] = useState(false);

  // Modal CRUD
  const [showModal, setShowModal] = useState(false);
  const [editEntry, setEditEntry] = useState<Partial<DirectoryEntry> | null>(null);
  const [saving, setSaving] = useState(false);
  const [modalError, setModalError] = useState('');

  // Import Wikidata
  const [importNat, setImportNat] = useState('');
  const [importing, setImporting] = useState(false);
  const [importMsg, setImportMsg] = useState('');

  // ── Chargement initial ──────────────────────────────────────────────────────

  useEffect(() => {
    Promise.all([
      api.get('/country-directory/countries'),
      api.get('/country-directory/stats'),
      api.get('/country-directory/nationalities'),
    ]).then(([cRes, sRes, nRes]) => {
      setCountries(cRes.data);
      setStats(sRes.data);
      setNationalities(nRes.data);
    }).finally(() => setLoading(false));
  }, []);

  const reload = useCallback(() => {
    Promise.all([
      api.get('/country-directory/countries'),
      api.get('/country-directory/stats'),
      api.get('/country-directory/nationalities'),
    ]).then(([cRes, sRes, nRes]) => {
      setCountries(cRes.data);
      setStats(sRes.data);
      setNationalities(nRes.data);
    });
  }, []);

  // ── Onglet Pays ─────────────────────────────────────────────────────────────

  const loadCountry = async (code: string) => {
    setSelectedCountry(code);
    setCountryLoading(true);
    try {
      const res = await api.get(`/country-directory/country/${code}`);
      setCountryEntries(res.data.entries || {});
    } finally {
      setCountryLoading(false);
    }
  };

  const filtered = useMemo(() => {
    let list = countries;
    if (search) {
      const q = search.toLowerCase();
      list = list.filter(c => c.country_name.toLowerCase().includes(q) || c.country_code.toLowerCase().includes(q));
    }
    if (filterContinent) list = list.filter(c => c.continent === filterContinent);
    return list;
  }, [countries, search, filterContinent]);

  const continents = useMemo(() => [...new Set(countries.map(c => c.continent))].sort(), [countries]);

  // ── Onglet Ambassades ───────────────────────────────────────────────────────

  const loadEmbassies = useCallback(async () => {
    setEmbassiesLoading(true);
    try {
      const params: Record<string, string> = { lang: displayLang };
      if (filterNationality) params.nationality = filterNationality;
      if (filterHostCountry) params.host_country = filterHostCountry;
      const res = await api.get('/country-directory/embassies', { params });
      setEmbassies(res.data);
    } finally {
      setEmbassiesLoading(false);
    }
  }, [filterNationality, filterHostCountry, displayLang]);

  useEffect(() => {
    if (tab === 'embassies') loadEmbassies();
  }, [tab, loadEmbassies]);

  // ── CRUD ────────────────────────────────────────────────────────────────────

  const openAdd = () => { setEditEntry({ ...EMPTY_FORM }); setModalError(''); setShowModal(true); };
  const openEdit = (e: DirectoryEntry) => { setEditEntry({ ...e }); setModalError(''); setShowModal(true); };

  const saveEntry = async () => {
    if (!editEntry) return;
    setSaving(true); setModalError('');
    try {
      if (editEntry.id) {
        await api.put(`/country-directory/${editEntry.id}`, editEntry);
      } else {
        await api.post('/country-directory', editEntry);
      }
      setShowModal(false);
      reload();
      if (selectedCountry) loadCountry(selectedCountry);
      if (tab === 'embassies') loadEmbassies();
    } catch (err: any) {
      setModalError(err?.response?.data?.message || 'Erreur lors de la sauvegarde');
    } finally {
      setSaving(false);
    }
  };

  const deleteEntry = async (id: number) => {
    if (!confirm('Désactiver cette entrée ?')) return;
    await api.delete(`/country-directory/${id}?soft=true`);
    reload();
    if (selectedCountry) loadCountry(selectedCountry);
    if (tab === 'embassies') loadEmbassies();
  };

  // ── Import Wikidata ─────────────────────────────────────────────────────────

  const runImport = async () => {
    if (!importNat.trim()) return;
    setImporting(true); setImportMsg('');
    try {
      // Déclenche la commande artisan via un endpoint dédié (à créer si besoin)
      // Pour l'instant on affiche la commande à lancer
      setImportMsg(`Lancez dans le terminal :\nphp artisan annuaire:import-wikidata --nationality=${importNat.toUpperCase()}\n\nOu pour tout importer :\nphp artisan annuaire:import-wikidata --nationality=all --skip-existing`);
    } finally {
      setImporting(false);
    }
  };

  // ── Render ──────────────────────────────────────────────────────────────────

  if (loading) return <div className="p-8 text-gray-400 animate-pulse">Chargement annuaire...</div>;

  return (
    <div className="space-y-5">
      {/* En-tête */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">Annuaire Mondial</h1>
          <p className="text-sm text-gray-400 mt-0.5">
            {fmt(stats?.total_entries ?? 0)} entrées · {fmt(stats?.countries ?? 0)} pays · {fmt(stats?.nationalities ?? 0)} nationalités · {fmt(stats?.ambassades_total ?? 0)} ambassades
          </p>
        </div>
        <button onClick={openAdd}
          className="px-3 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg font-medium">
          + Ajouter
        </button>
      </div>

      {/* Onglets */}
      <div className="flex gap-1 border-b border-gray-700">
        {([
        ['countries', 'Par pays hôte'],
        ['embassies', 'Par nationalité'],
        ['imports', 'Imports'],
        ['stats', 'Statistiques'],
      ] as [Tab, string][]).map(([t, label]) => (
          <button key={t} onClick={() => setTab(t)}
            className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${tab === t ? 'border-blue-500 text-blue-400' : 'border-transparent text-gray-400 hover:text-white'}`}>
            {label}
          </button>
        ))}
      </div>

      {/* ── Onglet : Par pays hôte ─────────────────────────────────────────── */}
      {tab === 'countries' && (
        <div className="space-y-4">
          {/* Filtres */}
          <div className="flex gap-3">
            <input type="search" value={search} onChange={e => setSearch(e.target.value)}
              placeholder="Rechercher un pays..."
              className="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder:text-gray-500 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
            <select value={filterContinent} onChange={e => setFilterContinent(e.target.value)}
              className="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
              <option value="">Tous continents</option>
              {continents.map(c => (
                <option key={c} value={c}>{CONTINENT_LABELS[c] || c} ({countries.filter(cc => cc.continent === c).length})</option>
              ))}
            </select>
          </div>

          {/* Table */}
          <div className="bg-gray-800 rounded-lg overflow-hidden">
            <div className="overflow-x-auto max-h-[580px] overflow-y-auto">
              <table className="w-full text-sm">
                <thead className="sticky top-0 bg-gray-900 z-10">
                  <tr className="text-gray-400 border-b border-gray-700 text-xs">
                    <th className="text-left py-2 px-3">Pays</th>
                    <th className="text-center py-2 px-1">Code</th>
                    <th className="text-center py-2 px-1">Continent</th>
                    <th className="text-right py-2 px-1">Liens</th>
                    <th className="text-right py-2 px-1">Off.</th>
                    <th className="text-right py-2 px-1">Adr.</th>
                    <th className="text-right py-2 px-1">Tel.</th>
                    <th className="text-center py-2 px-1">SOS</th>
                    <th className="text-left py-2 px-2">Couverture</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map(c => {
                    const pct = Math.round((c.categories_count / ALL_CATEGORIES.length) * 100);
                    return (
                      <tr key={c.country_code}
                        onClick={() => loadCountry(c.country_code)}
                        className={`border-b border-gray-700/30 hover:bg-gray-700/20 cursor-pointer ${selectedCountry === c.country_code ? 'bg-blue-500/10' : ''}`}>
                        <td className="py-1.5 px-3 font-medium text-white">{c.country_name}</td>
                        <td className="py-1.5 px-1 text-center text-gray-400 text-xs">{c.country_code}</td>
                        <td className="py-1.5 px-1 text-center">
                          <span className="px-1.5 py-0.5 bg-gray-700 rounded text-xs text-gray-300">{CONTINENT_LABELS[c.continent] || c.continent}</span>
                        </td>
                        <td className="py-1.5 px-1 text-right text-white tabular-nums">{c.total_links}</td>
                        <td className="py-1.5 px-1 text-right text-emerald-400 tabular-nums">{c.official_links}</td>
                        <td className="py-1.5 px-1 text-right text-gray-400 tabular-nums">{c.with_address}</td>
                        <td className="py-1.5 px-1 text-right text-gray-400 tabular-nums">{c.with_phone}</td>
                        <td className="py-1.5 px-1 text-center">
                          {c.emergency_number
                            ? <span className="text-red-400 text-xs font-bold">{c.emergency_number}</span>
                            : <span className="text-gray-600">—</span>}
                        </td>
                        <td className="py-1.5 px-2 w-36">
                          <div className="flex items-center gap-2">
                            <div className="flex-1 h-1.5 bg-gray-700 rounded-full overflow-hidden">
                              <div className={`h-full rounded-full ${pct >= 50 ? 'bg-emerald-500' : pct >= 20 ? 'bg-amber-500' : 'bg-red-500'}`} style={{ width: `${pct}%` }} />
                            </div>
                            <span className={`text-xs w-8 ${pct >= 50 ? 'text-emerald-400' : pct >= 20 ? 'text-amber-400' : 'text-red-400'}`}>{c.categories_count}/{ALL_CATEGORIES.length}</span>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
            <div className="px-3 py-2 text-xs text-gray-500 border-t border-gray-700">
              {filtered.length} pays sur {countries.length}
            </div>
          </div>

          {/* Détail pays */}
          {selectedCountry && (
            <CountryDetail
              code={selectedCountry}
              name={countries.find(c => c.country_code === selectedCountry)?.country_name ?? ''}
              entries={countryEntries}
              loading={countryLoading}
              onClose={() => setSelectedCountry(null)}
              onEdit={openEdit}
              onDelete={deleteEntry}
            />
          )}
        </div>
      )}

      {/* ── Onglet : Ambassades par nationalité ───────────────────────────── */}
      {tab === 'embassies' && (
        <div className="space-y-4">
          {/* Filtres */}
          <div className="flex flex-wrap gap-3 items-end">
            <div>
              <label className="block text-xs text-gray-400 mb-1">Nationalité</label>
              <select value={filterNationality} onChange={e => setFilterNationality(e.target.value)}
                className="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white min-w-[200px]">
                <option value="">Toutes nationalités ({nationalities.length})</option>
                {nationalities.map(n => (
                  <option key={n.nationality_code} value={n.nationality_code}>
                    {n.nationality_name} [{n.nationality_code}] — {n.embassy_count} ambassades
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs text-gray-400 mb-1">Pays hôte</label>
              <input type="text" value={filterHostCountry} onChange={e => setFilterHostCountry(e.target.value.toUpperCase())}
                placeholder="Ex: TH, DE, US..." maxLength={2}
                className="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white w-28 uppercase" />
            </div>
            <div>
              <label className="block text-xs text-gray-400 mb-1">Langue d'affichage</label>
              <select value={displayLang} onChange={e => setDisplayLang(e.target.value)}
                className="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                {LANGUAGES.map(l => <option key={l.code} value={l.code}>{l.label}</option>)}
              </select>
            </div>
            <button onClick={loadEmbassies} disabled={embassiesLoading}
              className="px-4 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm rounded-lg">
              {embassiesLoading ? 'Chargement...' : 'Filtrer'}
            </button>
          </div>

          {/* Import Wikidata */}
          <div className="bg-gray-800/60 border border-gray-700 rounded-lg p-4">
            <h3 className="text-sm font-bold text-white mb-2">Import Wikidata</h3>
            <p className="text-xs text-gray-400 mb-3">
              Importe automatiquement toutes les ambassades d'une nationalité depuis Wikidata (FR/EN/ES/AR/DE/PT).
              Couverture : 195 pays, ~5 000-38 000 entrées possibles.
            </p>
            <div className="flex gap-2 flex-wrap items-center">
              <input type="text" value={importNat} onChange={e => setImportNat(e.target.value)}
                placeholder="ISO code (ex: DE) ou 'all'"
                className="bg-gray-900 border border-gray-600 rounded px-3 py-1.5 text-sm text-white w-40" />
              <button onClick={runImport} disabled={importing || !importNat}
                className="px-3 py-1.5 bg-violet-600 hover:bg-violet-500 disabled:opacity-40 text-white text-sm rounded">
                Voir la commande
              </button>
              <span className="text-xs text-gray-500">Nécessite accès terminal au serveur Laravel</span>
            </div>
            {importMsg && (
              <pre className="mt-3 bg-gray-900 rounded p-3 text-xs text-emerald-300 whitespace-pre-wrap">{importMsg}</pre>
            )}
          </div>

          {/* Nationalités disponibles */}
          {!filterNationality && nationalities.length > 0 && (
            <div className="bg-gray-800 rounded-lg p-4">
              <h3 className="text-sm font-bold text-white mb-3">
                Nationalités importées ({nationalities.length})
              </h3>
              <div className="flex flex-wrap gap-2">
                {nationalities.map(n => (
                  <button key={n.nationality_code}
                    onClick={() => { setFilterNationality(n.nationality_code); loadEmbassies(); }}
                    className="px-2.5 py-1 bg-gray-700 hover:bg-blue-600/30 hover:border-blue-500 border border-gray-600 rounded text-xs text-white transition-colors">
                    {n.nationality_name} <span className="text-gray-400">({n.embassy_count})</span>
                  </button>
                ))}
              </div>
              {nationalities.length === 0 && (
                <p className="text-sm text-gray-400">
                  Aucune ambassade importée. Lancez l'import Wikidata ci-dessus.
                </p>
              )}
            </div>
          )}

          {/* Liste des ambassades */}
          {embassiesLoading ? (
            <div className="text-gray-400 animate-pulse py-4">Chargement des ambassades...</div>
          ) : embassies.length > 0 ? (
            <div className="bg-gray-800 rounded-lg overflow-hidden">
              <div className="px-3 py-2 border-b border-gray-700 text-xs text-gray-400">
                {embassies.length} ambassades {filterNationality ? `— nationalité ${filterNationality}` : ''} {filterHostCountry ? `dans ${filterHostCountry}` : ''}
              </div>
              <div className="overflow-x-auto max-h-[560px] overflow-y-auto">
                <table className="w-full text-sm">
                  <thead className="sticky top-0 bg-gray-900 z-10">
                    <tr className="text-gray-400 border-b border-gray-700 text-xs">
                      <th className="text-left py-2 px-3">Nationalité</th>
                      <th className="text-left py-2 px-3">Pays hôte</th>
                      <th className="text-left py-2 px-2">Titre</th>
                      <th className="text-left py-2 px-2">Contact</th>
                      <th className="text-center py-2 px-1">Lang.</th>
                      <th className="text-center py-2 px-1">Score</th>
                      <th className="text-center py-2 px-1">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {embassies.map(e => {
                      const displayTitle = displayLang !== 'fr' && e.translations?.[displayLang]?.title
                        ? e.translations[displayLang].title!
                        : e.title;
                      return (
                        <tr key={e.id} className="border-b border-gray-700/30 hover:bg-gray-700/10">
                          <td className="py-1.5 px-3">
                            <span className="px-1.5 py-0.5 bg-blue-500/20 text-blue-300 rounded text-xs font-mono">
                              {e.nationality_code}
                            </span>
                            <span className="ml-1 text-xs text-gray-400">{e.nationality_name}</span>
                          </td>
                          <td className="py-1.5 px-3">
                            <span className="text-white text-xs">{e.country_name}</span>
                            <span className="ml-1 text-gray-500 text-xs">({e.country_code})</span>
                          </td>
                          <td className="py-1.5 px-2 max-w-xs">
                            <a href={e.url} target="_blank" rel="noopener"
                              className="text-white hover:text-blue-400 text-xs font-medium line-clamp-1">
                              {displayTitle}
                            </a>
                            {e.address && <p className="text-gray-500 text-[10px] truncate">{e.address}</p>}
                          </td>
                          <td className="py-1.5 px-2 text-xs text-gray-400">
                            {e.phone && <div>📞 {e.phone}</div>}
                            {e.email && <div>✉ {e.email}</div>}
                          </td>
                          <td className="py-1.5 px-1 text-center">
                            <TranslationBadge entry={e} />
                          </td>
                          <td className="py-1.5 px-1 text-right tabular-nums">
                            <span className={`text-xs ${e.trust_score >= 85 ? 'text-emerald-400' : 'text-amber-400'}`}>
                              {e.trust_score}
                            </span>
                          </td>
                          <td className="py-1.5 px-2 text-center">
                            <div className="flex gap-1 justify-center">
                              <button onClick={() => openEdit(e)} className="text-gray-400 hover:text-white text-xs px-1.5 py-0.5 rounded hover:bg-gray-700">
                                Edit
                              </button>
                              <button onClick={() => deleteEntry(e.id)} className="text-gray-600 hover:text-red-400 text-xs px-1.5 py-0.5 rounded hover:bg-gray-700">
                                ×
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          ) : filterNationality ? (
            <div className="text-gray-400 text-sm py-4">Aucune ambassade pour cette sélection.</div>
          ) : null}
        </div>
      )}

      {/* ── Onglet : Imports ──────────────────────────────────────────────── */}
      {tab === 'imports' && <ImportsTab />}

      {/* ── Onglet : Statistiques ──────────────────────────────────────────── */}
      {tab === 'stats' && stats && (
        <div className="space-y-5">
          {/* Cards stats globales */}
          <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
            <StatCard label="Pays" value={fmt(stats.countries)} />
            <StatCard label="Nationalités" value={fmt(stats.nationalities)} color="text-violet-400" />
            <StatCard label="Ambassades" value={fmt(stats.ambassades_total)} color="text-blue-400" />
            <StatCard label="Liens total" value={fmt(stats.total_entries)} />
            <StatCard label="Officiels" value={fmt(stats.official)} color="text-emerald-400" />
            <StatCard label="Avec adresse" value={fmt(stats.with_address)} />
            <StatCard label="Avec GPS" value={fmt(stats.with_gps)} />
            <StatCard label="Avec email" value={fmt(stats.with_email)} />
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
            {/* Par catégorie */}
            <div className="bg-gray-800 rounded-lg p-4">
              <h3 className="text-sm font-bold text-white mb-3">Par catégorie</h3>
              <div className="space-y-1.5">
                {stats.by_category.map(cat => {
                  const pct = Math.round((cat.count / stats.countries) * 100);
                  return (
                    <div key={cat.category} className="flex items-center gap-2 text-xs">
                      <span className="w-24 text-gray-400 truncate">{CATEGORY_LABELS[cat.category] || cat.category}</span>
                      <div className="flex-1 h-2.5 bg-gray-700 rounded-full overflow-hidden">
                        <div className={`h-full rounded-full ${pct >= 80 ? 'bg-emerald-500' : pct >= 40 ? 'bg-blue-500' : pct >= 15 ? 'bg-amber-500' : 'bg-red-500'}`}
                          style={{ width: `${Math.min(pct, 100)}%` }} />
                      </div>
                      <span className="w-16 text-right text-gray-400 tabular-nums">{cat.count}/{stats.countries}</span>
                      <span className={`w-10 text-right font-bold tabular-nums ${pct >= 80 ? 'text-emerald-400' : pct >= 40 ? 'text-blue-400' : pct >= 15 ? 'text-amber-400' : 'text-red-400'}`}>{pct}%</span>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* Top nationalités */}
            <div className="bg-gray-800 rounded-lg p-4">
              <h3 className="text-sm font-bold text-white mb-3">Top nationalités importées</h3>
              {stats.top_nationalities.length === 0 ? (
                <p className="text-sm text-gray-400">Aucune ambassade importée depuis Wikidata.</p>
              ) : (
                <div className="space-y-1.5">
                  {stats.top_nationalities.map(n => (
                    <div key={n.nationality_code} className="flex items-center gap-2 text-xs">
                      <span className="w-6 text-gray-500 font-mono">{n.nationality_code}</span>
                      <span className="flex-1 text-gray-300 truncate">{n.nationality_name}</span>
                      <span className="text-blue-400 tabular-nums font-bold">{n.count}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Par continent */}
          <div className="bg-gray-800 rounded-lg p-4">
            <h3 className="text-sm font-bold text-white mb-3">Par continent</h3>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
              {stats.by_continent.map(c => (
                <div key={c.continent} className="bg-gray-700/50 rounded p-3 text-center">
                  <div className="text-sm font-bold text-white">{c.countries}</div>
                  <div className="text-xs text-gray-400">{CONTINENT_LABELS[c.continent] || c.continent}</div>
                  <div className="text-xs text-gray-500 mt-0.5">{fmt(c.links)} liens</div>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* ── Modal CRUD ──────────────────────────────────────────────────────── */}
      {showModal && editEntry && (
        <EntryModal
          entry={editEntry}
          onChange={setEditEntry}
          onSave={saveEntry}
          onClose={() => setShowModal(false)}
          saving={saving}
          error={modalError}
        />
      )}
    </div>
  );
}

// ── Sous-composants ───────────────────────────────────────────────────────────

function CountryDetail({ code, name, entries, loading, onClose, onEdit, onDelete }: {
  code: string; name: string;
  entries: Record<string, DirectoryEntry[]>;
  loading: boolean;
  onClose: () => void;
  onEdit: (e: DirectoryEntry) => void;
  onDelete: (id: number) => void;
}) {
  return (
    <div className="bg-gray-800 rounded-lg p-4">
      <div className="flex justify-between items-center mb-3">
        <h3 className="text-lg font-bold text-white">{name} ({code})</h3>
        <button onClick={onClose} className="text-gray-400 hover:text-white text-sm">Fermer</button>
      </div>

      {loading ? (
        <div className="text-gray-400 animate-pulse py-4">Chargement...</div>
      ) : (
        <div className="space-y-4">
          {/* Coverage */}
          <div className="flex flex-wrap gap-1.5">
            {ALL_CATEGORIES.map(cat => {
              const has = !!entries[cat]?.length;
              return (
                <span key={cat} className={`px-2 py-0.5 rounded text-xs font-medium ${has ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-700 text-gray-500'}`}>
                  {has ? '✓' : '✗'} {CATEGORY_LABELS[cat] || cat}
                </span>
              );
            })}
          </div>

          {/* Entrées */}
          {Object.entries(entries).map(([cat, catEntries]) => (
            <div key={cat}>
              <h4 className="text-sm font-bold text-blue-400 mb-1.5">{CATEGORY_LABELS[cat] || cat} ({(catEntries as DirectoryEntry[]).length})</h4>
              <div className="space-y-1">
                {(catEntries as DirectoryEntry[]).map(e => (
                  <div key={e.id} className="flex items-start gap-2 text-xs bg-gray-900 rounded px-3 py-2">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <a href={e.url} target="_blank" rel="noopener"
                          className="text-white hover:text-blue-400 font-medium">{e.title}</a>
                        {e.nationality_code && (
                          <span className="px-1.5 py-0.5 bg-blue-500/20 text-blue-300 rounded text-[10px] font-mono">{e.nationality_code}</span>
                        )}
                        {e.translations && Object.keys(e.translations).length > 0 && (
                          <span className="px-1 py-0.5 bg-violet-500/20 text-violet-300 rounded text-[10px]">
                            {Object.keys(e.translations).join('/')}
                          </span>
                        )}
                      </div>
                      {e.description && <p className="text-gray-500 mt-0.5 truncate">{e.description}</p>}
                      <div className="flex flex-wrap gap-3 mt-1 text-gray-500">
                        {e.address && <span>📍 {e.address}{e.city ? `, ${e.city}` : ''}</span>}
                        {e.phone && <span>📞 {e.phone}</span>}
                        {e.email && <span>✉ {e.email}</span>}
                        {e.opening_hours && <span>🕐 {e.opening_hours}</span>}
                      </div>
                    </div>
                    <div className="shrink-0 flex flex-col items-end gap-0.5">
                      {e.is_official && <span className="px-1 py-0.5 bg-emerald-500/15 text-emerald-400 rounded text-[10px]">Officiel</span>}
                      <span className="text-gray-600 text-[10px]">{e.domain}</span>
                      <span className={`text-[10px] ${e.trust_score >= 80 ? 'text-emerald-400' : 'text-gray-500'}`}>{e.trust_score}/100</span>
                      <div className="flex gap-1 mt-0.5">
                        <button onClick={() => onEdit(e)} className="text-gray-500 hover:text-white text-[10px]">Edit</button>
                        <button onClick={() => onDelete(e.id)} className="text-gray-600 hover:text-red-400 text-[10px]">×</button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function TranslationBadge({ entry }: { entry: DirectoryEntry }) {
  if (!entry.translations || Object.keys(entry.translations).length === 0) {
    return <span className="text-gray-600 text-[10px]">fr</span>;
  }
  const langs = ['fr', ...Object.keys(entry.translations)];
  return (
    <span className="text-[10px] text-violet-300 font-mono">
      {langs.join('+')}
    </span>
  );
}

function EntryModal({ entry, onChange, onSave, onClose, saving, error }: {
  entry: Partial<DirectoryEntry>;
  onChange: (e: Partial<DirectoryEntry>) => void;
  onSave: () => void;
  onClose: () => void;
  saving: boolean;
  error: string;
}) {
  const set = (field: string, value: unknown) => onChange({ ...entry, [field]: value });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
      <div className="bg-gray-900 rounded-xl border border-gray-700 w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div className="flex justify-between items-center p-4 border-b border-gray-700">
          <h2 className="text-white font-bold">{entry.id ? 'Modifier l\'entrée' : 'Ajouter une entrée'}</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-white">✕</button>
        </div>

        <div className="p-4 space-y-4">
          {/* Pays hôte */}
          <fieldset className="border border-gray-700 rounded-lg p-3">
            <legend className="text-xs text-gray-400 px-1">Pays hôte</legend>
            <div className="grid grid-cols-3 gap-2 mt-1">
              <Field label="Code ISO" placeholder="FR" maxLength={2} value={entry.country_code ?? ''} onChange={v => set('country_code', v.toUpperCase())} />
              <Field label="Nom (FR)" placeholder="France" value={entry.country_name ?? ''} onChange={v => set('country_name', v)} />
              <Field label="Continent" type="select" value={entry.continent ?? 'europe'} onChange={v => set('continent', v)}
                options={[
                  ['europe', 'Europe'], ['afrique', 'Afrique'], ['asie', 'Asie'],
                  ['amerique-nord', 'Amér. Nord'], ['amerique-sud', 'Amér. Sud'], ['oceanie', 'Océanie'],
                ]} />
            </div>
          </fieldset>

          {/* Nationalité */}
          <fieldset className="border border-gray-700 rounded-lg p-3">
            <legend className="text-xs text-gray-400 px-1">Nationalité (ambassade uniquement)</legend>
            <div className="grid grid-cols-2 gap-2 mt-1">
              <Field label="Code ISO nationalité" placeholder="DE (laisser vide si lien universel)" maxLength={2}
                value={entry.nationality_code ?? ''} onChange={v => set('nationality_code', v.toUpperCase() || null)} />
              <Field label="Nom nationalité" placeholder="Allemagne"
                value={entry.nationality_name ?? ''} onChange={v => set('nationality_name', v || null)} />
            </div>
          </fieldset>

          {/* Catégorie + lien */}
          <fieldset className="border border-gray-700 rounded-lg p-3">
            <legend className="text-xs text-gray-400 px-1">Classification & Lien</legend>
            <div className="grid grid-cols-2 gap-2 mt-1">
              <Field label="Catégorie" type="select" value={entry.category ?? 'ambassade'} onChange={v => set('category', v)}
                options={ALL_CATEGORIES.map(c => [c, CATEGORY_LABELS[c] || c])} />
              <Field label="Sous-catégorie" placeholder="ex: ambassade, consulat..." value={entry.sub_category ?? ''} onChange={v => set('sub_category', v || null)} />
              <div className="col-span-2">
                <Field label="Titre" placeholder="Ambassade de France en Allemagne" value={entry.title ?? ''} onChange={v => set('title', v)} />
              </div>
              <div className="col-span-2">
                <Field label="URL" placeholder="https://..." value={entry.url ?? ''} onChange={v => set('url', v)} />
              </div>
              <div className="col-span-2">
                <Field label="Description" placeholder="Description pour la génération IA" value={entry.description ?? ''} onChange={v => set('description', v || null)} />
              </div>
            </div>
          </fieldset>

          {/* Contact */}
          <fieldset className="border border-gray-700 rounded-lg p-3">
            <legend className="text-xs text-gray-400 px-1">Contact</legend>
            <div className="grid grid-cols-2 gap-2 mt-1">
              <Field label="Adresse" value={entry.address ?? ''} onChange={v => set('address', v || null)} />
              <Field label="Ville" value={entry.city ?? ''} onChange={v => set('city', v || null)} />
              <Field label="Téléphone" placeholder="+33 1 23 45 67 89" value={entry.phone ?? ''} onChange={v => set('phone', v || null)} />
              <Field label="Email" placeholder="contact@..." value={entry.email ?? ''} onChange={v => set('email', v || null)} />
              <div className="col-span-2">
                <Field label="Horaires" placeholder="Lun-Ven 9h-17h" value={entry.opening_hours ?? ''} onChange={v => set('opening_hours', v || null)} />
              </div>
            </div>
          </fieldset>

          {/* Qualité */}
          <fieldset className="border border-gray-700 rounded-lg p-3">
            <legend className="text-xs text-gray-400 px-1">Qualité & SEO</legend>
            <div className="grid grid-cols-3 gap-2 mt-1">
              <Field label="Trust score (0-100)" type="number" value={String(entry.trust_score ?? 90)} onChange={v => set('trust_score', parseInt(v))} />
              <Field label="Rel attribute" type="select" value={entry.rel_attribute ?? 'noopener'} onChange={v => set('rel_attribute', v)}
                options={[['noopener', 'noopener'], ['nofollow', 'nofollow'], ['sponsored', 'sponsored']]} />
              <Field label="Texte d'ancre SEO" value={entry.anchor_text ?? ''} onChange={v => set('anchor_text', v || null)} />
            </div>
            <div className="flex gap-4 mt-3">
              <label className="flex items-center gap-2 text-xs text-gray-300 cursor-pointer">
                <input type="checkbox" checked={entry.is_official ?? true} onChange={e => set('is_official', e.target.checked)}
                  className="rounded" />
                Site officiel
              </label>
              <label className="flex items-center gap-2 text-xs text-gray-300 cursor-pointer">
                <input type="checkbox" checked={entry.is_active ?? true} onChange={e => set('is_active', e.target.checked)}
                  className="rounded" />
                Actif
              </label>
            </div>
          </fieldset>

          {error && <p className="text-red-400 text-sm bg-red-500/10 border border-red-500/30 rounded p-2">{error}</p>}
        </div>

        <div className="flex justify-end gap-2 p-4 border-t border-gray-700">
          <button onClick={onClose} className="px-4 py-2 text-sm text-gray-300 hover:text-white">Annuler</button>
          <button onClick={onSave} disabled={saving}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm rounded-lg font-medium">
            {saving ? 'Sauvegarde...' : entry.id ? 'Mettre à jour' : 'Créer'}
          </button>
        </div>
      </div>
    </div>
  );
}

function Field({ label, value, onChange, placeholder, type = 'text', maxLength, options }: {
  label: string; value: string; onChange: (v: string) => void;
  placeholder?: string; type?: string; maxLength?: number;
  options?: [string, string][];
}) {
  const base = "w-full bg-gray-800 border border-gray-700 rounded px-2.5 py-1.5 text-sm text-white placeholder:text-gray-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500";
  return (
    <div>
      <label className="block text-xs text-gray-400 mb-0.5">{label}</label>
      {type === 'select' ? (
        <select value={value} onChange={e => onChange(e.target.value)} className={base}>
          {options?.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </select>
      ) : (
        <input type={type} value={value} onChange={e => onChange(e.target.value)}
          placeholder={placeholder} maxLength={maxLength} className={base} />
      )}
    </div>
  );
}

// ── Composant Imports ─────────────────────────────────────────────────────────

interface ImportJob {
  id: number; source: string; scope_type: string; scope_value: string | null;
  categories: string[] | null; status: string;
  total_expected: number; total_processed: number;
  total_inserted: number; total_updated: number; total_errors: number;
  progress_pct: number; launched_by: string;
  started_at: string | null; completed_at: string | null;
  duration_min: number | null; created_at: string;
}

interface ImportJobDetail extends ImportJob {
  error_message: string | null;
  log_lines: string[];
}

interface SourceMeta {
  label: string; description: string;
  scope_types: string[]; categories: string[];
  iso_codes: string[]; total_countries: number;
  estimated_time: string; cost_estimate?: string;
}

const SOURCE_COLORS: Record<string, string> = {
  wikidata:   'text-blue-400 bg-blue-500/20',
  overpass:   'text-emerald-400 bg-emerald-500/20',
  perplexity: 'text-orange-400 bg-orange-500/20',
};

const STATUS_COLORS: Record<string, string> = {
  pending:   'text-amber-400',
  running:   'text-blue-400',
  completed: 'text-emerald-400',
  failed:    'text-red-400',
  cancelled: 'text-gray-400',
};

const ALL_WIKIDATA_CATS    = ['ambassade'];
const ALL_OVERPASS_CATS    = ['sante', 'hopitaux', 'banque', 'education', 'transport', 'urgences', 'communaute'];
const ALL_PERPLEXITY_CATS  = ['immigration', 'fiscalite', 'logement', 'emploi', 'telecom', 'juridique', 'education', 'sante', 'communaute', 'banque'];

function ImportsTab() {
  const [imports, setImports] = useState<ImportJob[]>([]);
  const [sources, setSources] = useState<Record<string, SourceMeta>>({});
  const [loading, setLoading] = useState(true);
  const [selectedImport, setSelectedImport] = useState<ImportJobDetail | null>(null);
  const [pollingId, setPollingId] = useState<number | null>(null);

  // Formulaire
  const [source, setSource] = useState<'wikidata' | 'overpass' | 'perplexity'>('wikidata');
  const [scopeType, setScopeType] = useState<'nationality' | 'country' | 'all'>('nationality');
  const [scopeValue, setScopeValue] = useState('');
  const [selectedCats, setSelectedCats] = useState<string[]>([]);
  const [creating, setCreating] = useState(false);
  const [createError, setCreateError] = useState('');

  const availableCats = source === 'wikidata' ? ALL_WIKIDATA_CATS
    : source === 'overpass' ? ALL_OVERPASS_CATS
    : ALL_PERPLEXITY_CATS;

  // Charger l'historique + les métadonnées sources
  useEffect(() => {
    Promise.all([
      api.get('/country-directory/imports'),
      api.get('/country-directory/imports/sources'),
    ]).then(([iRes, sRes]) => {
      setImports(iRes.data);
      setSources(sRes.data);
    }).finally(() => setLoading(false));
  }, []);

  // Polling sur l'import sélectionné si running
  useEffect(() => {
    if (!selectedImport) return;
    if (!['running', 'pending'].includes(selectedImport.status)) return;

    const id = window.setInterval(async () => {
      const res = await api.get(`/country-directory/imports/${selectedImport.id}`);
      setSelectedImport(res.data);
      setImports(prev => prev.map(j => j.id === res.data.id ? res.data : j));

      if (!['running', 'pending'].includes(res.data.status)) {
        clearInterval(id);
        setPollingId(null);
      }
    }, 3000);

    setPollingId(id);
    return () => clearInterval(id);
  }, [selectedImport?.id, selectedImport?.status]);

  const reloadImports = async () => {
    const res = await api.get('/country-directory/imports');
    setImports(res.data);
  };

  const openDetail = async (id: number) => {
    const res = await api.get(`/country-directory/imports/${id}`);
    setSelectedImport(res.data);
  };

  const cancelImport = async (id: number) => {
    await api.post(`/country-directory/imports/${id}/cancel`);
    await reloadImports();
    if (selectedImport?.id === id) {
      const res = await api.get(`/country-directory/imports/${id}`);
      setSelectedImport(res.data);
    }
  };

  const deleteImport = async (id: number) => {
    if (!confirm('Supprimer cet import de l\'historique ?')) return;
    await api.delete(`/country-directory/imports/${id}`);
    await reloadImports();
    if (selectedImport?.id === id) setSelectedImport(null);
  };

  const toggleCat = (cat: string) => {
    setSelectedCats(prev => prev.includes(cat) ? prev.filter(c => c !== cat) : [...prev, cat]);
  };

  const launchImport = async () => {
    setCreating(true); setCreateError('');
    try {
      await api.post('/country-directory/imports', {
        source,
        scope_type: scopeType,
        scope_value: scopeType === 'all' ? null : (scopeValue.trim() || null),
        categories: selectedCats.length > 0 ? selectedCats : null,
      });
      setScopeValue('');
      setSelectedCats([]);
      await reloadImports();
    } catch (err: any) {
      setCreateError(err?.response?.data?.message || 'Erreur lors du lancement');
    } finally {
      setCreating(false);
    }
  };

  // Quand la source change, réinitialiser les catégories
  useEffect(() => {
    setSelectedCats([]);
    setScopeType(source === 'wikidata' ? 'nationality' : 'country');
    // Wikidata = nationality scope, Overpass + Perplexity = country scope
  }, [source]);

  const srcMeta = sources[source];
  const isRunning = (j: ImportJob) => ['running', 'pending'].includes(j.status);

  if (loading) return <div className="text-gray-400 animate-pulse py-4">Chargement...</div>;

  return (
    <div className="space-y-5">
      {/* ── Formulaire de lancement ── */}
      <div className="bg-gray-800 rounded-xl p-5 border border-gray-700">
        <h2 className="text-white font-bold text-base mb-1">Lancer un import</h2>
        <p className="text-xs text-gray-400 mb-4">
          L'import tourne en background (queue Laravel). Tu peux fermer la page, il continuera.
        </p>

        {/* Source */}
        <div className="mb-4">
          <label className="block text-xs text-gray-400 mb-2">Source de données</label>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
            {(['wikidata', 'overpass', 'perplexity'] as const).map(s => (
              <button key={s} onClick={() => setSource(s)}
                className={`p-3 rounded-lg border text-left transition-colors ${source === s ? 'border-blue-500 bg-blue-500/10' : 'border-gray-700 hover:border-gray-500'}`}>
                <div className={`text-xs font-bold mb-0.5 ${SOURCE_COLORS[s]?.split(' ')[0]}`}>
                  {s === 'wikidata' ? '🌐 Wikidata' : s === 'overpass' ? '🗺️ OpenStreetMap' : '🔍 Perplexity'}
                </div>
                <div className="text-xs text-gray-400 line-clamp-2">
                  {sources[s]?.description?.substring(0, 80)}...
                </div>
                {sources[s] && (
                  <div className="text-[10px] text-gray-500 mt-1">{sources[s].estimated_time}</div>
                )}
                {sources[s]?.cost_estimate && (
                  <div className="text-[10px] text-amber-500/70 mt-0.5">{sources[s].cost_estimate}</div>
                )}
              </button>
            ))}
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
          {/* Scope */}
          <div>
            <label className="block text-xs text-gray-400 mb-1">Périmètre</label>
            <select value={scopeType} onChange={e => setScopeType(e.target.value as any)}
              className="w-full bg-gray-900 border border-gray-600 rounded-lg px-3 py-2 text-sm text-white">
              {(srcMeta?.scope_types ?? ['all']).map(t => (
                <option key={t} value={t}>
                  {t === 'all' ? 'Tous (195 pays/nationalités)' : t === 'nationality' ? 'Nationalités spécifiques' : 'Pays hôtes spécifiques'}
                </option>
              ))}
            </select>
          </div>

          {/* Codes ISO */}
          {scopeType !== 'all' && (
            <div>
              <label className="block text-xs text-gray-400 mb-1">
                Codes ISO {scopeType === 'nationality' ? 'nationalités' : 'pays'} (séparés par virgule)
              </label>
              <input type="text" value={scopeValue} onChange={e => setScopeValue(e.target.value)}
                placeholder="Ex: FR,DE,MA,DZ,TN,GB"
                className="w-full bg-gray-900 border border-gray-600 rounded-lg px-3 py-2 text-sm text-white font-mono uppercase placeholder:normal-case placeholder:text-gray-600" />
              <p className="text-[10px] text-gray-500 mt-0.5">
                Laisse vide = tout importer ({srcMeta?.total_countries ?? 195} pays)
              </p>
            </div>
          )}
        </div>

        {/* Catégories */}
        <div className="mb-4">
          <label className="block text-xs text-gray-400 mb-2">
            Catégories (vide = toutes)
          </label>
          <div className="flex flex-wrap gap-1.5">
            {availableCats.map(cat => (
              <button key={cat} onClick={() => toggleCat(cat)}
                className={`px-2.5 py-1 rounded text-xs border transition-colors ${selectedCats.includes(cat) ? 'bg-blue-600 border-blue-500 text-white' : 'bg-gray-700 border-gray-600 text-gray-300 hover:border-gray-400'}`}>
                {CATEGORY_LABELS[cat] || cat}
              </button>
            ))}
          </div>
          {selectedCats.length === 0 && (
            <p className="text-[10px] text-gray-500 mt-1">Toutes les catégories seront importées.</p>
          )}
        </div>

        {/* Avertissement Perplexity coût */}
        {source === 'perplexity' && scopeType === 'all' && (
          <div className="mb-4 p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg text-xs text-amber-300">
            Import complet (195 pays × toutes catégories) : coût estimé ~$3-4 USD via Perplexity sonar.
            URLs recherchées sur le vrai web — aucune hallucination. Pour tester d'abord : quelques pays (ex: FR,DE,MA,TH).
          </div>
        )}

        {createError && (
          <p className="mb-3 text-red-400 text-sm">{createError}</p>
        )}

        <button onClick={launchImport} disabled={creating}
          className="px-5 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-medium text-sm rounded-lg">
          {creating ? 'Lancement...' : 'Lancer l\'import'}
        </button>
      </div>

      {/* ── Historique ── */}
      <div className="space-y-2">
        <div className="flex justify-between items-center">
          <h3 className="text-sm font-bold text-white">Historique des imports ({imports.length})</h3>
          <button onClick={reloadImports} className="text-xs text-gray-400 hover:text-white">Rafraîchir</button>
        </div>

        {imports.length === 0 ? (
          <p className="text-gray-400 text-sm py-3">Aucun import lancé.</p>
        ) : (
          <div className="space-y-2">
            {imports.map(j => (
              <div key={j.id}
                className={`bg-gray-800 rounded-lg p-3 border cursor-pointer hover:border-gray-500 transition-colors ${selectedImport?.id === j.id ? 'border-blue-500' : 'border-gray-700'}`}
                onClick={() => openDetail(j.id)}>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className={`text-xs font-bold px-2 py-0.5 rounded ${SOURCE_COLORS[j.source] || ''}`}>
                      {j.source}
                    </span>
                    <span className={`text-xs font-bold ${STATUS_COLORS[j.status]}`}>
                      {j.status === 'running' ? '⏳ ' : j.status === 'completed' ? '✓ ' : j.status === 'failed' ? '✗ ' : ''}
                      {j.status}
                    </span>
                    <span className="text-xs text-gray-400">
                      {j.scope_type === 'all' ? 'Tous les pays' : j.scope_value || 'Tous'}
                    </span>
                    {j.categories && j.categories.length > 0 && (
                      <span className="text-xs text-gray-500">[{j.categories.join(',')}]</span>
                    )}
                  </div>
                  <div className="flex items-center gap-2">
                    {j.duration_min !== null && (
                      <span className="text-xs text-gray-500">{j.duration_min}min</span>
                    )}
                    {isRunning(j) && (
                      <button onClick={e => { e.stopPropagation(); cancelImport(j.id); }}
                        className="text-xs px-2 py-0.5 bg-red-600/20 text-red-400 hover:bg-red-600/40 rounded">
                        Annuler
                      </button>
                    )}
                    {!isRunning(j) && (
                      <button onClick={e => { e.stopPropagation(); deleteImport(j.id); }}
                        className="text-xs text-gray-600 hover:text-red-400">×</button>
                    )}
                  </div>
                </div>

                {/* Barre de progression */}
                {(j.status === 'running' || j.status === 'completed') && j.total_expected > 0 && (
                  <div className="mt-2">
                    <div className="flex justify-between text-[10px] text-gray-400 mb-0.5">
                      <span>{j.total_processed}/{j.total_expected}</span>
                      <span>{j.total_inserted} insérés · {j.total_updated} mis à jour · {j.total_errors} erreurs</span>
                    </div>
                    <div className="h-1.5 bg-gray-700 rounded-full overflow-hidden">
                      <div className={`h-full rounded-full transition-all ${j.status === 'completed' ? 'bg-emerald-500' : 'bg-blue-500'}`}
                        style={{ width: `${j.progress_pct}%` }} />
                    </div>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* ── Détail / Log temps réel ── */}
      {selectedImport && (
        <div className="bg-gray-800 rounded-xl border border-gray-700 p-4">
          <div className="flex justify-between items-center mb-3">
            <h3 className="text-white font-bold text-sm">
              Import #{selectedImport.id} — {selectedImport.source}
              {isRunning(selectedImport) && (
                <span className="ml-2 inline-block w-2 h-2 bg-blue-400 rounded-full animate-pulse" />
              )}
            </h3>
            <button onClick={() => setSelectedImport(null)} className="text-gray-400 hover:text-white text-sm">Fermer</button>
          </div>

          {/* Stats */}
          <div className="grid grid-cols-4 gap-2 mb-3">
            <div className="bg-gray-900 rounded p-2 text-center">
              <div className="text-sm font-bold text-white">{selectedImport.total_inserted}</div>
              <div className="text-[10px] text-gray-400">Insérés</div>
            </div>
            <div className="bg-gray-900 rounded p-2 text-center">
              <div className="text-sm font-bold text-blue-400">{selectedImport.total_updated}</div>
              <div className="text-[10px] text-gray-400">Mis à jour</div>
            </div>
            <div className="bg-gray-900 rounded p-2 text-center">
              <div className="text-sm font-bold text-red-400">{selectedImport.total_errors}</div>
              <div className="text-[10px] text-gray-400">Erreurs</div>
            </div>
            <div className="bg-gray-900 rounded p-2 text-center">
              <div className="text-sm font-bold text-gray-300">{selectedImport.progress_pct}%</div>
              <div className="text-[10px] text-gray-400">Progression</div>
            </div>
          </div>

          {selectedImport.error_message && (
            <div className="mb-3 p-2 bg-red-500/10 border border-red-500/30 rounded text-xs text-red-300">
              {selectedImport.error_message}
            </div>
          )}

          {/* Log */}
          <div className="bg-gray-950 rounded-lg p-3 max-h-72 overflow-y-auto">
            <p className="text-[10px] text-gray-500 mb-2">
              Log temps réel {isRunning(selectedImport) ? '(actualisation toutes les 3s)' : ''}
            </p>
            {selectedImport.log_lines.length === 0 ? (
              <p className="text-xs text-gray-600">En attente de démarrage...</p>
            ) : (
              <div className="space-y-0.5">
                {selectedImport.log_lines.map((line, i) => (
                  <p key={i} className={`text-[11px] font-mono ${line.includes('Erreur') || line.includes('ERREUR') ? 'text-red-300' : line.includes('terminé') || line.includes('✓') ? 'text-emerald-300' : 'text-gray-400'}`}>
                    {line}
                  </p>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function StatCard({ label, value, color = 'text-white' }: { label: string; value: string; color?: string }) {
  return (
    <div className="bg-gray-800 rounded-lg p-3">
      <div className="text-xs text-gray-400">{label}</div>
      <div className={`text-lg font-bold tabular-nums ${color}`}>{value}</div>
    </div>
  );
}
