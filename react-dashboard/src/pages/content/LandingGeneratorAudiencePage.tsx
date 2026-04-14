import React, { useState, useCallback, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import type { AudienceType } from '../../api/contentApi';
import { fetchLandings, deleteLanding } from '../../api/contentApi';
import type { LandingPage, PaginatedResponse, ContentStatus } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import LandingCountryQueue from './LandingCountryQueue';
import LandingGenerationConfig from './LandingGenerationConfig';
import LandingPageMiniCard from './LandingPageMiniCard';

// ── Types ──────────────────────────────────────────────────────────────

interface Props {
  audienceType: AudienceType;
  label: string;
  icon: string;
  description: string;
}

type Tab = 'queue' | 'config' | 'landings';

type AugmentedLP = LandingPage & {
  audience_type?: string;
  template_id?: string;
  country_code?: string;
  generation_source?: string;
};

const STATUS_OPTIONS: { value: string; label: string }[] = [
  { value: '',          label: 'Tous les statuts' },
  { value: 'draft',     label: 'Brouillon' },
  { value: 'generating',label: 'Génération' },
  { value: 'review',    label: 'En revue' },
  { value: 'published', label: 'Publié' },
  { value: 'archived',  label: 'Archivé' },
];

const LANG_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'Toutes les langues' },
  { value: 'fr', label: 'Français' },
  { value: 'en', label: 'Anglais' },
  { value: 'es', label: 'Espagnol' },
  { value: 'de', label: 'Allemand' },
  { value: 'ar', label: 'Arabe' },
  { value: 'zh', label: 'Chinois' },
  { value: 'hi', label: 'Hindi' },
  { value: 'pt', label: 'Portugais' },
  { value: 'ru', label: 'Russe' },
];

// ── Landing Pages Tab ──────────────────────────────────────────────────

