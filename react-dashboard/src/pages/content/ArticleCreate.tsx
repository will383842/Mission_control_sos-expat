import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useContentGeneration, useCosts } from '../../hooks/useContentEngine';
import { fetchPresets } from '../../api/contentApi';
import type { GenerateArticleParams, ContentType, GenerationPreset } from '../../types/content';

// ── Constants ───────────────────────────────────────────────
const LANGUAGE_OPTIONS = [
  { value: 'fr', label: 'Francais' },
  { value: 'en', label: 'English' },
  { value: 'de', label: 'Deutsch' },
  { value: 'es', label: 'Espanol' },
  { value: 'pt', label: 'Portugues' },
  { value: 'ru', label: 'Russkiy' },
  { value: 'zh', label: 'Zhongwen' },
  { value: 'ar', label: 'Arabiya' },
  { value: 'hi', label: 'Hindi' },
];

const TYPE_OPTIONS: { value: ContentType; label: string }[] = [
  { value: 'article', label: 'Article' },
  { value: 'guide', label: 'Guide' },
  { value: 'news', label: 'Actualite' },
  { value: 'tutorial', label: 'Tutoriel' },
];

const TONE_OPTIONS = [
  { value: 'professional', label: 'Professionnel' },
  { value: 'casual', label: 'Decontracte' },
  { value: 'expert', label: 'Expert' },
  { value: 'friendly', label: 'Amical' },
];

const LENGTH_OPTIONS = [
  { value: 'short', label: 'Court (~800 mots)' },
  { value: 'medium', label: 'Moyen (~1500 mots)' },
  { value: 'long', label: 'Long (~2500 mots)' },
];

const TRANSLATION_LANGUAGES = [
  { value: 'fr', label: 'FR' },
  { value: 'en', label: 'EN' },
  { value: 'de', label: 'DE' },
  { value: 'es', label: 'ES' },
  { value: 'pt', label: 'PT' },
  { value: 'ru', label: 'RU' },
  { value: 'zh', label: 'ZH' },
  { value: 'ar', label: 'AR' },
  { value: 'hi', label: 'HI' },
];

const inputClass = 'w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

function estimateCost(length: string, generateFaq: boolean, translations: string[], dalleImages: boolean): number {
  let base = length === 'short' ? 8 : length === 'long' ? 25 : 15;
  if (generateFaq) base += 3;
  if (translations.length > 0) base += translations.length * 6;
  if (dalleImages) base += 10;
  return base;
}

// ── Budget gauge ────────────────────────────────────────────
function BudgetGauge({ label, used, max }: { label: string; used: number; max: number }) {
  const pct = max > 0 ? Math.min((used / max) * 100, 100) : 0;
  const color = pct >= 90 ? 'bg-danger' : pct >= 70 ? 'bg-amber' : 'bg-violet';
  return (
    <div>
      <div className="flex justify-between text-xs text-muted mb-1">
        <span>{label}</span>
        <span>${(used / 100).toFixed(2)} / ${(max / 100).toFixed(2)}</span>
      </div>
      <div className="h-2 bg-surface2 rounded-full overflow-hidden">
        <div className={`h-full ${color} rounded-full transition-all`} style={{ width: `${pct}%` }} />
      </div>
    </div>
  );
}

