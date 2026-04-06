import React, { useState, useEffect, useCallback } from 'react';
import api from '../../api/client';
import UnifiedContentTab from '../../components/UnifiedContentTab';
import { truncate, errMsg } from './helpers';

/**
 * Brand Content — Manage and score existing brand content articles.
 *
 * Displays content_articles with quality scoring,
 * allows keeping top articles and archiving low-quality ones.
 */

interface BrandArticle {
  id: number;
  title: string;
  url: string | null;
  category: string | null;
  section: string | null;
  word_count: number | null;
  quality_rating: number | null;
  processing_status: string | null;
  language: string | null;
  scraped_at: string | null;
}

const STATUS_STYLES: Record<string, { bg: string; text: string; label: string }> = {
  pending:           { bg: 'bg-muted/10',      text: 'text-muted/60',   label: 'En attente' },
  processed:         { bg: 'bg-blue-500/10',    text: 'text-blue-400',   label: 'Traite' },
  generation_ready:  { bg: 'bg-emerald-500/10', text: 'text-emerald-400', label: 'Pret' },
  archived:          { bg: 'bg-red-500/10',     text: 'text-red-400',    label: 'Archive' },
  enriched:          { bg: 'bg-violet-500/10',  text: 'text-violet-400', label: 'Enrichi' },
};

const TABS = [
  { id: 'all', label: 'Tous', icon: '📋' },
  { id: 'top', label: 'Top 30', icon: '⭐' },
  { id: 'archived', label: 'Archives', icon: '📦' },
];

export default function BrandContent() {
  const [tab, setTab] = useState('all');
  const [articles, setArticles] = useState<BrandArticle[]>([]);
  const [loading, setLoading] = useState(true);
  const [scoring, setScoring] = useState(false);
  const [scoreResult, setScoreResult] = useState('');
  const [search, setSearch] = useState('');

  const fetchArticles = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get('/content-articles', { params: { per_page: 500, sort: '-quality_rating' } });
      setArticles(Array.isArray(res.data?.data) ? res.data.data : Array.isArray(res.data) ? res.data : []);
    } catch {
      setArticles([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchArticles(); }, [fetchArticles]);

  const handleScore = async () => {
    setScoring(true);
    setScoreResult('Scoring en cours...');
    try {
      const res = await api.post('/content-articles/score', { apply: true, top: 30 });
      setScoreResult(`Score termine : ${res.data?.top ?? 0} top, ${res.data?.archived ?? 0} archives.`);
      fetchArticles();
    } catch (e) {
      setScoreResult(`Erreur : ${errMsg(e)}`);
    } finally {
      setScoring(false);
    }
  };

  const filtered = articles.filter(a => {
    if (search && !a.title?.toLowerCase().includes(search.toLowerCase())) return false;
    if (tab === 'top') return a.processing_status === 'generation_ready';
    if (tab === 'archived') return a.processing_status === 'archived';
    return true;
  });

  const stats = {
    total: articles.length,
    top: articles.filter(a => a.processing_status === 'generation_ready').length,
    archived: articles.filter(a => a.processing_status === 'archived').length,
    avgScore: articles.length > 0
      ? Math.round(articles.reduce((s, a) => s + (a.quality_rating ?? 0), 0) / articles.length)
      : 0,
  };

  const scoreColor = (score: number | null) => {
    if (!score) return 'text-muted/40';
    if (score >= 80) return 'text-emerald-400';
    if (score >= 50) return 'text-amber-400';
    return 'text-red-400';
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white flex items-center gap-3">
            🏷️ Brand Content
          </h1>
          <p className="text-muted text-sm mt-1">
            {stats.total} articles — Score moyen {stats.avgScore}/100
          </p>
        </div>
        <div className="flex gap-2">
          <button onClick={handleScore} disabled={scoring}
            className="px-4 py-2 rounded-lg bg-violet text-white text-sm font-medium hover:bg-violet/80 transition-all disabled:opacity-40">
            {scoring ? '⏳' : '🎯'} Scorer & Trier
          </button>
          <button onClick={fetchArticles} disabled={loading}
            className="px-4 py-2 rounded-lg bg-surface border border-border/30 text-sm text-muted hover:text-white transition-all">
            {loading ? '⏳' : '🔄'}
          </button>
        </div>
      </div>

      {scoreResult && (
        <p className="text-sm text-emerald-400 bg-emerald-500/10 px-4 py-2 rounded-lg">{scoreResult}</p>
      )}

      {/* Stats */}
      <div className="grid grid-cols-4 gap-3">
        <div className="bg-surface/60 border border-border/20 rounded-xl p-4 text-center">
          <div className="text-2xl font-bold text-white">{stats.total}</div>
          <div className="text-[11px] text-muted">Total</div>
        </div>
        <div className="bg-surface/60 border border-border/20 rounded-xl p-4 text-center">
          <div className="text-2xl font-bold text-emerald-400">{stats.top}</div>
          <div className="text-[11px] text-muted">Top (prets)</div>
        </div>
        <div className="bg-surface/60 border border-border/20 rounded-xl p-4 text-center">
          <div className="text-2xl font-bold text-red-400">{stats.archived}</div>
          <div className="text-[11px] text-muted">Archives</div>
        </div>
        <div className="bg-surface/60 border border-border/20 rounded-xl p-4 text-center">
          <div className="text-2xl font-bold text-amber-400">{stats.avgScore}</div>
          <div className="text-[11px] text-muted">Score moyen</div>
        </div>
      </div>

      {/* Tabs */}
      <UnifiedContentTab tabs={TABS} activeTab={tab} onTabChange={setTab} />

      {/* Search */}
      <input
        type="text" placeholder="Rechercher un article..."
        value={search} onChange={e => setSearch(e.target.value)}
        className="bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm w-full max-w-md"
      />

      {/* Table */}
      <div className="bg-surface/60 border border-border/20 rounded-xl overflow-hidden">
        <div className="max-h-[600px] overflow-y-auto">
          <table className="w-full text-sm">
            <thead className="bg-bg/80 sticky top-0">
              <tr>
                <th className="px-3 py-3 text-left text-muted font-medium">Titre</th>
                <th className="px-3 py-3 text-center text-muted font-medium">Score</th>
                <th className="px-3 py-3 text-center text-muted font-medium">Mots</th>
                <th className="px-3 py-3 text-left text-muted font-medium">Categorie</th>
                <th className="px-3 py-3 text-left text-muted font-medium">Statut</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border/10">
              {filtered.slice(0, 200).map(a => {
                const status = STATUS_STYLES[a.processing_status ?? ''];
                return (
                  <tr key={a.id} className="hover:bg-surface/40 transition-colors">
                    <td className="px-3 py-2.5 text-white">{truncate(a.title ?? 'Sans titre', 70)}</td>
                    <td className="px-3 py-2.5 text-center">
                      <span className={`font-bold ${scoreColor(a.quality_rating)}`}>
                        {a.quality_rating ?? '—'}
                      </span>
                    </td>
                    <td className="px-3 py-2.5 text-center text-muted">{a.word_count ?? '—'}</td>
                    <td className="px-3 py-2.5 text-muted">{a.category ?? '—'}</td>
                    <td className="px-3 py-2.5">
                      {status && (
                        <span className={`px-2 py-0.5 rounded-full text-[11px] ${status.bg} ${status.text}`}>
                          {status.label}
                        </span>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
      <p className="text-muted text-xs">{filtered.length} resultats</p>
    </div>
  );
}
