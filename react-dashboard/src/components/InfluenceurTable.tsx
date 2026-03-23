import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import type { Influenceur } from '../types/influenceur';
import ContactTypeBadge from './ContactTypeBadge';
import PlatformBadge from './PlatformBadge';
import StatusBadge from './StatusBadge';
import { getCountryFlag, getLanguageLabel } from '../lib/constants';

type SortKey = 'name' | 'followers' | 'status' | 'last_contact_at';

interface Props {
  influenceurs: Influenceur[];
}

export default function InfluenceurTable({ influenceurs }: Props) {
  const [sortKey, setSortKey] = useState<SortKey>('name');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');

  const handleSort = (key: SortKey) => {
    if (sortKey === key) setSortDir(d => d === 'asc' ? 'desc' : 'asc');
    else { setSortKey(key); setSortDir('asc'); }
  };

  const sorted = [...influenceurs].sort((a, b) => {
    const av = a[sortKey] ?? '';
    const bv = b[sortKey] ?? '';
    let cmp: number;
    if (sortKey === 'followers') {
      cmp = (Number(av) || 0) - (Number(bv) || 0);
    } else if (sortKey === 'last_contact_at') {
      cmp = new Date(av as string).getTime() - new Date(bv as string).getTime();
    } else {
      cmp = String(av).localeCompare(String(bv));
    }
    return sortDir === 'asc' ? cmp : -cmp;
  });

  const Th = ({ label, field }: { label: string; field?: SortKey }) => (
    <th
      className={`text-left text-xs text-muted font-medium px-4 py-3 whitespace-nowrap ${field ? 'cursor-pointer hover:text-white' : ''}`}
      onClick={() => field && handleSort(field)}
    >
      {label} {field && sortKey === field && (sortDir === 'asc' ? '↑' : '↓')}
    </th>
  );

  return (
    <div className="bg-surface border border-border rounded-xl overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead>
            <tr className="border-b border-border">
              <Th label="Nom" field="name" />
              <Th label="Type" />
              <Th label="Pays" />
              <Th label="Langue" />
              <Th label="Statut" field="status" />
              <Th label="Site web" />
              <Th label="Email" />
              <Th label="Tél" />
              <Th label="Dernier contact" field="last_contact_at" />
              <Th label="Rappel" />
            </tr>
          </thead>
          <tbody>
            {sorted.map(inf => (
              <tr key={inf.id} className="border-b border-border last:border-0 hover:bg-surface2 transition-colors">
                <td className="px-4 py-3">
                  <Link to={`/influenceurs/${inf.id}`} className="text-white hover:text-violet-light transition-colors font-medium text-sm whitespace-nowrap">
                    {inf.name}
                    {inf.handle && <span className="text-muted font-normal"> @{inf.handle}</span>}
                  </Link>
                </td>
                <td className="px-4 py-3"><ContactTypeBadge type={inf.contact_type} /></td>
                <td className="px-4 py-3 text-sm whitespace-nowrap">
                  {inf.country ? <span>{getCountryFlag(inf.country)} {inf.country}</span> : <span className="text-muted">—</span>}
                </td>
                <td className="px-4 py-3 text-sm whitespace-nowrap">
                  {inf.language ? <span>{getLanguageLabel(inf.language)}</span> : <span className="text-muted">—</span>}
                </td>
                <td className="px-4 py-3"><StatusBadge status={inf.status} /></td>
                <td className="px-4 py-3 text-sm whitespace-nowrap">
                  {(() => {
                    const url = inf.website_url || inf.profile_url;
                    if (!url) return <span className="text-muted/30 text-xs">—</span>;
                    let hostname = url;
                    try { hostname = new URL(url).hostname.replace(/^www\./, ''); } catch {}
                    return <a href={url} target="_blank" rel="noopener noreferrer" className="text-violet-light hover:underline text-xs">{hostname}</a>;
                  })()}
                </td>
                <td className="px-4 py-3 text-sm whitespace-nowrap">
                  {inf.email ? (
                    <a href={`mailto:${inf.email}`} className="text-cyan hover:underline text-xs">{inf.email}</a>
                  ) : (() => {
                    const formUrl = (inf.scraped_social as Record<string, unknown> | null)?.['_contact_form_url'] as string | undefined;
                    return formUrl ? (
                      <a href={formUrl} target="_blank" rel="noopener noreferrer" className="text-blue-400/80 hover:text-blue-400 text-xs">{'📝'} formulaire</a>
                    ) : (
                      <span className="text-red-400/50 text-xs">manquant</span>
                    );
                  })()}
                </td>
                <td className="px-4 py-3 text-sm whitespace-nowrap">
                  {inf.phone ? <a href={`tel:${inf.phone}`} className="text-muted text-xs">{inf.phone}</a> : <span className="text-muted/30 text-xs">—</span>}
                </td>
                <td className="px-4 py-3 text-muted text-sm whitespace-nowrap">
                  {inf.last_contact_at ? new Date(inf.last_contact_at).toLocaleDateString('fr-FR') : '—'}
                </td>
                <td className="px-4 py-3">
                  {inf.pending_reminder && (
                    <span className="px-2 py-0.5 bg-amber/20 text-amber text-xs rounded-full font-mono">RELANCER</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {sorted.length === 0 && (
        <div className="text-center py-12 text-muted text-sm">Aucun résultat.</div>
      )}
    </div>
  );
}
