import React, { useEffect, useState, useContext } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/client';
import { AuthContext } from '../hooks/useAuth';
import type { ObjectiveProgress, Influenceur } from '../types/influenceur';

const PERIOD_LABELS: Record<string, string> = {
  daily: 'Quotidien',
  weekly: 'Hebdomadaire',
  monthly: 'Mensuel',
};

const PLATFORM_COLORS: Record<string, string> = {
  instagram: 'text-pink-400',
  tiktok: 'text-cyan',
  youtube: 'text-red-400',
  linkedin: 'text-blue-400',
  x: 'text-gray-300',
  facebook: 'text-blue-500',
  pinterest: 'text-red-300',
  podcast: 'text-violet',
  blog: 'text-amber',
  newsletter: 'text-green-400',
};

const STATUS_LABELS: Record<string, string> = {
  prospect: 'Prospect',
  contacted: 'Contacte',
  negotiating: 'Negociation',
  active: 'Actif',
  refused: 'Refuse',
  inactive: 'Inactif',
};

const STATUS_COLORS: Record<string, string> = {
  prospect: 'bg-gray-500/10 text-gray-400',
  contacted: 'bg-cyan/10 text-cyan',
  negotiating: 'bg-amber/10 text-amber',
  active: 'bg-green-500/10 text-green-400',
  refused: 'bg-red-500/10 text-red-400',
  inactive: 'bg-gray-500/10 text-gray-500',
};

interface MyStats {
  total: number;
  byStatus: Record<string, number>;
  newThisMonth: number;
}

