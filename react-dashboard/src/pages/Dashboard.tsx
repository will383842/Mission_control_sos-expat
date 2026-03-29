import React, { useContext, useEffect, useState, useMemo } from 'react';
import { Link, Navigate } from 'react-router-dom';
import {
  AreaChart, Area, BarChart, Bar, PieChart, Pie, Cell,
  XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid, Legend,
} from 'recharts';
import { useStats } from '../hooks/useStats';
import { useReminders } from '../hooks/useReminders';
import { AuthContext } from '../hooks/useAuth';
import api from '../api/client';
import { getCountryFlag } from '../data/countries';
import { CONTACT_TYPES, CONTACT_TYPE_MAP, PIPELINE_STATUSES, STATUS_MAP, getLanguageLabel, getLanguageFlag } from '../lib/constants';
import type { ContactType, CoverageData, ProgressData, ProgressCountryRow } from '../types/influenceur';

// ── Labels ──────────────────────────────────────────────────
const ACTION_LABELS: Record<string, string> = {
  created: 'a cree', contact_added: 'a ajoute un contact pour', updated: 'a modifie',
  status_changed: 'a change le statut de', login: 's\'est connecte',
  reminder_dismissed: 'a dismiss un rappel pour', reminder_done: 'a traite un rappel pour',
  deleted: 'a supprime',
};

// ── Admin dashboard types ───────────────────────────────────
interface GlobalStat {
  total: number; with_email: number; with_phone: number; with_form: number;
  contactable: number; unreachable: number; email_pct: number; contactable_pct: number;
}
interface TypeStat {
  value: string; label: string; icon: string; color: string; sort_order: number;
  total: number; with_email: number; with_phone: number; with_form: number;
  contactable: number; unreachable: number; scraped: number;
  countries: number; countries_searched: number;
  email_pct: number; contactable_pct: number;
}
interface TypeLangStat {
  contact_type: string; language: string; total: number; with_email: number; with_form: number; email_pct: number;
}

