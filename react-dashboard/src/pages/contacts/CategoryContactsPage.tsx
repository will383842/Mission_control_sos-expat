import React, { useEffect, useRef, useState, useContext, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import api from '../../api/client';
import { useContacts } from '../../hooks/useContacts';
import { getLanguageFlag, getCountryFlag } from '../../lib/constants';
import ContactsTable from '../../components/ContactsTable';
import FilterBar from '../../components/FilterBar';
import { AuthContext } from '../../hooks/useAuth';
import { CONTACT_CATEGORIES, CONTACT_TYPES } from '../../lib/constants';
import type { ContactCategory, ContactType, InfluenceurFilters, PipelineStatus } from '../../types/influenceur';

export interface SubTypeChip {
  value: ContactType;
  label: string;
  icon?: string;
}

interface Props {
  category: ContactCategory;
  contactType?: ContactType;
  /** Chips cliquables pour filtrer par sous-type via URL ?type=X (remplace les routes dédiées). */
  subTypes?: SubTypeChip[];
}

type CreateForm = {
  contact_type: ContactType;
  name: string;
  first_name: string;
  last_name: string;
  company: string;
  email: string;
  phone: string;
  country: string;
  language: string;
  profile_url: string;
  website_url: string;
  linkedin_url: string;
  status: PipelineStatus;
  notes: string;
  source: string;
};

const EMPTY_FORM = (defaultType: ContactType): CreateForm => ({
  contact_type: defaultType,
  name: '', first_name: '', last_name: '', company: '',
  email: '', phone: '', country: '', language: '',
  profile_url: '', website_url: '', linkedin_url: '',
  status: 'new', notes: '', source: 'manual',
});

/**
 * Page générique par catégorie de contacts.
 * Chaque route dédiée (ex: /contacts/institutionnel) monte une instance FRAÎCHE
 * de ce composant, garantissant un filtre toujours correct sans bug de stale state.
 */
export default function CategoryContactsPage({ category, contactType: contactTypeProp, subTypes }: Props) {
  const [searchParams, setSearchParams] = useSearchParams();
  // Si l'URL contient ?type=X et qu'il fait partie des subTypes autorisés, il prime sur la prop.
  // Sinon on retombe sur contactTypeProp (comportement historique des routes dédiées).
  const typeFromUrl = searchParams.get('type') as ContactType | null;
  const isValidUrlType = typeFromUrl && subTypes?.some(s => s.value === typeFromUrl);
  const contactType: ContactType | undefined = isValidUrlType ? typeFromUrl : contactTypeProp;

  const { contacts, loading, error, hasMore, load, loadMore, createContact } = useContacts();
  const { user } = useContext(AuthContext);
  const loaderRef = useRef<HTMLDivElement>(null);

  const [showCreate, setShowCreate] = useState(false);
  const [createForm, setCreateForm] = useState<CreateForm>(
    EMPTY_FORM(CONTACT_CATEGORIES.find(c => c.value === category)?.types[0] as ContactType ?? 'association')
  );
  const [createError, setCreateError] = useState('');
  const [creating, setCreating] = useState(false);
  const [emailCheck, setEmailCheck] = useState<{ exists: boolean; id?: number; name?: string; contact_type?: string } | null>(null);
  const [emailCheckLoading, setEmailCheckLoading] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [categoryTotal, setCategoryTotal] = useState<number | null>(null);
  const [withEmail, setWithEmail]         = useState<number | null>(null);
  const [byLanguage, setByLanguage]       = useState<Record<string, number>>({});
  const [byCountry, setByCountry]         = useState<Record<string, number>>({});

  // Ref stable vers load — évite la dépendance cyclique dans useEffect
  const loadRef = useRef(load);
  useEffect(() => { loadRef.current = load; });

  // ── Chargement : se déclenche au montage ET si category/contactType changent ─
  // (double sécurité : même si React Router réutilise le composant sans remount)
  useEffect(() => {
    const filters: InfluenceurFilters = {
      category,
      ...(contactType ? { contact_type: contactType } : {}),
    };
    loadRef.current(filters);
    setCategoryTotal(null);
    setWithEmail(null);
    setByLanguage({});
    setByCountry({});

    // Récupère les stats complètes pour cette catégorie
    const params: Record<string, string> = { category };
    if (contactType) params.contact_type = contactType;
    api.get('/stats/category-count', { params })
      .then(({ data }) => {
        const d = data as { count?: number; with_email?: number; by_language?: Record<string, number>; by_country?: Record<string, number> };
        if (d.count !== undefined)      setCategoryTotal(d.count);
        if (d.with_email !== undefined) setWithEmail(d.with_email);
        if (d.by_language)              setByLanguage(d.by_language);
        if (d.by_country)               setByCountry(d.by_country);
      })
      .catch(() => {});
  }, [category, contactType]); // eslint-disable-line react-hooks/exhaustive-deps

  // ── Infinite scroll ────────────────────────────────────────────────────────
  useEffect(() => {
    const observer = new IntersectionObserver(
      entries => { if (entries[0].isIntersecting && hasMore && !loading) loadMore(); },
      { threshold: 0.1 }
    );
    if (loaderRef.current) observer.observe(loaderRef.current);
    return () => observer.disconnect();
  }, [hasMore, loading, loadMore]);

  // ── Filtres additionnels (catégorie toujours verrouillée) ─────────────────
  const STORAGE_KEY = `cat_filters_${category}${contactType ? '_' + contactType : ''}`;

  // Filtres additionnels sauvegardés (sans category/contact_type qui sont structurels)
  const savedFilters = useMemo<InfluenceurFilters>(() => {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}'); } catch { return {}; }
  }, [STORAGE_KEY]);

  const handleFilterChange = (newFilters: InfluenceurFilters) => {
    // Sauvegarder les filtres additionnels (sans les clés structurelles)
    const { category: _c, contact_type: _t, ...extra } = newFilters as InfluenceurFilters & { category?: string; contact_type?: string };
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(extra)); } catch { /* */ }
    load({
      ...newFilters,
      category,
      ...(contactType ? { contact_type: contactType } : {}),
    });
  };

  // ── Export ─────────────────────────────────────────────────────────────────
  const handleExport = async (format: 'csv' | 'excel') => {
    setExporting(true);
    try {
      const qs = new URLSearchParams({ category, ...(contactType ? { contact_type: contactType } : {}) });
      const response = await fetch(`/api/contacts/exports/${format}?${qs}`, { credentials: 'include' });
      if (!response.ok) throw new Error('Export failed');
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `contacts-${category}-${new Date().toISOString().split('T')[0]}.${format === 'csv' ? 'csv' : 'xlsx'}`;
      a.click();
      URL.revokeObjectURL(url);
    } catch { /* ignore */ }
    setExporting(false);
  };

  // ── Check email doublon ───────────────────────────────────────────────────
  const handleEmailBlur = async (email: string) => {
    if (!email || !email.includes('@')) { setEmailCheck(null); return; }
    setEmailCheckLoading(true);
    try {
      const res = await api.get('/contacts/check-email', { params: { email } });
      setEmailCheck(res.data);
    } catch {
      setEmailCheck(null);
    } finally {
      setEmailCheckLoading(false);
    }
  };

  // ── Création ───────────────────────────────────────────────────────────────
  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreateError('');
    setCreating(true);
    try {
      await createContact({
        contact_type: createForm.contact_type,
        name: createForm.name,
        first_name: createForm.first_name || null,
        last_name: createForm.last_name || null,
        company: createForm.company || null,
        email: createForm.email || null,
        phone: createForm.phone || null,
        country: createForm.country || null,
        language: createForm.language || null,
        profile_url: createForm.profile_url || null,
        website_url: createForm.website_url || null,
        linkedin_url: createForm.linkedin_url || null,
        status: createForm.status,
        notes: createForm.notes || null,
        source: createForm.source || 'manual',
      });
      setShowCreate(false);
      setCreateForm(EMPTY_FORM(createForm.contact_type));
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      setCreateError(e.response?.data?.message ?? 'Erreur lors de la création.');
    } finally {
      setCreating(false);
    }
  };

  // ── Données d'affichage ────────────────────────────────────────────────────
  const catConfig = CONTACT_CATEGORIES.find(c => c.value === category);
  const typeConfig = contactType ? CONTACT_TYPES.find(t => t.value === contactType) : null;
  const pageTitle  = typeConfig ? `${typeConfig.icon} ${typeConfig.label}` : `${catConfig?.icon ?? '👥'} ${catConfig?.label ?? category}`;
  const catTypes   = catConfig?.types ?? [];

  const inp = 'w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

  return (
    <div className="p-4 md:p-6 space-y-5">

      {/* ── En-tête ── */}
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <div>
          <h2 className={`font-title text-2xl font-bold ${catConfig?.text ?? 'text-white'}`}>
            {pageTitle}
          </h2>
          <p className="text-muted text-sm mt-0.5">
            {typeConfig
              ? `Sous-catégorie de ${catConfig?.label} · ${contacts.length} contacts chargés`
              : `${contacts.length} contacts chargés${categoryTotal ? ` sur ${categoryTotal.toLocaleString()} au total` : ''}`
            }
          </p>
          {/* ── Stats rapides ── */}
          {categoryTotal !== null && (
            <div className="flex flex-wrap items-center gap-3 mt-2">
              <span className="inline-flex items-center gap-1.5 text-xs bg-surface2 border border-border rounded-full px-2.5 py-1">
                <span className="text-white font-semibold">{categoryTotal.toLocaleString()}</span>
                <span className="text-muted">contacts</span>
              </span>
              {withEmail !== null && (
                <span className="inline-flex items-center gap-1.5 text-xs bg-surface2 border border-border rounded-full px-2.5 py-1">
                  <span>📧</span>
                  <span className="text-cyan-400 font-semibold">{withEmail.toLocaleString()}</span>
                  <span className="text-muted">avec email</span>
                  {categoryTotal > 0 && (
                    <span className="text-muted">({Math.round(withEmail / categoryTotal * 100)}%)</span>
                  )}
                </span>
              )}
              {Object.entries(byLanguage).slice(0, 4).map(([lang, count]) => (
                <span key={lang} className="inline-flex items-center gap-1 text-xs bg-surface2 border border-border rounded-full px-2.5 py-1">
                  <span>{getLanguageFlag(lang)}</span>
                  <span className="text-white font-medium">{count.toLocaleString()}</span>
                </span>
              ))}
              {Object.entries(byCountry).slice(0, 4).map(([country, count]) => (
                <span key={country} className="inline-flex items-center gap-1 text-xs bg-surface2 border border-border rounded-full px-2.5 py-1">
                  <span>{getCountryFlag(country)}</span>
                  <span className="text-white font-medium">{country}</span>
                  <span className="text-muted">·{count}</span>
                </span>
              ))}
            </div>
          )}
        </div>
        <div className="flex items-center gap-2">
          {user?.role === 'admin' && (
            <>
              <button onClick={() => handleExport('csv')} disabled={exporting}
                className="px-3 py-1.5 bg-surface2 text-muted hover:text-white text-xs rounded-lg border border-border transition-colors disabled:opacity-50">
                CSV
              </button>
              <button onClick={() => handleExport('excel')} disabled={exporting}
                className="px-3 py-1.5 bg-surface2 text-muted hover:text-white text-xs rounded-lg border border-border transition-colors disabled:opacity-50">
                Excel
              </button>
            </>
          )}
          <button
            onClick={() => { setShowCreate(!showCreate); setCreateError(''); }}
            className={`px-4 py-1.5 text-white text-sm rounded-lg transition-colors ${catConfig?.bg ?? 'bg-violet'} border ${catConfig?.border ?? 'border-violet/40'} hover:opacity-90`}
          >
            + Ajouter
          </button>
        </div>
      </div>

      {/* ── Types de contacts dans cette catégorie ── */}
      {!contactType && catTypes.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {catTypes.map(t => {
            const tc = CONTACT_TYPES.find(x => x.value === t);
            if (!tc) return null;
            return (
              <span key={t} className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs rounded-full border ${catConfig?.bg ?? 'bg-surface'} ${catConfig?.border ?? 'border-border'} ${catConfig?.text ?? 'text-muted'}`}>
                {tc.icon} {tc.label}
              </span>
            );
          })}
        </div>
      )}

      {/* ── Chips sous-types (remplacent les routes dédiées /contacts/youtubeurs, etc.) ── */}
      {subTypes && subTypes.length > 0 && (
        <div className="flex flex-wrap gap-2 mb-3">
          <button
            type="button"
            onClick={() => setSearchParams({}, { replace: true })}
            className={`text-xs px-3 py-1.5 rounded-full border transition ${
              !contactType
                ? 'bg-violet text-white border-violet'
                : 'bg-surface border-border text-muted hover:text-white hover:border-violet'
            }`}
          >
            Tous
          </button>
          {subTypes.map(st => (
            <button
              key={st.value}
              type="button"
              onClick={() => setSearchParams({ type: st.value }, { replace: true })}
              className={`text-xs px-3 py-1.5 rounded-full border transition ${
                contactType === st.value
                  ? 'bg-violet text-white border-violet'
                  : 'bg-surface border-border text-muted hover:text-white hover:border-violet'
              }`}
            >
              {st.icon ? `${st.icon} ${st.label}` : st.label}
            </button>
          ))}
        </div>
      )}

      {/* ── Barre de filtres (catégorie verrouillée) ── */}
      <FilterBar
        key={`${category}-${contactType ?? ''}`}
        initialFilters={{ category, ...(contactType ? { contact_type: contactType } : {}), ...savedFilters }}
        onFilterChange={handleFilterChange}
        lockedCategory={category}
        total={contacts.length}
      />

      {/* ── Formulaire de création ── */}
      {showCreate && (
        <form onSubmit={handleCreate} className="bg-surface border border-border rounded-xl p-5 space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="font-title font-semibold text-white text-sm">Nouveau contact — {catConfig?.label}</h3>
            <button type="button" onClick={() => setShowCreate(false)} className="text-muted hover:text-white text-lg leading-none">✕</button>
          </div>

          {createError && (
            <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg">{createError}</div>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label className="block text-xs text-muted mb-1">Type de contact *</label>
              <select value={createForm.contact_type}
                onChange={e => setCreateForm(p => ({ ...p, contact_type: e.target.value as ContactType }))}
                className={inp}>
                {catTypes.map(t => {
                  const tc = CONTACT_TYPES.find(x => x.value === t);
                  return tc ? <option key={t} value={t}>{tc.icon} {tc.label}</option> : null;
                })}
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Statut</label>
              <select value={createForm.status}
                onChange={e => setCreateForm(p => ({ ...p, status: e.target.value as PipelineStatus }))}
                className={inp}>
                <option value="new">Nouveau</option>
                <option value="prospect">Prospect</option>
                <option value="contacted">Contacté</option>
                <option value="negotiating">En négociation</option>
                <option value="active">Actif</option>
                <option value="refused">Refusé</option>
                <option value="inactive">Inactif</option>
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Source</label>
              <select value={createForm.source}
                onChange={e => setCreateForm(p => ({ ...p, source: e.target.value }))}
                className={inp}>
                <option value="manual">✏️ Ajout manuel</option>
                <option value="scraping">🕷️ Scraping</option>
                <option value="ai_research">🤖 Recherche IA</option>
                <option value="import">📥 Import</option>
                <option value="directory">📚 Annuaire</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <div className="md:col-span-2">
              <label className="block text-xs text-muted mb-1">Nom / Raison sociale *</label>
              <input type="text" value={createForm.name} required placeholder="Ex: ABC Consulting"
                onChange={e => setCreateForm(p => ({ ...p, name: e.target.value }))} className={inp} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Prénom</label>
              <input type="text" value={createForm.first_name} placeholder="Jean"
                onChange={e => setCreateForm(p => ({ ...p, first_name: e.target.value }))} className={inp} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Nom de famille</label>
              <input type="text" value={createForm.last_name} placeholder="Dupont"
                onChange={e => setCreateForm(p => ({ ...p, last_name: e.target.value }))} className={inp} />
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <div>
              <label className="block text-xs text-muted mb-1">Email</label>
              <input type="email" value={createForm.email} placeholder="contact@..."
                onChange={e => { setCreateForm(p => ({ ...p, email: e.target.value })); setEmailCheck(null); }}
                onBlur={e => handleEmailBlur(e.target.value)}
                className={`${inp} ${emailCheck?.exists ? 'border-amber-500/60' : ''}`} />
              {emailCheckLoading && <p className="text-xs text-muted mt-1">Vérification...</p>}
              {emailCheck?.exists && (
                <p className="text-xs text-amber-400 mt-1">
                  ⚠ Existe déjà :{' '}
                  <a href={`/contacts/${emailCheck.id}`} target="_blank" rel="noopener noreferrer" className="underline hover:text-amber-300">
                    {emailCheck.name}
                  </a> ({emailCheck.contact_type})
                </p>
              )}
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Téléphone</label>
              <input type="text" value={createForm.phone} placeholder="+33 6..."
                onChange={e => setCreateForm(p => ({ ...p, phone: e.target.value }))} className={inp} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Pays</label>
              <input type="text" value={createForm.country} placeholder="France"
                onChange={e => setCreateForm(p => ({ ...p, country: e.target.value }))} className={inp} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Langue</label>
              <select value={createForm.language} onChange={e => setCreateForm(p => ({ ...p, language: e.target.value }))} className={inp}>
                <option value="">— Sélectionner —</option>
                <option value="fr">🇫🇷 Français</option>
                <option value="en">🇬🇧 English</option>
                <option value="de">🇩🇪 Deutsch</option>
                <option value="es">🇪🇸 Español</option>
                <option value="pt">🇵🇹 Português</option>
                <option value="ar">🇸🇦 العربية</option>
                <option value="ru">🇷🇺 Русский</option>
                <option value="zh">🇨🇳 中文</option>
                <option value="it">🇮🇹 Italiano</option>
                <option value="nl">🇳🇱 Nederlands</option>
                <option value="pl">🇵🇱 Polski</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="block text-xs text-muted mb-1">Site web / Profil URL</label>
              <input type="url" value={createForm.profile_url} placeholder="https://..."
                onChange={e => setCreateForm(p => ({ ...p, profile_url: e.target.value }))} className={inp} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">LinkedIn</label>
              <input type="url" value={createForm.linkedin_url} placeholder="https://linkedin.com/in/..."
                onChange={e => setCreateForm(p => ({ ...p, linkedin_url: e.target.value }))} className={inp} />
            </div>
          </div>

          <div>
            <label className="block text-xs text-muted mb-1">Notes</label>
            <textarea value={createForm.notes} rows={2} placeholder="Informations complémentaires..."
              onChange={e => setCreateForm(p => ({ ...p, notes: e.target.value }))}
              className={`${inp} resize-none`} />
          </div>

          <div className="flex gap-3 pt-1">
            <button type="submit" disabled={creating}
              className="px-5 py-2 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white text-sm rounded-lg transition-colors">
              {creating ? 'Création...' : 'Créer le contact'}
            </button>
            <button type="button" onClick={() => { setShowCreate(false); setCreateError(''); }}
              className="px-4 py-2 text-muted hover:text-white text-sm transition-colors">
              Annuler
            </button>
          </div>
        </form>
      )}

      {/* ── Erreur ── */}
      {error && (
        <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg">{error}</div>
      )}

      {/* ── Table ── */}
      <ContactsTable influenceurs={contacts} />

      {/* ── Infinite scroll loader ── */}
      <div ref={loaderRef} className="py-4 flex justify-center">
        {loading && <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />}
        {!loading && !hasMore && contacts.length > 0 && (
          <p className="text-muted text-xs">Tous les contacts sont chargés ({contacts.length})</p>
        )}
        {!loading && contacts.length === 0 && (
          <div className="text-center py-12">
            <p className="text-4xl mb-3">{typeConfig?.icon ?? catConfig?.icon ?? '👥'}</p>
            <p className="text-white font-medium">Aucun contact trouvé</p>
            <p className="text-muted text-sm mt-1">Modifiez les filtres ou ajoutez un contact.</p>
          </div>
        )}
      </div>
    </div>
  );
}
