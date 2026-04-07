import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchStatisticsDatasets,
  fetchStatisticsStats,
  fetchStatisticsThemes,
  fetchStatisticsCoverage,
  researchStatistics,
  researchStatisticsBatch,
  validateStatisticsDataset,
  generateStatisticsArticle,
  generateStatisticsBatch,
  deleteStatisticsDataset,
} from '../../api/contentApi';
import { toast } from '../../components/Toast';

// ── Types ──────────────────────────────────────────────────
interface StatItem {
  value: string;
  label: string;
  year?: string;
  source_name?: string;
  source_url?: string;
  context?: string;
}

interface SourceItem {
  name: string;
  url?: string;
  accessed_at?: string;
}

interface Dataset {
  id: number;
  topic: string;
  theme: string;
  country_code: string | null;
  country_name: string | null;
  title: string;
  summary: string | null;
  stats: StatItem[];
  sources: SourceItem[];
  analysis: Record<string, unknown> | null;
  confidence_score: number;
  source_count: number;
  status: 'draft' | 'validated' | 'generating' | 'published' | 'failed';
  language: string;
  generated_article_id: number | null;
  last_researched_at: string | null;
  created_at: string;
  updated_at: string;
}

interface StatsOverview {
  total: number;
  draft: number;
  validated: number;
  published: number;
  failed: number;
  by_theme: Record<string, number>;
  avg_confidence: number;
  themes: Record<string, { en: string; fr: string }>;
}

interface ThemeInfo {
  key: string;
  label_en: string;
  label_fr: string;
  total: number;
  published: number;
  validated: number;
  draft: number;
}

// ── Constants ──────────────────────────────────────────────
const THEMES: Record<string, { icon: string; label: string }> = {
  expatries:     { icon: '🌍', label: 'Expatries' },
  voyageurs:     { icon: '✈️', label: 'Voyageurs' },
  nomades:       { icon: '💻', label: 'Nomades Digitaux' },
  etudiants:     { icon: '🎓', label: 'Etudiants' },
  investisseurs: { icon: '💰', label: 'Investisseurs' },
};

const STATUS_STYLES: Record<string, { bg: string; text: string; label: string }> = {
  draft:      { bg: 'bg-muted/10',    text: 'text-muted',       label: 'Brouillon' },
  validated:  { bg: 'bg-cyan/10',     text: 'text-cyan',        label: 'Valide' },
  generating: { bg: 'bg-amber/10',    text: 'text-amber',       label: 'En cours' },
  published:  { bg: 'bg-success/10',  text: 'text-success',     label: 'Publie' },
  failed:     { bg: 'bg-danger/10',   text: 'text-danger',      label: 'Echec' },
};

const TABS = ['datasets', 'rechercher', 'generer', 'couverture'] as const;
type Tab = typeof TABS[number];

// ── Top 20 countries for quick research ────────────────────
const TOP_COUNTRIES = [
  { code: 'FR', name: 'France' }, { code: 'US', name: 'United States' }, { code: 'GB', name: 'United Kingdom' },
  { code: 'DE', name: 'Germany' }, { code: 'ES', name: 'Spain' }, { code: 'PT', name: 'Portugal' },
  { code: 'IT', name: 'Italy' }, { code: 'CA', name: 'Canada' }, { code: 'AU', name: 'Australia' },
  { code: 'AE', name: 'United Arab Emirates' }, { code: 'TH', name: 'Thailand' }, { code: 'JP', name: 'Japan' },
  { code: 'SG', name: 'Singapore' }, { code: 'NL', name: 'Netherlands' }, { code: 'CH', name: 'Switzerland' },
  { code: 'BR', name: 'Brazil' }, { code: 'MX', name: 'Mexico' }, { code: 'IN', name: 'India' },
  { code: 'MA', name: 'Morocco' }, { code: 'ZA', name: 'South Africa' },
];

