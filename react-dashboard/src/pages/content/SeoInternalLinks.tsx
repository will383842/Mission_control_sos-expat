import { useEffect, useState } from 'react';
import * as contentApi from '../../api/contentApi';
import type { InternalLinksGraph, GeneratedArticle } from '../../types/content';

type ViewMode = 'list' | 'graph';

interface LinkRow {
  source: number;
  sourceTitle: string;
  sourceLang: string;
  target: number;
  targetTitle: string;
  targetLang: string;
  anchorText: string;
}

export default function SeoInternalLinks() {
  const [graph, setGraph] = useState<InternalLinksGraph | null>(null);
  const [orphaned, setOrphaned] = useState<GeneratedArticle[]>([]);
  const [loading, setLoading] = useState(true);
  const [viewMode, setViewMode] = useState<ViewMode>('list');
  const [search, setSearch] = useState('');
  const [filterLang, setFilterLang] = useState('');
  const [fixingId, setFixingId] = useState<number | null>(null);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    setLoading(true);
    try {
      const [graphRes, orphanedRes] = await Promise.all([
        contentApi.fetchInternalLinksGraph(),
        contentApi.fetchOrphanedArticles(),
      ]);
      setGraph(graphRes.data);
      setOrphaned(orphanedRes.data);
    } catch { /* silent */ }
    finally { setLoading(false); }
  };

  const handleFixOrphaned = async (articleId: number) => {
    setFixingId(articleId);
    try {
      await contentApi.fixOrphanedArticle(articleId);
      await loadData();
    } catch { /* silent */ }
    finally { setFixingId(null); }
  };

  // Build flat link rows from graph
  const linkRows: LinkRow[] = graph
    ? graph.edges.map(edge => {
        const sourceNode = graph.nodes.find(n => n.id === edge.source);
        const targetNode = graph.nodes.find(n => n.id === edge.target);
        return {
          source: edge.source,
          sourceTitle: sourceNode?.title ?? `Article #${edge.source}`,
          sourceLang: sourceNode?.language ?? '',
          target: edge.target,
          targetTitle: targetNode?.title ?? `Article #${edge.target}`,
          targetLang: targetNode?.language ?? '',
          anchorText: edge.anchor_text,
        };
      })
    : [];

  // Filtered rows
  const filteredRows = linkRows.filter(row => {
    if (search) {
      const s = search.toLowerCase();
      if (
        !row.sourceTitle.toLowerCase().includes(s) &&
        !row.targetTitle.toLowerCase().includes(s) &&
        !row.anchorText.toLowerCase().includes(s)
      ) return false;
    }
    if (filterLang && row.sourceLang !== filterLang) return false;
    return true;
  });

  // Graph view stats
  const nodeCount = graph?.nodes.length ?? 0;
  const edgeCount = graph?.edges.length ?? 0;
  const avgLinksPerArticle = nodeCount > 0 ? (edgeCount / nodeCount).toFixed(1) : '0';

  // Incoming link counts
  const incomingCounts: Record<number, number> = {};
  graph?.edges.forEach(e => {
    incomingCounts[e.target] = (incomingCounts[e.target] ?? 0) + 1;
  });

  const topConnected = graph?.nodes
    ? [...graph.nodes]
        .map(n => ({ ...n, incoming: incomingCounts[n.id] ?? 0 }))
        .sort((a, b) => b.incoming - a.incoming)
        .slice(0, 10)
    : [];

  const orphanedInGraph = graph?.nodes
    ? graph.nodes.filter(n => !incomingCounts[n.id])
    : [];

  // Unique languages for filter
  const languages = Array.from(new Set(graph?.nodes.map(n => n.language) ?? [])).sort();

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-violet" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="font-title text-2xl font-bold text-white">Liens internes</h1>
        <button
          onClick={loadData}
          className="px-3 py-1.5 text-sm bg-surface2 hover:bg-surface2/80 text-white rounded-lg transition"
        >
          Rafraichir
        </button>
      </div>

      {/* Stat cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="bg-surface rounded-xl p-5 border border-border">
          <p className="text-muted text-sm mb-1">Total liens</p>
          <p className="text-2xl font-bold text-violet-light">{edgeCount}</p>
        </div>
        <div className="bg-surface rounded-xl p-5 border border-border">
          <p className="text-muted text-sm mb-1">Moy. liens/article</p>
          <p className="text-2xl font-bold text-success">{avgLinksPerArticle}</p>
        </div>
        <div className="bg-surface rounded-xl p-5 border border-border">
          <p className="text-muted text-sm mb-1">Orphelins</p>
          <p className="text-2xl font-bold text-danger">{orphanedInGraph.length}</p>
        </div>
      </div>

      {/* View toggle */}
      <div className="flex gap-2">
        <button
          onClick={() => setViewMode('list')}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition ${
            viewMode === 'list'
              ? 'bg-violet text-white'
              : 'bg-surface2 text-white hover:bg-surface2/80'
          }`}
        >
          Liste
        </button>
        <button
          onClick={() => setViewMode('graph')}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition ${
            viewMode === 'graph'
              ? 'bg-violet text-white'
              : 'bg-surface2 text-white hover:bg-surface2/80'
          }`}
        >
          Graphe
        </button>
      </div>

      {viewMode === 'list' ? (
        /* LIST VIEW */
        <div className="bg-surface rounded-xl p-6 border border-border">
          {/* Filters */}
          <div className="flex flex-wrap gap-3 mb-4">
            <input
              type="text"
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder="Rechercher..."
              className="px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white placeholder-muted focus:outline-none focus:border-violet w-64"
            />
            <select
              value={filterLang}
              onChange={e => setFilterLang(e.target.value)}
              className="px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
            >
              <option value="">Toutes les langues</option>
              {languages.map(lang => (
                <option key={lang} value={lang}>{lang.toUpperCase()}</option>
              ))}
            </select>
          </div>

          {filteredRows.length === 0 ? (
            <p className="text-muted text-sm">Aucun lien trouve.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-muted border-b border-border">
                    <th className="text-left py-2 px-3">Source</th>
                    <th className="text-center py-2 px-3"></th>
                    <th className="text-left py-2 px-3">Cible</th>
                    <th className="text-left py-2 px-3">Ancre</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredRows.slice(0, 100).map((row, i) => (
                    <tr key={i} className="border-b border-border/50 hover:bg-surface2/30">
                      <td className="py-2 px-3">
                        <span className="text-white">{row.sourceTitle}</span>
                        <span className="ml-1.5 text-xs text-muted uppercase">{row.sourceLang}</span>
                      </td>
                      <td className="py-2 px-3 text-center text-muted">&rarr;</td>
                      <td className="py-2 px-3">
                        <span className="text-white">{row.targetTitle}</span>
                        <span className="ml-1.5 text-xs text-muted uppercase">{row.targetLang}</span>
                      </td>
                      <td className="py-2 px-3 text-muted italic">{row.anchorText}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {filteredRows.length > 100 && (
                <p className="text-muted text-xs text-center mt-2">
                  Affichage des 100 premiers sur {filteredRows.length}
                </p>
              )}
            </div>
          )}
        </div>
      ) : (
        /* GRAPH VIEW */
        <div className="space-y-6">
          {/* Graph placeholder */}
          <div className="bg-surface rounded-xl p-6 border border-border">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-semibold text-white">Graphe interactif</h2>
              <span className="text-sm text-muted">
                {nodeCount} articles, {edgeCount} liens
              </span>
            </div>
            <div className="flex items-center justify-center h-64 border-2 border-dashed border-border rounded-lg">
              <div className="text-center">
                <svg className="w-16 h-16 mx-auto text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
                <p className="text-muted text-sm">
                  Visualisation D3.js - a venir
                </p>
                <p className="text-muted text-xs mt-1">
                  {nodeCount} noeuds &middot; {edgeCount} aretes
                </p>
              </div>
            </div>
          </div>

          {/* Top connected */}
          <div className="bg-surface rounded-xl p-6 border border-border">
            <h2 className="text-lg font-semibold text-white mb-4">Articles les plus connectes</h2>
            {topConnected.length === 0 ? (
              <p className="text-muted text-sm">Aucune donnee.</p>
            ) : (
              <div className="space-y-2">
                {topConnected.map(node => (
                  <div key={node.id} className="flex items-center justify-between py-2 px-3 bg-surface2/30 rounded-lg">
                    <div>
                      <span className="text-white text-sm">{node.title}</span>
                      <span className="ml-2 text-xs text-muted uppercase">{node.language}</span>
                    </div>
                    <div className="flex items-center gap-3">
                      <span className="text-xs text-success font-medium">
                        {node.incoming} liens entrants
                      </span>
                      <span className={`text-xs font-medium ${node.seo_score >= 80 ? 'text-success' : node.seo_score >= 60 ? 'text-amber' : 'text-danger'}`}>
                        SEO: {node.seo_score}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Orphaned in graph */}
          {orphanedInGraph.length > 0 && (
            <div className="bg-surface rounded-xl p-6 border border-border">
              <h2 className="text-lg font-semibold text-white mb-4">
                Articles sans liens entrants ({orphanedInGraph.length})
              </h2>
              <div className="space-y-2">
                {orphanedInGraph.slice(0, 15).map(node => (
                  <div key={node.id} className="flex items-center justify-between py-2 px-3 bg-surface2/30 rounded-lg">
                    <div>
                      <span className="text-white text-sm">{node.title}</span>
                      <span className="ml-2 text-xs text-muted uppercase">{node.language}</span>
                    </div>
                    <button
                      onClick={() => handleFixOrphaned(node.id)}
                      disabled={fixingId === node.id}
                      className="px-3 py-1 text-xs bg-violet hover:bg-violet/90 disabled:opacity-50 text-white rounded transition"
                    >
                      {fixingId === node.id ? 'Correction...' : 'Corriger'}
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