export default function ResearcherDashboard() {
  const { user } = useContext(AuthContext);
  const [objective, setObjective] = useState<ObjectiveProgress | null>(null);
  const [objectiveLoading, setObjectiveLoading] = useState(true);
  const [stats, setStats] = useState<MyStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);
  const [recentInfluenceurs, setRecentInfluenceurs] = useState<Influenceur[]>([]);
  const [recentLoading, setRecentLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    // Fetch objective progress
    api.get<ObjectiveProgress>('/objectives/progress')
      .then(({ data }) => setObjective(data))
      .catch((err) => {
        if (err.response?.status !== 404) {
          setError('Erreur lors du chargement de l\'objectif.');
        }
      })
      .finally(() => setObjectiveLoading(false));

    // Fetch stats (scoped to researcher by backend)
    api.get<MyStats>('/stats')
      .then(({ data }) => setStats(data))
      .catch(() => setError('Erreur lors du chargement des statistiques.'))
      .finally(() => setStatsLoading(false));

    // Fetch recent influenceurs
    api.get<{ data: Influenceur[] }>('/influenceurs', { params: { limit: 10 } })
      .then(({ data }) => setRecentInfluenceurs(data.data ?? data as unknown as Influenceur[]))
      .catch(() => {})
      .finally(() => setRecentLoading(false));
  }, []);

  const loading = objectiveLoading && statsLoading;

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  // Compute stat cards from stats
  const todayCount = objective?.current_count ?? 0;
  const weekCount = stats?.byStatus ? Object.values(stats.byStatus).reduce((a, b) => a + b, 0) : 0;
  const monthCount = stats?.newThisMonth ?? 0;
  const totalCount = stats?.total ?? 0;

  return (
    <div className="p-6 space-y-6">
      <div>
        <h2 className="font-title text-2xl font-bold text-white">Mon Tableau de Bord</h2>
        <p className="text-muted text-sm mt-1">Bienvenue, {user?.name}</p>
      </div>

      {error && (
        <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg">
          {error}
        </div>
      )}

      {/* Section 1: Mon objectif */}
      <div className="bg-surface border border-border rounded-xl p-6">
        <h3 className="font-title font-semibold text-white mb-4">Mon objectif</h3>

        {objectiveLoading ? (
          <div className="flex items-center justify-center py-8">
            <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
          </div>
        ) : objective ? (
          <div className="flex items-center gap-8">
            {/* Progress circle */}
            <div className="relative w-32 h-32 flex-shrink-0">
              <svg className="w-32 h-32 transform -rotate-90" viewBox="0 0 120 120">
                <circle
                  cx="60" cy="60" r="52"
                  stroke="currentColor"
                  strokeWidth="8"
                  fill="none"
                  className="text-surface2"
                />
                <circle
                  cx="60" cy="60" r="52"
                  stroke="currentColor"
                  strokeWidth="8"
                  fill="none"
                  strokeDasharray={`${2 * Math.PI * 52}`}
                  strokeDashoffset={`${2 * Math.PI * 52 * (1 - Math.min(objective.percentage, 100) / 100)}`}
                  strokeLinecap="round"
                  className={
                    objective.percentage >= 80 ? 'text-green-500' :
                    objective.percentage >= 50 ? 'text-amber' :
                    'text-red-500'
                  }
                />
              </svg>
              <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className={`text-2xl font-bold font-title ${
                  objective.percentage >= 80 ? 'text-green-400' :
                  objective.percentage >= 50 ? 'text-amber' :
                  'text-red-400'
                }`}>
                  {Math.round(objective.percentage)}%
                </span>
              </div>
            </div>

            <div className="space-y-2">
              <p className="text-white text-lg font-medium">
                <span className="text-3xl font-bold font-title">{objective.current_count}</span>
                <span className="text-muted"> / {objective.target_count}</span>
              </p>
              <p className="text-muted text-sm">
                Periode : <span className="text-gray-300">{PERIOD_LABELS[objective.period] ?? objective.period}</span>
              </p>
              <p className="text-muted text-sm">
                Depuis le : <span className="text-gray-300">
                  {new Date(objective.start_date).toLocaleDateString('fr-FR')}
                </span>
              </p>
            </div>
          </div>
        ) : (
          <div className="text-center py-8">
            <p className="text-muted text-sm">Aucun objectif defini pour le moment.</p>
            <p className="text-xs text-gray-500 mt-1">Un administrateur peut vous fixer un objectif depuis la console admin.</p>
          </div>
        )}
      </div>

      {/* Section 2: Mes statistiques */}
      <div>
        <h3 className="font-title font-semibold text-white mb-3">Mes statistiques</h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-surface border border-border rounded-xl p-5">
            <p className="text-3xl font-bold text-cyan font-title">{todayCount}</p>
            <p className="text-xs text-muted mt-1">Aujourd'hui</p>
          </div>
          <div className="bg-surface border border-border rounded-xl p-5">
            <p className="text-3xl font-bold text-violet font-title">{weekCount}</p>
            <p className="text-xs text-muted mt-1">Cette semaine</p>
          </div>
          <div className="bg-surface border border-border rounded-xl p-5">
            <p className="text-3xl font-bold text-amber font-title">{monthCount}</p>
            <p className="text-xs text-muted mt-1">Ce mois</p>
          </div>
          <div className="bg-surface border border-border rounded-xl p-5">
            <p className="text-3xl font-bold text-white font-title">{totalCount}</p>
            <p className="text-xs text-muted mt-1">Total</p>
          </div>
        </div>
      </div>

      {/* Section 3: Mes derniers ajouts */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <div className="px-5 py-4 border-b border-border flex items-center justify-between">
          <h3 className="font-title font-semibold text-white">Mes derniers ajouts</h3>
          <Link to="/influenceurs" className="text-xs text-violet hover:text-violet-light transition-colors">
            Voir tout &rarr;
          </Link>
        </div>

        {recentLoading ? (
          <div className="flex items-center justify-center py-8">
            <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
          </div>
        ) : recentInfluenceurs.length === 0 ? (
          <div className="p-8 text-center text-muted text-sm">
            Aucun influenceur ajoute pour le moment.
          </div>
        ) : (
          <table className="w-full">
            <thead>
              <tr className="border-b border-border">
                {['Nom', 'Plateforme', 'Statut', "Date d'ajout"].map(h => (
                  <th key={h} className="text-left text-xs text-muted font-medium px-4 py-3">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {recentInfluenceurs.slice(0, 10).map(inf => (
                <tr key={inf.id} className="border-b border-border last:border-0 hover:bg-surface2 transition-colors">
                  <td className="px-4 py-3">
                    <Link
                      to={`/influenceurs/${inf.id}`}
                      className="text-sm font-medium text-white hover:text-violet-light transition-colors"
                    >
                      {inf.name}
                    </Link>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`text-sm capitalize ${PLATFORM_COLORS[inf.primary_platform] ?? 'text-gray-300'}`}>
                      {inf.primary_platform}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-0.5 rounded-full text-xs ${STATUS_COLORS[inf.status] ?? 'bg-surface2 text-muted'}`}>
                      {STATUS_LABELS[inf.status] ?? inf.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-muted text-sm">
                    {new Date(inf.created_at).toLocaleDateString('fr-FR', {
                      day: 'numeric',
                      month: 'short',
                      year: 'numeric',
                    })}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
