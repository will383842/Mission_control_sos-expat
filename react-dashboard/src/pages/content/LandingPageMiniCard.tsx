import React from 'react';
import { useNavigate } from 'react-router-dom';
import type { LandingPage, ContentStatus } from '../../types/content';

// ── Types ──────────────────────────────────────────────────────────────

interface Props {
  landing: LandingPage & {
    audience_type?: string;
    template_id?: string;
    country_code?: string;
    generation_source?: string;
  };
  onDelete?: (id: number) => void;
}

// ── Constants ──────────────────────────────────────────────────────────

const STATUS_COLORS: Record<ContentStatus, string> = {
  draft:      'bg-muted/20 text-muted',
  generating: 'bg-amber-500/20 text-amber-400 animate-pulse',
  review:     'bg-blue-500/20 text-blue-400',
  scheduled:  'bg-violet/20 text-violet',
  published:  'bg-emerald-500/20 text-emerald-400',
  archived:   'bg-muted/10 text-muted/60',
};

const STATUS_LABELS: Record<ContentStatus, string> = {
  draft:      'Brouillon',
  generating: 'Génération…',
  review:     'En revue',
  scheduled:  'Planifié',
  published:  'Publié',
  archived:   'Archivé',
};

const AUDIENCE_COLORS: Record<string, string> = {
  clients:  'bg-blue-500/10 text-blue-400 border-blue-500/20',
  lawyers:  'bg-violet/10 text-violet border-violet/20',
  helpers:  'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
  matching: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
};

const AUDIENCE_LABELS: Record<string, string> = {
  clients:  '👤 Clients',
  lawyers:  '⚖️ Avocats',
  helpers:  '🧳 Helpers',
  matching: '🎯 Matching',
};

function seoColor(score: number): string {
  if (score >= 80) return 'text-emerald-400';
  if (score >= 60) return 'text-amber-400';
  return 'text-red-400';
}

function formatDate(d: string | null): string {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ── Component ──────────────────────────────────────────────────────────

export default function LandingPageMiniCard({ landing, onDelete }: Props) {
  const navigate = useNavigate();
  const status   = (landing.status ?? 'draft') as ContentStatus;
  const audience = landing.audience_type ?? 'manual';
  const seo      = landing.seo_score ?? 0;

  return (
    <div
      onClick={() => navigate(`/content/landings/${landing.id}`)}
      className="group flex items-center gap-4 px-4 py-3 rounded-xl bg-bg/50 border border-border/20 hover:border-border/50 hover:bg-bg/80 transition-all cursor-pointer"
    >
      {/* Audience badge */}
      <span
        className={`hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium border shrink-0 ${
          AUDIENCE_COLORS[audience] ?? 'bg-muted/10 text-muted border-border/20'
        }`}
      >
        {AUDIENCE_LABELS[audience] ?? audience}
      </span>

      {/* Title + template */}
      <div className="flex-1 min-w-0">
        <p className="text-sm text-white font-medium truncate group-hover:text-violet-light transition-colors">
          {landing.title ?? landing.slug ?? `#${landing.id}`}
        </p>
        <div className="flex items-center gap-2 mt-0.5 flex-wrap">
          {(landing.country_code || landing.country) && (
            <span className="text-[11px] text-muted font-mono">
              {landing.country_code ?? landing.country}
            </span>
          )}
          {landing.template_id && (
            <span className="text-[11px] text-muted/70 font-mono truncate max-w-[120px]">
              {landing.template_id}
            </span>
          )}
          {landing.language && (
            <span className="text-[11px] text-muted uppercase">{landing.language}</span>
          )}
        </div>
      </div>

      {/* SEO score */}
      {seo > 0 && (
        <div className={`hidden md:flex flex-col items-center shrink-0 ${seoColor(seo)}`}>
          <span className="text-sm font-bold font-mono">{seo}</span>
          <span className="text-[10px] opacity-70">SEO</span>
        </div>
      )}

      {/* Status */}
      <span
        className={`text-[11px] font-semibold px-2 py-0.5 rounded-full shrink-0 ${
          STATUS_COLORS[status]
        }`}
      >
        {STATUS_LABELS[status]}
      </span>

      {/* Date */}
      <span className="hidden lg:block text-[11px] text-muted shrink-0">
        {formatDate(landing.created_at ?? null)}
      </span>

      {/* Actions */}
      {onDelete && (
        <button
          onClick={(e) => { e.stopPropagation(); onDelete(landing.id); }}
          className="opacity-0 group-hover:opacity-100 text-muted hover:text-red-400 transition-all p-1 shrink-0"
          title="Supprimer"
        >
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M2 2l10 10M12 2L2 12" />
          </svg>
        </button>
      )}
    </div>
  );
}
