import { useEffect, useState } from 'react';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell,
} from 'recharts';
import { useSeoDashboard } from '../../hooks/useContentEngine';
import * as contentApi from '../../api/contentApi';
import type { HreflangMatrixEntry, GeneratedArticle } from '../../types/content';

const LANGUAGES = ['fr', 'en', 'de', 'es', 'pt', 'ru', 'zh', 'ar', 'hi'] as const;

function scoreColor(score: number): string {
  if (score < 60) return '#ef4444';
  if (score <= 80) return '#f59e0b';
  return '#10b981';
}

function severityIcon(severity: string): string {
  if (severity === 'error') return '\u274C';
  if (severity === 'warning') return '\u26A0\uFE0F';
  return '\u2139\uFE0F';
}

function hreflangIcon(val: boolean | undefined): string {
  if (val === true) return '\u2705';
  if (val === false) return '\u274C';
  return '\u26A0\uFE0F';
}

export default function SeoDashboard() {
  const { dashboard, loading, load } = useSeoDashboard();
  const [hreflangMatrix, setHreflangMatrix] = useState<HreflangMatrixEntry[]>([]);
  const [orphanedArticles, setOrphanedArticles] = useState<GeneratedArticle[]>([]);
  const [loadingMatrix, setLoadingMatrix] = useState(false);
  const [loadingOrphaned, setLoadingOrphaned] = useState(false);
  const [analyzingAll, setAnalyzingAll] = useState(false);
  const [fixingOrphaned, setFixingOrphaned] = useState(false);

  useEffect(() => {
    load();
    loadHreflangMatrix();
    loadOrphanedArticles();
  }, []);

  const loadHreflangMatrix = async () => {
    setLoadingMatrix(true);
    try {
      const { data } = await contentApi.fetchHreflangMatrix();
      setHreflangMatrix(data);
    } catch { /* silent */ }
    finally { setLoadingMatrix(false); }
  };

  const loadOrphanedArticles = async () => {
    setLoadingOrphaned(true);
    try {
      const { data } = await contentApi.fetchOrphanedArticles();
      setOrphanedArticles(data);
    } catch { /* silent */ }
    finally { setLoadingOrphaned(false); }
  };

  const handleAnalyzeAll = async () => {
    setAnalyzingAll(true);
    try {
      for (const article of orphanedArticles) {
        await contentApi.analyzeSeo({ model_type: 'article', model_id: article.id });
      }
      await load();
    } catch { /* silent */ }
    finally { setAnalyzingAll(false); }
  };

  const handleFixOrphaned = async () => {
    setFixingOrphaned(true);
    try {
      for (const article of orphanedArticles) {
        await contentApi.fixOrphanedArticle(article.id);
      }
      await loadOrphanedArticles();
      await load();
    } catch { /* silent */ }
    finally { setFixingOrphaned(false); }
  };

  const avgScore = dashboard?.scores_by_language
    ? Math.round(
        dashboard.scores_by_language.reduce((sum, s) => sum + s.avg_score * s.count, 0) /
        Math.max(1, dashboard.scores_by_language.reduce((sum, s) => sum + s.count, 0))
      )
    : 0;

  const totalArticles = dashboard?.scores_by_language
    ? dashboard.scores_by_language.reduce((sum, s) => sum + s.count, 0)
    : 0;

  const totalIndexed = dashboard?.score_ranges
    ? dashboard.score_ranges.reduce((sum, r) => sum + r.count, 0)
    : 0;

  const hreflangCoverage = hreflangMatrix.length > 0
    ? Math.round(
        (hreflangMatrix.reduce((sum, entry) => {
          const filled = LANGUAGES.filter(l => entry.translations[l] === true).length;
          return sum + filled;
        }, 0) / (hreflangMatrix.length * LANGUAGES.length)) * 100
      )
    : 0;

  if (loading && !dashboard) {
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
        <h1 className="font-title text-2xl font-bold text-white">SEO Dashboard</h1>
        <button
          onClick={() => load()}
          className="px-3 py-1.5 text-sm bg-surface2 hover:bg-surface2/80 text-muted hover:text-white rounded-lg border border-border transition-colors"
        >
          Rafraichir
        </button>
      </div>

      {/* 4 stat cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Score SEO moyen" value={`${avgScore}/100`} color={scoreColor(avgScore)} />
        <StatCard label="Articles indexes" value={String(totalIndexed)} color="#8b5cf6" />
        <StatCard label="Articles orphelins" value={String(dashboard?.orphaned_count ?? 0)} color={dashboard?.orphaned_count ? '#ef4444' : '#10b981'} />
        <StatCard label="Couverture hreflang" value={`${hreflangCoverage}%`} color={hreflangCoverage > 70 ? '#10b981' : '#f59e0b'} />
      </div>

      {/* Score by language bar chart */}
      {dashboard?.scores_by_language && dashboard.scores_by_language.length > 0 && (
        <div className="bg-surface rounded-xl p-6 border border-border">
          <h2 className="text-lg font-semibold text-white mb-4">Score par langue</h2>
          <ResponsiveContainer width="100%" height={300}>
            <BarChart data={dashboard.scores_by_language} layout="vertical" margin={{ left: 40, right: 20 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#1e2530" />
              <XAxis type="number" domain={[0, 100]} stroke="#6b7280" />
              <YAxis type="category" dataKey="language" stroke="#6b7280" width={40} />
              <Tooltip
                contentStyle={{ backgroundColor: '#101419', border: '1px solid #1e2530', borderRadius: 8 }}
                labelStyle={{ color: '#f3f4f6' }}
                itemStyle={{ color: '#d1d5db' }}
                formatter={(value: number, _name: string, entry: any) =>
                  [`${Math.round(value)} (${entry.payload.count} articles)`, 'Score']
                }
              />
              <Bar dataKey="avg_score" radius={[0, 4, 4, 0]}>
                {dashboard.scores_by_language.map((entry, i) => (
                  <Cell key={i} fill={scoreColor(entry.avg_score)} />
                ))}
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        </div>
      )}

      {/* Top issues table */}
      {dashboard?.top_issues && dashboard.top_issues.length > 0 && (
        <div className="bg-surface rounded-xl p-6 border border-border">
          <h2 className="text-lg font-semibold text-white mb-4">Problemes principaux</h2>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-muted border-b border-border">
                  <th className="text-left py-2 px-3">Severite</th>
                  <th className="text-left py-2 px-3">Type</th>
                  <th className="text-right py-2 px-3">Nombre</th>
                  <th className="text-right py-2 px-3">Action</th>
                </tr>
              </thead>
              <tbody>
                {dashboard.top_issues.map((issue, i) => (
                  <tr key={i} className="border-b border-border/50 hover:bg-surface2/50">
                    <td className="py-2 px-3">{severityIcon(issue.severity)}</td>
                    <td className="py-2 px-3 text-white">{issue.type}</td>
                    <td className="py-2 px-3 text-right text-white font-medium">{issue.count}</td>
                    <td className="py-2 px-3 text-right">
                      <button className="text-violet-light hover:text-white text-xs font-medium transition-colors">
                        Corriger
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Hreflang matrix */}
      <div className="bg-surface rounded-xl p-6 border border-border">
        <h2 className="text-lg font-semibold text-white mb-4">Matrice Hreflang</h2>
        {loadingMatrix ? (
          <div className="flex items-center justify-center h-32">
            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-violet" />
          </div>
        ) : hreflangMatrix.length === 0 ? (
          <p className="text-muted text-sm">Aucune donnee hreflang disponible.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-muted border-b border-border">
                  <th className="text-left py-2 px-3 min-w-[200px]">Article</th>
                  {LANGUAGES.map(lang => (
                    <th key={lang} className="text-center py-2 px-2 uppercase">{lang}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {hreflangMatrix.map(entry => (
                  <tr key={entry.article_id} className="border-b border-border/50 hover:bg-surface2/50">
                    <td className="py-2 px-3 text-white truncate max-w-[250px]" title={entry.title}>
                      {entry.title}
                    </td>
                    {LANGUAGES.map(lang => (
                      <td key={lang} className="py-2 px-2 text-center">
                        {lang === entry.language ? (
                          <span className="text-violet-light text-xs font-bold">SRC</span>
                        ) : (
                          hreflangIcon(entry.translations[lang])
                        )}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Orphaned articles */}
      {orphanedArticles.length > 0 && (
        <div className="bg-surface rounded-xl p-6 border border-border">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-white">
              Articles orphelins ({orphanedArticles.length})
            </h2>
            <button
              onClick={handleFixOrphaned}
              disabled={fixingOrphaned}
              className="px-3 py-1.5 text-sm bg-violet hover:bg-violet/90 disabled:opacity-50 text-white rounded-lg transition-colors"
            >
              {fixingOrphaned ? 'Correction...' : 'Corriger tous'}
            </button>
          </div>
          <div className="space-y-2">
            {orphanedArticles.slice(0, 10).map(article => (
              <div key={article.id} className="flex items-center justify-between py-2 px-3 bg-surface2/50 rounded-lg">
                <div>
                  <span className="text-white text-sm">{article.title}</span>
                  <span className="ml-2 text-xs text-muted uppercase">{article.language}</span>
                </div>
                <span className="text-xs text-danger">0 liens entrants</span>
              </div>
            ))}
            {orphanedArticles.length > 10 && (
              <p className="text-muted text-xs text-center">
                +{orphanedArticles.length - 10} articles supplementaires
              </p>
            )}
          </div>
        </div>
      )}

      {/* Quick actions */}
      <div className="bg-surface rounded-xl p-6 border border-border">
        <h2 className="text-lg font-semibold text-white mb-4">Actions rapides</h2>
        <div className="flex flex-wrap gap-3">
          <button
            onClick={handleAnalyzeAll}
            disabled={analyzingAll}
            className="px-4 py-2 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition-colors"
          >
            {analyzingAll ? 'Analyse en cours...' : 'Analyser tout'}
          </button>
          <button className="px-4 py-2 bg-success hover:bg-success/90 text-white rounded-lg text-sm font-medium transition-colors">
            Generer sitemap
          </button>
          <button
            onClick={handleFixOrphaned}
            disabled={fixingOrphaned || orphanedArticles.length === 0}
            className="px-4 py-2 bg-amber hover:bg-amber/90 disabled:opacity-50 text-black rounded-lg text-sm font-medium transition-colors"
          >
            {fixingOrphaned ? 'Correction...' : `Corriger orphelins (${dashboard?.orphaned_count ?? 0})`}
          </button>
        </div>
      </div>
    </div>
  );
}

function StatCard({ label, value, color }: { label: string; value: string; color: string }) {
  return (
    <div className="bg-surface rounded-xl p-5 border border-border">
      <p className="text-muted text-sm mb-1">{label}</p>
      <p className="text-2xl font-bold" style={{ color }}>{value}</p>
    </div>
  );
}
