import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';

interface Sequence {
  id: number; current_step: number; status: string; stop_reason: string | null;
  next_send_at: string | null; started_at: string | null;
  influenceur: { id: number; name: string; email: string; contact_type: string; country: string } | null;
}

export default function ProspectionSequences() {
  const [sequences, setSequences] = useState<Sequence[]>([]);
  const [filter, setFilter] = useState('active');
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await api.get(`/outreach/sequences?status=${filter}`);
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

  // Pipeline summary
  const stepCounts = [1, 2, 3, 4].map(step => sequences.filter(s => s.current_step === step).length);
  const totalActive = sequences.filter(s => s.status === 'active').length;

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center gap-3">
        <Link to="/prospection" className="text-muted hover:text-white transition-colors text-sm">← Prospection</Link>
        <h1 className="text-2xl font-title font-bold text-white">Sequences</h1>
      </div>

      {/* Pipeline visual */}
      {totalActive > 0 && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="text-white font-title font-semibold mb-4">Pipeline</h3>
          <div className="flex items-center gap-2">
            {[
              { label: 'Step 1', count: stepCounts[0], color: 'bg-cyan' },
              { label: 'Step 2', count: stepCounts[1], color: 'bg-blue-500' },
              { label: 'Step 3', count: stepCounts[2], color: 'bg-violet' },
              { label: 'Step 4', count: stepCounts[3], color: 'bg-purple-500' },
            ].map((s, i) => (
              <React.Fragment key={i}>
                {i > 0 && <span className="text-muted text-xs">→</span>}
                <div className="flex-1 text-center">
                  <div className={`${s.color} rounded-lg py-3 px-2`}>
                    <div className="text-white text-lg font-bold">{s.count}</div>
                  </div>
                  <div className="text-xs text-muted mt-1">{s.label}</div>
                </div>
              </React.Fragment>
            ))}
          </div>
        </div>
      )}

      {/* Filter */}
      <div className="flex gap-2">
        {['active', 'paused', 'completed', 'stopped', 'all'].map(f => (
          <button key={f} onClick={() => setFilter(f)}
            className={`px-3 py-1.5 text-xs rounded-lg transition-colors ${filter === f ? 'bg-violet text-white' : 'text-muted hover:text-white hover:bg-surface2'}`}>
            {f === 'all' ? 'Toutes' : f.charAt(0).toUpperCase() + f.slice(1)}
          </button>
        ))}
      </div>

      {/* Table */}
      {loading ? (
        <div className="flex justify-center py-12"><div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>
      ) : (
        <div className="bg-surface border border-border rounded-xl overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border">
                {['Contact', 'Type', 'Pays', 'Step', 'Status', 'Prochain envoi', 'Actions'].map(h => (
                  <th key={h} className="text-left text-[10px] text-muted font-medium uppercase px-4 py-3">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {sequences.map(seq => (
                <tr key={seq.id} className="border-b border-border/50 hover:bg-surface2">
                  <td className="px-4 py-3">
                    <div className="text-white text-xs font-medium">{seq.influenceur?.name}</div>
                    <div className="text-[10px] text-cyan">{seq.influenceur?.email}</div>
                  </td>
                  <td className="px-4 py-3 text-xs text-muted">{seq.influenceur?.contact_type}</td>
                  <td className="px-4 py-3 text-xs text-muted">{seq.influenceur?.country}</td>
                  <td className="px-4 py-3">
                    <div className="flex gap-1">
                      {[1, 2, 3, 4].map(s => (
                        <span key={s} className={`w-6 h-6 rounded flex items-center justify-center text-[10px] font-bold ${
                          s <= seq.current_step ? 'bg-emerald-500/20 text-emerald-400' : 'bg-surface2 text-muted/30'
                        }`}>{s}</span>
                      ))}
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-0.5 text-[10px] rounded-full font-medium ${
                      seq.status === 'active' ? 'bg-emerald-500/20 text-emerald-400' :
                      seq.status === 'paused' ? 'bg-amber/20 text-amber' :
                      seq.status === 'completed' ? 'bg-blue-500/20 text-blue-400' :
                      'bg-red-500/20 text-red-400'
                    }`}>
                      {seq.status}{seq.stop_reason ? ` (${seq.stop_reason})` : ''}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-xs text-muted">
                    {seq.next_send_at ? new Date(seq.next_send_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-1">
                      {seq.status === 'active' && (
                        <button onClick={() => handleAction(seq.id, 'pause')} disabled={actionLoading === seq.id}
                          className="px-2 py-1 text-[10px] bg-amber/20 text-amber rounded hover:bg-amber/30">Pause</button>
                      )}
                      {seq.status === 'paused' && (
                        <button onClick={() => handleAction(seq.id, 'resume')} disabled={actionLoading === seq.id}
                          className="px-2 py-1 text-[10px] bg-emerald-500/20 text-emerald-400 rounded hover:bg-emerald-500/30">Reprendre</button>
                      )}
                      {['active', 'paused'].includes(seq.status) && (
                        <button onClick={() => handleAction(seq.id, 'stop')} disabled={actionLoading === seq.id}
                          className="px-2 py-1 text-[10px] text-red-400/60 hover:text-red-400 rounded">Stop</button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
              {sequences.length === 0 && (
                <tr><td colSpan={7} className="text-center py-12 text-muted text-sm">Aucune sequence {filter !== 'all' ? filter : ''}</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
