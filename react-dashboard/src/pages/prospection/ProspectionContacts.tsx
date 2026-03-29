import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';
import { CONTACT_TYPES } from '../../lib/constants';

interface Contact {
  id: number; name: string; email: string | null; phone: string | null;
  contact_type: string; country: string; language: string;
  email_verified_status: string; quality_score: number;
  website_url: string | null; profile_url: string | null;
}

export default function ProspectionContacts() {
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [loading, setLoading] = useState(true);
  const [typeFilter, setTypeFilter] = useState('');
  const [countryFilter, setCountryFilter] = useState('');
  const [total, setTotal] = useState(0);

  const fetchContacts = async () => {
    setLoading(true);
    try {
      const params: Record<string, string> = { per_page: '50' };
      if (typeFilter) params.contact_type = typeFilter;
      if (countryFilter) params.country = countryFilter;
      // Only show contacts with verified email (eligible for outreach)
      params.has_email = 'true';
      const { data } = await api.get('/contacts', { params });
      setContacts(data.data || []);
      setTotal(data.total || data.data?.length || 0);
    } catch { /* ignore */ }
    setLoading(false);
  };

  useEffect(() => { fetchContacts(); }, [typeFilter, countryFilter]);

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center gap-3">
        <Link to="/prospection" className="text-muted hover:text-white transition-colors text-sm">← Prospection</Link>
        <h1 className="text-2xl font-title font-bold text-white">Contacts eligibles</h1>
      </div>

      {/* Filters */}
      <div className="flex items-center gap-3 flex-wrap">
        <select value={typeFilter} onChange={e => setTypeFilter(e.target.value)}
          className="bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none">
          <option value="">Tous les types</option>
          {CONTACT_TYPES.map(t => <option key={t.value} value={t.value}>{t.icon} {t.label}</option>)}
        </select>
        <input value={countryFilter} onChange={e => setCountryFilter(e.target.value)} placeholder="Pays..."
          className="bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none w-48" />
        <span className="text-xs text-muted ml-auto">{total} contacts avec email</span>
      </div>

      {/* Table */}
      {loading ? (
        <div className="flex justify-center py-12"><div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>
      ) : (
        <div className="bg-surface border border-border rounded-xl overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border">
                {['Nom', 'Type', 'Pays', 'Email', 'Verifie', 'Score', 'Site web'].map(h => (
                  <th key={h} className="text-left text-[10px] text-muted font-medium uppercase px-4 py-3">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {contacts.map(c => (
                <tr key={c.id} className="border-b border-border/50 hover:bg-surface2">
                  <td className="px-4 py-3">
                    <Link to={`/contacts/${c.id}`} className="text-white text-xs font-medium hover:text-violet-light">{c.name}</Link>
                  </td>
                  <td className="px-4 py-3 text-xs text-muted">{c.contact_type}</td>
                  <td className="px-4 py-3 text-xs text-muted">{c.country}</td>
                  <td className="px-4 py-3">
                    {c.email ? <span className="text-cyan text-xs">{c.email}</span> : <span className="text-muted/30 text-xs">—</span>}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-0.5 text-[10px] rounded-full ${
                      c.email_verified_status === 'verified' ? 'bg-emerald-500/20 text-emerald-400' :
                      c.email_verified_status === 'invalid' ? 'bg-red-500/20 text-red-400' :
                      c.email_verified_status === 'risky' ? 'bg-amber/20 text-amber' :
                      'bg-gray-500/20 text-muted'
                    }`}>{c.email_verified_status}</span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`font-mono text-xs ${c.quality_score >= 75 ? 'text-emerald-400' : c.quality_score >= 50 ? 'text-amber' : 'text-red-400'}`}>
                      {c.quality_score}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    {(c.website_url || c.profile_url) ? (
                      <a href={c.website_url || c.profile_url!} target="_blank" rel="noopener noreferrer"
                        className="text-violet-light text-xs hover:underline truncate block max-w-[150px]">
                        {(c.website_url || c.profile_url || '').replace(/^https?:\/\/(www\.)?/, '').slice(0, 30)}
                      </a>
                    ) : <span className="text-muted/30 text-xs">—</span>}
                  </td>
                </tr>
              ))}
              {contacts.length === 0 && (
                <tr><td colSpan={7} className="text-center py-12 text-muted text-sm">Aucun contact eligible avec ces filtres</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
