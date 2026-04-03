import React, { useEffect, useRef, useState, useContext } from 'react';
import { useSearchParams } from 'react-router-dom';
import api from '../api/client';
import { useContacts } from '../hooks/useContacts';
import ContactsTable from '../components/ContactsTable';
import FilterBar from '../components/FilterBar';
import { AuthContext } from '../hooks/useAuth';
import type { ContactCategory, ContactType, InfluenceurFilters, PipelineStatus } from '../types/influenceur';
import { CONTACT_CATEGORIES, CONTACT_TYPES, PIPELINE_STATUSES } from '../lib/constants';

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

const EMPTY_FORM: CreateForm = {
  contact_type: CONTACT_TYPES[0]?.value || 'association',
  name: '', first_name: '', last_name: '', company: '',
  email: '', phone: '', country: '', language: '',
  profile_url: '', website_url: '', linkedin_url: '',
  status: 'new', notes: '', source: 'manual',
};

interface ContactsSummary {
  total: number;
  with_email: number;
  with_phone: number;
  verified: number;
  by_category: Record<string, number>;
}

export default function Contacts() {
  const { contacts, loading, error, hasMore, load, loadMore, createContact } = useContacts();
  const { user } = useContext(AuthContext);
  const [searchParams] = useSearchParams();

  const [showCreate, setShowCreate] = useState(false);
  const [createForm, setCreateForm] = useState<CreateForm>(EMPTY_FORM);
  const [createError, setCreateError] = useState('');
  const [creating, setCreating] = useState(false);
  const [emailCheck, setEmailCheck] = useState<{ exists: boolean; id?: number; name?: string; contact_type?: string } | null>(null);
  const [emailCheckLoading, setEmailCheckLoading] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [summary, setSummary] = useState<ContactsSummary | null>(null);
  const [activeCategory, setActiveCategory] = useState<ContactCategory | null>(null);
  const [currentFilters, setCurrentFilters] = useState<InfluenceurFilters>({});
  const loaderRef = useRef<HTMLDivElement>(null);

  // Lire ?category= et ?contact_type= depuis l'URL et filtrer automatiquement
  useEffect(() => {
    const cat = searchParams.get('category') as ContactCategory | null;
    const contactType = searchParams.get('contact_type') ?? undefined;
    setActiveCategory(cat);
    const filters: InfluenceurFilters = {
      ...(cat ? { category: cat } : {}),
      ...(contactType ? { contact_type: contactType } : {}),
    };
    setCurrentFilters(filters);
    load(filters);
  }, [searchParams]);

  // Charger le résumé stats
  useEffect(() => {
    api.get<ContactsSummary>('/stats').then(({ data }) => {
      setSummary({
        total: (data as unknown as { total?: number }).total ?? 0,
        with_email: 0,
        with_phone: 0,
        verified: 0,
        by_category: {},
      });
    }).catch(() => {});
    api.get('/stats/coverage-matrix').then(({ data }) => {
      const d = data as { by_category?: Record<string, { total: number; with_email: number; with_phone: number }> };
      if (d.by_category) {
        let totalEmail = 0, totalPhone = 0, total = 0;
        const by_category: Record<string, number> = {};
        for (const [cat, v] of Object.entries(d.by_category)) {
          total += v.total;
          totalEmail += v.with_email;
          totalPhone += v.with_phone;
          by_category[cat] = v.total;
        }
        setSummary(prev => ({
          total: prev?.total ?? total,
          with_email: totalEmail,
          with_phone: totalPhone,
          verified: prev?.verified ?? 0,
          by_category,
        }));
      }
    }).catch(() => {});
  }, []);

  // Infinite scroll
  useEffect(() => {
    const observer = new IntersectionObserver(
      entries => { if (entries[0].isIntersecting && hasMore && !loading) loadMore(); },
      { threshold: 0.1 }
    );
    if (loaderRef.current) observer.observe(loaderRef.current);
    return () => observer.disconnect();
  }, [hasMore, loading, loadMore]);

  const handleFilterChange = (filters: InfluenceurFilters) => {
    setCurrentFilters(filters);
    load(filters);
  };

  const handleCategoryClick = (cat: ContactCategory | null) => {
    setActiveCategory(cat);
    const newFilters: InfluenceurFilters = { ...currentFilters, category: cat ?? undefined, contact_type: undefined };
    setCurrentFilters(newFilters);
    load(newFilters);
  };

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
      setCreateForm(EMPTY_FORM);
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      setCreateError(e.response?.data?.message ?? 'Erreur lors de la création.');
    } finally {
      setCreating(false);
    }
  };

  const handleExport = async (format: 'csv' | 'excel') => {
    setExporting(true);
    try {
      const response = await fetch(`/api/contacts/exports/${format}`, { credentials: 'include' });
      if (!response.ok) throw new Error('Export failed');
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `contacts-${new Date().toISOString().split('T')[0]}.${format === 'csv' ? 'csv' : 'xlsx'}`;
      a.click();
      URL.revokeObjectURL(url);
    } catch { /* ignore */ }
    setExporting(false);
  };

  const inp = 'w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

  const total = contacts.length;
  const withEmailCount = summary?.with_email ?? 0;
  const withPhoneCount = summary?.with_phone ?? 0;
  const verifiedCount = summary?.verified ?? 0;
  const globalTotal = summary?.total ?? 0;

  const statItems = [
    { label: 'Total', value: globalTotal.toLocaleString(), icon: '👥', color: 'text-white', bg: 'bg-surface' },
    {
      label: 'Avec email',
      value: withEmailCount > 0 ? `${withEmailCount.toLocaleString()} (${globalTotal ? Math.round(withEmailCount / globalTotal * 100) : 0}%)` : '—',
      icon: '✉️', color: 'text-cyan-400', bg: 'bg-cyan-500/10',
    },
    {
      label: 'Avec téléphone',
      value: withPhoneCount > 0 ? `${withPhoneCount.toLocaleString()} (${globalTotal ? Math.round(withPhoneCount / globalTotal * 100) : 0}%)` : '—',
      icon: '📞', color: 'text-teal-400', bg: 'bg-teal-500/10',
    },
    {
      label: 'Vérifiés',
      value: verifiedCount > 0 ? verifiedCount.toLocaleString() : '—',
      icon: '✅', color: 'text-green-400', bg: 'bg-green-500/10',
    },
  ];

  return (
    <div className="p-4 md:p-6 space-y-5">

      {/* ── En-tête ── */}
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">Base de contacts</h2>
          <p className="text-muted text-sm mt-0.5">Gestion unifiée de tous vos contacts</p>
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
            onClick={() => { setShowCreate(!showCreate); setCreateError(''); setCreateForm(EMPTY_FORM); }}
            className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
          >
            + Ajouter
          </button>
        </div>
      </div>

      {/* ── Stats bar ── */}
      {globalTotal > 0 && (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {statItems.map(s => (
            <div key={s.label} className={`${s.bg} border border-border rounded-xl px-4 py-3 flex items-center gap-3`}>
              <span className="text-xl">{s.icon}</span>
              <div>
                <div className={`font-bold text-lg leading-tight ${s.color}`}>{s.value}</div>
                <div className="text-muted text-xs">{s.label}</div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* ── Navigation par catégorie ── */}
      <div className="flex items-center gap-2 flex-wrap">
        <button
          onClick={() => handleCategoryClick(null)}
          className={`px-3 py-1.5 rounded-lg text-sm border transition-colors ${
            !activeCategory
              ? 'bg-violet/10 border-violet/30 text-violet-light font-medium'
              : 'bg-bg border-border text-muted hover:text-white'
          }`}
        >
          Tout
          {summary?.total && <span className="ml-1.5 text-xs opacity-70">{summary.total.toLocaleString()}</span>}
        </button>
        {CONTACT_CATEGORIES.map(cat => {
          const count = summary?.by_category?.[cat.value] ?? 0;
          const isActive = activeCategory === cat.value;
          return (
            <button
              key={cat.value}
              onClick={() => handleCategoryClick(cat.value)}
              className={`px-3 py-1.5 rounded-lg text-sm border transition-colors ${
                isActive
                  ? `${cat.bg} ${cat.border} ${cat.text} font-medium`
                  : 'bg-bg border-border text-muted hover:text-white'
              }`}
            >
              {cat.icon} {cat.label}
              {count > 0 && <span className="ml-1.5 text-xs opacity-70">{count.toLocaleString()}</span>}
            </button>
          );
        })}
      </div>

      {/* ── Barre de filtres ── */}
      <FilterBar
        onFilterChange={handleFilterChange}
        total={total}
        summary={summary}
      />

      {/* ── Formulaire de création ── */}
      {showCreate && (
        <form onSubmit={handleCreate} className="bg-surface border border-border rounded-xl p-5 space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="font-title font-semibold text-white text-sm">Nouveau contact</h3>
            <button type="button" onClick={() => setShowCreate(false)} className="text-muted hover:text-white text-lg leading-none">✕</button>
          </div>

          {createError && (
            <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg">{createError}</div>
          )}

          {/* Ligne 1 : Type + Statut + Source */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label className="block text-xs text-muted mb-1">Catégorie / Type *</label>
              <select value={createForm.contact_type}
                onChange={e => setCreateForm(p => ({ ...p, contact_type: e.target.value as ContactType }))}
                className={inp}>
                {CONTACT_CATEGORIES.map(cat => (
                  <optgroup key={cat.value} label={`${cat.icon} ${cat.label}`}>
                    {CONTACT_TYPES.filter(t => cat.types.includes(t.value)).map(t => (
                      <option key={t.value} value={t.value}>{t.icon} {t.label}</option>
                    ))}
                  </optgroup>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Statut</label>
              <select value={createForm.status}
                onChange={e => setCreateForm(p => ({ ...p, status: e.target.value as PipelineStatus }))}
                className={inp}>
                {PIPELINE_STATUSES.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
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

          {/* Ligne 2 : Identité */}
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <div className="md:col-span-2">
              <label className="block text-xs text-muted mb-1">Nom / Raison sociale *</label>
              <input type="text" value={createForm.name} required placeholder="Ex: ABC Consulting"
                onChange={e => setCreateForm(p => ({ ...p, name: e.target.value }))} className={inp} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Prénom (personne)</label>
              <input type="text" value={createForm.first_name} placeholder="Jean"
                onChange={e => setCreateForm(p => ({ ...p, first_name: e.target.value }))} className={inp} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Nom de famille</label>
              <input type="text" value={createForm.last_name} placeholder="Dupont"
                onChange={e => setCreateForm(p => ({ ...p, last_name: e.target.value }))} className={inp} />
            </div>
          </div>

          {/* Ligne 3 : Contact */}
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
                  <a href={`/contacts/${emailCheck.id}`} target="_blank" rel="noopener noreferrer"
                    className="underline hover:text-amber-300">
                    {emailCheck.name}
                  </a>
                  {' '}({emailCheck.contact_type})
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
              </select>
            </div>
          </div>

          {/* Ligne 4 : URLs */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label className="block text-xs text-muted mb-1">Site web / Profil URL</label>
              <input type="url" value={createForm.profile_url} placeholder="https://..."
                onChange={e => setCreateForm(p => ({ ...p, profile_url: e.target.value }))} className={inp} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Site web officiel</label>
              <input type="url" value={createForm.website_url} placeholder="https://..."
                onChange={e => setCreateForm(p => ({ ...p, website_url: e.target.value }))} className={inp} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">LinkedIn</label>
              <input type="url" value={createForm.linkedin_url} placeholder="https://linkedin.com/in/..."
                onChange={e => setCreateForm(p => ({ ...p, linkedin_url: e.target.value }))} className={inp} />
            </div>
          </div>

          {/* Notes */}
          <div>
            <label className="block text-xs text-muted mb-1">Notes internes</label>
            <textarea value={createForm.notes} rows={2} placeholder="Informations complémentaires..."
              onChange={e => setCreateForm(p => ({ ...p, notes: e.target.value }))}
              className={`${inp} resize-none`} />
          </div>

          <div className="flex gap-3 pt-1">
            <button type="submit" disabled={creating}
              className="px-5 py-2 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white text-sm rounded-lg transition-colors">
              {creating ? 'Création...' : 'Créer le contact'}
            </button>
            <button type="button" onClick={() => { setShowCreate(false); setCreateForm(EMPTY_FORM); setCreateError(''); }}
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
            <p className="text-4xl mb-3">👥</p>
            <p className="text-white font-medium">Aucun contact trouvé</p>
            <p className="text-muted text-sm mt-1">Modifiez les filtres ou ajoutez un contact.</p>
          </div>
        )}
      </div>
    </div>
  );
}
