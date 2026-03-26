import { useEffect, useState, type FormEvent } from 'react';
import * as contentApi from '../../api/contentApi';
import type { GenerationPreset } from '../../types/content';

const CONTENT_TYPES = [
  { value: 'article', label: 'Article', color: 'bg-violet/20 text-violet-light' },
  { value: 'comparative', label: 'Comparatif', color: 'bg-blue-500/20 text-blue-400' },
  { value: 'landing', label: 'Landing', color: 'bg-amber/20 text-amber' },
  { value: 'press', label: 'Presse', color: 'bg-success/20 text-success' },
];

const TONES = ['professional', 'casual', 'expert', 'friendly'] as const;
const LENGTHS = ['short', 'medium', 'long'] as const;
const MODELS = ['gpt-4o', 'gpt-4o-mini'] as const;
const IMAGE_SOURCES = ['unsplash', 'dalle', 'none'] as const;
const LANGUAGES = [
  { code: 'fr', label: 'Francais' },
  { code: 'en', label: 'Anglais' },
  { code: 'de', label: 'Allemand' },
  { code: 'es', label: 'Espagnol' },
  { code: 'pt', label: 'Portugais' },
  { code: 'ru', label: 'Russe' },
  { code: 'zh', label: 'Chinois' },
  { code: 'ar', label: 'Arabe' },
  { code: 'hi', label: 'Hindi' },
];

function getContentTypeBadge(type: string) {
  const ct = CONTENT_TYPES.find(c => c.value === type);
  return (
    <span className={`px-2 py-0.5 rounded text-xs font-medium ${ct?.color ?? 'bg-muted/20 text-muted'}`}>
      {ct?.label ?? type}
    </span>
  );
}

interface PresetConfig {
  model: string;
  tone: string;
  length: string;
  faq_count: number;
  research: boolean;
  image_source: string;
  internal_links: boolean;
  affiliate_links: boolean;
  translation_languages: string[];
}

interface PresetForm {
  name: string;
  description: string;
  content_type: string;
  is_default: boolean;
  config: PresetConfig;
}

const defaultConfig: PresetConfig = {
  model: 'gpt-4o',
  tone: 'professional',
  length: 'medium',
  faq_count: 8,
  research: true,
  image_source: 'unsplash',
  internal_links: true,
  affiliate_links: true,
  translation_languages: [],
};

const emptyForm: PresetForm = {
  name: '',
  description: '',
  content_type: 'article',
  is_default: false,
  config: { ...defaultConfig },
};

