import React from 'react';
import {
  AreaChart, Area, BarChart, Bar, PieChart, Pie, Cell,
  // FunnelChart and Funnel are not available in recharts v3 — using custom bar-based funnel below
  // FunnelChart, Funnel, LabelList,
  XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer
} from 'recharts';
import { useStats } from '../hooks/useStats';

const COLORS = ['#7c3aed', '#06b6d4', '#f59e0b', '#10b981', '#ef4444', '#6b7280'];

export default function Statistiques() {
  const { stats, loading } = useStats();

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  if (!stats) return null;

  const statusData = Object.entries(stats.byStatus).map(([name, value]) => ({ name, value }));
  const tooltipStyle = { backgroundColor: '#101419', border: '1px solid #1e2530', borderRadius: 8, color: '#e2e8f0' };

  return (
    <div className="p-6 space-y-6">
      <h2 className="font-title text-2xl font-bold text-white">Statistiques</h2>

      {/* KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Total', value: stats.total, color: 'text-white' },
          { label: 'Taux de réponse', value: `${stats.responseRate}%`, color: 'text-cyan' },
          { label: 'Taux de conversion', value: `${stats.conversionRate}%`, color: 'text-violet-light' },
          { label: 'Actifs', value: stats.active, color: 'text-green-400' },
        ].map(kpi => (
          <div key={kpi.label} className="bg-surface border border-border rounded-xl p-5">
            <p className="text-muted text-xs">{kpi.label}</p>
            <p className={`text-3xl font-title font-bold mt-1 ${kpi.color}`}>{kpi.value}</p>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Évolution contacts */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">Évolution des contacts (12 semaines)</h3>
          <ResponsiveContainer width="100%" height={200}>
            <AreaChart data={stats.contactsEvolution}>
              <defs>
                <linearGradient id="gradViolet" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#7c3aed" stopOpacity={0.3} />
                  <stop offset="95%" stopColor="#7c3aed" stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke="#1e2530" />
              <XAxis dataKey="week" stroke="#6b7280" tick={{ fontSize: 11 }} />
              <YAxis stroke="#6b7280" tick={{ fontSize: 11 }} />
              <Tooltip contentStyle={tooltipStyle} />
              <Area type="monotone" dataKey="count" stroke="#7c3aed" fill="url(#gradViolet)" strokeWidth={2} />
            </AreaChart>
          </ResponsiveContainer>
        </div>

        {/* Répartition statuts */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">Répartition par statut</h3>
          <ResponsiveContainer width="100%" height={200}>
            <PieChart>
              <Pie data={statusData} dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius={80} label>
                {statusData.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
              </Pie>
              <Tooltip contentStyle={tooltipStyle} />
              <Legend />
            </PieChart>
          </ResponsiveContainer>
        </div>

        {/* Plateformes */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">Influenceurs par plateforme</h3>
          <ResponsiveContainer width="100%" height={200}>
            <BarChart data={stats.byPlatform}>
              <CartesianGrid strokeDasharray="3 3" stroke="#1e2530" />
              <XAxis dataKey="primary_platform" stroke="#6b7280" tick={{ fontSize: 11 }} />
              <YAxis stroke="#6b7280" tick={{ fontSize: 11 }} />
              <Tooltip contentStyle={tooltipStyle} />
              <Bar dataKey="count" fill="#06b6d4" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>

        {/* Taux de réponse par plateforme */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">Taux de réponse par plateforme</h3>
          <ResponsiveContainer width="100%" height={200}>
            <BarChart data={stats.responseByPlatform}>
              <CartesianGrid strokeDasharray="3 3" stroke="#1e2530" />
              <XAxis dataKey="platform" stroke="#6b7280" tick={{ fontSize: 11 }} />
              <YAxis stroke="#6b7280" tick={{ fontSize: 11 }} unit="%" />
              <Tooltip contentStyle={tooltipStyle} formatter={(v: number) => [`${v}%`, 'Taux de réponse']} />
              <Bar dataKey="rate" fill="#f59e0b" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>

        {/* Funnel */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">Funnel de conversion</h3>
          <div className="space-y-2">
            {stats.funnel.map((step, i) => {
              const max = stats.funnel[0]?.count || 1;
              const pct = Math.round(step.count / max * 100);
              return (
                <div key={step.stage} className="flex items-center gap-3">
                  <span className="text-sm text-muted w-28 flex-shrink-0">{step.stage}</span>
                  <div className="flex-1 bg-surface2 rounded-full h-5 overflow-hidden">
                    <div
                      className="h-full rounded-full transition-all"
                      style={{ width: `${pct}%`, backgroundColor: COLORS[i % COLORS.length] }}
                    />
                  </div>
                  <span className="text-sm font-mono text-white w-8 text-right">{step.count}</span>
                </div>
              );
            })}
          </div>
        </div>

        {/* Activité équipe */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">Activité équipe ce mois</h3>
          <ResponsiveContainer width="100%" height={200}>
            <BarChart data={stats.teamActivity.map(t => ({ name: t.user?.name ?? '?', count: t.count }))}>
              <CartesianGrid strokeDasharray="3 3" stroke="#1e2530" />
              <XAxis dataKey="name" stroke="#6b7280" tick={{ fontSize: 11 }} />
              <YAxis stroke="#6b7280" tick={{ fontSize: 11 }} />
              <Tooltip contentStyle={tooltipStyle} />
              <Bar dataKey="count" fill="#7c3aed" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>
    </div>
  );
}
