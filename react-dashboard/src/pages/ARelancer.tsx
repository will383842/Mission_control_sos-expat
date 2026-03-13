import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useReminders } from '../hooks/useReminders';
import StatusBadge from '../components/StatusBadge';
import PlatformBadge from '../components/PlatformBadge';

export default function ARelancer() {
  const { reminders, loading, dismiss, markDone } = useReminders();
  const [dismissingId, setDismissingId] = useState<number | null>(null);
  const [dismissNote, setDismissNote] = useState('');

  const handleDismiss = async (id: number) => {
    await dismiss(id, dismissNote || undefined);
    setDismissingId(null);
    setDismissNote('');
  };

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  return (
    <div className="p-6">
      <div className="mb-6">
        <h2 className="font-title text-2xl font-bold text-white">À relancer</h2>
        <p className="text-muted text-sm mt-1">
          {reminders.length} influenceur{reminders.length !== 1 ? 's' : ''} en attente de relance
        </p>
      </div>

      {reminders.length === 0 ? (
        <div className="bg-surface border border-border rounded-xl p-12 text-center">
          <p className="text-4xl mb-3">✅</p>
          <p className="text-white font-medium">Aucun rappel en attente</p>
          <p className="text-muted text-sm mt-1">Tous les influenceurs sont à jour.</p>
        </div>
      ) : (
        <div className="space-y-3">
          {reminders.map(r => (
            <div key={r.id} className="bg-surface border border-border rounded-xl p-5">
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <Link
                      to={`/influenceurs/${r.influenceur_id}`}
                      className="font-medium text-white hover:text-violet-light transition-colors"
                    >
                      {r.influenceur?.name}
                    </Link>
                    <StatusBadge status={r.influenceur?.status} />
                    {r.influenceur?.primary_platform && (
                      <PlatformBadge platform={r.influenceur.primary_platform} />
                    )}
                  </div>

                  <div className="flex items-center gap-4 mt-2 text-sm text-muted">
                    <span>
                      Dernier contact :{' '}
                      {r.influenceur?.last_contact_at
                        ? new Date(r.influenceur.last_contact_at).toLocaleDateString('fr-FR')
                        : 'Jamais'}
                    </span>
                    {r.days_elapsed != null && (
                      <span className="text-amber font-medium">
                        {r.days_elapsed} jour{r.days_elapsed !== 1 ? 's' : ''} écoulé{r.days_elapsed !== 1 ? 's' : ''}
                      </span>
                    )}
                    {r.influenceur?.assigned_to_user && (
                      <span>Assigné à : {r.influenceur.assigned_to_user.name}</span>
                    )}
                  </div>
                </div>

                <div className="flex items-center gap-2 flex-shrink-0">
                  <Link
                    to={`/influenceurs/${r.influenceur_id}`}
                    className="px-3 py-1.5 bg-violet/20 hover:bg-violet/30 text-violet-light text-sm rounded-lg transition-colors"
                  >
                    Ajouter relance
                  </Link>
                  <button
                    onClick={() => setDismissingId(dismissingId === r.id ? null : r.id)}
                    className="px-3 py-1.5 bg-surface2 hover:bg-border text-muted hover:text-white text-sm rounded-lg border border-border transition-colors"
                  >
                    Reporter
                  </button>
                  <button
                    onClick={() => markDone(r.id)}
                    className="px-3 py-1.5 bg-green-500/10 hover:bg-green-500/20 text-green-400 text-sm rounded-lg transition-colors"
                  >
                    Traité
                  </button>
                </div>
              </div>

              {/* Panneau dismiss avec note */}
              {dismissingId === r.id && (
                <div className="mt-4 pt-4 border-t border-border flex gap-3">
                  <input
                    type="text"
                    value={dismissNote}
                    onChange={e => setDismissNote(e.target.value)}
                    placeholder="Note (optionnel)..."
                    className="flex-1 bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-white placeholder-muted focus:outline-none focus:border-violet"
                  />
                  <button
                    onClick={() => handleDismiss(r.id)}
                    className="px-4 py-2 bg-amber/20 hover:bg-amber/30 text-amber text-sm rounded-lg transition-colors"
                  >
                    Confirmer
                  </button>
                  <button
                    onClick={() => { setDismissingId(null); setDismissNote(''); }}
                    className="px-4 py-2 text-muted hover:text-white text-sm transition-colors"
                  >
                    Annuler
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
