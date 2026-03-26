import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useContentArticles, useGenerationStats, useCosts } from '../../hooks/useContentEngine';
import type { GeneratedArticle, ContentStatus } from '../../types/content';

// ── Status helpers ──────────────────────────────────────────
const STATUS_COLORS: Record<ContentStatus, string> = {
  draft: 'bg-muted/20 text-muted',
  generating: 'bg-amber/20 text-amber',
  review: 'bg-orange-500/20 text-orange-400',
  scheduled: 'bg-cyan/20 text-cyan',
  published: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted',
};

const STATUS_LABELS: Record<ContentStatus, string> = {
  draft: 'Brouillon',
  generating: 'Generation...',
  review: 'A relire',
  scheduled: 'Planifie',
  published: 'Publie',
  archived: 'Archive',
};

function seoColor(score: number) {
  if (score >= 80) return 'text-success';
  if (score >= 60) return 'text-amber';
  return 'text-danger';
}

function seoBgColor(score: number) {
  if (score >= 80) return 'bg-success/20 text-success';
  if (score >= 60) return 'bg-amber/20 text-amber';
  return 'bg-danger/20 text-danger';
}

// ── Budget gauge ────────────────────────────────────────────
function BudgetGauge({ label, used, max }: { label: string; used: number; max: number }) {
  const pct = max > 0 ? Math.min((used / max) * 100, 100) : 0;
  const color = pct >= 90 ? 'bg-danger' : pct >= 70 ? 'bg-amber' : 'bg-violet';
  return (
    <div>
      <div className="flex justify-between text-xs text-muted mb-1">
        <span>{label}</span>
        <span>${(used / 100).toFixed(2)} / ${(max / 100).toFixed(2)}</span>
      </div>
      <div className="h-2 bg-surface2 rounded-full overflow-hidden">
        <div className={`h-full ${color} rounded-full transition-all`} style={{ width: `${pct}%` }} />
      </div>
    </div>
  );
}

