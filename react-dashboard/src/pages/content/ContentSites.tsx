import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';

interface ContentSource {
  id: number;
  name: string;
  slug: string;
  base_url: string;
  status: string;
  total_countries: number;
  total_articles: number;
  total_links: number;
  last_scraped_at: string | null;
}

export default function ContentSites() {
  const [sources, setSources] = useState<ContentSource[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAdd, setShowAdd] = useState(false);
  const [newName, setNewName] = useState('');
  const [newUrl, setNewUrl] = useState('');
  const [adding, setAdding] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchSources = async () => {
    try {
      const res = await api.get('/content/sources');
      setSources(res.data);
    } catch {
      setError('Erreur de chargement');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchSources(); }, []);

  // Auto-refresh while any source is scraping
  useEffect(() => {
    if (!sources.some(s => s.status === 'scraping')) return;
    const interval = setInterval(fetchSources, 15000);
    return () => clearInterval(interval);
  }, [sources]);

  const handleAdd = async (e: FormEvent) => {
    e.preventDefault();
    setAdding(true);
    try {
      await api.post('/content/sources', { name: newName, base_url: newUrl });
      setNewName(''); setNewUrl(''); setShowAdd(false);
      fetchSources();
    } catch (err: any) {
      setError(err.response?.data?.message || 'Erreur');
    } finally {
      setAdding(false);
    }
  };

  const handleScrape = async (slug: string) => {
    try {
      await api.post(`/content/sources/${slug}/scrape`);
      fetchSources();
    } catch (err: any) {
      setError(err.response?.status === 409 ? 'Scraping deja en cours' : 'Erreur');
      setTimeout(() => setError(null), 4000);
    }
  };

  const handleScrapeBusinesses = async (slug: string) => {
    try {
      await api.post(`/businesses/scrape/${slug}`);
      setError(null);
    } catch {
      setError('Erreur lancement annuaire');
    }
  };

  const handleScrapeMagazine = async (slug: string) => {
    try {
      await api.post(`/content/sources/${slug}/scrape-magazine`);
      setError(null);
    } catch {
      setError('Erreur lancement magazine');
    }
  };

  const handleScrapeServices = async (slug: string) => {
    try {
      await api.post(`/content/sources/${slug}/scrape-services`);
    } catch {
      setError('Erreur lancement services');
    }
  };

  const handleScrapeThematic = async (slug: string) => {
    try {
      await api.post(`/content/sources/${slug}/scrape-thematic`);
    } catch {
      setError('Erreur lancement thematiques');
    }
  };

  const handleScrapeCities = async (slug: string) => {
    try {
      await api.post(`/content/sources/${slug}/scrape-cities`);
    } catch {
      setError('Erreur lancement villes');
    }
  };

  const handleScrapeQA = async (slug: string) => {
    try {
      await api.post(`/questions/scrape/${slug}`);
    } catch {
      setError('Erreur lancement Q&A');
    }
  };

  const handleScrapeFull = async (slug: string) => {
    try {
      await api.post(`/content/sources/${slug}/scrape-full`);
      fetchSources();
    } catch {
      setError('Erreur lancement scraping complet');
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64" role="status">
        <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-title text-2xl font-bold text-white">Les Sites</h1>
          <p className="text-muted text-sm mt-1">Sites sources pour le scraping de contenu et d'annuaires</p>
        </div>
        <button onClick={() => setShowAdd(!showAdd)}
          className="px-4 py-2 bg-violet hover:bg-violet/80 text-white rounded-lg text-sm font-medium transition-colors">
          + Ajouter un site
        </button>
      </div>

      {error && (
        <div className="bg-red-900/20 border border-red-500/30 text-red-400 p-3 rounded-xl text-sm flex justify-between">
          <span>{error}</span>
          <button onClick={() => setError(null)} className="text-red-300 hover:text-white ml-3">x</button>
        </div>
      )}

      {showAdd && (
        <form onSubmit={handleAdd} className="bg-surface border border-border rounded-xl p-4 flex gap-3 items-end">
          <div className="flex-1">
            <label htmlFor="site-name" className="text-xs text-muted block mb-1">Nom du site</label>
            <input id="site-name" type="text" value={newName} onChange={(e) => setNewName(e.target.value)}
              placeholder="Expat.com" required
              className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm" />
          </div>
          <div className="flex-1">
            <label htmlFor="site-url" className="text-xs text-muted block mb-1">URL des guides</label>
            <input id="site-url" type="url" value={newUrl} onChange={(e) => setNewUrl(e.target.value)}
              placeholder="https://www.expat.com/fr/guide/" required
              className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm" />
          </div>
          <button type="submit" disabled={adding}
            className="px-4 py-2 bg-violet hover:bg-violet/80 text-white rounded-lg text-sm font-medium disabled:opacity-50">
            {adding ? '...' : 'Ajouter'}
          </button>
        </form>
      )}

      {sources.length === 0 ? (
        <div className="bg-surface border border-border rounded-xl p-12 text-center">
          <div className="text-4xl mb-3">🌐</div>
          <p className="text-white font-medium mb-1">Aucun site configure</p>
          <p className="text-muted text-sm mb-4">Ajoutez un site source pour commencer le scraping</p>
          <button onClick={() => setShowAdd(true)}
            className="px-4 py-2 bg-violet hover:bg-violet/80 text-white rounded-lg text-sm">
            + Ajouter un site
          </button>
        </div>
      ) : (
        <div className="space-y-4">
          {sources.map((src) => (
            <div key={src.id} className="bg-surface border border-border rounded-xl p-5">
              {/* Header */}
              <div className="flex items-center justify-between mb-4">
                <div>
                  <div className="flex items-center gap-3">
                    <h2 className="text-white font-title font-bold text-lg">{src.name}</h2>
                    {src.status === 'scraping' && (
                      <span className="px-2.5 py-0.5 bg-amber/20 text-amber rounded-full text-xs font-medium animate-pulse">Scraping...</span>
                    )}
                    {src.status === 'completed' && (
                      <span className="px-2.5 py-0.5 bg-green-900/30 text-green-400 rounded-full text-xs font-medium">Termine</span>
                    )}
                    {src.status === 'pending' && (
                      <span className="px-2.5 py-0.5 bg-gray-700 text-gray-400 rounded-full text-xs font-medium">En attente</span>
                    )}
                  </div>
                  <p className="text-xs text-muted mt-0.5">{src.base_url}</p>
                </div>
              </div>

              {/* Stats */}
              <div className="grid grid-cols-4 gap-4 mb-4">
                <div className="text-center">
                  <div className="text-white font-bold text-lg">{src.total_countries}</div>
                  <div className="text-xs text-muted">Pays</div>
                </div>
                <div className="text-center">
                  <div className="text-white font-bold text-lg">{src.total_articles.toLocaleString()}</div>
                  <div className="text-xs text-muted">Articles</div>
                </div>
                <div className="text-center">
                  <div className="text-white font-bold text-lg">{src.total_links.toLocaleString()}</div>
                  <div className="text-xs text-muted">Liens</div>
                </div>
                <div className="text-center">
                  <div className="text-white font-bold text-xs">
                    {src.last_scraped_at ? new Date(src.last_scraped_at).toLocaleDateString('fr-FR') : '-'}
                  </div>
                  <div className="text-xs text-muted">Dernier scraping</div>
                </div>
              </div>

              {/* Actions */}
              <div className="flex gap-2 border-t border-border pt-3">
                <Link to={`/content/${src.slug}`}
                  className="px-3 py-1.5 bg-surface2 text-white rounded-lg text-xs font-medium hover:bg-violet/20 hover:text-violet-light transition-colors">
                  Voir les guides & pays
                </Link>
                <button onClick={() => handleScrape(src.slug)} disabled={src.status === 'scraping'}
                  className="px-3 py-1.5 bg-violet/20 text-violet-light rounded-lg text-xs font-medium hover:bg-violet/30 disabled:opacity-50 transition-colors">
                  {src.status === 'scraping' ? 'Guides en cours...' : 'Scraper les guides'}
                </button>
                <button onClick={() => handleScrapeMagazine(src.slug)}
                  className="px-3 py-1.5 bg-amber/20 text-amber rounded-lg text-xs font-medium hover:bg-amber/30 transition-colors">
                  Scraper le magazine
                </button>
                <button onClick={() => handleScrapeServices(src.slug)}
                  className="px-3 py-1.5 bg-green-900/30 text-green-400 rounded-lg text-xs font-medium hover:bg-green-900/40 transition-colors">
                  Scraper les services
                </button>
                <button onClick={() => handleScrapeThematic(src.slug)}
                  className="px-3 py-1.5 bg-pink-900/30 text-pink-400 rounded-lg text-xs font-medium hover:bg-pink-900/40 transition-colors">
                  Guides thematiques
                </button>
                <button onClick={() => handleScrapeCities(src.slug)}
                  className="px-3 py-1.5 bg-blue-900/30 text-blue-400 rounded-lg text-xs font-medium hover:bg-blue-900/40 transition-colors">
                  Articles manquants
                </button>
                <button onClick={() => handleScrapeBusinesses(src.slug)}
                  className="px-3 py-1.5 bg-cyan/20 text-cyan rounded-lg text-xs font-medium hover:bg-cyan/30 transition-colors">
                  Scraper l'annuaire
                </button>
                <button onClick={() => handleScrapeQA(src.slug)}
                  className="px-3 py-1.5 bg-purple-900/30 text-purple-400 rounded-lg text-xs font-medium hover:bg-purple-900/40 transition-colors">
                  Scraper Q&A forum
                </button>
                <button onClick={() => handleScrapeFull(src.slug)} disabled={src.status === 'scraping'}
                  className="px-3 py-1.5 bg-white/10 text-white rounded-lg text-xs font-medium hover:bg-white/20 disabled:opacity-50 transition-colors">
                  Scraper tout (WordPress)
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
