import React, { useState, useEffect, useCallback } from 'react';
import { toast } from '../../components/Toast';
import type { AudienceType } from '../../api/contentApi';
import {
  fetchLandingCampaign,
  updateLandingCampaign,
  fetchLandingProblemCategories,
} from '../../api/contentApi';
import type { LandingCampaignData } from './LandingCountryQueue';

// ── Types ──────────────────────────────────────────────────────────────

interface ProblemCategory {
  value: string;
  label: string;
  count: number;
}

interface Props {
  audienceType: AudienceType;
  /** Callback when config is saved (re-fetch parent) */
  onSaved?: () => void;
}

// ── Helpers ────────────────────────────────────────────────────────────

const BUSINESS_VALUES = [
  { value: 'high',   label: '🔴 High' },
  { value: 'medium', label: '🟡 Medium' },
  { value: 'low',    label: '🟢 Low' },
];

// ── Component ──────────────────────────────────────────────────────────

export default function LandingGenerationConfig({ audienceType, onSaved }: Props) {
  const [data, setData]                     = useState<LandingCampaignData | null>(null);
  const [categories, setCategories]         = useState<ProblemCategory[]>([]);
  const [loading, setLoading]               = useState(true);
  const [saving, setSaving]                 = useState(false);

  // Local editable state
  const [selectedTemplates, setSelectedTemplates] = useState<string[]>([]);
  const [pagesPerCountry, setPagesPerCountry]     = useState(10);
  const [dailyLimit, setDailyLimit]               = useState(0); // 0 = illimité
  const [filterCategories, setFilterCategories]   = useState<string[]>([]);
  const [filterMinUrgency, setFilterMinUrgency]   = useState(0);
  const [filterBusinessValues, setFilterBusinessValues] = useState<string[]>([]);

  const isClients = audienceType === 'clients';

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [campaignRes, catRes] = await Promise.all([
        fetchLandingCampaign(audienceType),
        isClients ? fetchLandingProblemCategories() : Promise.resolve(null),
      ]);
      const d: LandingCampaignData = campaignRes.data;
      setData(d);
      setSelectedTemplates(d.selected_templates ?? []);
      setPagesPerCountry(d.pages_per_country);
      setDailyLimit(d.daily_limit ?? 0);
      setFilterCategories(d.problem_filters?.categories ?? []);
      setFilterMinUrgency(d.problem_filters?.min_urgency ?? 0);
      setFilterBusinessValues(d.problem_filters?.business_values ?? []);
      if (catRes) setCategories(catRes.data);
    } catch {
      toast.error('Erreur lors du chargement de la configuration');
    } finally {
      setLoading(false);
    }
  }, [audienceType, isClients]);

  useEffect(() => { loadData(); }, [loadData]);

  const toggleTemplate = (id: string) => {
    setSelectedTemplates(prev =>
      prev.includes(id) ? prev.filter(t => t !== id) : [...prev, id],
    );
  };

  const toggleCategory = (value: string) => {
    setFilterCategories(prev =>
      prev.includes(value) ? prev.filter(c => c !== value) : [...prev, value],
    );
  };

  const toggleBusinessValue = (value: string) => {
    setFilterBusinessValues(prev =>
      prev.includes(value) ? prev.filter(v => v !== value) : [...prev, value],
    );
  };

  const handleSave = async () => {
    if (selectedTemplates.length === 0) {
      toast.error('Sélectionnez au moins un template');
      return;
    }
    setSaving(true);
    try {
      await updateLandingCampaign(audienceType, {
        selected_templates: selectedTemplates,
        pages_per_country:  pagesPerCountry,
        daily_limit:        dailyLimit,
        ...(isClients && {
          problem_filters: {
            categories:      filterCategories.length > 0 ? filterCategories : undefined,
            min_urgency:     filterMinUrgency > 0 ? filterMinUrgency : undefined,
            business_values: filterBusinessValues.length > 0 ? filterBusinessValues : undefined,
          },
        }),
      });
      toast.success('Configuration sauvegardée');
      onSaved?.();
    } catch {
      toast.error('Erreur lors de la sauvegarde');
    } finally {
      setSaving(false);
    }
  };

  if (loading || !data) {
    return (
      <div className="bg-surface/60 border border-border/20 rounded-xl p-6 flex items-center justify-center h-40 text-muted">
        Chargement configuration…
      </div>
    );
  }

  const templates = data.available_templates;

  return (
    <div className="space-y-6">
      {/* ── Templates ── */}
      <div className="bg-surface/60 border border-border/20 rounded-xl p-6 space-y-4">
        <div className="flex items-center justify-between">
          <h3 className="text-white font-semibold">Templates de génération</h3>
          <span className="text-xs text-muted">
            {selectedTemplates.length}/{templates.length} sélectionnés
          </span>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {templates.map((tpl) => {
            const active = selectedTemplates.includes(tpl.id);
            return (
              <button
                key={tpl.id}
                onClick={() => toggleTemplate(tpl.id)}
                className={`flex items-start gap-3 p-4 rounded-xl border text-left transition-all ${
                  active
                    ? 'bg-violet/10 border-violet/40 ring-1 ring-violet/30'
                    : 'bg-bg/50 border-border/20 hover:border-border/40'
                }`}
              >
                <div
                  className={`w-5 h-5 rounded flex items-center justify-center shrink-0 mt-0.5 border transition-colors ${
                    active ? 'bg-violet border-violet' : 'border-border/50'
                  }`}
                >
                  {active && (
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="white" strokeWidth="2.5">
                      <path d="M2 6l3 3 5-5" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                  )}
                </div>
                <div className="min-w-0">
                  <p className={`text-sm font-medium ${active ? 'text-white' : 'text-gray-300'}`}>
                    {tpl.label}
                  </p>
                  {tpl.description && (
                    <p className="text-xs text-muted mt-0.5 line-clamp-2">{tpl.description}</p>
                  )}
                  <p className="text-[11px] text-violet-light/70 mt-1 font-mono">{tpl.id}</p>
                </div>
              </button>
            );
          })}
        </div>
      </div>

      {/* ── Volume ── */}
      <div className="bg-surface/60 border border-border/20 rounded-xl p-6 space-y-5">
        <h3 className="text-white font-semibold">Volume &amp; limites</h3>

        {/* LPs par pays */}
        <div className="flex items-center gap-4 flex-wrap">
          <label className="text-sm text-muted w-44 shrink-0">LPs max par pays :</label>
          <input
            type="number"
            min={1}
            max={500}
            value={pagesPerCountry}
            onChange={(e) => setPagesPerCountry(Math.max(1, parseInt(e.target.value) || 1))}
            className="w-24 bg-bg border border-border rounded-lg px-3 py-1.5 text-white text-center text-sm"
          />
          <span className="text-xs text-muted">
            {selectedTemplates.length > 0
              ? `≈ ${Math.ceil(pagesPerCountry / Math.max(1, selectedTemplates.length))} problèmes × ${selectedTemplates.length} templates`
              : '—'}
          </span>
        </div>

        {/* Limite journalière */}
        <div className="flex items-center gap-4 flex-wrap">
          <label className="text-sm text-muted w-44 shrink-0">
            Limite journalière :
          </label>
          <input
            type="number"
            min={0}
            max={9999}
            value={dailyLimit}
            onChange={(e) => setDailyLimit(Math.max(0, parseInt(e.target.value) || 0))}
            className="w-24 bg-bg border border-border rounded-lg px-3 py-1.5 text-white text-center text-sm"
          />
          <span className="text-xs text-muted">
            {dailyLimit === 0
              ? 'Illimitée — toutes les LPs sont générées sans plafond quotidien'
              : `Max ${dailyLimit} LPs générées par jour. Bloque le lancement si déjà atteint.`}
          </span>
        </div>
      </div>

      {/* ── Filtres problèmes (clients seulement) ── */}
      {isClients && (
        <div className="bg-surface/60 border border-border/20 rounded-xl p-6 space-y-5">
          <h3 className="text-white font-semibold">Filtres problèmes</h3>
          <p className="text-xs text-muted">
            Filtrez les 417 problèmes sources pour cibler les LPs les plus stratégiques.
          </p>

          {/* Catégories */}
          {categories.length > 0 && (
            <div className="space-y-2">
              <label className="text-sm text-gray-300 font-medium">Catégories</label>
              <div className="flex flex-wrap gap-2">
                {categories.map((cat) => {
                  const active = filterCategories.includes(cat.value);
                  return (
                    <button
                      key={cat.value}
                      onClick={() => toggleCategory(cat.value)}
                      className={`px-3 py-1 rounded-full text-xs border transition-all ${
                        active
                          ? 'bg-violet/20 border-violet/40 text-white'
                          : 'bg-bg border-border/30 text-muted hover:border-border/60 hover:text-gray-300'
                      }`}
                    >
                      {cat.label}
                      <span className="ml-1.5 opacity-60">{cat.count}</span>
                    </button>
                  );
                })}
              </div>
              {filterCategories.length > 0 && (
                <button
                  onClick={() => setFilterCategories([])}
                  className="text-xs text-violet-light hover:underline"
                >
                  Réinitialiser catégories
                </button>
              )}
            </div>
          )}

          {/* Business value */}
          <div className="space-y-2">
            <label className="text-sm text-gray-300 font-medium">Valeur business</label>
            <div className="flex gap-2">
              {BUSINESS_VALUES.map((bv) => {
                const active = filterBusinessValues.includes(bv.value);
                return (
                  <button
                    key={bv.value}
                    onClick={() => toggleBusinessValue(bv.value)}
                    className={`px-3 py-1 rounded-full text-xs border transition-all ${
                      active
                        ? 'bg-violet/20 border-violet/40 text-white'
                        : 'bg-bg border-border/30 text-muted hover:border-border/60 hover:text-gray-300'
                    }`}
                  >
                    {bv.label}
                  </button>
                );
              })}
            </div>
          </div>

          {/* Urgence min */}
          <div className="space-y-2">
            <label className="text-sm text-gray-300 font-medium">
              Urgence minimum : <span className="text-white font-semibold">{filterMinUrgency}</span>
              {filterMinUrgency === 0 && <span className="text-muted"> (tous)</span>}
            </label>
            <input
              type="range"
              min={0}
              max={10}
              step={1}
              value={filterMinUrgency}
              onChange={(e) => setFilterMinUrgency(parseInt(e.target.value))}
              className="w-full accent-violet"
            />
            <div className="flex justify-between text-[10px] text-muted">
              <span>0 — tous</span>
              <span>5 — moyen</span>
              <span>10 — max</span>
            </div>
          </div>
        </div>
      )}

      {/* ── Save ── */}
      <div className="flex items-center justify-between pt-2">
        <button
          onClick={loadData}
          className="px-4 py-2 rounded-lg bg-bg border border-border/30 text-xs text-muted hover:text-white transition-colors"
        >
          Annuler
        </button>
        <button
          onClick={handleSave}
          disabled={saving || selectedTemplates.length === 0}
          className="px-6 py-2 rounded-lg bg-violet text-white text-sm font-semibold hover:bg-violet/80 transition-all disabled:opacity-40 flex items-center gap-2"
        >
          {saving ? (
            <>
              <svg className="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4" />
              </svg>
              Sauvegarde…
            </>
          ) : '💾 Sauvegarder la configuration'}
        </button>
      </div>
    </div>
  );
}
