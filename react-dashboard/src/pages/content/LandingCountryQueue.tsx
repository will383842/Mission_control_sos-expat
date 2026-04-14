import React, { useState, useEffect, useCallback } from 'react';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { getCountryInfo, ALL_CAMPAIGN_CODES, CAMPAIGN_COUNTRIES } from '../../data/countryCampaign';
import { toast } from '../../components/Toast';
import type { AudienceType } from '../../api/contentApi';
import {
  fetchLandingCampaign,
  updateLandingCampaign,
  launchLandingCampaign,
  addLandingCampaignCountry,
  removeLandingCampaignCountry,
  reorderLandingCampaign,
} from '../../api/contentApi';

// ── Types ─────────────────────────────────────────────────────────────

export interface LandingQueueItem {
  code: string;
  count: number;
  target: number;
  status: 'active' | 'pending';
}

export interface LandingCampaignData {
  audience_type: AudienceType;
  status: 'idle' | 'running' | 'paused' | 'completed';
  current_country: string | null;
  pages_per_country: number;
  daily_limit: number;        // 0 = illimité
  today_generated: number;    // LPs générées aujourd'hui
  daily_remaining: number | null; // null si illimité
  selected_templates: string[];
  problem_filters: {
    categories?: string[];
    min_urgency?: number;
    business_values?: string[];
  } | null;
  total_generated: number;
  total_cost_cents: number;
  queue: LandingQueueItem[];
  completed_countries: { code: string; count: number }[];
  available_templates: { id: string; label: string; description: string }[];
  started_at?: string | null;
  completed_at?: string | null;
}

interface Props {
  audienceType: AudienceType;
  label?: string;
  onDataChange?: (data: LandingCampaignData) => void;
}

// ── Sortable Row ──────────────────────────────────────────────────────

function SortableCountryRow({
  item,
  onRemove,
}: {
  item: LandingQueueItem;
  onRemove: (code: string) => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: item.code,
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  const info = getCountryInfo(item.code);
  const pct = item.target > 0 ? Math.round((item.count / item.target) * 100) : 0;

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`flex items-center gap-3 p-3 rounded-xl border transition-all ${
        item.status === 'active'
          ? 'bg-emerald-500/10 border-emerald-500/30'
          : 'bg-bg/50 border-border/20 hover:border-border/40'
      }`}
    >
      {/* Drag handle */}
      <button
        {...attributes}
        {...listeners}
        className="cursor-grab active:cursor-grabbing text-muted hover:text-white p-1"
        title="Glisser pour réordonner"
      >
        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
          <circle cx="5" cy="3" r="1.5" /><circle cx="11" cy="3" r="1.5" />
          <circle cx="5" cy="8" r="1.5" /><circle cx="11" cy="8" r="1.5" />
          <circle cx="5" cy="13" r="1.5" /><circle cx="11" cy="13" r="1.5" />
        </svg>
      </button>

      <span className="text-xl">{info.flag}</span>
      <span className="text-sm text-white font-medium w-40 truncate">{info.name}</span>

      <div className="flex-1 flex items-center gap-2">
        <div className="flex-1 h-2 bg-bg rounded-full overflow-hidden">
          <div
            className={`h-full rounded-full transition-all duration-500 ${
              item.status === 'active' ? 'bg-emerald-500' : 'bg-violet/60'
            }`}
            style={{ width: `${Math.min(100, pct)}%` }}
          />
        </div>
        <span className="text-xs text-muted font-mono w-20 text-right">
          {item.count}/{item.target} LPs
        </span>
      </div>

      <span
        className={`text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap ${
          item.status === 'active'
            ? 'bg-emerald-500/20 text-emerald-400'
            : 'bg-muted/10 text-muted'
        }`}
      >
        {item.status === 'active' ? 'EN COURS' : 'EN ATTENTE'}
      </span>

      <button
        onClick={() => onRemove(item.code)}
        className="text-muted hover:text-red-400 transition-colors p-1 shrink-0"
        title="Retirer de la queue"
      >
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M2 2l10 10M12 2L2 12" />
        </svg>
      </button>
    </div>
  );
}