const LANG_FLAGS: Record<string, string> = {
  fr: '🇫🇷', en: '🇬🇧', es: '🇪🇸', de: '🇩🇪', pt: '🇧🇷', ar: '🇸🇦', it: '🇮🇹', nl: '🇳🇱', zh: '🇨🇳', ja: '🇯🇵', ko: '🇰🇷', ru: '🇷🇺', th: '🇹🇭',
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

// ── Section wrapper ─────────────────────────────────────────
function DashSection({ title, children, className = '' }: { title: string; children: React.ReactNode; className?: string }) {
  return (
    <div className={`bg-surface border border-border rounded-xl p-5 ${className}`}>
      <h3 className="font-title font-semibold text-white mb-4">{title}</h3>
      {children}
    </div>
  );
}

// ── Collapsible section ─────────────────────────────────────
function CollapsibleSection({ title, defaultOpen = false, children }: { title: string; defaultOpen?: boolean; children: React.ReactNode }) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <div className="bg-surface border border-border rounded-xl overflow-hidden">
      <button
        onClick={() => setOpen(!open)}
        className="w-full px-5 py-4 flex items-center justify-between hover:bg-surface2/30 transition-colors"
      >
        <h3 className="font-title font-semibold text-white">{title}</h3>
        <svg
          className={`w-4 h-4 text-muted transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
          fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      {open && <div className="border-t border-border">{children}</div>}
    </div>
  );
}

// ═══════════════════════════════════════════════════════════
// DASHBOARD (unified)
// ═══════════════════════════════════════════════════════════

const COLORS = ['#7c3aed', '#06b6d4', '#f59e0b', '#10b981', '#ef4444', '#6b7280'];
const LANG_COLORS = ['#7c3aed', '#06b6d4', '#f59e0b', '#10b981', '#ef4444', '#3b82f6', '#ec4899', '#14b8a6'];

export default function Dashboard() {
  const { stats, loading } = useStats();
  const { reminders } = useReminders();
  const { user } = useContext(AuthContext);

  // Coverage data (admin)
  const [coverage, setCoverage] = useState<CoverageData | null>(null);
  const [coverageLoading, setCoverageLoading] = useState(true);
  const [progress, setProgress] = useState<ProgressData | null>(null);
  const [progressLoading, setProgressLoading] = useState(true);

  // Admin dashboard data (from AdminConsole)
  const [globalStat, setGlobalStat] = useState<GlobalStat | null>(null);
  const [typeStats, setTypeStats] = useState<TypeStat[]>([]);
  const [typeLangStats, setTypeLangStats] = useState<TypeLangStat[]>([]);
  const [expandedType, setExpandedType] = useState<string | null>(null);

  useEffect(() => {
    if (user?.role === 'admin') {
      api.get<CoverageData>('/stats/coverage')
        .then(({ data }) => setCoverage(data))
        .catch(() => {})
        .finally(() => setCoverageLoading(false));

      api.get<ProgressData>('/stats/progress')
        .then(({ data }) => setProgress(data))
        .catch(() => {})
        .finally(() => setProgressLoading(false));

      api.get('/stats/admin-dashboard')
        .then(({ data }) => {
          setGlobalStat(data.global);
          setTypeStats(data.per_type);
          setTypeLangStats(data.per_type_lang || []);
        })
        .catch(() => {});
    } else {
      setCoverageLoading(false);
      setProgressLoading(false);
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

  const funnelData = useMemo(() => {
    if (!stats?.funnel) return [];
    return stats.funnel.map(f => {
      const cfg = STATUS_MAP[f.stage as keyof typeof STATUS_MAP];
      return { ...f, label: cfg?.label ?? f.stage, fill: cfg?.color ?? '#6B7280' };
    });
  }, [stats]);

  const topCountries = useMemo(() => {
    if (!coverage?.by_country) return [];
    return coverage.by_country.slice(0, 10);
  }, [coverage]);

  const languageData = useMemo(() => {
    if (!coverage?.by_language) return [];
    return coverage.by_language.slice(0, 8);
  }, [coverage]);

  const statusData = useMemo(() => {
    if (!stats?.byStatus) return [];
    return Object.entries(stats.byStatus).map(([name, value]) => ({ name, value }));
  }, [stats]);

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
          1. KPI ROW — Admin gets enriched KPIs, others get basic
      ═══════════════════════════════════════════════════════ */}
      {user?.role === 'admin' && globalStat ? (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div className="bg-surface border border-border rounded-xl p-4">
            <div className="flex items-center gap-2 mb-1">
              <span className="text-sm">👥</span>
              <span className="text-[10px] text-muted uppercase tracking-wider">Total contacts</span>
            </div>
            <p className="text-2xl font-bold text-white font-title">{globalStat.total.toLocaleString()}</p>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4">
            <div className="flex items-center gap-2 mb-1">
              <span className="text-sm">✉️</span>
              <span className="text-[10px] text-muted uppercase tracking-wider">Avec email</span>
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-2xl font-bold text-cyan font-title">{globalStat.with_email.toLocaleString()}</span>
              <span className="text-xs text-muted">{globalStat.email_pct}%</span>
            </div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4">
            <div className="flex items-center gap-2 mb-1">
              <span className="text-sm">📞</span>
              <span className="text-[10px] text-muted uppercase tracking-wider">Avec telephone</span>
            </div>
            <p className="text-2xl font-bold text-emerald-400 font-title">{globalStat.with_phone.toLocaleString()}</p>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4">
            <div className="flex items-center gap-2 mb-1">
              <span className="text-sm">📝</span>
              <span className="text-[10px] text-muted uppercase tracking-wider">Avec formulaire</span>
            </div>
            <p className="text-2xl font-bold text-blue-400 font-title">{globalStat.with_form.toLocaleString()}</p>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4">
            <div className="flex items-center gap-2 mb-1">
              <span className="text-sm">✓</span>
              <span className="text-[10px] text-muted uppercase tracking-wider">Contactables</span>
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-2xl font-bold text-green-400 font-title">{globalStat.contactable.toLocaleString()}</span>
              <span className="text-xs text-muted">{globalStat.contactable_pct}%</span>
            </div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4">
            <div className="flex items-center gap-2 mb-1">
              <span className="text-sm">⚠</span>
              <span className="text-[10px] text-muted uppercase tracking-wider">Sans coordonnees</span>
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-2xl font-bold text-red-400 font-title">{globalStat.unreachable.toLocaleString()}</span>
              <span className="text-xs text-muted">{globalStat.total > 0 ? `${Math.round(globalStat.unreachable / globalStat.total * 100)}%` : '0%'}</span>
            </div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4">
            <div className="flex items-center gap-2 mb-1">
              <span className="text-sm">📈</span>
              <span className="text-[10px] text-muted uppercase tracking-wider">Taux reponse</span>
            </div>
            <p className="text-2xl font-bold text-cyan font-title">{stats?.responseRate ?? 0}%</p>
          </div>
          <Link
            to="/a-relancer"
            className={`bg-surface border rounded-xl p-4 transition-colors hover:border-red-500/50 ${
              urgentReminders > 0 ? 'border-red-500/40' : 'border-border'
            }`}
          >
            <div className="flex items-center gap-2 mb-1">
              <span className="text-sm">🔔</span>
              <span className="text-[10px] text-muted uppercase tracking-wider">Relances</span>
            </div>
            <p className={`text-2xl font-bold font-title ${urgentReminders > 0 ? 'text-red-400' : 'text-muted'}`}>
              {urgentReminders}
            </p>
          </Link>
        </div>
      ) : (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          <div className="bg-surface border border-border rounded-xl p-5">
            <p className="text-[11px] text-muted uppercase tracking-wider font-medium">Total contacts</p>
            <p className="text-3xl font-bold text-white font-title mt-1">{stats?.total ?? 0}</p>
          </div>
          <div className="bg-surface border border-border rounded-xl p-5">
            <p className="text-[11px] text-muted uppercase tracking-wider font-medium">Actifs / Signes</p>
            <p className="text-3xl font-bold text-emerald-400 font-title mt-1">{activeAndSigned}</p>
          </div>
          <div className="bg-surface border border-border rounded-xl p-5">
            <p className="text-[11px] text-muted uppercase tracking-wider font-medium">Taux de reponse</p>
            <p className="text-3xl font-bold text-cyan font-title mt-1">{stats?.responseRate ?? 0}%</p>
          </div>
          <div className="bg-surface border border-border rounded-xl p-5">
            <p className="text-[11px] text-muted uppercase tracking-wider font-medium">Taux conversion</p>
            <p className="text-3xl font-bold text-violet font-title mt-1">{stats?.conversionRate ?? 0}%</p>
          </div>
          <div className="bg-surface border border-border rounded-xl p-5">
            <p className="text-[11px] text-muted uppercase tracking-wider font-medium">Nouveaux ce mois</p>
            <p className="text-3xl font-bold text-amber font-title mt-1">{stats?.newThisMonth ?? 0}</p>
          </div>
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
      )}

      {/* ═══════════════════════════════════════════════════════
          2. CONTACT TYPE CARDS
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
          3. PIPELINE + EVOLUTION (side by side)
      ═══════════════════════════════════════════════════════ */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <DashSection title="Pipeline">
          {funnelData.length > 0 ? (
            <ResponsiveContainer width="100%" height={Math.max(240, funnelData.length * 34)}>
              <BarChart data={funnelData} layout="vertical" margin={{ left: 0, right: 16, top: 0, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#1e2530" horizontal={false} />
                <XAxis type="number" tick={{ fill: '#6B7280', fontSize: 11 }} axisLine={false} tickLine={false} />
                <YAxis type="category" dataKey="label" tick={{ fill: '#9CA3AF', fontSize: 11 }} axisLine={false} tickLine={false} width={90} />
                <Tooltip content={<ChartTooltip />} cursor={{ fill: 'rgba(124,58,237,0.08)' }} />
                <Bar dataKey="count" radius={[0, 4, 4, 0]} maxBarSize={24}>
                  {funnelData.map((entry, idx) => (
                    <Cell key={idx} fill={entry.fill} />
                  ))}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <p className="text-muted text-sm text-center py-8">Aucune donnee pipeline.</p>
          )}
        </DashSection>

        <DashSection title="Evolution (12 semaines)">
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
                    try {
                      const d = new Date(v);
                      return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
                    } catch { return v; }
                  }}
                />
                <YAxis tick={{ fill: '#6B7280', fontSize: 11 }} axisLine={false} tickLine={false} />
                <Tooltip content={<ChartTooltip />} />
                <Area
                  type="monotone" dataKey="count" stroke="#7c3aed" strokeWidth={2}
                  fill="url(#gradViolet)"
                  dot={{ r: 3, fill: '#7c3aed', strokeWidth: 0 }}
                  activeDot={{ r: 5, fill: '#a78bfa', strokeWidth: 0 }}
                />
              </AreaChart>
            </ResponsiveContainer>
          ) : (
            <p className="text-muted text-sm text-center py-8">Pas encore de donnees d'evolution.</p>
          )}
        </DashSection>
      </div>

      {/* ═══════════════════════════════════════════════════════
          4. STATISTIQUES CHARTS (from Statistiques.tsx)
      ═══════════════════════════════════════════════════════ */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Status distribution */}
        <DashSection title="Repartition par statut">
          {statusData.length > 0 ? (
            <ResponsiveContainer width="100%" height={220}>
              <PieChart>
                <Pie data={statusData} dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius={80} label>
                  {statusData.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                </Pie>
                <Tooltip content={<ChartTooltip />} />
                <Legend wrapperStyle={{ fontSize: 11 }} />
              </PieChart>
            </ResponsiveContainer>
          ) : (
            <p className="text-muted text-sm text-center py-8">Aucune donnee.</p>
          )}
        </DashSection>

        {/* Platform breakdown */}
        <DashSection title="Contacts par plateforme">
          {(stats?.byPlatform ?? []).length > 0 ? (
            <ResponsiveContainer width="100%" height={220}>
              <BarChart data={stats!.byPlatform}>
                <CartesianGrid strokeDasharray="3 3" stroke="#1e2530" />
                <XAxis dataKey="primary_platform" stroke="#6b7280" tick={{ fontSize: 11 }} />
                <YAxis stroke="#6b7280" tick={{ fontSize: 11 }} />
                <Tooltip content={<ChartTooltip />} />
                <Bar dataKey="count" fill="#06b6d4" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <p className="text-muted text-sm text-center py-8">Aucune donnee.</p>
          )}
        </DashSection>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Response rate by platform */}
        <DashSection title="Taux de reponse par plateforme">
          {(stats?.responseByPlatform ?? []).length > 0 ? (
            <ResponsiveContainer width="100%" height={220}>
              <BarChart data={stats!.responseByPlatform}>
                <CartesianGrid strokeDasharray="3 3" stroke="#1e2530" />
                <XAxis dataKey="platform" stroke="#6b7280" tick={{ fontSize: 11 }} />
                <YAxis stroke="#6b7280" tick={{ fontSize: 11 }} unit="%" />
                <Tooltip content={<ChartTooltip />} />
                <Bar dataKey="rate" fill="#f59e0b" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <p className="text-muted text-sm text-center py-8">Aucune donnee.</p>
          )}
        </DashSection>

        {/* Team activity */}
        <DashSection title="Activite equipe ce mois">
          {(stats?.teamActivity ?? []).length > 0 ? (
            <ResponsiveContainer width="100%" height={220}>
              <BarChart data={stats!.teamActivity.map(t => ({ name: t.user?.name ?? '?', count: t.count }))}>
                <CartesianGrid strokeDasharray="3 3" stroke="#1e2530" />
                <XAxis dataKey="name" stroke="#6b7280" tick={{ fontSize: 11 }} />
                <YAxis stroke="#6b7280" tick={{ fontSize: 11 }} />
                <Tooltip content={<ChartTooltip />} />
                <Bar dataKey="count" fill="#7c3aed" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <p className="text-muted text-sm text-center py-8">Aucune donnee.</p>
          )}
        </DashSection>
      </div>

      {/* ═══════════════════════════════════════════════════════
          5. COUNTRIES + LANGUAGES (admin only)
      ═══════════════════════════════════════════════════════ */}
      {user?.role === 'admin' && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
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
                          style={{ width: `${pct}%`, background: `linear-gradient(90deg, #7c3aed 0%, #06b6d4 100%)` }}
                        />
                      </div>
                      <span className="text-sm font-bold text-white font-mono w-10 text-right">{row.total}</span>
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="text-muted text-sm text-center py-8">Aucune donnee pays.</p>
            )}
          </div>

          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white mb-4">Langues</h3>
            {coverageLoading ? (
              <Skeleton className="h-48 mx-auto w-48 rounded-full" />
            ) : languageData.length > 0 ? (
              <div>
                <ResponsiveContainer width="100%" height={200}>
                  <PieChart>
                    <Pie
                      data={languageData} dataKey="total" nameKey="language"
                      cx="50%" cy="50%" innerRadius={50} outerRadius={80} paddingAngle={3} strokeWidth={0}
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
                            <p className="text-sm font-bold text-white">{getLanguageLabel(d.language)}</p>
                            <p className="text-xs text-muted">{d.total} contacts</p>
                          </div>
                        );
                      }}
                    />
                  </PieChart>
                </ResponsiveContainer>
                <div className="flex flex-wrap gap-1.5 mt-3 justify-center">
                  {languageData.map((lang, i) => (
                    <span key={lang.language} className="inline-flex items-center gap-1 text-[10px] text-white bg-surface2 rounded-full px-2 py-0.5">
                      <span className="w-2 h-2 rounded-full inline-block flex-shrink-0" style={{ backgroundColor: LANG_COLORS[i % LANG_COLORS.length] }} />
                      {getLanguageLabel(lang.language)} ({lang.total})
                    </span>
                  ))}
                </div>
              </div>
            ) : (
              <p className="text-muted text-sm text-center py-8">Aucune donnee langue.</p>
            )}
          </div>
        </div>
      )}

      {/* ═══════════════════════════════════════════════════════
          6. ADMIN: PER-TYPE STATS TABLE (from AdminConsole)
      ═══════════════════════════════════════════════════════ */}
      {user?.role === 'admin' && typeStats.length > 0 && (
        <CollapsibleSection title="Stats par type de contact" defaultOpen={false}>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border">
                  {['Type', 'Contacts', 'Emails', 'Formulaires', 'Telephones', 'Contactables', 'Sans coord.', 'Pays'].map(h => (
                    <th key={h} className="text-left text-[10px] text-muted font-medium uppercase tracking-wider px-4 py-3 whitespace-nowrap">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {typeStats.filter(t => t.total > 0 || t.countries_searched > 0).map(t => {
                  const contactablePct = t.total > 0 ? t.contactable_pct : 0;
                  const langRows = typeLangStats.filter(tl => tl.contact_type === t.value);
                  const isExpanded = expandedType === t.value;
                  return (
                    <React.Fragment key={t.value}>
                      <tr
                        onClick={() => setExpandedType(isExpanded ? null : t.value)}
                        className="border-b border-border/50 hover:bg-surface2 transition-colors cursor-pointer"
                      >
                        <td className="px-4 py-3">
                          <span className="flex items-center gap-2">
                            <span className="text-[10px] text-muted">{isExpanded ? '▼' : '▶'}</span>
                            <span>{t.icon}</span>
                            <span className="text-white font-medium text-xs">{t.label}</span>
                          </span>
                        </td>
                        <td className="px-4 py-3 font-mono text-white font-bold">{t.total}</td>
                        <td className="px-4 py-3">
                          <span className="font-mono text-cyan">{t.with_email}</span>
                          {t.total > 0 && <span className="text-[10px] text-muted ml-1">({t.email_pct}%)</span>}
                        </td>
                        <td className="px-4 py-3 font-mono text-blue-400">{t.with_form}</td>
                        <td className="px-4 py-3 font-mono text-emerald-400">{t.with_phone}</td>
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-2">
                            <div className="w-16 bg-surface2 rounded-full h-1.5">
                              <div
                                className={`h-1.5 rounded-full ${contactablePct >= 80 ? 'bg-emerald-500' : contactablePct >= 50 ? 'bg-amber' : 'bg-red-500'}`}
                                style={{ width: `${Math.max(contactablePct, 3)}%` }}
                              />
                            </div>
                            <span className={`text-xs font-mono ${contactablePct >= 80 ? 'text-emerald-400' : contactablePct >= 50 ? 'text-amber' : 'text-red-400'}`}>
                              {t.contactable} ({contactablePct}%)
                            </span>
                          </div>
                        </td>
                        <td className="px-4 py-3">
                          {t.unreachable > 0 ? (
                            <span className="font-mono text-red-400">{t.unreachable}</span>
                          ) : (
                            <span className="text-muted/30">0</span>
                          )}
                        </td>
                        <td className="px-4 py-3 text-xs text-muted">
                          {t.countries > 0 && <span className="text-white">{t.countries}</span>}
                          {t.countries_searched > 0 && <span className="text-muted"> / {t.countries_searched} rech.</span>}
                        </td>
                      </tr>
                      {isExpanded && langRows.length > 0 && langRows.map(lr => (
                        <tr key={`${t.value}-${lr.language}`} className="bg-surface2/30 border-b border-border/30">
                          <td className="px-4 py-2 pl-12">
                            <span className="text-xs text-muted">{LANG_FLAGS[lr.language] || '🌐'} {lr.language.toUpperCase()}</span>
                          </td>
                          <td className="px-4 py-2 font-mono text-xs text-white">{lr.total}</td>
                          <td className="px-4 py-2">
                            <span className="font-mono text-xs text-cyan">{lr.with_email}</span>
                            <span className="text-[10px] text-muted ml-1">({lr.email_pct}%)</span>
                          </td>
                          <td className="px-4 py-2 font-mono text-xs text-blue-400">{lr.with_form}</td>
                          <td className="px-4 py-2" colSpan={4} />
                        </tr>
                      ))}
                      {isExpanded && langRows.length === 0 && (
                        <tr className="bg-surface2/30 border-b border-border/30">
                          <td colSpan={8} className="px-4 py-2 pl-12 text-xs text-muted">Pas de ventilation par langue</td>
                        </tr>
                      )}
                    </React.Fragment>
                  );
                })}
              </tbody>
            </table>
          </div>
        </CollapsibleSection>
      )}

      {/* ═══════════════════════════════════════════════════════
          7. PROGRESS PAR PAYS (admin only)
      ═══════════════════════════════════════════════════════ */}
      {user?.role === 'admin' && (
        <CollapsibleSection title="Progress par pays" defaultOpen={false}>
          <div className="p-5">
            {progressLoading ? (
              <div className="space-y-3">
                {Array.from({ length: 5 }).map((_, i) => <Skeleton key={i} className="h-10" />)}
              </div>
            ) : progress?.by_country && progress.by_country.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-border">
                      <th className="text-left text-xs text-muted font-medium px-3 py-2 whitespace-nowrap">Pays</th>
                      <th className="text-right text-xs text-muted font-medium px-3 py-2 whitespace-nowrap">Total</th>
                      <th className="text-right text-xs text-muted font-medium px-3 py-2 whitespace-nowrap">Avec email</th>
                      <th className="text-right text-xs text-muted font-medium px-3 py-2 whitespace-nowrap">Avec tel</th>
                      <th className="text-right text-xs text-muted font-medium px-3 py-2 whitespace-nowrap">Scrape</th>
                    </tr>
                  </thead>
                  <tbody>
                    {progress.by_country.map(row => (
                      <tr key={row.country} className="border-b border-border/40 last:border-0 hover:bg-surface2 transition-colors">
                        <td className="px-3 py-2 whitespace-nowrap">
                          <span className="mr-1.5">{getCountryFlag(row.country)}</span>
                          <span className="text-white">{row.country}</span>
                        </td>
                        <td className="px-3 py-2 text-right text-white font-mono font-bold">{row.total}</td>
                        <td className="px-3 py-2 text-right whitespace-nowrap">
                          <span className="text-emerald-400 font-mono">{row.with_email}</span>
                          <span className="text-muted text-xs ml-1">({row.email_pct}%)</span>
                        </td>
                        <td className="px-3 py-2 text-right whitespace-nowrap">
                          <span className="text-cyan font-mono">{row.with_phone}</span>
                          <span className="text-muted text-xs ml-1">({row.phone_pct}%)</span>
                        </td>
                        <td className="px-3 py-2 text-right text-amber font-mono">{row.scraped}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="text-muted text-sm text-center py-8">Aucune donnee de progress.</p>
            )}
          </div>
        </CollapsibleSection>
      )}

      {/* ═══════════════════════════════════════════════════════
          8. REMINDERS + RECENT ACTIVITY
      ═══════════════════════════════════════════════════════ */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Reminders Widget */}
        <div className={`bg-surface border rounded-xl p-5 ${urgentReminders > 0 ? 'border-red-500/30' : 'border-border'}`}>
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <h3 className="font-title font-semibold text-white">A relancer</h3>
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
              <p className="text-emerald-500 text-xs mt-1">Tout est a jour</p>
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
                    to={`/contacts/${r.influenceur_id}`}
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
                <Link to="/a-relancer" className="block text-center text-xs text-violet hover:text-violet-light py-2 transition-colors">
                  +{reminders.length - 3} autres relances
                </Link>
              )}
            </div>
          )}
        </div>

        {/* Recent Activity */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">Activite recente</h3>
          {(stats?.recentActivity ?? []).length === 0 ? (
            <p className="text-muted text-sm text-center py-6">Aucune activite.</p>
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
                            to={`/contacts/${log.influenceur_id}`}
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
