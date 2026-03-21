import React, { useEffect, useRef, useState, useContext } from 'react';
import { useInfluenceurs } from '../hooks/useInfluenceurs';
import InfluenceurCard from '../components/InfluenceurCard';
import InfluenceurTable from '../components/InfluenceurTable';
import FilterSidebar from '../components/FilterSidebar';
import { AuthContext } from '../hooks/useAuth';
import type { ContactType, InfluenceurFilters, Platform, PipelineStatus } from '../types/influenceur';
import { CONTACT_TYPES, PIPELINE_STATUSES } from '../lib/constants';

const PLATFORM_OPTIONS: { value: Platform; label: string }[] = [
  { value: 'instagram', label: 'Instagram' },
  { value: 'tiktok', label: 'TikTok' },
  { value: 'youtube', label: 'YouTube' },
  { value: 'linkedin', label: 'LinkedIn' },
  { value: 'x', label: 'X' },
  { value: 'facebook', label: 'Facebook' },
  { value: 'pinterest', label: 'Pinterest' },
  { value: 'podcast', label: 'Podcast' },
  { value: 'blog', label: 'Blog' },
  { value: 'newsletter', label: 'Newsletter' },
  { value: 'website', label: 'Website' },
];

const TYPES_WITH_PLATFORMS: ContactType[] = ['influenceur', 'tiktoker', 'youtuber', 'instagramer', 'blogger', 'group_admin'];

type CreateForm = {
  contact_type: ContactType;
  name: string;
  handle: string;
  platforms: Platform[];
  primary_platform: Platform;
  followers: string;
  email: string;
  phone: string;
  country: string;
  language: string;
  niche: string;
  profile_url: string;
  status: PipelineStatus;
  notes: string;
};

const EMPTY_FORM: CreateForm = {
  contact_type: CONTACT_TYPES[0].value,
  name: '', handle: '', platforms: ['instagram'], primary_platform: 'instagram',
  followers: '', email: '', phone: '', country: '', language: '',
  niche: '', profile_url: '', status: 'new', notes: '',
};

