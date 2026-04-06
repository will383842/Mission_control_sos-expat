import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../../services/api';

/**
 * Content Orchestrator — Global content generation dashboard.
 *
 * Single page to pilot ALL content generation:
 * - Daily quota tracking (20/day limit)
 * - Content type distribution
 * - Priority country queue
 * - Real-time generation status
 * - Quick actions for each content type
 */

interface DailyStats {
  total: number;
  limit: number;
  by_type: Record<string, number>;
  cost_cents: number;
  last_at: string | null;
}

interface QueueItem {
  type: string;
  id: number;
  title: string;
  country: string | null;
  priority_score: number;
}

const TYPE_CONFIG: Record<string, { label: string; icon: string; limit: number; route: string; color: string }> = {
  qa:           { label: 'Q/R',           icon: '❓', limit: 8,  route: '/content/generate-qr',       color: 'from-blue-500 to-blue-600' },
  news:         { label: 'News RSS',      icon: '📰', limit: 5,  route: '/content/news',              color: 'from-emerald-500 to-emerald-600' },
  article:      { label: 'Articles',      icon: '📝', limit: 4,  route: '/content/art-mots-cles',     color: 'from-violet-500 to-violet-600' },
  guide:        { label: 'Fiches Pays',   icon: '🌍', limit: 2,  route: '/content/fiches-general',    color: 'from-amber-500 to-amber-600' },
  comparative:  { label: 'Comparatifs',   icon: '⚖️', limit: 3,  route: '/content/comparatives',      color: 'from-rose-500 to-rose-600' },
};

const PRIORITY_COUNTRIES = [
  { code: 'FR', name: 'France', tier: 1 },
  { code: 'US', name: 'USA', tier: 1 },
  { code: 'GB', name: 'UK', tier: 1 },
  { code: 'ES', name: 'Espagne', tier: 1 },
  { code: 'DE', name: 'Allemagne', tier: 1 },
  { code: 'TH', name: 'Thaïlande', tier: 1 },
  { code: 'PT', name: 'Portugal', tier: 1 },
  { code: 'CA', name: 'Canada', tier: 2 },
  { code: 'AU', name: 'Australie', tier: 2 },
  { code: 'IT', name: 'Italie', tier: 2 },
  { code: 'AE', name: 'Dubai/EAU', tier: 2 },
  { code: 'JP', name: 'Japon', tier: 2 },
  { code: 'SG', name: 'Singapour', tier: 2 },
  { code: 'MA', name: 'Maroc', tier: 2 },
];

