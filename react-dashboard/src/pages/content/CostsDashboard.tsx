import { useEffect, useState } from 'react';
import {
  AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend,
} from 'recharts';
import { useCosts } from '../../hooks/useContentEngine';
import * as contentApi from '../../api/contentApi';
import type { CostBreakdownEntry, CostTrendEntry } from '../../types/content';

const SERVICE_COLORS: Record<string, string> = {
  openai: '#8b5cf6',
  perplexity: '#3b82f6',
  dalle: '#f97316',
  anthropic: '#10b981',
  unsplash: '#6b7280',
};

function cents(val: number): string {
  return `$${(val / 100).toFixed(2)}`;
}

function pct(val: number, total: number): number {
  return total > 0 ? Math.round((val / total) * 100) : 0;
}

export default function CostsDashboard() {
  const { overview, loading, load } = useCosts();
  const [breakdown, setBreakdown] = useState<CostBreakdownEntry[]>([]);
  const [trends, setTrends] = useState<CostTrendEntry[]>([]);
  const [loadingBreakdown, setLoadingBreakdown] = useState(false);
  const [loadingTrends, setLoadingTrends] = useState(false);

  useEffect(() => {
    load();
    loadBreakdown();
    loadTrends();
  }, []);

  const loadBreakdown = async () => {
    setLoadingBreakdown(true);
    try {
      const { data } = await contentApi.fetchCostBreakdown({ period: 'month' });
      setBreakdown(data);
    } catch { /* silent */ }
    finally { setLoadingBreakdown(false); }
  };

  const loadTrends = async () => {
    setLoadingTrends(true);
    try {
      const { data } = await contentApi.fetchCostTrends({ days: 30 });
      setTrends(data);
    } catch { /* silent */ }
    finally { setLoadingTrends(false); }
  };

  // Transform trends for stacked area chart
  const allServices = new Set<string>();
  trends.forEach(t => {
    Object.keys(t.by_service).forEach(s => allServices.add(s));
  });
  const serviceKeys = Array.from(allServices);

  const chartData = trends.map(t => {
    const row: Record<string, number | string> = { date: t.date.slice(5) }; // MM-DD
    serviceKeys.forEach(s => {
      row[s] = (t.by_service[s] ?? 0) / 100; // cents to dollars
    });
    return row;
  });

  // Budget calculations
  const dailyBudget = overview?.daily_budget_cents ?? 0;
  const monthlyBudget = overview?.monthly_budget_cents ?? 0;
  const todayCents = overview?.today_cents ?? 0;
  const monthCents = overview?.this_month_cents ?? 0;
  const budgetRemaining = monthlyBudget - monthCents;

  // Breakdown grouped by service
  const grouped: Record<string, CostBreakdownEntry[]> = {};
  breakdown.forEach(entry => {
    if (!grouped[entry.service]) grouped[entry.service] = [];
    grouped[entry.service].push(entry);
  });
  const totalCostCents = breakdown.reduce((sum, e) => sum + e.total_cost_cents, 0);

  if (loading && !overview) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-violet" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="font-title text-2xl font-bold text-white">Couts IA</h1>
        <button
          onClick={() => { load(); loadBreakdown(); loadTrends(); }}
          className="px-3 py-1.5 text-sm bg-surface2 hover:bg-surface2/80 text-white rounded-lg transition"
        >
          Rafraichir
        </button>
      </div>

      {/* Budget warnings */}
      {overview?.is_over_daily && (
        <div className="bg-amber/10 border border-yellow-500/30 rounded-lg p-4 flex items-center gap-3">
          <span className="text-amber text-lg">!</span>
          <p className="text-amber text-sm">
            Budget journalier depasse : {cents(todayCents)} / {cents(dailyBudget)}
          </p>
        </div>
      )}
      {overview?.is_over_monthly && (
        <div className="bg-danger/10 border border-red-500/30 rounded-lg p-4 flex items-center gap-3">
          <span className="text-danger text-lg">!</span>
          <p className="text-danger text-sm">
            Budget mensuel depasse : {cents(monthCents)} / {cents(monthlyBudget)}
          </p>
        </div>
      )}

      {/* 4 stat cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Today */}
        <div className="bg-surface rounded-xl p-5 border border-border">
          <p className="text-muted text-sm mb-1">Aujourd'hui</p>
          <p className="text-2xl font-bold text-white">{cents(todayCents)}</p>
          {dailyBudget > 0 && (
            <div className="mt-2">
              <div className="w-full bg-surface2 rounded-full h-1.5">
                <div
                  className={`h-1.5 rounded-full transition-all ${
                    todayCents > dailyBudget ? 'bg-danger' : 'bg-violet'
                  }`}
                  style={{ width: `${Math.min(100, pct(todayCents, dailyBudget))}%` }}
                />
              </div>
              <p className="text-xs text-muted mt-1">{pct(todayCents, dailyBudget)}% du budget</p>
            </div>
          )}
        </div>

        {/* This week */}
        <div className="bg-surface rounded-xl p-5 border border-border">
          <p className="text-muted text-sm mb-1">Cette semaine</p>
          <p className="text-2xl font-bold text-white">{cents(overview?.this_week_cents ?? 0)}</p>
        </div>

        {/* This month */}
        <div className="bg-surface rounded-xl p-5 border border-border">
          <p className="text-muted text-sm mb-1">Ce mois</p>
          <p className="text-2xl font-bold text-white">{cents(monthCents)}</p>
          {monthlyBudget > 0 && (
            <div className="mt-2">
              <div className="w-full bg-surface2 rounded-full h-1.5">
                <div
                  className={`h-1.5 rounded-full transition-all ${
                    monthCents > monthlyBudget ? 'bg-danger' : 'bg-success'
                  }`}
                  style={{ width: `${Math.min(100, pct(monthCents, monthlyBudget))}%` }}
                />
              </div>
              <p className="text-xs text-muted mt-1">{pct(monthCents, monthlyBudget)}% du budget</p>
            </div>
          )}
        </div>

        {/* Budget remaining */}
        <div className="bg-surface rounded-xl p-5 border border-border">
          <p className="text-muted text-sm mb-1">Budget restant</p>
          <p className={`text-2xl font-bold ${budgetRemaining >= 0 ? 'text-success' : 'text-danger'}`}>
            {cents(Math.max(0, budgetRemaining))}
          </p>
          {monthlyBudget > 0 && (
            <p className="text-xs text-muted mt-1">
              sur {cents(monthlyBudget)} ({100 - pct(monthCents, monthlyBudget)}%)
            </p>
          )}
        </div>
      </div>

      {/* Trends chart */}
      <div className="bg-surface rounded-xl p-6 border border-border">
        <h2 className="text-lg font-semibold text-white mb-4">Tendance 30 jours</h2>
        {loadingTrends ? (
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-violet" />
          </div>
        ) : chartData.length === 0 ? (
          <p className="text-muted text-sm text-center py-16">Aucune donnee de couts disponible.</p>
        ) : (
          <ResponsiveContainer width="100%" height={320}>
            <AreaChart data={chartData} margin={{ top: 5, right: 20, left: 10, bottom: 5 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#1e2530" />
              <XAxis dataKey="date" stroke="#6b7280" tick={{ fontSize: 11 }} />
              <YAxis stroke="#6b7280" tick={{ fontSize: 11 }} tickFormatter={v => `$${v}`} />
              <Tooltip
                contentStyle={{ backgroundColor: '#101419', border: '1px solid #1e2530', borderRadius: 8 }}
                labelStyle={{ color: '#f3f4f6' }}
                itemStyle={{ color: '#d1d5db' }}
                formatter={(value: number) => [`$${value.toFixed(2)}`, undefined]}
              />
              <Legend />
              {serviceKeys.map(service => (
                <Area
                  key={service}
                  type="monotone"
                  dataKey={service}
                  stackId="1"
                  stroke={SERVICE_COLORS[service] ?? '#6b7280'}
                  fill={SERVICE_COLORS[service] ?? '#6b7280'}
                  fillOpacity={0.4}
                />
              ))}
            </AreaChart>
          </ResponsiveContainer>
        )}
      </div>

      {/* Breakdown table */}
      <div className="bg-surface rounded-xl p-6 border border-border">
        <h2 className="text-lg font-semibold text-white mb-4">Repartition detaillee</h2>
        {loadingBreakdown ? (
          <div className="flex items-center justify-center h-32">
            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-violet" />
          </div>
        ) : breakdown.length === 0 ? (
          <p className="text-muted text-sm">Aucune donnee.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-muted border-b border-border">
                  <th className="text-left py-2 px-3">Service</th>
                  <th className="text-left py-2 px-3">Modele</th>
                  <th className="text-left py-2 px-3">Operation</th>
                  <th className="text-right py-2 px-3">Appels</th>
                  <th className="text-right py-2 px-3">Tokens</th>
                  <th className="text-right py-2 px-3">Cout</th>
                </tr>
              </thead>
              <tbody>
                {Object.entries(grouped).map(([service, entries]) => (
                  entries.map((entry, i) => (
                    <tr key={`${service}-${i}`} className="border-b border-border/50 hover:bg-surface2/30">
                      {i === 0 && (
                        <td className="py-2 px-3 text-white font-medium" rowSpan={entries.length}>
                          <div className="flex items-center gap-2">
                            <span
                              className="w-2.5 h-2.5 rounded-full"
                              style={{ backgroundColor: SERVICE_COLORS[service] ?? '#6b7280' }}
                            />
                            {service}
                          </div>
                        </td>
                      )}
                      <td className="py-2 px-3 text-white">{entry.model}</td>
                      <td className="py-2 px-3 text-muted">{entry.operation}</td>
                      <td className="py-2 px-3 text-right text-white">{entry.count.toLocaleString()}</td>
                      <td className="py-2 px-3 text-right text-white">{entry.total_tokens.toLocaleString()}</td>
                      <td className="py-2 px-3 text-right text-white font-medium">{cents(entry.total_cost_cents)}</td>
                    </tr>
                  ))
                ))}
              </tbody>
              <tfoot>
                <tr className="border-t-2 border-border">
                  <td colSpan={5} className="py-3 px-3 text-white font-bold">TOTAL</td>
                  <td className="py-3 px-3 text-right text-white font-bold">{cents(totalCostCents)}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
