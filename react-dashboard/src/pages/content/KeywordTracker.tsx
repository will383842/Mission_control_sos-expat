import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  fetchKeywords,
  fetchKeywordGaps,
  fetchKeywordCannibalization,
} from '../../api/contentApi';
import type { KeywordTracking, KeywordGap, KeywordCannibalization, KeywordType, PaginatedResponse } from '../../types/content';

// ── Constants ───────────────────────────────────────────────
type TabKey = 'all' | 'long_tail' | 'cannibalization' | 'gaps';

const TABS: { key: TabKey; label: string }[] = [
  { key: 'all', label: 'Mots-cles' },
  { key: 'long_tail', label: 'Longue traine' },
  { key: 'cannibalization', label: 'Cannibalisation' },
  { key: 'gaps', label: 'Gaps' },
];

const TYPE_COLORS: Record<KeywordType, string> = {
  primary: 'bg-violet/20 text-violet-light',
  secondary: 'bg-blue-500/20 text-blue-400',
  long_tail: 'bg-cyan/20 text-cyan',
  lsi: 'bg-amber/20 text-amber',
  paa: 'bg-orange-500/20 text-orange-400',
  semantic: 'bg-success/20 text-success',
};

const TYPE_LABELS: Record<KeywordType, string> = {
  primary: 'Principal',
  secondary: 'Secondaire',
  long_tail: 'Longue traine',
  lsi: 'LSI',
  paa: 'PAA',
  semantic: 'Semantique',
};

const TREND_ICONS: Record<string, { icon: string; color: string }> = {
  rising: { icon: '/\\', color: 'text-success' },
  stable: { icon: '--', color: 'text-muted' },
  declining: { icon: '\\/', color: 'text-danger' },
};

const TYPE_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'Tous les types' },
  { value: 'primary', label: 'Principal' },
  { value: 'secondary', label: 'Secondaire' },
  { value: 'long_tail', label: 'Longue traine' },
  { value: 'lsi', label: 'LSI' },
  { value: 'paa', label: 'PAA' },
  { value: 'semantic', label: 'Semantique' },
];

const LANGUAGE_OPTIONS = [
  { value: '', label: 'Toutes les langues' },
  { value: 'fr', label: 'Francais' },
  { value: 'en', label: 'English' },
  { value: 'de', label: 'Deutsch' },
  { value: 'es', label: 'Espanol' },
];

const PRIORITY_COLORS: Record<string, string> = {
  high: 'bg-danger/20 text-danger',
  medium: 'bg-amber/20 text-amber',
  low: 'bg-muted/20 text-muted',
};

const SEVERITY_COLORS: Record<string, string> = {
  high: 'bg-danger/20 text-danger',
  medium: 'bg-amber/20 text-amber',
  low: 'bg-muted/20 text-muted',
};

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

type SortField = 'keyword' | 'type' | 'language' | 'search_volume_estimate' | 'difficulty_estimate' | 'articles_using_count';
type SortDir = 'asc' | 'desc';

