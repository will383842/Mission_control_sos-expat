import React, { useState } from 'react';
import api from '../api/client';

interface Props {
  influenceurId: number;
  onSaved: () => void;
  onCancel: () => void;
}

export default function ContactForm({ influenceurId, onSaved, onCancel }: Props) {
  const [form, setForm] = useState({
    date: new Date().toISOString().split('T')[0],
    channel: 'email',
    result: 'sent',
    sender: '',
    message: '',
    reply: '',
    notes: '',
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await api.post(`/contacts/${influenceurId}/timeline`, form);
      onSaved();
    } catch {
      setError('Erreur lors de l\'enregistrement.');
    } finally {
      setLoading(false);
    }
  };

  const inputClass = 'w-full bg-surface border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && (
        <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg">{error}</div>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label className="block text-xs text-muted mb-1.5">Date *</label>
          <input type="date" value={form.date} onChange={e => setForm(p => ({ ...p, date: e.target.value }))} required className={inputClass} />
        </div>
        <div>
          <label className="block text-xs text-muted mb-1.5">Canal *</label>
          <select value={form.channel} onChange={e => setForm(p => ({ ...p, channel: e.target.value }))} className={inputClass}>
            {[['email','Email'],['instagram','Instagram'],['linkedin','LinkedIn'],['whatsapp','WhatsApp'],['phone','Téléphone'],['other','Autre']].map(([v,l]) => (
              <option key={v} value={v}>{l}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs text-muted mb-1.5">Résultat *</label>
          <select value={form.result} onChange={e => setForm(p => ({ ...p, result: e.target.value }))} className={inputClass}>
            {[['sent','Envoyé'],['replied','Répondu'],['refused','Refusé'],['registered','Signé'],['no_answer','Sans réponse']].map(([v,l]) => (
              <option key={v} value={v}>{l}</option>
            ))}
          </select>
        </div>
      </div>

      <div>
        <label className="block text-xs text-muted mb-1.5">Expéditeur</label>
        <input type="text" value={form.sender} onChange={e => setForm(p => ({ ...p, sender: e.target.value }))} placeholder="Votre nom" className={inputClass} />
      </div>

      <div>
        <label className="block text-xs text-muted mb-1.5">Message envoyé</label>
        <textarea value={form.message} onChange={e => setForm(p => ({ ...p, message: e.target.value }))} rows={3} className={`${inputClass} resize-none`} placeholder="Contenu du message..." />
      </div>

      <div>
        <label className="block text-xs text-muted mb-1.5">Réponse reçue</label>
        <textarea value={form.reply} onChange={e => setForm(p => ({ ...p, reply: e.target.value }))} rows={2} className={`${inputClass} resize-none`} placeholder="Réponse du contact..." />
      </div>

      <div>
        <label className="block text-xs text-muted mb-1.5">Notes internes</label>
        <input type="text" value={form.notes} onChange={e => setForm(p => ({ ...p, notes: e.target.value }))} className={inputClass} placeholder="Notes..." />
      </div>

      <div className="flex gap-3">
        <button type="submit" disabled={loading} className="px-4 py-2 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white text-sm rounded-lg transition-colors">
          {loading ? 'Enregistrement...' : 'Enregistrer le contact'}
        </button>
        <button type="button" onClick={onCancel} className="px-4 py-2 text-muted hover:text-white text-sm transition-colors">
          Annuler
        </button>
      </div>
    </form>
  );
}
