import React, { useEffect, useState, useContext } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/client';
import { AuthContext } from '../hooks/useAuth';
import type { ObjectiveProgress, ObjectiveWithProgress, Influenceur } from '../types/influenceur';
import { getCountryFlag } from '../data/countries';
import { getLanguageLabel } from '../lib/constants';

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

function getProgressColor(percentage: number, daysRemaining: number): string {
  if (daysRemaining < 0) return 'text-gray-500';
  if (percentage >= 80 && daysRemaining > 0) return 'text-green-400';
  if (percentage >= 50 || daysRemaining <= 3) return 'text-amber';
  if (percentage < 50 && daysRemaining <= 3) return 'text-red-400';
  return 'text-violet-light';
}

function getProgressBarColor(percentage: number, daysRemaining: number): string {
  if (daysRemaining < 0) return 'text-gray-500';
  if (percentage >= 80 && daysRemaining > 0) return 'text-green-500';
  if (percentage >= 50 || daysRemaining <= 3) return 'text-amber';
  if (percentage < 50 && daysRemaining <= 3) return 'text-red-500';
  return 'text-violet';
}

function getCardBorderColor(obj: ObjectiveWithProgress): string {
  if (obj.days_remaining < 0) return 'border-gray-600';
  if (obj.percentage >= 80 && obj.days_remaining > 0) return 'border-green-500/40';
  if (obj.percentage >= 50 || obj.days_remaining <= 3) return 'border-amber/40';
  if (obj.percentage < 50 && obj.days_remaining <= 3) return 'border-red-500/40';
  return 'border-violet/40';
}

function isInfluenceurValid(inf: Influenceur): { valid: boolean; missing: string[] } {
  const missing: string[] = [];
  if (!inf.profile_url) missing.push('URL profil');
  if (!inf.name) missing.push('Nom');
  if (!inf.email && !inf.phone) missing.push('Email ou telephone');
  return { valid: missing.length === 0, missing };
}

