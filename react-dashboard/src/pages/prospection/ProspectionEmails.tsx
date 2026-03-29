import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';
import { CONTACT_TYPES } from '../../lib/constants';

interface Email {
  id: number; step: number; subject: string; body_text: string;
  from_email: string; from_name: string; status: string; ai_generated: boolean;
  sent_at: string | null; opened_at: string | null; clicked_at: string | null;
  bounced_at: string | null; bounce_reason: string | null; replied_at: string | null;
  created_at: string;
  influenceur: { id: number; name: string; email: string; contact_type: string; country: string } | null;
}

interface Sequence {
  id: number; current_step: number; status: string; stop_reason: string | null;
  next_send_at: string | null; started_at: string | null; completed_at: string | null;
  influenceur: { id: number; name: string; email: string; contact_type: string; country: string } | null;
}

type Tab = 'generate' | 'review' | 'sent';

const STEP_LABELS: Record<number, string> = {
  1: 'Premier contact',
  2: 'Relance J+3',
  3: 'Relance J+7',
  4: 'Dernier message J+14',
};

const STATUS_COLORS: Record<string, string> = {
  sent: 'bg-cyan/20 text-cyan',
  delivered: 'bg-blue-500/20 text-blue-400',
  opened: 'bg-emerald-500/20 text-emerald-400',
  clicked: 'bg-green-500/20 text-green-400',
  replied: 'bg-violet/20 text-violet-light',
  bounced: 'bg-red-500/20 text-red-400',
  failed: 'bg-red-500/20 text-red-400',
  unsubscribed: 'bg-gray-500/20 text-gray-400',
  approved: 'bg-amber/20 text-amber',
};

const SEQ_STATUS_COLORS: Record<string, string> = {
  active: 'bg-emerald-500/20 text-emerald-400',
  paused: 'bg-amber/20 text-amber',
  completed: 'bg-blue-500/20 text-blue-400',
  stopped: 'bg-red-500/20 text-red-400',
};

const STOP_ICONS: Record<string, string> = {
  replied: '💬',
  bounced: '⛔',
  hard_bounce: '⛔',
  unsubscribed: '🚫',
  manual: '✋',
};

