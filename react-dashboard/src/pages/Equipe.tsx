import React, { useEffect, useState } from 'react';
import api from '../api/client';
import type { TeamMember } from '../types/influenceur';

type MemberForm = {
  name: string;
  email: string;
  password: string;
  role: 'admin' | 'member';
};

export default function Equipe() {
  const [members, setMembers] = useState<TeamMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<MemberForm>({ name: '', email: '', password: '', role: 'member' });
  const [error, setError] = useState('');

  const fetchMembers = async () => {
    const { data } = await api.get<TeamMember[]>('/team');
    setMembers(data);
    setLoading(false);
  };

  useEffect(() => { fetchMembers(); }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    try {
      if (editingId) {
        const payload: Partial<MemberForm> = { name: form.name, email: form.email, role: form.role };
        if (form.password) payload.password = form.password;
        await api.put(`/team/${editingId}`, payload);
      } else {
        await api.post('/team', form);
      }
      await fetchMembers();
      setShowForm(false);
      setEditingId(null);
      setForm({ name: '', email: '', password: '', role: 'member' });
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      setError(e.response?.data?.message ?? 'Erreur lors de la sauvegarde.');
    }
  };

  const handleEdit = (member: TeamMember) => {
    setEditingId(member.id);
    setForm({ name: member.name, email: member.email, password: '', role: member.role });
    setShowForm(true);
  };

  const handleDeactivate = async (id: number) => {
    if (!confirm('Désactiver ce membre ?')) return;
    await api.delete(`/team/${id}`);
    await fetchMembers();
  };

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  return (
    <div className="p-6">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">Équipe</h2>
          <p className="text-muted text-sm mt-1">{members.length} membre{members.length !== 1 ? 's' : ''}</p>
        </div>
        <button
          onClick={() => { setShowForm(!showForm); setEditingId(null); setForm({ name: '', email: '', password: '', role: 'member' }); }}
          className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Ajouter un membre
        </button>
      </div>

      {/* Formulaire */}
      {showForm && (
        <form onSubmit={handleSubmit} className="bg-surface border border-border rounded-xl p-5 mb-6 space-y-4">
          <h3 className="font-title font-semibold text-white">{editingId ? 'Modifier le membre' : 'Nouveau membre'}</h3>

          {error && (
            <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg">{error}</div>
          )}

          <div className="grid grid-cols-2 gap-4">
            {[
              { label: 'Nom', field: 'name', type: 'text', placeholder: 'Prénom Nom' },
              { label: 'Email', field: 'email', type: 'email', placeholder: 'email@sos-expat.com' },
            ].map(({ label, field, type, placeholder }) => (
              <div key={field}>
                <label className="block text-sm text-gray-400 mb-1.5">{label}</label>
                <input
                  type={type}
                  value={(form as Record<string, string>)[field]}
                  onChange={e => setForm(p => ({ ...p, [field]: e.target.value }))}
                  required
                  placeholder={placeholder}
                  className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
                />
              </div>
            ))}
            <div>
              <label className="block text-sm text-gray-400 mb-1.5">
                Mot de passe {editingId && <span className="text-muted">(laisser vide = inchangé)</span>}
              </label>
              <input
                type="password"
                value={form.password}
                onChange={e => setForm(p => ({ ...p, password: e.target.value }))}
                required={!editingId}
                placeholder="••••••••"
                className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
              />
            </div>
            <div>
              <label className="block text-sm text-gray-400 mb-1.5">Rôle</label>
              <select
                value={form.role}
                onChange={e => setForm(p => ({ ...p, role: e.target.value as 'admin' | 'member' }))}
                className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
              >
                <option value="member">Membre</option>
                <option value="admin">Admin</option>
              </select>
            </div>
          </div>

          <div className="flex gap-3">
            <button type="submit" className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
              {editingId ? 'Sauvegarder' : 'Créer'}
            </button>
            <button type="button" onClick={() => setShowForm(false)} className="px-4 py-2 text-muted hover:text-white text-sm transition-colors">
              Annuler
            </button>
          </div>
        </form>
      )}

      {/* Liste membres */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <table className="w-full">
          <thead>
            <tr className="border-b border-border">
              {['Membre', 'Email', 'Rôle', 'Statut', 'Dernière connexion', 'Actions'].map(h => (
                <th key={h} className="text-left text-xs text-muted font-medium px-4 py-3">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {members.map(member => (
              <tr key={member.id} className="border-b border-border last:border-0 hover:bg-surface2 transition-colors">
                <td className="px-4 py-3">
                  <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-full bg-violet/20 flex items-center justify-center text-violet-light font-bold text-sm">
                      {member.name[0]}
                    </div>
                    <span className="text-white text-sm font-medium">{member.name}</span>
                  </div>
                </td>
                <td className="px-4 py-3 text-muted text-sm">{member.email}</td>
                <td className="px-4 py-3">
                  <span className={`px-2 py-0.5 rounded-full text-xs font-mono ${member.role === 'admin' ? 'bg-violet/20 text-violet-light' : 'bg-surface2 text-muted'}`}>
                    {member.role}
                  </span>
                </td>
                <td className="px-4 py-3">
                  <span className={`px-2 py-0.5 rounded-full text-xs ${member.is_active ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400'}`}>
                    {member.is_active ? 'Actif' : 'Désactivé'}
                  </span>
                </td>
                <td className="px-4 py-3 text-muted text-sm">
                  {member.last_login_at
                    ? new Date(member.last_login_at).toLocaleDateString('fr-FR')
                    : 'Jamais'}
                </td>
                <td className="px-4 py-3">
                  <div className="flex gap-2">
                    <button onClick={() => handleEdit(member)} className="text-xs text-muted hover:text-white transition-colors">
                      Modifier
                    </button>
                    {member.is_active && (
                      <button onClick={() => handleDeactivate(member.id)} className="text-xs text-red-400 hover:text-red-300 transition-colors">
                        Désactiver
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
