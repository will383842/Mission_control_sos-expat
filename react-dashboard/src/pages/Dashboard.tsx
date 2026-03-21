import React, { useContext, useEffect, useState, useMemo } from 'react';
import { Link, Navigate } from 'react-router-dom';
import {
  AreaChart, Area, BarChart, Bar, PieChart, Pie, Cell,
  XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid,
} from 'recharts';
import { useStats } from '../hooks/useStats';
import { useReminders } from '../hooks/useReminders';
import { AuthContext } from '../hooks/useAuth';
import api from '../api/client';
import { getCountryFlag } from '../data/countries';
import { CONTACT_TYPES, CONTACT_TYPE_MAP, PIPELINE_STATUSES, STATUS_MAP } from '../lib/constants';
import type { ContactType, CoverageData } from '../types/influenceur';

// ── Labels ──────────────────────────────────────────────────
const ACTION_LABELS: Record<string, string> = {
  created: 'a créé', contact_added: 'a ajouté un contact pour', updated: 'a modifié',
  status_changed: 'a changé le statut de', login: 's\'est connecté',
  reminder_dismissed: 'a dismissé un rappel pour', reminder_done: 'a traité un rappel pour',
  deleted: 'a supprimé',
};

// ── Skeleton placeholder ────────────────────────────────────
function Skeleton({ className = '' }: { className?: string }) {
  return <div className={`animate-pulse bg-surface2 rounded-lg ${className}`} />;
}

function KpiSkeleton() {
  return (
    <div className="bg-surface border border-border rounded-xl p-5 space-y-2">
      <Skeleton className="h-3 w-20" />
      <Skeleton className="h-8 w-16" />
    </div>
  );
}

// ── Custom Recharts tooltip ─────────────────────────────────
function ChartTooltip({ active, payload, label }: any) {
  if (!active || !payload?.length) return null;
  return (
    <div className="bg-surface border border-border rounded-lg px-3 py-2 shadow-xl">
      <p className="text-xs text-muted mb-1">{label}</p>
      {payload.map((p: any, i: number) => (
        <p key={i} className="text-sm font-bold text-white">
          {p.value} {p.name || ''}
        </p>
      ))}
    </div>
  );
}

// ═══════════════════════════════════════════════════════════
// DASHBOARD
// ═══════════════════════════════════════════════════════════

