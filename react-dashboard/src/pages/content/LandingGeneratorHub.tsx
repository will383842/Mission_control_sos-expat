import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { fetchLandingCampaign, fetchLandings } from '../../api/contentApi';
import type { AudienceType } from '../../api/contentApi';
import type { LandingCampaignData } from './LandingCountryQueue';
import type { LandingPage, PaginatedResponse } from '../../types/content';

// ── Types ──────────────────────────────────────────────────────────────

interface AudienceStat {
  type: AudienceType;
  label: string;
  icon: string;
  route: string;
  data: LandingCampaignData | null;
  total: number;
  published: number;
  avgSeo: number;
}

// ── Helpers ────────────────────────────────────────────────────────────

const AUDIENCES: { type: AudienceType; label: string; icon: string; route: string }[] = [
  { type: 'clients',  label: 'Clients',  icon: '👤', route: '/content/landing-generator/clients' },
  { type: 'lawyers',  label: 'Avocats',  icon: '⚖️', route: '/content/landing-generator/avocats' },
  { type: 'helpers',  label: 'Helpers',  icon: '🧳', route: '/content/landing-generator/helpers' },
  { type: 'matching', label: 'Matching', icon: '🎯', route: '/content/landing-generator/matching' },
];

const STATUS_DOT: Record<string, string> = {
  idle:      'bg-muted',
  running:   'bg-emerald-500 animate-pulse',
  paused:    'bg-amber-400',
  completed: 'bg-violet',
};

const STATUS_LABEL: Record<string, string> = {
  idle:      'En attente',
  running:   'En cours',
  paused:    'Pausé',
  completed: 'Terminé',
};