export default function ContentOrchestrator() {
  const navigate = useNavigate();
  const [stats, setStats] = useState<DailyStats | null>(null);
  const [queue, setQueue] = useState<QueueItem[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchData = useCallback(async () => {
    try {
      setLoading(true);
      const [statsRes, queueRes] = await Promise.all([
        api.get('/content/scheduler/today').catch(() => ({ data: null })),
        api.get('/content/scheduler/next-batch?limit=10').catch(() => ({ data: [] })),
      ]);
      if (statsRes.data) setStats(statsRes.data);
      if (queueRes.data) setQueue(Array.isArray(queueRes.data) ? queueRes.data : []);
    } catch {
      // Silent fail — show empty state
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);

  const dailyTotal = stats?.total ?? 0;
  const dailyLimit = stats?.limit ?? 20;
  const dailyPct = Math.min(100, Math.round((dailyTotal / dailyLimit) * 100));
  const dailyCost = ((stats?.cost_cents ?? 0) / 100).toFixed(2);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">Content Orchestrator</h1>
          <p className="text-muted text-sm mt-1">Pilotage global de la génération de contenu SOS-Expat.com</p>
        </div>
        <button
          onClick={fetchData}
          disabled={loading}
          className="px-4 py-2 rounded-lg bg-surface border border-border/30 text-sm text-muted hover:text-white transition-all"
        >
          {loading ? '⏳' : '🔄'} Rafraîchir
        </button>
      </div>

      {/* Daily Quota Bar */}
      <div className="bg-surface/60 backdrop-blur border border-border/20 rounded-2xl p-6">
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-lg font-semibold text-white">Quota journalier</h2>
          <div className="flex items-center gap-4 text-sm">
            <span className="text-muted">Coût : <span className="text-white font-medium">${dailyCost}</span></span>
            <span className={`font-bold ${dailyPct >= 90 ? 'text-red-400' : dailyPct >= 60 ? 'text-amber-400' : 'text-emerald-400'}`}>
              {dailyTotal} / {dailyLimit}
            </span>
          </div>
        </div>
        <div className="w-full h-3 bg-bg rounded-full overflow-hidden">
          <div
            className={`h-full rounded-full transition-all duration-500 ${
              dailyPct >= 90 ? 'bg-red-500' : dailyPct >= 60 ? 'bg-amber-500' : 'bg-emerald-500'
            }`}
            style={{ width: `${dailyPct}%` }}
          />
        </div>
      </div>

      {/* Content Type Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
        {Object.entries(TYPE_CONFIG).map(([type, config]) => {
          const count = stats?.by_type?.[type] ?? 0;
          const pct = Math.min(100, Math.round((count / config.limit) * 100));
          return (
            <button
              key={type}
              onClick={() => navigate(config.route)}
              className="bg-surface/60 backdrop-blur border border-border/20 rounded-xl p-4 text-left hover:border-violet/30 transition-all group"
            >
              <div className="flex items-center gap-2 mb-2">
                <span className="text-xl">{config.icon}</span>
                <span className="text-sm font-medium text-white group-hover:text-violet-light transition-colors">
                  {config.label}
                </span>
              </div>
              <div className="text-2xl font-bold text-white mb-1">{count}</div>
              <div className="w-full h-1.5 bg-bg rounded-full overflow-hidden">
                <div
                  className={`h-full rounded-full bg-gradient-to-r ${config.color}`}
                  style={{ width: `${pct}%` }}
                />
              </div>
              <div className="text-[11px] text-muted mt-1">
                {count}/{config.limit} aujourd'hui
              </div>
            </button>
          );
        })}
      </div>

      {/* Two-column: Priority Queue + Priority Countries */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Next in Queue */}
        <div className="bg-surface/60 backdrop-blur border border-border/20 rounded-2xl p-6">
          <h2 className="text-lg font-semibold text-white mb-4">🎯 Prochains à générer</h2>
          {queue.length === 0 ? (
            <p className="text-muted text-sm">Aucun article en attente.</p>
          ) : (
            <div className="space-y-2 max-h-80 overflow-y-auto">
              {queue.map((item, i) => (
                <div
                  key={`${item.type}-${item.id}`}
                  className="flex items-center gap-3 p-3 rounded-lg bg-bg/50 hover:bg-bg/80 transition-colors"
                >
                  <span className="text-lg">{TYPE_CONFIG[item.type]?.icon ?? '📄'}</span>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm text-white truncate">{item.title}</p>
                    <p className="text-[11px] text-muted">
                      {item.country && <span className="mr-2">🏳️ {item.country}</span>}
                      Score: {item.priority_score}
                    </p>
                  </div>
                  <span className="text-[10px] text-muted font-mono">#{i + 1}</span>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Priority Countries */}
        <div className="bg-surface/60 backdrop-blur border border-border/20 rounded-2xl p-6">
          <h2 className="text-lg font-semibold text-white mb-4">🌍 Pays prioritaires</h2>
          <div className="grid grid-cols-2 gap-2">
            {PRIORITY_COUNTRIES.map((c) => (
              <div
                key={c.code}
                className={`flex items-center gap-2 p-2.5 rounded-lg text-sm ${
                  c.tier === 1
                    ? 'bg-violet/10 border border-violet/20 text-violet-light'
                    : 'bg-bg/50 border border-border/10 text-muted'
                }`}
              >
                <span className="font-mono text-[11px] font-bold">{c.code}</span>
                <span className="truncate">{c.name}</span>
                {c.tier === 1 && <span className="ml-auto text-[10px]">⭐</span>}
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Quick Actions */}
      <div className="bg-surface/60 backdrop-blur border border-border/20 rounded-2xl p-6">
        <h2 className="text-lg font-semibold text-white mb-4">⚡ Actions rapides</h2>
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <button
            onClick={() => navigate('/content/generate-qr')}
            className="p-4 rounded-xl bg-bg/50 border border-border/20 hover:border-violet/30 transition-all text-left"
          >
            <span className="text-2xl">❓</span>
            <p className="text-sm font-medium text-white mt-2">Générer Q/R</p>
            <p className="text-[11px] text-muted">Depuis stock questions</p>
          </button>
          <button
            onClick={() => navigate('/content/news')}
            className="p-4 rounded-xl bg-bg/50 border border-border/20 hover:border-violet/30 transition-all text-left"
          >
            <span className="text-2xl">📰</span>
            <p className="text-sm font-medium text-white mt-2">Flux RSS</p>
            <p className="text-[11px] text-muted">Actualités auto</p>
          </button>
          <button
            onClick={() => navigate('/content/fiches-general')}
            className="p-4 rounded-xl bg-bg/50 border border-border/20 hover:border-violet/30 transition-all text-left"
          >
            <span className="text-2xl">🌍</span>
            <p className="text-sm font-medium text-white mt-2">Fiches Pays</p>
            <p className="text-[11px] text-muted">197 pays × 3 types</p>
          </button>
          <button
            onClick={() => navigate('/content/articles')}
            className="p-4 rounded-xl bg-bg/50 border border-border/20 hover:border-violet/30 transition-all text-left"
          >
            <span className="text-2xl">✍️</span>
            <p className="text-sm font-medium text-white mt-2">Article Manuel</p>
            <p className="text-[11px] text-muted">Titre libre</p>
          </button>
        </div>
      </div>
    </div>
  );
}
