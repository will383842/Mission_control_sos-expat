import React, { useEffect, useState } from 'react';
import api from '../api/client';
import type { ResearcherStat, ObjectiveWithProgress } from '../types/influenceur';

interface ObjectiveForm {
  country: string;
  language: string;
  niche: string;
  target_count: number;
  deadline: string;
}

const emptyForm: ObjectiveForm = {
  country: '',
  language: '',
  niche: '',
  target_count: 10,
  deadline: '',
};

function getProgressBarColor(obj: ObjectiveWithProgress): string {
  if (obj.days_remaining < 0) return 'bg-gray-500';
  if (obj.percentage >= 80 && obj.days_remaining > 0) return 'bg-green-500';
  if (obj.percentage >= 50 || obj.days_remaining <= 3) return 'bg-amber';
  if (obj.percentage < 50 && obj.days_remaining <= 3) return 'bg-red-500';
  return 'bg-violet';
}

function getProgressTextColor(obj: ObjectiveWithProgress): string {
  if (obj.days_remaining < 0) return 'text-gray-400';
  if (obj.percentage >= 80 && obj.days_remaining > 0) return 'text-green-400';
  if (obj.percentage >= 50 || obj.days_remaining <= 3) return 'text-amber';
  if (obj.percentage < 50 && obj.days_remaining <= 3) return 'text-red-400';
  return 'text-violet-light';
}

