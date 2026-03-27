import { useEffect, useState, useCallback } from 'react';
import api from '../../api/client';

// ── Types ──────────────────────────────────────────────────
interface Category {
  id: number; slug: string; name: string; description: string; icon: string; sort_order: number;
  total_items: number; cleaned_items: number; raw_items: number; ready_items: number;
  countries: number; themes: number; sub_categories: number;
}

interface SourceItem {
  id: number; source_type: string; source_id: number | null; title: string;
  country: string | null; country_slug: string | null; theme: string | null;
  sub_category: string | null; language: string; word_count: number;
  quality_score: number; is_cleaned: boolean; processing_status: string;
  used_count: number; data_json: Record<string, unknown> | null;
}

interface SubCategory { sub_category: string; count: number; cleaned: number; raw: number }
interface CountryItem { country: string; country_slug: string; count: number }
interface ThemeItem { theme: string; count: number }

interface CategoryData {
  items: { data: SourceItem[]; total: number; current_page: number; last_page: number };
  sub_categories: SubCategory[];
  countries: CountryItem[];
  themes: ThemeItem[];
}

interface SourceArticle {
  id: number; title: string; url?: string; content_text?: string; word_count?: number;
  category?: string; section?: string; meta_title?: string; meta_description?: string;
  source_name?: string; source_url?: string; scraped_at?: string;
  country?: string; city?: string; replies?: number; views?: number;
  last_post_date?: string; last_post_author?: string; article_status?: string;
}
interface QaQuestion { id: number; title: string; url: string; views: number; replies: number; country: string }
interface ItemDetail {
  item: SourceItem;
  source: SourceArticle | null;
  pillar_sources: SourceArticle[] | null;
  qa_questions: QaQuestion[] | null;
}

interface OverallStats {
  overall: { total: number; cleaned: number; raw: number; ready: number; used: number; countries: number; themes: number };
  by_status: { processing_status: string; count: number }[];
  by_source_type: { source_type: string; count: number }[];
}

// ── Helpers ────────────────────────────────────────────────
const ICONS: Record<string, string> = {
  globe: '🌍', 'bar-chart': '📊', 'message-circle': '💬', type: '✏️',
  'help-circle': '❓', 'file-text': '📄', search: '🔍', 'alert-triangle': '⚠️', users: '👥',
  'book-open': '📖',
};

const STATUS_COLORS: Record<string, string> = {
  raw: 'bg-gray-600 text-gray-300', cleaned: 'bg-blue-500/20 text-blue-400',
  ready: 'bg-emerald-500/20 text-emerald-400', used: 'bg-purple-500/20 text-purple-400',
};

const THEME_LABELS: Record<string, string> = {
  visa: 'Visa', emploi: 'Emploi', logement: 'Logement', sante: 'Sante',
  banque: 'Banque', education: 'Education', transport: 'Transport', telecom: 'Telecom',
  fiscalite: 'Fiscalite', retraite: 'Retraite', famille: 'Famille', cout_vie: 'Cout de vie',
  general: 'General', autre: 'Autre', 'question-directe': 'Question directe',
  'sujet-discussion': 'Discussion', retraites: 'Retraites', familles: 'Familles',
  'digital-nomads': 'Digital Nomads', entrepreneurs: 'Entrepreneurs', pvtistes: 'PVTistes',
  etudiants: 'Etudiants', 'multi-theme': 'Multi-theme (pillar)',
  arnaques: 'Arnaques', vols: 'Vols', accidents: 'Accidents', agressions: 'Agressions',
  'securite-generale': 'Securite generale', urgences: 'Urgences',
};

function fmt(n: number): string { return n.toLocaleString('fr-FR'); }

