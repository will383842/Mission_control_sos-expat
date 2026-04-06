import React, { useState, useEffect, useCallback } from 'react';
import api from '../../services/api';

/**
 * Content Orchestrator — Full auto-pilot content generation dashboard.
 *
 * Configure: daily target, % distribution per type, auto-pilot on/off.
 * Monitor: real-time progress, daily plan, priority queue.
 * Control: start/pause/stop generation.
 */

interface OrchestratorConfig {
  daily_target: number;
  auto_pilot: boolean;
  type_distribution: Record<string, number>;
  priority_countries: string[];
  status: 'running' | 'paused' | 'stopped';
  last_run_at: string | null;
  today_generated: number;
  today_cost_cents: number;
  type_labels: Record<string, string>;
}

interface DailyPlan {
  target: number;
  generated: number;
  remaining: number;
  plan: Array<{ type: string; label: string; count: number; pct: number }>;
  auto_pilot: boolean;
  status: string;
}

interface QueueItem {
  type: string;
  id: number;
  title: string;
  country: string | null;
  priority_score: number;
}

const TYPE_ICONS: Record<string, string> = {
  qa: '❓', art_mots_cles: '🔑', art_longues_traines: '🎯',
  guide: '🌍', guide_expat: '✈️', guide_vacances: '🏖️', guide_city: '🏙️',
  comparative: '⚖️', affiliation: '💰',
  outreach_chatters: '💬', outreach_influenceurs: '📢', outreach_admin_groupes: '👥',
  outreach_avocats: '⚖️', outreach_expats: '🧳',
  testimonial: '💬', brand_content: '🏷️',
};

const TYPE_COLORS: Record<string, string> = {
  qa: 'bg-blue-500', art_mots_cles: 'bg-violet-500', art_longues_traines: 'bg-indigo-500',
  guide: 'bg-amber-500', guide_expat: 'bg-orange-500', guide_vacances: 'bg-yellow-500', guide_city: 'bg-sky-500',
  comparative: 'bg-rose-500', affiliation: 'bg-emerald-500',
  outreach_chatters: 'bg-cyan-500', outreach_influenceurs: 'bg-teal-500', outreach_admin_groupes: 'bg-lime-500',
  outreach_avocats: 'bg-green-500', outreach_expats: 'bg-fuchsia-500',
  testimonial: 'bg-pink-500', brand_content: 'bg-purple-500',
};

