import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  fetchTemplates, createTemplate, deleteTemplate,
  type ContentTemplate,
} from '../../api/contentApi';
import { toast } from '../../components/Toast';

const PRESET_CONFIG: Record<string, { emoji: string; label: string; gradient: string; desc: string; titleHint: string; expansion: string; instructions: string }> = {
  'mots-cles': {
    emoji: '🔑', label: 'Mots cles', gradient: 'from-violet/20 to-violet/5',
    desc: 'Articles SEO autour de mots-cles principaux',
    titleHint: 'Ex: assurance expatrie, visa digital nomad...',
    expansion: 'manual',
    instructions: 'Article SEO optimise. Donnees chiffrees, exemples concrets, angle pratique expatries/voyageurs.',
  },
  'longues-traines': {
    emoji: '🎯', label: 'Longues traines', gradient: 'from-cyan/20 to-cyan/5',
    desc: 'Articles ciblant des requetes longue traine a faible concurrence',
    titleHint: 'Ex: comment ouvrir un compte bancaire en tant qu\'expatrie au Portugal...',
    expansion: 'manual',
    instructions: 'Longue traine : reponse directe en premier paragraphe (featured snippet), ton conversationnel, FAQ PAA.',
  },
  'rec-avocats': {
    emoji: '⚖️', label: 'Rec Avocats', gradient: 'from-amber/20 to-amber/5',
    desc: 'Recrutement avocats partenaires par pays',
    titleHint: 'Devenir avocat partenaire SOS-Expat en {pays}',
    expansion: 'all_countries',
    instructions: 'Recrutement avocats. Avantages : revenus complementaires, flexibilite, clientele internationale. CTA inscription.',
  },
  'rec-expats': {
    emoji: '🧳', label: 'Rec Expats', gradient: 'from-success/20 to-success/5',
    desc: 'Recrutement expats aidants par pays',
    titleHint: 'Devenir expat aidant en {pays}',
    expansion: 'all_countries',
    instructions: 'Recrutement expats. Programme SOS-Expat : aider, gagner un revenu, partager son experience. CTA inscription.',
  },
  'visa-pays': {
    emoji: '🛂', label: 'Visa par pays', gradient: 'from-blue-500/20 to-blue-500/5',
    desc: 'Guides visa pour chaque pays',
    titleHint: 'Comment obtenir un visa pour {pays}',
    expansion: 'all_countries',
    instructions: 'Guide visa complet : types, demarches, couts, delais, documents. Donnees specifiques au pays.',
  },
  'cout-vie': {
    emoji: '💰', label: 'Cout de la vie', gradient: 'from-danger/20 to-danger/5',
    desc: 'Cout de la vie par pays',
    titleHint: 'Cout de la vie en {pays} en 2026',
    expansion: 'all_countries',
    instructions: 'Cout de la vie : logement, alimentation, transport, sante, telecom. Tableaux comparatifs. Devise locale + EUR/USD.',
  },
  'custom': {
    emoji: '✨', label: 'Custom', gradient: 'from-muted/20 to-muted/5',
    desc: 'Template personnalise',
    titleHint: 'Votre template avec {variables}...',
    expansion: 'manual',
    instructions: '',
  },
};

const STATUS_STYLES: Record<string, string> = {
  pending: 'bg-muted/10 text-muted',
  generating: 'bg-amber/10 text-amber',
  published: 'bg-success/10 text-success',
  failed: 'bg-danger/10 text-danger',
  skipped: 'bg-muted/10 text-muted',
};

