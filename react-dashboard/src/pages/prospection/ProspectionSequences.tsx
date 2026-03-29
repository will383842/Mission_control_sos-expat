import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';
import { CONTACT_TYPES } from '../../lib/constants';

interface Sequence {
  id: number; current_step: number; status: string; stop_reason: string | null;
  next_send_at: string | null; started_at: string | null; completed_at: string | null;
  influenceur: { id: number; name: string; email: string; contact_type: string; country: string } | null;
}

const STEP_LABELS: Record<number, string> = {
  1: 'Premier contact', 2: 'Relance J+3', 3: 'Relance J+7', 4: 'Dernier message J+14',
};

const STATUS_CONFIG: Record<string, { bg: string; text: string; label: string }> = {
  active: { bg: 'bg-emerald-500/20', text: 'text-emerald-400', label: 'Active' },
  paused: { bg: 'bg-amber/20', text: 'text-amber', label: 'En pause' },
  completed: { bg: 'bg-blue-500/20', text: 'text-blue-400', label: 'Terminee' },
  stopped: { bg: 'bg-red-500/20', text: 'text-red-400', label: 'Stoppee' },
};

const STOP_REASONS: Record<string, { icon: string; label: string; color: string }> = {
  replied: { icon: '💬', label: 'Contact a repondu', color: 'text-green-400' },
  bounced: { icon: '⛔', label: 'Email bounce (hard)', color: 'text-red-400' },
  hard_bounce: { icon: '⛔', label: 'Hard bounce', color: 'text-red-400' },
  unsubscribed: { icon: '🚫', label: 'Desinscription', color: 'text-gray-400' },
  manual: { icon: '✋', label: 'Arret manuel', color: 'text-amber' },
};