export default function Influenceurs() {
  const { influenceurs, loading, error, hasMore, load, loadMore, createInfluenceur } = useInfluenceurs();
  const { user } = useContext(AuthContext);
  const [view, setView] = useState<'cards' | 'table'>('table');
  const [showCreate, setShowCreate] = useState(false);
  const [createForm, setCreateForm] = useState<CreateForm>(EMPTY_FORM);
  const [createError, setCreateError] = useState('');
  const [creating, setCreating] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);
  const loaderRef = useRef<HTMLDivElement>(null);

  useEffect(() => { load(); }, []);

  // Infinite scroll observer
  useEffect(() => {
    const observer = new IntersectionObserver(
      entries => { if (entries[0].isIntersecting && hasMore && !loading) loadMore(); },
      { threshold: 0.1 }
    );
    if (loaderRef.current) observer.observe(loaderRef.current);
    return () => observer.disconnect();
  }, [hasMore, loading, loadMore]);

  const handleFilterChange = (filters: InfluenceurFilters) => {
    load(filters);
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreateError('');
    setCreating(true);
    try {
      const payload: Record<string, unknown> = {
        contact_type: createForm.contact_type,
        name: createForm.name,
        handle: createForm.handle || null,
        email: createForm.email || null,
        phone: createForm.phone || null,
        country: createForm.country || null,
        language: createForm.language || null,
        niche: createForm.niche || null,
        profile_url: createForm.profile_url || null,
        status: createForm.status,
        notes: createForm.notes || null,
        followers: createForm.followers ? Number(createForm.followers) : null,
      };
      if (TYPES_WITH_PLATFORMS.includes(createForm.contact_type)) {
        payload.platforms = createForm.platforms;
        payload.primary_platform = createForm.primary_platform;
      }
      await createInfluenceur(payload);
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
      const response = await fetch(`/api/influenceurs/exports/${format}`, {
        credentials: 'include',
      });
      if (!response.ok) throw new Error('Export failed');
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `influenceurs-${new Date().toISOString().split('T')[0]}.${format === 'csv' ? 'csv' : 'xlsx'}`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      console.error('Export failed');
    } finally {
      setExporting(false);
    }
  };

  const togglePlatform = (p: Platform) => {
    setCreateForm(prev => {
      const has = prev.platforms.includes(p);
      const platforms = has ? prev.platforms.filter(x => x !== p) : [...prev.platforms, p];
      if (platforms.length === 0) return prev;
      const primary = platforms.includes(prev.primary_platform) ? prev.primary_platform : platforms[0];
      return { ...prev, platforms, primary_platform: primary };
    });
  };

  const inputClass = 'w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

  return (
    <div className="flex h-screen overflow-hidden">
      {/* Desktop FilterSidebar */}
      <FilterSidebar onFilterChange={handleFilterChange} />

      {/* Mobile FilterSidebar drawer */}
      {mobileFiltersOpen && (
        <div className="md:hidden">
          <div className="fixed inset-0 z-40 bg-black/60" onClick={() => setMobileFiltersOpen(false)} />
          <div className="fixed inset-0 z-50">
            <FilterSidebar onFilterChange={handleFilterChange} onClose={() => setMobileFiltersOpen(false)} />
          </div>
        </div>
      )}

      <div className="flex-1 overflow-auto p-4 md:p-6">
        {/* Header */}
        <div className="flex flex-wrap items-center justify-between gap-3 mb-6">
          <div>
            <h2 className="font-title text-2xl font-bold text-white">Contacts</h2>
            <p className="text-muted text-sm mt-1">{influenceurs.length} chargé{influenceurs.length !== 1 ? 's' : ''}</p>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            {/* Mobile filter button */}
            <button
              onClick={() => setMobileFiltersOpen(true)}
              className="md:hidden px-3 py-2 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors"
            >
              Filtres
            </button>

            {/* Export buttons (admin only) */}
            {user?.role === 'admin' && (
              <div className="flex gap-1">
                <button
                  onClick={() => handleExport('csv')}
                  disabled={exporting}
                  className="px-3 py-2 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors disabled:opacity-50"
                >
                  CSV
                </button>
                <button
                  onClick={() => handleExport('excel')}
                  disabled={exporting}
                  className="px-3 py-2 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors disabled:opacity-50"
                >
                  Excel
                </button>
              </div>
            )}

            {/* Toggle vue */}
            <div className="flex bg-surface border border-border rounded-lg p-1">
              <button
                onClick={() => setView('cards')}
                className={`px-3 py-1.5 rounded text-sm transition-colors ${view === 'cards' ? 'bg-violet/20 text-violet-light' : 'text-muted hover:text-white'}`}
              >
                Cartes
              </button>
              <button
                onClick={() => setView('table')}
                className={`px-3 py-1.5 rounded text-sm transition-colors ${view === 'table' ? 'bg-violet/20 text-violet-light' : 'text-muted hover:text-white'}`}
              >
                Tableau
              </button>
            </div>
            <button
              onClick={() => setShowCreate(!showCreate)}
              className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
            >
              + Ajouter
            </button>
          </div>
        </div>

        {/* Formulaire de création */}
        {showCreate && (
          <form onSubmit={handleCreate} className="bg-surface border border-border rounded-xl p-5 mb-6 space-y-4">
            <h3 className="font-title font-semibold text-white">Nouveau contact</h3>

            {createError && (
              <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg">{createError}</div>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
              <div>
                <label className="block text-xs text-muted mb-1.5">Type de contact *</label>
                <select
                  value={createForm.contact_type}
                  onChange={e => setCreateForm(p => ({ ...p, contact_type: e.target.value as ContactType }))}
                  className={inputClass}
                >
                  {CONTACT_TYPES.map(t => <option key={t.value} value={t.value}>{t.icon} {t.label}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-xs text-muted mb-1.5">Nom *</label>
                <input
                  type="text"
                  value={createForm.name}
                  onChange={e => setCreateForm(p => ({ ...p, name: e.target.value }))}
                  required
                  placeholder="Nom complet"
                  className={inputClass}
                />
              </div>
              <div>
                <label className="block text-xs text-muted mb-1.5">Handle</label>
                <input
                  type="text"
                  value={createForm.handle}
                  onChange={e => setCreateForm(p => ({ ...p, handle: e.target.value }))}
                  placeholder="@handle"
                  className={inputClass}
                />
              </div>
              <div>
                <label className="block text-xs text-muted mb-1.5">Statut</label>
                <select
                  value={createForm.status}
                  onChange={e => setCreateForm(p => ({ ...p, status: e.target.value as PipelineStatus }))}
                  className={inputClass}
                >
                  {PIPELINE_STATUSES.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-xs text-muted mb-1.5">Email</label>
                <input
                  type="email"
                  value={createForm.email}
                  onChange={e => setCreateForm(p => ({ ...p, email: e.target.value }))}
                  placeholder="email@example.com"
                  className={inputClass}
                />
              </div>
              <div>
                <label className="block text-xs text-muted mb-1.5">Téléphone</label>
                <input
                  type="text"
                  value={createForm.phone}
                  onChange={e => setCreateForm(p => ({ ...p, phone: e.target.value }))}
                  placeholder="+33..."
                  className={inputClass}
                />
              </div>
              <div>
                <label className="block text-xs text-muted mb-1.5">Followers</label>
                <input
                  type="number"
                  value={createForm.followers}
                  onChange={e => setCreateForm(p => ({ ...p, followers: e.target.value }))}
                  placeholder="10000"
                  min={0}
                  className={inputClass}
                />
              </div>
              <div>
                <label className="block text-xs text-muted mb-1.5">Pays</label>
                <input
                  type="text"
                  value={createForm.country}
                  onChange={e => setCreateForm(p => ({ ...p, country: e.target.value }))}
                  placeholder="France"
                  className={inputClass}
                />
              </div>
              <div>
                <label className="block text-xs text-muted mb-1.5">Langue</label>
                <input
                  type="text"
                  value={createForm.language}
                  onChange={e => setCreateForm(p => ({ ...p, language: e.target.value }))}
                  placeholder="FR"
                  className={inputClass}
                />
              </div>
              <div>
                <label className="block text-xs text-muted mb-1.5">Niche</label>
                <input
                  type="text"
                  value={createForm.niche}
                  onChange={e => setCreateForm(p => ({ ...p, niche: e.target.value }))}
                  placeholder="Voyage, Expat..."
                  className={inputClass}
                />
              </div>
            </div>

            {/* Plateformes (only for influenceur/blogger/group_admin) */}
            {TYPES_WITH_PLATFORMS.includes(createForm.contact_type) && <div>
              <label className="block text-xs text-muted mb-2">Plateformes *</label>
              <div className="flex flex-wrap gap-2">
                {PLATFORM_OPTIONS.map(p => (
                  <button
                    key={p.value}
                    type="button"
                    onClick={() => togglePlatform(p.value)}
                    className={`px-3 py-1.5 rounded-lg text-xs transition-colors border ${
                      createForm.platforms.includes(p.value)
                        ? 'bg-violet/20 text-violet-light border-violet/40'
                        : 'bg-surface2 text-muted border-border hover:text-white'
                    }`}
                  >
                    {p.label}
                    {createForm.primary_platform === p.value && createForm.platforms.includes(p.value) && (
                      <span className="ml-1 text-amber">*</span>
                    )}
                  </button>
                ))}
              </div>
              {createForm.platforms.length > 1 && (
                <div className="mt-2">
                  <label className="text-xs text-muted mr-2">Principale :</label>
                  <select
                    value={createForm.primary_platform}
                    onChange={e => setCreateForm(p => ({ ...p, primary_platform: e.target.value as Platform }))}
                    className="bg-surface2 border border-border rounded px-2 py-1 text-xs text-white focus:outline-none focus:border-violet"
                  >
                    {createForm.platforms.map(pl => (
                      <option key={pl} value={pl}>{PLATFORM_OPTIONS.find(o => o.value === pl)?.label}</option>
                    ))}
                  </select>
                </div>
              )}
            </div>}

            <div>
              <label className="block text-xs text-muted mb-1.5">URL du profil / site web</label>
              <input
                type="url"
                value={createForm.profile_url}
                onChange={e => setCreateForm(p => ({ ...p, profile_url: e.target.value }))}
                placeholder="https://instagram.com/..."
                className={inputClass}
              />
            </div>

            <div>
              <label className="block text-xs text-muted mb-1.5">Notes</label>
              <textarea
                value={createForm.notes}
                onChange={e => setCreateForm(p => ({ ...p, notes: e.target.value }))}
                rows={2}
                placeholder="Notes internes..."
                className={`${inputClass} resize-none`}
              />
            </div>

            <div className="flex gap-3">
              <button
                type="submit"
                disabled={creating}
                className="px-4 py-2 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white text-sm rounded-lg transition-colors"
              >
                {creating ? 'Création...' : 'Créer'}
              </button>
              <button
                type="button"
                onClick={() => { setShowCreate(false); setCreateForm(EMPTY_FORM); setCreateError(''); }}
                className="px-4 py-2 text-muted hover:text-white text-sm transition-colors"
              >
                Annuler
              </button>
            </div>
          </form>
        )}

        {/* Error */}
        {error && (
          <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg mb-4">{error}</div>
        )}

        {/* Content */}
        {view === 'cards' ? (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {influenceurs.map(inf => (
              <InfluenceurCard key={inf.id} influenceur={inf} />
            ))}
          </div>
        ) : (
          <InfluenceurTable influenceurs={influenceurs} />
        )}

        {/* Loader infinite scroll */}
        <div ref={loaderRef} className="py-6 flex justify-center">
          {loading && (
            <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
          )}
          {!loading && !hasMore && influenceurs.length > 0 && (
            <p className="text-muted text-sm">Tous les contacts sont chargés.</p>
          )}
          {!loading && influenceurs.length === 0 && (
            <div className="text-center py-12">
              <p className="text-4xl mb-3">👥</p>
              <p className="text-white font-medium">Aucun contact trouvé</p>
              <p className="text-muted text-sm mt-1">Modifiez les filtres ou ajoutez un contact.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
