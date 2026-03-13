import React from 'react';
import type { Contact } from '../types/influenceur';

const RESULT_CONFIG: Record<string, { label: string; color: string }> = {
  sent:      { label: 'Envoyé',      color: 'text-cyan bg-cyan/10' },
  replied:   { label: 'Répondu',     color: 'text-green-400 bg-green-500/10' },
  refused:   { label: 'Refusé',      color: 'text-red-400 bg-red-500/10' },
  registered:{ label: 'Signé',       color: 'text-violet-light bg-violet/10' },
  no_answer: { label: 'Sans réponse',color: 'text-muted bg-surface2' },
};

const CHANNEL_ICONS: Record<string, string> = {
  email: '📧', instagram: '📸', linkedin: '💼', whatsapp: '💬', phone: '📞', other: '📝',
};

interface Props {
  contacts: Contact[];
}

export default function ContactTimeline({ contacts }: Props) {
  if (contacts.length === 0) return (
    <p className="text-muted text-sm text-center py-8">Aucun contact enregistré.</p>
  );

  return (
    <div className="space-y-4">
      {contacts.map((contact, i) => {
        const result = RESULT_CONFIG[contact.result] ?? { label: contact.result, color: 'text-muted bg-surface2' };
        const isFirst = i === 0;
        return (
          <div key={contact.id} className="flex gap-4">
            {/* Timeline line */}
            <div className="flex flex-col items-center">
              <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0 ${isFirst ? 'bg-violet/30 text-violet-light' : 'bg-surface2 text-muted'}`}>
                {contact.rank}
              </div>
              {i < contacts.length - 1 && <div className="w-px flex-1 bg-border mt-2" />}
            </div>

            {/* Content */}
            <div className="flex-1 pb-4">
              <div className="bg-surface2 border border-border rounded-xl p-4">
                <div className="flex items-center justify-between gap-2 flex-wrap">
                  <div className="flex items-center gap-2">
                    <span title={contact.channel}>{CHANNEL_ICONS[contact.channel]}</span>
                    <span className={`px-2 py-0.5 rounded-full text-xs font-mono ${result.color}`}>{result.label}</span>
                    {isFirst && (
                      <span className="px-2 py-0.5 bg-violet/10 text-violet-light text-xs rounded-full font-mono">1er contact</span>
                    )}
                  </div>
                  <div className="flex items-center gap-3 text-xs text-muted">
                    {contact.user?.name && <span>{contact.user.name}</span>}
                    <span>{new Date(contact.date).toLocaleDateString('fr-FR')}</span>
                  </div>
                </div>

                {contact.sender && (
                  <p className="text-xs text-muted mt-2">Expéditeur : <span className="text-white">{contact.sender}</span></p>
                )}
                {contact.message && (
                  <p className="text-sm text-gray-300 mt-2 whitespace-pre-wrap border-l-2 border-violet/30 pl-3">{contact.message}</p>
                )}
                {contact.reply && (
                  <div className="mt-2 pl-4 border-l-2 border-cyan/30">
                    <p className="text-xs text-cyan mb-1">Réponse :</p>
                    <p className="text-sm text-gray-300 whitespace-pre-wrap">{contact.reply}</p>
                  </div>
                )}
                {contact.notes && (
                  <p className="text-xs text-muted mt-2 italic">{contact.notes}</p>
                )}
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}
