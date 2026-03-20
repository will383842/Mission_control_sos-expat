import React, { useContext } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { useStats } from '../hooks/useStats';
import { useReminders } from '../hooks/useReminders';
import { AuthContext } from '../hooks/useAuth';
import StatusBadge from '../components/StatusBadge';
import PlatformBadge from '../components/PlatformBadge';

const STATUS_LABELS: Record<string, string> = {
  prospect: 'Prospect', contacted: 'Contacté', negotiating: 'Négociation',
  active: 'Actif', refused: 'Refusé', inactive: 'Inactif',
};

const ACTION_LABELS: Record<string, string> = {
  created: 'a créé', contact_added: 'a ajouté un contact pour', updated: 'a modifié',
  status_changed: 'a changé le statut de', login: 's\'est connecté',
  reminder_dismissed: 'a dismissé un rappel pour', reminder_done: 'a traité un rappel pour',
  deleted: 'a supprimé',
};

export default function Dashboard() {
  const { stats, loading } = useStats();
  const { reminders, dismiss } = useReminders();
  const { user } = useContext(AuthContext);

  if (user?.role === 'researcher') {
    return <Navigate to="/mon-tableau" replace />;
  }

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  const statusOrder = ['prospect', 'contacted', 'negotiating', 'active', 'refused', 'inactive'];
  const statusColors: Record<string, string> = {
    prospect: 'text-muted', contacted: 'text-cyan', negotiating: 'text-amber',
    active: 'text-success', refused: 'text-danger', inactive: 'text-muted',
  };

  return (
    <div className="p-6 space-y-6">
      <div>
        <h2 className="font-title text-2xl font-bold text-white">Dashboard</h2>
        <p className="text-muted text-sm mt-1">Bienvenue, {user?.name}</p>
      </div>

      {/* Barre stats */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        {statusOrder.map(status => (
          <div key={status} className="bg-surface border border-border rounded-xl p-4">
            <p className={`text-2xl font-bold font-title ${statusColors[status]}`}>
              {stats?.byStatus?.[status] ?? 0}
            </p>
            <p className="text-xs text-muted mt-1">{STATUS_LABELS[status]}</p>
          </div>
        ))}
      </div>

      {/* KPIs rapides */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-muted text-sm">Taux de réponse</p>
          <p className="text-3xl font-bold text-cyan font-title mt-1">{stats?.responseRate ?? 0}%</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-muted text-sm">Taux de conversion</p>
          <p className="text-3xl font-bold text-violet font-title mt-1">{stats?.conversionRate ?? 0}%</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-muted text-sm">Nouveaux ce mois</p>
          <p className="text-3xl font-bold text-amber font-title mt-1">{stats?.newThisMonth ?? 0}</p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* À relancer */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-title font-semibold text-white">À relancer</h3>
            <Link to="/a-relancer" className="text-xs text-violet hover:text-violet-light transition-colors">
              Voir tout →
            </Link>
          </div>
          {reminders.length === 0 ? (
            <p className="text-muted text-sm">Aucun rappel en attente.</p>
          ) : (
            <div className="space-y-3">
              {reminders.slice(0, 5).map(r => (
                <div key={r.id} className="flex items-center justify-between py-2 border-b border-border last:border-0">
                  <div>
                    <p className="text-sm font-medium text-white">{r.influenceur?.name}</p>
                    <p className="text-xs text-amber mt-0.5">
                      {r.days_elapsed != null ? `${r.days_elapsed}j sans contact` : 'Date inconnue'}
                    </p>
                  </div>
                  <button
                    onClick={() => dismiss(r.id)}
                    className="text-xs text-muted hover:text-white px-2 py-1 rounded border border-border hover:border-gray-600 transition-colors"
                  >
                    Reporter
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Dernières activités */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title font-semibold text-white mb-4">Activité récente</h3>
          {(stats?.recentActivity ?? []).length === 0 ? (
            <p className="text-muted text-sm">Aucune activité.</p>
          ) : (
            <div className="space-y-3">
              {stats?.recentActivity?.map(log => (
                <div key={log.id} className="flex gap-3 py-2 border-b border-border last:border-0">
                  <div className="w-7 h-7 rounded-full bg-violet/20 flex items-center justify-center text-violet-light text-xs font-bold flex-shrink-0">
                    {log.user?.name?.[0] ?? '?'}
                  </div>
                  <div>
                    <p className="text-sm text-white">
                      <span className="font-medium">{log.user?.name}</span>{' '}
                      {ACTION_LABELS[log.action] ?? log.action}
                      {log.influenceur && (
                        <> <Link to={`/influenceurs/${log.influenceur_id}`} className="text-violet-light hover:underline">{log.influenceur.name}</Link></>
                      )}
                    </p>
                    <p className="text-xs text-muted mt-0.5">
                      {new Date(log.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
