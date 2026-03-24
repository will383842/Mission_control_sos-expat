import React, { useEffect, useRef, useState, useContext } from 'react';
import { useInfluenceurs } from '../hooks/useInfluenceurs';
import InfluenceurTable from '../components/InfluenceurTable';
import FilterBar from '../components/FilterBar';
import { AuthContext } from '../hooks/useAuth';
import type { ContactType, InfluenceurFilters, PipelineStatus } from '../types/influenceur';
import { CONTACT_TYPES, PIPELINE_STATUSES } from '../lib/constants';

type CreateForm = {
  contact_type: ContactType;
  name: string;
  email: string;
  phone: string;
  country: string;
  language: string;
  profile_url: string;
  status: PipelineStatus;
  notes: string;
};

const EMPTY_FORM: CreateForm = {
  contact_type: CONTACT_TYPES[0]?.value || 'association',
  name: '', email: '', phone: '', country: '', language: '',
  profile_url: '', status: 'new', notes: '',
};

export default function Influenceurs() {
  const { influenceurs, loading, error, hasMore, load, loadMore, createInfluenceur } = useInfluenceurs();
  const { user } = useContext(AuthContext);
  const [showCreate, setShowCreate] = useState(false);
  const [createForm, setCreateForm] = useState<CreateForm>(EMPTY_FORM);
  const [createError, setCreateError] = useState('');
  const [creating, setCreating] = useState(false);
  const [exporting, setExporting] = useState(false);
  const loaderRef = useRef<HTMLDivElement>(null);

  useEffect(() => { load(); }, []);

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
    load(filters);
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreateError('');
    setCreating(true);
    try {
      await createInfluenceur({
        contact_type: createForm.contact_type,
        name: createForm.name,
        email: createForm.email || null,
        phone: createForm.phone || null,
        country: createForm.country || null,
        language: createForm.language || null,
        profile_url: createForm.profile_url || null,
        status: createForm.status,
        notes: createForm.notes || null,
      });
      setShowCreate(false);
      setCreateForm(EMPTY_FORM);
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      setCreateError(e.response?.data?.message ?? 'Erreur lors de la creation.');
    } finally {
      setCreating(false);
    }
  };

  const handleExport = async (format: 'csv' | 'excel') => {
    setExporting(true);
    try {
      const response = await fetch(`/api/influenceurs/exports/${format}`, { credentials: 'include' });
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

  const inputClass = 'w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

  return (
    <div className="p-4 md:p-6 space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between gap-3">
        <h2 className="font-title text-2xl font-bold text-white">Contacts</h2>
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
          <button onClick={() => setShowCreate(!showCreate)}
            className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
            + Ajouter
          </button>
        </div>
      </div>

      {/* Filter bar */}
      <FilterBar onFilterChange={handleFilterChange} total={influenceurs.length} />

      {/* Create form */}
      {showCreate && (
        <form onSubmit={handleCreate} className="bg-surface border border-border rounded-xl p-5 space-y-4">
          <h3 className="font-title font-semibold text-white text-sm">Nouveau contact</h3>
          {createError && (
            <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg">{createError}</div>
          )}
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <div>
              <label className="block text-xs text-muted mb-1">Type *</label>
              <select value={createForm.contact_type}
                onChange={e => setCreateForm(p => ({ ...p, contact_type: e.target.value as ContactType }))}
                className={inputClass}>
                {CONTACT_TYPES.map(t => <option key={t.value} value={t.value}>{t.icon} {t.label}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Nom *</label>
              <input type="text" value={createForm.name} required placeholder="Nom complet"
                onChange={e => setCreateForm(p => ({ ...p, name: e.target.value }))} className={inputClass} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Email</label>
              <input type="email" value={createForm.email} placeholder="email@..."
                onChange={e => setCreateForm(p => ({ ...p, email: e.target.value }))} className={inputClass} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Telephone</label>
              <input type="text" value={createForm.phone} placeholder="+33..."
                onChange={e => setCreateForm(p => ({ ...p, phone: e.target.value }))} className={inputClass} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Pays</label>
              <input type="text" value={createForm.country} placeholder="France"
                onChange={e => setCreateForm(p => ({ ...p, country: e.target.value }))} className={inputClass} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Langue</label>
              <input type="text" value={createForm.language} placeholder="FR"
                onChange={e => setCreateForm(p => ({ ...p, language: e.target.value }))} className={inputClass} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">URL</label>
              <input type="url" value={createForm.profile_url} placeholder="https://..."
                onChange={e => setCreateForm(p => ({ ...p, profile_url: e.target.value }))} className={inputClass} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Statut</label>
              <select value={createForm.status}
                onChange={e => setCreateForm(p => ({ ...p, status: e.target.value as PipelineStatus }))}
                className={inputClass}>
                {PIPELINE_STATUSES.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
              </select>
            </div>
          </div>
          <div>
            <label className="block text-xs text-muted mb-1">Notes</label>
            <textarea value={createForm.notes} rows={2} placeholder="Notes internes..."
              onChange={e => setCreateForm(p => ({ ...p, notes: e.target.value }))}
              className={`${inputClass} resize-none`} />
          </div>
          <div className="flex gap-3">
            <button type="submit" disabled={creating}
              className="px-4 py-2 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white text-sm rounded-lg transition-colors">
              {creating ? 'Creation...' : 'Creer'}
            </button>
            <button type="button" onClick={() => { setShowCreate(false); setCreateForm(EMPTY_FORM); setCreateError(''); }}
              className="px-4 py-2 text-muted hover:text-white text-sm transition-colors">
              Annuler
            </button>
          </div>
        </form>
      )}

      {/* Error */}
      {error && (
        <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg">{error}</div>
      )}

      {/* Table */}
      <InfluenceurTable influenceurs={influenceurs} />

      {/* Loader */}
      <div ref={loaderRef} className="py-4 flex justify-center">
        {loading && <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />}
        {!loading && !hasMore && influenceurs.length > 0 && (
          <p className="text-muted text-xs">Tous les contacts sont charges.</p>
        )}
        {!loading && influenceurs.length === 0 && (
          <div className="text-center py-12">
            <p className="text-4xl mb-3">👥</p>
            <p className="text-white font-medium">Aucun contact trouve</p>
            <p className="text-muted text-sm mt-1">Modifiez les filtres ou ajoutez un contact.</p>
          </div>
        )}
      </div>
    </div>
  );
}