function formatDate(d: string | null): string {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

export default function ProspectionSequences() {
  const [sequences, setSequences] = useState<Sequence[]>([]);
  const [filter, setFilter] = useState('active');
  const [filterType, setFilterType] = useState('');
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await api.get(`/outreach/sequences?status=${filter === 'all' ? '' : filter}`);
      setSequences(data.data || []);
    } catch { /* ignore */ }
    setLoading(false);
  };

  useEffect(() => { fetchData(); }, [filter]);

  const handleAction = async (id: number, action: string) => {
    setActionLoading(id);
    try {
      await api.post(`/outreach/sequences/${id}/${action}`);
      fetchData();
    } catch { /* ignore */ }
    setActionLoading(null);
  };

  // Stats
  const filtered = filterType ? sequences.filter(s => s.influenceur?.contact_type === filterType) : sequences;
  const statusCounts = {
    active: sequences.filter(s => s.status === 'active').length,
    paused: sequences.filter(s => s.status === 'paused').length,
    completed: sequences.filter(s => s.status === 'completed').length,
    stopped: sequences.filter(s => s.status === 'stopped').length,
  };
  const stepCounts = [1, 2, 3, 4].map(step => sequences.filter(s => s.current_step === step && s.status === 'active').length);

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center gap-3">
        <Link to="/prospection" className="text-muted hover:text-white transition-colors text-sm">&larr; Prospection</Link>
        <h1 className="text-2xl font-title font-bold text-white">Sequences</h1>
        <span className="text-muted text-sm ml-auto">{sequences.length} sequence{sequences.length !== 1 ? 's' : ''}</span>
      </div>

      {/* KPI row */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        {Object.entries(statusCounts).map(([status, count]) => {
          const cfg = STATUS_CONFIG[status];
          return (
            <button key={status} onClick={() => setFilter(status)}
              className={`bg-surface border rounded-xl p-4 transition-colors text-left ${filter === status ? 'border-violet/50' : 'border-border hover:border-border/80'}`}>
              <p className="text-[10px] text-muted uppercase tracking-wider">{cfg.label}</p>
              <p className={`text-2xl font-bold font-title mt-1 ${cfg.text}`}>{count}</p>
            </button>
          );
        })}
      </div>

      {/* Pipeline visual (active only) */}
      {statusCounts.active > 0 && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="text-white font-title font-semibold text-sm mb-4">Pipeline actif</h3>
          <div className="flex items-center gap-2">
            {[
              { label: 'Step 1', count: stepCounts[0], color: 'bg-cyan', desc: 'Premier contact' },
              { label: 'Step 2', count: stepCounts[1], color: 'bg-blue-500', desc: 'Relance J+3' },
              { label: 'Step 3', count: stepCounts[2], color: 'bg-violet', desc: 'Relance J+7' },
              { label: 'Step 4', count: stepCounts[3], color: 'bg-purple-500', desc: 'Dernier msg' },
            ].map((s, i) => (
              <React.Fragment key={i}>
                {i > 0 && <div className="text-muted text-xs flex-shrink-0">&rarr;</div>}
                <div className="flex-1 text-center">
                  <div className={`${s.color} rounded-lg py-3 px-2`}>
                    <div className="text-white text-lg font-bold">{s.count}</div>
                  </div>
                  <div className="text-[10px] text-muted mt-1">{s.label}</div>
                  <div className="text-[9px] text-muted/50">{s.desc}</div>
                </div>
              </React.Fragment>
            ))}
          </div>
        </div>
      )}

      {/* Filters */}
      <div className="flex flex-wrap gap-3 items-center">
        <div className="flex gap-1">
          {['active', 'paused', 'completed', 'stopped', 'all'].map(f => (
            <button key={f} onClick={() => setFilter(f)}
              className={`px-3 py-1.5 text-xs rounded-lg transition-colors ${filter === f ? 'bg-violet text-white' : 'text-muted hover:text-white hover:bg-surface2'}`}>
              {f === 'all' ? 'Toutes' : STATUS_CONFIG[f]?.label || f}
            </button>
          ))}
        </div>
        <select value={filterType} onChange={e => setFilterType(e.target.value)}
          className="bg-bg border border-border rounded-lg px-3 py-1.5 text-white text-xs focus:border-violet outline-none">
          <option value="">Tous les types</option>
          {CONTACT_TYPES.map(t => <option key={t.value} value={t.value}>{t.icon} {t.label}</option>)}
        </select>
      </div>

      {/* Table */}
      {loading ? (
        <div className="flex justify-center py-12"><div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>
      ) : (
        <div className="bg-surface border border-border rounded-xl overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border">
                {['Contact', 'Type', 'Pays', 'Progression', 'Status', 'Prochain envoi', 'Actions'].map(h => (
                  <th key={h} className="text-left text-[10px] text-muted font-medium uppercase px-4 py-3">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {filtered.map(seq => {
                const cfg = STATUS_CONFIG[seq.status] || STATUS_CONFIG.active;
                const stopCfg = seq.stop_reason ? STOP_REASONS[seq.stop_reason] : null;
                const isExpanded = expandedId === seq.id;
                return (
                  <React.Fragment key={seq.id}>
                    <tr className={`border-b border-border/50 hover:bg-surface2/50 cursor-pointer transition-colors ${isExpanded ? 'bg-surface2/30' : ''}`}
                      onClick={() => setExpandedId(isExpanded ? null : seq.id)}>
                      <td className="px-4 py-3">
                        <Link to={`/contacts/${seq.influenceur?.id}`} className="hover:text-violet-light transition-colors"
                          onClick={e => e.stopPropagation()}>
                          <div className="text-white text-xs font-medium">{seq.influenceur?.name}</div>
                          <div className="text-[10px] text-cyan">{seq.influenceur?.email}</div>
                        </Link>
                      </td>
                      <td className="px-4 py-3">
                        <span className="text-xs bg-violet/10 text-violet-light px-2 py-0.5 rounded">{seq.influenceur?.contact_type}</span>
                      </td>
                      <td className="px-4 py-3 text-xs text-muted">{seq.influenceur?.country}</td>
                      <td className="px-4 py-3">
                        <div className="flex gap-1">
                          {[1, 2, 3, 4].map(s => (
                            <span key={s} className={`w-6 h-6 rounded flex items-center justify-center text-[10px] font-bold ${
                              s <= seq.current_step ? 'bg-emerald-500/20 text-emerald-400' :
                              s === seq.current_step + 1 && seq.status === 'active' ? 'bg-cyan/10 text-cyan/50 animate-pulse' :
                              'bg-surface2 text-muted/30'
                            }`}>{s}</span>
                          ))}
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <span className={`px-2 py-0.5 text-[10px] rounded-full font-medium ${cfg.bg} ${cfg.text}`}>
                          {cfg.label}
                        </span>
                        {stopCfg && (
                          <div className="flex items-center gap-1 mt-1">
                            <span className="text-[10px]">{stopCfg.icon}</span>
                            <span className={`text-[10px] ${stopCfg.color}`}>{stopCfg.label}</span>
                          </div>
                        )}
                      </td>
                      <td className="px-4 py-3 text-xs text-muted">
                        {seq.next_send_at ? formatDate(seq.next_send_at) : '—'}
                      </td>
                      <td className="px-4 py-3" onClick={e => e.stopPropagation()}>
                        <div className="flex gap-1">
                          {seq.status === 'active' && (
                            <button onClick={() => handleAction(seq.id, 'pause')} disabled={actionLoading === seq.id}
                              className="px-2 py-1 text-[10px] bg-amber/20 text-amber rounded hover:bg-amber/30 transition-colors">Pause</button>
                          )}
                          {seq.status === 'paused' && (
                            <button onClick={() => handleAction(seq.id, 'resume')} disabled={actionLoading === seq.id}
                              className="px-2 py-1 text-[10px] bg-emerald-500/20 text-emerald-400 rounded hover:bg-emerald-500/30 transition-colors">Reprendre</button>
                          )}
                          {['active', 'paused'].includes(seq.status) && (
                            <button onClick={() => handleAction(seq.id, 'stop')} disabled={actionLoading === seq.id}
                              className="px-2 py-1 text-[10px] text-red-400/60 hover:text-red-400 rounded transition-colors">Stop</button>
                          )}
                        </div>
                      </td>
                    </tr>
                    {/* Expanded timeline */}
                    {isExpanded && (
                      <tr>
                        <td colSpan={7} className="bg-surface2/20 px-6 py-4 border-b border-border/50">
                          <div className="flex items-start gap-6">
                            {/* Step timeline */}
                            <div className="flex-1">
                              <p className="text-xs text-muted mb-3 font-medium">Timeline de la sequence</p>
                              <div className="space-y-3">
                                {[1, 2, 3, 4].map(step => {
                                  const done = step <= seq.current_step;
                                  const current = step === seq.current_step + 1 && seq.status === 'active';
                                  const isStopped = seq.status === 'stopped' && step === seq.current_step + 1;
                                  return (
                                    <div key={step} className="flex items-center gap-3">
                                      <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 ${
                                        done ? 'bg-emerald-500/20 text-emerald-400' :
                                        current ? 'bg-cyan/20 text-cyan ring-2 ring-cyan/30' :
                                        isStopped ? 'bg-red-500/20 text-red-400' :
                                        'bg-surface2 text-muted/40'
                                      }`}>{step}</div>
                                      <div className="flex-1">
                                        <p className={`text-xs font-medium ${done ? 'text-white' : current ? 'text-cyan' : 'text-muted/50'}`}>
                                          {STEP_LABELS[step]}
                                        </p>
                                        <p className="text-[10px] text-muted">
                                          {done ? 'Envoye' :
                                           current ? (seq.next_send_at ? `Prevu le ${formatDate(seq.next_send_at)}` : 'En attente') :
                                           isStopped ? (stopCfg ? `${stopCfg.icon} ${stopCfg.label}` : 'Stoppe') :
                                           'A venir'}
                                        </p>
                                      </div>
                                      {step < 4 && (
                                        <span className="text-[10px] text-muted/30 flex-shrink-0">
                                          {step === 1 ? '+3j' : step === 2 ? '+4j' : '+7j'}
                                        </span>
                                      )}
                                    </div>
                                  );
                                })}
                              </div>
                            </div>
                            {/* Info panel */}
                            <div className="w-48 space-y-3">
                              <div>
                                <p className="text-[10px] text-muted uppercase tracking-wider">Demarree le</p>
                                <p className="text-xs text-white">{formatDate(seq.started_at)}</p>
                              </div>
                              {seq.completed_at && (
                                <div>
                                  <p className="text-[10px] text-muted uppercase tracking-wider">Terminee le</p>
                                  <p className="text-xs text-white">{formatDate(seq.completed_at)}</p>
                                </div>
                              )}
                              {seq.stop_reason && stopCfg && (
                                <div className="p-2 bg-red-500/10 border border-red-500/20 rounded-lg">
                                  <p className="text-[10px] text-red-400 font-medium">{stopCfg.icon} {stopCfg.label}</p>
                                </div>
                              )}
                              <Link to={`/contacts/${seq.influenceur?.id}`}
                                className="block text-xs text-violet hover:text-violet-light transition-colors">
                                Voir la fiche contact &rarr;
                              </Link>
                            </div>
                          </div>
                        </td>
                      </tr>
                    )}
                  </React.Fragment>
                );
              })}
              {filtered.length === 0 && (
                <tr><td colSpan={7} className="text-center py-12 text-muted text-sm">Aucune sequence {filter !== 'all' ? STATUS_CONFIG[filter]?.label.toLowerCase() : ''}</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