function seoColor(score: number): string {
  if (score >= 80) return 'text-emerald-400';
  if (score >= 60) return 'text-amber-400';
  return 'text-red-400';
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

// ── Stat Card ──────────────────────────────────────────────────────────

function AudienceStatCard({ stat, onClick }: { stat: AudienceStat; onClick: () => void }) {
  const d      = stat.data;
  const status = d?.status ?? 'idle';
  const pct    = d && d.queue.length > 0
    ? Math.round(
        d.queue.reduce((acc, i) => acc + Math.min(1, i.count / Math.max(1, i.target)), 0)
        / d.queue.length * 100,
      )
    : 0;

  return (
    <button
      onClick={onClick}
      className="w-full text-left bg-surface/60 border border-border/20 rounded-2xl p-5 hover:border-violet/30 hover:bg-surface/80 transition-all group"
    >
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <span className="text-xl">{stat.icon}</span>
          <span className="text-white font-semibold">{stat.label}</span>
        </div>
        <div className="flex items-center gap-1.5">
          <span className={`w-2 h-2 rounded-full ${STATUS_DOT[status]}`} />
          <span className="text-xs text-muted">{STATUS_LABEL[status]}</span>
        </div>
      </div>

      {/* Stats grid */}
      <div className="grid grid-cols-3 gap-3 mb-4">
        <div className="bg-bg/60 rounded-xl p-3 text-center">
          <p className="text-xl font-bold text-white font-mono">{stat.total}</p>
          <p className="text-[10px] text-muted mt-0.5">Générées</p>
        </div>
        <div className="bg-bg/60 rounded-xl p-3 text-center">
          <p className="text-xl font-bold text-emerald-400 font-mono">{stat.published}</p>
          <p className="text-[10px] text-muted mt-0.5">Publiées</p>
        </div>
        <div className="bg-bg/60 rounded-xl p-3 text-center">
          <p className={`text-xl font-bold font-mono ${seoColor(stat.avgSeo)}`}>
            {stat.avgSeo > 0 ? stat.avgSeo : '—'}
          </p>
          <p className="text-[10px] text-muted mt-0.5">SEO moy.</p>
        </div>
      </div>

      {/* Campaign progress */}
      {d && (
        <div className="space-y-1.5">
          <div className="flex items-center justify-between text-xs text-muted">
            <span>
              {d.current_country
                ? `Pays actuel : ${d.current_country}`
                : `${d.queue.length} pays en queue`}
            </span>
            <span className="font-mono">{pct}%</span>
          </div>
          <div className="w-full h-1.5 bg-bg rounded-full overflow-hidden">
            <div
              className={`h-full rounded-full transition-all duration-700 ${
                status === 'running' ? 'bg-emerald-500' : 'bg-violet/60'
              }`}
              style={{ width: `${pct}%` }}
            />
          </div>
          {d.total_cost_cents > 0 && (
            <p className="text-[10px] text-muted text-right">
              {(d.total_cost_cents / 100).toFixed(2)}$ dépensés
            </p>
          )}
        </div>
      )}

      <div className="mt-4 flex items-center justify-end text-[11px] text-violet-light/70 group-hover:text-violet-light transition-colors">
        Gérer →
      </div>
    </button>
  );
}

// ── Recent LP Row ──────────────────────────────────────────────────────

const AUDIENCE_BADGE: Record<string, string> = {
  clients:  'bg-blue-500/10 text-blue-400',
  lawyers:  'bg-violet/10 text-violet',
  helpers:  'bg-emerald-500/10 text-emerald-400',
  matching: 'bg-amber-500/10 text-amber-400',
};

const STATUS_COLORS: Record<string, string> = {
  draft:      'bg-muted/20 text-muted',
  generating: 'bg-amber-500/20 text-amber-400',
  review:     'bg-blue-500/20 text-blue-400',
  scheduled:  'bg-violet/20 text-violet',
  published:  'bg-emerald-500/20 text-emerald-400',
  archived:   'bg-muted/10 text-muted/60',
};

type AugmentedLP = LandingPage & {
  audience_type?: string;
  template_id?: string;
  country_code?: string;
};

function RecentLpRow({ lp, onClick }: { lp: AugmentedLP; onClick: () => void }) {
  const audience = lp.audience_type ?? 'manual';
  return (
    <div
      onClick={onClick}
      className="flex items-center gap-3 px-4 py-2.5 rounded-xl bg-bg/40 hover:bg-bg/70 border border-transparent hover:border-border/30 transition-all cursor-pointer group"
    >
      <span className={`hidden sm:inline text-[11px] font-medium px-2 py-0.5 rounded-full ${AUDIENCE_BADGE[audience] ?? 'bg-muted/10 text-muted'}`}>
        {audience}
      </span>
      <p className="flex-1 text-sm text-gray-300 truncate group-hover:text-white transition-colors">
        {lp.title ?? lp.slug}
      </p>
      {(lp.country_code || lp.country) && (
        <span className="text-xs text-muted font-mono shrink-0">
          {lp.country_code ?? lp.country}
        </span>
      )}
      {lp.seo_score > 0 && (
        <span className={`text-xs font-mono font-bold shrink-0 ${seoColor(lp.seo_score)}`}>
          {lp.seo_score}
        </span>
      )}
      <span className={`hidden md:inline text-[11px] px-2 py-0.5 rounded-full font-medium shrink-0 ${STATUS_COLORS[lp.status] ?? STATUS_COLORS.draft}`}>
        {lp.status}
      </span>
      <span className="text-[11px] text-muted shrink-0">{formatDate(lp.created_at)}</span>
    </div>
  );
}

// ── Main Component ─────────────────────────────────────────────────────

export default function LandingGeneratorHub() {
  const navigate = useNavigate();
  const [stats, setStats]     = useState<AudienceStat[]>([]);
  const [recent, setRecent]   = useState<AugmentedLP[]>([]);
  const [loading, setLoading] = useState(true);

  const loadAll = useCallback(async () => {
    setLoading(true);
    try {
      const [campaignResults, landingsRes] = await Promise.all([
        Promise.allSettled(
          AUDIENCES.map((a) => fetchLandingCampaign(a.type)),
        ),
        fetchLandings({
          per_page: 12,
          page: 1,
          generation_source: 'ai_generated',
        }),
      ]);

      // Build stats per audience
      const built: AudienceStat[] = AUDIENCES.map((a, i) => {
        const result = campaignResults[i];
        const data: LandingCampaignData | null =
          result.status === 'fulfilled' ? result.value.data : null;
        return {
          ...a,
          data,
          total:     data?.total_generated ?? 0,
          published: 0,   // computed below if we have LP data
          avgSeo:    0,
        };
      });

      // Parse recent landings
      const lpData = landingsRes.data as unknown as PaginatedResponse<AugmentedLP>;
      const lps    = lpData.data ?? [];
      setRecent(lps);

      // Compute published/avgSeo per audience from recent (rough approximation)
      const byAudience: Record<string, { count: number; seoSum: number; published: number }> = {};
      for (const lp of lps) {
        const a = lp.audience_type ?? 'manual';
        if (!byAudience[a]) byAudience[a] = { count: 0, seoSum: 0, published: 0 };
        byAudience[a].count++;
        byAudience[a].seoSum  += lp.seo_score ?? 0;
        if (lp.status === 'published') byAudience[a].published++;
      }

      setStats(built.map((s) => {
        const b = byAudience[s.type];
        return {
          ...s,
          published: b ? Math.round((s.data?.total_generated ?? 0) * (b.published / Math.max(1, b.count))) : 0,
          avgSeo:    b ? Math.round(b.seoSum / b.count) : 0,
        };
      }));
    } catch {
      /* silent */
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadAll();
    const interval = setInterval(loadAll, 30000);
    return () => clearInterval(interval);
  }, [loadAll]);

  const totalGenerated = stats.reduce((acc, s) => acc + s.total, 0);
  const totalCost      = stats.reduce((acc, s) => acc + (s.data?.total_cost_cents ?? 0), 0);
  const running        = stats.filter((s) => s.data?.status === 'running').length;

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-8">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white">Landing Generator</h1>
          <p className="text-muted text-sm mt-1">
            Génération automatique de landing pages par pays · 4 audiences · 14 templates
          </p>
        </div>
        <div className="flex items-center gap-3 flex-wrap">
          <div className="bg-bg border border-border/30 rounded-xl px-4 py-2 text-center">
            <p className="text-lg font-bold text-white font-mono">{totalGenerated}</p>
            <p className="text-[10px] text-muted">LPs générées</p>
          </div>
          {running > 0 && (
            <div className="bg-emerald-500/10 border border-emerald-500/30 rounded-xl px-4 py-2 text-center">
              <p className="text-lg font-bold text-emerald-400 font-mono">{running}</p>
              <p className="text-[10px] text-muted">En cours</p>
            </div>
          )}
          {totalCost > 0 && (
            <div className="bg-bg border border-border/30 rounded-xl px-4 py-2 text-center">
              <p className="text-lg font-bold text-violet font-mono">
                {(totalCost / 100).toFixed(2)}$
              </p>
              <p className="text-[10px] text-muted">Coût total</p>
            </div>
          )}
          <button
            onClick={loadAll}
            className="px-3 py-2 rounded-lg bg-bg border border-border/30 text-muted hover:text-white transition-colors"
            title="Actualiser"
          >
            ↻
          </button>
        </div>
      </div>

      {/* Audience Cards */}
      {loading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {AUDIENCES.map((a) => (
            <div key={a.type} className="h-52 bg-surface/40 border border-border/20 rounded-2xl animate-pulse" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {stats.map((stat) => (
            <AudienceStatCard
              key={stat.type}
              stat={stat}
              onClick={() => navigate(stat.route)}
            />
          ))}
        </div>
      )}

      {/* Recent LPs */}
      <div className="bg-surface/60 border border-border/20 rounded-2xl p-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-white font-semibold">Dernières LPs générées</h2>
          <button
            onClick={() => navigate('/content/landings')}
            className="text-xs text-violet-light hover:underline"
          >
            Voir toutes →
          </button>
        </div>

        {loading ? (
          <div className="space-y-2">
            {Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="h-10 bg-bg/40 rounded-xl animate-pulse" />
            ))}
          </div>
        ) : recent.length === 0 ? (
          <p className="text-muted text-sm text-center py-10">
            Aucune landing page générée. Lancez une campagne depuis un onglet audience.
          </p>
        ) : (
          <div className="space-y-1.5">
            {recent.map((lp) => (
              <RecentLpRow
                key={lp.id}
                lp={lp}
                onClick={() => navigate(`/content/landings/${lp.id}`)}
              />
            ))}
          </div>
        )}
      </div>

      {/* Quick Actions */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {AUDIENCES.map((a) => (
          <button
            key={a.type}
            onClick={() => navigate(a.route)}
            className="flex flex-col items-center gap-2 p-4 rounded-xl bg-surface/40 border border-border/20 hover:border-violet/30 hover:bg-surface/60 transition-all group"
          >
            <span className="text-2xl">{a.icon}</span>
            <span className="text-sm text-gray-300 group-hover:text-white transition-colors">
              {a.label}
            </span>
            <span className="text-[11px] text-violet-light/60 group-hover:text-violet-light transition-colors">
              Gérer →
            </span>
          </button>
        ))}
      </div>
    </div>
  );
}