export default function GenerationPresetsPage() {
  const [presets, setPresets] = useState<GenerationPreset[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<PresetForm>(emptyForm);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  useEffect(() => {
    loadPresets();
  }, []);

  const loadPresets = async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await contentApi.fetchPresets();
      setPresets(data);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Erreur chargement';
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = () => {
    setEditingId(null);
    setForm({ ...emptyForm, config: { ...defaultConfig } });
    setShowForm(true);
  };

  const handleEdit = (preset: GenerationPreset) => {
    const cfg = preset.config as Record<string, unknown>;
    setEditingId(preset.id);
    setForm({
      name: preset.name,
      description: preset.description ?? '',
      content_type: preset.content_type,
      is_default: preset.is_default,
      config: {
        model: (cfg.model as string) ?? 'gpt-4o',
        tone: (cfg.tone as string) ?? 'professional',
        length: (cfg.length as string) ?? 'medium',
        faq_count: (cfg.faq_count as number) ?? 8,
        research: (cfg.research as boolean) ?? true,
        image_source: (cfg.image_source as string) ?? 'unsplash',
        internal_links: (cfg.internal_links as boolean) ?? true,
        affiliate_links: (cfg.affiliate_links as boolean) ?? true,
        translation_languages: (cfg.translation_languages as string[]) ?? [],
      },
    });
    setShowForm(true);
  };

  const handleSave = async (e: FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setSaveError(null);
    try {
      const payload = {
        name: form.name,
        description: form.description || null,
        content_type: form.content_type,
        is_default: form.is_default,
        config: form.config as unknown as Record<string, unknown>,
      };
      if (editingId) {
        await contentApi.updatePreset(editingId, payload);
      } else {
        await contentApi.createPreset(payload);
      }
      setShowForm(false);
      setEditingId(null);
      await loadPresets();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Erreur sauvegarde';
      setSaveError(message);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Supprimer ce preset ?')) return;
    try {
      await contentApi.deletePreset(id);
      setPresets(prev => prev.filter(p => p.id !== id));
    } catch { /* silent */ }
  };

  const updateConfig = (field: keyof PresetConfig, value: unknown) => {
    setForm(f => ({
      ...f,
      config: { ...f.config, [field]: value },
    }));
  };

  const toggleLang = (code: string) => {
    setForm(f => ({
      ...f,
      config: {
        ...f.config,
        translation_languages: f.config.translation_languages.includes(code)
          ? f.config.translation_languages.filter(l => l !== code)
          : [...f.config.translation_languages, code],
      },
    }));
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-violet" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="font-title text-2xl font-bold text-white">Presets de generation</h1>
        <button
          onClick={handleCreate}
          className="px-4 py-2 bg-violet hover:bg-violet/90 text-white rounded-lg text-sm font-medium transition"
        >
          + Nouveau preset
        </button>
      </div>

      {error && <p className="text-danger text-sm">{error}</p>}

      {/* Preset cards grid */}
      {!showForm && (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {presets.length === 0 ? (
            <p className="text-muted text-sm col-span-full text-center py-12">Aucun preset.</p>
          ) : (
            presets.map(preset => {
              const cfg = preset.config as Record<string, unknown>;
              return (
                <div key={preset.id} className="bg-surface rounded-xl p-5 border border-border">
                  <div className="flex items-start justify-between mb-3">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <h3 className="text-white font-medium truncate">{preset.name}</h3>
                        {preset.is_default && (
                          <span className="text-amber text-sm" title="Preset par defaut">
                            &#9733;
                          </span>
                        )}
                      </div>
                      <div className="mt-1">{getContentTypeBadge(preset.content_type)}</div>
                    </div>
                  </div>

                  {preset.description && (
                    <p className="text-muted text-sm mb-3 line-clamp-2">{preset.description}</p>
                  )}

                  <div className="flex flex-wrap gap-1.5 mb-3">
                    {cfg.model && (
                      <span className="px-1.5 py-0.5 bg-surface2 text-muted rounded text-xs">
                        {String(cfg.model)}
                      </span>
                    )}
                    {cfg.tone && (
                      <span className="px-1.5 py-0.5 bg-surface2 text-muted rounded text-xs">
                        {String(cfg.tone)}
                      </span>
                    )}
                    {cfg.length && (
                      <span className="px-1.5 py-0.5 bg-surface2 text-muted rounded text-xs">
                        {String(cfg.length)}
                      </span>
                    )}
                    {cfg.research && (
                      <span className="px-1.5 py-0.5 bg-blue-500/20 text-blue-400 rounded text-xs">
                        research
                      </span>
                    )}
                    {cfg.internal_links && (
                      <span className="px-1.5 py-0.5 bg-success/20 text-success rounded text-xs">
                        liens internes
                      </span>
                    )}
                    {cfg.affiliate_links && (
                      <span className="px-1.5 py-0.5 bg-amber/20 text-amber rounded text-xs">
                        affilies
                      </span>
                    )}
                  </div>

                  <div className="flex gap-2">
                    <button
                      onClick={() => handleEdit(preset)}
                      className="px-3 py-1 text-xs bg-surface2 hover:bg-surface2/80 text-white rounded transition"
                    >
                      Modifier
                    </button>
                    <button
                      onClick={() => handleDelete(preset.id)}
                      className="px-3 py-1 text-xs bg-danger/20 hover:bg-danger/40 text-danger rounded transition"
                    >
                      Supprimer
                    </button>
                  </div>
                </div>
              );
            })
          )}
        </div>
      )}

      {/* Create/Edit form */}
      {showForm && (
        <div className="bg-surface rounded-xl p-6 border border-border">
          <h2 className="text-lg font-semibold text-white mb-4">
            {editingId ? 'Modifier preset' : 'Nouveau preset'}
          </h2>
          <form onSubmit={handleSave} className="space-y-5">
            {/* Name, Description, Content type */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="text-xs text-muted block mb-1">Nom</label>
                <input
                  type="text"
                  value={form.name}
                  onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                  required
                  className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                />
              </div>
              <div>
                <label className="text-xs text-muted block mb-1">Type de contenu</label>
                <select
                  value={form.content_type}
                  onChange={e => setForm(f => ({ ...f, content_type: e.target.value }))}
                  className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                >
                  {CONTENT_TYPES.map(ct => (
                    <option key={ct.value} value={ct.value}>{ct.label}</option>
                  ))}
                </select>
              </div>
            </div>
            <div>
              <label className="text-xs text-muted block mb-1">Description</label>
              <textarea
                value={form.description}
                onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
                rows={2}
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet resize-none"
              />
            </div>

            {/* Config section */}
            <div className="pt-4 border-t border-border">
              <h3 className="text-white font-medium mb-4">Configuration</h3>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                  <label className="text-xs text-muted block mb-1">Modele</label>
                  <select
                    value={form.config.model}
                    onChange={e => updateConfig('model', e.target.value)}
                    className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                  >
                    {MODELS.map(m => (
                      <option key={m} value={m}>{m}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="text-xs text-muted block mb-1">Ton</label>
                  <select
                    value={form.config.tone}
                    onChange={e => updateConfig('tone', e.target.value)}
                    className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                  >
                    {TONES.map(t => (
                      <option key={t} value={t}>{t}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="text-xs text-muted block mb-1">Longueur</label>
                  <select
                    value={form.config.length}
                    onChange={e => updateConfig('length', e.target.value)}
                    className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                  >
                    {LENGTHS.map(l => (
                      <option key={l} value={l}>{l}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                  <label className="text-xs text-muted block mb-1">
                    Nombre de FAQ: {form.config.faq_count}
                  </label>
                  <input
                    type="range"
                    min={4}
                    max={20}
                    step={1}
                    value={form.config.faq_count}
                    onChange={e => updateConfig('faq_count', Number(e.target.value))}
                    className="w-full mt-1 accent-violet"
                  />
                  <div className="flex justify-between text-xs text-muted mt-1">
                    <span>4</span>
                    <span>20</span>
                  </div>
                </div>
                <div>
                  <label className="text-xs text-muted block mb-1">Source d'images</label>
                  <select
                    value={form.config.image_source}
                    onChange={e => updateConfig('image_source', e.target.value)}
                    className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                  >
                    {IMAGE_SOURCES.map(s => (
                      <option key={s} value={s}>{s}</option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Checkboxes */}
              <div className="flex flex-wrap gap-6 mb-4">
                <label className="flex items-center gap-2 text-sm text-white">
                  <input
                    type="checkbox"
                    checked={form.config.research}
                    onChange={e => updateConfig('research', e.target.checked)}
                    className="rounded border-border bg-surface2 text-violet focus:ring-violet"
                  />
                  Recherche de sources
                </label>
                <label className="flex items-center gap-2 text-sm text-white">
                  <input
                    type="checkbox"
                    checked={form.config.internal_links}
                    onChange={e => updateConfig('internal_links', e.target.checked)}
                    className="rounded border-border bg-surface2 text-violet focus:ring-violet"
                  />
                  Liens internes
                </label>
                <label className="flex items-center gap-2 text-sm text-white">
                  <input
                    type="checkbox"
                    checked={form.config.affiliate_links}
                    onChange={e => updateConfig('affiliate_links', e.target.checked)}
                    className="rounded border-border bg-surface2 text-violet focus:ring-violet"
                  />
                  Liens affilies
                </label>
                <label className="flex items-center gap-2 text-sm text-white">
                  <input
                    type="checkbox"
                    checked={form.is_default}
                    onChange={e => setForm(f => ({ ...f, is_default: e.target.checked }))}
                    className="rounded border-border bg-surface2 text-amber focus:ring-amber"
                  />
                  Preset par defaut
                </label>
              </div>

              {/* Translation languages */}
              <div>
                <label className="text-xs text-muted block mb-2">Langues de traduction par defaut</label>
                <div className="flex flex-wrap gap-2">
                  {LANGUAGES.map(lang => (
                    <button
                      key={lang.code}
                      type="button"
                      onClick={() => toggleLang(lang.code)}
                      className={`px-3 py-1.5 rounded-lg text-xs font-medium transition ${
                        form.config.translation_languages.includes(lang.code)
                          ? 'bg-violet text-white'
                          : 'bg-surface2 text-muted hover:bg-surface2/80'
                      }`}
                    >
                      {lang.label} ({lang.code.toUpperCase()})
                    </button>
                  ))}
                </div>
              </div>
            </div>

            {saveError && <p className="text-danger text-sm">{saveError}</p>}

            <div className="flex gap-3 pt-2">
              <button
                type="submit"
                disabled={saving}
                className="px-4 py-2 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition"
              >
                {saving ? 'Sauvegarde...' : (editingId ? 'Mettre a jour' : 'Creer')}
              </button>
              <button
                type="button"
                onClick={() => { setShowForm(false); setEditingId(null); }}
                className="px-4 py-2 bg-surface2 hover:bg-surface2/80 text-white rounded-lg text-sm transition"
              >
                Annuler
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