function formatDate(d: string | null): string {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

export default function ProspectionEmails() {
  const [tab, setTab] = useState<Tab>('generate');
  const [reviewQueue, setReviewQueue] = useState<Email[]>([]);
  const [sequences, setSequences] = useState<Sequence[]>([]);
  const [loading, setLoading] = useState(true);
  const [sentLoading, setSentLoading] = useState(false);
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  // Filters
  const [filterType, setFilterType] = useState('');
  const [filterSeqStatus, setFilterSeqStatus] = useState('all');

  // Generate form
  const [genType, setGenType] = useState('');
  const [genCountry, setGenCountry] = useState('');
  const [genStep, setGenStep] = useState(1);
  const [genLimit, setGenLimit] = useState(20);
  const [generating, setGenerating] = useState(false);
  const [genResult, setGenResult] = useState<{ msg: string; ok: boolean } | null>(null);

  // Edit modal
  const [editEmail, setEditEmail] = useState<Email | null>(null);
  const [editSubject, setEditSubject] = useState('');
  const [editBody, setEditBody] = useState('');

  const fetchReview = useCallback(async () => {
    try {
      const { data } = await api.get('/outreach/review-queue');
      setReviewQueue(data.data || []);
    } catch { /* ignore */ }
  }, []);

  const fetchSent = useCallback(async () => {
    setSentLoading(true);
    try {
      const { data } = await api.get(`/outreach/sequences?status=${filterSeqStatus === 'all' ? '' : filterSeqStatus}`);
      setSequences(data.data || []);
    } catch { /* ignore */ }
    setSentLoading(false);
  }, [filterSeqStatus]);

  useEffect(() => {
    (async () => {
      await fetchReview();
      setLoading(false);
    })();
  }, [fetchReview]);

  useEffect(() => {
    if (tab === 'sent') fetchSent();
  }, [tab, fetchSent]);

  const handleGenerate = async () => {
    if (!genType) return;
    setGenerating(true);
    setGenResult(null);
    try {
      const { data } = await api.post('/outreach/generate', {
        contact_type: genType, country: genCountry || undefined, step: genStep, limit: genLimit,
      });
      setGenResult({ msg: data.message, ok: true });
      setTimeout(fetchReview, 3000);
    } catch (err: any) {
      setGenResult({ msg: err.response?.data?.message || 'Erreur de generation', ok: false });
    }
    setGenerating(false);
  };

  const handleApprove = async (id: number) => {
    setActionLoading(id);
    try {
      await api.post(`/outreach/review/${id}/approve`);
      setReviewQueue(prev => prev.filter(e => e.id !== id));
    } catch { /* ignore */ }
    setActionLoading(null);
  };

  const handleReject = async (id: number) => {
    setActionLoading(id);
    try {
      await api.post(`/outreach/review/${id}/reject`);
      setReviewQueue(prev => prev.filter(e => e.id !== id));
    } catch { /* ignore */ }
    setActionLoading(null);
  };

  const handleApproveAll = async () => {
    const ids = reviewQueue.map(e => e.id);
    if (ids.length === 0) return;
    try {
      await api.post('/outreach/review/approve-batch', { ids });
      setReviewQueue([]);
    } catch { /* ignore */ }
  };

  const openEdit = (email: Email) => {
    setEditEmail(email);
    setEditSubject(email.subject);
    setEditBody(email.body_text);
  };

  const handleSaveEdit = async () => {
    if (!editEmail) return;
    setActionLoading(editEmail.id);
    try {
      await api.post(`/outreach/review/${editEmail.id}/edit`, {
        subject: editSubject, body_text: editBody,
      });
      setReviewQueue(prev => prev.filter(e => e.id !== editEmail.id));
      setEditEmail(null);
    } catch { /* ignore */ }
    setActionLoading(null);
  };

  // Filtered sequences
  const filteredSequences = filterType
    ? sequences.filter(s => s.influenceur?.contact_type === filterType)
    : sequences;

  if (loading) return <div className="flex items-center justify-center h-64"><div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>;

  const tabClass = (t: Tab) => `px-4 py-2 text-sm font-medium rounded-lg transition-colors ${tab === t ? 'bg-violet text-white' : 'text-muted hover:text-white hover:bg-surface2'}`;

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Link to="/prospection" className="text-muted hover:text-white transition-colors text-sm">&larr; Prospection</Link>
          <h1 className="text-2xl font-title font-bold text-white">Emails</h1>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-2">
        <button className={tabClass('generate')} onClick={() => setTab('generate')}>Generer</button>
        <button className={tabClass('review')} onClick={() => setTab('review')}>
          En review {reviewQueue.length > 0 && <span className="ml-1 px-1.5 py-0.5 bg-amber/30 text-amber rounded-full text-[10px]">{reviewQueue.length}</span>}
        </button>
        <button className={tabClass('sent')} onClick={() => setTab('sent')}>Suivi envois</button>
      </div>

      {/* ═══════════════════════════════════════
          GENERATE TAB
      ═══════════════════════════════════════ */}
      {tab === 'generate' && (
        <div className="bg-surface border border-border rounded-xl p-6 space-y-5">
          <div>
            <h3 className="text-white font-title font-semibold">Generer des emails avec l'IA</h3>
            <p className="text-muted text-xs mt-1">Claude genere un email unique et personnalise pour chaque contact eligible</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label className="block text-xs text-muted mb-1.5">Type de contact *</label>
              <select value={genType} onChange={e => setGenType(e.target.value)}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2.5 text-white text-sm focus:border-violet outline-none">
                <option value="">Choisir un type...</option>
                {CONTACT_TYPES.map(t => <option key={t.value} value={t.value}>{t.icon} {t.label}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1.5">Pays (optionnel)</label>
              <input value={genCountry} onChange={e => setGenCountry(e.target.value)} placeholder="Tous les pays"
                className="w-full bg-bg border border-border rounded-lg px-3 py-2.5 text-white text-sm focus:border-violet outline-none" />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1.5">Step de la sequence</label>
              <select value={genStep} onChange={e => setGenStep(Number(e.target.value))}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2.5 text-white text-sm focus:border-violet outline-none">
                {[1, 2, 3, 4].map(s => (
                  <option key={s} value={s}>Step {s} — {STEP_LABELS[s]}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1.5">Nombre de contacts (max 50)</label>
              <input type="number" value={genLimit} onChange={e => setGenLimit(Number(e.target.value))} min={1} max={50}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2.5 text-white text-sm focus:border-violet outline-none" />
            </div>
          </div>

          {/* Step info */}
          <div className="flex items-center gap-3 p-3 bg-surface2/50 rounded-lg">
            <span className="text-cyan text-xs">i</span>
            <p className="text-xs text-muted">
              {genStep === 1 && 'Premier contact personnalise : hook + valeur + CTA. Texte brut, pas de HTML.'}
              {genStep === 2 && 'Relance J+3 : nouvel angle, stat ou temoignage. Ne repete pas le step 1.'}
              {genStep === 3 && 'Relance J+7 : ton direct, question oui/non. "Si non, pas de souci."'}
              {genStep === 4 && 'Dernier message J+14 : 2 phrases max. "Je ne vous recontacterai plus."'}
            </p>
          </div>

          <div className="flex items-center gap-4">
            <button onClick={handleGenerate} disabled={generating || !genType}
              className="px-6 py-2.5 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white text-sm rounded-lg font-medium transition-colors">
              {generating ? 'Generation en cours...' : `Generer ${genLimit} emails (Step ${genStep})`}
            </button>
            {genResult && (
              <span className={`text-sm ${genResult.ok ? 'text-emerald-400' : 'text-red-400'}`}>{genResult.msg}</span>
            )}
          </div>
        </div>
      )}

      {/* ═══════════════════════════════════════
          REVIEW TAB
      ═══════════════════════════════════════ */}
      {tab === 'review' && (
        <div className="space-y-4">
          {reviewQueue.length > 0 && (
            <div className="flex justify-between items-center">
              <p className="text-muted text-sm">{reviewQueue.length} email{reviewQueue.length > 1 ? 's' : ''} en attente de review</p>
              <button onClick={handleApproveAll}
                className="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm rounded-lg transition-colors font-medium">
                Tout approuver ({reviewQueue.length})
              </button>
            </div>
          )}

          {reviewQueue.length === 0 && (
            <div className="text-center py-16 text-muted">
              <p className="text-4xl mb-3">✓</p>
              <p className="text-white font-medium">File de review vide</p>
              <p className="text-sm mt-1">Generez des emails dans l'onglet "Generer" pour remplir la file</p>
            </div>
          )}

          {reviewQueue.map(email => (
            <div key={email.id} className="bg-surface border border-border rounded-xl overflow-hidden">
              {/* Header */}
              <div className="px-5 py-3 border-b border-border bg-surface2/30 flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-3">
                  <span className="text-xs bg-violet/20 text-violet-light px-2 py-0.5 rounded">{email.influenceur?.contact_type}</span>
                  <Link to={`/contacts/${email.influenceur?.id}`} className="text-white text-sm font-medium hover:text-violet-light transition-colors">
                    {email.influenceur?.name}
                  </Link>
                  <span className="text-muted text-xs">{email.influenceur?.country}</span>
                  <span className="text-cyan text-xs">{email.influenceur?.email}</span>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-xs bg-surface2 text-muted px-2 py-0.5 rounded">Step {email.step}</span>
                  {email.ai_generated && <span className="text-xs bg-violet/10 text-violet-light px-2 py-0.5 rounded">IA</span>}
                </div>
              </div>

              {/* Email content */}
              <div className="p-5">
                <div className="text-xs text-muted mb-2">De: {email.from_name} &lt;{email.from_email}&gt;</div>
                <div className="text-sm text-white font-medium mb-3">Objet: {email.subject}</div>
                <div className="bg-bg rounded-lg p-4 text-sm text-gray-300 whitespace-pre-wrap leading-relaxed">
                  {email.body_text}
                </div>
              </div>

              {/* Actions */}
              <div className="px-5 py-3 border-t border-border flex items-center gap-2 justify-end">
                <button onClick={() => openEdit(email)} disabled={actionLoading === email.id}
                  className="px-3 py-1.5 text-xs text-muted hover:text-white border border-border rounded-lg hover:border-violet/30 transition-colors">
                  Editer
                </button>
                <button onClick={() => handleReject(email.id)} disabled={actionLoading === email.id}
                  className="px-3 py-1.5 text-xs bg-red-500/10 text-red-400 rounded-lg hover:bg-red-500/20 transition-colors">
                  Rejeter
                </button>
                <button onClick={() => handleApprove(email.id)} disabled={actionLoading === email.id}
                  className="px-3 py-1.5 text-xs bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition-colors font-medium">
                  Approuver
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* ═══════════════════════════════════════
          SENT / TRACKING TAB
      ═══════════════════════════════════════ */}
      {tab === 'sent' && (
        <div className="space-y-4">
          {/* Filters */}
          <div className="flex flex-wrap gap-3 items-center">
            <select value={filterType} onChange={e => setFilterType(e.target.value)}
              className="bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none">
              <option value="">Tous les types</option>
              {CONTACT_TYPES.map(t => <option key={t.value} value={t.value}>{t.icon} {t.label}</option>)}
            </select>
            <div className="flex gap-1">
              {[
                { key: 'all', label: 'Tous' },
                { key: 'active', label: 'Actifs', color: 'emerald' },
                { key: 'paused', label: 'En pause', color: 'amber' },
                { key: 'completed', label: 'Termines', color: 'blue' },
                { key: 'stopped', label: 'Stoppes', color: 'red' },
              ].map(f => (
                <button key={f.key} onClick={() => setFilterSeqStatus(f.key)}
                  className={`px-3 py-1.5 text-xs rounded-lg transition-colors ${
                    filterSeqStatus === f.key ? 'bg-violet text-white' : 'text-muted hover:text-white hover:bg-surface2'
                  }`}>
                  {f.label}
                </button>
              ))}
            </div>
          </div>

          {sentLoading ? (
            <div className="flex justify-center py-12"><div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>
          ) : filteredSequences.length === 0 ? (
            <div className="text-center py-16 text-muted">
              <p className="text-4xl mb-3">📬</p>
              <p className="text-white font-medium">Aucune sequence {filterSeqStatus !== 'all' ? filterSeqStatus : ''}</p>
              <p className="text-sm mt-1">Les sequences apparaissent ici apres l'envoi du premier email</p>
            </div>
          ) : (
            <div className="bg-surface border border-border rounded-xl overflow-hidden">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border">
                    {['Contact', 'Type', 'Pays', 'Progression', 'Status', 'Prochain envoi', 'Demarre le'].map(h => (
                      <th key={h} className="text-left text-[10px] text-muted font-medium uppercase tracking-wider px-4 py-3">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {filteredSequences.map(seq => (
                    <tr key={seq.id} className="border-b border-border/50 hover:bg-surface2/50 transition-colors">
                      <td className="px-4 py-3">
                        <Link to={`/contacts/${seq.influenceur?.id}`} className="hover:text-violet-light transition-colors">
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
                            <div key={s} className="relative group">
                              <span className={`w-7 h-7 rounded flex items-center justify-center text-[10px] font-bold ${
                                s <= seq.current_step ? 'bg-emerald-500/20 text-emerald-400' :
                                s === seq.current_step + 1 && seq.status === 'active' ? 'bg-cyan/20 text-cyan animate-pulse' :
                                'bg-surface2 text-muted/30'
                              }`}>{s}</span>
                              <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block z-10">
                                <div className="bg-bg border border-border rounded px-2 py-1 text-[10px] text-muted whitespace-nowrap">
                                  {STEP_LABELS[s]}
                                </div>
                              </div>
                            </div>
                          ))}
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <span className={`px-2 py-0.5 text-[10px] rounded-full font-medium ${SEQ_STATUS_COLORS[seq.status] || 'bg-surface2 text-muted'}`}>
                          {seq.status}
                        </span>
                        {seq.stop_reason && (
                          <div className="flex items-center gap-1 mt-1">
                            <span className="text-xs">{STOP_ICONS[seq.stop_reason] || '⏹'}</span>
                            <span className="text-[10px] text-red-400/80">{seq.stop_reason}</span>
                          </div>
                        )}
                      </td>
                      <td className="px-4 py-3 text-xs text-muted">
                        {seq.next_send_at ? formatDate(seq.next_send_at) : '—'}
                      </td>
                      <td className="px-4 py-3 text-xs text-muted">
                        {formatDate(seq.started_at)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <div className="px-4 py-3 border-t border-border text-xs text-muted">
                {filteredSequences.length} sequence{filteredSequences.length > 1 ? 's' : ''}
              </div>
            </div>
          )}
        </div>
      )}

      {/* ═══════════════════════════════════════
          EDIT MODAL
      ═══════════════════════════════════════ */}
      {editEmail && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60" onClick={() => setEditEmail(null)}>
          <div className="bg-surface border border-border rounded-xl w-full max-w-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between">
              <h3 className="text-white font-title font-semibold">Editer l'email</h3>
              <div className="flex items-center gap-2 text-xs text-muted">
                <span>{editEmail.influenceur?.name}</span>
                <span className="bg-surface2 px-2 py-0.5 rounded">Step {editEmail.step}</span>
              </div>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Objet</label>
              <input value={editSubject} onChange={e => setEditSubject(e.target.value)}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none" />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Corps (texte brut — pas de HTML pour maximiser la delivrabilite)</label>
              <textarea value={editBody} onChange={e => setEditBody(e.target.value)} rows={12}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none resize-y leading-relaxed" />
            </div>
            <p className="text-[10px] text-muted">Texte brut uniquement. Le formatage HTML est volontairement desactive pour maximiser la delivrabilite.</p>
            <div className="flex justify-end gap-3 pt-2">
              <button onClick={() => setEditEmail(null)} className="px-4 py-2 text-muted hover:text-white text-sm transition-colors">Annuler</button>
              <button onClick={handleSaveEdit} disabled={actionLoading === editEmail.id}
                className="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm rounded-lg transition-colors font-medium">
                Sauvegarder et approuver
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