// ── Component ───────────────────────────────────────────────
export default function ArticleCreate() {
  const navigate = useNavigate();
  const { generating, error: genError, generateArticle } = useContentGeneration();
  const { overview, load: loadCosts } = useCosts();

  // Form state
  const [topic, setTopic] = useState('');
  const [language, setLanguage] = useState('fr');
  const [country, setCountry] = useState('');
  const [contentType, setContentType] = useState<ContentType>('article');
  const [keywords, setKeywords] = useState<string[]>([]);
  const [keywordInput, setKeywordInput] = useState('');
  const [instructions, setInstructions] = useState('');
  const [presetId, setPresetId] = useState<number | undefined>(undefined);
  const [tone, setTone] = useState<'professional' | 'casual' | 'expert' | 'friendly'>('professional');
  const [length, setLength] = useState<'short' | 'medium' | 'long'>('medium');

  // Advanced options
  const [showAdvanced, setShowAdvanced] = useState(false);
  const [generateFaq, setGenerateFaq] = useState(true);
  const [faqCount, setFaqCount] = useState(8);
  const [researchSources, setResearchSources] = useState(true);
  const [imageSource, setImageSource] = useState<'unsplash' | 'dalle' | 'none'>('unsplash');
  const [autoInternalLinks, setAutoInternalLinks] = useState(true);
  const [autoAffiliateLinks, setAutoAffiliateLinks] = useState(true);
  const [autoTranslations, setAutoTranslations] = useState(false);
  const [translationLanguages, setTranslationLanguages] = useState<string[]>([]);

  // Presets
  const [presets, setPresets] = useState<GenerationPreset[]>([]);

  useEffect(() => {
    loadCosts();
    fetchPresets().then(res => setPresets(res.data)).catch(() => {});
  }, [loadCosts]);

  const handleKeywordAdd = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' && keywordInput.trim()) {
      e.preventDefault();
      if (!keywords.includes(keywordInput.trim())) {
        setKeywords([...keywords, keywordInput.trim()]);
      }
      setKeywordInput('');
    }
  };

  const removeKeyword = (kw: string) => {
    setKeywords(keywords.filter(k => k !== kw));
  };

  const toggleTranslationLang = (lang: string) => {
    setTranslationLanguages(prev =>
      prev.includes(lang) ? prev.filter(l => l !== lang) : [...prev, lang]
    );
  };

  const applyPreset = (preset: GenerationPreset) => {
    setPresetId(preset.id);
    const c = preset.config as Record<string, unknown>;
    if (c.tone) setTone(c.tone as typeof tone);
    if (c.length) setLength(c.length as typeof length);
    if (c.content_type) setContentType(c.content_type as ContentType);
    if (c.generate_faq !== undefined) setGenerateFaq(!!c.generate_faq);
    if (c.research_sources !== undefined) setResearchSources(!!c.research_sources);
    if (c.image_source) setImageSource(c.image_source as typeof imageSource);
  };

  const costEstimate = estimateCost(length, generateFaq, autoTranslations ? translationLanguages : [], imageSource === 'dalle');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!topic.trim()) return;

    const params: GenerateArticleParams = {
      topic: topic.trim(),
      language,
      country: country || undefined,
      content_type: contentType,
      keywords: keywords.length > 0 ? keywords : undefined,
      instructions: instructions || undefined,
      tone,
      length,
      generate_faq: generateFaq,
      faq_count: generateFaq ? faqCount : undefined,
      research_sources: researchSources,
      image_source: imageSource,
      auto_internal_links: autoInternalLinks,
      auto_affiliate_links: autoAffiliateLinks,
      translation_languages: autoTranslations && translationLanguages.length > 0 ? translationLanguages : undefined,
      preset_id: presetId,
    };

    const article = await generateArticle(params);
    if (article) {
      navigate(`/content/articles/${article.id}`);
    }
  };

  return (
    <div className="p-4 md:p-6">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm text-muted mb-4">
        <button onClick={() => navigate('/content/overview')} className="hover:text-white transition-colors">Contenu</button>
        <span>/</span>
        <button onClick={() => navigate('/content/articles')} className="hover:text-white transition-colors">Articles</button>
        <span>/</span>
        <span className="text-white">Nouveau</span>
      </div>

      <h2 className="font-title text-2xl font-bold text-white mb-6">Generer un article</h2>

      <div className="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-6">
        {/* LEFT: Form */}
        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Error */}
          {genError && (
            <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-lg">
              {genError}
            </div>
          )}

          {/* Topic */}
          <div>
            <label className="block text-xs text-muted mb-1">Sujet / Titre *</label>
            <input
              type="text"
              value={topic}
              onChange={e => setTopic(e.target.value)}
              placeholder="Ex: Guide complet de l'expatriation en Allemagne"
              className={`${inputClass} text-base py-3`}
              required
            />
          </div>

          {/* Language, Country, Type */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label className="block text-xs text-muted mb-1">Langue *</label>
              <select value={language} onChange={e => setLanguage(e.target.value)} className={inputClass}>
                {LANGUAGE_OPTIONS.map(o => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Pays</label>
              <input
                type="text"
                value={country}
                onChange={e => setCountry(e.target.value)}
                placeholder="Ex: Allemagne"
                className={inputClass}
              />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Type</label>
              <select value={contentType} onChange={e => setContentType(e.target.value as ContentType)} className={inputClass}>
                {TYPE_OPTIONS.map(o => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </select>
            </div>
          </div>

          {/* Keywords */}
          <div>
            <label className="block text-xs text-muted mb-1">Mots-cles (Entree pour ajouter)</label>
            <div className="flex flex-wrap items-center gap-2 bg-bg border border-border rounded-lg px-3 py-2 min-h-[40px]">
              {keywords.map(kw => (
                <span
                  key={kw}
                  className="inline-flex items-center gap-1 bg-violet/20 text-violet-light text-xs px-2 py-1 rounded"
                >
                  {kw}
                  <button
                    type="button"
                    onClick={() => removeKeyword(kw)}
                    className="text-violet hover:text-white transition-colors"
                  >
                    x
                  </button>
                </span>
              ))}
              <input
                type="text"
                value={keywordInput}
                onChange={e => setKeywordInput(e.target.value)}
                onKeyDown={handleKeywordAdd}
                placeholder={keywords.length === 0 ? 'expatriation, visa, demarches...' : ''}
                className="flex-1 bg-transparent text-white text-sm outline-none min-w-[100px]"
              />
            </div>
          </div>

          {/* Instructions */}
          <div>
            <label className="block text-xs text-muted mb-1">Instructions (optionnel)</label>
            <textarea
              value={instructions}
              onChange={e => setInstructions(e.target.value)}
              rows={3}
              placeholder="Instructions supplementaires pour l'IA..."
              className={inputClass}
            />
          </div>

          {/* Preset, Tone, Length */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label className="block text-xs text-muted mb-1">Preset</label>
              <select
                value={presetId || ''}
                onChange={e => {
                  const id = Number(e.target.value);
                  const preset = presets.find(p => p.id === id);
                  if (preset) applyPreset(preset);
                  else setPresetId(undefined);
                }}
                className={inputClass}
              >
                <option value="">Aucun preset</option>
                {presets.map(p => (
                  <option key={p.id} value={p.id}>{p.name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Ton</label>
              <select value={tone} onChange={e => setTone(e.target.value as typeof tone)} className={inputClass}>
                {TONE_OPTIONS.map(o => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Longueur</label>
              <select value={length} onChange={e => setLength(e.target.value as typeof length)} className={inputClass}>
                {LENGTH_OPTIONS.map(o => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </select>
            </div>
          </div>

          {/* Advanced options */}
          <div className="bg-surface border border-border rounded-xl overflow-hidden">
            <button
              type="button"
              onClick={() => setShowAdvanced(!showAdvanced)}
              className="w-full flex items-center justify-between px-5 py-3 text-sm text-white hover:bg-surface2 transition-colors"
            >
              <span>Options avancees</span>
              <svg
                className={`w-4 h-4 text-muted transition-transform ${showAdvanced ? 'rotate-180' : ''}`}
                fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}
              >
                <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            {showAdvanced && (
              <div className="px-5 pb-5 space-y-4 border-t border-border pt-4">
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                  <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                    <input type="checkbox" checked={generateFaq} onChange={e => setGenerateFaq(e.target.checked)} className="accent-violet" />
                    Generer FAQ
                  </label>
                  <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                    <input type="checkbox" checked={researchSources} onChange={e => setResearchSources(e.target.checked)} className="accent-violet" />
                    Rechercher sources
                  </label>
                  <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                    <input type="checkbox" checked={imageSource === 'unsplash'} onChange={e => setImageSource(e.target.checked ? 'unsplash' : 'none')} className="accent-violet" />
                    Images Unsplash
                  </label>
                  <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                    <input type="checkbox" checked={imageSource === 'dalle'} onChange={e => setImageSource(e.target.checked ? 'dalle' : 'none')} className="accent-violet" />
                    Images DALL-E
                  </label>
                  <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                    <input type="checkbox" checked={autoInternalLinks} onChange={e => setAutoInternalLinks(e.target.checked)} className="accent-violet" />
                    Liens internes
                  </label>
                  <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                    <input type="checkbox" checked={autoAffiliateLinks} onChange={e => setAutoAffiliateLinks(e.target.checked)} className="accent-violet" />
                    Liens affilies
                  </label>
                </div>

                {/* FAQ count slider */}
                {generateFaq && (
                  <div>
                    <label className="block text-xs text-muted mb-1">Nombre de FAQ: {faqCount}</label>
                    <input
                      type="range"
                      min={4}
                      max={20}
                      value={faqCount}
                      onChange={e => setFaqCount(Number(e.target.value))}
                      className="w-full accent-violet"
                    />
                  </div>
                )}

                {/* Auto translations */}
                <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                  <input type="checkbox" checked={autoTranslations} onChange={e => setAutoTranslations(e.target.checked)} className="accent-violet" />
                  Traductions automatiques
                </label>

                {autoTranslations && (
                  <div className="flex flex-wrap gap-2">
                    {TRANSLATION_LANGUAGES.filter(l => l.value !== language).map(l => (
                      <label
                        key={l.value}
                        className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg border cursor-pointer text-xs transition-colors ${
                          translationLanguages.includes(l.value)
                            ? 'bg-violet/20 border-violet text-violet-light'
                            : 'bg-surface2 border-border text-muted hover:text-white'
                        }`}
                      >
                        <input
                          type="checkbox"
                          checked={translationLanguages.includes(l.value)}
                          onChange={() => toggleTranslationLang(l.value)}
                          className="hidden"
                        />
                        {l.label}
                      </label>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Cost estimate + Generate */}
          <div className="flex items-center justify-between pt-2">
            <span className="text-sm text-muted">
              Cout estime: ~${(costEstimate / 100).toFixed(2)}
            </span>
            <button
              type="submit"
              disabled={generating || !topic.trim()}
              className="px-8 py-3 bg-violet hover:bg-violet/90 text-white font-semibold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {generating ? (
                <span className="inline-flex items-center gap-2">
                  <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                  Generation...
                </span>
              ) : (
                'Generer l\'article'
              )}
            </button>
          </div>
        </form>

        {/* RIGHT: Sidebar */}
        <div className="space-y-4">
          {/* Quick presets */}
          {presets.length > 0 && (
            <div className="bg-surface border border-border rounded-xl p-5">
              <h3 className="font-title font-semibold text-white text-sm mb-3">Presets rapides</h3>
              <div className="space-y-2">
                {presets.slice(0, 5).map(preset => (
                  <button
                    key={preset.id}
                    onClick={() => applyPreset(preset)}
                    className={`w-full text-left px-3 py-2 rounded-lg border text-sm transition-colors ${
                      presetId === preset.id
                        ? 'bg-violet/20 border-violet text-white'
                        : 'bg-surface2 border-border text-muted hover:text-white hover:border-border'
                    }`}
                  >
                    <span className="font-medium">{preset.name}</span>
                    {preset.description && (
                      <p className="text-xs text-muted mt-0.5 truncate">{preset.description}</p>
                    )}
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Budget gauge */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white text-sm mb-3">Budget IA</h3>
            {overview ? (
              <div className="space-y-3">
                <BudgetGauge label="Aujourd'hui" used={overview.today_cents} max={overview.daily_budget_cents} />
                <BudgetGauge label="Ce mois" used={overview.this_month_cents} max={overview.monthly_budget_cents} />
              </div>
            ) : (
              <p className="text-sm text-muted">Chargement...</p>
            )}
          </div>

          {/* Tips */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white text-sm mb-3">Conseils</h3>
            <ul className="space-y-2 text-xs text-muted">
              <li>- Un sujet precis genere de meilleurs resultats</li>
              <li>- Ajoutez 3-5 mots-cles pour optimiser le SEO</li>
              <li>- Le mode "Expert" produit un contenu plus technique</li>
              <li>- Les traductions sont generees apres l'article principal</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}
