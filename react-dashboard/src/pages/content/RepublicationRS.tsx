import React from 'react';
import { useNavigate, useParams } from 'react-router-dom';

// ── Types ──────────────────────────────────────────────────────────────

type Platform = 'linkedin' | 'pinterest' | 'threads' | 'facebook' | 'instagram' | 'reddit';

interface PlatformConfig {
  id: Platform;
  label: string;
  emoji: string;
  color: string;
  bgColor: string;
  borderColor: string;
  description: string;
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
    description: 'Republication des articles et actualités sur LinkedIn',
  },
  {
    id: 'pinterest',
    label: 'Pinterest',
    emoji: '📌',
    color: 'text-red-600',
    bgColor: 'bg-red-50',
    borderColor: 'border-red-200',
    description: 'Épingler les visuels et contenus sur Pinterest',
  },
  {
    id: 'threads',
    label: 'Threads',
    emoji: '🧵',
    color: 'text-gray-800',
    bgColor: 'bg-gray-50',
    borderColor: 'border-gray-200',
    description: 'Publier des fils de discussion sur Threads',
  },
  {
    id: 'facebook',
    label: 'Facebook',
    emoji: '📘',
    color: 'text-blue-600',
    bgColor: 'bg-blue-50',
    borderColor: 'border-blue-100',
    description: 'Partager les contenus sur la page Facebook SOS Expat',
  },
  {
    id: 'instagram',
    label: 'Instagram',
    emoji: '📸',
    color: 'text-pink-600',
    bgColor: 'bg-pink-50',
    borderColor: 'border-pink-200',
    description: 'Publier photos et stories sur Instagram',
  },
  {
    id: 'reddit',
    label: 'Reddit',
    emoji: '🤖',
    color: 'text-orange-600',
    bgColor: 'bg-orange-50',
    borderColor: 'border-orange-200',
    description: 'Partager les articles dans les subreddits ciblés',
  },
];

// ── Component ──────────────────────────────────────────────────────────

export default function RepublicationRS() {
  const { platform = 'linkedin' } = useParams<{ platform: Platform }>();
  const navigate = useNavigate();

  const active = PLATFORMS.find((p) => p.id === platform) ?? PLATFORMS[0];

  return (
    <div className="p-6 max-w-5xl mx-auto space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-foreground">📣 Republication RS</h1>
        <p className="text-muted-foreground text-sm mt-1">
          Republication automatique des contenus sur les réseaux sociaux
        </p>
      </div>

      {/* Tabs */}
      <div className="flex flex-wrap gap-2 border-b border-border pb-3">
        {PLATFORMS.map((p) => {
          const isActive = p.id === active.id;
          return (
            <button
              key={p.id}
              onClick={() => navigate(`/content/republication-rs/${p.id}`)}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-colors ${
                isActive
                  ? 'bg-violet text-white'
                  : 'bg-surface text-muted-foreground hover:text-foreground hover:bg-surface/80 border border-border'
              }`}
            >
              <span>{p.emoji}</span>
              {p.label}
            </button>
          );
        })}
      </div>

      {/* Content area */}
      <div className={`rounded-xl border p-10 ${active.bgColor} ${active.borderColor} flex flex-col items-center justify-center text-center gap-4 min-h-64`}>
        <span className="text-5xl">{active.emoji}</span>
        <div>
          <h2 className={`text-xl font-semibold mb-1 ${active.color}`}>{active.label}</h2>
          <p className="text-sm text-muted-foreground max-w-md">{active.description}</p>
        </div>
        <span className="mt-2 inline-flex items-center px-3 py-1 rounded-full bg-white/60 border border-border text-xs text-muted-foreground">
          Fonctionnalité en cours de développement
        </span>
      </div>
    </div>
  );
}