// ── Main component ──────────────────────────────────────────
export default function ContentOverview() {
  const navigate = useNavigate();
  const { articles, loading: loadingArticles, load: loadArticles } = useContentArticles();
  const { stats, loading: loadingStats, load: loadStats } = useGenerationStats();
  const { overview, loading: loadingCosts, load: loadCosts } = useCosts();

  useEffect(() => {
    loadArticles({ page: 1 });
    loadStats();
    loadCosts();
  }, [loadArticles, loadStats, loadCosts]);

  const loading = loadingArticles || loadingStats || loadingCosts;

  // Stat cards data
  const cards = [
    {
      label: 'Articles',
      value: stats?.total_all_time ?? '-',
      icon: (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      ),
      color: 'text-violet bg-violet/20',
    },
    {
      label: 'Publies',
      value: stats?.by_status?.published ?? 0,
      icon: (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      ),
      color: 'text-success bg-success/20',
    },
    {
      label: 'Score SEO moyen',
      value: stats ? `${Math.round(stats.avg_seo_score)}/100` : '-',
      icon: (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      ),
      color: 'text-amber bg-amber/20',
    },
    {
      label: 'Cout IA ce mois',
      value: overview ? `$${(overview.this_month_cents / 100).toFixed(2)}` : '-',
      icon: (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      ),
      color: 'text-danger bg-danger/20',
    },
  ];

  const recentArticles = articles.slice(0, 10);

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between gap-3">
        <h2 className="font-title text-2xl font-bold text-white">Content Engine</h2>
        <div className="flex items-center gap-2">
          <button
            onClick={() => navigate('/content/articles/new')}
            className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
          >
            + Article
          </button>
          <button
            onClick={() => navigate('/content/comparatives/new')}
            className="px-4 py-1.5 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors"
          >
            + Comparatif
          </button>
          <button
            onClick={() => navigate('/content/campaigns/new')}
            className="px-4 py-1.5 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors"
          >
            + Campagne
          </button>
        </div>
      </div>

      {/* Stat cards */}
      {loading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {[1, 2, 3, 4].map(i => (
            <div key={i} className="bg-surface border border-border rounded-xl p-5 space-y-2">
              <div className="animate-pulse bg-surface2 rounded-lg h-3 w-20" />
              <div className="animate-pulse bg-surface2 rounded-lg h-8 w-16" />
            </div>
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {cards.map((card) => (
            <div key={card.label} className="bg-surface border border-border rounded-xl p-5">
              <div className="flex items-center justify-between mb-3">
                <span className="text-xs text-muted uppercase tracking-wide">{card.label}</span>
                <span className={`p-2 rounded-lg ${card.color}`}>{card.icon}</span>
              </div>
              <p className="text-2xl font-bold text-white">{card.value}</p>
            </div>
          ))}
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Recent articles table */}
        <div className="lg:col-span-2 bg-surface border border-border rounded-xl p-5">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-title font-semibold text-white">Articles recents</h3>
            <button
              onClick={() => navigate('/content/articles')}
              className="text-xs text-violet hover:text-violet-light transition-colors"
            >
              Voir tout
            </button>
          </div>

          {loadingArticles ? (
            <div className="text-sm text-muted">Chargement...</div>
          ) : recentArticles.length === 0 ? (
            <div className="text-center py-10">
              <p className="text-muted text-sm mb-3">Aucun article genere</p>
              <button
                onClick={() => navigate('/content/articles/new')}
                className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
              >
                Generer votre premier article
              </button>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                    <th className="pb-3 pr-4">Titre</th>
                    <th className="pb-3 pr-4">Langue</th>
                    <th className="pb-3 pr-4">Statut</th>
                    <th className="pb-3 pr-4">SEO</th>
                    <th className="pb-3 pr-4">Cree le</th>
                    <th className="pb-3">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {recentArticles.map((article) => (
                    <tr
                      key={article.id}
                      className="border-b border-border/50 hover:bg-surface2/50 transition-colors cursor-pointer"
                      onClick={() => navigate(`/content/articles/${article.id}`)}
                    >
                      <td className="py-3 pr-4">
                        <span className="text-white font-medium truncate block max-w-[250px]">
                          {article.title}
                        </span>
                      </td>
                      <td className="py-3 pr-4 text-muted uppercase">{article.language}</td>
                      <td className="py-3 pr-4">
                        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[article.status]}`}>
                          {STATUS_LABELS[article.status]}
                        </span>
                      </td>
                      <td className="py-3 pr-4">
                        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${seoBgColor(article.seo_score)}`}>
                          {article.seo_score}/100
                        </span>
                      </td>
                      <td className="py-3 pr-4 text-muted">
                        {new Date(article.created_at).toLocaleDateString('fr-FR')}
                      </td>
                      <td className="py-3">
                        <div className="flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
                          <button
                            onClick={() => navigate(`/content/articles/${article.id}`)}
                            className="text-xs text-violet hover:text-violet-light transition-colors"
                          >
                            Voir
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Sidebar: Budget + Quick stats */}
        <div className="space-y-4">
          {/* Budget gauge */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white mb-4">Budget IA</h3>
            {overview ? (
              <div className="space-y-4">
                <BudgetGauge
                  label="Aujourd'hui"
                  used={overview.today_cents}
                  max={overview.daily_budget_cents}
                />
                <BudgetGauge
                  label="Ce mois"
                  used={overview.this_month_cents}
                  max={overview.monthly_budget_cents}
                />
                {overview.is_over_daily && (
                  <p className="text-xs text-danger">Budget quotidien depasse</p>
                )}
                {overview.is_over_monthly && (
                  <p className="text-xs text-danger">Budget mensuel depasse</p>
                )}
              </div>
            ) : (
              <p className="text-sm text-muted">Chargement...</p>
            )}
          </div>

          {/* Quick stats */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white mb-4">Statistiques</h3>
            {stats ? (
              <div className="space-y-3">
                <div className="flex justify-between text-sm">
                  <span className="text-muted">Cette semaine</span>
                  <span className="text-white font-medium">{stats.total_this_week} articles</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-muted">Ce mois</span>
                  <span className="text-white font-medium">{stats.total_this_month} articles</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-muted">Temps moyen</span>
                  <span className="text-white font-medium">{Math.round(stats.avg_generation_seconds)}s</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-muted">Qualite moyenne</span>
                  <span className={`font-medium ${seoColor(stats.avg_quality_score)}`}>
                    {Math.round(stats.avg_quality_score)}/100
                  </span>
                </div>
                {/* Status breakdown */}
                <div className="pt-3 border-t border-border">
                  <p className="text-xs text-muted uppercase tracking-wide mb-2">Par statut</p>
                  {stats.by_status && Object.entries(stats.by_status).map(([status, count]) => (
                    <div key={status} className="flex justify-between text-xs mb-1">
                      <span className={`px-1.5 py-0.5 rounded ${STATUS_COLORS[status as ContentStatus] || 'text-muted'}`}>
                        {STATUS_LABELS[status as ContentStatus] || status}
                      </span>
                      <span className="text-muted">{count}</span>
                    </div>
                  ))}
                </div>
              </div>
            ) : (
              <p className="text-sm text-muted">Chargement...</p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
