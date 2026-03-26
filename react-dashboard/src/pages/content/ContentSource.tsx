import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../../api/client';

interface Source {
  id: number;
  name: string;
  slug: string;
  base_url: string;
  status: string;
  total_countries: number;
  total_articles: number;
  total_links: number;
  scraped_countries: number;
  last_scraped_at: string | null;
}

interface Country {
  id: number;
  name: string;
  slug: string;
  continent: string | null;
  articles_count: number;
  scraped_at: string | null;
}

export default function ContentSourcePage() {
  const { sourceSlug } = useParams<{ sourceSlug: string }>();
  const [source, setSource] = useState<Source | null>(null);
  const [countries, setCountries] = useState<Country[]>([]);
  const [continents, setContinents] = useState<string[]>([]);
  const [filterContinent, setFilterContinent] = useState('');
  const [searchCountry, setSearchCountry] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!sourceSlug) return;
    setLoading(true);
    Promise.all([
      api.get(`/content/sources/${sourceSlug}`),
      api.get(`/content/sources/${sourceSlug}/countries`),
    ]).then(([srcRes, ctryRes]) => {
      setSource(srcRes.data);
      setCountries(ctryRes.data.countries);
      setContinents(ctryRes.data.continents);
    }).catch(() => setError('Erreur de chargement')).finally(() => setLoading(false));
  }, [sourceSlug]);

  // Auto-refresh while scraping
  useEffect(() => {
    if (source?.status !== 'scraping' || !sourceSlug) return;
    const interval = setInterval(() => {
      Promise.all([
        api.get(`/content/sources/${sourceSlug}`),
        api.get(`/content/sources/${sourceSlug}/countries`),
      ]).then(([srcRes, ctryRes]) => {
        setSource(srcRes.data);
        setCountries(ctryRes.data.countries);
      }).catch(() => {});
    }, 10000);
    return () => clearInterval(interval);
  }, [source?.status, sourceSlug]);

  const handleScrape = async () => {
    if (!sourceSlug) return;
    try {
      await api.post(`/content/sources/${sourceSlug}/scrape`);
      const res = await api.get(`/content/sources/${sourceSlug}`);
      setSource(res.data);
    } catch (err: any) {
      if (err.response?.status === 409) {
        setError('Scraping deja en cours');
        setTimeout(() => setError(null), 5000);
      }
    }
  };

  // Filter + search
  const filtered = countries.filter((c) => {
    if (filterContinent && c.continent !== filterContinent) return false;
    if (searchCountry && !c.name.toLowerCase().includes(searchCountry.toLowerCase())) return false;
    return true;
  });

  // Group by continent
  const grouped = filtered.reduce((acc, c) => {
    const key = c.continent || 'Autre';
    if (!acc[key]) acc[key] = [];
    acc[key].push(c);
    return acc;
  }, {} as Record<string, Country[]>);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64" role="status">
        <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  if (!source) return <div className="p-6 text-muted">Source non trouvee</div>;

  const progress = source.total_countries > 0
    ? Math.round((source.scraped_countries / source.total_countries) * 100)
    : 0;

  const scrapedCount = countries.filter(c => c.scraped_at).length;
  const totalArticles = countries.reduce((sum, c) => sum + c.articles_count, 0);

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-title text-2xl font-bold text-white">{source.name}</h1>
          <p className="text-muted text-sm">{source.base_url}</p>
        </div>
        <div className="flex items-center gap-3">
          {source.status === 'scraping' && (
            <span className="px-3 py-1 bg-amber/20 text-amber rounded-full text-xs font-medium animate-pulse">
              Scraping en cours...
            </span>
          )}
          {source.status === 'completed' && (
            <span className="px-3 py-1 bg-green-900/30 text-green-400 rounded-full text-xs font-medium">
              Termine
            </span>
          )}
          <button
            onClick={handleScrape}
            disabled={source.status === 'scraping'}
            className="px-4 py-2 bg-violet hover:bg-violet/80 text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors"
          >
            {source.status === 'scraping' ? 'En cours...' : 'Lancer le scraping'}
          </button>
        </div>
      </div>

      {error && (
        <div className="bg-red-900/20 border border-red-500/30 text-red-400 p-3 rounded-xl text-sm">{error}</div>
      )}

      {/* Stats bar */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
        {[
          { label: 'Continents', value: continents.length },
          { label: 'Pays', value: `${scrapedCount}/${countries.length}` },
          { label: 'Articles', value: totalArticles.toLocaleString() },
          { label: 'Liens', value: source.total_links.toLocaleString() },
          { label: 'Progression', value: `${progress}%` },
        ].map((s) => (
          <div key={s.label} className="bg-surface border border-border rounded-xl p-3 text-center">
            <div className="text-lg font-bold text-white">{s.value}</div>
            <div className="text-xs text-muted">{s.label}</div>
          </div>
        ))}
      </div>

      {/* Progress bar if scraping */}
      {source.status === 'scraping' && (
        <div className="bg-surface border border-border rounded-xl p-4">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-white">Progression</span>
            <span className="text-sm text-muted">{scrapedCount}/{countries.length} pays</span>
          </div>
          <div className="h-2 bg-surface2 rounded-full overflow-hidden">
            <div
              className="h-full bg-violet rounded-full transition-all duration-500"
              style={{ width: `${progress}%` }}
            />
          </div>
        </div>
      )}

      {/* Search + filter */}
      <div className="flex flex-wrap gap-3 items-center">
        <input
          type="text"
          value={searchCountry}
          onChange={(e) => setSearchCountry(e.target.value)}
          placeholder="Rechercher un pays..."
          className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-64"
        />
        <div className="flex gap-1.5 flex-wrap">
          <button
            onClick={() => setFilterContinent('')}
            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
              !filterContinent ? 'bg-violet text-white' : 'bg-surface2 text-muted hover:text-white'
            }`}
          >
            Tous ({countries.length})
          </button>
          {continents.map((c) => {
            const count = countries.filter(co => co.continent === c).length;
            return (
              <button
                key={c}
                onClick={() => setFilterContinent(filterContinent === c ? '' : c)}
                className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                  filterContinent === c ? 'bg-violet text-white' : 'bg-surface2 text-muted hover:text-white'
                }`}
              >
                {c} ({count})
              </button>
            );
          })}
        </div>
      </div>

      {/* Countries grouped by continent */}
      <div className="space-y-8">
        {Object.entries(grouped).sort(([a], [b]) => a.localeCompare(b)).map(([continent, ctries]) => (
          <div key={continent}>
            <div className="flex items-center gap-3 mb-3">
              <h2 className="text-white font-title font-bold text-lg">{continent}</h2>
              <span className="text-xs text-muted bg-surface2 px-2 py-0.5 rounded">
                {ctries.length} pays &middot; {ctries.reduce((s, c) => s + c.articles_count, 0)} articles
              </span>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2">
              {ctries.sort((a, b) => a.name.localeCompare(b.name)).map((c) => (
                <Link
                  key={c.id}
                  to={`/content/${sourceSlug}/${c.slug}`}
                  className="bg-surface border border-border rounded-lg p-3 hover:bg-surface2 hover:border-violet/30 transition-all group"
                >
                  <div className="flex items-center justify-between">
                    <div className="min-w-0">
                      <div className="text-white text-sm font-medium group-hover:text-violet-light transition-colors truncate">
                        {c.name}
                      </div>
                      <div className="text-xs text-muted mt-0.5">
                        {c.articles_count > 0 ? `${c.articles_count} articles` : 'En attente'}
                      </div>
                    </div>
                    <div className="flex-shrink-0 ml-2">
                      {c.scraped_at ? (
                        <span className="w-2.5 h-2.5 rounded-full bg-green-400 block" title="Scrape" />
                      ) : source.status === 'scraping' ? (
                        <span className="w-2.5 h-2.5 rounded-full bg-amber animate-pulse block" title="En file" />
                      ) : (
                        <span className="w-2.5 h-2.5 rounded-full bg-gray-600 block" title="Non scrape" />
                      )}
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
