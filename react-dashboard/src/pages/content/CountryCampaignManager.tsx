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
import {
  fetchCampaignStatus,
  updateCountryCampaign,
  addCampaignCountry,
  removeCampaignCountry,
  reorderCampaign,
  launchCampaign,
} from '../../api/contentApi';
import { getCountryInfo, ALL_CAMPAIGN_CODES, CAMPAIGN_COUNTRIES } from '../../data/countryCampaign';
import { toast } from '../../components/Toast';

interface QueueItem {
  code: string;
  count: number;
  target: number;
  status: 'active' | 'pending';
}

interface CompletedCountry {
  code: string;
  count: number;
}

interface CampaignData {
  queue: QueueItem[];
  current_country: string | null;
  articles_per_country: number;
  completed_countries: CompletedCountry[];
}

// ── Sortable row component ────────────────────────��─────────────────

function SortableCountryRow({
  item,
  onRemove,
}: {
  item: QueueItem;
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
        title="Glisser pour reordonner"
      >
        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
          <circle cx="5" cy="3" r="1.5" />
          <circle cx="11" cy="3" r="1.5" />
          <circle cx="5" cy="8" r="1.5" />
          <circle cx="11" cy="8" r="1.5" />
          <circle cx="5" cy="13" r="1.5" />
          <circle cx="11" cy="13" r="1.5" />
        </svg>
      </button>

      {/* Flag + name */}
      <span className="text-xl">{info.flag}</span>
      <span className="text-sm text-white font-medium w-40 truncate">{info.name}</span>

      {/* Progress bar */}
      <div className="flex-1 flex items-center gap-2">
        <div className="flex-1 h-2 bg-bg rounded-full overflow-hidden">
          <div
            className={`h-full rounded-full transition-all duration-500 ${
              item.status === 'active' ? 'bg-emerald-500' : 'bg-violet/60'
            }`}
            style={{ width: `${Math.min(100, pct)}%` }}
          />
        </div>
        <span className="text-xs text-muted font-mono w-16 text-right">
          {item.count}/{item.target}
        </span>
      </div>

      {/* Status badge */}
      <span
        className={`text-xs font-semibold px-2 py-0.5 rounded-full ${
          item.status === 'active'
            ? 'bg-emerald-500/20 text-emerald-400'
            : 'bg-muted/10 text-muted'
        }`}
      >
        {item.status === 'active' ? 'EN COURS' : 'EN ATTENTE'}
      </span>

      {/* Remove button */}
      <button
        onClick={() => onRemove(item.code)}
        className="text-muted hover:text-red-400 transition-colors p-1"
        title="Retirer de la queue"
      >
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M2 2l10 10M12 2L2 12" />
        </svg>
      </button>
    </div>
  );
}

// ── Main component ──────────────────────────────────────────────────

