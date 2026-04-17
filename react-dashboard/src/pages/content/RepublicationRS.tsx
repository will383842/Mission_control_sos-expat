import React from 'react';
import { useParams } from 'react-router-dom';
import {
  useSocialStats,
  useSocialOAuthStatus,
  socialOAuthAuthorizeUrl,
  type SocialPlatform,
} from '../../hooks/useSocialPlatform';

// ── Types ──────────────────────────────────────────────────────────────

type Platform = SocialPlatform | 'pinterest' | 'reddit';

interface PlatformConfig {
  id: Platform;
  label: string;
  emoji: string;
  color: string;
  bgColor: string;
  borderColor: string;
  description: string;
  /** A platform with backend support ; false = placeholder only. */
  backendReady: boolean;
}

// ── Config ─────────────────────────────────────────────────────────────

const PLATFORMS: PlatformConfig[] = [
  {
    id: 'linkedin',
    label: 'LinkedIn',
    emoji: '💼',
    color: 'text-blue-700',
    bgColor: 'bg-blue-50',
    borderColor: 'border-blue-200',
    description: 'Publication automatique, 1er commentaire, commentaires suivis via Telegram',
    backendReady: true,
  },
  {
    id: 'facebook',
    label: 'Facebook',
    emoji: '📘',
    color: 'text-blue-600',
    bgColor: 'bg-blue-50',
    borderColor: 'border-blue-100',
    description: 'Publication sur la Page Pro SOS-Expat (en attente de l\'approbation Meta App Review)',
    backendReady: true,
  },
  {
    id: 'threads',
    label: 'Threads',
    emoji: '🧵',
    color: 'text-gray-800',
    bgColor: 'bg-gray-50',
    borderColor: 'border-gray-200',
    description: 'Fils de discussion Meta Threads (limite 500 caractères, 250 posts/24h)',
    backendReady: true,
  },
  {
    id: 'instagram',
    label: 'Instagram',
    emoji: '📸',
    color: 'text-pink-600',
    bgColor: 'bg-pink-50',
    borderColor: 'border-pink-200',
    description: 'Compte Business Instagram (image obligatoire, lié à la Page Facebook)',
    backendReady: true,
  },
  {
    id: 'pinterest',
    label: 'Pinterest',
    emoji: '📌',
    color: 'text-red-600',
    bgColor: 'bg-red-50',
    borderColor: 'border-red-200',
    description: 'Pas encore planifié',
    backendReady: false,
  },
  {
    id: 'reddit',
    label: 'Reddit',
    emoji: '🤖',
    color: 'text-orange-600',
    bgColor: 'bg-orange-50',
    borderColor: 'border-orange-200',
    description: 'Pas encore planifié',
    backendReady: false,
  },
];

// ── Main component ─────────────────────────────────────────────────────

export default function RepublicationRS() {
  const { platform = 'linkedin' } = useParams<{ platform: Platform }>();
  const active = PLATFORMS.find((p) => p.id === platform) ?? PLATFORMS[0];

  // Single-platform page — navigation between platforms happens via the
  // sidebar sub-menu, not via in-page tabs. Each /content/republication-rs/{platform}
  // URL is its own self-contained dashboard for that one platform.
  return (
    <div className="p-6 max-w-5xl mx-auto space-y-6">
      {/* Header — platform-specific */}
      <div className="flex items-center gap-3">
        <span className="text-3xl">{active.emoji}</span>
        <div>
          <h1 className={`text-2xl font-bold ${active.color}`}>
            Republication RS — {active.label}
          </h1>
          <p className="text-muted-foreground text-sm mt-1">{active.description}</p>
        </div>
      </div>

      {/* Platform content (no tabs) */}
      {active.backendReady ? (
        <PlatformPanel config={active} />
      ) : (
        <PlaceholderPanel config={active} />
      )}
    </div>
  );
}

// ── Panel for backend-ready platforms ──────────────────────────────────