function formatDeadline(deadline: string): string {
  return new Date(deadline).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

function getTomorrowDate(): string {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  return d.toISOString().split('T')[0];
}

export default function AdminConsole() {
  const [researchers, setResearchers] = useState<ResearcherStat[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  // Objective form state
  const [editingUserId, setEditingUserId] = useState<number | null>(null);
  const [form, setForm] = useState<ObjectiveForm>(emptyForm);
  const [saving, setSaving] = useState(false);

  // Summary stats
  const [totalValid, setTotalValid] = useState(0);

  const fetchResearchers = async () => {
    try {
      setError('');
      const { data } = await api.get<ResearcherStat[]>('/researchers/stats');
      setResearchers(data);
      // Compute total valid across all researchers
      const total = data.reduce((sum, r) => sum + r.valid_count, 0);
      setTotalValid(total);
    } catch {
      setError('Impossible de charger les statistiques des chercheurs.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchResearchers(); }, []);

  const handleAddObjective = (researcherId: number) => {
    setEditingUserId(researcherId);
    setForm({ ...emptyForm, deadline: getTomorrowDate() });
    setSuccess('');
  };

  const handleSubmitObjective = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingUserId) return;

    // Validate deadline is in the future
    const deadlineDate = new Date(form.deadline);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (deadlineDate <= today) {
      setError('La deadline doit etre une date future.');
      return;
    }

    setSaving(true);
    setError('');
    setSuccess('');
    try {
      await api.post('/objectives', {
        user_id: editingUserId,
        target_count: form.target_count,
        deadline: form.deadline,
        country: form.country || null,
        language: form.language || null,
        niche: form.niche || null,
      });
      setSuccess('Objectif cree avec succes.');
      setEditingUserId(null);
      await fetchResearchers();
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      setError(e.response?.data?.message ?? 'Erreur lors de la sauvegarde de l\'objectif.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h2 className="font-title text-2xl font-bold text-white">Console Admin</h2>
        <p className="text-muted text-sm mt-1">Gestion des chercheurs, objectifs et progression</p>
      </div>

      {error && (
        <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg">
          {error}
        </div>
      )}

      {success && (
        <div className="bg-green-500/10 border border-green-500/30 text-green-400 text-sm px-4 py-3 rounded-lg">
          {success}
        </div>
      )}

      {/* Section 1: Chercheurs & Objectifs */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <div className="px-5 py-4 border-b border-border">
          <h3 className="font-title font-semibold text-white">Chercheurs & Objectifs</h3>
          <p className="text-xs text-muted mt-0.5">{researchers.length} chercheur{researchers.length !== 1 ? 's' : ''} actif{researchers.length !== 1 ? 's' : ''}</p>
        </div>

        {researchers.length === 0 ? (
          <div className="p-8 text-center text-muted text-sm">
            Aucun chercheur enregistre. Ajoutez un membre avec le role "Chercheur" dans la page Equipe.
          </div>
        ) : (
          <div className="divide-y divide-border">
            {researchers.map(r => (
              <div key={r.id} className="p-4 md:p-5">
                {/* Researcher header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-cyan/20 flex items-center justify-center text-cyan font-bold text-sm flex-shrink-0">
                      {r.name?.[0] ?? '?'}
                    </div>
                    <div>
                      <p className="text-white font-medium">{r.name}</p>
                      <p className="text-muted text-xs">{r.email}</p>
                      <p className="text-muted text-xs mt-0.5">
                        Derniere connexion:{' '}
                        {r.last_login_at
                          ? new Date(r.last_login_at).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + new Date(r.last_login_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
                          : 'Jamais'}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-4">
                    <div className="text-center">
                      <p className="text-xl font-bold text-white font-title">{r.total_created}</p>
                      <p className="text-[10px] text-muted uppercase tracking-wider">Total crees</p>
                    </div>
                    <div className="w-px h-8 bg-border" />
                    <div className="text-center">
                      <p className="text-xl font-bold text-green-400 font-title">{r.valid_count}</p>
                      <p className="text-[10px] text-muted uppercase tracking-wider">Valides</p>
                    </div>
                    <div className="w-px h-8 bg-border" />
                    <div className="text-center">
                      <p className="text-xl font-bold text-muted font-title">
                        {r.total_created > 0 ? Math.round((r.valid_count / r.total_created) * 100) : 0}%
                      </p>
                      <p className="text-[10px] text-muted uppercase tracking-wider">Ratio</p>
                    </div>
                  </div>
                </div>

                {/* Active objectives */}
                {r.objectives.length > 0 ? (
                  <div className="overflow-x-auto mb-3">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b border-border/50">
                          {['Pays', 'Langue', 'Niche', 'Cible', 'Progression', 'Deadline', 'Jours restants'].map(h => (
                            <th key={h} className="text-left text-[10px] text-muted font-medium uppercase tracking-wider px-3 py-2 whitespace-nowrap">{h}</th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {r.objectives.map(obj => (
                          <tr key={obj.id} className="border-b border-border/30 last:border-0">
                            <td className="px-3 py-2 text-gray-300 whitespace-nowrap">{obj.country ?? 'Tous pays'}</td>
                            <td className="px-3 py-2 text-gray-300 whitespace-nowrap">{obj.language ?? 'Toutes'}</td>
                            <td className="px-3 py-2 text-gray-300 whitespace-nowrap">{obj.niche ?? 'Tous types'}</td>
                            <td className="px-3 py-2 text-white font-mono font-bold">{obj.target_count}</td>
                            <td className="px-3 py-2 min-w-[160px]">
                              <div className="flex items-center gap-2">
                                <div className="flex-1 bg-surface2 rounded-full h-2">
                                  <div
                                    className={`h-2 rounded-full transition-all ${getProgressBarColor(obj)}`}
                                    style={{ width: `${Math.min(obj.percentage, 100)}%` }}
                                  />
                                </div>
                                <span className={`text-xs font-mono font-bold whitespace-nowrap ${getProgressTextColor(obj)}`}>
                                  {obj.current_count}/{obj.target_count} ({Math.round(obj.percentage)}%)
                                </span>
                              </div>
                            </td>
                            <td className="px-3 py-2 text-gray-300 whitespace-nowrap">{formatDeadline(obj.deadline)}</td>
                            <td className="px-3 py-2 whitespace-nowrap">
                              {obj.days_remaining < 0 ? (
                                <span className="text-gray-500 text-xs font-medium">Expire</span>
                              ) : obj.days_remaining === 0 ? (
                                <span className="text-red-400 text-xs font-bold">Aujourd'hui</span>
                              ) : (
                                <span className={`text-xs font-bold ${obj.days_remaining <= 3 ? 'text-amber' : 'text-gray-300'}`}>
                                  {obj.days_remaining}j
                                </span>
                              )}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <p className="text-xs text-muted mb-3 pl-1">Aucun objectif actif</p>
                )}

                {/* Add objective button */}
                <button
                  onClick={() => handleAddObjective(r.id)}
                  className="text-xs text-violet hover:text-violet-light transition-colors font-medium"
                >
                  + Ajouter objectif
                </button>

                {/* Inline objective form */}
                {editingUserId === r.id && (
                  <div className="mt-4 bg-surface2/50 rounded-lg p-4 border border-border/50">
                    <form onSubmit={handleSubmitObjective}>
                      <p className="text-xs text-gray-400 mb-3">
                        Nouvel objectif pour <span className="text-white font-medium">{r.name}</span>
                      </p>
                      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
                        <div>
                          <label className="block text-[10px] text-gray-400 uppercase tracking-wider mb-1">Pays</label>
                          <input
                            type="text"
                            placeholder="ex: France"
                            value={form.country}
                            onChange={e => setForm(p => ({ ...p, country: e.target.value }))}
                            className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet placeholder:text-gray-600"
                          />
                        </div>
                        <div>
                          <label className="block text-[10px] text-gray-400 uppercase tracking-wider mb-1">Langue</label>
                          <input
                            type="text"
                            placeholder="ex: fr"
                            value={form.language}
                            onChange={e => setForm(p => ({ ...p, language: e.target.value }))}
                            className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet placeholder:text-gray-600"
                          />
                        </div>
                        <div>
                          <label className="block text-[10px] text-gray-400 uppercase tracking-wider mb-1">Niche / Type</label>
                          <input
                            type="text"
                            placeholder="ex: Voyage"
                            value={form.niche}
                            onChange={e => setForm(p => ({ ...p, niche: e.target.value }))}
                            className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet placeholder:text-gray-600"
                          />
                        </div>
                        <div>
                          <label className="block text-[10px] text-gray-400 uppercase tracking-wider mb-1">Quantite *</label>
                          <input
                            type="number"
                            min={1}
                            required
                            value={form.target_count}
                            onChange={e => setForm(p => ({ ...p, target_count: parseInt(e.target.value) || 1 }))}
                            className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
                          />
                        </div>
                        <div>
                          <label className="block text-[10px] text-gray-400 uppercase tracking-wider mb-1">Deadline *</label>
                          <input
                            type="date"
                            required
                            min={getTomorrowDate()}
                            value={form.deadline}
                            onChange={e => setForm(p => ({ ...p, deadline: e.target.value }))}
                            className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
                          />
                        </div>
                      </div>
                      <div className="flex items-center gap-3">
                        <button
                          type="submit"
                          disabled={saving}
                          className="px-5 py-2 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white text-sm rounded-lg transition-colors font-medium"
                        >
                          {saving ? 'Enregistrement...' : 'Creer l\'objectif'}
                        </button>
                        <button
                          type="button"
                          onClick={() => setEditingUserId(null)}
                          className="px-4 py-2 text-muted hover:text-white text-sm transition-colors"
                        >
                          Annuler
                        </button>
                      </div>
                    </form>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Section 3: Summary */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-3">Resume global</h3>
          <div className="flex items-center gap-4">
            <div className="w-14 h-14 rounded-xl bg-green-500/10 flex items-center justify-center">
              <span className="text-2xl">✓</span>
            </div>
            <div>
              <p className="text-3xl font-bold text-green-400 font-title">{totalValid}</p>
              <p className="text-xs text-muted">Influenceurs valides dans le systeme</p>
            </div>
          </div>
        </div>

        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-3">Doublons</h3>
          <div className="flex items-start gap-3 bg-surface2 rounded-lg p-3">
            <div className="w-8 h-8 rounded-lg bg-cyan/10 flex items-center justify-center flex-shrink-0">
              <span className="text-cyan text-sm">i</span>
            </div>
            <div>
              <p className="text-sm text-gray-300">
                Les doublons sont automatiquement bloques a la creation.
              </p>
              <p className="text-xs text-muted mt-1">
                Le systeme verifie l'URL du profil avant d'enregistrer. Si un doublon est detecte, la creation est refusee.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