export default function ResearcherDashboard() {
  const { user } = useContext(AuthContext);
  const [progress, setProgress] = useState<ObjectiveProgress | null>(null);
  const [progressLoading, setProgressLoading] = useState(true);
  const [recentInfluenceurs, setRecentInfluenceurs] = useState<Influenceur[]>([]);
  const [recentLoading, setRecentLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    // Fetch objective progress
    api.get<ObjectiveProgress>('/objectives/progress')
      .then(({ data }) => setProgress(data))
      .catch((err) => {
        if (err.response?.status !== 404) {
          setError('Erreur lors du chargement de la progression.');
        }
      })
      .finally(() => setProgressLoading(false));

    // Fetch recent influenceurs
    api.get<{ data: Influenceur[] }>('/contacts', { params: { limit: 10 } })
      .then(({ data }) => setRecentInfluenceurs(data.data ?? data as unknown as Influenceur[]))
      .catch(() => {})
      .finally(() => setRecentLoading(false));
  }, []);

  const loading = progressLoading;

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  const globalPct = progress?.global_progress?.percentage ?? 0;
  const globalCurrent = progress?.global_progress?.total_current ?? 0;
  const globalTarget = progress?.global_progress?.total_target ?? 0;
  const objectives = progress?.objectives ?? [];

  // SVG circle params
  const radius = 52;
  const circumference = 2 * Math.PI * radius;
  const strokeOffset = circumference * (1 - Math.min(globalPct, 100) / 100);

  const heroColor = globalPct >= 80 ? 'text-green-500' : globalPct >= 50 ? 'text-amber' : 'text-red-500';
  const heroTextColor = globalPct >= 80 ? 'text-green-400' : globalPct >= 50 ? 'text-amber' : 'text-red-400';

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h2 className="font-title text-2xl font-bold text-white">Mon Tableau de Bord</h2>
        <p className="text-muted text-sm mt-1">Bienvenue, {user?.name}</p>
      </div>

      {error && (
        <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg">
          {error}
        </div>
      )}

      {/* Section 1: Progression globale (hero) */}
      <div className="bg-surface border border-border rounded-xl p-6 md:p-8">
        <h3 className="font-title font-semibold text-white mb-6">Progression globale</h3>

        {globalTarget > 0 ? (
          <div className="flex flex-col sm:flex-row items-center gap-8">
            {/* Large circular progress */}
            <div className="relative w-40 h-40 flex-shrink-0">
              <svg className="w-40 h-40 transform -rotate-90" viewBox="0 0 120 120">
                <circle
                  cx="60" cy="60" r={radius}
                  stroke="currentColor"
                  strokeWidth="8"
                  fill="none"
                  className="text-surface2"
                />
                <circle
                  cx="60" cy="60" r={radius}
                  stroke="currentColor"
                  strokeWidth="8"
                  fill="none"
                  strokeDasharray={`${circumference}`}
                  strokeDashoffset={`${strokeOffset}`}
                  strokeLinecap="round"
                  className={heroColor}
                  style={{ transition: 'stroke-dashoffset 0.8s ease-out' }}
                />
              </svg>
              <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className={`text-3xl font-bold font-title ${heroTextColor}`}>
                  {Math.round(globalPct)}%
                </span>
              </div>
            </div>

            <div className="space-y-2 text-center sm:text-left">
              <p className="text-white text-lg font-medium">
                <span className={`text-4xl font-bold font-title ${heroTextColor}`}>{globalCurrent}</span>
                <span className="text-muted text-xl"> / {globalTarget}</span>
              </p>
              <p className="text-muted text-sm">contacts valides au total</p>
              {objectives.length > 0 && (
                <p className="text-xs text-gray-500">
                  {objectives.length} objectif{objectives.length !== 1 ? 's' : ''} actif{objectives.length !== 1 ? 's' : ''}
                </p>
              )}
            </div>
          </div>
        ) : (
          <div className="text-center py-8">
            <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-surface2 flex items-center justify-center">
              <span className="text-2xl text-muted">--</span>
            </div>
            <p className="text-muted text-sm">Aucun objectif defini pour le moment.</p>
            <p className="text-xs text-gray-500 mt-1">Un administrateur peut vous fixer des objectifs depuis la console admin.</p>
          </div>
        )}
      </div>

      {/* Section 2: Mes objectifs (cards grid) */}
      {objectives.length > 0 && (
        <div>
          <h3 className="font-title font-semibold text-white mb-3">Mes objectifs</h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {objectives.map(obj => {
              const pctColor = getProgressColor(obj.percentage, obj.days_remaining);
              const barColor = getProgressBarColor(obj.percentage, obj.days_remaining);
              const borderColor = getCardBorderColor(obj);
              const barFillPct = Math.min(obj.percentage, 100);

              return (
                <div key={obj.id} className={`bg-surface border ${borderColor} rounded-xl p-5 transition-colors`}>
                  {/* Country + Language + Niche header */}
                  <div className="flex items-start justify-between mb-4">
                    <div>
                      <p className="text-white font-medium flex items-center gap-2">
                        <span className="text-lg">
                          {obj.countries && obj.countries.length > 0
                            ? getCountryFlag(obj.countries[0])
                            : '🌍'}
                        </span>
                        {obj.countries && obj.countries.length > 0
                          ? (obj.countries.length <= 2
                            ? obj.countries.join(', ')
                            : `${obj.countries[0]} +${obj.countries.length - 1}`)
                          : 'Tous pays'}
                      </p>
                      <p className="text-xs text-muted mt-0.5">
                        {obj.language ? getLanguageLabel(obj.language) : 'Toutes langues'} {obj.niche ? `/ ${obj.niche}` : '/ Tous types'}
                      </p>
                    </div>
                    <span className={`text-2xl font-bold font-title ${pctColor}`}>
                      {Math.round(obj.percentage)}%
                    </span>
                  </div>

                  {/* Progress bar */}
                  <div className="mb-3">
                    <div className="flex items-center justify-between mb-1">
                      <span className={`text-xs font-mono font-bold ${pctColor}`}>
                        {obj.current_count} / {obj.target_count}
                      </span>
                    </div>
                    <div className="w-full bg-surface2 rounded-full h-2.5">
                      <div
                        className={`h-2.5 rounded-full transition-all duration-500 ${barColor.replace('text-', 'bg-')}`}
                        style={{ width: `${barFillPct}%` }}
                      />
                    </div>
                  </div>

                  {/* Deadline */}
                  <div className="flex items-center justify-between">
                    <span className="text-xs text-muted">
                      Deadline: {new Date(obj.deadline).toLocaleDateString('fr-FR', {
                        day: 'numeric',
                        month: 'short',
                      })}
                    </span>
                    {obj.days_remaining < 0 ? (
                      <span className="text-xs text-gray-500 font-medium px-2 py-0.5 bg-gray-500/10 rounded-full">Expire</span>
                    ) : obj.days_remaining === 0 ? (
                      <span className="text-xs text-red-400 font-bold px-2 py-0.5 bg-red-500/10 rounded-full">Aujourd'hui !</span>
                    ) : obj.days_remaining <= 3 ? (
                      <span className="text-xs text-amber font-bold px-2 py-0.5 bg-amber/10 rounded-full">
                        {obj.days_remaining}j restant{obj.days_remaining !== 1 ? 's' : ''}
                      </span>
                    ) : (
                      <span className="text-xs text-gray-400 px-2 py-0.5 bg-surface2 rounded-full">
                        {obj.days_remaining}j restants
                      </span>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Section 3: Criteres de validation */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <h3 className="font-title font-semibold text-white mb-3">Criteres de validation</h3>
        <p className="text-xs text-muted mb-3">Un contact est considéré comme "valide" lorsqu'il remplit ces conditions :</p>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div className="flex items-center gap-3 bg-surface2 rounded-lg p-3">
            <div className="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center flex-shrink-0">
              <span className="text-green-400 text-sm font-bold">1</span>
            </div>
            <div>
              <p className="text-sm text-white font-medium">URL du profil</p>
              <p className="text-xs text-muted">Obligatoire, lien vers le profil (pas une video)</p>
            </div>
          </div>
          <div className="flex items-center gap-3 bg-surface2 rounded-lg p-3">
            <div className="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center flex-shrink-0">
              <span className="text-green-400 text-sm font-bold">2</span>
            </div>
            <div>
              <p className="text-sm text-white font-medium">Nom complet</p>
              <p className="text-xs text-muted">Le nom du contact doit être renseigné</p>
            </div>
          </div>
          <div className="flex items-center gap-3 bg-surface2 rounded-lg p-3">
            <div className="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center flex-shrink-0">
              <span className="text-green-400 text-sm font-bold">3</span>
            </div>
            <div>
              <p className="text-sm text-white font-medium">Email ou telephone</p>
              <p className="text-xs text-muted">Au moins un moyen de contact direct</p>
            </div>
          </div>
          <div className="flex items-center gap-3 bg-surface2 rounded-lg p-3">
            <div className="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center flex-shrink-0">
              <span className="text-green-400 text-sm font-bold">4</span>
            </div>
            <div>
              <p className="text-sm text-white font-medium">Pas de doublon</p>
              <p className="text-xs text-muted">L'URL du profil ne doit pas deja exister</p>
            </div>
          </div>
        </div>
      </div>

      {/* Section 4: Mes derniers ajouts */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <div className="px-5 py-4 border-b border-border flex items-center justify-between">
          <h3 className="font-title font-semibold text-white">Mes derniers ajouts</h3>
          <Link to="/contacts" className="text-xs text-violet hover:text-violet-light transition-colors">
            Voir tout &rarr;
          </Link>
        </div>

        {recentLoading ? (
          <div className="flex items-center justify-center py-8">
            <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
          </div>
        ) : recentInfluenceurs.length === 0 ? (
          <div className="p-8 text-center text-muted text-sm">
            Aucun contact ajouté pour le moment. Commencez par en créer un !
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-border">
                  {['Nom', 'Plateforme', 'Pays', 'Langue', 'Validation'].map(h => (
                    <th key={h} className="text-left text-xs text-muted font-medium px-4 py-3 whitespace-nowrap">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {recentInfluenceurs.slice(0, 10).map(inf => {
                  const validation = isInfluenceurValid(inf);
                  return (
                    <tr key={inf.id} className="border-b border-border last:border-0 hover:bg-surface2 transition-colors">
                      <td className="px-4 py-3">
                        <Link
                          to={`/contacts/${inf.id}`}
                          className="text-sm font-medium text-white hover:text-violet-light transition-colors whitespace-nowrap"
                        >
                          {inf.name}
                        </Link>
                      </td>
                      <td className="px-4 py-3">
                        <span className={`text-sm capitalize ${PLATFORM_COLORS[inf.primary_platform] ?? 'text-gray-300'}`}>
                          {inf.primary_platform}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-gray-300 text-sm whitespace-nowrap">
                        {inf.country ?? '-'}
                      </td>
                      <td className="px-4 py-3 text-gray-300 text-sm whitespace-nowrap">
                        {inf.language ? getLanguageLabel(inf.language) : '-'}
                      </td>
                      <td className="px-4 py-3">
                        {validation.valid ? (
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-green-500/10 text-green-400 font-medium">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" className="flex-shrink-0">
                              <path d="M2 6l3 3 5-5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                            </svg>
                            Valide
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-amber/10 text-amber" title={`Manque: ${validation.missing.join(', ')}`}>
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" className="flex-shrink-0">
                              <path d="M6 3v3.5M6 8.5v.5" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
                            </svg>
                            {validation.missing.length} manquant{validation.missing.length > 1 ? 's' : ''}
                          </span>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