function PlatformPanel({ config }: { config: PlatformConfig }) {
  const platform = config.id as SocialPlatform;
  const stats = useSocialStats(platform);
  const oauth = useSocialOAuthStatus(platform);

  return (
    <div className="space-y-4">
      {/* OAuth connection status */}
      <section className="border border-border rounded-lg p-4 bg-surface">
        <h3 className="font-semibold text-sm mb-3">🔐 Connexion OAuth</h3>
        {oauth.isLoading ? (
          <p className="text-sm text-muted-foreground">Chargement du statut…</p>
        ) : oauth.isError ? (
          <p className="text-sm text-red-600">
            Erreur : impossible de récupérer le statut OAuth.
            {platform !== 'linkedin' && ' Cette plateforme est peut-être désactivée (Phase 5 en attente).'}
          </p>
        ) : (
          <div className="space-y-2">
            {Object.entries(oauth.data?.tokens ?? {}).map(([accountType, t]) => (
              <div
                key={accountType}
                className="flex items-center justify-between text-sm bg-white/60 border border-border rounded px-3 py-2"
              >
                <div>
                  <span className="font-medium">{accountType}</span>
                  <span className="ml-2 text-muted-foreground">{t.name ?? '(non connecté)'}</span>
                </div>
                <div className="flex items-center gap-2">
                  {t.connected ? (
                    <span className="text-xs px-2 py-0.5 rounded bg-green-100 text-green-700">
                      ✓ connecté{t.expires_in_days !== null ? ` (${t.expires_in_days}j)` : ''}
                    </span>
                  ) : (
                    <a
                      href={socialOAuthAuthorizeUrl(platform, accountType)}
                      className="text-xs px-2 py-0.5 rounded bg-violet text-white"
                    >
                      Connecter
                    </a>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Stats */}
      <section className="border border-border rounded-lg p-4 bg-surface">
        <h3 className="font-semibold text-sm mb-3">📊 Statistiques</h3>
        {stats.isLoading ? (
          <p className="text-sm text-muted-foreground">Chargement…</p>
        ) : stats.isError ? (
          <p className="text-sm text-red-600">Erreur chargement stats.</p>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            <StatCell label="Semaine" value={stats.data?.posts_this_week ?? 0} />
            <StatCell label="Programmés" value={stats.data?.posts_scheduled ?? 0} />
            <StatCell label="Publiés" value={stats.data?.posts_published ?? 0} />
            <StatCell label="Reach total" value={(stats.data?.total_reach ?? 0).toLocaleString()} />
            <StatCell label="Engagement moy." value={`${stats.data?.avg_engagement_rate ?? 0}%`} />
            <StatCell label="Meilleur jour" value={stats.data?.top_performing_day ?? '—'} />
            <StatCell label="Articles dispo" value={stats.data?.available_articles ?? 0} />
            <StatCell label="FAQs dispo" value={stats.data?.available_faqs ?? 0} />
          </div>
        )}
      </section>
    </div>
  );
}

// ── Placeholder for platforms not planned yet ──────────────────────────

function PlaceholderPanel({ config }: { config: PlatformConfig }) {
  return (
    <div
      className={`rounded-xl border p-10 ${config.bgColor} ${config.borderColor} flex flex-col items-center justify-center text-center gap-4 min-h-64`}
    >
      <span className="text-5xl">{config.emoji}</span>
      <div>
        <h2 className={`text-xl font-semibold mb-1 ${config.color}`}>{config.label}</h2>
        <p className="text-sm text-muted-foreground max-w-md">{config.description}</p>
      </div>
      <span className="mt-2 inline-flex items-center px-3 py-1 rounded-full bg-white/60 border border-border text-xs text-muted-foreground">
        Non planifié dans cette itération
      </span>
    </div>
  );
}

// ── Reusable stat cell ─────────────────────────────────────────────────

function StatCell({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="bg-white/60 border border-border rounded px-3 py-2">
      <div className="text-xs text-muted-foreground">{label}</div>
      <div className="text-lg font-semibold text-foreground">{value}</div>
    </div>
  );
}