// ── Component ───────────────────────────────────────────────
export default function KeywordTracker() {
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState<TabKey>('all');

  // Keywords data
  const [keywords, setKeywords] = useState<KeywordTracking[]>([]);
  const [keywordsLoading, setKeywordsLoading] = useState(true);
  const [keywordsPagination, setKeywordsPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [filterType, setFilterType] = useState('');
  const [filterLanguage, setFilterLanguage] = useState('');
  const [filterCountry, setFilterCountry] = useState('');
  const [filterSearch, setFilterSearch] = useState('');
  const [sortField, setSortField] = useState<SortField>('keyword');
  const [sortDir, setSortDir] = useState<SortDir>('asc');

  // Gaps data
  const [gaps, setGaps] = useState<KeywordGap[]>([]);
  const [gapsLoading, setGapsLoading] = useState(false);

  // Cannibalization data
  const [cannibalization, setCannibalization] = useState<KeywordCannibalization[]>([]);
  const [canniLoading, setCanniLoading] = useState(false);

  const loadKeywords = useCallback(async (page = 1, typeOverride?: string) => {
    setKeywordsLoading(true);
    try {
      const params: Record<string, string | number> = { page };
      const type = typeOverride ?? filterType;
      if (type) params.type = type;
      if (filterLanguage) params.language = filterLanguage;
      if (filterCountry) params.country = filterCountry;
      if (filterSearch) params.search = filterSearch;
      const res = await fetchKeywords(params);
      const data = res.data as unknown as PaginatedResponse<KeywordTracking>;
      setKeywords(data.data);
      setKeywordsPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
    } catch {
      // silently handled
    } finally {
      setKeywordsLoading(false);
    }
  }, [filterType, filterLanguage, filterCountry, filterSearch]);

  const loadGaps = useCallback(async () => {
    setGapsLoading(true);
    try {
      const params: Record<string, string> = {};
      if (filterLanguage) params.language = filterLanguage;
      if (filterCountry) params.country = filterCountry;
      const res = await fetchKeywordGaps(params);
      setGaps(res.data as unknown as KeywordGap[]);
    } catch {
      // silently handled
    } finally {
      setGapsLoading(false);
    }
  }, [filterLanguage, filterCountry]);

  const loadCannibalization = useCallback(async () => {
    setCanniLoading(true);
    try {
      const res = await fetchKeywordCannibalization();
      setCannibalization(res.data as unknown as KeywordCannibalization[]);
    } catch {
      // silently handled
    } finally {
      setCanniLoading(false);
    }
  }, []);

  useEffect(() => {
    if (activeTab === 'all') loadKeywords(1);
    else if (activeTab === 'long_tail') loadKeywords(1, 'long_tail');
    else if (activeTab === 'gaps') loadGaps();
    else if (activeTab === 'cannibalization') loadCannibalization();
  }, [activeTab, loadKeywords, loadGaps, loadCannibalization]);

  const handleSort = (field: SortField) => {
    if (sortField === field) {
      setSortDir(prev => prev === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortDir('asc');
    }
  };

  const sortedKeywords = [...keywords].sort((a, b) => {
    const av = a[sortField];
    const bv = b[sortField];
    if (av == null && bv == null) return 0;
    if (av == null) return 1;
    if (bv == null) return -1;
    const cmp = typeof av === 'string' ? av.localeCompare(bv as string) : (av as number) - (bv as number);
    return sortDir === 'asc' ? cmp : -cmp;
  });

  // Stats
  const byType = keywords.reduce<Record<string, number>>((acc, k) => {
    acc[k.type] = (acc[k.type] || 0) + 1;
    return acc;
  }, {});

  const byLang = keywords.reduce<Record<string, number>>((acc, k) => {
    acc[k.language] = (acc[k.language] || 0) + 1;
    return acc;
  }, {});

  const SortHeader = ({ field, label }: { field: SortField; label: string }) => (
    <th
      className="pb-3 pr-4 cursor-pointer hover:text-white transition-colors"
      onClick={() => handleSort(field)}
    >
      <span className="inline-flex items-center gap-1">
        {label}
        {sortField === field && <span className="text-violet">{sortDir === 'asc' ? '↑' : '↓'}</span>}
      </span>
    </th>
  );

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <h2 className="font-title text-2xl font-bold text-white">Mots-cles</h2>

      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Total mots-cles</span>
          <p className="text-2xl font-bold text-white mt-2">{keywordsPagination.total}</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Par type</span>
          <div className="flex flex-wrap gap-1 mt-2">
            {Object.entries(byType).map(([type, count]) => (
              <span key={type} className={`px-1.5 py-0.5 rounded text-[10px] font-medium ${TYPE_COLORS[type as KeywordType] || 'text-muted'}`}>
                {TYPE_LABELS[type as KeywordType] || type}: {count}
              </span>
            ))}
          </div>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Par langue</span>
          <div className="flex flex-wrap gap-1 mt-2">
            {Object.entries(byLang).map(([lang, count]) => (
              <span key={lang} className="px-1.5 py-0.5 rounded text-[10px] bg-surface2 text-muted font-medium uppercase">
                {lang}: {count}
              </span>
            ))}
          </div>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Cannibalisation</span>
          <p className="text-2xl font-bold text-danger mt-2">{cannibalization.length}</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        {TABS.map(tab => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`px-4 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px ${
              activeTab === tab.key
                ? 'text-violet-light border-violet'
                : 'text-muted hover:text-white border-transparent'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Content per tab */}
      {(activeTab === 'all' || activeTab === 'long_tail') && (
        <div className="space-y-4">
          {/* Filters */}
          {activeTab === 'all' && (
            <div className="flex items-center gap-3 flex-wrap">
              <select value={filterType} onChange={e => setFilterType(e.target.value)} className={inputClass}>
                {TYPE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
              </select>
              <select value={filterLanguage} onChange={e => setFilterLanguage(e.target.value)} className={inputClass}>
                {LANGUAGE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
              </select>
              <input type="text" placeholder="Pays..." value={filterCountry} onChange={e => setFilterCountry(e.target.value)} className={inputClass + ' w-32'} />
              <input type="text" placeholder="Rechercher..." value={filterSearch} onChange={e => setFilterSearch(e.target.value)} className={inputClass + ' w-48'} />
            </div>
          )}

          {/* Table */}
          <div className="bg-surface border border-border rounded-xl p-5">
            {keywordsLoading ? (
              <div className="space-y-3">
                {[1, 2, 3, 4, 5].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-lg h-10" />)}
              </div>
            ) : sortedKeywords.length === 0 ? (
              <p className="text-center py-10 text-muted text-sm">Aucun mot-cle trouve</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                      <SortHeader field="keyword" label="Mot-cle" />
                      <SortHeader field="type" label="Type" />
                      <SortHeader field="language" label="Langue" />
                      <SortHeader field="search_volume_estimate" label="Volume" />
                      <SortHeader field="difficulty_estimate" label="Difficulte" />
                      <th className="pb-3 pr-4">Tendance</th>
                      <SortHeader field="articles_using_count" label="Articles" />
                    </tr>
                  </thead>
                  <tbody>
                    {sortedKeywords.map(kw => (
                      <tr key={kw.id} className="border-b border-border/50 hover:bg-surface2/50 transition-colors">
                        <td className="py-3 pr-4 text-white font-medium">{kw.keyword}</td>
                        <td className="py-3 pr-4">
                          <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${TYPE_COLORS[kw.type]}`}>
                            {TYPE_LABELS[kw.type]}
                          </span>
                        </td>
                        <td className="py-3 pr-4 text-muted uppercase">{kw.language}</td>
                        <td className="py-3 pr-4 text-white">{kw.search_volume_estimate ?? '-'}</td>
                        <td className="py-3 pr-4">
                          {kw.difficulty_estimate != null ? (
                            <span className={`text-xs ${kw.difficulty_estimate >= 70 ? 'text-danger' : kw.difficulty_estimate >= 40 ? 'text-amber' : 'text-success'}`}>
                              {kw.difficulty_estimate}/100
                            </span>
                          ) : '-'}
                        </td>
                        <td className="py-3 pr-4">
                          {kw.trend ? (
                            <span className={`text-xs font-mono ${TREND_ICONS[kw.trend]?.color || 'text-muted'}`}>
                              {TREND_ICONS[kw.trend]?.icon || kw.trend}
                            </span>
                          ) : '-'}
                        </td>
                        <td className="py-3 pr-4 text-white">{kw.articles_using_count}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {keywordsPagination.last_page > 1 && (
              <div className="flex items-center justify-between mt-4 pt-4 border-t border-border">
                <span className="text-xs text-muted">{keywordsPagination.total} mots-cles</span>
                <div className="flex gap-2">
                  <button
                    onClick={() => loadKeywords(keywordsPagination.current_page - 1, activeTab === 'long_tail' ? 'long_tail' : undefined)}
                    disabled={keywordsPagination.current_page <= 1}
                    className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40"
                  >
                    Precedent
                  </button>
                  <span className="px-3 py-1 text-xs text-muted">{keywordsPagination.current_page} / {keywordsPagination.last_page}</span>
                  <button
                    onClick={() => loadKeywords(keywordsPagination.current_page + 1, activeTab === 'long_tail' ? 'long_tail' : undefined)}
                    disabled={keywordsPagination.current_page >= keywordsPagination.last_page}
                    className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40"
                  >
                    Suivant
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {activeTab === 'cannibalization' && (
        <div className="bg-surface border border-border rounded-xl p-5">
          {canniLoading ? (
            <div className="space-y-3">
              {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-lg h-16" />)}
            </div>
          ) : cannibalization.length === 0 ? (
            <p className="text-center py-10 text-muted text-sm">Aucune cannibalisation detectee</p>
          ) : (
            <div className="space-y-4">
              {cannibalization.map((item, i) => (
                <div key={i} className="border border-border rounded-lg p-4">
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-white font-medium">{item.keyword}</span>
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${SEVERITY_COLORS[item.severity]}`}>
                      {item.severity}
                    </span>
                  </div>
                  <div className="space-y-1">
                    {item.articles.map(article => (
                      <button
                        key={article.id}
                        onClick={() => navigate(`/content/articles/${article.id}`)}
                        className="block text-sm text-violet hover:text-violet-light transition-colors"
                      >
                        {article.title}
                      </button>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {activeTab === 'gaps' && (
        <div className="bg-surface border border-border rounded-xl p-5">
          {gapsLoading ? (
            <div className="space-y-3">
              {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-lg h-12" />)}
            </div>
          ) : gaps.length === 0 ? (
            <p className="text-center py-10 text-muted text-sm">Aucun gap detecte</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                    <th className="pb-3 pr-4">Mot-cle</th>
                    <th className="pb-3 pr-4">Type</th>
                    <th className="pb-3 pr-4">Couvert</th>
                    <th className="pb-3 pr-4">Priorite</th>
                    <th className="pb-3">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {gaps.map((gap, i) => (
                    <tr key={i} className="border-b border-border/50 hover:bg-surface2/50 transition-colors">
                      <td className="py-3 pr-4 text-white font-medium">{gap.keyword}</td>
                      <td className="py-3 pr-4 text-muted">{gap.type}</td>
                      <td className="py-3 pr-4">
                        {gap.covered ? (
                          <span className="text-xs text-success">Oui</span>
                        ) : (
                          <span className="text-xs text-danger">Non</span>
                        )}
                      </td>
                      <td className="py-3 pr-4">
                        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${PRIORITY_COLORS[gap.suggested_priority]}`}>
                          {gap.suggested_priority}
                        </span>
                      </td>
                      <td className="py-3">
                        {!gap.covered && (
                          <button
                            onClick={() => navigate('/content/clusters')}
                            className="text-xs text-violet hover:text-violet-light transition-colors"
                          >
                            Creer cluster
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
