import React, { useState, useEffect, useCallback } from 'react';
import api from '../../api/client';
import { generateArticle } from '../../api/contentApi';
import UnifiedContentTab from '../../components/UnifiedContentTab';
import { toast } from '../../components/Toast';
import { truncate, errMsg } from './helpers';

/**
 * Art Longues Traînes — Long-tail keyword article management.
 *
 * Displays keywords from keyword_tracking where type='long_tail',
 * grouped by search intent and category.
 * Allows generating articles from selected keywords.
 */

interface Keyword {
  id: number;
  keyword: string;
  type: string;
  search_intent: string | null;
  language: string;
  country: string | null;
  category: string | null;
  search_volume_estimate: string | null;
  articles_using_count: number;
  created_at: string;
}

const INTENT_STYLES: Record<string, { bg: string; text: string; label: string }> = {
  informational:              { bg: 'bg-blue-500/10',    text: 'text-blue-400',    label: 'Informationnel' },
  commercial_investigation:   { bg: 'bg-violet-500/10',  text: 'text-violet-400',  label: 'Investigation' },
  transactional:              { bg: 'bg-emerald-500/10', text: 'text-emerald-400', label: 'Transactionnel' },
  local:                      { bg: 'bg-amber-500/10',   text: 'text-amber-400',   label: 'Local' },
  urgency:                    { bg: 'bg-red-500/10',     text: 'text-red-400',     label: 'Urgence' },
};

const TABS = [
  { id: 'sources', label: 'Sources', icon: '📋' },
  { id: 'generation', label: 'Génération', icon: '⚡' },
  { id: 'generated', label: 'Contenus générés', icon: '✅' },
];

