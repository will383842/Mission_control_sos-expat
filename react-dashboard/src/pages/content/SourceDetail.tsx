import { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../../api/client';
import { Modal } from '../../ui/Modal';

// ── Source metadata ────────────────────────────────────────
const SOURCE_META: Record<string, { label: string; icon: string; description: string; accentColor: string }> = {
  'fiche-pays':     { label: 'Fiches Pays',    icon: '🌍', description: "Guides complets d'expatriation par pays", accentColor: 'text-blue-400' },
  'fiche-villes':   { label: 'Fiches Villes',   icon: '🏙️', description: 'Articles dédiés aux villes expat', accentColor: 'text-cyan-400' },
  'qa':             { label: 'Q&A',             icon: '❓', description: 'Questions réelles des expatriés', accentColor: 'text-violet-light' },
  'fiches-pratiques': { label: 'Fiches Pratiques', icon: '📋', description: 'Articles thématiques par catégorie', accentColor: 'text-emerald-400' },
  'temoignages':    { label: 'Témoignages',     icon: '💬', description: "Récits authentiques d'expatriés", accentColor: 'text-pink-400' },
  'annuaires':      { label: 'Annuaires',       icon: '📚', description: 'Répertoires de services par pays', accentColor: 'text-amber-400' },
  'comparatifs':    { label: 'Comparatifs',     icon: '⚖️', description: 'Comparaisons pays, services, coûts', accentColor: 'text-orange-400' },
  'affiliation':    { label: 'Affiliation',     icon: '🔗', description: 'Contenus orientés conversion', accentColor: 'text-yellow-400' },
  'chatters':       { label: 'Chatters',        icon: '💭', description: 'Contenus communautaires chatters', accentColor: 'text-teal-400' },
  'admin-groups':   { label: 'Admin Groups',    icon: '👥', description: 'Sources des groupes administrés', accentColor: 'text-indigo-400' },
  'bloggeurs':      { label: 'Bloggeurs',       icon: '✍️', description: 'Articles bloggeurs partenaires', accentColor: 'text-rose-400' },
  'avocats':        { label: 'Avocats',         icon: '⚖️', description: 'Recrutement avocats prestataires', accentColor: 'text-slate-300' },
  'expats-aidants': { label: 'Expats Aidants',  icon: '🧳', description: 'Recrutement expats aidants prestataires', accentColor: 'text-sky-400' },
  'besoins-reels':  { label: 'Besoins Réels',   icon: '🎯', description: 'Longues traînes, intentions de recherche', accentColor: 'text-lime-400' },
  'art-mots-cles':  { label: 'Art Mots Clés',   icon: '🔑', description: 'Articles mots-clés fort volume', accentColor: 'text-violet-400' },
  'longues-traines':{ label: 'Longues Traînes', icon: '📐', description: 'Articles longue traîne qualifiés', accentColor: 'text-lime-400' },
  'brand-content':  { label: 'Brand Content',   icon: '🏷️', description: 'Contenus de marque SOS-Expat', accentColor: 'text-amber-400' },
};

// ── Types ──────────────────────────────────────────────────
interface SourceItem {
  id: number;
  source_type: string;
  input_quality: 'full_content' | 'title_only' | 'structured' | null;
  title: string;
  country: string | null;
  theme: string | null;
  sub_category: string | null;
  language: string;
  word_count: number;
  quality_score: number;
  is_cleaned: boolean;
  processing_status: string;
  used_count: number;
}

interface SubCategory { sub_category: string; count: number }
interface CountryItem  { country: string; country_slug: string; count: number }
interface ThemeItem    { theme: string; count: number }

interface CategoryData {
  items: { data: SourceItem[]; total: number; current_page: number; last_page: number };
  sub_categories: SubCategory[];
  countries: CountryItem[];
  themes: ThemeItem[];
}

interface ItemDetail {
  item: SourceItem;
  source: {
    url?: string; content_text?: string; word_count?: number; meta_description?: string;
    source_name?: string; scraped_at?: string; views?: number; replies?: number; city?: string;
  } | null;
  pillar_sources?: { id: number; title: string; url?: string; source_name?: string; word_count?: number; meta_description?: string; content_text?: string }[];
  qa_questions?: { id: number; title: string; url: string; views: number }[];
}

const THEME_LABELS: Record<string, string> = {
  visa: 'Visa', emploi: 'Emploi', logement: 'Logement', sante: 'Santé',
  banque: 'Banque', education: 'Education', transport: 'Transport', telecom: 'Telecom',
  fiscalite: 'Fiscalité', retraite: 'Retraite', famille: 'Famille', cout_vie: 'Coût de vie',
  general: 'Général', autre: 'Autre',
};

const STATUS_COLORS: Record<string, string> = {
  raw:     'bg-surface2 text-t3',
  cleaned: 'bg-blue-500/20 text-blue-400',
  ready:   'bg-emerald-500/20 text-emerald-400',
  used:    'bg-violet/20 text-violet-light',
};

function fmt(n: number): string { return n.toLocaleString('fr-FR'); }

// ── Component ──────────────────────────────────────────────
export default function SourceDetail() {
  const { sourceType } = useParams<{ sourceType: string }>();
  const navigate = useNavigate();
  const meta = SOURCE_META[sourceType ?? ''];

  const [catData, setCatData] = useState<CategoryData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);

  // Filters
  const [filterCleaned, setFilterCleaned]   = useState('');
  const [filterTheme,   setFilterTheme]     = useState('');
  const [filterCountry, setFilterCountry]   = useState('');
  const [filterSubCat,  setFilterSubCat]    = useState('');
  const [filterSearch,  setFilterSearch]    = useState('');

  // Detail drawer
  const [detail, setDetail]           = useState<ItemDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);

  const loadData = useCallback(async (p = 1) => {
    if (!sourceType) return;
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams();
      params.set('page', String(p));
      if (filterCleaned) params.set('cleaned', filterCleaned);
      if (filterTheme)   params.set('theme', filterTheme);
      if (filterCountry) params.set('country_slug', filterCountry);
      if (filterSubCat)  params.set('sub_category', filterSubCat);
      if (filterSearch)  params.set('search', filterSearch);
      const res = await api.get(`/generation-sources/${sourceType}/items?${params}`);
      setCatData(res.data);
    } catch {
      setError('Impossible de charger cette source. Vérifiez que le backend est démarré.');
    } finally {
      setLoading(false);
    }
  }, [sourceType, filterCleaned, filterTheme, filterCountry, filterSubCat, filterSearch]);

  useEffect(() => { setPage(1); loadData(1); }, [loadData]);

  const handlePageChange = (p: number) => { setPage(p); loadData(p); };

  const openDetail = async (item: SourceItem) => {
    setDetailLoading(true);
    setDetail(null);
    try {
      const res = await api.get(`/generation-sources/items/${item.id}`);
      setDetail(res.data);
    } catch {
      setDetail({ item, source: null });
    }
    setDetailLoading(false);
  };

  // ── Not found ──
  if (!meta) {
    return (
      <div className="p-8 text-center">
        <p className="text-t3">Source inconnue : <code className="text-t1">{sourceType}</code></p>
        <button onClick={() => navigate('/content/sources')} className="mt-4 text-violet-light hover:underline text-sm">
          ← Retour aux sources
        </button>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">

      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm text-t3">
        <button onClick={() => navigate('/content/sources')} className="hover:text-t1 transition-colors">
          Sources de génération
        </button>
        <span>/</span>
        <span className="text-t1 font-medium">{meta.label}</span>
      </div>

      {/* Header */}
      <div className="flex items-start justify-between">
        <div className="flex items-center gap-4">
          <span className="text-4xl">{meta.icon}</span>
          <div>
            <h1 className="text-2xl font-bold text-t1">{meta.label}</h1>
            <p className="text-t3 text-sm mt-0.5">{meta.description}</p>
          </div>
        </div>
        {catData && (
          <div className="text-right flex-shrink-0">
            <p className={`text-2xl font-bold ${meta.accentColor}`}>{fmt(catData.items.total)}</p>
            <p className="text-xs text-t3">items trouvés</p>
          </div>
        )}
      </div>

      {/* Filters */}
      <div className="bg-surface border border-border rounded-xl p-4">
        <div className="flex flex-wrap gap-2">
          <select
            value={filterCleaned}
            onChange={e => setFilterCleaned(e.target.value)}
            className="bg-surface2 text-t1 text-sm rounded-lg px-3 py-2 border border-border focus:outline-none focus:border-violet"
          >
            <option value="">Tout (brut + nettoyé)</option>
            <option value="true">Base nettoyée</option>
            <option value="false">Base brute</option>
          </select>

          {(catData?.themes?.length ?? 0) > 1 && (
            <select
              value={filterTheme}
              onChange={e => setFilterTheme(e.target.value)}
              className="bg-surface2 text-t1 text-sm rounded-lg px-3 py-2 border border-border focus:outline-none focus:border-violet"
            >
              <option value="">Tous les thèmes</option>
              {catData!.themes.map(t => (
                <option key={t.theme} value={t.theme}>
                  {THEME_LABELS[t.theme] ?? t.theme} ({t.count})
                </option>
              ))}
            </select>
          )}

          {(catData?.countries?.length ?? 0) > 1 && (
            <select
              value={filterCountry}
              onChange={e => setFilterCountry(e.target.value)}
              className="bg-surface2 text-t1 text-sm rounded-lg px-3 py-2 border border-border focus:outline-none focus:border-violet"
            >
              <option value="">Tous les pays</option>
              {catData!.countries.map(c => (
                <option key={c.country_slug} value={c.country_slug}>
                  {c.country} ({c.count})
                </option>
              ))}
            </select>
          )}

          {(catData?.sub_categories?.length ?? 0) > 1 && (
            <select
              value={filterSubCat}
              onChange={e => setFilterSubCat(e.target.value)}
              className="bg-surface2 text-t1 text-sm rounded-lg px-3 py-2 border border-border focus:outline-none focus:border-violet"
            >
              <option value="">Toutes sous-catégories</option>
              {catData!.sub_categories.map(s => (
                <option key={s.sub_category} value={s.sub_category}>
                  {s.sub_category} ({s.count})
                </option>
              ))}
            </select>
          )}

          <input
            type="text"
            placeholder="Rechercher..."
            value={filterSearch}
            onChange={e => setFilterSearch(e.target.value)}
            className="bg-surface2 text-t1 text-sm rounded-lg px-3 py-2 border border-border focus:outline-none focus:border-violet w-52"
          />

          {(filterCleaned || filterTheme || filterCountry || filterSubCat || filterSearch) && (
            <button
              onClick={() => { setFilterCleaned(''); setFilterTheme(''); setFilterCountry(''); setFilterSubCat(''); setFilterSearch(''); }}
              className="text-xs text-t3 hover:text-t1 px-3 py-2 border border-border rounded-lg transition-colors"
            >
              Effacer filtres
            </button>
          )}
        </div>

        {/* Sub-category tags */}
        {(catData?.sub_categories?.length ?? 0) > 0 && !filterSubCat && (
          <div className="flex flex-wrap gap-1.5 mt-3">
            {catData!.sub_categories.slice(0, 24).map(s => (
              <button
                key={s.sub_category}
                onClick={() => setFilterSubCat(s.sub_category)}
                className="px-2 py-0.5 bg-surface2 hover:bg-border rounded text-xs text-t3 hover:text-t1 transition-colors"
              >
                {s.sub_category} <span className="text-t3/60">{s.count}</span>
              </button>
            ))}
            {catData!.sub_categories.length > 24 && (
              <span className="text-xs text-t3 self-center">+{catData!.sub_categories.length - 24} autres</span>
            )}
          </div>
        )}
      </div>

      {/* Table */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        {loading ? (
          <div className="p-8 text-t3 text-sm animate-pulse text-center">Chargement...</div>
        ) : error ? (
          <div className="p-8 text-center">
            <p className="text-red-400 text-sm">{error}</p>
            <button onClick={() => loadData(page)} className="mt-3 text-xs text-violet-light hover:underline">
              Réessayer
            </button>
          </div>
        ) : !catData || catData.items.data.length === 0 ? (
          <div className="p-8 text-center">
            <p className="text-t3 text-sm">Aucun item trouvé pour cette source.</p>
            <p className="text-t3 text-xs mt-1">Le backend n'a peut-être pas encore de données pour ce type.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-t3 text-xs uppercase tracking-wider border-b border-border bg-surface2/40">
                  <th className="text-left py-3 px-4 w-8">#</th>
                  <th className="text-left py-3 px-4">Titre</th>
                  <th className="text-left py-3 px-4">Pays</th>
                  <th className="text-left py-3 px-4">Thème</th>
                  <th className="text-right py-3 px-4">Mots</th>
                  <th className="text-right py-3 px-4">Score</th>
                  <th className="text-center py-3 px-4">Statut</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/40">
                {catData.items.data.map((item, i) => (
                  <tr key={item.id} className="hover:bg-surface2/30 transition-colors">
                    <td className="py-2.5 px-4 text-t3 text-xs">
                      {(page - 1) * 50 + i + 1}
                    </td>
                    <td className="py-2.5 px-4 max-w-sm">
                      <button
                        onClick={() => openDetail(item)}
                        className="text-t1 hover:text-violet-light text-left transition-colors line-clamp-1"
                        title={item.title}
                      >
                        {item.title}
                      </button>
                      {item.sub_category && (
                        <p className="text-[10px] text-t3 mt-0.5">{item.sub_category}</p>
                      )}
                    </td>
                    <td className="py-2.5 px-4 text-t2 text-xs whitespace-nowrap">
                      {item.country ?? <span className="text-t3">—</span>}
                    </td>
                    <td className="py-2.5 px-4">
                      {item.theme ? (
                        <span className="px-2 py-0.5 bg-blue-500/10 text-blue-400 rounded text-xs">
                          {THEME_LABELS[item.theme] ?? item.theme}
                        </span>
                      ) : (
                        <span className="text-t3 text-xs">—</span>
                      )}
                    </td>
                    <td className="py-2.5 px-4 text-right text-xs text-t2">
                      {item.word_count > 0 ? fmt(item.word_count) : <span className="text-t3">—</span>}
                    </td>
                    <td className="py-2.5 px-4 text-right">
                      <span
                        className="text-xs font-bold"
                        style={{
                          color: item.quality_score >= 80 ? '#34d399'
                               : item.quality_score >= 50 ? '#fbbf24'
                               : '#6b7280',
                        }}
                      >
                        {item.quality_score}
                      </span>
                    </td>
                    <td className="py-2.5 px-4 text-center">
                      <span className={`px-2 py-0.5 rounded text-xs ${STATUS_COLORS[item.processing_status] ?? 'bg-surface2 text-t3'}`}>
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
        {catData && catData.items.last_page > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t border-border">
            <span className="text-xs text-t3">
              Page {page} sur {catData.items.last_page} · {fmt(catData.items.total)} résultats
            </span>
            <div className="flex gap-2">
              <button
                disabled={page <= 1}
                onClick={() => handlePageChange(page - 1)}
                className="px-3 py-1.5 bg-surface2 hover:bg-border text-t2 text-xs rounded-lg disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
              >
                ← Préc.
              </button>
              <button
                disabled={page >= catData.items.last_page}
                onClick={() => handlePageChange(page + 1)}
                className="px-3 py-1.5 bg-surface2 hover:bg-border text-t2 text-xs rounded-lg disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
              >
                Suiv. →
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Detail drawer */}
      <Modal
        open={!!detail || detailLoading}
        onClose={() => setDetail(null)}
        title={detail?.item?.title ?? 'Chargement...'}
        size="xl"
        placement="right"
      >
        {detailLoading ? (
          <div className="p-8 text-t3 text-sm animate-pulse">Chargement...</div>
        ) : detail && (
          <div className="space-y-5">
                {/* Meta grid */}
                <div className="grid grid-cols-2 gap-3 text-sm">
                  {[
                    ['Type', detail.item.source_type],
                    ['Qualité source', detail.item.input_quality === 'full_content' ? '📄 Contenu complet'
                      : detail.item.input_quality === 'structured' ? '🗂 Structuré'
                      : detail.item.input_quality === 'title_only' ? '📝 Titre seul'
                      : '—'],
                    ['Pays', detail.item.country ?? '—'],
                    ['Thème', THEME_LABELS[detail.item.theme ?? ''] ?? detail.item.theme ?? '—'],
                    ['Sous-catégorie', detail.item.sub_category ?? '—'],
                    ['Score qualité', `${detail.item.quality_score}/100`],
                    ['Statut', `${detail.item.is_cleaned ? '✓ Nettoyé' : '○ Brut'} · ${detail.item.processing_status}`],
                    ...(detail.item.word_count > 0 ? [['Mots', fmt(detail.item.word_count)]] : []),
                    ...(detail.item.used_count > 0 ? [['Utilisé', `${detail.item.used_count} fois`]] : []),
                  ].map(([k, v]) => (
                    <div key={k}>
                      <span className="text-t3 text-xs">{k}</span>
                      <p className="text-t1 text-sm font-medium mt-0.5">{v}</p>
                    </div>
                  ))}
                </div>

                {detail.source && (
                  <>
                    <div className="border-t border-border" />

                    {detail.source.url && (
                      <div>
                        <p className="text-t3 text-xs mb-1">URL source</p>
                        <a
                          href={detail.source.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-violet-light text-sm hover:underline break-all"
                        >
                          {detail.source.url}
                        </a>
                      </div>
                    )}

                    {detail.source.source_name && (
                      <div className="flex items-center gap-3 text-sm">
                        <span className="text-t3">Source :</span>
                        <span className="text-amber-400 font-medium">{detail.source.source_name}</span>
                        {detail.source.scraped_at && (
                          <span className="text-t3 text-xs">· {detail.source.scraped_at}</span>
                        )}
                      </div>
                    )}

                    {detail.source.views !== undefined && (
                      <div className="flex gap-4 text-sm">
                        <span className="text-t3">Vues : <span className="text-blue-400 font-bold">{fmt(detail.source.views ?? 0)}</span></span>
                        <span className="text-t3">Réponses : <span className="text-t1">{fmt(detail.source.replies ?? 0)}</span></span>
                        {detail.source.city && <span className="text-t3">Ville : <span className="text-t1">{detail.source.city}</span></span>}
                      </div>
                    )}

                    {detail.source.meta_description && (
                      <div>
                        <p className="text-t3 text-xs mb-1">Meta description</p>
                        <p className="text-t2 text-sm">{detail.source.meta_description}</p>
                      </div>
                    )}

                    {detail.source.content_text && (
                      <div>
                        <p className="text-t3 text-xs mb-1">
                          Contenu ({fmt(detail.source.word_count ?? 0)} mots)
                        </p>
                        <div className="bg-surface2 rounded-lg p-3 max-h-80 overflow-y-auto">
                          <pre className="text-t2 text-xs whitespace-pre-wrap font-sans leading-relaxed">
                            {detail.source.content_text}
                          </pre>
                        </div>
                      </div>
                    )}
                  </>
                )}

                {/* Pillar sources */}
                {detail.pillar_sources && detail.pillar_sources.length > 0 && (
                  <>
                    <div className="border-t border-border" />
                    <h4 className="text-t1 font-semibold text-sm">
                      {detail.pillar_sources.length} articles sources agrégés
                    </h4>
                    {detail.pillar_sources.map(src => (
                      <div key={src.id} className="bg-surface2 rounded-lg p-3 border border-border space-y-1">
                        <div className="flex justify-between items-center">
                          <span className="text-amber-400 text-xs font-medium">{src.source_name}</span>
                          <span className="text-t3 text-xs">{fmt(src.word_count ?? 0)} mots</span>
                        </div>
                        <p className="text-t1 text-sm font-medium">{src.title}</p>
                        {src.url && (
                          <a href={src.url} target="_blank" rel="noopener noreferrer"
                            className="text-violet-light text-xs hover:underline truncate block">
                            {src.url}
                          </a>
                        )}
                        {src.meta_description && (
                          <p className="text-t3 text-xs">{src.meta_description}</p>
                        )}
                        {src.content_text && (
                          <details className="mt-1">
                            <summary className="text-violet-light text-xs cursor-pointer hover:text-white">
                              Voir le contenu
                            </summary>
                            <div className="mt-1 bg-surface rounded p-2 max-h-40 overflow-y-auto">
                              <pre className="text-t2 text-xs whitespace-pre-wrap font-sans">{src.content_text}</pre>
                            </div>
                          </details>
                        )}
                      </div>
                    ))}
                  </>
                )}

                {/* Q&A questions */}
                {detail.qa_questions && detail.qa_questions.length > 0 && (
                  <>
                    <div className="border-t border-border" />
                    <h4 className="text-t1 font-semibold text-sm">
                      Top questions ({detail.qa_questions.length})
                    </h4>
                    <div className="max-h-48 overflow-y-auto space-y-1">
                      {detail.qa_questions.map(q => (
                        <div key={q.id} className="flex justify-between items-center text-xs py-1.5 border-b border-border/40">
                          <a href={q.url} target="_blank" rel="noopener noreferrer"
                            className="text-t2 hover:text-t1 truncate flex-1 mr-2">
                            {q.title}
                          </a>
                          <span className="text-blue-400 whitespace-nowrap">{fmt(q.views)} vues</span>
                        </div>
                      ))}
                    </div>
                  </>
                )}
          </div>
        )}
      </Modal>
    </div>
  );
}