// ── Component ──────────────────────────────────────────────
export default function GenerationSources() {
  const [categories, setCategories] = useState<Category[]>([]);
  const [stats, setStats] = useState<OverallStats | null>(null);
  const [selectedCat, setSelectedCat] = useState<string | null>(null);
  const [catData, setCatData] = useState<CategoryData | null>(null);
  const [loading, setLoading] = useState(true);
  const [catLoading, setCatLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [detail, setDetail] = useState<ItemDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);

  // Filters
  const [filterCleaned, setFilterCleaned] = useState<string>('');
  const [filterTheme, setFilterTheme] = useState('');
  const [filterCountry, setFilterCountry] = useState('');
  const [filterSubCat, setFilterSubCat] = useState('');
  const [filterSearch, setFilterSearch] = useState('');
  const [page, setPage] = useState(1);

  useEffect(() => {
    Promise.all([
      api.get('/generation-sources/categories'),
      api.get('/generation-sources/stats'),
    ]).then(([catRes, statsRes]) => {
      setCategories(catRes.data);
      setStats(statsRes.data);
    }).catch(() => setError('Erreur de chargement'))
      .finally(() => setLoading(false));
  }, []);

  const loadCategory = useCallback(async (slug: string, p = 1) => {
    setCatLoading(true);
    try {
      const params = new URLSearchParams();
      params.set('page', String(p));
      if (filterCleaned) params.set('cleaned', filterCleaned);
      if (filterTheme) params.set('theme', filterTheme);
      if (filterCountry) params.set('country_slug', filterCountry);
      if (filterSubCat) params.set('sub_category', filterSubCat);
      if (filterSearch) params.set('search', filterSearch);
      const res = await api.get(`/generation-sources/${slug}/items?${params}`);
      setCatData(res.data);
    } catch (err) { console.error('Failed to load category:', slug, err); }
    setCatLoading(false);
  }, [filterCleaned, filterTheme, filterCountry, filterSubCat, filterSearch]);

  useEffect(() => {
    if (selectedCat) { setPage(1); loadCategory(selectedCat, 1); }
  }, [selectedCat, filterCleaned, filterTheme, filterCountry, filterSubCat, filterSearch, loadCategory]);

  const handlePageChange = (p: number) => {
    setPage(p);
    if (selectedCat) loadCategory(selectedCat, p);
  };

  const openDetail = async (item: SourceItem) => {
    setDetailLoading(true);
    setDetail(null);
    try {
      const res = await api.get(`/generation-sources/items/${item.id}`);
      setDetail(res.data);
    } catch { setDetail({ item, source: null }); }
    setDetailLoading(false);
  };

  if (loading) return <div className="p-8 text-gray-400 animate-pulse">Chargement des sources...</div>;
  if (error) return <div className="p-8 text-red-400">{error}</div>;

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-white">Sources de Generation</h1>
        <p className="text-gray-400 text-sm mt-1">Base de donnees organisee pour l'outil de generation d'articles</p>
      </div>

      {/* Global Stats */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
          <StatCard label="Total items" value={fmt(stats.overall.total)} />
          <StatCard label="Base nettoyee" value={fmt(stats.overall.cleaned)} color="text-blue-400" />
          <StatCard label="Base brute" value={fmt(stats.overall.raw)} color="text-gray-400" />
          <StatCard label="Prets a generer" value={fmt(stats.overall.ready)} color="text-emerald-400" />
          <StatCard label="Pays couverts" value={fmt(stats.overall.countries)} color="text-amber-400" />
        </div>
      )}

      {/* Categories Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {categories.map(cat => (
          <button key={cat.slug} onClick={() => { setSelectedCat(cat.slug); setFilterSubCat(''); setFilterTheme(''); setFilterCountry(''); setFilterCleaned(''); setFilterSearch(''); }}
            className={`text-left p-4 rounded-lg border transition-all ${selectedCat === cat.slug ? 'bg-blue-600/20 border-blue-500' : 'bg-gray-800 border-gray-700 hover:border-gray-500'}`}
          >
            <div className="flex items-center gap-2 mb-2">
              <span className="text-xl">{ICONS[cat.icon] || '📁'}</span>
              <h3 className="font-semibold text-white">{cat.name}</h3>
            </div>
            <p className="text-xs text-gray-400 mb-3 line-clamp-2">{cat.description}</p>
            <div className="flex gap-3 text-xs">
              <span className="text-white font-bold">{fmt(cat.total_items)} items</span>
              <span className="text-blue-400">{fmt(cat.cleaned_items)} nettoyes</span>
              {cat.raw_items > 0 && <span className="text-gray-500">{fmt(cat.raw_items)} bruts</span>}
              {cat.ready_items > 0 && <span className="text-emerald-400">{fmt(cat.ready_items)} prets</span>}
            </div>
            {cat.countries > 0 && <div className="text-xs text-gray-500 mt-1">{cat.countries} pays · {cat.themes} themes · {cat.sub_categories} sous-categories</div>}
          </button>
        ))}
      </div>

      {/* Category Detail */}
      {selectedCat && catData && (
        <div className="bg-gray-800 rounded-lg p-4 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-bold text-white">{categories.find(c => c.slug === selectedCat)?.name}</h2>
            <span className="text-sm text-gray-400">{fmt(catData.items.total)} resultats</span>
          </div>

          {/* Filters */}
          <div className="flex flex-wrap gap-2">
            <select value={filterCleaned} onChange={e => setFilterCleaned(e.target.value)} className="bg-gray-700 text-white text-xs rounded px-2 py-1.5 border border-gray-600">
              <option value="">Tout (brut + nettoye)</option>
              <option value="true">Base nettoyee</option>
              <option value="false">Base brute</option>
            </select>
            {catData.themes.length > 1 && (
              <select value={filterTheme} onChange={e => setFilterTheme(e.target.value)} className="bg-gray-700 text-white text-xs rounded px-2 py-1.5 border border-gray-600">
                <option value="">Tous les themes</option>
                {catData.themes.map(t => <option key={t.theme} value={t.theme}>{THEME_LABELS[t.theme] || t.theme} ({t.count})</option>)}
              </select>
            )}
            {catData.countries.length > 1 && (
              <select value={filterCountry} onChange={e => setFilterCountry(e.target.value)} className="bg-gray-700 text-white text-xs rounded px-2 py-1.5 border border-gray-600">
                <option value="">Tous les pays</option>
                {catData.countries.map(c => <option key={c.country_slug} value={c.country_slug}>{c.country} ({c.count})</option>)}
              </select>
            )}
            {catData.sub_categories.length > 1 && (
              <select value={filterSubCat} onChange={e => setFilterSubCat(e.target.value)} className="bg-gray-700 text-white text-xs rounded px-2 py-1.5 border border-gray-600">
                <option value="">Toutes sous-categories</option>
                {catData.sub_categories.map(s => <option key={s.sub_category} value={s.sub_category}>{s.sub_category} ({s.count})</option>)}
              </select>
            )}
            <input type="text" placeholder="Rechercher..." value={filterSearch} onChange={e => setFilterSearch(e.target.value)}
              className="bg-gray-700 text-white text-xs rounded px-2 py-1.5 border border-gray-600 w-48"
            />
          </div>

          {/* Sub-categories sidebar summary */}
          {catData.sub_categories.length > 0 && !filterSubCat && (
            <div className="flex flex-wrap gap-1.5">
              {catData.sub_categories.slice(0, 20).map(s => (
                <button key={s.sub_category} onClick={() => setFilterSubCat(s.sub_category)}
                  className="px-2 py-0.5 bg-gray-700 hover:bg-gray-600 rounded text-xs text-gray-300 transition-colors"
                >
                  {s.sub_category} <span className="text-gray-500">{s.count}</span>
                  {s.raw > 0 && <span className="text-red-400 ml-1">({s.raw} bruts)</span>}
                </button>
              ))}
              {catData.sub_categories.length > 20 && <span className="text-xs text-gray-500 self-center">+{catData.sub_categories.length - 20} autres</span>}
            </div>
          )}

          {/* Items table */}
          {catLoading ? (
            <div className="text-gray-400 animate-pulse py-4">Chargement...</div>
          ) : (
            <div className="overflow-x-auto max-h-[500px] overflow-y-auto">
              <table className="w-full text-sm">
                <thead className="sticky top-0 bg-gray-800">
                  <tr className="text-gray-400 border-b border-gray-700">
                    <th className="text-left py-2 px-2 w-8">#</th>
                    <th className="text-left py-2 px-2">Titre</th>
                    <th className="text-left py-2 px-2">Pays</th>
                    <th className="text-left py-2 px-2">Theme</th>
                    <th className="text-left py-2 px-2">Sous-cat.</th>
                    <th className="text-right py-2 px-2">Mots</th>
                    <th className="text-right py-2 px-2">Score</th>
                    <th className="text-center py-2 px-2">Statut</th>
                  </tr>
                </thead>
                <tbody>
                  {catData.items.data.map((item, i) => (
                    <tr key={item.id} className="border-b border-gray-700/30 hover:bg-gray-700/20">
                      <td className="py-1.5 px-2 text-gray-500 text-xs">{(page - 1) * 50 + i + 1}</td>
                      <td className="py-1.5 px-2 max-w-sm truncate">
                        <button onClick={() => openDetail(item)} className="text-white hover:text-blue-400 text-left transition-colors" title={item.title}>
                          {item.title}
                        </button>
                        {item.source_type === 'template' && item.data_json && (
                          <span className="ml-1 text-xs text-amber-400">[{(item.data_json as {variables?: string[]}).variables?.join(', ')}]</span>
                        )}
                        {item.source_type === 'pillar' && (
                          <span className="ml-1 text-xs bg-emerald-500/20 text-emerald-400 px-1 rounded">pillar</span>
                        )}
                      </td>
                      <td className="py-1.5 px-2 text-gray-300 text-xs">{item.country || '-'}</td>
                      <td className="py-1.5 px-2 text-xs">
                        <span className="px-1.5 py-0.5 bg-blue-500/10 text-blue-400 rounded">{THEME_LABELS[item.theme || ''] || item.theme || '-'}</span>
                      </td>
                      <td className="py-1.5 px-2 text-gray-500 text-xs truncate max-w-[120px]">{item.sub_category || '-'}</td>
                      <td className="py-1.5 px-2 text-right text-xs">{item.word_count > 0 ? fmt(item.word_count) : '-'}</td>
                      <td className="py-1.5 px-2 text-right text-xs font-bold" style={{ color: item.quality_score >= 80 ? '#34d399' : item.quality_score >= 50 ? '#fbbf24' : '#9ca3af' }}>
                        {item.quality_score}
                      </td>
                      <td className="py-1.5 px-2 text-center">
                        <span className={`px-1.5 py-0.5 rounded text-xs ${STATUS_COLORS[item.processing_status] || 'bg-gray-600 text-gray-300'}`}>
                          {item.is_cleaned ? '✓' : '○'} {item.processing_status}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Pagination */}
          {catData.items.last_page > 1 && (
            <div className="flex items-center justify-center gap-2 pt-2">
              <button disabled={page <= 1} onClick={() => handlePageChange(page - 1)} className="px-3 py-1 bg-gray-700 rounded text-sm disabled:opacity-30">Prec.</button>
              <span className="text-sm text-gray-400">Page {page} / {catData.items.last_page}</span>
              <button disabled={page >= catData.items.last_page} onClick={() => handlePageChange(page + 1)} className="px-3 py-1 bg-gray-700 rounded text-sm disabled:opacity-30">Suiv.</button>
            </div>
          )}
        </div>
      )}
      {/* Detail Panel (slide-over) */}
      {(detail || detailLoading) && (
        <div className="fixed inset-0 z-50 flex justify-end" onClick={() => setDetail(null)}>
          <div className="absolute inset-0 bg-black/50" />
          <div className="relative w-full max-w-2xl bg-gray-900 border-l border-gray-700 overflow-y-auto shadow-2xl" onClick={e => e.stopPropagation()}>
            <div className="sticky top-0 bg-gray-900 border-b border-gray-700 p-4 flex justify-between items-center z-10">
              <h3 className="font-bold text-white text-lg truncate pr-4">{detail?.item?.title || 'Chargement...'}</h3>
              <button onClick={() => setDetail(null)} className="text-gray-400 hover:text-white text-xl px-2">✕</button>
            </div>

            {detailLoading ? (
              <div className="p-8 text-gray-400 animate-pulse">Chargement du contenu...</div>
            ) : detail && (
              <div className="p-4 space-y-4">
                {/* Meta info */}
                <div className="grid grid-cols-2 gap-3 text-sm">
                  <div><span className="text-gray-500">Type:</span> <span className="text-white">{detail.item.source_type}</span></div>
                  <div><span className="text-gray-500">Pays:</span> <span className="text-white">{detail.item.country || '-'}</span></div>
                  <div><span className="text-gray-500">Theme:</span> <span className="text-white">{THEME_LABELS[detail.item.theme || ''] || detail.item.theme || '-'}</span></div>
                  <div><span className="text-gray-500">Sous-cat:</span> <span className="text-white">{detail.item.sub_category || '-'}</span></div>
                  <div><span className="text-gray-500">Score:</span> <span className="text-emerald-400 font-bold">{detail.item.quality_score}/100</span></div>
                  <div><span className="text-gray-500">Statut:</span> <span className={`px-1.5 py-0.5 rounded text-xs ${STATUS_COLORS[detail.item.processing_status] || ''}`}>{detail.item.is_cleaned ? '✓ Nettoye' : '○ Brut'} · {detail.item.processing_status}</span></div>
                  {detail.item.word_count > 0 && <div><span className="text-gray-500">Mots:</span> <span className="text-white">{fmt(detail.item.word_count)}</span></div>}
                  {detail.item.used_count > 0 && <div><span className="text-gray-500">Utilise:</span> <span className="text-purple-400">{detail.item.used_count} fois</span></div>}
                </div>

                {/* Source data */}
                {detail.source && (
                  <div className="space-y-3">
                    <hr className="border-gray-700" />

                    {/* Article source */}
                    {detail.source.url && (
                      <div>
                        <span className="text-gray-500 text-xs">URL source:</span>
                        <a href={detail.source.url} target="_blank" rel="noopener noreferrer" className="block text-blue-400 text-sm hover:underline truncate mt-0.5">
                          {detail.source.url}
                        </a>
                      </div>
                    )}

                    {detail.source.source_name && (
                      <div className="text-sm">
                        <span className="text-gray-500">Source:</span> <span className="text-amber-400">{detail.source.source_name}</span>
                        {detail.source.scraped_at && <span className="text-gray-600 ml-2">· scrappe le {detail.source.scraped_at}</span>}
                      </div>
                    )}

                    {/* Q&A specific */}
                    {detail.source.views !== undefined && (
                      <div className="flex gap-4 text-sm">
                        <span className="text-gray-500">Vues: <span className="text-blue-400 font-bold">{fmt(detail.source.views || 0)}</span></span>
                        <span className="text-gray-500">Reponses: <span className="text-white">{fmt(detail.source.replies || 0)}</span></span>
                        {detail.source.city && <span className="text-gray-500">Ville: <span className="text-white">{detail.source.city}</span></span>}
                      </div>
                    )}

                    {detail.source.meta_description && (
                      <div>
                        <span className="text-gray-500 text-xs">Meta description:</span>
                        <p className="text-gray-300 text-sm mt-0.5">{detail.source.meta_description}</p>
                      </div>
                    )}

                    {/* Full content */}
                    {detail.source.content_text && (
                      <div>
                        <span className="text-gray-500 text-xs">Contenu ({fmt(detail.source.word_count || 0)} mots):</span>
                        <div className="mt-1 bg-gray-800 rounded-lg p-3 max-h-[400px] overflow-y-auto">
                          <pre className="text-gray-300 text-sm whitespace-pre-wrap font-sans leading-relaxed">{detail.source.content_text}</pre>
                        </div>
                      </div>
                    )}

                    {!detail.source.content_text && detail.source.url && (
                      <div className="text-gray-500 text-sm italic">Pas de contenu texte stocke. <a href={detail.source.url} target="_blank" rel="noopener noreferrer" className="text-blue-400 hover:underline">Voir sur le site source →</a></div>
                    )}
                  </div>
                )}

                {/* Pillar summary: categories covered, total words, etc. */}
                {detail.item.source_type === 'pillar' && detail.item.data_json && (
                  <div className="space-y-2">
                    <hr className="border-gray-700" />
                    <h4 className="text-white font-semibold text-sm">Fiche Pays — Couverture</h4>
                    <div className="grid grid-cols-2 gap-2 text-sm">
                      {(detail.item.data_json as Record<string, unknown>).categories_count != null && (
                        <div><span className="text-gray-500">Themes couverts:</span> <span className="text-emerald-400 font-bold">{String((detail.item.data_json as Record<string, unknown>).categories_count)}</span></div>
                      )}
                      {(detail.item.data_json as Record<string, unknown>).sources_count != null && (
                        <div><span className="text-gray-500">Articles sources:</span> <span className="text-white">{String((detail.item.data_json as Record<string, unknown>).sources_count)}</span></div>
                      )}
                      {(detail.item.data_json as Record<string, unknown>).total_source_words != null && (
                        <div><span className="text-gray-500">Mots total sources:</span> <span className="text-white">{fmt(Number((detail.item.data_json as Record<string, unknown>).total_source_words))}</span></div>
                      )}
                      {(detail.item.data_json as Record<string, unknown>).continent && (
                        <div><span className="text-gray-500">Continent:</span> <span className="text-white">{String((detail.item.data_json as Record<string, unknown>).continent)}</span></div>
                      )}
                    </div>
                    {Array.isArray((detail.item.data_json as Record<string, unknown>).categories_covered) && (
                      <div className="flex flex-wrap gap-1 mt-1">
                        {((detail.item.data_json as Record<string, unknown>).categories_covered as string[]).map((cat: string) => (
                          <span key={cat} className="px-1.5 py-0.5 bg-blue-500/10 text-blue-400 rounded text-xs">{THEME_LABELS[cat] || cat}</span>
                        ))}
                      </div>
                    )}
                  </div>
                )}

                {/* Pillar article: show all aggregated sources */}
                {detail.pillar_sources && detail.pillar_sources.length > 0 && (
                  <div className="space-y-3">
                    <hr className="border-gray-700" />
                    <h4 className="text-white font-semibold text-sm">{detail.pillar_sources.length} articles sources agrégés</h4>
                    {detail.pillar_sources.map((src, i) => (
                      <div key={src.id} className="bg-gray-800 rounded-lg p-3 border border-gray-700">
                        <div className="flex justify-between items-start mb-1">
                          <span className="text-amber-400 text-xs font-medium">{src.source_name}</span>
                          <span className="text-gray-500 text-xs">{fmt(src.word_count || 0)} mots</span>
                        </div>
                        <h5 className="text-white text-sm font-medium">{src.title}</h5>
                        {src.url && <a href={src.url} target="_blank" rel="noopener noreferrer" className="text-blue-400 text-xs hover:underline truncate block mt-0.5">{src.url}</a>}
                        {src.meta_description && <p className="text-gray-400 text-xs mt-1">{src.meta_description}</p>}
                        {src.content_text && (
                          <details className="mt-2">
                            <summary className="text-blue-400 text-xs cursor-pointer hover:text-blue-300">Voir le contenu</summary>
                            <div className="mt-1 bg-gray-900 rounded p-2 max-h-[200px] overflow-y-auto">
                              <pre className="text-gray-300 text-xs whitespace-pre-wrap font-sans">{src.content_text}</pre>
                            </div>
                          </details>
                        )}
                      </div>
                    ))}
                  </div>
                )}

                {/* Q&A questions for pillar */}
                {detail.qa_questions && detail.qa_questions.length > 0 && (
                  <div className="space-y-2">
                    <hr className="border-gray-700" />
                    <h4 className="text-white font-semibold text-sm">Top questions Q&A ({detail.qa_questions.length})</h4>
                    <div className="max-h-[200px] overflow-y-auto space-y-1">
                      {detail.qa_questions.map(q => (
                        <div key={q.id} className="flex justify-between items-center text-xs py-1 border-b border-gray-800">
                          <a href={q.url} target="_blank" rel="noopener noreferrer" className="text-gray-300 hover:text-white truncate flex-1 mr-2">{q.title}</a>
                          <span className="text-blue-400 whitespace-nowrap">{fmt(q.views)} vues</span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Template data */}
                {detail.item.source_type === 'template' && detail.item.data_json && (
                  <div>
                    <hr className="border-gray-700" />
                    <span className="text-gray-500 text-xs">Template data:</span>
                    <pre className="text-gray-300 text-sm bg-gray-800 rounded p-2 mt-1">{JSON.stringify(detail.item.data_json, null, 2)}</pre>
                  </div>
                )}
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
      <div className={`text-lg font-bold ${color}`}>{value}</div>
    </div>
  );
}
