import React, { useEffect, useState } from 'react';
import api from '../api/client';
import type { ResearcherStat } from '../types/influenceur';

type ObjectiveForm = {
  target_count: number;
  period: 'daily' | 'weekly' | 'monthly';
};

const PERIOD_LABELS: Record<string, string> = {
  daily: 'Quotidien',
  weekly: 'Hebdomadaire',
  monthly: 'Mensuel',
};

export default function AdminConsole() {
  const [researchers, setResearchers] = useState<ResearcherStat[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  // Objective form state
  const [editingUserId, setEditingUserId] = useState<number | null>(null);
  const [form, setForm] = useState<ObjectiveForm>({ target_count: 10, period: 'daily' });
  const [saving, setSaving] = useState(false);

  const fetchResearchers = async () => {
    try {
      setError('');
      const { data } = await api.get<ResearcherStat[]>('/researchers/stats');
      setResearchers(data);
    } catch {
      setError('Impossible de charger les statistiques des chercheurs.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchResearchers(); }, []);

  const handleSetObjective = (researcher: ResearcherStat) => {
    setEditingUserId(researcher.id);
    setSuccess('');
    if (researcher.objective) {
      setForm({
        target_count: researcher.objective.target_count,
        period: researcher.objective.period as ObjectiveForm['period'],
      });
    } else {
      setForm({ target_count: 10, period: 'daily' });
    }
  };

  const handleSubmitObjective = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingUserId) return;
    setSaving(true);
    setError('');
    setSuccess('');
    try {
      await api.post('/objectives', {
        user_id: editingUserId,
        target_count: form.target_count,
        period: form.period,
        start_date: new Date().toISOString().split('T')[0],
      });
      setSuccess('Objectif mis a jour avec succes.');
      setEditingUserId(null);
      await fetchResearchers();
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      setError(e.response?.data?.message ?? 'Erreur lors de la sauvegarde de l\'objectif.');
    } finally {
      setSaving(false);
    }
  };

  const getProgressColor = (percentage: number) => {
    if (percentage >= 80) return 'bg-green-500';
    if (percentage >= 50) return 'bg-amber';
    return 'bg-red-500';
  };

  const getProgressTextColor = (percentage: number) => {
    if (percentage >= 80) return 'text-green-400';
    if (percentage >= 50) return 'text-amber';
    return 'text-red-400';
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
        <p className="text-muted text-sm mt-1">Gestion des chercheurs et objectifs</p>
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

      {/* Section: Chercheurs */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <div className="px-5 py-4 border-b border-border">
          <h3 className="font-title font-semibold text-white">Chercheurs</h3>
          <p className="text-xs text-muted mt-0.5">{researchers.length} chercheur{researchers.length !== 1 ? 's' : ''}</p>
        </div>

        {researchers.length === 0 ? (
          <div className="p-8 text-center text-muted text-sm">
            Aucun chercheur enregistre. Ajoutez un membre avec le role "Chercheur" dans la page Equipe.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-border">
                  {['Nom', 'Email', "Aujourd'hui", 'Semaine', 'Mois', 'Total', 'Objectif', 'Actions'].map(h => (
                    <th key={h} className="text-left text-xs text-muted font-medium px-4 py-3 whitespace-nowrap">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {researchers.map(r => (
                  <React.Fragment key={r.id}>
                    <tr className="border-b border-border last:border-0 hover:bg-surface2 transition-colors">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-3">
                          <div className="w-8 h-8 rounded-full bg-cyan/20 flex items-center justify-center text-cyan font-bold text-sm flex-shrink-0">
                            {r.name[0]}
                          </div>
                          <span className="text-white text-sm font-medium whitespace-nowrap">{r.name}</span>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-muted text-sm whitespace-nowrap">{r.email}</td>
                      <td className="px-4 py-3 text-white text-sm font-mono">{r.created_today}</td>
                      <td className="px-4 py-3 text-white text-sm font-mono">{r.created_this_week}</td>
                      <td className="px-4 py-3 text-white text-sm font-mono">{r.created_this_month}</td>
                      <td className="px-4 py-3 text-white text-sm font-mono font-bold">{r.total_created}</td>
                      <td className="px-4 py-3">
                        {r.objective ? (
                          <div className="min-w-[140px]">
                            <div className="flex items-center justify-between mb-1">
                              <span className={`text-xs font-mono font-bold ${getProgressTextColor(r.objective.percentage)}`}>
                                {r.objective.current_count}/{r.objective.target_count}
                              </span>
                              <span className={`text-xs font-mono ${getProgressTextColor(r.objective.percentage)}`}>
                                {Math.round(r.objective.percentage)}%
                              </span>
                            </div>
                            <div className="w-full bg-surface2 rounded-full h-2">
                              <div
                                className={`h-2 rounded-full transition-all ${getProgressColor(r.objective.percentage)}`}
                                style={{ width: `${Math.min(r.objective.percentage, 100)}%` }}
                              />
                            </div>
                            <p className="text-[10px] text-muted mt-0.5">{PERIOD_LABELS[r.objective.period] ?? r.objective.period}</p>
                          </div>
                        ) : (
                          <span className="text-xs text-muted">Aucun objectif</span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <button
                          onClick={() => handleSetObjective(r)}
                          className="text-xs text-violet hover:text-violet-light transition-colors whitespace-nowrap"
                        >
                          {r.objective ? 'Modifier objectif' : 'Fixer objectif'}
                        </button>
                      </td>
                    </tr>

                    {/* Inline objective form */}
                    {editingUserId === r.id && (
                      <tr className="border-b border-border">
                        <td colSpan={8} className="px-4 py-4 bg-surface2/50">
                          <form onSubmit={handleSubmitObjective} className="flex flex-wrap items-end gap-4">
                            <div>
                              <label className="block text-xs text-gray-400 mb-1">
                                Objectif pour <span className="text-white font-medium">{r.name}</span>
                              </label>
                            </div>
                            <div>
                              <label className="block text-xs text-gray-400 mb-1">Nombre cible</label>
                              <input
                                type="number"
                                min={1}
                                value={form.target_count}
                                onChange={e => setForm(p => ({ ...p, target_count: parseInt(e.target.value) || 1 }))}
                                className="w-24 bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
                              />
                            </div>
                            <div>
                              <label className="block text-xs text-gray-400 mb-1">Periode</label>
                              <select
                                value={form.period}
                                onChange={e => setForm(p => ({ ...p, period: e.target.value as ObjectiveForm['period'] }))}
                                className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
                              >
                                <option value="daily">Quotidien</option>
                                <option value="weekly">Hebdomadaire</option>
                                <option value="monthly">Mensuel</option>
                              </select>
                            </div>
                            <button
                              type="submit"
                              disabled={saving}
                              className="px-4 py-2 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white text-sm rounded-lg transition-colors"
                            >
                              {saving ? 'Enregistrement...' : 'Enregistrer'}
                            </button>
                            <button
                              type="button"
                              onClick={() => setEditingUserId(null)}
                              className="px-4 py-2 text-muted hover:text-white text-sm transition-colors"
                            >
                              Annuler
                            </button>
                          </form>
                        </td>
                      </tr>
                    )}
                  </React.Fragment>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Section: Doublons */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <h3 className="font-title font-semibold text-white mb-2">Doublons</h3>
        <div className="flex items-start gap-3 bg-surface2 rounded-lg p-4">
          <span className="text-cyan text-lg">ℹ️</span>
          <div>
            <p className="text-sm text-gray-300">
              Les doublons sont automatiquement bloques a la creation.
            </p>
            <p className="text-xs text-muted mt-1">
              Le systeme verifie le handle et l'email avant d'enregistrer un nouvel influenceur. Si un doublon est detecte, la creation est refusee avec un message d'erreur explicite.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
