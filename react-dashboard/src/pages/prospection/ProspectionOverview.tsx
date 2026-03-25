import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';
import { CONTACT_TYPES, CONTACT_TYPE_MAP } from '../../lib/constants';

interface Stats {
  global: {
    total: number; pending_review: number; approved: number; sent: number;
    opened: number; clicked: number; replied: number; bounced: number; unsubscribed: number;
  };
  by_step: { step: number; total: number; sent: number }[];
  by_type: { contact_type: string; total: number; sent: number; opened?: number; clicked?: number; replied?: number; bounced?: number }[];
  warmup: { from_email: string; domain: string; day_count: number; emails_sent_today: number; current_daily_limit: number }[];
}

interface Alert { type: string; domain?: string; message: string }

const STEP_LABELS: Record<number, string> = { 1: 'Premier contact', 2: 'Relance J+3', 3: 'Relance J+7', 4: 'Dernier message J+14' };

function pct(n: number, d: number): string {
  return d > 0 ? (n / d * 100).toFixed(1) : '0';
}

export default function ProspectionOverview() {
  const [stats, setStats] = useState<Stats | null>(null);
  const [alerts, setAlerts] = useState<Alert[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const [s, a] = await Promise.all([
          api.get('/outreach/stats'),
          api.get('/outreach/alerts').catch(() => ({ data: [] })),
        ]);
        setStats(s.data);
        setAlerts(Array.isArray(a.data) ? a.data : []);
      } catch { /* ignore */ }
      setLoading(false);
    })();
  }, []);

  if (loading) return <div className="flex items-center justify-center h-64"><div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>;

  const g = stats?.global;
  const totalSent = g?.sent || 0;
  const openRate = pct(g?.opened || 0, totalSent);
  const clickRate = pct(g?.clicked || 0, totalSent);
  const replyRate = pct(g?.replied || 0, totalSent);
  const bounceRate = pct(g?.bounced || 0, totalSent);

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center gap-3">
        <Link to="/prospection" className="text-muted hover:text-white transition-colors text-sm">&larr; Prospection</Link>
        <h1 className="text-2xl font-title font-bold text-white">Vue d'ensemble</h1>
      </div>

      {/* Alerts */}
      {alerts.length > 0 && (
        <div className="space-y-2">
          {alerts.map((a, i) => (
            <div key={i} className={`rounded-lg p-3 flex items-center gap-3 ${a.type === 'critical' ? 'bg-red-500/10 border border-red-500/30' : 'bg-amber/10 border border-amber/30'}`}>
              <span className={a.type === 'critical' ? 'text-red-400' : 'text-amber'}>{a.type === 'critical' ? '🚨' : '⚠️'}</span>
              <span className={`text-sm ${a.type === 'critical' ? 'text-red-400' : 'text-amber'}`}>
                {a.domain && <span className="font-medium">{a.domain} — </span>}{a.message}
              </span>
            </div>
          ))}
        </div>
      )}

      {/* KPIs */}
      {g && (
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
          {[
            { label: 'Generes', value: g.total, color: 'text-white' },
            { label: 'En review', value: g.pending_review, color: g.pending_review > 0 ? 'text-amber' : 'text-muted' },
            { label: 'Envoyes', value: totalSent, color: 'text-cyan' },
            { label: 'Ouverts', value: g.opened, color: 'text-blue-400', sub: `${openRate}%` },
            { label: 'Cliques', value: g.clicked, color: 'text-emerald-400', sub: `${clickRate}%` },
            { label: 'Repondus', value: g.replied, color: 'text-green-400', sub: `${replyRate}%` },
            { label: 'Bounces', value: g.bounced, color: Number(bounceRate) > 5 ? 'text-red-400' : 'text-muted', sub: `${bounceRate}%` },
            { label: 'Desinscrits', value: g.unsubscribed, color: 'text-muted' },
          ].map((kpi, i) => (
            <div key={i} className="bg-surface border border-border rounded-xl p-3">
              <div className="text-[10px] text-muted uppercase tracking-wider mb-1">{kpi.label}</div>
              <div className="flex items-baseline gap-1">
                <span className={`text-xl font-bold font-title ${kpi.color}`}>{kpi.value}</span>
                {kpi.sub && <span className="text-[10px] text-muted">{kpi.sub}</span>}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Funnel */}
      {g && totalSent > 0 && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="text-white font-title font-semibold mb-4">Funnel de conversion</h3>
          <div className="space-y-2.5">
            {[
              { label: 'Generes', value: g.total, color: 'bg-gray-500' },
              { label: 'Envoyes', value: totalSent, color: 'bg-cyan' },
              { label: 'Ouverts', value: g.opened, color: 'bg-blue-500' },
              { label: 'Cliques', value: g.clicked, color: 'bg-emerald-500' },
              { label: 'Repondus', value: g.replied, color: 'bg-green-500' },
            ].map((step, i) => {
              const barPct = g.total > 0 ? (step.value / g.total * 100) : 0;
              const prevValue = i > 0 ? [g.total, totalSent, g.opened, g.clicked, g.replied][i - 1] : step.value;
              const dropoff = prevValue > 0 && i > 0 ? ((prevValue - step.value) / prevValue * 100).toFixed(0) : null;
              return (
                <div key={i} className="flex items-center gap-3">
                  <span className="text-xs text-muted w-20 text-right">{step.label}</span>
                  <div className="flex-1 bg-surface2 rounded-full h-7 relative overflow-hidden">
                    <div className={`h-7 rounded-full ${step.color} transition-all`} style={{ width: `${Math.max(barPct, 2)}%` }} />
                    <span className="absolute inset-0 flex items-center justify-center text-xs text-white font-mono">
                      {step.value} ({barPct.toFixed(0)}%)
                    </span>
                  </div>
                  {dropoff && Number(dropoff) > 0 && (
                    <span className="text-[10px] text-red-400/60 w-12 text-right">-{dropoff}%</span>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Step breakdown */}
        {stats?.by_step && stats.by_step.length > 0 && (
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="text-white font-title font-semibold mb-3">Performance par step</h3>
            <div className="space-y-3">
              {stats.by_step.map(s => {
                const sentPct = s.total > 0 ? Math.round(s.sent / s.total * 100) : 0;
                return (
                  <div key={s.step} className="flex items-center gap-3">
                    <div className={`w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold ${
                      s.step === 1 ? 'bg-cyan/20 text-cyan' :
                      s.step === 2 ? 'bg-blue-500/20 text-blue-400' :
                      s.step === 3 ? 'bg-violet/20 text-violet-light' :
                      'bg-purple-500/20 text-purple-400'
                    }`}>{s.step}</div>
                    <div className="flex-1 min-w-0">
                      <div className="flex justify-between text-xs mb-1">
                        <span className="text-white font-medium">{STEP_LABELS[s.step]}</span>
                        <span className="text-muted">{s.sent}/{s.total} envoyes</span>
                      </div>
                      <div className="w-full bg-surface2 rounded-full h-2">
                        <div className="h-2 rounded-full bg-cyan transition-all" style={{ width: `${sentPct}%` }} />
                      </div>
                    </div>
                    <span className="text-xs text-cyan font-mono w-10 text-right">{sentPct}%</span>
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* Performance par type */}
        {stats?.by_type && stats.by_type.length > 0 && (
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="text-white font-title font-semibold mb-3">Performance par type</h3>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border">
                    {['Type', 'Total', 'Envoyes', 'Taux'].map(h => (
                      <th key={h} className="text-left text-[10px] text-muted uppercase px-2 py-2">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {stats.by_type.map(t => {
                    const typeCfg = CONTACT_TYPE_MAP[t.contact_type];
                    const rate = t.total > 0 ? Math.round(t.sent / t.total * 100) : 0;
                    return (
                      <tr key={t.contact_type} className="border-b border-border/30 hover:bg-surface2/30 transition-colors">
                        <td className="px-2 py-2">
                          <span className="flex items-center gap-2">
                            {typeCfg && <span className="text-sm">{typeCfg.icon}</span>}
                            <span className="text-white text-xs">{typeCfg?.label || t.contact_type}</span>
                          </span>
                        </td>
                        <td className="px-2 py-2 text-right text-muted font-mono text-xs">{t.total}</td>
                        <td className="px-2 py-2 text-right text-cyan font-mono text-xs">{t.sent}</td>
                        <td className="px-2 py-2 text-right">
                          <span className={`font-mono text-xs ${rate >= 80 ? 'text-emerald-400' : rate >= 50 ? 'text-amber' : 'text-red-400'}`}>{rate}%</span>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>

      {/* Warmup status */}
      {stats?.warmup && stats.warmup.length > 0 && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="text-white font-title font-semibold mb-3">Warm-up domaines</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            {stats.warmup.map(w => {
              const p = Math.min(w.emails_sent_today / Math.max(w.current_daily_limit, 1) * 100, 100);
              return (
                <div key={w.from_email} className="bg-surface2 rounded-lg p-3">
                  <div className="flex justify-between items-center mb-2">
                    <span className="text-xs text-white font-medium">{w.domain}</span>
                    <span className="text-[10px] text-muted">Jour {w.day_count}</span>
                  </div>
                  <div className="flex justify-between text-xs mb-1">
                    <span className="text-muted">{w.from_email}</span>
                    <span className={`font-mono ${p >= 100 ? 'text-amber' : 'text-cyan'}`}>{w.emails_sent_today}/{w.current_daily_limit}</span>
                  </div>
                  <div className="w-full bg-bg rounded-full h-2">
                    <div className={`h-2 rounded-full transition-all ${p >= 100 ? 'bg-amber' : 'bg-cyan'}`}
                      style={{ width: `${p}%` }} />
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {g && g.total === 0 && (
        <div className="text-center py-16 text-muted">
          <p className="text-5xl mb-4">✉️</p>
          <p className="text-white font-medium text-lg">Aucun email genere</p>
          <p className="text-sm mt-2">Commencez par <Link to="/prospection/emails" className="text-violet-light hover:underline">generer des emails</Link></p>
        </div>
      )}
    </div>
  );
}
