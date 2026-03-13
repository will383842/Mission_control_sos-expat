import React from 'react';
import { Link } from 'react-router-dom';
import type { ReminderWithInfluenceur } from '../types/influenceur';

interface Props {
  reminder: ReminderWithInfluenceur;
  onDismiss: (id: number) => void;
  onDone: (id: number) => void;
}

export default function ReminderAlert({ reminder, onDismiss, onDone }: Props) {
  return (
    <div className="flex items-center justify-between gap-4 bg-amber/5 border border-amber/20 rounded-xl p-4">
      <div className="flex items-center gap-3">
        <span className="text-amber text-lg">🔔</span>
        <div>
          <Link to={`/influenceurs/${reminder.influenceur_id}`} className="text-white font-medium hover:text-violet-light transition-colors text-sm">
            {reminder.influenceur?.name}
          </Link>
          <p className="text-amber text-xs mt-0.5">
            {reminder.days_elapsed != null ? `${reminder.days_elapsed} jours sans contact` : 'Relance en attente'}
          </p>
        </div>
      </div>
      <div className="flex gap-2">
        <button onClick={() => onDone(reminder.id)} className="text-xs text-green-400 hover:text-green-300 px-2 py-1 rounded transition-colors">Traité</button>
        <button onClick={() => onDismiss(reminder.id)} className="text-xs text-muted hover:text-white px-2 py-1 rounded transition-colors">Reporter</button>
      </div>
    </div>
  );
}