export default function ArtStatistiques() {
  const [tab, setTab] = useState<Tab>('datasets');
  const [datasets, setDatasets] = useState<Dataset[]>([]);
  const [overview, setOverview] = useState<StatsOverview | null>(null);
  const [themes, setThemes] = useState<ThemeInfo[]>([]);
  const [coverage, setCoverage] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);

  // Filters
  const [filterTheme, setFilterTheme] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [filterCountry, setFilterCountry] = useState('');
  const [searchQ, setSearchQ] = useState('');

  // Research
  const [researchTheme, setResearchTheme] = useState('expatries');
  const [researchCountry, setResearchCountry] = useState('');
  const [researchCountryName, setResearchCountryName] = useState('');
  const [researching, setResearching] = useState(false);
  const [researchResult, setResearchResult] = useState<{ stats_found: number; dataset: Dataset } | null>(null);

  // Batch research
  const [batchTheme, setBatchTheme] = useState('expatries');
  const [batchCountries, setBatchCountries] = useState<{ code: string; name: string }[]>([]);
  const [batchRunning, setBatchRunning] = useState(false);

  // Generation
  const [generatingIds, setGeneratingIds] = useState<Set<number>>(new Set());

  // ── Load data ──────────────────────────────────────────────
  const loadDatasets = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = { per_page: 200 };
      if (filterTheme) params.theme = filterTheme;
      if (filterStatus) params.status = filterStatus;
      if (filterCountry) params.country_code = filterCountry;
      if (searchQ) params.search = searchQ;
      const res = await fetchStatisticsDatasets(params);
      const raw = res.data?.data ?? res.data ?? [];
      setDatasets(Array.isArray(raw) ? raw : []);
    } catch {
      toast.error('Erreur chargement datasets');
    } finally {
      setLoading(false);
    }
  }, [filterTheme, filterStatus, filterCountry, searchQ]);

  const loadOverview = useCallback(async () => {
    try {
      const [statsRes, themesRes] = await Promise.all([
        fetchStatisticsStats(),
        fetchStatisticsThemes(),
      ]);
      setOverview(statsRes.data);
      setThemes(Array.isArray(themesRes.data) ? themesRes.data : []);
    } catch { /* silent */ }
  }, []);

  const loadCoverage = useCallback(async () => {
    try {
      const res = await fetchStatisticsCoverage();
      setCoverage(Array.isArray(res.data) ? res.data : []);
    } catch { /* silent */ }
  }, []);

  useEffect(() => { loadDatasets(); }, [loadDatasets]);
  useEffect(() => { loadOverview(); }, [loadOverview]);

  // ── Research ───────────────────────────────────────────────
  const handleResearch = async () => {
    if (!researchCountry) { toast.error('Selectionnez un pays'); return; }
    setResearching(true);
    setResearchResult(null);
    try {
      const res = await researchStatistics({
        theme: researchTheme,
        country_code: researchCountry,
        country_name: researchCountryName,
      });
      setResearchResult(res.data);
      toast.success(`${res.data.stats_found} statistiques trouvees`);
      await loadDatasets();
      await loadOverview();
    } catch (e: any) {
      toast.error(e?.response?.data?.error || 'Erreur recherche');
    } finally {
      setResearching(false);
    }
  };

  const handleBatchResearch = async () => {
    if (batchCountries.length === 0) { toast.error('Selectionnez au moins un pays'); return; }
    setBatchRunning(true);
    try {
      const res = await researchStatisticsBatch({
        theme: batchTheme,
        countries: batchCountries,
      });
      toast.success(`${res.data.queued} recherches lancees, ${res.data.skipped} deja faites`);
      await loadDatasets();
      await loadOverview();
    } catch (e: any) {
      toast.error(e?.response?.data?.error || 'Erreur batch');
    } finally {
      setBatchRunning(false);
    }
  };

  // ── Validate ───────────────────────────────────────────────
  const handleValidate = async (id: number) => {
    try {
      await validateStatisticsDataset(id);
      toast.success('Dataset valide par Claude');
      await loadDatasets();
      await loadOverview();
    } catch (e: any) {
      toast.error(e?.response?.data?.error || 'Erreur validation');
    }
  };

  // ── Generate article ──────────────────────────────────────
  const handleGenerate = async (ds: Dataset) => {
    setGeneratingIds(prev => new Set(prev).add(ds.id));
    try {
      await generateStatisticsArticle(ds.id);
      toast.success(`Article genere: ${ds.title}`);
      await loadDatasets();
      await loadOverview();
    } catch (e: any) {
      toast.error(e?.response?.data?.error || 'Erreur generation');
    } finally {
      setGeneratingIds(prev => { const s = new Set(prev); s.delete(ds.id); return s; });
    }
  };

  const handleBatchGenerate = async (limit: number) => {
    const eligible = datasets.filter(d => d.status === 'validated' || d.status === 'draft');
    if (eligible.length === 0) { toast.error('Aucun dataset eligible'); return; }
    const ids = eligible.slice(0, limit).map(d => d.id);
    try {
      const res = await generateStatisticsBatch(ids);
      toast.success(`${res.data.queued} articles en generation`);
      await loadDatasets();
    } catch (e: any) {
      toast.error(e?.response?.data?.error || 'Erreur batch generation');
    }
  };

  // ── Delete ─────────────────────────────────────────────────
  const handleDelete = async (id: number) => {
    try {
      await deleteStatisticsDataset(id);
      setDatasets(prev => prev.filter(d => d.id !== id));
      toast.success('Dataset supprime');
    } catch {
      toast.error('Erreur suppression');
    }
  };

  // ── Derived ────────────────────────────────────────────────
  const draftCount     = datasets.filter(d => d.status === 'draft').length;
  const validatedCount = datasets.filter(d => d.status === 'validated').length;
  const publishedCount = datasets.filter(d => d.status === 'published').length;
  const countries = [...new Set(datasets.map(d => d.country_code).filter(Boolean))].sort();

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-title font-bold text-white tracking-tight flex items-center gap-3">
            <span className="text-3xl">📊</span>
            Statistiques
          </h1>
          <p className="text-sm text-muted mt-1">
            Recherche, validation et generation d'articles statistiques — 197 pays x 5 themes
          </p>
        </div>
        <button
          onClick={() => { loadDatasets(); loadOverview(); }}
          disabled={loading}
          className="text-xs text-muted hover:text-white px-3 py-1.5 bg-surface2/50 rounded-lg transition-colors disabled:opacity-40"
        >
          {loading ? '...' : '🔄'} Rafraichir
        </button>
      </div>

      {/* Stats cards */}
      <div className="grid grid-cols-5 gap-3">
        {[
          { label: 'Total',     value: overview?.total ?? datasets.length, color: 'text-white' },
          { label: 'Brouillons', value: overview?.draft ?? draftCount,     color: 'text-muted' },
          { label: 'Valides',   value: overview?.validated ?? validatedCount, color: 'text-cyan' },
          { label: 'Publies',   value: overview?.published ?? publishedCount, color: 'text-success' },
          { label: 'Confiance', value: `${overview?.avg_confidence ?? 0}%`,   color: 'text-violet-light' },
        ].map(s => (
          <div key={s.label} className="bg-surface/60 backdrop-blur border border-border/30 rounded-xl p-3 text-center">
            <p className={`text-2xl font-bold ${s.color}`}>{s.value}</p>
            <p className="text-[10px] text-muted uppercase tracking-wider">{s.label}</p>
          </div>
        ))}
      </div>

      {/* Theme breakdown */}
      {themes.length > 0 && (
        <div className="grid grid-cols-5 gap-2">
          {themes.map(t => {
            const info = THEMES[t.key];
            return (
              <button
                key={t.key}
                onClick={() => { setFilterTheme(filterTheme === t.key ? '' : t.key); setTab('datasets'); }}
                className={`bg-surface/40 border rounded-xl p-2 text-center transition-all ${
                  filterTheme === t.key ? 'border-violet/50 bg-violet/10' : 'border-border/20 hover:border-border/40'
                }`}
              >
                <p className="text-lg">{info?.icon ?? '📊'}</p>
                <p className="text-[10px] text-white font-medium">{info?.label ?? t.key}</p>
                <p className="text-[9px] text-muted">{t.published}/{t.total}</p>
              </button>
            );
          })}
        </div>
      )}

      {/* Tabs */}
      <div className="flex gap-1 bg-surface/40 backdrop-blur rounded-xl p-1 border border-border/20">
        {([
          ['datasets',   '📦', 'Datasets'],
          ['rechercher', '🔍', 'Rechercher'],
          ['generer',    '⚡', 'Generer'],
          ['couverture', '🗺️', 'Couverture'],
        ] as [Tab, string, string][]).map(([t, emoji, label]) => (
          <button
            key={t}
            onClick={() => { setTab(t); if (t === 'couverture') loadCoverage(); }}
            className={`flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition-all ${
              tab === t ? 'bg-violet/20 text-violet-light border border-violet/30' : 'text-muted hover:text-white'
            }`}
          >
            <span>{emoji}</span> {label}
          </button>
        ))}
      </div>

      {/* ═══ TAB: DATASETS ═══ */}
      {tab === 'datasets' && (
        <div className="space-y-4">
          {/* Filters */}
          <div className="flex gap-2 flex-wrap">
            <input
              type="text" value={searchQ} onChange={e => setSearchQ(e.target.value)}
              placeholder="Rechercher..."
              className="bg-bg/60 border border-border/40 rounded-xl px-3 py-2 text-white text-sm w-48 focus:outline-none focus:border-violet/50"
            />
            <select value={filterTheme} onChange={e => setFilterTheme(e.target.value)}
              className="bg-bg/60 border border-border/40 rounded-xl px-3 py-2 text-white text-sm">
              <option value="">Tous themes</option>
              {Object.entries(THEMES).map(([k, v]) => <option key={k} value={k}>{v.icon} {v.label}</option>)}
            </select>
            <select value={filterStatus} onChange={e => setFilterStatus(e.target.value)}
              className="bg-bg/60 border border-border/40 rounded-xl px-3 py-2 text-white text-sm">
              <option value="">Tous statuts</option>
              {Object.entries(STATUS_STYLES).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
            </select>
            <select value={filterCountry} onChange={e => setFilterCountry(e.target.value)}
              className="bg-bg/60 border border-border/40 rounded-xl px-3 py-2 text-white text-sm">
              <option value="">Tous pays</option>
              {countries.map(c => <option key={c} value={c!}>{c}</option>)}
            </select>
          </div>

          <p className="text-xs text-muted">{datasets.length} datasets affiches</p>

          {/* Dataset list */}
          <div className="bg-surface/40 backdrop-blur border border-border/20 rounded-2xl overflow-hidden">
            {loading ? (
              <div className="px-5 py-12 text-center">
                <p className="text-3xl mb-2 animate-pulse">📊</p>
                <p className="text-sm text-muted">Chargement...</p>
              </div>
            ) : datasets.length > 0 ? (
              <div className="divide-y divide-border/10 max-h-[600px] overflow-y-auto">
                {datasets.map(ds => {
                  const st = STATUS_STYLES[ds.status] ?? STATUS_STYLES.draft;
                  const themeInfo = THEMES[ds.theme];
                  const isGenerating = generatingIds.has(ds.id);
                  return (
                    <div key={ds.id} className="flex items-center gap-3 px-5 py-3 hover:bg-surface2/20 transition-colors group">
                      <span className="text-lg shrink-0">{themeInfo?.icon ?? '📊'}</span>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm text-white truncate">{ds.title}</p>
                        <div className="flex gap-2 mt-0.5">
                          {ds.country_name && <span className="text-[10px] text-muted">{ds.country_code} {ds.country_name}</span>}
                          <span className="text-[10px] text-muted">{ds.stats?.length ?? 0} stats</span>
                          <span className="text-[10px] text-muted">{ds.source_count} sources</span>
                          {ds.confidence_score > 0 && (
                            <span className={`text-[10px] ${ds.confidence_score >= 70 ? 'text-success' : ds.confidence_score >= 40 ? 'text-amber' : 'text-danger'}`}>
                              {ds.confidence_score}% confiance
                            </span>
                          )}
                        </div>
                      </div>
                      <span className={`shrink-0 px-2.5 py-1 rounded-lg text-[10px] font-semibold ${st.bg} ${st.text} ${ds.status === 'generating' ? 'animate-pulse' : ''}`}>
                        {st.label}
                      </span>
                      <div className="shrink-0 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        {ds.status === 'published' && ds.generated_article_id && (
                          <>
                            <a href={`/content/articles/${ds.generated_article_id}`}
                              className="px-2 py-1 text-[10px] bg-success/20 text-success rounded-lg hover:bg-success/30">
                              Voir article
                            </a>
                          </>
                        )}
                        {ds.status === 'draft' && (
                          <button onClick={() => handleValidate(ds.id)}
                            className="px-2 py-1 text-[10px] bg-cyan/20 text-cyan rounded-lg hover:bg-cyan/30">
                            Valider
                          </button>
                        )}
                        {(ds.status === 'validated' || ds.status === 'draft') && (
                          <button onClick={() => handleGenerate(ds)} disabled={isGenerating}
                            className="px-2 py-1 text-[10px] bg-violet/20 text-violet-light rounded-lg hover:bg-violet/30 disabled:opacity-50">
                            {isGenerating ? '...' : 'Generer'}
                          </button>
                        )}
                        {ds.status === 'failed' && (
                          <button onClick={() => handleGenerate(ds)} disabled={isGenerating}
                            className="px-2 py-1 text-[10px] bg-amber/20 text-amber rounded-lg hover:bg-amber/30 disabled:opacity-50">
                            Reessayer
                          </button>
                        )}
                        <button onClick={() => handleDelete(ds.id)}
                          className="px-2 py-1 text-[10px] text-danger/60 hover:text-danger">
                          x
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : (
              <div className="px-5 py-12 text-center">
                <p className="text-3xl mb-2">📊</p>
                <p className="text-sm text-muted">Aucun dataset. Utilisez l'onglet "Rechercher" pour lancer la recherche de statistiques.</p>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ═══ TAB: RECHERCHER ═══ */}
      {tab === 'rechercher' && (
        <div className="space-y-4">
          {/* Single research */}
          <div className="bg-gradient-to-br from-violet/20 to-violet/5 border border-border/30 rounded-2xl p-6">
            <h3 className="text-sm font-bold text-white mb-3">🔍 Recherche unitaire (Perplexity)</h3>
            <p className="text-xs text-muted mb-4">
              Recherche des statistiques verifiees sur un theme + pays via Perplexity AI.
              Sources: ONU, OCDE, Banque mondiale, Eurostat, offices statistiques nationaux.
            </p>
            <div className="flex gap-3 items-end flex-wrap">
              <div>
                <label className="text-[10px] text-muted uppercase block mb-1">Theme</label>
                <select value={researchTheme} onChange={e => setResearchTheme(e.target.value)}
                  className="bg-bg/60 border border-border/40 rounded-xl px-3 py-2 text-white text-sm">
                  {Object.entries(THEMES).map(([k, v]) => <option key={k} value={k}>{v.icon} {v.label}</option>)}
                </select>
              </div>
              <div>
                <label className="text-[10px] text-muted uppercase block mb-1">Pays</label>
                <select value={researchCountry} onChange={e => {
                  setResearchCountry(e.target.value);
                  const c = TOP_COUNTRIES.find(c => c.code === e.target.value);
                  setResearchCountryName(c?.name ?? '');
                }}
                  className="bg-bg/60 border border-border/40 rounded-xl px-3 py-2 text-white text-sm">
                  <option value="">-- Choisir --</option>
                  {TOP_COUNTRIES.map(c => <option key={c.code} value={c.code}>{c.code} - {c.name}</option>)}
                </select>
              </div>
              <button onClick={handleResearch} disabled={researching || !researchCountry}
                className="px-5 py-2.5 bg-gradient-to-r from-violet to-violet-light text-white text-sm font-semibold rounded-xl shadow-lg shadow-violet/20 hover:shadow-violet/40 transition-all disabled:opacity-50">
                {researching ? '🔄 Recherche en cours...' : '🔍 Rechercher'}
              </button>
            </div>
            {researchResult && (
              <div className="mt-4 bg-bg/40 rounded-xl p-4 border border-border/20">
                <p className="text-sm text-success font-medium">{researchResult.stats_found} statistiques trouvees</p>
                <div className="mt-2 space-y-1 max-h-60 overflow-y-auto">
                  {researchResult.dataset.stats?.map((s, i) => (
                    <div key={i} className="text-xs text-muted flex gap-2">
                      <span className="text-white font-medium shrink-0">{s.value}</span>
                      <span>{s.label}</span>
                      <span className="text-muted/60">({s.year ?? 'N/A'} - {s.source_name ?? 'N/A'})</span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* Batch research */}
          <div className="bg-surface/40 border border-border/20 rounded-2xl p-6">
            <h3 className="text-sm font-bold text-white mb-3">🚀 Recherche par lot (max 20 pays)</h3>
            <p className="text-xs text-muted mb-4">
              Lance la recherche Perplexity pour plusieurs pays en parallele.
              Les pays deja recherches dans les 7 derniers jours sont ignores.
            </p>
            <div className="flex gap-3 items-end mb-4">
              <div>
                <label className="text-[10px] text-muted uppercase block mb-1">Theme</label>
                <select value={batchTheme} onChange={e => setBatchTheme(e.target.value)}
                  className="bg-bg/60 border border-border/40 rounded-xl px-3 py-2 text-white text-sm">
                  {Object.entries(THEMES).map(([k, v]) => <option key={k} value={k}>{v.icon} {v.label}</option>)}
                </select>
              </div>
              <button onClick={handleBatchResearch} disabled={batchRunning || batchCountries.length === 0}
                className="px-4 py-2 bg-violet/20 text-violet-light text-sm rounded-xl border border-violet/20 hover:bg-violet/30 disabled:opacity-50">
                {batchRunning ? '🔄 En cours...' : `Lancer ${batchCountries.length} recherches`}
              </button>
              <button onClick={() => setBatchCountries(TOP_COUNTRIES.slice(0, 20))}
                className="px-3 py-2 text-xs text-muted hover:text-white bg-surface2/30 rounded-lg">
                Top 20
              </button>
              <button onClick={() => setBatchCountries([])}
                className="px-3 py-2 text-xs text-muted hover:text-white bg-surface2/30 rounded-lg">
                Reset
              </button>
            </div>
            <div className="flex flex-wrap gap-1.5">
              {TOP_COUNTRIES.map(c => {
                const selected = batchCountries.some(bc => bc.code === c.code);
                return (
                  <button key={c.code} onClick={() => {
                    setBatchCountries(prev =>
                      selected ? prev.filter(p => p.code !== c.code) : [...prev, c]
                    );
                  }}
                    className={`px-2 py-1 rounded-lg text-[10px] font-medium transition-all ${
                      selected
                        ? 'bg-violet/20 text-violet-light border border-violet/30'
                        : 'bg-bg/40 text-muted border border-border/20 hover:border-border/40'
                    }`}>
                    {c.code}
                  </button>
                );
              })}
            </div>
          </div>

          {/* Info */}
          <div className="bg-surface/40 border border-border/20 rounded-2xl p-6">
            <h3 className="text-sm font-bold text-white mb-3">ℹ️ Comment ca marche</h3>
            <ul className="text-xs text-muted space-y-2">
              <li>1. <strong>Recherche</strong> — Perplexity cherche des stats verifiees (ONU, OCDE, Banque mondiale)</li>
              <li>2. <strong>Validation</strong> — Claude analyse la coherence et attribue un score de confiance</li>
              <li>3. <strong>Generation</strong> — Un article complet est genere avec les stats sourcees</li>
              <li>4. <strong>Publication</strong> — L'article est publie sur le blog en 9 langues</li>
            </ul>
            <div className="mt-3 p-3 bg-amber/5 border border-amber/20 rounded-lg">
              <p className="text-[10px] text-amber">
                Cout: ~$0.005/recherche Perplexity + ~$0.02/validation Claude + cout generation article.
                197 pays x 5 themes = 985 datasets = ~$5 de recherche + ~$20 de validation.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* ═══ TAB: GENERER ═══ */}
      {tab === 'generer' && (
        <div className="space-y-4">
          {validatedCount + draftCount > 0 ? (
            <div className="bg-gradient-to-br from-violet/20 to-violet/5 border border-border/30 rounded-2xl p-6">
              <h3 className="text-sm font-bold text-white mb-2">⚡ Generation par lot</h3>
              <p className="text-xs text-muted mb-4">
                {validatedCount} datasets valides + {draftCount} brouillons prets pour la generation.
              </p>
              <div className="flex gap-3">
                <button onClick={() => handleBatchGenerate(5)}
                  className="px-4 py-2 bg-violet/20 text-violet-light text-sm font-medium rounded-xl border border-violet/20 hover:bg-violet/30">
                  Generer 5
                </button>
                <button onClick={() => handleBatchGenerate(20)}
                  className="px-4 py-2 bg-violet/20 text-violet-light text-sm font-medium rounded-xl border border-violet/20 hover:bg-violet/30">
                  Generer 20
                </button>
                <button onClick={() => handleBatchGenerate(validatedCount + draftCount)}
                  className="px-4 py-2 bg-amber/20 text-amber text-sm font-medium rounded-xl border border-amber/20 hover:bg-amber/30">
                  Tout generer ({validatedCount + draftCount})
                </button>
              </div>
            </div>
          ) : (
            <div className="bg-surface/40 border border-border/20 rounded-2xl p-6 text-center">
              <p className="text-3xl mb-2">✅</p>
              <p className="text-sm text-muted">
                {datasets.length === 0
                  ? 'Lancez une recherche dans l\'onglet "Rechercher" pour commencer.'
                  : 'Tous les datasets ont ete generes !'}
              </p>
            </div>
          )}

          {/* Validate all drafts */}
          {draftCount > 0 && (
            <div className="bg-surface/40 border border-border/20 rounded-2xl p-6">
              <h3 className="text-sm font-bold text-white mb-2">🔬 Valider les brouillons</h3>
              <p className="text-xs text-muted mb-3">
                {draftCount} brouillons n'ont pas encore ete valides par Claude.
                La validation analyse la coherence des sources et attribue un score de confiance.
              </p>
              <button onClick={async () => {
                const drafts = datasets.filter(d => d.status === 'draft');
                for (const d of drafts.slice(0, 10)) {
                  await handleValidate(d.id);
                  await new Promise(r => setTimeout(r, 1500));
                }
              }}
                className="px-4 py-2 bg-cyan/20 text-cyan text-sm rounded-xl border border-cyan/20 hover:bg-cyan/30">
                Valider {Math.min(draftCount, 10)} brouillons
              </button>
            </div>
          )}

          <div className="bg-surface/40 border border-border/20 rounded-2xl p-6">
            <h3 className="text-sm font-bold text-white mb-3">ℹ️ Pipeline de generation</h3>
            <ul className="text-xs text-muted space-y-2">
              <li>1. Le dataset statistique est transforme en brief d'article</li>
              <li>2. L'IA genere un article complet avec les stats sourcees</li>
              <li>3. FAQ automatique basee sur les donnees</li>
              <li>4. Maillage interne automatique vers fiches pays et articles connexes</li>
              <li>5. Publication et traduction en 9 langues</li>
            </ul>
          </div>
        </div>
      )}

      {/* ═══ TAB: COUVERTURE ═══ */}
      {tab === 'couverture' && (
        <div className="space-y-4">
          <div className="bg-surface/40 border border-border/20 rounded-2xl p-6">
            <h3 className="text-sm font-bold text-white mb-3">🗺️ Matrice de couverture pays x themes</h3>
            <p className="text-xs text-muted mb-4">
              Vue d'ensemble de la couverture statistique par pays et theme.
            </p>
            {coverage.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead>
                    <tr className="border-b border-border/20">
                      <th className="text-left py-2 px-3 text-muted">Pays</th>
                      {Object.entries(THEMES).map(([k, v]) => (
                        <th key={k} className="text-center py-2 px-2 text-muted">{v.icon}</th>
                      ))}
                      <th className="text-center py-2 px-2 text-muted">Confiance</th>
                    </tr>
                  </thead>
                  <tbody>
                    {coverage.map(row => (
                      <tr key={row.country_code} className="border-b border-border/10 hover:bg-surface2/10">
                        <td className="py-2 px-3 text-white font-medium">
                          {row.country_code} {row.country_name}
                        </td>
                        {Object.keys(THEMES).map(theme => {
                          const status = row.themes?.[theme];
                          const dot = status === 'published' ? '🟢'
                            : status === 'validated' ? '🔵'
                            : status === 'draft' ? '🟡'
                            : status === 'failed' ? '🔴'
                            : '⚪';
                          return <td key={theme} className="text-center py-2 px-2">{dot}</td>;
                        })}
                        <td className="text-center py-2 px-2">
                          <span className={`${row.avg_confidence >= 70 ? 'text-success' : row.avg_confidence >= 40 ? 'text-amber' : 'text-danger'}`}>
                            {row.avg_confidence}%
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="text-center py-8">
                <p className="text-muted text-sm">Aucune donnee de couverture. Lancez des recherches d'abord.</p>
              </div>
            )}
            <div className="mt-4 flex gap-4 text-[10px] text-muted">
              <span>🟢 Publie</span>
              <span>🔵 Valide</span>
              <span>🟡 Brouillon</span>
              <span>🔴 Echec</span>
              <span>⚪ Non recherche</span>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
