import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';

interface Stats {
  global: { total: number; pending_review: number; approved: number; sent: number; opened: number; clicked: number; replied: number; bounced: number; unsubscribed: number };
  by_step: { step: number; total: number; sent: number }[];
  by_type: { contact_type: string; total: number; sent: number }[];
  warmup: { from_email: string; domain: string; day_count: number; emails_sent_today: number; current_daily_limit: number }[];
}

interface Alert { type: string; domain?: string; message: string }

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
  const totalSent = (g?.sent || 0);
  const bounceRate = totalSent > 0 ? ((g?.bounced || 0) / totalSent * 100).toFixed(1) : '0';
  const clickRate = totalSent > 0 ? ((g?.clicked || 0) / totalSent * 100).toFixed(1) : '0';
  const replyRate = totalSent > 0 ? ((g?.replied || 0) / totalSent * 100).toFixed(1) : '0';

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center gap-3">
        <Link to="/prospection" className="text-muted hover:text-white transition-colors text-sm">← Prospection</Link>
        <h1 className="text-2xl font-title font-bold text-white">Vue d'ensemble</h1>
      </div>

      {/* Alerts */}
      {alerts.length > 0 && (
        <div className="space-y-2">
          {alerts.map((a, i) => (
            <div key={i} className={`rounded-lg p-3 flex items-center gap-3 ${a.type === 'critical' ? 'bg-red-500/10 border border-red-500/30' : 'bg-amber/10 border border-amber/30'}`}>
              <span className={a.type === 'critical' ? 'text-red-400' : 'text-amber'}>{a.type === 'critical' ? '🚨' : '⚠️'}</span>
              <span className={`text-sm ${a.type === 'critical' ? 'text-red-400' : 'text-amber'}`}>
                {a.domain && <span className="font-medium">{a.domain} — </span>}
                {a.message}
              </span>
            </div>
          ))}
        </div>
      )}

      {/* KPIs */}
      {g && (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          {[
            { label: 'Envoyes', value: totalSent, color: 'text-cyan' },
            { label: 'Cliques', value: g.clicked, color: 'text-emerald-400', sub: `${clickRate}%` },
            { label: 'Repondus', value: g.replied, color: 'text-green-400', sub: `${replyRate}%` },
            { label: 'En review', value: g.pending_review, color: g.pending_review > 0 ? 'text-amber' : 'text-muted' },
            { label: 'Bounces', value: g.bounced, color: Number(bounceRate) > 5 ? 'text-red-400' : 'text-muted', sub: `${bounceRate}%` },
            { label: 'Desinscrits', value: g.unsubscribed, color: 'text-muted' },
          ].map((kpi, i) => (
            <div key={i} className="bg-surface border border-border rounded-xl p-4">
              <div className="text-[10px] text-muted uppercase tracking-wider mb-1">{kpi.label}</div>
              <div className="flex items-baseline gap-2">
                <span className={`text-2xl font-bold font-title ${kpi.color}`}>{kpi.value}</span>
                {kpi.sub && <span className="text-xs text-muted">{kpi.sub}</span>}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Funnel */}
      {g && totalSent > 0 && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="text-white font-title font-semibold mb-4">Funnel de conversion</h3>
          <div className="space-y-3">
            {[
              { label: 'Generes', value: g.total, color: 'bg-gray-500' },
              { label: 'Envoyes', value: totalSent, color: 'bg-cyan' },
              { label: 'Cliques', value: g.clicked, color: 'bg-emerald-500' },
              { label: 'Repondus', value: g.replied, color: 'bg-green-500' },
            ].map((step, i) => {
              const pct = g.total > 0 ? (step.value / g.total * 100) : 0;
              return (
                <div key={i} className="flex items-center gap-3">
                  <span className="text-xs text-muted w-20 text-right">{step.label}</span>
                  <div className="flex-1 bg-surface2 rounded-full h-6 relative">
                    <div className={`h-6 rounded-full ${step.color} transition-all`} style={{ width: `${Math.max(pct, 2)}%` }} />
                    <span className="absolute inset-0 flex items-center justify-center text-xs text-white font-mono">{step.value} ({pct.toFixed(0)}%)</span>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* Performance par type */}
        {stats?.by_type && stats.by_type.length > 0 && (
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="text-white font-title font-semibold mb-3">Performance par type</h3>
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border">
                  <th className="text-left text-[10px] text-muted uppercase px-2 py-2">Type</th>
                  <th className="text-right text-[10px] text-muted uppercase px-2 py-2">Total</th>
                  <th className="text-right text-[10px] text-muted uppercase px-2 py-2">Envoyes</th>
                  <th className="text-right text-[10px] text-muted uppercase px-2 py-2">Taux</th>
                </tr>
              </thead>
              <tbody>
                {stats.by_type.map(t => (
                  <tr key={t.contact_type} className="border-b border-border/30">
                    <td className="px-2 py-2 text-white text-xs">{t.contact_type}</td>
                    <td className="px-2 py-2 text-right text-muted font-mono">{t.total}</td>
                    <td className="px-2 py-2 text-right text-cyan font-mono">{t.sent}</td>
                    <td className="px-2 py-2 text-right text-emerald-400 font-mono">{t.total > 0 ? Math.round(t.sent / t.total * 100) : 0}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Warmup status */}
        {stats?.warmup && stats.warmup.length > 0 && (
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="text-white font-title font-semibold mb-3">Warm-up domaines</h3>
            <div className="space-y-3">
              {stats.warmup.map(w => (
                <div key={w.from_email} className="bg-surface2 rounded-lg p-3">
                  <div className="flex justify-between items-center mb-2">
                    <span className="text-xs text-white font-medium">{w.domain}</span>
                    <span className="text-[10px] text-muted">Jour {w.day_count}</span>
                  </div>
                  <div className="flex justify-between text-xs mb-1">
                    <span className="text-muted">{w.from_email}</span>
                    <span className="text-cyan font-mono">{w.emails_sent_today}/{w.current_daily_limit}</span>
                  </div>
                  <div className="w-full bg-bg rounded-full h-2">
                    <div className={`h-2 rounded-full transition-all ${w.emails_sent_today >= w.current_daily_limit ? 'bg-amber' : 'bg-cyan'}`}
                      style={{ width: `${Math.min(w.emails_sent_today / Math.max(w.current_daily_limit, 1) * 100, 100)}%` }} />
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>

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