export default function ContentOrchestrator() {
  const [config, setConfig] = useState<OrchestratorConfig | null>(null);
  const [plan, setPlan] = useState<DailyPlan | null>(null);
  const [queue, setQueue] = useState<QueueItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [editTarget, setEditTarget] = useState(20);
  const [editDist, setEditDist] = useState<Record<string, number>>({});

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const [cfgRes, planRes, queueRes] = await Promise.all([
        api.get('/content/orchestrator/config').catch(() => ({ data: null })),
        api.get('/content/orchestrator/daily-plan').catch(() => ({ data: null })),
        api.get('/content/scheduler/next-batch?limit=10').catch(() => ({ data: [] })),
      ]);
      if (cfgRes.data) {
        setConfig(cfgRes.data);
        setEditTarget(cfgRes.data.daily_target);
        setEditDist(cfgRes.data.type_distribution);
      }
      if (planRes.data) setPlan(planRes.data);
      if (queueRes.data) setQueue(Array.isArray(queueRes.data) ? queueRes.data : []);
    } catch { /* silent */ }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);

  const saveConfig = async (partial: Partial<OrchestratorConfig>) => {
    setSaving(true);
    try {
      const res = await api.put('/content/orchestrator/config', partial);
      setConfig(res.data);
      fetchData();
    } catch { /* silent */ }
    finally { setSaving(false); }
  };

  const handleSaveDistribution = () => {
    const total = Object.values(editDist).reduce((s, v) => s + v, 0);
    if (total !== 100) {
      alert(`Le total des pourcentages doit faire 100% (actuellement ${total}%)`);
      return;
    }
    saveConfig({ daily_target: editTarget, type_distribution: editDist });
  };

  const handleToggleAutoPilot = () => {
    if (!config) return;
    const newStatus = config.status === 'running' ? 'paused' : 'running';
    saveConfig({ auto_pilot: newStatus === 'running', status: newStatus });
  };

  const handleStop = () => saveConfig({ auto_pilot: false, status: 'stopped' });

  if (loading || !config) {
    return <div className="flex items-center justify-center h-64 text-muted">Chargement...</div>;
  }

  const pct = config.daily_target > 0 ? Math.round((config.today_generated / config.daily_target) * 100) : 0;
  const distTotal = Object.values(editDist).reduce((s, v) => s + v, 0);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">🎯 Content Orchestrator</h1>
          <p className="text-muted text-sm mt-1">Pilotage automatique de la generation de contenu SOS-Expat.com</p>
        </div>
        <div className="flex gap-2">
          <button onClick={handleToggleAutoPilot} disabled={saving}
            className={`px-5 py-2.5 rounded-xl font-semibold text-sm transition-all ${
              config.status === 'running'
                ? 'bg-amber-500 text-black hover:bg-amber-400'
                : 'bg-emerald-500 text-white hover:bg-emerald-400'
            }`}>
            {config.status === 'running' ? '⏸️ Pause' : '▶️ Lancer Auto-Pilot'}
          </button>
          {config.status === 'running' && (
            <button onClick={handleStop} className="px-4 py-2.5 rounded-xl bg-red-600 text-white text-sm font-semibold hover:bg-red-500">
              ⏹️ Stop
            </button>
          )}
          <button onClick={fetchData} className="px-4 py-2.5 rounded-xl bg-surface border border-border/30 text-sm text-muted hover:text-white">
            🔄
          </button>
        </div>
      </div>

      {/* Status Banner */}
      <div className={`rounded-xl p-4 flex items-center gap-4 ${
        config.status === 'running' ? 'bg-emerald-500/10 border border-emerald-500/30' :
        config.status === 'paused' ? 'bg-amber-500/10 border border-amber-500/30' :
        'bg-red-500/10 border border-red-500/30'
      }`}>
        <span className="text-3xl">{config.status === 'running' ? '🟢' : config.status === 'paused' ? '🟡' : '🔴'}</span>
        <div>
          <p className="text-white font-semibold">
            {config.status === 'running' ? 'Auto-Pilot ACTIF — Generation automatique en cours' :
             config.status === 'paused' ? 'En pause — La generation est suspendue' :
             'Arrete — Aucune generation automatique'}
          </p>
          <p className="text-muted text-sm">
            {config.today_generated}/{config.daily_target} articles aujourd'hui — ${(config.today_cost_cents / 100).toFixed(2)} depense
            {config.last_run_at && ` — Derniere gen: ${new Date(config.last_run_at).toLocaleTimeString('fr-FR')}`}
          </p>
        </div>
      </div>

      {/* RSS Independent Section */}
      <div className="bg-surface/60 border border-border/20 rounded-xl p-5 flex items-center gap-4">
        <span className="text-2xl">📰</span>
        <div className="flex-1">
          <p className="text-white font-semibold">News RSS (independant)</p>
          <p className="text-muted text-xs">{config.today_rss_generated ?? 0}/{config.rss_daily_target ?? 10} news aujourd'hui — auto fetch 4h + generation 08:00</p>
        </div>
        <div className="flex items-center gap-2">
          <input type="number" min="0" max="10000"
            value={config.rss_daily_target ?? 10}
            onChange={e => saveConfig({ rss_daily_target: parseInt(e.target.value) || 10 })}
            className="w-20 bg-bg border border-border rounded-lg px-3 py-2 text-white text-center text-sm" />
          <span className="text-xs text-muted">/jour</span>
        </div>
      </div>

      {/* Telegram Alerts Toggle */}
      <div className="bg-surface/60 border border-border/20 rounded-xl p-4 flex items-center gap-4">
        <span className="text-xl">🔔</span>
        <div className="flex-1">
          <p className="text-white text-sm font-medium">Alertes Telegram</p>
          <p className="text-muted text-xs">Notification en cas d'erreur, quota atteint, ou compte API vide</p>
        </div>
        <button onClick={() => saveConfig({ telegram_alerts: !config.telegram_alerts })}
          className={`px-4 py-1.5 rounded-lg text-xs font-semibold transition-all ${
            config.telegram_alerts ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-muted/10 text-muted border border-border/20'
          }`}>
          {config.telegram_alerts ? '✅ Actif' : '⭕ Desactive'}
        </button>
      </div>

      {/* Progress Bar */}
      <div className="bg-surface/60 border border-border/20 rounded-xl p-6">
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-lg font-semibold text-white">Progression journaliere</h2>
          <span className={`text-2xl font-bold ${pct >= 90 ? 'text-red-400' : pct >= 60 ? 'text-amber-400' : 'text-emerald-400'}`}>
            {config.today_generated} / {config.daily_target}
          </span>
        </div>
        <div className="w-full h-4 bg-bg rounded-full overflow-hidden">
          <div className={`h-full rounded-full transition-all duration-700 ${
            pct >= 90 ? 'bg-red-500' : pct >= 60 ? 'bg-amber-500' : 'bg-emerald-500'
          }`} style={{ width: `${Math.min(100, pct)}%` }} />
        </div>
        {/* Type breakdown */}
        {plan && plan.plan.length > 0 && (
          <div className="flex gap-1 mt-3 h-2 rounded-full overflow-hidden bg-bg">
            {plan.plan.map(p => (
              <div key={p.type} className={`${TYPE_COLORS[p.type] ?? 'bg-muted'} transition-all`}
                style={{ width: `${p.pct}%` }} title={`${p.label}: ${p.count} articles (${p.pct}%)`} />
            ))}
          </div>
        )}
      </div>

      {/* Configuration */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Daily Target + Distribution */}
        <div className="bg-surface/60 border border-border/20 rounded-xl p-6">
          <h2 className="text-lg font-semibold text-white mb-4">Configuration</h2>

          {/* Daily Target */}
          <div className="mb-6">
            <label className="text-sm text-muted mb-2 block">Articles par jour</label>
            <div className="flex items-center gap-3">
              <input type="range" min="1" max="200" value={editTarget}
                onChange={e => setEditTarget(parseInt(e.target.value))}
                className="flex-1 accent-violet" />
              <input type="number" min="1" max="1000" value={editTarget}
                onChange={e => setEditTarget(Math.max(1, parseInt(e.target.value) || 1))}
                className="w-20 bg-bg border border-border rounded-lg px-3 py-2 text-white text-center text-sm" />
            </div>
          </div>

          {/* Type Distribution */}
          <div className="space-y-3">
            <div className="flex justify-between items-center">
              <label className="text-sm text-muted">Repartition par type (%)</label>
              <span className={`text-xs font-bold ${distTotal === 100 ? 'text-emerald-400' : 'text-red-400'}`}>
                Total: {distTotal}%
              </span>
            </div>
            {Object.entries(config.type_labels).map(([type, label]) => (
              <div key={type} className="flex items-center gap-3">
                <span className="text-lg w-6">{TYPE_ICONS[type] ?? '📄'}</span>
                <span className="text-sm text-white w-36 truncate">{label}</span>
                <input type="range" min="0" max="50" value={editDist[type] ?? 0}
                  onChange={e => setEditDist(d => ({ ...d, [type]: parseInt(e.target.value) }))}
                  className="flex-1 accent-violet" />
                <input type="number" min="0" max="100" value={editDist[type] ?? 0}
                  onChange={e => setEditDist(d => ({ ...d, [type]: Math.max(0, parseInt(e.target.value) || 0) }))}
                  className="w-14 bg-bg border border-border rounded-lg px-2 py-1.5 text-white text-center text-xs" />
                <span className="text-xs text-muted w-8">%</span>
              </div>
            ))}
          </div>

          <button onClick={handleSaveDistribution} disabled={saving || distTotal !== 100}
            className="mt-4 w-full px-4 py-2.5 rounded-xl bg-violet text-white font-semibold text-sm hover:bg-violet/80 transition-all disabled:opacity-40">
            {saving ? '⏳' : '💾'} Sauvegarder la configuration
          </button>
        </div>

        {/* Daily Plan + Queue */}
        <div className="space-y-6">
          {/* Today's Plan */}
          <div className="bg-surface/60 border border-border/20 rounded-xl p-6">
            <h2 className="text-lg font-semibold text-white mb-3">Plan du jour</h2>
            {plan && plan.plan.length > 0 ? (
              <div className="space-y-2">
                {plan.plan.map(p => (
                  <div key={p.type} className="flex items-center gap-3 p-2 rounded-lg bg-bg/50">
                    <span className="text-lg">{TYPE_ICONS[p.type] ?? '📄'}</span>
                    <span className="text-sm text-white flex-1">{p.label}</span>
                    <span className="text-sm font-bold text-violet-light">{p.count}</span>
                    <span className="text-xs text-muted">{p.pct}%</span>
                  </div>
                ))}
                <div className="flex justify-between pt-2 border-t border-border/20 mt-2">
                  <span className="text-sm text-muted">Restant aujourd'hui</span>
                  <span className="text-sm font-bold text-white">{plan.remaining} articles</span>
                </div>
              </div>
            ) : (
              <p className="text-muted text-sm">Objectif journalier atteint ou pas de plan configure.</p>
            )}
          </div>

          {/* Priority Queue */}
          <div className="bg-surface/60 border border-border/20 rounded-xl p-6">
            <h2 className="text-lg font-semibold text-white mb-3">Prochains a generer</h2>
            {queue.length > 0 ? (
              <div className="space-y-2 max-h-60 overflow-y-auto">
                {queue.map((item, i) => (
                  <div key={`${item.type}-${item.id}`} className="flex items-center gap-3 p-2 rounded-lg bg-bg/50">
                    <span className="text-xs text-muted font-mono w-5">#{i + 1}</span>
                    <span>{TYPE_ICONS[item.type] ?? '📄'}</span>
                    <span className="text-sm text-white flex-1 truncate">{item.title}</span>
                    {item.country && <span className="text-xs text-muted">{item.country}</span>}
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-muted text-sm">File d'attente vide.</p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