// ── Main Component ────────────────────────────────────────────────────

const SUPPORTED_LANGUAGES = [
  { code: 'fr', label: '🇫🇷 Français' },
  { code: 'en', label: '🇬🇧 English' },
  { code: 'es', label: '🇪🇸 Español' },
  { code: 'de', label: '🇩🇪 Deutsch' },
  { code: 'pt', label: '🇧🇷 Português' },
  { code: 'ar', label: '🇸🇦 العربية' },
  { code: 'zh', label: '🇨🇳 中文' },
  { code: 'hi', label: '🇮🇳 हिन्दी' },
  { code: 'ru', label: '🇷🇺 Русский' },
];

export default function LandingCountryQueue({ audienceType, label, onDataChange }: Props) {
  const [data, setData]                   = useState<LandingCampaignData | null>(null);
  const [loading, setLoading]             = useState(true);
  const [launching, setLaunching]         = useState(false);
  const [editThreshold, setEditThreshold] = useState(10);
  const [showThreshold, setShowThreshold] = useState(false);
  const [addCode, setAddCode]             = useState('');
  const [language, setLanguage]           = useState('fr');

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const fetchData = useCallback(async () => {
    try {
      const res = await fetchLandingCampaign(audienceType);
      setData(res.data);
      setEditThreshold(res.data.pages_per_country);
      onDataChange?.(res.data);
    } catch {
      /* silent */
    } finally {
      setLoading(false);
    }
  }, [audienceType, onDataChange]);

  useEffect(() => {
    fetchData();
    const interval = setInterval(fetchData, 15000); // refresh auto toutes 15s
    return () => clearInterval(interval);
  }, [fetchData]);

  const handleDragEnd = async (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || !data || active.id === over.id) return;

    const oldIndex  = data.queue.findIndex((i) => i.code === active.id);
    const newIndex  = data.queue.findIndex((i) => i.code === over.id);
    const reordered = arrayMove(data.queue, oldIndex, newIndex);

    setData({ ...data, queue: reordered });

    try {
      // Envoyer UNIQUEMENT les pays de la queue active — pas les pays terminés.
      // Ajouter les completedCodes causerait leur réinsertion dans la queue active
      // et écraserait current_country avec un pays déjà terminé.
      await reorderLandingCampaign(audienceType, reordered.map((i) => i.code));
      await fetchData();
    } catch {
      toast.error('Erreur lors du réordonnancement');
      fetchData();
    }
  };

  const handleRemove = async (code: string) => {
    if (!data) return;
    setData({ ...data, queue: data.queue.filter((i) => i.code !== code) });
    try {
      await removeLandingCampaignCountry(audienceType, code);
      await fetchData();
      toast.success(`${getCountryInfo(code).name} retiré de la queue`);
    } catch {
      toast.error('Erreur lors de la suppression');
      fetchData();
    }
  };

  const handleAdd = async () => {
    if (!addCode) return;
    try {
      await addLandingCampaignCountry(audienceType, addCode);
      await fetchData();
      toast.success(`${getCountryInfo(addCode).name} ajouté à la queue`);
      setAddCode('');
    } catch {
      toast.error("Erreur lors de l'ajout");
    }
  };

  const handleSaveThreshold = async () => {
    try {
      await updateLandingCampaign(audienceType, { pages_per_country: editThreshold });
      await fetchData();
      setShowThreshold(false);
      toast.success(`Seuil mis à jour : ${editThreshold} LPs/pays`);
    } catch {
      toast.error('Erreur lors de la mise à jour');
    }
  };

  const handleLaunch = async () => {
    setLaunching(true);
    try {
      const res = await launchLandingCampaign(audienceType, language);
      toast.success(`${res.data.dispatched} jobs dispatchés pour ${res.data.country} [${language.toUpperCase()}] !`);
      await fetchData();
    } catch (err: unknown) {
      const axiosErr = err as { response?: { status?: number; data?: { error?: string; today_generated?: number; daily_limit?: number } } };
      if (axiosErr?.response?.status === 429) {
        const d = axiosErr.response.data;
        toast.error(d?.error ?? `Limite journalière atteinte (${d?.today_generated ?? '?'}/${d?.daily_limit ?? '?'})`);
      } else {
        toast.error('Erreur lors du lancement');
      }
    } finally {
      setLaunching(false);
    }
  };

  if (loading || !data) {
    return (
      <div className="bg-surface/60 border border-border/20 rounded-xl p-6 flex items-center justify-center h-40 text-muted">
        Chargement campagne…
      </div>
    );
  }

  const queueCodes     = new Set(data.queue.map((i) => i.code));
  const completedCodes = new Set(data.completed_countries.map((c) => c.code));
  const availableCodes = ALL_CAMPAIGN_CODES.filter((c) => !queueCodes.has(c) && !completedCodes.has(c));
  const currentItem    = data.queue.find((i) => i.status === 'active');

  const statusColors: Record<string, string> = {
    idle:      'text-muted',
    running:   'text-emerald-400',
    paused:    'text-amber-400',
    completed: 'text-violet-400',
  };

  return (
    <div className="bg-surface/60 border border-border/20 rounded-xl p-6 space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h2 className="text-lg font-semibold text-white">{label ?? 'Country Campaign'}</h2>
          <p className="text-muted text-xs mt-0.5">
            <span className={`font-semibold ${statusColors[data.status] ?? 'text-muted'}`}>
              {data.status.toUpperCase()}
            </span>
            {' · '}
            {data.pages_per_country} LPs/pays
            {' · '}
            {data.total_generated} générées au total
            {data.total_cost_cents > 0 && (
              <> · {(data.total_cost_cents / 100).toFixed(2)}$</>
            )}
            {data.daily_limit > 0 ? (
              <span className={`ml-2 px-2 py-0.5 rounded-full text-[10px] font-semibold ${
                data.daily_remaining === 0
                  ? 'bg-red-500/20 text-red-400'
                  : 'bg-amber-500/10 text-amber-400'
              }`}>
                {data.today_generated}/{data.daily_limit} aujourd'hui
                {data.daily_remaining === 0 ? ' — limite atteinte' : ` (${data.daily_remaining} restantes)`}
              </span>
            ) : (
              <span className="ml-2 text-[10px] text-muted/60">
                {data.today_generated > 0 && `${data.today_generated} aujourd'hui`}
              </span>
            )}
          </p>
        </div>
        <div className="flex gap-2 flex-wrap items-center">
          <button
            onClick={() => setShowThreshold(!showThreshold)}
            className="px-3 py-1.5 rounded-lg bg-bg border border-border/30 text-xs text-muted hover:text-white transition-colors"
          >
            Seuil : {data.pages_per_country}
          </button>
          {/* Language selector — determines content language for generation */}
          <select
            value={language}
            onChange={(e) => setLanguage(e.target.value)}
            className="bg-bg border border-border/30 rounded-lg px-2 py-1.5 text-xs text-white"
            title="Langue de génération"
          >
            {SUPPORTED_LANGUAGES.map((l) => (
              <option key={l.code} value={l.code}>{l.label}</option>
            ))}
          </select>
          <button
            onClick={handleLaunch}
            disabled={launching || !currentItem}
            className="px-4 py-1.5 rounded-lg bg-violet text-white text-xs font-semibold hover:bg-violet/80 transition-all disabled:opacity-40 flex items-center gap-1.5"
          >
            {launching ? (
              <>
                <svg className="animate-spin w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4" />
                </svg>
                Lancement…
              </>
            ) : `▶ Lancer [${language.toUpperCase()}]`}
          </button>
          <button
            onClick={fetchData}
            className="px-3 py-1.5 rounded-lg bg-bg border border-border/30 text-xs text-muted hover:text-white"
            title="Actualiser"
          >
            ↻
          </button>
        </div>
      </div>

      {/* Threshold edit */}
      {showThreshold && (
        <div className="flex items-center gap-3 p-3 rounded-xl bg-bg/50 border border-border/20">
          <span className="text-sm text-muted">LPs par pays :</span>
          <input
            type="number"
            min={1}
            max={500}
            value={editThreshold}
            onChange={(e) => setEditThreshold(Math.max(1, parseInt(e.target.value) || 10))}
            className="w-20 bg-bg border border-border rounded-lg px-3 py-1.5 text-white text-center text-sm"
          />
          <button
            onClick={handleSaveThreshold}
            className="px-3 py-1.5 rounded-lg bg-violet text-white text-xs font-semibold hover:bg-violet/80"
          >
            Sauvegarder
          </button>
          <button
            onClick={() => { setShowThreshold(false); setEditThreshold(data.pages_per_country); }}
            className="px-3 py-1.5 rounded-lg text-xs text-muted hover:text-white"
          >
            Annuler
          </button>
        </div>
      )}

      {/* Current country highlight */}
      {currentItem && (
        <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
          <div className="flex items-center gap-3 mb-2">
            <span className="text-2xl">{getCountryInfo(currentItem.code).flag}</span>
            <div>
              <p className="text-white font-semibold">{getCountryInfo(currentItem.code).name}</p>
              <p className="text-emerald-400 text-xs">
                EN COURS — {currentItem.count}/{currentItem.target} LPs ({Math.round((currentItem.count / Math.max(1, currentItem.target)) * 100)}%)
              </p>
            </div>
          </div>
          <div className="w-full h-3 bg-bg rounded-full overflow-hidden">
            <div
              className="h-full bg-emerald-500 rounded-full transition-all duration-700"
              style={{ width: `${Math.min(100, Math.round((currentItem.count / Math.max(1, currentItem.target)) * 100))}%` }}
            />
          </div>
        </div>
      )}

      {/* Sortable queue */}
      {data.queue.length > 0 ? (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
          <SortableContext items={data.queue.map((i) => i.code)} strategy={verticalListSortingStrategy}>
            <div className="space-y-2">
              {data.queue.map((item) => (
                <SortableCountryRow key={item.code} item={item} onRemove={handleRemove} />
              ))}
            </div>
          </SortableContext>
        </DndContext>
      ) : (
        <p className="text-muted text-sm text-center py-6 bg-bg/30 rounded-xl border border-border/10">
          Aucun pays dans la queue. Ajoutez des pays ci-dessous.
        </p>
      )}

      {/* Add country */}
      <div className="flex items-center gap-3 p-3 rounded-xl bg-bg/30 border border-border/10">
        <span className="text-sm text-muted shrink-0">Ajouter :</span>
        <select
          value={addCode}
          onChange={(e) => setAddCode(e.target.value)}
          className="flex-1 bg-bg border border-border rounded-lg px-3 py-1.5 text-white text-sm"
        >
          <option value="">Choisir un pays…</option>
          {availableCodes.map((code) => {
            const info = CAMPAIGN_COUNTRIES[code];
            return (
              <option key={code} value={code}>
                {info?.flag} {info?.name} ({code})
              </option>
            );
          })}
        </select>
        <button
          onClick={handleAdd}
          disabled={!addCode}
          className="px-4 py-1.5 rounded-lg bg-violet text-white text-xs font-semibold hover:bg-violet/80 disabled:opacity-40 shrink-0"
        >
          + Ajouter
        </button>
      </div>

      {/* Completed countries */}
      {data.completed_countries.length > 0 && (
        <div className="pt-3 border-t border-border/20">
          <p className="text-xs text-muted mb-2">
            Pays terminés ({data.completed_countries.length})
          </p>
          <div className="flex flex-wrap gap-2">
            {data.completed_countries.map((c) => {
              const info = getCountryInfo(c.code);
              return (
                <span
                  key={c.code}
                  className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-400"
                >
                  {info.flag} {info.name}
                  <span className="text-emerald-400/60">{c.count} LPs</span>
                </span>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