export default function ArtLonguesTraines() {
  const [tab, setTab] = useState('sources');
  const [keywords, setKeywords] = useState<Keyword[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState({ intent: '', category: '', search: '' });
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [generating, setGenerating] = useState(false);
  const [discoverResult, setDiscoverResult] = useState<string>('');

  const fetchKeywords = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get('/content-gen/keywords', { params: { type: 'long_tail', per_page: 500 } });
      setKeywords(Array.isArray(res.data?.data) ? res.data.data : Array.isArray(res.data) ? res.data : []);
    } catch {
      setKeywords([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchKeywords(); }, [fetchKeywords]);

  const filtered = keywords.filter(kw => {
    if (filter.intent && kw.search_intent !== filter.intent) return false;
    if (filter.category && kw.category !== filter.category) return false;
    if (filter.search && !kw.keyword.toLowerCase().includes(filter.search.toLowerCase())) return false;
    return true;
  });

  const intents = [...new Set(keywords.map(k => k.search_intent).filter(Boolean))];
  const categories = [...new Set(keywords.map(k => k.category).filter(Boolean))];

  const stats = {
    total: keywords.length,
    unused: keywords.filter(k => k.articles_using_count === 0).length,
    byIntent: Object.fromEntries(intents.map(i => [i, keywords.filter(k => k.search_intent === i).length])),
  };

  const toggleSelect = (id: number) => {
    const next = new Set(selected);
    if (next.has(id)) next.delete(id); else next.add(id);
    setSelected(next);
  };

  const handleGenerate = async () => {
    if (selected.size === 0) return;
    setGenerating(true);
    try {
      const items = keywords.filter(k => selected.has(k.id));
      for (const kw of items) {
        await generateArticle({
          topic: kw.keyword,
          content_type: 'article',
          language: kw.language || 'fr',
          country: kw.country ?? undefined,
          keywords: [kw.keyword],
        });
      }
      setSelected(new Set());
      toast.success(`${items.length} article(s) en generation`);
      fetchKeywords();
    } catch (e) {
      toast.error(errMsg(e));
    } finally {
      setGenerating(false);
    }
  };

  const handleDiscover = async () => {
    setDiscoverResult('Decouverte en cours...');
    try {
      const res = await api.post('/content-gen/keywords/discover', { limit: 20 });
      setDiscoverResult(`Decouverte terminee : ${res.data?.inserted ?? 0} nouveaux mots-cles.`);
      fetchKeywords();
    } catch (e) {
      setDiscoverResult(`Erreur : ${errMsg(e)}`);
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white flex items-center gap-3">
            🎯 Art Longues Traines
          </h1>
          <p className="text-muted text-sm mt-1">
            {stats.total} mots-cles longue traine — {stats.unused} sans article
          </p>
        </div>
        <button onClick={fetchKeywords} disabled={loading}
          className="px-4 py-2 rounded-lg bg-surface border border-border/30 text-sm text-muted hover:text-white transition-all">
          {loading ? '⏳' : '🔄'}
        </button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 lg:grid-cols-6 gap-3">
        <div className="bg-surface/60 border border-border/20 rounded-xl p-4 text-center">
          <div className="text-2xl font-bold text-white">{stats.total}</div>
          <div className="text-[11px] text-muted">Total</div>
        </div>
        {Object.entries(INTENT_STYLES).map(([key, style]) => (
          <div key={key} className="bg-surface/60 border border-border/20 rounded-xl p-4 text-center">
            <div className={`text-2xl font-bold ${style.text}`}>{stats.byIntent[key] ?? 0}</div>
            <div className="text-[11px] text-muted">{style.label}</div>
          </div>
        ))}
      </div>

      {/* Tabs */}
      <UnifiedContentTab tabs={TABS} activeTab={tab} onTabChange={setTab} />

      {/* Sources Tab (keywords + discover) */}
      {tab === 'sources' && (
        <div className="space-y-4">
          {/* Filters */}
          <div className="flex flex-wrap gap-3">
            <input
              type="text" placeholder="Rechercher..."
              value={filter.search} onChange={e => setFilter(f => ({ ...f, search: e.target.value }))}
              className="bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm w-64"
            />
            <select value={filter.intent} onChange={e => setFilter(f => ({ ...f, intent: e.target.value }))}
              className="bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="">Toutes intentions</option>
              {intents.map(i => <option key={i} value={i!}>{INTENT_STYLES[i!]?.label ?? i}</option>)}
            </select>
            <select value={filter.category} onChange={e => setFilter(f => ({ ...f, category: e.target.value }))}
              className="bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="">Toutes categories</option>
              {categories.map(c => <option key={c} value={c!}>{c}</option>)}
            </select>
          </div>

          {/* Table */}
          <div className="bg-surface/60 border border-border/20 rounded-xl overflow-hidden">
            <div className="max-h-[600px] overflow-y-auto">
              <table className="w-full text-sm">
                <thead className="bg-bg/80 sticky top-0">
                  <tr>
                    <th className="w-10 px-3 py-3"></th>
                    <th className="px-3 py-3 text-left text-muted font-medium">Mot-cle</th>
                    <th className="px-3 py-3 text-left text-muted font-medium">Intention</th>
                    <th className="px-3 py-3 text-left text-muted font-medium">Categorie</th>
                    <th className="px-3 py-3 text-left text-muted font-medium">Pays</th>
                    <th className="px-3 py-3 text-center text-muted font-medium">Articles</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border/10">
                  {filtered.slice(0, 200).map(kw => {
                    const intent = INTENT_STYLES[kw.search_intent ?? ''];
                    return (
                      <tr key={kw.id} className="hover:bg-surface/40 transition-colors">
                        <td className="px-3 py-2.5">
                          <input type="checkbox" checked={selected.has(kw.id)}
                            onChange={() => toggleSelect(kw.id)}
                            className="rounded border-border" />
                        </td>
                        <td className="px-3 py-2.5 text-white">{truncate(kw.keyword, 80)}</td>
                        <td className="px-3 py-2.5">
                          {intent && (
                            <span className={`px-2 py-0.5 rounded-full text-[11px] ${intent.bg} ${intent.text}`}>
                              {intent.label}
                            </span>
                          )}
                        </td>
                        <td className="px-3 py-2.5 text-muted">{kw.category ?? '—'}</td>
                        <td className="px-3 py-2.5 text-muted font-mono text-xs">{kw.country ?? '—'}</td>
                        <td className="px-3 py-2.5 text-center">
                          <span className={kw.articles_using_count > 0 ? 'text-emerald-400' : 'text-muted/40'}>
                            {kw.articles_using_count}
                          </span>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
          <p className="text-muted text-xs">{filtered.length} resultats</p>

          {/* Discover section */}
          <div className="bg-surface/60 border border-border/20 rounded-xl p-6 space-y-4">
            <h2 className="text-lg font-semibold text-white">Decouverte automatique</h2>
            <p className="text-muted text-sm">
              Analyse les articles publies et decouvre de nouvelles requetes longue traine via l'IA.
              Les doublons sont automatiquement filtres.
            </p>
            <button onClick={handleDiscover}
              className="px-6 py-3 rounded-xl bg-violet text-white font-semibold hover:bg-violet/80 transition-all">
              🔍 Lancer la decouverte
            </button>
            {discoverResult && (
              <p className="text-sm text-emerald-400 bg-emerald-500/10 px-4 py-2 rounded-lg">{discoverResult}</p>
            )}
          </div>
        </div>
      )}

      {/* Génération Tab */}
      {tab === 'generation' && (
        <div className="bg-surface/60 border border-border/20 rounded-xl p-6 space-y-4">
          <h2 className="text-lg font-semibold text-white">Generer des articles depuis la selection</h2>
          <p className="text-muted text-sm">
            {selected.size} mot(s)-cle(s) selectionne(s). Chaque mot-cle generera un article avec l'intention de recherche adaptee.
          </p>
          <button onClick={handleGenerate} disabled={selected.size === 0 || generating}
            className="px-6 py-3 rounded-xl bg-violet text-white font-semibold hover:bg-violet/80 transition-all disabled:opacity-40">
            {generating ? '⏳ Generation...' : `⚡ Generer ${selected.size} article(s)`}
          </button>
        </div>
      )}

      {/* Contenus générés Tab */}
      {tab === 'generated' && (
        <div className="space-y-4">
          {keywords.filter(k => k.articles_using_count > 0).length > 0 ? (
            <div className="bg-surface/60 border border-border/20 rounded-xl overflow-hidden">
              <div className="max-h-[600px] overflow-y-auto">
                <table className="w-full text-sm">
                  <thead className="bg-bg/80 sticky top-0">
                    <tr>
                      <th className="px-3 py-3 text-left text-muted font-medium">Mot-cle</th>
                      <th className="px-3 py-3 text-left text-muted font-medium">Intention</th>
                      <th className="px-3 py-3 text-left text-muted font-medium">Categorie</th>
                      <th className="px-3 py-3 text-center text-muted font-medium">Articles</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border/10">
                    {keywords.filter(k => k.articles_using_count > 0).map(kw => {
                      const intent = INTENT_STYLES[kw.search_intent ?? ''];
                      return (
                        <tr key={kw.id} className="hover:bg-surface/40 transition-colors">
                          <td className="px-3 py-2.5 text-white">{truncate(kw.keyword, 80)}</td>
                          <td className="px-3 py-2.5">
                            {intent && (
                              <span className={`px-2 py-0.5 rounded-full text-[11px] ${intent.bg} ${intent.text}`}>
                                {intent.label}
                              </span>
                            )}
                          </td>
                          <td className="px-3 py-2.5 text-muted">{kw.category ?? '—'}</td>
                          <td className="px-3 py-2.5 text-center text-emerald-400">{kw.articles_using_count}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            <div className="bg-surface/60 border border-border/20 rounded-xl p-6 text-center">
              <p className="text-3xl mb-2">📭</p>
              <p className="text-sm text-muted">Aucun article genere. Selectionnez des mots-cles et lancez la generation.</p>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
