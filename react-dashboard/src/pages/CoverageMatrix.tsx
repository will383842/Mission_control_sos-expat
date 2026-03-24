import React, { useEffect, useState } from 'react';
import api from '../api/client';
import { CONTACT_TYPES } from '../lib/constants';

interface MatrixRow {
  contact_type: string;
  country: string;
  language: string;
  searched: boolean;
  last_searched_at: string | null;
  search_found: number;
  search_imported: number;
  contacts: number;
  with_email: number;
  with_phone: number;
  with_form: number;
  email_pct: number;
  contactable_pct: number;
}

interface TypeSummary {
  type: string;
  countries: number;
  countries_searched: number;
  coverage_pct: number;
  total_contacts: number;
  with_email: number;
  email_pct: number;
}

export default function CoverageMatrix() {
  const [matrix, setMatrix] = useState<MatrixRow[]>([]);
  const [summary, setSummary] = useState<TypeSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [typeFilter, setTypeFilter] = useState('');
  const [langFilter, setLangFilter] = useState('');
  const [showSearchedOnly, setShowSearchedOnly] = useState(false);

  const fetchData = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (typeFilter) params.set('type', typeFilter);
      if (langFilter) params.set('lang', langFilter);
      const { data } = await api.get(`/stats/coverage-matrix?${params}`);
      setMatrix(data.matrix || []);
      setSummary(data.summary || []);
    } catch { /* ignore */ }
    setLoading(false);
  };

  useEffect(() => { fetchData(); }, [typeFilter, langFilter]);

  const filtered = showSearchedOnly ? matrix.filter(r => r.searched) : matrix;

  // Group by country for the selected type
  const grouped = typeFilter
    ? filtered
    : filtered.reduce<MatrixRow[]>((acc, row) => {
        const existing = acc.find(r => r.country === row.country && r.language === row.language);
        if (existing) {
          existing.contacts += row.contacts;
          existing.with_email += row.with_email;
          existing.with_form += row.with_form;
          existing.searched = existing.searched || row.searched;
        } else {
          acc.push({ ...row });
        }
        return acc;
      }, []);

  const totalContacts = filtered.reduce((s, r) => s + r.contacts, 0);
  const totalEmails = filtered.reduce((s, r) => s + r.with_email, 0);
  const totalSearched = filtered.filter(r => r.searched).length;
  const totalCombos = filtered.length;

  // Active contact types for filter
  const activeTypes = CONTACT_TYPES.filter(t =>
    summary.some(s => s.type === t.value) || matrix.some(m => m.contact_type === t.value)
  );

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-title font-bold text-white">Avancement</h1>
        <p className="text-muted text-sm mt-1">Couverture par type de contact, pays et langue</p>
      </div>

      {/* Summary cards by type */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
        {summary.map(s => {
          const ct = CONTACT_TYPES.find(t => t.value === s.type);
          const isActive = typeFilter === s.type;
          return (
            <button
              key={s.type}
              onClick={() => setTypeFilter(isActive ? '' : s.type)}
              className={`bg-surface border rounded-xl p-4 text-left transition-all ${
                isActive ? 'border-violet ring-1 ring-violet/30' : 'border-border hover:border-violet/30'
              }`}
            >
              <div className="flex items-center gap-2 mb-2">
                <span className="text-lg">{ct?.icon || '📋'}</span>
                <span className="text-xs font-medium text-white truncate">{ct?.label || s.type}</span>
              </div>
              <div className="space-y-1">
                <div className="flex justify-between text-xs">
                  <span className="text-muted">Pays</span>
                  <span className="text-white font-mono">{s.countries_searched}/{s.countries}</span>
                </div>
                <div className="w-full bg-surface2 rounded-full h-1.5">
                  <div
                    className={`h-1.5 rounded-full ${s.coverage_pct === 100 ? 'bg-emerald-500' : s.coverage_pct > 50 ? 'bg-amber' : 'bg-red-500'}`}
                    style={{ width: `${Math.max(s.coverage_pct, 2)}%` }}
                  />
                </div>
                <div className="flex justify-between text-xs">
                  <span className="text-muted">Contacts</span>
                  <span className="text-white font-mono">{s.total_contacts}</span>
                </div>
                <div className="flex justify-between text-xs">
                  <span className="text-muted">Emails</span>
                  <span className={`font-mono ${s.email_pct >= 50 ? 'text-emerald-400' : 'text-amber'}`}>
                    {s.with_email} ({s.email_pct}%)
                  </span>
                </div>
              </div>
            </button>
          );
        })}
      </div>

      {/* Filters bar */}
      <div className="flex items-center gap-4 flex-wrap">
        <select
          value={typeFilter}
          onChange={e => setTypeFilter(e.target.value)}
          className="bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white focus:border-violet outline-none"
        >
          <option value="">Tous les types</option>
          {activeTypes.map(t => (
            <option key={t.value} value={t.value}>{t.icon} {t.label}</option>
          ))}
        </select>

        <select
          value={langFilter}
          onChange={e => setLangFilter(e.target.value)}
          className="bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white focus:border-violet outline-none"
        >
          <option value="">Toutes les langues</option>
          <option value="fr">FR Francais</option>
          <option value="en">EN English</option>
          <option value="es">ES Espanol</option>
          <option value="pt">PT Portugues</option>
          <option value="de">DE Deutsch</option>
          <option value="ar">AR Arabe</option>
        </select>

        <label className="flex items-center gap-2 text-sm text-muted cursor-pointer">
          <input
            type="checkbox"
            checked={showSearchedOnly}
            onChange={e => setShowSearchedOnly(e.target.checked)}
            className="rounded border-gray-600 bg-bg text-violet focus:ring-violet"
          />
          Recherchés uniquement
        </label>

        <div className="ml-auto flex gap-4 text-xs text-muted">
          <span>{totalCombos} combinaisons</span>
          <span>{totalSearched} recherchées</span>
          <span className="text-white font-medium">{totalContacts} contacts</span>
          <span className="text-cyan">{totalEmails} emails</span>
        </div>
      </div>

      {/* Matrix table */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left">
                {!typeFilter && <th className="px-4 py-3 text-xs font-medium text-muted">Type</th>}
                <th className="px-4 py-3 text-xs font-medium text-muted">Pays</th>
                <th className="px-4 py-3 text-xs font-medium text-muted">Langue</th>
                <th className="px-4 py-3 text-xs font-medium text-muted text-center">Recherché</th>
                <th className="px-4 py-3 text-xs font-medium text-muted text-right">Contacts</th>
                <th className="px-4 py-3 text-xs font-medium text-muted text-right">Emails</th>
                <th className="px-4 py-3 text-xs font-medium text-muted text-right">Formulaires</th>
                <th className="px-4 py-3 text-xs font-medium text-muted text-center">Contactable</th>
                <th className="px-4 py-3 text-xs font-medium text-muted">Derniere recherche</th>
              </tr>
            </thead>
            <tbody>
              {(typeFilter ? filtered : grouped).map((row, i) => {
                const ct = CONTACT_TYPES.find(t => t.value === row.contact_type);
                const contactable = row.with_email + row.with_form;
                const contactablePct = row.contacts > 0 ? Math.round(contactable / row.contacts * 100) : 0;

                return (
                  <tr key={i} className="border-b border-border/50 hover:bg-surface2 transition-colors">
                    {!typeFilter && (
                      <td className="px-4 py-2.5">
                        <span className="text-xs">{ct?.icon} {ct?.label || row.contact_type}</span>
                      </td>
                    )}
                    <td className="px-4 py-2.5 text-white font-medium">{row.country}</td>
                    <td className="px-4 py-2.5 text-muted uppercase">{row.language}</td>
                    <td className="px-4 py-2.5 text-center">
                      {row.searched ? (
                        <span className="inline-block w-5 h-5 bg-emerald-500/20 text-emerald-400 rounded-full text-xs leading-5 font-bold">✓</span>
                      ) : (
                        <span className="inline-block w-5 h-5 bg-red-500/10 text-red-400/50 rounded-full text-xs leading-5">✗</span>
                      )}
                    </td>
                    <td className="px-4 py-2.5 text-right font-mono">
                      {row.contacts > 0 ? (
                        <span className="text-white">{row.contacts}</span>
                      ) : (
                        <span className="text-muted/30">0</span>
                      )}
                    </td>
                    <td className="px-4 py-2.5 text-right font-mono">
                      {row.with_email > 0 ? (
                        <span className="text-cyan">{row.with_email}</span>
                      ) : (
                        <span className="text-muted/30">0</span>
                      )}
                    </td>
                    <td className="px-4 py-2.5 text-right font-mono">
                      {row.with_form > 0 ? (
                        <span className="text-blue-400">{row.with_form}</span>
                      ) : (
                        <span className="text-muted/30">0</span>
                      )}
                    </td>
                    <td className="px-4 py-2.5 text-center">
                      {row.contacts > 0 ? (
                        <div className="flex items-center gap-2 justify-center">
                          <div className="w-16 bg-surface2 rounded-full h-1.5">
                            <div
                              className={`h-1.5 rounded-full ${contactablePct === 100 ? 'bg-emerald-500' : contactablePct >= 50 ? 'bg-amber' : 'bg-red-500'}`}
                              style={{ width: `${Math.max(contactablePct, 4)}%` }}
                            />
                          </div>
                          <span className={`text-xs font-mono ${contactablePct === 100 ? 'text-emerald-400' : contactablePct >= 50 ? 'text-amber' : 'text-red-400'}`}>
                            {contactablePct}%
                          </span>
                        </div>
                      ) : (
                        <span className="text-muted/30 text-xs">—</span>
                      )}
                    </td>
                    <td className="px-4 py-2.5 text-xs text-muted">
                      {row.last_searched_at
                        ? new Date(row.last_searched_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
                        : '—'}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        {filtered.length === 0 && (
          <div className="text-center py-12 text-muted text-sm">Aucune donnee pour ces filtres.</div>
        )}
      </div>
    </div>
  );
}
