import React, { useEffect, useState, useContext } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../api/client';
import { AuthContext } from '../hooks/useAuth';
import type { Contact, Influenceur, TeamMember } from '../types/influenceur';
import ContactTimeline from '../components/ContactTimeline';
import ContactForm from '../components/ContactForm';
import StatusBadge from '../components/StatusBadge';
import PlatformBadge from '../components/PlatformBadge';

const STATUS_OPTIONS = [
  { value: 'prospect', label: 'Prospect' },
  { value: 'contacted', label: 'Contacté' },
  { value: 'negotiating', label: 'En négociation' },
  { value: 'active', label: 'Actif' },
  { value: 'refused', label: 'Refusé' },
  { value: 'inactive', label: 'Inactif' },
];

export default function InfluenceurDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useContext(AuthContext);
  const [influenceur, setInfluenceur] = useState<Influenceur | null>(null);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [loading, setLoading] = useState(true);
  const [team, setTeam] = useState<TeamMember[]>([]);
  const [editing, setEditing] = useState(false);
  const [formData, setFormData] = useState<Partial<Influenceur>>({});
  const [showContactForm, setShowContactForm] = useState(false);
  const [saveError, setSaveError] = useState('');

  useEffect(() => {
    if (!id) return;
    Promise.all([
      api.get<Influenceur>(`/influenceurs/${id}`),
      api.get<Contact[]>(`/influenceurs/${id}/contacts`),
      api.get<TeamMember[]>('/team').catch(() => ({ data: [] as TeamMember[] })),
    ]).then(([infRes, contactsRes, teamRes]) => {
      setInfluenceur(infRes.data);
      setFormData(infRes.data);
      setContacts(contactsRes.data);
      setTeam(teamRes.data);
    }).finally(() => setLoading(false));
  }, [id]);

  const handleSave = async () => {
    if (!id || !influenceur) return;
    setSaveError('');
    try {
      const { data } = await api.put<Influenceur>(`/influenceurs/${id}`, formData);
      setInfluenceur(data);
      setEditing(false);
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      setSaveError(e.response?.data?.message ?? 'Erreur lors de la sauvegarde.');
    }
  };

  const handleDelete = async () => {
    if (!id || !confirm('Supprimer cet influenceur ? Cette action est irréversible.')) return;
    try {
      await api.delete(`/influenceurs/${id}`);
      navigate('/influenceurs');
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      setSaveError(e.response?.data?.message ?? 'Erreur lors de la suppression.');
    }
  };

  const refreshContacts = async () => {
    if (!id) return;
    const { data } = await api.get<Contact[]>(`/influenceurs/${id}/contacts`);
    setContacts(data);
    setShowContactForm(false);
  };

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  if (!influenceur) return (
    <div className="p-4 md:p-6 text-center text-muted">Influenceur introuvable.</div>
  );

  return (
    <div className="p-4 md:p-6 max-w-4xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <button onClick={() => navigate(-1)} className="text-muted hover:text-white text-sm transition-colors">
          ← Retour
        </button>
        <div className="flex flex-wrap gap-2">
          {editing ? (
            <>
              <button onClick={handleSave} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
                Sauvegarder
              </button>
              <button onClick={() => { setEditing(false); setFormData(influenceur); setSaveError(''); }} className="px-4 py-2 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors">
                Annuler
              </button>
            </>
          ) : (
            <>
              <button onClick={() => setEditing(true)} className="px-4 py-2 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors">
                Modifier
              </button>
              {(user?.role === 'admin' || user?.role === 'researcher') && (
                <button onClick={handleDelete} className="px-4 py-2 bg-red-500/10 text-red-400 hover:bg-red-500/20 text-sm rounded-lg border border-red-500/30 transition-colors">
                  Supprimer
                </button>
              )}
            </>
          )}
        </div>
      </div>

      {/* Error */}
      {saveError && (
        <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg">{saveError}</div>
      )}

      {/* Fiche principale */}
      <div className="bg-surface border border-border rounded-2xl p-4 md:p-6">
        <div className="flex items-start gap-4">
          {influenceur.avatar_url ? (
            <img src={influenceur.avatar_url} alt={influenceur.name} className="w-16 h-16 rounded-full object-cover flex-shrink-0" />
          ) : (
            <div className="w-16 h-16 rounded-full bg-violet/20 flex items-center justify-center text-2xl font-bold text-violet-light flex-shrink-0">
              {influenceur.name[0]}
            </div>
          )}
          <div className="flex-1 min-w-0">
            {editing ? (
              <input
                value={formData.name ?? ''}
                onChange={e => setFormData(p => ({ ...p, name: e.target.value }))}
                className="text-2xl font-title font-bold bg-surface2 border border-border rounded-lg px-3 py-1 text-white w-full focus:outline-none focus:border-violet"
              />
            ) : (
              <h1 className="text-2xl font-title font-bold text-white">{influenceur.name}</h1>
            )}
            <div className="flex items-center gap-2 mt-2 flex-wrap">
              {influenceur.handle && <span className="font-mono text-sm text-cyan">@{influenceur.handle}</span>}
              <PlatformBadge platform={influenceur.primary_platform} />
              {editing ? (
                <select
                  value={formData.status ?? influenceur.status}
                  onChange={e => setFormData(p => ({ ...p, status: e.target.value as Influenceur['status'] }))}
                  className="bg-surface2 border border-border rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-violet"
                >
                  {STATUS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
              ) : (
                <StatusBadge status={influenceur.status} />
              )}
              {influenceur.pending_reminder && (
                <span className="px-2 py-0.5 bg-amber/20 text-amber text-xs rounded-full font-mono">RELANCER</span>
              )}
            </div>
            {influenceur.followers && (
              <p className="text-muted text-sm mt-1">{influenceur.followers.toLocaleString('fr-FR')} followers</p>
            )}
          </div>
        </div>

        {/* Infos détaillées */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6 pt-6 border-t border-border text-sm">
          {[
            { label: 'Email', value: influenceur.email, field: 'email' },
            { label: 'Téléphone', value: influenceur.phone, field: 'phone' },
            { label: 'Pays', value: influenceur.country, field: 'country' },
            { label: 'Langue', value: influenceur.language, field: 'language' },
            { label: 'Niche', value: influenceur.niche, field: 'niche' },
          ].map(({ label, value, field }) => (
            <div key={field}>
              <p className="text-muted text-xs mb-1">{label}</p>
              {editing ? (
                <input
                  value={(formData as Record<string, unknown>)[field] as string ?? ''}
                  onChange={e => setFormData(p => ({ ...p, [field]: e.target.value }))}
                  className="bg-surface2 border border-border rounded px-2 py-1 text-white text-sm w-full focus:outline-none focus:border-violet"
                />
              ) : (
                <p className="text-white">{value ?? '—'}</p>
              )}
            </div>
          ))}

          {/* Assignation */}
          <div>
            <p className="text-muted text-xs mb-1">Assigné à</p>
            {editing ? (
              <select
                value={formData.assigned_to ?? ''}
                onChange={e => setFormData(p => ({ ...p, assigned_to: e.target.value ? Number(e.target.value) : null }))}
                className="bg-surface2 border border-border rounded px-2 py-1 text-sm text-white w-full focus:outline-none focus:border-violet"
              >
                <option value="">Non assigné</option>
                {team.map(m => <option key={m.id} value={m.id}>{m.name}</option>)}
              </select>
            ) : (
              <p className="text-white">{influenceur.assigned_to_user?.name ?? '—'}</p>
            )}
          </div>
        </div>

        {/* Rappels */}
        <div className="mt-4 pt-4 border-t border-border">
          <p className="text-muted text-xs mb-2">Rappel automatique</p>
          <div className="flex items-center gap-4 flex-wrap">
            <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
              <input
                type="checkbox"
                checked={editing ? (formData.reminder_active ?? influenceur.reminder_active) : influenceur.reminder_active}
                onChange={e => editing && setFormData(p => ({ ...p, reminder_active: e.target.checked }))}
                disabled={!editing}
                className="accent-violet"
              />
              Actif
            </label>
            <span className="text-sm text-muted">après</span>
            {editing ? (
              <input
                type="number"
                min={1} max={365}
                value={formData.reminder_days ?? influenceur.reminder_days}
                onChange={e => setFormData(p => ({ ...p, reminder_days: Number(e.target.value) }))}
                className="w-16 bg-surface2 border border-border rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-violet"
              />
            ) : (
              <span className="text-white font-mono text-sm">{influenceur.reminder_days}</span>
            )}
            <span className="text-sm text-muted">jours</span>
          </div>
        </div>

        {/* Notes */}
        <div className="mt-4 pt-4 border-t border-border">
          <p className="text-muted text-xs mb-2">Notes</p>
          {editing ? (
            <textarea
              value={formData.notes ?? ''}
              onChange={e => setFormData(p => ({ ...p, notes: e.target.value }))}
              rows={3}
              className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet resize-none"
            />
          ) : (
            <p className="text-white text-sm whitespace-pre-wrap">{influenceur.notes ?? '—'}</p>
          )}
        </div>
      </div>

      {/* Timeline contacts */}
      <div className="bg-surface border border-border rounded-2xl p-4 md:p-6">
        <div className="flex items-center justify-between mb-4 flex-wrap gap-2">
          <h3 className="font-title font-semibold text-white">Timeline des contacts</h3>
          <button
            onClick={() => setShowContactForm(!showContactForm)}
            className="px-3 py-1.5 bg-violet/20 hover:bg-violet/30 text-violet-light text-sm rounded-lg transition-colors"
          >
            + Ajouter un contact
          </button>
        </div>

        {showContactForm && (
          <div className="mb-6 pb-6 border-b border-border">
            <ContactForm influenceurId={influenceur.id} onSaved={refreshContacts} onCancel={() => setShowContactForm(false)} />
          </div>
        )}

        <ContactTimeline contacts={contacts} />
      </div>
    </div>
  );
}
