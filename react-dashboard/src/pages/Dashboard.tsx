import React, { useContext, useEffect, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { useStats } from '../hooks/useStats';
import { useReminders } from '../hooks/useReminders';
import { AuthContext } from '../hooks/useAuth';
import api from '../api/client';
import { CONTINENTS, TOTAL_COUNTRIES, getCountryFlag } from '../data/countries';

const STATUS_LABELS: Record<string, string> = {
  prospect: 'Prospect', contacted: 'Contacté', negotiating: 'Négociation',
  active: 'Actif', refused: 'Refusé', inactive: 'Inactif',
};

const ACTION_LABELS: Record<string, string> = {
  created: 'a créé', contact_added: 'a ajouté un contact pour', updated: 'a modifié',
  status_changed: 'a changé le statut de', login: 's\'est connecté',
  reminder_dismissed: 'a dismissé un rappel pour', reminder_done: 'a traité un rappel pour',
  deleted: 'a supprimé',
};

interface CoverageData {
  by_country: { country: string; total: number }[];
  by_language: { language: string; total: number }[];
  by_continent: { continent: string; total: number; countries_count: number; countries: { country: string; total: number }[] }[];
  countries_covered: number;
  languages_covered: number;
  total_influenceurs: number;
}

export default function Dashboard() {
  const { stats, loading } = useStats();
  const { reminders, dismiss } = useReminders();
  const { user } = useContext(AuthContext);
  const [coverage, setCoverage] = useState<CoverageData | null>(null);
  const [coverageLoading, setCoverageLoading] = useState(true);
  const [coverageTab, setCoverageTab] = useState<'countries' | 'languages' | 'continents'>('continents');

  useEffect(() => {
    if (user?.role === 'admin') {
      api.get<CoverageData>('/stats/coverage')
        .then(({ data }) => setCoverage(data))
        .catch(() => {})
        .finally(() => setCoverageLoading(false));
    }
  }, [user]);

  if (user?.role === 'researcher') {
    return <Navigate to="/mon-tableau" replace />;
  }

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  const statusOrder = ['prospect', 'contacted', 'negotiating', 'active', 'refused', 'inactive'];
  const statusColors: Record<string, string> = {
    prospect: 'text-muted', contacted: 'text-cyan', negotiating: 'text-amber',
    active: 'text-success', refused: 'text-danger', inactive: 'text-muted',
  };

  const coveragePct = coverage ? Math.round((coverage.countries_covered / TOTAL_COUNTRIES) * 100) : 0;
  const continentsCovered = coverage?.by_continent?.length ?? 0;
  const totalContinents = Object.keys(CONTINENTS).length;

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h2 className="font-title text-2xl font-bold text-white">Dashboard</h2>
        <p className="text-muted text-sm mt-1">Bienvenue, {user?.name}</p>
      </div>

      {/* Barre stats */}
      <div className="grid grid-cols-3 md:grid-cols-6 gap-3">
        {statusOrder.map(status => (
          <div key={status} className="bg-surface border border-border rounded-xl p-4">
            <p className={`text-2xl font-bold font-title ${statusColors[status]}`}>
              {stats?.byStatus?.[status] ?? 0}
            </p>
            <p className="text-xs text-muted mt-1">{STATUS_LABELS[status]}</p>
          </div>
        ))}
      </div>

      {/* KPIs rapides */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-muted text-sm">Taux de réponse</p>
          <p className="text-3xl font-bold text-cyan font-title mt-1">{stats?.responseRate ?? 0}%</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-muted text-sm">Taux de conversion</p>
          <p className="text-3xl font-bold text-violet font-title mt-1">{stats?.conversionRate ?? 0}%</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-muted text-sm">Nouveaux ce mois</p>
          <p className="text-3xl font-bold text-amber font-title mt-1">{stats?.newThisMonth ?? 0}</p>
        </div>
      </div>

      {/* Couverture mondiale */}
      {user?.role === 'admin' && (
        <div className="bg-surface border border-border rounded-xl overflow-hidden">
          <div className="px-5 py-4 border-b border-border">
            <h3 className="font-title font-semibold text-white">Couverture mondiale</h3>
            <p className="text-xs text-muted mt-0.5">Repartition des influenceurs par pays, langue et continent</p>
          </div>

          {coverageLoading ? (
            <div className="flex items-center justify-center py-8">
              <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
            </div>
          ) : coverage ? (
            <div className="p-5 space-y-5">
              {/* KPI cards */}
              <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div className="bg-surface2 rounded-xl p-4 text-center">
                  <p className="text-2xl font-bold text-cyan font-title">{coverage.total_influenceurs}</p>
                  <p className="text-[10px] text-muted uppercase tracking-wider mt-1">Total influenceurs</p>
                </div>
                <div className="bg-surface2 rounded-xl p-4 text-center">
                  <p className="text-2xl font-bold text-violet font-title">{coverage.countries_covered}</p>
                  <p className="text-[10px] text-muted uppercase tracking-wider mt-1">Pays couverts / {TOTAL_COUNTRIES}</p>
                </div>
                <div className="bg-surface2 rounded-xl p-4 text-center">
                  <p className="text-2xl font-bold text-amber font-title">{continentsCovered}</p>
                  <p className="text-[10px] text-muted uppercase tracking-wider mt-1">Continents actifs / {totalContinents}</p>
                </div>
                <div className="bg-surface2 rounded-xl p-4 text-center">
                  <p className="text-2xl font-bold text-green-400 font-title">{coverage.languages_covered}</p>
                  <p className="text-[10px] text-muted uppercase tracking-wider mt-1">Langues couvertes</p>
                </div>
              </div>

              {/* Progress bar mondiale */}
              <div>
                <div className="flex items-center justify-between mb-2">
                  <span className="text-xs text-gray-400">Couverture monde</span>
                  <span className="text-xs font-mono font-bold text-cyan">{coveragePct}%</span>
                </div>
                <div className="w-full bg-surface2 rounded-full h-3">
                  <div
                    className="h-3 rounded-full bg-gradient-to-r from-violet to-cyan transition-all duration-700"
                    style={{ width: `${Math.min(coveragePct, 100)}%` }}
                  />
                </div>
                <p className="text-[10px] text-muted mt-1">{coverage.countries_covered} pays sur {TOTAL_COUNTRIES} ont au moins 1 influenceur</p>
              </div>

              {/* Tabs */}
              <div className="flex gap-1 bg-surface2 rounded-lg p-1">
                {([
                  { key: 'continents', label: 'Par continent' },
                  { key: 'countries', label: 'Par pays' },
                  { key: 'languages', label: 'Par langue' },
                ] as const).map(tab => (
                  <button
                    key={tab.key}
                    onClick={() => setCoverageTab(tab.key)}
                    className={`flex-1 text-xs font-medium py-2 px-3 rounded-md transition-colors ${
                      coverageTab === tab.key
                        ? 'bg-violet text-white'
                        : 'text-muted hover:text-white'
                    }`}
                  >
                    {tab.label}
                  </button>
                ))}
              </div>

              {/* Tab content */}
              {coverageTab === 'continents' && (
                <div className="space-y-3">
                  {coverage.by_continent.map(cont => {
                    const maxTotal = coverage.by_continent[0]?.total ?? 1;
                    const barWidth = Math.max(5, Math.round((cont.total / maxTotal) * 100));
                    return (
                      <div key={cont.continent} className="bg-surface2 rounded-lg p-4">
                        <div className="flex items-center justify-between mb-2">
                          <div>
                            <span className="text-white font-medium text-sm">{cont.continent}</span>
                            <span className="text-muted text-xs ml-2">({cont.countries_count} pays)</span>
                          </div>
                          <span className="text-lg font-bold text-white font-title">{cont.total}</span>
                        </div>
                        <div className="w-full bg-bg rounded-full h-2 mb-2">
                          <div
                            className="h-2 rounded-full bg-violet transition-all"
                            style={{ width: `${barWidth}%` }}
                          />
                        </div>
                        {/* Top 5 countries in continent */}
                        <div className="flex flex-wrap gap-2 mt-2">
                          {cont.countries.slice(0, 5).map(c => (
                            <span key={c.country} className="text-[10px] text-gray-400 bg-bg rounded-md px-2 py-1">
                              {getCountryFlag(c.country)} {c.country}: <span className="text-white font-bold">{c.total}</span>
                            </span>
                          ))}
                          {cont.countries.length > 5 && (
                            <span className="text-[10px] text-muted bg-bg rounded-md px-2 py-1">
                              +{cont.countries.length - 5} autres
                            </span>
                          )}
                        </div>
                      </div>
                    );
                  })}
                  {coverage.by_continent.length === 0 && (
                    <p className="text-muted text-sm text-center py-4">Aucune donnee par continent.</p>
                  )}
                </div>
              )}

              {coverageTab === 'countries' && (
                <div className="overflow-x-auto max-h-80 overflow-y-auto">
                  <table className="w-full text-sm">
                    <thead className="sticky top-0 bg-surface z-10">
                      <tr className="border-b border-border">
                        <th className="text-left text-[10px] text-muted font-medium uppercase tracking-wider px-3 py-2">#</th>
                        <th className="text-left text-[10px] text-muted font-medium uppercase tracking-wider px-3 py-2">Pays</th>
                        <th className="text-right text-[10px] text-muted font-medium uppercase tracking-wider px-3 py-2">Influenceurs</th>
                        <th className="text-left text-[10px] text-muted font-medium uppercase tracking-wider px-3 py-2 min-w-[120px]">Part</th>
                      </tr>
                    </thead>
                    <tbody>
                      {coverage.by_country.map((row, i) => {
                        const pct = coverage.total_influenceurs > 0
                          ? Math.round((row.total / coverage.total_influenceurs) * 100)
                          : 0;
                        return (
                          <tr key={row.country} className="border-b border-border/30 last:border-0">
                            <td className="px-3 py-2 text-muted text-xs">{i + 1}</td>
                            <td className="px-3 py-2 text-white whitespace-nowrap">
                              {getCountryFlag(row.country)} {row.country}
                            </td>
                            <td className="px-3 py-2 text-right text-white font-mono font-bold">{row.total}</td>
                            <td className="px-3 py-2">
                              <div className="flex items-center gap-2">
                                <div className="flex-1 bg-surface2 rounded-full h-1.5">
                                  <div
                                    className="h-1.5 rounded-full bg-cyan transition-all"
                                    style={{ width: `${Math.max(3, pct)}%` }}
                                  />
                                </div>
                                <span className="text-xs text-muted font-mono w-8 text-right">{pct}%</span>
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                  {coverage.by_country.length === 0 && (
                    <p className="text-muted text-sm text-center py-4">Aucune donnee par pays.</p>
                  )}
                </div>
              )}

              {coverageTab === 'languages' && (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-border">
                        <th className="text-left text-[10px] text-muted font-medium uppercase tracking-wider px-3 py-2">#</th>
                        <th className="text-left text-[10px] text-muted font-medium uppercase tracking-wider px-3 py-2">Langue</th>
                        <th className="text-right text-[10px] text-muted font-medium uppercase tracking-wider px-3 py-2">Influenceurs</th>
                        <th className="text-left text-[10px] text-muted font-medium uppercase tracking-wider px-3 py-2 min-w-[120px]">Part</th>
                      </tr>
                    </thead>
                    <tbody>
                      {coverage.by_language.map((row, i) => {
                        const pct = coverage.total_influenceurs > 0
                          ? Math.round((row.total / coverage.total_influenceurs) * 100)
                          : 0;
                        return (
                          <tr key={row.language} className="border-b border-border/30 last:border-0">
                            <td className="px-3 py-2 text-muted text-xs">{i + 1}</td>
                            <td className="px-3 py-2 text-white font-medium uppercase">{row.language}</td>
                            <td className="px-3 py-2 text-right text-white font-mono font-bold">{row.total}</td>
                            <td className="px-3 py-2">
                              <div className="flex items-center gap-2">
                                <div className="flex-1 bg-surface2 rounded-full h-1.5">
                                  <div
                                    className="h-1.5 rounded-full bg-amber transition-all"
                                    style={{ width: `${Math.max(3, pct)}%` }}
                                  />
                                </div>
                                <span className="text-xs text-muted font-mono w-8 text-right">{pct}%</span>
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                  {coverage.by_language.length === 0 && (
                    <p className="text-muted text-sm text-center py-4">Aucune donnee par langue.</p>
                  )}
                </div>
              )}
            </div>
          ) : (
            <div className="p-8 text-center text-muted text-sm">Impossible de charger les donnees de couverture.</div>
          )}
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* À relancer */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-title font-semibold text-white">À relancer</h3>
            <Link to="/a-relancer" className="text-xs text-violet hover:text-violet-light transition-colors">
              Voir tout →
            </Link>
          </div>
          {reminders.length === 0 ? (
            <p className="text-muted text-sm">Aucun rappel en attente.</p>
          ) : (
            <div className="space-y-3">
              {reminders.slice(0, 5).map(r => (
                <div key={r.id} className="flex items-center justify-between py-2 border-b border-border last:border-0">
                  <div>
                    <p className="text-sm font-medium text-white">{r.influenceur?.name}</p>
                    <p className="text-xs text-amber mt-0.5">
                      {r.days_elapsed != null ? `${r.days_elapsed}j sans contact` : 'Date inconnue'}
                    </p>
                  </div>
                  <button
                    onClick={() => dismiss(r.id)}
                    className="text-xs text-muted hover:text-white px-2 py-1 rounded border border-border hover:border-gray-600 transition-colors"
                  >
                    Reporter
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Dernières activités */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">Activité récente</h3>
          {(stats?.recentActivity ?? []).length === 0 ? (
            <p className="text-muted text-sm">Aucune activité.</p>
          ) : (
            <div className="space-y-3">
              {stats?.recentActivity?.map(log => (
                <div key={log.id} className="flex gap-3 py-2 border-b border-border last:border-0">
                  <div className="w-7 h-7 rounded-full bg-violet/20 flex items-center justify-center text-violet-light text-xs font-bold flex-shrink-0">
                    {log.user?.name?.[0] ?? '?'}
                  </div>
                  <div className="min-w-0">
                    <p className="text-sm text-white">
                      <span className="font-medium">{log.user?.name}</span>{' '}
                      {ACTION_LABELS[log.action] ?? log.action}
                      {log.influenceur && (
                        <> <Link to={`/influenceurs/${log.influenceur_id}`} className="text-violet-light hover:underline">{log.influenceur.name}</Link></>
                      )}
                    </p>
                    <p className="text-xs text-muted mt-0.5">
                      {new Date(log.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
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