export default function ContentTemplates() {
  const navigate = useNavigate();
  const [templates, setTemplates] = useState<ContentTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [showCreate, setShowCreate] = useState(false);
  const [filterPreset, setFilterPreset] = useState('');

  // Create form
  const [newName, setNewName] = useState('');
  const [newPreset, setNewPreset] = useState('mots-cles');
  const [newTitle, setNewTitle] = useState('');
  const [newInstructions, setNewInstructions] = useState('');
  const [newExpansion, setNewExpansion] = useState('manual');
  const [creating, setCreating] = useState(false);

  const loadTemplates = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = {};
      if (filterPreset) params.preset_type = filterPreset;
      const res = await fetchTemplates(params);
      setTemplates((res.data as any).data ?? []);
    } catch {
      toast.error('Erreur chargement templates');
    } finally {
      setLoading(false);
    }
  }, [filterPreset]);

  useEffect(() => { loadTemplates(); }, [loadTemplates]);

  // Auto-fill when preset changes
  useEffect(() => {
    const cfg = PRESET_CONFIG[newPreset];
    if (cfg) {
      setNewTitle(cfg.titleHint);
      setNewInstructions(cfg.instructions);
      setNewExpansion(cfg.expansion);
      if (!newName) setNewName(cfg.label);
    }
  }, [newPreset]);

  const handleCreate = async () => {
    if (!newName.trim() || !newTitle.trim()) {
      toast.error('Nom et template titre requis');
      return;
    }
    setCreating(true);
    try {
      await createTemplate({
        name: newName.trim(),
        preset_type: newPreset,
        content_type: 'article',
        title_template: newTitle.trim(),
        expansion_mode: newExpansion,
        generation_instructions: newInstructions.trim() || null,
        language: 'fr',
        tone: PRESET_CONFIG[newPreset]?.label.includes('Rec') ? 'professional' : 'professional',
        article_length: 'medium',
        generate_faq: true,
        faq_count: 6,
        research_sources: true,
        auto_internal_links: true,
        auto_affiliate_links: true,
        auto_translate: true,
      } as any);
      toast.success('Template cree !');
      setShowCreate(false);
      setNewName('');
      setNewTitle('');
      setNewInstructions('');
      loadTemplates();
    } catch (e: unknown) {
      toast.error(e?.response?.data?.message || 'Erreur creation');
    } finally {
      setCreating(false);
    }
  };

  const handleDelete = async (id: number, name: string) => {
    if (!confirm(`Supprimer le template "${name}" et tous ses items ?`)) return;
    try {
      await deleteTemplate(id);
      toast.success('Template supprime');
      loadTemplates();
    } catch {
      toast.error('Erreur suppression');
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-title font-bold text-white tracking-tight">
            Content Templates
          </h1>
          <p className="text-sm text-muted mt-1">Generez des articles a grande echelle avec des templates intelligents</p>
        </div>
        <button
          onClick={() => setShowCreate(!showCreate)}
          className="px-5 py-2.5 bg-gradient-to-r from-violet to-violet-light text-white text-sm font-semibold rounded-xl shadow-lg shadow-violet/20 hover:shadow-violet/40 transition-all hover:scale-[1.02] active:scale-[0.98]"
        >
          + Nouveau template
        </button>
      </div>

      {/* Create modal */}
      {showCreate && (
        <div className="bg-surface/80 backdrop-blur-xl border border-border/50 rounded-2xl p-6 space-y-5 shadow-2xl">
          <h2 className="text-lg font-title font-bold text-white">Nouveau template</h2>

          {/* Preset cards */}
          <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
            {Object.entries(PRESET_CONFIG).map(([key, cfg]) => (
              <button
                key={key}
                onClick={() => setNewPreset(key)}
                className={`p-3 rounded-xl border text-left transition-all ${
                  newPreset === key
                    ? `border-violet bg-gradient-to-br ${cfg.gradient} shadow-lg`
                    : 'border-border/50 bg-surface2/50 hover:border-border'
                }`}
              >
                <span className="text-xl">{cfg.emoji}</span>
                <p className="text-xs font-semibold text-white mt-1">{cfg.label}</p>
                <p className="text-[10px] text-muted mt-0.5 line-clamp-2">{cfg.desc}</p>
              </button>
            ))}
          </div>

          {/* Form fields */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="text-xs font-medium text-muted uppercase tracking-wider mb-1.5 block">Nom du template</label>
              <input
                type="text"
                value={newName}
                onChange={e => setNewName(e.target.value)}
                className="w-full bg-bg/80 border border-border/50 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-violet/50 focus:ring-1 focus:ring-violet/20 transition-all"
                placeholder="Mon template SEO"
              />
            </div>
            <div>
              <label className="text-xs font-medium text-muted uppercase tracking-wider mb-1.5 block">Expansion</label>
              <select
                value={newExpansion}
                onChange={e => setNewExpansion(e.target.value)}
                className="w-full bg-bg/80 border border-border/50 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-violet/50 transition-all"
              >
                <option value="manual">Manuel (mots-cles libres)</option>
                <option value="all_countries">Tous les 197 pays</option>
                <option value="selected_countries">Pays selectionnes</option>
                <option value="custom_list">Liste personnalisee</option>
              </select>
            </div>
          </div>

          <div>
            <label className="text-xs font-medium text-muted uppercase tracking-wider mb-1.5 block">
              Template titre <span className="text-violet/60">{'— Utilisez {pays} pour la variable pays'}</span>
            </label>
            <input
              type="text"
              value={newTitle}
              onChange={e => setNewTitle(e.target.value)}
              className="w-full bg-bg/80 border border-border/50 rounded-xl px-4 py-2.5 text-white text-sm font-mono focus:outline-none focus:border-violet/50 focus:ring-1 focus:ring-violet/20 transition-all"
              placeholder="Comment obtenir un visa {pays}"
            />
          </div>

          <div>
            <label className="text-xs font-medium text-muted uppercase tracking-wider mb-1.5 block">Instructions de generation (optionnel)</label>
            <textarea
              value={newInstructions}
              onChange={e => setNewInstructions(e.target.value)}
              rows={3}
              className="w-full bg-bg/80 border border-border/50 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-violet/50 focus:ring-1 focus:ring-violet/20 transition-all resize-none"
              placeholder="Instructions specifiques pour l'IA..."
            />
          </div>

          <div className="flex justify-end gap-3">
            <button onClick={() => setShowCreate(false)} className="px-4 py-2 text-sm text-muted hover:text-white transition-colors">
              Annuler
            </button>
            <button
              onClick={handleCreate}
              disabled={creating}
              className="px-6 py-2.5 bg-gradient-to-r from-violet to-violet-light text-white text-sm font-semibold rounded-xl shadow-lg shadow-violet/20 hover:shadow-violet/40 transition-all disabled:opacity-50"
            >
              {creating ? 'Creation...' : 'Creer le template'}
            </button>
          </div>
        </div>
      )}

      {/* Filter tabs */}
      <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
        <button
          onClick={() => setFilterPreset('')}
          className={`shrink-0 px-4 py-2 rounded-xl text-xs font-medium transition-all ${
            !filterPreset ? 'bg-violet/20 text-violet-light border border-violet/30' : 'bg-surface2/50 text-muted border border-transparent hover:border-border/50'
          }`}
        >
          Tous
        </button>
        {Object.entries(PRESET_CONFIG).map(([key, cfg]) => (
          <button
            key={key}
            onClick={() => setFilterPreset(key)}
            className={`shrink-0 px-4 py-2 rounded-xl text-xs font-medium transition-all flex items-center gap-1.5 ${
              filterPreset === key ? 'bg-violet/20 text-violet-light border border-violet/30' : 'bg-surface2/50 text-muted border border-transparent hover:border-border/50'
            }`}
          >
            <span>{cfg.emoji}</span> {cfg.label}
          </button>
        ))}
      </div>

      {/* Templates grid */}
      {loading ? (
        <div className="flex items-center justify-center h-40 text-muted">
          <div className="w-5 h-5 border-2 border-violet/30 border-t-violet rounded-full animate-spin mr-3" />
          Chargement...
        </div>
      ) : templates.length > 0 ? (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {templates.map(t => {
            const cfg = PRESET_CONFIG[t.preset_type] || PRESET_CONFIG.custom;
            const progress = t.total_items > 0 ? Math.round(((t.generated_items + t.published_items) / t.total_items) * 100) : 0;

            return (
              <div
                key={t.id}
                className={`group relative bg-gradient-to-br ${cfg.gradient} border border-border/30 rounded-2xl p-5 hover:border-violet/30 hover:shadow-xl hover:shadow-violet/5 transition-all cursor-pointer`}
                onClick={() => navigate(`/content/templates/${t.id}`)}
              >
                {/* Header */}
                <div className="flex items-start justify-between mb-3">
                  <div className="flex items-center gap-2.5">
                    <span className="text-2xl">{cfg.emoji}</span>
                    <div>
                      <h3 className="text-sm font-bold text-white group-hover:text-violet-light transition-colors">{t.name}</h3>
                      <p className="text-[10px] text-muted uppercase tracking-wider">{cfg.label}</p>
                    </div>
                  </div>
                  <button
                    onClick={e => { e.stopPropagation(); handleDelete(t.id, t.name); }}
                    className="opacity-0 group-hover:opacity-100 text-muted hover:text-danger transition-all text-xs"
                  >
                    ✕
                  </button>
                </div>

                {/* Title template */}
                <p className="text-xs font-mono text-muted/80 mb-4 line-clamp-1 bg-bg/30 rounded-lg px-2.5 py-1.5">
                  {t.title_template}
                </p>

                {/* Stats */}
                <div className="grid grid-cols-3 gap-2 mb-3">
                  <div className="text-center">
                    <p className="text-lg font-bold text-white">{t.total_items}</p>
                    <p className="text-[10px] text-muted">Total</p>
                  </div>
                  <div className="text-center">
                    <p className="text-lg font-bold text-success">{(t.generated_items || 0) + (t.published_items || 0)}</p>
                    <p className="text-[10px] text-muted">Generes</p>
                  </div>
                  <div className="text-center">
                    <p className="text-lg font-bold text-amber">{t.pending_count ?? (t.total_items - (t.generated_items || 0) - (t.published_items || 0) - (t.failed_items || 0))}</p>
                    <p className="text-[10px] text-muted">En attente</p>
                  </div>
                </div>

                {/* Progress bar */}
                <div className="w-full bg-bg/40 rounded-full h-1.5 overflow-hidden">
                  <div
                    className="h-full rounded-full bg-gradient-to-r from-violet to-success transition-all duration-500"
                    style={{ width: `${progress}%` }}
                  />
                </div>
                <p className="text-[10px] text-muted text-right mt-1">{progress}%</p>
              </div>
            );
          })}
        </div>
      ) : (
        <div className="text-center py-16 text-muted">
          <p className="text-4xl mb-3">📋</p>
          <p className="text-sm">Aucun template. Creez votre premier template pour commencer.</p>
        </div>
      )}
    </div>
  );
}
