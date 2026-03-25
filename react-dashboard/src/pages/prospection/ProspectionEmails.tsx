import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';
import { CONTACT_TYPES } from '../../lib/constants';

interface Email {
  id: number; step: number; subject: string; body_text: string;
  from_email: string; from_name: string; status: string; ai_generated: boolean;
  sent_at: string | null; opened_at: string | null; clicked_at: string | null;
  bounced_at: string | null; bounce_reason: string | null;
  created_at: string;
  influenceur: { id: number; name: string; email: string; contact_type: string; country: string } | null;
}

type Tab = 'generate' | 'review' | 'sent' | 'all';

export default function ProspectionEmails() {
  const [tab, setTab] = useState<Tab>('generate');
  const [reviewQueue, setReviewQueue] = useState<Email[]>([]);
  const [sentEmails, setSentEmails] = useState<Email[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  // Generate form
  const [genType, setGenType] = useState('');
  const [genCountry, setGenCountry] = useState('');
  const [genStep, setGenStep] = useState(1);
  const [genLimit, setGenLimit] = useState(20);
  const [generating, setGenerating] = useState(false);
  const [genResult, setGenResult] = useState<string | null>(null);

  // Edit modal
  const [editEmail, setEditEmail] = useState<Email | null>(null);
  const [editSubject, setEditSubject] = useState('');
  const [editBody, setEditBody] = useState('');

  const fetchData = async () => {
    setLoading(true);
    try {
      const [reviewRes, sentRes] = await Promise.all([
        api.get('/outreach/review-queue'),
        api.get('/outreach/stats'), // Sent emails come from stats for now
      ]);
      setReviewQueue(reviewRes.data.data || []);
    } catch { /* ignore */ }
    setLoading(false);
  };

  useEffect(() => { fetchData(); }, []);

  const handleGenerate = async () => {
    if (!genType) return;
    setGenerating(true);
    setGenResult(null);
    try {
      const { data } = await api.post('/outreach/generate', {
        contact_type: genType, country: genCountry || undefined, step: genStep, limit: genLimit,
      });
      setGenResult(data.message);
      setTimeout(fetchData, 3000);
    } catch (err: any) {
      setGenResult(err.response?.data?.message || 'Erreur de generation');
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

  if (loading) return <div className="flex items-center justify-center h-64"><div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>;

  const tabClass = (t: Tab) => `px-4 py-2 text-sm font-medium rounded-lg transition-colors ${tab === t ? 'bg-violet text-white' : 'text-muted hover:text-white hover:bg-surface2'}`;

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Link to="/prospection" className="text-muted hover:text-white transition-colors text-sm">← Prospection</Link>
          <h1 className="text-2xl font-title font-bold text-white">Emails</h1>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-2">
        <button className={tabClass('generate')} onClick={() => setTab('generate')}>Generer</button>
        <button className={tabClass('review')} onClick={() => setTab('review')}>
          En review {reviewQueue.length > 0 && <span className="ml-1 px-1.5 py-0.5 bg-amber/30 text-amber rounded-full text-[10px]">{reviewQueue.length}</span>}
        </button>
        <button className={tabClass('sent')} onClick={() => setTab('sent')}>Envoyes</button>
      </div>

      {/* Generate tab */}
      {tab === 'generate' && (
        <div className="bg-surface border border-border rounded-xl p-6 space-y-4">
          <div>
            <h3 className="text-white font-title font-semibold">Generer des emails avec l'IA</h3>
            <p className="text-muted text-xs mt-1">Claude Haiku genere un email unique et personnalise pour chaque contact</p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
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
              <label className="block text-xs text-muted mb-1.5">Step</label>
              <select value={genStep} onChange={e => setGenStep(Number(e.target.value))}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2.5 text-white text-sm focus:border-violet outline-none">
                <option value={1}>Step 1 — Premier contact</option>
                <option value={2}>Step 2 — Relance J+3</option>
                <option value={3}>Step 3 — Relance J+7</option>
                <option value={4}>Step 4 — Dernier message J+14</option>
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1.5">Nombre de contacts</label>
              <input type="number" value={genLimit} onChange={e => setGenLimit(Number(e.target.value))} min={1} max={50}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2.5 text-white text-sm focus:border-violet outline-none" />
            </div>
          </div>
          <div className="flex items-center gap-4">
            <button onClick={handleGenerate} disabled={generating || !genType}
              className="px-6 py-2.5 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white text-sm rounded-lg font-medium transition-colors">
              {generating ? 'Generation en cours...' : 'Generer les emails'}
            </button>
            {genResult && <span className="text-sm text-emerald-400">{genResult}</span>}
          </div>
        </div>
      )}

      {/* Review tab */}
      {tab === 'review' && (
        <div className="space-y-4">
          {reviewQueue.length > 0 && (
            <div className="flex justify-between items-center">
              <p className="text-muted text-sm">{reviewQueue.length} email{reviewQueue.length > 1 ? 's' : ''} en attente</p>
              <button onClick={handleApproveAll}
                className="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm rounded-lg transition-colors font-medium">
                Tout approuver
              </button>
            </div>
          )}

          {reviewQueue.length === 0 && (
            <div className="text-center py-16 text-muted">
              <p className="text-4xl mb-3">✓</p>
              <p className="text-white font-medium">File de review vide</p>
              <p className="text-sm mt-1">Generez des emails pour remplir la file</p>
            </div>
          )}

          {reviewQueue.map(email => (
            <div key={email.id} className="bg-surface border border-border rounded-xl overflow-hidden">
              {/* Header */}
              <div className="px-5 py-3 border-b border-border bg-surface2/30 flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <span className="text-xs bg-violet/20 text-violet-light px-2 py-0.5 rounded">{email.influenceur?.contact_type}</span>
                  <span className="text-white text-sm font-medium">{email.influenceur?.name}</span>
                  <span className="text-muted text-xs">{email.influenceur?.country}</span>
                  <span className="text-cyan text-xs">{email.influenceur?.email}</span>
                </div>
                <span className="text-muted text-xs">Step {email.step}</span>
              </div>

              {/* Email preview */}
              <div className="p-5">
                <div className="flex items-center gap-2 text-xs text-muted mb-3">
                  <span>De: {email.from_name} &lt;{email.from_email}&gt;</span>
                </div>
                <div className="text-sm text-white font-medium mb-3">Objet: {email.subject}</div>
                <div className="bg-bg rounded-lg p-4 text-sm text-gray-300 whitespace-pre-wrap leading-relaxed font-mono">
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

      {/* Sent tab */}
      {tab === 'sent' && (
        <div className="text-center py-16 text-muted">
          <p className="text-4xl mb-3">📬</p>
          <p className="text-white font-medium">Historique des emails envoyes</p>
          <p className="text-sm mt-1">Les emails envoyes apparaitront ici une fois le systeme actif</p>
        </div>
      )}

      {/* Edit modal */}
      {editEmail && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60" onClick={() => setEditEmail(null)}>
          <div className="bg-surface border border-border rounded-xl w-full max-w-2xl p-6 space-y-4" onClick={e => e.stopPropagation()}>
            <h3 className="text-white font-title font-semibold">Editer l'email</h3>
            <div>
              <label className="block text-xs text-muted mb-1">Objet</label>
              <input value={editSubject} onChange={e => setEditSubject(e.target.value)}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none" />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Corps (texte brut)</label>
              <textarea value={editBody} onChange={e => setEditBody(e.target.value)} rows={10}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none resize-y font-mono" />
            </div>
            <div className="flex justify-end gap-3">
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