function LandingsTab({ audienceType }: { audienceType: AudienceType }) {
  const navigate = useNavigate();
  const [landings, setLandings]   = useState<AugmentedLP[]>([]);
  const [loading, setLoading]     = useState(true);
  const [page, setPage]           = useState(1);
  const [lastPage, setLastPage]   = useState(1);
  const [total, setTotal]         = useState(0);
  const [search, setSearch]       = useState('');
  const [language, setLanguage]   = useState('');
  const [status, setStatus]       = useState('');
  const [toDelete, setToDelete]   = useState<AugmentedLP | null>(null);

  const loadLandings = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = {
        page,
        audience_type: audienceType,
        per_page: 30,
      };
      if (search.trim())  params.search   = search.trim();
      if (language)       params.language = language;
      if (status)         params.status   = status;

      const res  = await fetchLandings(params);
      const data = res.data as unknown as PaginatedResponse<AugmentedLP>;
      setLandings(data.data ?? []);
      setTotal(data.total ?? 0);
      setLastPage(data.last_page ?? 1);
    } catch {
      toast.error('Erreur lors du chargement des landing pages');
    } finally {
      setLoading(false);
    }
  }, [audienceType, page, search, language, status]);

  useEffect(() => { loadLandings(); }, [loadLandings]);

  const handleDelete = async (id: number) => {
    try {
      await deleteLanding(id);
      toast.success('Landing page supprimée');
      loadLandings();
    } catch {
      toast.error('Erreur lors de la suppression');
    }
    setToDelete(null);
  };

  return (
    <div className="space-y-4">
      {/* Filters */}
      <div className="flex flex-wrap gap-3">
        <input
          type="search"
          placeholder="Rechercher…"
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          className="flex-1 min-w-[180px] bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white placeholder-muted"
        />
        <select
          value={language}
          onChange={(e) => { setLanguage(e.target.value); setPage(1); }}
          className="bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white"
        >
          {LANG_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>{o.label}</option>
          ))}
        </select>
        <select
          value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1); }}
          className="bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white"
        >
          {STATUS_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>{o.label}</option>
          ))}
        </select>
        <button
          onClick={loadLandings}
          className="px-3 py-2 rounded-lg bg-bg border border-border/30 text-muted hover:text-white"
        >
          ↻
        </button>
      </div>

      {/* Count */}
      <p className="text-xs text-muted">
        {total} landing page{total > 1 ? 's' : ''}
        {status && ` · ${status}`}
        {language && ` · ${language}`}
      </p>

      {/* List */}
      {loading ? (
        <div className="space-y-2">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="h-14 bg-bg/40 rounded-xl animate-pulse" />
          ))}
        </div>
      ) : landings.length === 0 ? (
        <div className="text-muted text-sm text-center py-16 bg-bg/30 rounded-xl border border-border/10">
          Aucune landing page trouvée.{' '}
          {status || search || language ? 'Essayez de changer les filtres.' : 'Lancez une génération depuis l\'onglet "Queue".'}
        </div>
      ) : (
        <div className="space-y-1.5">
          {landings.map((lp) => (
            <LandingPageMiniCard
              key={lp.id}
              landing={lp}
              onDelete={(id) => setToDelete(landings.find((l) => l.id === id) ?? null)}
            />
          ))}
        </div>
      )}

      {/* Pagination */}
      {lastPage > 1 && (
        <div className="flex items-center justify-center gap-3 pt-2">
          <button
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            disabled={page === 1}
            className="px-4 py-1.5 rounded-lg bg-bg border border-border/30 text-sm text-muted hover:text-white disabled:opacity-40"
          >
            ← Préc.
          </button>
          <span className="text-sm text-muted font-mono">{page} / {lastPage}</span>
          <button
            onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
            disabled={page === lastPage}
            className="px-4 py-1.5 rounded-lg bg-bg border border-border/30 text-sm text-muted hover:text-white disabled:opacity-40"
          >
            Suiv. →
          </button>
        </div>
      )}

      {/* Delete confirm */}
      {toDelete && (
        <ConfirmModal
          title="Supprimer cette landing page ?"
          message={`"${toDelete.title ?? toDelete.slug}" sera supprimée définitivement.`}
          confirmLabel="Supprimer"
          onConfirm={() => handleDelete(toDelete.id)}
          onCancel={() => setToDelete(null)}
        />
      )}
    </div>
  );
}

// ── Main Component ─────────────────────────────────────────────────────

export default function LandingGeneratorAudiencePage({
  audienceType,
  label,
  icon,
  description,
}: Props) {
  const [tab, setTab]             = useState<Tab>('queue');
  const [queueKey, setQueueKey]   = useState(0);

  const TABS: { key: Tab; label: string }[] = [
    { key: 'queue',    label: '🌍 Queue Pays' },
    { key: 'config',   label: '⚙️ Configuration' },
    { key: 'landings', label: '📄 Landing Pages' },
  ];

  return (
    <div className="p-6 max-w-5xl mx-auto space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-white flex items-center gap-3">
          <span>{icon}</span>
          {label}
          <span className="text-[11px] font-normal text-muted bg-bg px-2 py-0.5 rounded-full border border-border/30 font-mono">
            {audienceType}
          </span>
        </h1>
        <p className="text-muted text-sm mt-1">{description}</p>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 p-1 bg-bg/60 border border-border/30 rounded-xl w-fit">
        {TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
              tab === t.key
                ? 'bg-violet text-white shadow-glow-violet'
                : 'text-muted hover:text-white hover:bg-white/5'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {/* Tab Content */}
      {tab === 'queue' && (
        <LandingCountryQueue
          key={queueKey}
          audienceType={audienceType}
          label={`Queue ${label}`}
        />
      )}

      {tab === 'config' && (
        <LandingGenerationConfig
          audienceType={audienceType}
          onSaved={() => setQueueKey((k) => k + 1)}
        />
      )}

      {tab === 'landings' && (
        <LandingsTab audienceType={audienceType} />
      )}
    </div>
  );
}
