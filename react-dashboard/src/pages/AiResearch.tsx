import React, { useEffect, useState } from 'react';
import { useAiResearch } from '../hooks/useAiResearch';
import { CONTACT_TYPES, COUNTRIES, LANGUAGES, getContactType } from '../lib/constants';
import type { ContactType, ParsedContact } from '../types/influenceur';

export default function AiResearch() {
  const { session, history, launching, importing, error, previewPrompt, launch, importContacts, importAll, loadHistory, setError } = useAiResearch();
  const [contactType, setContactType] = useState<ContactType>('influenceur');
  const [country, setCountry] = useState('Thaïlande');
  const [language, setLanguage] = useState('fr');
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [tab, setTab] = useState<'search' | 'history'>('search');
  const [view, setView] = useState<'cards' | 'table'>('table');

  // Prompt preview state
  const [promptText, setPromptText] = useState<string>('');
  const [promptVisible, setPromptVisible] = useState(false);
  const [promptLoading, setPromptLoading] = useState(false);
  const [excludedCount, setExcludedCount] = useState(0);

  useEffect(() => { loadHistory(); }, [loadHistory]);

  // When type/country/language changes, hide the prompt preview
  useEffect(() => {
    setPromptVisible(false);
    setPromptText('');
  }, [contactType, country, language]);

  const handlePreviewPrompt = async () => {
    setPromptLoading(true);
    setError(null);
    const result = await previewPrompt(contactType, country, language);
    if (result) {
      setPromptText(result.prompt);
      setExcludedCount(result.excluded_count);
      setPromptVisible(true);
    }
    setPromptLoading(false);
  };

  const handleLaunch = async () => {
    setSelected(new Set());
    setError(null);
    // Send the (potentially modified) prompt
    await launch(contactType, country, language, promptVisible ? promptText : undefined);
    setPromptVisible(false);
  };

  const toggleSelect = (index: number) => {
    setSelected(prev => {
      const next = new Set(prev);
      next.has(index) ? next.delete(index) : next.add(index);
      return next;
    });
  };

  const selectAll = () => {
    const contacts = session?.parsed_contacts ?? [];
    setSelected(new Set(contacts.map((_, i) => i)));
  };

  const handleImport = async () => {
    if (!session || selected.size === 0) return;
    const result = await importContacts(session.id, Array.from(selected));
    if (result) {
      setSelected(new Set());
    }
  };

  const handleImportAll = async () => {
    if (!session) return;
    await importAll(session.id);
    setSelected(new Set());
  };

  const ct = getContactType(contactType);
  const contacts = session?.parsed_contacts ?? [];
  const isRunning = session?.status === 'pending' || session?.status === 'running';
  const isCompleted = session?.status === 'completed';

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">🤖 Recherche IA</h2>
          <p className="text-muted text-sm mt-1">Trouvez de nouveaux contacts avec 3 recherches Claude parallèles</p>
        </div>
        <div className="flex gap-2">
          <button onClick={() => setTab('search')} className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${tab === 'search' ? 'bg-violet/20 text-violet-light' : 'text-muted hover:text-white'}`}>
            Recherche
          </button>
          <button onClick={() => setTab('history')} className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${tab === 'history' ? 'bg-violet/20 text-violet-light' : 'text-muted hover:text-white'}`}>
            Historique ({history.length})
          </button>
        </div>
      </div>

      {tab === 'search' && (
        <>
          {/* Search controls */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              {/* Contact Type */}
              <div>
                <label className="text-xs text-muted block mb-1.5">Type de contact</label>
                <select value={contactType} onChange={e => setContactType(e.target.value as ContactType)}
                  className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white focus:border-violet outline-none">
                  {CONTACT_TYPES.map(t => (
                    <option key={t.value} value={t.value}>{t.icon} {t.label}</option>
                  ))}
                </select>
              </div>

              {/* Country */}
              <div>
                <label className="text-xs text-muted block mb-1.5">Pays</label>
                <select value={country} onChange={e => setCountry(e.target.value)}
                  className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white focus:border-violet outline-none">
                  {COUNTRIES.map(c => (
                    <option key={c.name} value={c.name}>{c.flag} {c.name}</option>
                  ))}
                </select>
              </div>

              {/* Language */}
              <div>
                <label className="text-xs text-muted block mb-1.5">Langue</label>
                <select value={language} onChange={e => setLanguage(e.target.value)}
                  className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white focus:border-violet outline-none">
                  {LANGUAGES.map(l => (
                    <option key={l.code} value={l.code}>{l.flag} {l.label}</option>
                  ))}
                </select>
              </div>

              {/* Buttons */}
              <div className="flex items-end gap-2">
                {!promptVisible ? (
                  <button onClick={handlePreviewPrompt} disabled={promptLoading || launching || isRunning}
                    className="w-full bg-surface2 hover:bg-surface border border-border hover:border-violet/30 disabled:opacity-50 text-white font-semibold py-2 px-4 rounded-lg text-sm transition-colors">
                    {promptLoading ? (
                      <span className="flex items-center justify-center gap-2">
                        <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                        Chargement...
                      </span>
                    ) : (
                      <span>👁 Voir le prompt</span>
                    )}
                  </button>
                ) : (
                  <button onClick={handleLaunch} disabled={launching || isRunning || !promptText.trim()}
                    className="w-full bg-violet hover:bg-violet/80 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold py-2 px-4 rounded-lg text-sm transition-colors">
                    {launching || isRunning ? (
                      <span className="flex items-center justify-center gap-2">
                        <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                        {session?.status === 'running' ? 'Recherche en cours...' : 'Lancement...'}
                      </span>
                    ) : (
                      <span>🚀 Lancer la Recherche</span>
                    )}
                  </button>
                )}
              </div>
            </div>

            {/* Prompt preview + editor */}
            {promptVisible && !isRunning && (
              <div className="mt-4 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <h4 className="text-sm font-semibold text-white">Prompt de recherche</h4>
                    {excludedCount > 0 && (
                      <span className="text-[10px] bg-amber/20 text-amber px-2 py-0.5 rounded-full">
                        {excludedCount} contacts déjà en base seront exclus
                      </span>
                    )}
                  </div>
                  <button onClick={() => setPromptVisible(false)}
                    className="text-xs text-muted hover:text-white transition-colors">
                    ✕ Fermer
                  </button>
                </div>

                <textarea
                  value={promptText}
                  onChange={e => setPromptText(e.target.value)}
                  rows={12}
                  className="w-full bg-bg border border-border rounded-lg px-4 py-3 text-sm text-white outline-none focus:border-violet resize-y font-mono leading-relaxed"
                />

                <div className="flex items-center justify-between">
                  <p className="text-[10px] text-muted">
                    {promptText.length} caractères • Modifie librement avant de lancer • Ce prompt sera envoyé à Perplexity
                  </p>
                  <div className="flex gap-2">
                    <button onClick={handlePreviewPrompt}
                      className="text-xs text-muted hover:text-white px-3 py-1.5 rounded-lg border border-border hover:border-violet/30 transition-colors">
                      🔄 Réinitialiser
                    </button>
                  </div>
                </div>
              </div>
            )}

            {/* Current search info */}
            {session && isRunning && (
              <div className="mt-4 bg-violet/10 border border-violet/20 rounded-lg p-4">
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
                  <div>
                    <p className="text-sm font-medium text-white">3 recherches IA en parallèle...</p>
                    <p className="text-xs text-muted mt-0.5">
                      {ct.icon} {ct.label} en {country} • Analyse et déduplication automatique
                    </p>
                  </div>
                </div>
                <div className="mt-3 flex gap-4 text-xs text-muted">
                  <span>✅ Prompt généré</span>
                  <span className="text-violet-light">⏳ Perplexity — recherche web réelle...</span>
                  <span className="text-muted">⏳ Claude — analyse et structuration...</span>
                  <span className="text-muted">⏳ Déduplication...</span>
                </div>
              </div>
            )}

            {/* Error */}
            {(error || session?.status === 'failed') && (
              <div className="mt-4 bg-red-500/10 border border-red-500/20 rounded-lg p-4 text-red-400 text-sm">
                {error || session?.error_message || 'Erreur inconnue'}
              </div>
            )}
          </div>

          {/* Results */}
          {isCompleted && contacts.length > 0 && (
            <div className="space-y-4">
              {/* Results header */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <h3 className="font-title text-lg font-bold text-white">
                    {contacts.length} contacts trouvés
                  </h3>
                  {session && session.contacts_duplicates > 0 && (
                    <span className="text-xs bg-amber/20 text-amber px-2 py-0.5 rounded-full">
                      {session.contacts_duplicates} doublons filtrés
                    </span>
                  )}
                  {session && (
                    <span className="text-xs text-muted">
                      {session.tokens_used.toLocaleString()} tokens • ~${(session.cost_cents / 100).toFixed(2)}
                    </span>
                  )}
                </div>
                <div className="flex gap-2 items-center">
                  {/* View toggle */}
                  <div className="flex bg-surface2 rounded-lg p-0.5 mr-2">
                    <button onClick={() => setView('table')}
                      className={`px-2 py-1 rounded text-xs font-medium transition-colors ${view === 'table' ? 'bg-violet text-white' : 'text-muted hover:text-white'}`}>
                      ☰ Lignes
                    </button>
                    <button onClick={() => setView('cards')}
                      className={`px-2 py-1 rounded text-xs font-medium transition-colors ${view === 'cards' ? 'bg-violet text-white' : 'text-muted hover:text-white'}`}>
                      ▦ Cards
                    </button>
                  </div>

                  {/* Auto-imported badge */}
                  {session && session.contacts_imported > 0 && (
                    <span className="text-xs bg-emerald-500/20 text-emerald-400 px-2.5 py-1 rounded-full font-medium">
                      ✅ {session.contacts_imported} importés automatiquement
                    </span>
                  )}

                  {/* Green validation button (cosmetic — personal landmark) */}
                  <button onClick={() => {/* cosmetic only */}}
                    className="bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold px-4 py-1.5 rounded-lg transition-colors shadow-lg shadow-emerald-500/20">
                    ✓ Validé
                  </button>
                </div>
              </div>

              {/* ============ TABLE VIEW ============ */}
              {view === 'table' && (
                <div className="bg-surface border border-border rounded-xl overflow-hidden">
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b border-border text-left">
                          <th className="p-3 w-8">
                            <input type="checkbox" className="rounded"
                              checked={selected.size === contacts.length && contacts.length > 0}
                              onChange={() => selected.size === contacts.length ? setSelected(new Set()) : selectAll()} />
                          </th>
                          <th className="p-3 text-xs font-semibold text-muted">Nom</th>
                          <th className="p-3 text-xs font-semibold text-muted">Email</th>
                          <th className="p-3 text-xs font-semibold text-muted">Téléphone</th>
                          <th className="p-3 text-xs font-semibold text-muted">URL</th>
                          <th className="p-3 text-xs font-semibold text-muted">Plateforme</th>
                          <th className="p-3 text-xs font-semibold text-muted">Abonnés</th>
                          <th className="p-3 text-xs font-semibold text-muted text-center">Fiabilité</th>
                          <th className="p-3 text-xs font-semibold text-muted">Source</th>
                        </tr>
                      </thead>
                      <tbody>
                        {contacts.map((contact: ParsedContact, index: number) => {
                          const reliability = (contact as any).reliability_score ?? 0;
                          const reliabilityReason = (contact as any).reliability_reason ?? '';
                          const webSource = (contact as any).web_source;
                          const reliabilityColor = reliability >= 4 ? 'text-emerald-400'
                            : reliability >= 3 ? 'text-amber'
                            : reliability >= 2 ? 'text-orange-400'
                            : 'text-red-400';
                          const reliabilityBg = reliability >= 4 ? 'bg-emerald-500/20'
                            : reliability >= 3 ? 'bg-amber/20'
                            : reliability >= 2 ? 'bg-orange-500/20'
                            : 'bg-red-500/20';

                          return (
                            <tr key={index}
                              onClick={() => toggleSelect(index)}
                              className={`border-b border-border/50 cursor-pointer transition-colors ${
                                selected.has(index) ? 'bg-violet/10' : 'hover:bg-surface2'
                              }`}>
                              <td className="p-3">
                                <input type="checkbox" className="rounded"
                                  checked={selected.has(index)}
                                  onChange={() => toggleSelect(index)} />
                              </td>
                              <td className="p-3">
                                <span className="font-medium text-white">{contact.name}</span>
                              </td>
                              <td className="p-3">
                                {contact.email ? (
                                  <span className="text-cyan text-xs">✅ {contact.email}</span>
                                ) : (
                                  <span className="text-red-400/60 text-xs">❌ Manquant</span>
                                )}
                              </td>
                              <td className="p-3">
                                {contact.phone ? (
                                  <span className="text-muted text-xs">{contact.phone}</span>
                                ) : (
                                  <span className="text-muted/40 text-xs">—</span>
                                )}
                              </td>
                              <td className="p-3 max-w-[200px]">
                                {contact.profile_url ? (
                                  <a href={contact.profile_url} target="_blank" rel="noopener noreferrer"
                                    onClick={e => e.stopPropagation()}
                                    className="text-xs text-violet-light hover:underline truncate block">
                                    {contact.profile_url.replace(/^https?:\/\/(www\.)?/, '').substring(0, 35)}...
                                  </a>
                                ) : (
                                  <span className="text-red-400/40 text-xs">—</span>
                                )}
                              </td>
                              <td className="p-3">
                                {contact.platforms?.filter(p => p !== 'website').map(p => (
                                  <span key={p} className="text-[10px] bg-violet/10 text-violet-light px-1.5 py-0.5 rounded mr-1">
                                    {p}
                                  </span>
                                ))}
                                {contact.platforms?.includes('website') && contact.profile_url && (
                                  <a href={contact.profile_url} target="_blank" rel="noopener noreferrer"
                                    onClick={e => e.stopPropagation()}
                                    className="text-[10px] bg-cyan/10 text-cyan px-1.5 py-0.5 rounded hover:underline">
                                    🌐 site web
                                  </a>
                                )}
                                {contact.platforms?.includes('website') && !contact.profile_url && (
                                  <span className="text-[10px] text-muted/40">🌐 website</span>
                                )}
                              </td>
                              <td className="p-3 text-xs text-muted text-right font-mono">
                                {contact.followers ? contact.followers.toLocaleString() : '—'}
                              </td>
                              <td className="p-3 text-center">
                                <span className={`text-[10px] px-1.5 py-0.5 rounded-full font-bold ${reliabilityBg} ${reliabilityColor}`}
                                  title={reliabilityReason}>
                                  {reliability}/5
                                </span>
                              </td>
                              <td className="p-3 max-w-[150px]">
                                {webSource ? (
                                  <a href={webSource} target="_blank" rel="noopener noreferrer"
                                    onClick={e => e.stopPropagation()}
                                    className="text-[10px] text-muted hover:text-cyan truncate block">
                                    {webSource.replace(/^https?:\/\/(www\.)?/, '').substring(0, 25)}...
                                  </a>
                                ) : (
                                  <span className="text-muted/30 text-[10px]">—</span>
                                )}
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>

                  {/* Missing contacts warning at bottom of table */}
                  {contacts.some((c: any) => !c.has_email && !c.has_phone) && (
                    <div className="px-4 py-2.5 bg-amber/5 border-t border-amber/20">
                      <p className="text-[11px] text-amber">
                        ⚠️ {contacts.filter((c: any) => !c.has_email && !c.has_phone).length} contact(s) sans aucun moyen de contact — il faudra chercher leurs coordonnées manuellement
                      </p>
                    </div>
                  )}
                </div>
              )}

              {/* ============ CARDS VIEW ============ */}
              {view === 'cards' && <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                {contacts.map((contact: ParsedContact, index: number) => {
                  const reliability = (contact as any).reliability_score ?? 0;
                  const reliabilityReason = (contact as any).reliability_reason ?? '';
                  const hasEmail = !!(contact.email);
                  const hasPhone = !!(contact.phone);
                  const hasUrl = !!(contact.profile_url);
                  const webSource = (contact as any).web_source;

                  const reliabilityColor = reliability >= 4 ? 'bg-emerald-500/20 text-emerald-400'
                    : reliability >= 3 ? 'bg-amber/20 text-amber'
                    : reliability >= 2 ? 'bg-orange-500/20 text-orange-400'
                    : 'bg-red-500/20 text-red-400';

                  const reliabilityLabel = reliability >= 4 ? 'Fiable'
                    : reliability >= 3 ? 'Partiel'
                    : reliability >= 2 ? 'Faible'
                    : 'Très faible';

                  return (
                    <div key={index}
                      onClick={() => toggleSelect(index)}
                      className={`bg-surface border rounded-xl p-4 cursor-pointer transition-all ${
                        selected.has(index)
                          ? 'border-violet ring-1 ring-violet/30'
                          : 'border-border hover:border-violet/30'
                      }`}>
                      {/* Header: name + reliability + checkbox */}
                      <div className="flex items-start justify-between gap-2">
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-2">
                            <p className="text-sm font-semibold text-white truncate">{contact.name}</p>
                            <span className={`text-[9px] px-1.5 py-0.5 rounded-full font-bold ${reliabilityColor}`}
                              title={reliabilityReason}>
                              {reliability}/5 {reliabilityLabel}
                            </span>
                          </div>

                          {/* Contact info with visual indicators */}
                          <div className="flex items-center gap-3 mt-1.5">
                            {hasEmail ? (
                              <span className="text-xs text-cyan truncate max-w-[180px]" title={contact.email!}>
                                ✅ {contact.email}
                              </span>
                            ) : (
                              <span className="text-xs text-red-400/70">❌ Email manquant</span>
                            )}
                          </div>
                          <div className="flex items-center gap-3 mt-0.5">
                            {hasPhone ? (
                              <span className="text-xs text-muted">📞 {contact.phone}</span>
                            ) : (
                              <span className="text-[11px] text-muted/50">📞 Non trouvé</span>
                            )}
                          </div>
                        </div>
                        <div className={`w-5 h-5 rounded border-2 flex items-center justify-center flex-shrink-0 ${
                          selected.has(index) ? 'bg-violet border-violet' : 'border-gray-600'
                        }`}>
                          {selected.has(index) && <span className="text-white text-xs">✓</span>}
                        </div>
                      </div>

                      {/* URL */}
                      {hasUrl ? (
                        <a href={contact.profile_url!} target="_blank" rel="noopener noreferrer"
                          onClick={e => e.stopPropagation()}
                          className="text-xs text-violet-light hover:underline mt-2 block truncate">
                          🔗 {contact.profile_url}
                        </a>
                      ) : (
                        <p className="text-[11px] text-red-400/50 mt-2">🔗 Pas d'URL trouvée</p>
                      )}

                      {/* Web source */}
                      {webSource && (
                        <a href={webSource} target="_blank" rel="noopener noreferrer"
                          onClick={e => e.stopPropagation()}
                          className="text-[10px] text-muted hover:text-cyan mt-1 block truncate">
                          📄 Source: {webSource}
                        </a>
                      )}

                      {/* Tags row */}
                      <div className="flex items-center gap-1.5 mt-2 flex-wrap">
                        {contact.followers != null && contact.followers > 0 && (
                          <span className="text-[10px] bg-surface2 text-muted px-1.5 py-0.5 rounded">
                            {contact.followers.toLocaleString()} abonnés
                          </span>
                        )}
                        {contact.platforms?.filter(p => p !== 'website').map(p => (
                          <span key={p} className="text-[10px] bg-violet/10 text-violet-light px-1.5 py-0.5 rounded">
                            {p}
                          </span>
                        ))}
                        {contact.platforms?.includes('website') && hasUrl && (
                          <a href={contact.profile_url!} target="_blank" rel="noopener noreferrer"
                            onClick={e => e.stopPropagation()}
                            className="text-[10px] bg-cyan/10 text-cyan px-1.5 py-0.5 rounded hover:underline">
                            🌐 site web
                          </a>
                        )}
                      </div>

                      {/* Missing data warning bubble */}
                      {(!hasEmail && !hasPhone) && (
                        <div className="mt-2 bg-red-500/10 border border-red-500/20 rounded-lg px-2.5 py-1.5">
                          <p className="text-[10px] text-red-400 font-medium">⚠️ Aucun moyen de contact trouvé</p>
                          <p className="text-[9px] text-red-400/60">Il faudra rechercher l'email manuellement sur leur site</p>
                        </div>
                      )}

                      {/* Reliability detail on hover area */}
                      {reliabilityReason && (
                        <p className="text-[9px] text-muted/50 mt-1.5 truncate" title={reliabilityReason}>
                          ℹ️ {reliabilityReason}
                        </p>
                      )}
                    </div>
                  );
                })}
              </div>}
            </div>
          )}

          {isCompleted && contacts.length === 0 && (
            <div className="bg-surface border border-border rounded-xl p-12 text-center">
              <p className="text-4xl mb-3">🔍</p>
              <p className="text-muted">Aucun nouveau contact trouvé pour cette recherche.</p>
              <p className="text-xs text-muted mt-1">Tous les contacts trouvés existaient déjà dans la base.</p>
            </div>
          )}

          {/* Auto-imported notification */}
          {session && session.contacts_imported > 0 && (
            <div className="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-4 text-emerald-400 text-sm">
              ✅ {session.contacts_imported} contacts importés automatiquement dans ta base — visible dans l'onglet Contacts
            </div>
          )}
        </>
      )}

      {/* History tab */}
      {tab === 'history' && (
        <div className="space-y-3">
          {history.length === 0 && (
            <div className="bg-surface border border-border rounded-xl p-12 text-center">
              <p className="text-4xl mb-3">📭</p>
              <p className="text-muted">Aucune recherche effectuée</p>
            </div>
          )}
          {history.map(s => {
            const t = getContactType(s.contact_type);
            return (
              <div key={s.id} className="bg-surface border border-border rounded-xl p-4 flex items-center gap-4">
                <span className="text-2xl">{t.icon}</span>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-white">{t.label} — {s.country}</p>
                  <p className="text-xs text-muted mt-0.5">
                    {new Date(s.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                  </p>
                </div>
                <div className="flex items-center gap-4 text-xs">
                  <span className="text-muted">{s.contacts_found} trouvés</span>
                  <span className="text-emerald-400">{s.contacts_imported} importés</span>
                  <span className="text-amber">{s.contacts_duplicates} doublons</span>
                  <span className={`px-2 py-0.5 rounded-full font-medium ${
                    s.status === 'completed' ? 'bg-emerald-500/20 text-emerald-400' :
                    s.status === 'failed' ? 'bg-red-500/20 text-red-400' :
                    'bg-amber/20 text-amber'
                  }`}>
                    {s.status}
                  </span>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
