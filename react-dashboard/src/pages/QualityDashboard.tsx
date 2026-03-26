import React, { useEffect, useState } from 'react';
import api from '../api/client';

interface QualityStats {
  total: number;
  email: { no_email: number; unverified: number; verified: number; invalid: number; risky: number };
  scores: { not_scored: number; low: number; medium: number; good: number; excellent: number };
  pending_dupes: number;
  pending_types: number;
}

interface DupeFlag {
  id: number; match_type: string; confidence: number; status: string;
  influenceur_a: { id: number; name: string; email: string; contact_type: string; country: string; profile_url: string } | null;
  influenceur_b: { id: number; name: string; email: string; contact_type: string; country: string; profile_url: string } | null;
}

interface TypeFlag {
  id: number; current_type: string; suggested_type: string | null; reason: string; details: Record<string, string>; status: string;
  influenceur: { id: number; name: string; email: string; contact_type: string; country: string; profile_url: string } | null;
}

type Tab = 'overview' | 'duplicates' | 'types';

export default function QualityDashboard() {
  const [stats, setStats] = useState<QualityStats | null>(null);
  const [dupes, setDupes] = useState<DupeFlag[]>([]);
  const [typeFlags, setTypeFlags] = useState<TypeFlag[]>([]);
  const [tab, setTab] = useState<Tab>('overview');
  const [loading, setLoading] = useState(true);
  const [running, setRunning] = useState(false);
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  const fetchAll = async () => {
    setLoading(true);
    try {
      const [statsRes, dupesRes, typesRes] = await Promise.all([
        api.get('/quality/dashboard'),
        api.get('/quality/duplicates?status=pending'),
        api.get('/quality/type-flags?status=pending'),
      ]);
      setStats(statsRes.data);
      setDupes(dupesRes.data.data || []);
      setTypeFlags(typesRes.data.data || []);
    } catch { /* ignore */ }
    setLoading(false);
  };

  useEffect(() => { fetchAll(); }, []);

  const runPipeline = async () => {
    setRunning(true);
    try {
      await api.post('/quality/run-all');
      setTimeout(fetchAll, 5000);
    } catch { /* ignore */ }
    setTimeout(() => setRunning(false), 5000);
  };

  const resolveDupe = async (flagId: number, action: string) => {
    setActionLoading(flagId);
    try {
      await api.post(`/quality/duplicates/${flagId}/resolve`, { action });
      setDupes(prev => prev.filter(d => d.id !== flagId));
      setStats(prev => prev ? { ...prev, pending_dupes: prev.pending_dupes - 1 } : prev);
    } catch { /* ignore */ }
    setActionLoading(null);
  };

  const resolveType = async (flagId: number, action: string, newType?: string) => {
    setActionLoading(flagId);
    try {
      await api.post(`/quality/type-flags/${flagId}/resolve`, { action, new_type: newType });
      setTypeFlags(prev => prev.filter(f => f.id !== flagId));
      setStats(prev => prev ? { ...prev, pending_types: prev.pending_types - 1 } : prev);
    } catch { /* ignore */ }
    setActionLoading(null);
  };

  if (loading) return <div className="flex items-center justify-center h-64"><div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>;

  const tabClass = (t: Tab) => `px-4 py-2 text-sm font-medium rounded-lg transition-colors ${tab === t ? 'bg-violet text-white' : 'text-muted hover:text-white hover:bg-surface2'}`;

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-title font-bold text-white">Qualite des contacts</h1>
          <p className="text-muted text-sm mt-1">Verification, deduplication et classification</p>
        </div>
        <button onClick={runPipeline} disabled={running}
          className="px-4 py-2 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white text-sm rounded-lg transition-colors">
          {running ? 'Verification en cours...' : 'Lancer la verification'}
        </button>
      </div>

      {/* KPI cards */}
      {stats && (() => {
        const validEmails = stats.email.verified + stats.email.risky;
        const totalWithEmail = validEmails + stats.email.invalid + stats.email.unverified;
        return (
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div className="bg-surface border border-emerald-500/30 rounded-xl p-4">
              <div className="text-[10px] text-muted uppercase mb-1">Emails valides</div>
              <div className="text-2xl font-bold text-emerald-400">{validEmails}</div>
              <div className="text-xs text-muted">{stats.email.verified} confirmes + {stats.email.risky} probables</div>
            </div>
            <div className="bg-surface border border-border rounded-xl p-4">
              <div className="text-[10px] text-muted uppercase mb-1">En attente</div>
              <div className="text-2xl font-bold text-amber">{stats.email.unverified}</div>
              <div className="text-xs text-muted">non verifies</div>
            </div>
            <div className="bg-surface border border-border rounded-xl p-4">
              <div className="text-[10px] text-muted uppercase mb-1">Invalides</div>
              <div className="text-2xl font-bold text-red-400">{stats.email.invalid}</div>
              <div className="text-xs text-muted">MX ou SMTP rejete</div>
            </div>
            <div className="bg-surface border border-border rounded-xl p-4">
              <div className="text-[10px] text-muted uppercase mb-1">Sans email</div>
              <div className="text-2xl font-bold text-muted">{stats.email.no_email}</div>
              <div className="text-xs text-muted">{stats.total > 0 ? Math.round(stats.email.no_email / stats.total * 100) : 0}% du total</div>
            </div>
            <div className="bg-surface border border-border rounded-xl p-4">
              <div className="text-[10px] text-muted uppercase mb-1">Doublons</div>
              <div className="text-2xl font-bold text-violet-light">{stats.pending_dupes}</div>
              <div className="text-xs text-muted">a resoudre</div>
            </div>
            <div className="bg-surface border border-border rounded-xl p-4">
              <div className="text-[10px] text-muted uppercase mb-1">Mauvais types</div>
              <div className="text-2xl font-bold text-orange-400">{stats.pending_types}</div>
              <div className="text-xs text-muted">a corriger</div>
            </div>
          </div>
        );
      })()}

      {/* Tabs */}
      <div className="flex gap-2">
        <button className={tabClass('overview')} onClick={() => setTab('overview')}>Vue d'ensemble</button>
        <button className={tabClass('duplicates')} onClick={() => setTab('duplicates')}>
          Doublons {stats && stats.pending_dupes > 0 && <span className="ml-1 px-1.5 py-0.5 bg-violet/30 text-violet-light rounded-full text-[10px]">{stats.pending_dupes}</span>}
        </button>
        <button className={tabClass('types')} onClick={() => setTab('types')}>
          Mauvais types {stats && stats.pending_types > 0 && <span className="ml-1 px-1.5 py-0.5 bg-orange-500/30 text-orange-400 rounded-full text-[10px]">{stats.pending_types}</span>}
        </button>
      </div>

      {/* Overview tab */}
      {tab === 'overview' && stats && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Email verification */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white mb-4">Verification emails</h3>
            {[
              { label: 'Verifies', value: stats.email.verified, color: 'bg-emerald-500', textColor: 'text-emerald-400' },
              { label: 'Risques (catch-all)', value: stats.email.risky, color: 'bg-amber', textColor: 'text-amber' },
              { label: 'Non verifies', value: stats.email.unverified, color: 'bg-gray-500', textColor: 'text-muted' },
              { label: 'Invalides', value: stats.email.invalid, color: 'bg-red-500', textColor: 'text-red-400' },
              { label: 'Sans email', value: stats.email.no_email, color: 'bg-gray-700', textColor: 'text-muted/50' },
            ].map(row => (
              <div key={row.label} className="flex items-center gap-3 mb-2">
                <div className="w-24 bg-surface2 rounded-full h-2">
                  <div className={`h-2 rounded-full ${row.color}`} style={{ width: `${Math.max(stats.total > 0 ? row.value / stats.total * 100 : 0, 2)}%` }} />
                </div>
                <span className={`text-sm font-mono ${row.textColor}`}>{row.value}</span>
                <span className="text-xs text-muted">{row.label}</span>
              </div>
            ))}
          </div>

          {/* Quality scores */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white mb-4">Scores de qualite</h3>
            {[
              { label: 'Excellent (76-100)', value: stats.scores.excellent, color: 'bg-emerald-500', textColor: 'text-emerald-400' },
              { label: 'Bon (51-75)', value: stats.scores.good, color: 'bg-green-500', textColor: 'text-green-400' },
              { label: 'Moyen (26-50)', value: stats.scores.medium, color: 'bg-amber', textColor: 'text-amber' },
              { label: 'Faible (1-25)', value: stats.scores.low, color: 'bg-red-500', textColor: 'text-red-400' },
              { label: 'Non evalue', value: stats.scores.not_scored, color: 'bg-gray-600', textColor: 'text-muted' },
            ].map(row => (
              <div key={row.label} className="flex items-center gap-3 mb-2">
                <div className="w-24 bg-surface2 rounded-full h-2">
                  <div className={`h-2 rounded-full ${row.color}`} style={{ width: `${Math.max(stats.total > 0 ? row.value / stats.total * 100 : 0, 2)}%` }} />
                </div>
                <span className={`text-sm font-mono ${row.textColor}`}>{row.value}</span>
                <span className="text-xs text-muted">{row.label}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Duplicates tab */}
      {tab === 'duplicates' && (
        <div className="bg-surface border border-border rounded-xl overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border">
                <th className="text-left text-[10px] text-muted font-medium uppercase px-4 py-3">Contact A</th>
                <th className="text-left text-[10px] text-muted font-medium uppercase px-4 py-3">Contact B</th>
                <th className="text-left text-[10px] text-muted font-medium uppercase px-4 py-3">Type</th>
                <th className="text-center text-[10px] text-muted font-medium uppercase px-4 py-3">Confiance</th>
                <th className="text-right text-[10px] text-muted font-medium uppercase px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              {dupes.map(d => (
                <tr key={d.id} className="border-b border-border/50 hover:bg-surface2">
                  <td className="px-4 py-3">
                    <div className="text-white text-xs font-medium">{d.influenceur_a?.name}</div>
                    <div className="text-[10px] text-muted">{d.influenceur_a?.contact_type} | {d.influenceur_a?.country}</div>
                    <div className="text-[10px] text-cyan">{d.influenceur_a?.email || '—'}</div>
                  </td>
                  <td className="px-4 py-3">
                    <div className="text-white text-xs font-medium">{d.influenceur_b?.name}</div>
                    <div className="text-[10px] text-muted">{d.influenceur_b?.contact_type} | {d.influenceur_b?.country}</div>
                    <div className="text-[10px] text-cyan">{d.influenceur_b?.email || '—'}</div>
                  </td>
                  <td className="px-4 py-3">
                    <span className="px-2 py-0.5 text-[10px] bg-violet/20 text-violet-light rounded-full">{d.match_type.replace('_', ' ')}</span>
                  </td>
                  <td className="px-4 py-3 text-center">
                    <span className={`font-mono text-xs ${d.confidence >= 80 ? 'text-red-400' : 'text-amber'}`}>{d.confidence}%</span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-1 justify-end">
                      <button onClick={() => resolveDupe(d.id, 'merge_a')} disabled={actionLoading === d.id}
                        className="px-2 py-1 text-[10px] bg-emerald-500/20 text-emerald-400 rounded hover:bg-emerald-500/30">Garder A</button>
                      <button onClick={() => resolveDupe(d.id, 'merge_b')} disabled={actionLoading === d.id}
                        className="px-2 py-1 text-[10px] bg-blue-500/20 text-blue-400 rounded hover:bg-blue-500/30">Garder B</button>
                      <button onClick={() => resolveDupe(d.id, 'keep_both')} disabled={actionLoading === d.id}
                        className="px-2 py-1 text-[10px] bg-surface2 text-muted rounded hover:text-white">Les deux</button>
                      <button onClick={() => resolveDupe(d.id, 'dismiss')} disabled={actionLoading === d.id}
                        className="px-2 py-1 text-[10px] text-muted hover:text-white">Ignorer</button>
                    </div>
                  </td>
                </tr>
              ))}
              {dupes.length === 0 && (
                <tr><td colSpan={5} className="text-center py-8 text-muted text-sm">Aucun doublon en attente</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Type flags tab */}
      {tab === 'types' && (
        <div className="bg-surface border border-border rounded-xl overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border">
                <th className="text-left text-[10px] text-muted font-medium uppercase px-4 py-3">Contact</th>
                <th className="text-left text-[10px] text-muted font-medium uppercase px-4 py-3">Type actuel</th>
                <th className="text-left text-[10px] text-muted font-medium uppercase px-4 py-3">Probleme</th>
                <th className="text-left text-[10px] text-muted font-medium uppercase px-4 py-3">Type suggere</th>
                <th className="text-right text-[10px] text-muted font-medium uppercase px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              {typeFlags.map(f => (
                <tr key={f.id} className="border-b border-border/50 hover:bg-surface2">
                  <td className="px-4 py-3">
                    <div className="text-white text-xs font-medium">{f.influenceur?.name}</div>
                    <div className="text-[10px] text-cyan">{f.influenceur?.email || '—'}</div>
                  </td>
                  <td className="px-4 py-3">
                    <span className="text-xs text-muted">{f.current_type}</span>
                  </td>
                  <td className="px-4 py-3">
                    <span className="text-xs text-orange-400">{f.reason.replace(/_/g, ' ')}</span>
                    {f.details && <div className="text-[10px] text-muted mt-0.5">{JSON.stringify(f.details).slice(0, 60)}</div>}
                  </td>
                  <td className="px-4 py-3">
                    {f.suggested_type ? (
                      <span className="text-xs text-emerald-400">{f.suggested_type}</span>
                    ) : (
                      <span className="text-xs text-muted">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-1 justify-end">
                      {f.suggested_type && (
                        <button onClick={() => resolveType(f.id, 'fix', f.suggested_type!)} disabled={actionLoading === f.id}
                          className="px-2 py-1 text-[10px] bg-emerald-500/20 text-emerald-400 rounded hover:bg-emerald-500/30">
                          Corriger → {f.suggested_type}
                        </button>
                      )}
                      <button onClick={() => resolveType(f.id, 'dismiss')} disabled={actionLoading === f.id}
                        className="px-2 py-1 text-[10px] text-muted hover:text-white">Ignorer</button>
                    </div>
                  </td>
                </tr>
              ))}
              {typeFlags.length === 0 && (
                <tr><td colSpan={5} className="text-center py-8 text-muted text-sm">Aucun probleme de type en attente</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