export default function Dashboard() {
  const { stats, loading } = useStats();
  const { reminders } = useReminders();
  const { user } = useContext(AuthContext);
  const [coverage, setCoverage] = useState<CoverageData | null>(null);
  const [coverageLoading, setCoverageLoading] = useState(true);

  useEffect(() => {
    if (user?.role === 'admin') {
      api.get<CoverageData>('/stats/coverage')
        .then(({ data }) => setCoverage(data))
        .catch(() => {})
        .finally(() => setCoverageLoading(false));
    } else {
      setCoverageLoading(false);
    }
  }, [user]);

  // Redirect researchers
  if (user?.role === 'researcher') {
    return <Navigate to="/mon-tableau" replace />;
  }

  // ── Derived data ────────────────────────────────────────
  const activeAndSigned = useMemo(() => {
    if (!stats) return 0;
    return (stats.byStatus?.['active'] ?? 0) + (stats.byStatus?.['signed'] ?? 0);
  }, [stats]);

  const urgentReminders = reminders.length;

  // Pipeline funnel data with colors
  const funnelData = useMemo(() => {
    if (!stats?.funnel) return [];
    return stats.funnel.map(f => {
      const cfg = STATUS_MAP[f.stage as keyof typeof STATUS_MAP];
      return { ...f, label: cfg?.label ?? f.stage, fill: cfg?.color ?? '#6B7280' };
    });
  }, [stats]);

  // Top 10 countries
  const topCountries = useMemo(() => {
    if (!coverage?.by_country) return [];
    return coverage.by_country.slice(0, 10);
  }, [coverage]);

  // Languages for donut
  const languageData = useMemo(() => {
    if (!coverage?.by_language) return [];
    return coverage.by_language.slice(0, 8);
  }, [coverage]);

  const LANG_COLORS = ['#7c3aed', '#06b6d4', '#f59e0b', '#10b981', '#ef4444', '#3b82f6', '#ec4899', '#14b8a6'];

  // ── Loading skeleton ────────────────────────────────────
  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div>
          <Skeleton className="h-7 w-40" />
          <Skeleton className="h-4 w-56 mt-2" />
        </div>
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          {Array.from({ length: 6 }).map((_, i) => <KpiSkeleton key={i} />)}
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-20" />
          ))}
        </div>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <Skeleton className="h-72" />
          <Skeleton className="h-72" />
        </div>
      </div>
    );
  }

  // ═══════════════════════════════════════════════════════════
  // RENDER
  // ═══════════════════════════════════════════════════════════

  return (
    <div className="p-4 md:p-6 space-y-6">

      {/* ── Header ─────────────────────────────────────────── */}
      <div>
        <h2 className="font-title text-2xl font-bold text-white">Dashboard</h2>
        <p className="text-muted text-sm mt-1">Bienvenue, {user?.name}</p>
      </div>

      {/* ═══════════════════════════════════════════════════════
          1. KPI ROW — 6 cards
      ═══════════════════════════════════════════════════════ */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        {/* Total contacts */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-[11px] text-muted uppercase tracking-wider font-medium">Total contacts</p>
          <p className="text-3xl font-bold text-white font-title mt-1">{stats?.total ?? 0}</p>
        </div>

        {/* Actifs / Signés */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-[11px] text-muted uppercase tracking-wider font-medium">Actifs / Signés</p>
          <p className="text-3xl font-bold text-emerald-400 font-title mt-1">{activeAndSigned}</p>
        </div>

        {/* Taux de réponse */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-[11px] text-muted uppercase tracking-wider font-medium">Taux de réponse</p>
          <p className="text-3xl font-bold text-cyan font-title mt-1">{stats?.responseRate ?? 0}%</p>
        </div>

        {/* Taux de conversion */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-[11px] text-muted uppercase tracking-wider font-medium">Taux conversion</p>
          <p className="text-3xl font-bold text-violet font-title mt-1">{stats?.conversionRate ?? 0}%</p>
        </div>

        {/* Nouveaux ce mois */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-[11px] text-muted uppercase tracking-wider font-medium">Nouveaux ce mois</p>
          <p className="text-3xl font-bold text-amber font-title mt-1">{stats?.newThisMonth ?? 0}</p>
        </div>

        {/* Relances urgentes */}
        <Link
          to="/a-relancer"
          className={`bg-surface border rounded-xl p-5 transition-colors hover:border-red-500/50 ${
            urgentReminders > 0 ? 'border-red-500/40' : 'border-border'
          }`}
        >
          <p className="text-[11px] text-muted uppercase tracking-wider font-medium">Relances urgentes</p>
          <p className={`text-3xl font-bold font-title mt-1 ${urgentReminders > 0 ? 'text-red-400' : 'text-muted'}`}>
            {urgentReminders}
          </p>
        </Link>
      </div>

      {/* ═══════════════════════════════════════════════════════
          2. CARDS BY CONTACT TYPE
      ═══════════════════════════════════════════════════════ */}
      {stats?.byContactType && Object.keys(stats.byContactType).length > 0 && (
        <div>
          <h3 className="font-title font-semibold text-white mb-3 text-sm">Par type de contact</h3>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-7 gap-2.5">
            {CONTACT_TYPES.map(ct => {
              const count = stats.byContactType?.[ct.value] ?? 0;
              if (count === 0) return null;
              const maxCount = Math.max(...Object.values(stats.byContactType ?? {}));
              const barPct = maxCount > 0 ? Math.max(8, Math.round((count / maxCount) * 100)) : 0;
              return (
                <div key={ct.value} className="bg-surface border border-border rounded-xl p-3.5 group hover:border-border/80 transition-colors">
                  <div className="flex items-center gap-2 mb-2">
                    <span className="text-base">{ct.icon}</span>
                    <span className="text-[11px] text-muted truncate">{ct.label}</span>
                  </div>
                  <p className={`text-xl font-bold font-title ${ct.text}`}>{count}</p>
                  <div className="w-full bg-surface2 rounded-full h-1 mt-2">
                    <div
                      className="h-1 rounded-full transition-all duration-500"
                      style={{ width: `${barPct}%`, backgroundColor: ct.color }}
                    />
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* ═══════════════════════════════════════════════════════
          3. PIPELINE FUNNEL + 4. EVOLUTION CHART (side by side)
      ═══════════════════════════════════════════════════════ */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {/* Pipeline Funnel — Horizontal bar chart */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">Pipeline</h3>
          {funnelData.length > 0 ? (
            <ResponsiveContainer width="100%" height={Math.max(240, funnelData.length * 34)}>
              <BarChart data={funnelData} layout="vertical" margin={{ left: 0, right: 16, top: 0, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#1e2530" horizontal={false} />
                <XAxis type="number" tick={{ fill: '#6B7280', fontSize: 11 }} axisLine={false} tickLine={false} />
                <YAxis
                  type="category"
                  dataKey="label"
                  tick={{ fill: '#9CA3AF', fontSize: 11 }}
                  axisLine={false}
                  tickLine={false}
                  width={90}
                />
                <Tooltip content={<ChartTooltip />} cursor={{ fill: 'rgba(124,58,237,0.08)' }} />
                <Bar dataKey="count" radius={[0, 4, 4, 0]} maxBarSize={24}>
                  {funnelData.map((entry, idx) => (
                    <Cell key={idx} fill={entry.fill} />
                  ))}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <p className="text-muted text-sm text-center py-8">Aucune donnée pipeline.</p>
          )}
        </div>

        {/* Evolution Chart — Area chart */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">Évolution (12 semaines)</h3>
          {(stats?.contactsEvolution ?? []).length > 0 ? (
            <ResponsiveContainer width="100%" height={280}>
              <AreaChart data={stats!.contactsEvolution} margin={{ left: -10, right: 8, top: 4, bottom: 0 }}>
                <defs>
                  <linearGradient id="gradViolet" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="#7c3aed" stopOpacity={0.35} />
                    <stop offset="100%" stopColor="#7c3aed" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#1e2530" />
                <XAxis
                  dataKey="week"
                  tick={{ fill: '#6B7280', fontSize: 10 }}
                  axisLine={false}
                  tickLine={false}
                  tickFormatter={(v: string) => {
                    // Show short date like "12 Mar"
                    try {
                      const d = new Date(v);
                      return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
                    } catch { return v; }
                  }}
                />
                <YAxis tick={{ fill: '#6B7280', fontSize: 11 }} axisLine={false} tickLine={false} />
                <Tooltip content={<ChartTooltip />} />
                <Area
                  type="monotone"
                  dataKey="count"
                  stroke="#7c3aed"
                  strokeWidth={2}
                  fill="url(#gradViolet)"
                  dot={{ r: 3, fill: '#7c3aed', strokeWidth: 0 }}
                  activeDot={{ r: 5, fill: '#a78bfa', strokeWidth: 0 }}
                />
              </AreaChart>
            </ResponsiveContainer>
          ) : (
            <p className="text-muted text-sm text-center py-8">Pas encore de données d'évolution.</p>
          )}
        </div>
      </div>

      {/* ═══════════════════════════════════════════════════════
          5. TOP COUNTRIES + 6. LANGUAGES (side by side)
      ═══════════════════════════════════════════════════════ */}
      {user?.role === 'admin' && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

          {/* Top 10 Countries — Horizontal bars */}
          <div className="lg:col-span-2 bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white mb-4">Top 10 pays</h3>
            {coverageLoading ? (
              <div className="space-y-3">
                {Array.from({ length: 5 }).map((_, i) => <Skeleton key={i} className="h-7" />)}
              </div>
            ) : topCountries.length > 0 ? (
              <div className="space-y-2.5">
                {topCountries.map((row, i) => {
                  const maxVal = topCountries[0]?.total ?? 1;
                  const pct = Math.max(6, Math.round((row.total / maxVal) * 100));
                  return (
                    <div key={row.country} className="flex items-center gap-3">
                      <span className="text-xs text-muted w-4 text-right font-mono">{i + 1}</span>
                      <span className="text-sm w-6 text-center">{getCountryFlag(row.country)}</span>
                      <span className="text-sm text-white w-28 truncate">{row.country}</span>
                      <div className="flex-1 bg-surface2 rounded-full h-2.5">
                        <div
                          className="h-2.5 rounded-full transition-all duration-500"
                          style={{
                            width: `${pct}%`,
                            background: `linear-gradient(90deg, #7c3aed ${0}%, #06b6d4 ${100}%)`,
                          }}
                        />
                      </div>
                      <span className="text-sm font-bold text-white font-mono w-10 text-right">{row.total}</span>
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="text-muted text-sm text-center py-8">Aucune donnée pays.</p>
            )}
          </div>

          {/* Languages Distribution — Donut chart */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white mb-4">Langues</h3>
            {coverageLoading ? (
              <Skeleton className="h-48 mx-auto w-48 rounded-full" />
            ) : languageData.length > 0 ? (
              <div>
                <ResponsiveContainer width="100%" height={200}>
                  <PieChart>
                    <Pie
                      data={languageData}
                      dataKey="total"
                      nameKey="language"
                      cx="50%"
                      cy="50%"
                      innerRadius={50}
                      outerRadius={80}
                      paddingAngle={3}
                      strokeWidth={0}
                    >
                      {languageData.map((_, i) => (
                        <Cell key={i} fill={LANG_COLORS[i % LANG_COLORS.length]} />
                      ))}
                    </Pie>
                    <Tooltip
                      content={({ active, payload }) => {
                        if (!active || !payload?.length) return null;
                        const d = payload[0].payload;
                        return (
                          <div className="bg-surface border border-border rounded-lg px-3 py-2 shadow-xl">
                            <p className="text-sm font-bold text-white">{d.language}</p>
                            <p className="text-xs text-muted">{d.total} contacts</p>
                          </div>
                        );
                      }}
                    />
                  </PieChart>
                </ResponsiveContainer>
                {/* Legend pills */}
                <div className="flex flex-wrap gap-1.5 mt-3 justify-center">
                  {languageData.map((lang, i) => (
                    <span
                      key={lang.language}
                      className="inline-flex items-center gap-1 text-[10px] text-white bg-surface2 rounded-full px-2 py-0.5"
                    >
                      <span
                        className="w-2 h-2 rounded-full inline-block flex-shrink-0"
                        style={{ backgroundColor: LANG_COLORS[i % LANG_COLORS.length] }}
                      />
                      {lang.language.toUpperCase()} ({lang.total})
                    </span>
                  ))}
                </div>
              </div>
            ) : (
              <p className="text-muted text-sm text-center py-8">Aucune donnée langue.</p>
            )}
          </div>
        </div>
      )}

      {/* ═══════════════════════════════════════════════════════
          7. REMINDERS + 8. RECENT ACTIVITY (side by side)
      ═══════════════════════════════════════════════════════ */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {/* Reminders Widget */}
        <div className={`bg-surface border rounded-xl p-5 ${
          urgentReminders > 0 ? 'border-red-500/30' : 'border-border'
        }`}>
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <h3 className="font-title font-semibold text-white">À relancer</h3>
              {urgentReminders > 0 && (
                <span className="bg-red-500/20 text-red-400 text-[10px] font-bold px-2 py-0.5 rounded-full">
                  {urgentReminders}
                </span>
              )}
            </div>
            <Link to="/a-relancer" className="text-xs text-violet hover:text-violet-light transition-colors">
              Voir tout
            </Link>
          </div>
          {reminders.length === 0 ? (
            <div className="text-center py-6">
              <p className="text-muted text-sm">Aucun rappel en attente.</p>
              <p className="text-emerald-500 text-xs mt-1">Tout est à jour</p>
            </div>
          ) : (
            <div className="space-y-2.5">
              {reminders.slice(0, 3).map(r => {
                const typeCfg = r.influenceur?.contact_type
                  ? CONTACT_TYPE_MAP[r.influenceur.contact_type]
                  : null;
                return (
                  <Link
                    key={r.id}
                    to={`/influenceurs/${r.influenceur_id}`}
                    className="flex items-center gap-3 p-3 rounded-lg bg-surface2 hover:bg-surface2/80 transition-colors group"
                  >
                    <div
                      className="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 text-base"
                      style={{ backgroundColor: typeCfg ? `${typeCfg.color}20` : '#1e2530' }}
                    >
                      {typeCfg?.icon ?? '?'}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-medium text-white truncate group-hover:text-violet-light transition-colors">
                        {r.influenceur?.name}
                      </p>
                      <p className="text-xs text-amber mt-0.5">
                        {r.days_elapsed != null ? `${r.days_elapsed}j sans contact` : 'Date inconnue'}
                      </p>
                    </div>
                    <svg className="w-4 h-4 text-muted group-hover:text-violet flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                    </svg>
                  </Link>
                );
              })}
              {reminders.length > 3 && (
                <Link
                  to="/a-relancer"
                  className="block text-center text-xs text-violet hover:text-violet-light py-2 transition-colors"
                >
                  +{reminders.length - 3} autres relances
                </Link>
              )}
            </div>
          )}
        </div>

        {/* Recent Activity */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">Activité récente</h3>
          {(stats?.recentActivity ?? []).length === 0 ? (
            <p className="text-muted text-sm text-center py-6">Aucune activité.</p>
          ) : (
            <div className="space-y-1">
              {stats?.recentActivity?.slice(0, 5).map(log => (
                <div key={log.id} className="flex gap-3 py-2.5 border-b border-border/40 last:border-0">
                  <div className="w-8 h-8 rounded-full bg-violet/15 flex items-center justify-center text-violet-light text-xs font-bold flex-shrink-0">
                    {log.user?.name?.[0]?.toUpperCase() ?? '?'}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="text-sm text-white leading-snug">
                      <span className="font-medium">{log.user?.name}</span>{' '}
                      <span className="text-muted">{ACTION_LABELS[log.action] ?? log.action}</span>
                      {log.influenceur && (
                        <>
                          {' '}
                          <Link
                            to={`/influenceurs/${log.influenceur_id}`}
                            className="text-violet-light hover:underline font-medium"
                          >
                            {log.influenceur.name}
                          </Link>
                        </>
                      )}
                    </p>
                    <p className="text-[11px] text-muted mt-0.5">
                      {new Date(log.created_at).toLocaleDateString('fr-FR', {
                        day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
                      })}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