export default function CountryCampaignManager() {
  const [data, setData] = useState<CampaignData | null>(null);
  const [loading, setLoading] = useState(true);
  const [launching, setLaunching] = useState(false);
  const [editThreshold, setEditThreshold] = useState(100);
  const [showThresholdEdit, setShowThresholdEdit] = useState(false);
  const [addCountryCode, setAddCountryCode] = useState('');

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const fetchData = useCallback(async () => {
    try {
      const res = await fetchCampaignStatus();
      setData(res.data);
      setEditThreshold(res.data.articles_per_country);
    } catch {
      /* silent */
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleDragEnd = async (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || !data || active.id === over.id) return;

    const oldIndex = data.queue.findIndex((i) => i.code === active.id);
    const newIndex = data.queue.findIndex((i) => i.code === over.id);
    const reordered = arrayMove(data.queue, oldIndex, newIndex);

    // Optimistic update
    setData({ ...data, queue: reordered });

    try {
      const newQueue = reordered.map((i) => i.code);
      // Also include completed countries to preserve full queue
      const completedCodes = data.completed_countries.map((c) => c.code);
      await reorderCampaign([...newQueue, ...completedCodes]);
      await fetchData();
    } catch {
      toast.error('Erreur lors du reordonnancement');
      fetchData();
    }
  };

  const handleRemove = async (code: string) => {
    if (!data) return;
    // Optimistic update
    setData({ ...data, queue: data.queue.filter((i) => i.code !== code) });
    try {
      await removeCampaignCountry(code);
      await fetchData();
      toast.success(`${getCountryInfo(code).name} retire de la queue`);
    } catch {
      toast.error('Erreur lors de la suppression');
      fetchData();
    }
  };

  const handleAdd = async () => {
    if (!addCountryCode) return;
    try {
      await addCampaignCountry(addCountryCode);
      await fetchData();
      toast.success(`${getCountryInfo(addCountryCode).name} ajoute a la queue`);
      setAddCountryCode('');
    } catch {
      toast.error("Erreur lors de l'ajout");
    }
  };

  const handleSaveThreshold = async () => {
    try {
      await updateCountryCampaign({ articles_per_country: editThreshold });
      await fetchData();
      setShowThresholdEdit(false);
      toast.success(`Seuil mis a jour: ${editThreshold} articles/pays`);
    } catch {
      toast.error('Erreur lors de la mise a jour du seuil');
    }
  };

  const handleLaunch = async () => {
    setLaunching(true);
    try {
      await launchCampaign();
      toast.success('Campaign lancee ! Generation en cours...');
    } catch {
      toast.error('Erreur lors du lancement');
    } finally {
      setLaunching(false);
    }
  };

  if (loading || !data) {
    return (
      <div className="bg-surface/60 border border-border/20 rounded-xl p-6">
        <div className="flex items-center justify-center h-32 text-muted">Chargement campaign...</div>
      </div>
    );
  }

  // Countries available to add (not in queue AND not completed)
  const queueCodes = new Set(data.queue.map((i) => i.code));
  const completedCodes = new Set(data.completed_countries.map((c) => c.code));
  const availableCodes = ALL_CAMPAIGN_CODES.filter(
    (code) => !queueCodes.has(code) && !completedCodes.has(code),
  );

  const currentItem = data.queue.find((i) => i.status === 'active');

  return (
    <div className="bg-surface/60 border border-border/20 rounded-xl p-6 space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-lg font-semibold text-white">Country Campaign</h2>
          <p className="text-muted text-xs mt-0.5">
            Topical Authority SEO — {data.articles_per_country} articles par pays, un pays a la fois
          </p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={() => setShowThresholdEdit(!showThresholdEdit)}
            className="px-3 py-1.5 rounded-lg bg-bg border border-border/30 text-xs text-muted hover:text-white transition-colors"
          >
            Seuil: {data.articles_per_country}
          </button>
          <button
            onClick={handleLaunch}
            disabled={launching || !currentItem}
            className="px-4 py-1.5 rounded-lg bg-violet text-white text-xs font-semibold hover:bg-violet/80 transition-all disabled:opacity-40"
          >
            {launching ? 'Lancement...' : 'Lancer la generation'}
          </button>
          <button
            onClick={fetchData}
            className="px-3 py-1.5 rounded-lg bg-bg border border-border/30 text-xs text-muted hover:text-white"
          >
            Actualiser
          </button>
        </div>
      </div>

      {/* Threshold edit */}
      {showThresholdEdit && (
        <div className="flex items-center gap-3 p-3 rounded-xl bg-bg/50 border border-border/20">
          <span className="text-sm text-muted">Articles par pays :</span>
          <input
            type="number"
            min={10}
            max={500}
            value={editThreshold}
            onChange={(e) => setEditThreshold(Math.max(10, parseInt(e.target.value) || 100))}
            className="w-20 bg-bg border border-border rounded-lg px-3 py-1.5 text-white text-center text-sm"
          />
          <button
            onClick={handleSaveThreshold}
            className="px-3 py-1.5 rounded-lg bg-violet text-white text-xs font-semibold hover:bg-violet/80"
          >
            Sauvegarder
          </button>
          <button
            onClick={() => {
              setShowThresholdEdit(false);
              setEditThreshold(data.articles_per_country);
            }}
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
              <p className="text-white font-semibold">
                {getCountryInfo(currentItem.code).name}
              </p>
              <p className="text-emerald-400 text-xs">
                EN COURS — {currentItem.count}/{currentItem.target} articles
              </p>
            </div>
          </div>
          <div className="w-full h-3 bg-bg rounded-full overflow-hidden">
            <div
              className="h-full bg-emerald-500 rounded-full transition-all duration-700"
              style={{
                width: `${Math.min(100, Math.round((currentItem.count / currentItem.target) * 100))}%`,
              }}
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
        <p className="text-muted text-sm text-center py-4">
          Aucun pays dans la queue. Ajoutez des pays ci-dessous.
        </p>
      )}

      {/* Add country */}
      <div className="flex items-center gap-3 p-3 rounded-xl bg-bg/30 border border-border/10">
        <span className="text-sm text-muted">Ajouter :</span>
        <select
          value={addCountryCode}
          onChange={(e) => setAddCountryCode(e.target.value)}
          className="flex-1 bg-bg border border-border rounded-lg px-3 py-1.5 text-white text-sm"
        >
          <option value="">Choisir un pays...</option>
          {availableCodes.map((code) => {
            const info = CAMPAIGN_COUNTRIES[code];
            return (
              <option key={code} value={code}>
                {info.flag} {info.name} ({code})
              </option>
            );
          })}
        </select>
        <button
          onClick={handleAdd}
          disabled={!addCountryCode}
          className="px-4 py-1.5 rounded-lg bg-violet text-white text-xs font-semibold hover:bg-violet/80 disabled:opacity-40"
        >
          + Ajouter
        </button>
      </div>

      {/* Completed countries */}
      {data.completed_countries.length > 0 && (
        <div className="pt-3 border-t border-border/20">
          <p className="text-xs text-muted mb-2">
            Pays termines ({data.completed_countries.length})
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
                  <span className="text-emerald-400/60">{c.count} articles</span>
                </span>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
