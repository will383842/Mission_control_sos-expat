import React from 'react';
import { Link } from 'react-router-dom';
import type { Influenceur } from '../types/influenceur';
import PlatformBadge from './PlatformBadge';
import StatusBadge from './StatusBadge';

interface Props {
  influenceur: Influenceur;
}

export default function InfluenceurCard({ influenceur }: Props) {
  return (
    <Link
      to={`/influenceurs/${influenceur.id}`}
      className="bg-surface border border-border rounded-2xl p-5 hover:border-violet/40 hover:bg-surface2 transition-all block group"
    >
      <div className="flex items-start gap-3">
        {influenceur.avatar_url ? (
          <img
            src={influenceur.avatar_url}
            alt={influenceur.name}
            className="w-12 h-12 rounded-full object-cover flex-shrink-0"
          />
        ) : (
          <div className="w-12 h-12 rounded-full bg-violet/20 flex items-center justify-center text-lg font-bold text-violet-light flex-shrink-0">
            {influenceur.name[0]}
          </div>
        )}
        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between gap-2">
            <h3 className="font-medium text-white group-hover:text-violet-light transition-colors truncate">
              {influenceur.name}
            </h3>
            {influenceur.pending_reminder && (
              <span className="px-2 py-0.5 bg-amber/20 text-amber text-xs rounded-full font-mono flex-shrink-0">
                RELANCER
              </span>
            )}
          </div>
          {influenceur.handle && (
            <p className="text-cyan text-sm font-mono mt-0.5 truncate">@{influenceur.handle}</p>
          )}
        </div>
      </div>

      <div className="flex items-center gap-2 mt-3 flex-wrap">
        <PlatformBadge platform={influenceur.primary_platform} />
        <StatusBadge status={influenceur.status} />
      </div>

      <div className="flex items-center justify-between mt-3 text-sm">
        <span className="text-muted">
          {influenceur.followers != null
            ? `${influenceur.followers.toLocaleString('fr-FR')} followers`
            : 'Followers inconnus'}
        </span>
        {influenceur.assigned_to_user && (
          <span className="text-muted text-xs truncate max-w-[100px]">
            → {influenceur.assigned_to_user.name}
          </span>
        )}
      </div>

      {influenceur.last_contact_at && (
        <p className="text-xs text-muted mt-2">
          Dernier contact : {new Date(influenceur.last_contact_at).toLocaleDateString('fr-FR')}
        </p>
      )}
    </Link>
  );
}
