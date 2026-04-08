import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import type { Influenceur } from '../types/influenceur';
import ContactTypeBadge from './ContactTypeBadge';
import StatusBadge from './StatusBadge';
import { getCountryFlag, getLanguageLabel, getCategoryForType } from '../lib/constants';

type SortKey = 'name' | 'data_completeness' | 'status' | 'last_contact_at' | 'country' | 'score';

interface Props {
  influenceurs: Influenceur[];
}

/** Barre de complétion colorée (0-100) */
function CompletenessBar({ value }: { value: number }) {
  const color =
    value >= 75 ? 'bg-green-500' :
    value >= 50 ? 'bg-amber-500' :
    value >= 25 ? 'bg-orange-500' : 'bg-red-500';

  return (
    <div className="flex items-center gap-1.5 min-w-[60px]">
      <div className="flex-1 h-1.5 bg-surface2 rounded-full overflow-hidden">
        <div className={`h-full rounded-full transition-all ${color}`} style={{ width: `${value}%` }} />
      </div>
      <span className="text-muted text-xs tabular-nums w-7 text-right">{value}%</span>
    </div>
  );
}

/** Affichage de l'email avec statut + tooltip scraped emails */
function EmailCell({ inf }: { inf: Influenceur }) {
  const [showTooltip, setShowTooltip] = useState(false);
  const extraEmails = (inf.scraped_emails ?? []).filter(e => e !== inf.email);

  if (!inf.email) {
    // Vérifie si des emails ont été scrappés même sans email principal
    if (extraEmails.length > 0) {
      return (
        <div className="relative" onMouseEnter={() => setShowTooltip(true)} onMouseLeave={() => setShowTooltip(false)}>
          <span className="inline-flex items-center gap-1 text-xs text-amber-400/70 cursor-help">
            <span>📬</span>
            <span>{extraEmails.length} scrappé{extraEmails.length > 1 ? 's' : ''}</span>
          </span>
          {showTooltip && (
            <div className="absolute left-0 top-full mt-1 z-50 bg-surface2 border border-border rounded-lg p-2 space-y-1 w-60 shadow-xl">
              <p className="text-xs text-muted mb-1">Emails scrappés :</p>
              {extraEmails.slice(0, 5).map((e, i) => (
                <a key={i} href={`mailto:${e}`} className="block text-xs text-cyan-400 hover:underline truncate">{e}</a>
              ))}
            </div>
          )}
        </div>
      );
    }
    return <span className="text-red-400/40 text-xs">—</span>;
  }

  return (
    <div className="relative flex items-center gap-1" onMouseEnter={() => setShowTooltip(true)} onMouseLeave={() => setShowTooltip(false)}>
      <a href={`mailto:${inf.email}`} className="text-cyan-400 hover:underline text-xs truncate max-w-[140px]">
        {inf.email}
      </a>
      {inf.is_verified && <span className="text-green-400 text-xs" title="Vérifié">✓</span>}
      {inf.bounce_count > 0 && <span className="text-red-400 text-xs" title={`${inf.bounce_count} bounce(s)`}>⚠</span>}
      {extraEmails.length > 0 && (
        <span className="text-muted text-xs cursor-help" title={`+${extraEmails.length} autre(s)`}>
          +{extraEmails.length}
        </span>
      )}
      {showTooltip && (inf.is_verified || inf.bounce_count > 0 || extraEmails.length > 0) && (
        <div className="absolute left-0 top-full mt-1 z-50 bg-surface2 border border-border rounded-lg p-2 space-y-1 w-60 shadow-xl">
          <p className="text-xs text-muted font-medium">{inf.email}</p>
          {inf.is_verified && <p className="text-xs text-green-400">✓ Email vérifié</p>}
          {inf.bounce_count > 0 && <p className="text-xs text-red-400">⚠ {inf.bounce_count} bounce(s)</p>}
          {extraEmails.length > 0 && (
            <div className="border-t border-border pt-1 mt-1">
              <p className="text-xs text-muted mb-1">Autres emails scrappés :</p>
              {extraEmails.slice(0, 4).map((e, i) => (
                <a key={i} href={`mailto:${e}`} className="block text-xs text-cyan-300 hover:underline truncate">{e}</a>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

/** Liens sociaux compacts */
function SocialLinks({ inf }: { inf: Influenceur }) {
  const links: { url: string | null; icon: string; label: string }[] = [
    { url: inf.linkedin_url, icon: '💼', label: 'LinkedIn' },
    { url: inf.twitter_url,  icon: '𝕏',  label: 'X/Twitter' },
    { url: inf.instagram_url,icon: '📸', label: 'Instagram' },
    { url: inf.tiktok_url,   icon: '🎵', label: 'TikTok' },
    { url: inf.youtube_url,  icon: '▶️', label: 'YouTube' },
    { url: inf.facebook_url, icon: '👤', label: 'Facebook' },
  ].filter(l => !!l.url);

  if (links.length === 0) return <span className="text-muted/30 text-xs">—</span>;

  return (
    <div className="flex items-center gap-1">
      {links.slice(0, 3).map(l => (
        <a
          key={l.label}
          href={l.url!}
          target="_blank"
          rel="noopener noreferrer"
          title={l.label}
          className="text-sm hover:opacity-75 transition-opacity"
        >
          {l.icon}
        </a>
      ))}
      {links.length > 3 && <span className="text-muted text-xs">+{links.length - 3}</span>}
    </div>
  );
}

export default function InfluenceurTable({ influenceurs }: Props) {
  const [sortKey, setSortKey] = useState<SortKey>('name');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');

  const handleSort = (key: SortKey) => {
    if (sortKey === key) setSortDir(d => d === 'asc' ? 'desc' : 'asc');
    else { setSortKey(key); setSortDir('asc'); }
  };

  const sorted = [...influenceurs].sort((a, b) => {
    let cmp = 0;
    if (sortKey === 'data_completeness' || sortKey === 'score') {
      cmp = (Number(a[sortKey]) || 0) - (Number(b[sortKey]) || 0);
    } else if (sortKey === 'last_contact_at') {
      cmp = new Date(a[sortKey] ?? 0).getTime() - new Date(b[sortKey] ?? 0).getTime();
    } else {
      cmp = String(a[sortKey] ?? '').localeCompare(String(b[sortKey] ?? ''), 'fr');
    }
    return sortDir === 'asc' ? cmp : -cmp;
  });

  const Th = ({ label, field, className = '' }: { label: string; field?: SortKey; className?: string }) => (
    <th
      className={`text-left text-xs text-muted font-medium px-3 py-3 whitespace-nowrap ${field ? 'cursor-pointer hover:text-white select-none' : ''} ${className}`}
      onClick={() => field && handleSort(field)}
    >
      {label}{field && sortKey === field ? (sortDir === 'asc' ? ' ↑' : ' ↓') : ''}
    </th>
  );

  return (
    <div className="bg-surface border border-border rounded-xl overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead>
            <tr className="border-b border-border bg-surface2/30">
              <Th label="" className="w-1 p-0" />       {/* barre catégorie */}
              <Th label="Contact" field="name" />
              <Th label="Type" />
              <Th label="Pays" field="country" />
              <Th label="Langue" />
              <Th label="Email" />
              <Th label="Téléphone" />
              <Th label="Réseaux" />
              <Th label="Formulaire" />
              <Th label="Statut" field="status" />
              <Th label="Complétude" field="data_completeness" />
              <Th label="Dernier contact" field="last_contact_at" />
              <Th label="" />  {/* actions */}
            </tr>
          </thead>
          <tbody>
            {sorted.map(inf => {
              const cat = getCategoryForType(inf.contact_type);
              const mainUrl = inf.website_url || inf.profile_url;
              const formUrl = (inf.scraped_social as Record<string, unknown> | null)?.['_contact_form_url'] as string | undefined;

              return (
                <tr
                  key={inf.id}
                  className="border-b border-border last:border-0 hover:bg-surface2/60 transition-colors group"
                >
                  {/* Barre catégorie (couleur indicative) */}
                  <td className="p-0 w-1">
                    <div className="w-0.5 h-full min-h-[48px]" style={{ backgroundColor: cat.color, opacity: 0.6 }} />
                  </td>

                  {/* Nom + handle */}
                  <td className="px-3 py-3">
                    <div className="flex items-center gap-2">
                      {/* Avatar initiales */}
                      <div
                        className="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                        style={{ backgroundColor: cat.color + '25', color: cat.color }}
                      >
                        {inf.name.charAt(0).toUpperCase()}
                      </div>
                      <div>
                        <Link
                          to={`/contacts/${inf.id}`}
                          className="text-white hover:text-violet-light transition-colors font-medium text-sm whitespace-nowrap"
                        >
                          {inf.first_name || inf.last_name
                            ? `${inf.first_name ?? ''} ${inf.last_name ?? ''}`.trim() || inf.name
                            : inf.name
                          }
                        </Link>
                        <div className="flex items-center gap-1.5 mt-0.5">
                          {inf.company && inf.company !== inf.name && (
                            <span className="text-muted text-xs truncate max-w-[120px]">{inf.company}</span>
                          )}
                          {inf.handle && (
                            <span className="text-muted/60 text-xs">@{inf.handle}</span>
                          )}
                          {inf.contact_kind === 'individual' && (
                            <span className="text-muted/40 text-xs" title="Personne physique">👤</span>
                          )}
                        </div>
                      </div>
                    </div>
                  </td>

                  {/* Type */}
                  <td className="px-3 py-3">
                    <ContactTypeBadge type={inf.contact_type} />
                  </td>

                  {/* Pays */}
                  <td className="px-3 py-3 text-sm whitespace-nowrap">
                    {inf.country
                      ? <span className="text-white/80 text-xs">{getCountryFlag(inf.country)} {inf.country}</span>
                      : <span className="text-muted/30 text-xs">—</span>
                    }
                  </td>

                  {/* Langue */}
                  <td className="px-3 py-3 text-sm whitespace-nowrap">
                    {inf.language
                      ? <span className="text-muted text-xs">{getLanguageLabel(inf.language)}</span>
                      : <span className="text-muted/30 text-xs">—</span>
                    }
                  </td>

                  {/* Email */}
                  <td className="px-3 py-3">
                    <EmailCell inf={inf} />
                  </td>

                  {/* Téléphone */}
                  <td className="px-3 py-3 whitespace-nowrap">
                    {inf.phone ? (
                      <a href={`tel:${inf.phone}`} className="text-muted text-xs hover:text-white transition-colors">
                        {inf.phone}
                      </a>
                    ) : inf.scraped_phones && inf.scraped_phones.length > 0 ? (
                      <a href={`tel:${inf.scraped_phones[0]}`} className="text-muted/60 text-xs hover:text-white transition-colors">
                        {inf.scraped_phones[0]}
                      </a>
                    ) : (
                      <span className="text-muted/30 text-xs">—</span>
                    )}
                  </td>

                  {/* Réseaux sociaux */}
                  <td className="px-3 py-3">
                    <SocialLinks inf={inf} />
                  </td>

                  {/* Formulaire de contact */}
                  <td className="px-3 py-3">
                    {formUrl ? (
                      <a
                        href={formUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 rounded text-xs transition-colors"
                        title={formUrl}
                      >
                        📝 Form
                      </a>
                    ) : mainUrl ? (
                      <a
                        href={mainUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-violet-light/60 hover:text-violet-light text-xs transition-colors truncate max-w-[80px] block"
                      >
                        {(() => {
                          try { return new URL(mainUrl).hostname.replace(/^www\./, ''); } catch { return mainUrl; }
                        })()}
                      </a>
                    ) : (
                      <span className="text-muted/30 text-xs">—</span>
                    )}
                  </td>

                  {/* Statut */}
                  <td className="px-3 py-3">
                    <StatusBadge status={inf.status} />
                  </td>

                  {/* Complétude */}
                  <td className="px-3 py-3">
                    <CompletenessBar value={inf.data_completeness ?? 0} />
                  </td>

                  {/* Dernier contact */}
                  <td className="px-3 py-3 whitespace-nowrap">
                    {inf.last_contact_at ? (
                      <span className="text-muted text-xs">
                        {new Date(inf.last_contact_at).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })}
                      </span>
                    ) : (
                      <span className="text-muted/30 text-xs">—</span>
                    )}
                  </td>

                  {/* Actions rapides */}
                  <td className="px-3 py-3">
                    <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                      {inf.backlink_synced_at && (
                        <span
                          className="px-1 py-0.5 bg-cyan-500/15 text-cyan-400 text-[10px] rounded font-bold"
                          title={`Synced to Backlink Engine: ${new Date(inf.backlink_synced_at).toLocaleDateString('fr-FR')}`}
                        >
                          BL
                        </span>
                      )}
                      {inf.pending_reminder && (
                        <span className="px-1.5 py-0.5 bg-amber/20 text-amber text-xs rounded font-mono" title="À relancer">
                          🔔
                        </span>
                      )}
                      {inf.is_verified && (
                        <span className="text-green-400/70 text-xs" title="Contact vérifié">✓</span>
                      )}
                      {inf.unsubscribed && (
                        <span className="text-red-400/70 text-xs" title="Désabonné">🚫</span>
                      )}
                      <Link
                        to={`/contacts/${inf.id}`}
                        className="text-muted hover:text-violet-light text-xs transition-colors"
                        title="Voir le détail"
                      >
                        →
                      </Link>
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {sorted.length === 0 && (
        <div className="text-center py-12 text-muted text-sm">Aucun contact trouvé.</div>
      )}

      {/* Légende complétude */}
      {sorted.length > 0 && (
        <div className="px-4 py-2 border-t border-border flex items-center gap-4 text-xs text-muted/60">
          <span>Complétude :</span>
          <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-green-500 inline-block" /> ≥75%</span>
          <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-amber-500 inline-block" /> ≥50%</span>
          <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-orange-500 inline-block" /> ≥25%</span>
          <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-red-500 inline-block" /> &lt;25%</span>
        </div>
      )}
    </div>
  );
}
