import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { createCampaign } from '../../api/contentApi';
import { useCosts } from '../../hooks/useContentEngine';
import type { CampaignType, CampaignConfig, ContentCampaign } from '../../types/content';

// ── Constants ───────────────────────────────────────────────
const CAMPAIGN_TYPES: { value: CampaignType; label: string; description: string }[] = [
  { value: 'country_coverage', label: 'Couverture pays', description: 'Generer des articles pour un pays dans plusieurs langues et themes.' },
  { value: 'thematic', label: 'Thematique', description: 'Serie d\'articles autour d\'un theme specifique.' },
  { value: 'pillar_cluster', label: 'Pilier + clusters', description: 'Un article pilier et ses articles satellites (SEO hub).' },
  { value: 'comparative_series', label: 'Serie de comparatifs', description: 'Plusieurs comparatifs generes en serie.' },
  { value: 'custom', label: 'Personnalise', description: 'Liste libre de titres a generer.' },
];

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

const THEME_PRESETS = [
  'Visa & Immigration', 'Fiscalite', 'Logement', 'Sante', 'Education',
  'Emploi', 'Droit du travail', 'Banque & Finance', 'Vie quotidienne', 'Culture',
];

const inputClass = 'w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

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
export default function CampaignCreate() {
  const navigate = useNavigate();
  const { overview, load: loadCosts } = useCosts();

  const [name, setName] = useState('');
  const [campaignType, setCampaignType] = useState<CampaignType>('country_coverage');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Config fields by type
  const [country, setCountry] = useState('');
  const [themes, setThemes] = useState<string[]>([]);
  const [languages, setLanguages] = useState<string[]>(['fr']);
  const [articlesPerDay, setArticlesPerDay] = useState(3);
  const [startDate, setStartDate] = useState('');

  // Thematic
  const [themeTopics, setThemeTopics] = useState<string[]>([]);
  const [themeTopicInput, setThemeTopicInput] = useState('');

  // Pillar/cluster
  const [pillarTopic, setPillarTopic] = useState('');
  const [clusterCount, setClusterCount] = useState(5);

  // Comparative series
  const [comparisonTopics, setComparisonTopics] = useState<string[]>(['']);

  // Custom
  const [customTitles, setCustomTitles] = useState<string[]>(['']);

  useEffect(() => {
    loadCosts();
  }, [loadCosts]);

  const toggleTheme = (theme: string) => {
    setThemes(prev => prev.includes(theme) ? prev.filter(t => t !== theme) : [...prev, theme]);
  };

  const toggleLanguage = (lang: string) => {
    setLanguages(prev => prev.includes(lang) ? prev.filter(l => l !== lang) : [...prev, lang]);
  };

  const handleThemeTopicAdd = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' && themeTopicInput.trim()) {
      e.preventDefault();
      if (!themeTopics.includes(themeTopicInput.trim())) {
        setThemeTopics([...themeTopics, themeTopicInput.trim()]);
      }
      setThemeTopicInput('');
    }
  };

  const addComparisonTopic = () => setComparisonTopics([...comparisonTopics, '']);
  const updateComparisonTopic = (i: number, v: string) => setComparisonTopics(comparisonTopics.map((t, idx) => idx === i ? v : t));
  const removeComparisonTopic = (i: number) => {
    if (comparisonTopics.length > 1) setComparisonTopics(comparisonTopics.filter((_, idx) => idx !== i));
  };

  const addCustomTitle = () => setCustomTitles([...customTitles, '']);
  const updateCustomTitle = (i: number, v: string) => setCustomTitles(customTitles.map((t, idx) => idx === i ? v : t));
  const removeCustomTitle = (i: number) => {
    if (customTitles.length > 1) setCustomTitles(customTitles.filter((_, idx) => idx !== i));
  };

  // Estimate article count
  const estimateItemCount = (): number => {
    switch (campaignType) {
      case 'country_coverage': return themes.length * languages.length;
      case 'thematic': return themeTopics.length * languages.length;
      case 'pillar_cluster': return 1 + clusterCount;
      case 'comparative_series': return comparisonTopics.filter(t => t.trim()).length;
      case 'custom': return customTitles.filter(t => t.trim()).length;
      default: return 0;
    }
  };

  const itemCount = estimateItemCount();
  const budgetEstimate = itemCount * 15; // ~$0.15 per article average

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim() || itemCount === 0) return;

    setSubmitting(true);
    setError(null);

    const config: CampaignConfig = { languages };

    switch (campaignType) {
      case 'country_coverage':
        config.country = country;
        config.themes = themes;
        config.articles_per_day = articlesPerDay;
        break;
      case 'thematic':
        (config as Record<string, unknown>).topics = themeTopics;
        config.articles_per_day = articlesPerDay;
        break;
      case 'pillar_cluster':
        (config as Record<string, unknown>).pillar_topic = pillarTopic;
        (config as Record<string, unknown>).cluster_count = clusterCount;
        break;
      case 'comparative_series':
        (config as Record<string, unknown>).topics = comparisonTopics.filter(t => t.trim());
        break;
      case 'custom':
        (config as Record<string, unknown>).titles = customTitles.filter(t => t.trim());
        break;
    }

    try {
      const payload: Partial<ContentCampaign> = {
        name: name.trim(),
        campaign_type: campaignType,
        config,
      };
      if (startDate) (payload as Record<string, unknown>).scheduled_start = startDate;

      const { data } = await createCampaign(payload);
      navigate(`/content/campaigns/${data.id}`);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Erreur lors de la creation');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="p-4 md:p-6">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm text-muted mb-4">
        <button onClick={() => navigate('/content/overview')} className="hover:text-white transition-colors">Contenu</button>
        <span>/</span>
        <button onClick={() => navigate('/content/campaigns')} className="hover:text-white transition-colors">Campagnes</button>
        <span>/</span>
        <span className="text-white">Nouvelle</span>
      </div>

      <h2 className="font-title text-2xl font-bold text-white mb-6">Creer une campagne</h2>

      <div className="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-6">
        {/* LEFT: Form */}
        <form onSubmit={handleSubmit} className="space-y-5">
          {error && (
            <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-lg">{error}</div>
          )}

          {/* Name */}
          <div>
            <label className="block text-xs text-muted mb-1">Nom de la campagne *</label>
            <input
              type="text"
              value={name}
              onChange={e => setName(e.target.value)}
              placeholder="Ex: Couverture Allemagne Q1 2026"
              className={`${inputClass} text-base py-3`}
              required
            />
          </div>

          {/* Type */}
          <div>
            <label className="block text-xs text-muted mb-2">Type de campagne</label>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
              {CAMPAIGN_TYPES.map(type => (
                <button
                  key={type.value}
                  type="button"
                  onClick={() => setCampaignType(type.value)}
                  className={`text-left px-4 py-3 rounded-lg border transition-colors ${
                    campaignType === type.value
                      ? 'bg-violet/20 border-violet text-white'
                      : 'bg-surface2 border-border text-muted hover:text-white'
                  }`}
                >
                  <span className="block text-sm font-medium">{type.label}</span>
                  <span className="block text-[11px] text-muted mt-0.5">{type.description}</span>
                </button>
              ))}
            </div>
          </div>

          {/* Languages */}
          <div>
            <label className="block text-xs text-muted mb-2">Langues</label>
            <div className="flex flex-wrap gap-2">
              {LANGUAGE_OPTIONS.map(l => (
                <label
                  key={l.value}
                  className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg border cursor-pointer text-xs transition-colors ${
                    languages.includes(l.value)
                      ? 'bg-violet/20 border-violet text-violet-light'
                      : 'bg-surface2 border-border text-muted hover:text-white'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={languages.includes(l.value)}
                    onChange={() => toggleLanguage(l.value)}
                    className="hidden"
                  />
                  {l.label}
                </label>
              ))}
            </div>
          </div>

          {/* Config: Country coverage */}
          {campaignType === 'country_coverage' && (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
              <h4 className="text-sm font-semibold text-white">Configuration - Couverture pays</h4>
              <div>
                <label className="block text-xs text-muted mb-1">Pays *</label>
                <input
                  type="text"
                  value={country}
                  onChange={e => setCountry(e.target.value)}
                  placeholder="Ex: Allemagne"
                  className={inputClass}
                />
              </div>
              <div>
                <label className="block text-xs text-muted mb-2">Themes</label>
                <div className="flex flex-wrap gap-2">
                  {THEME_PRESETS.map(theme => (
                    <label
                      key={theme}
                      className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg border cursor-pointer text-xs transition-colors ${
                        themes.includes(theme)
                          ? 'bg-violet/20 border-violet text-violet-light'
                          : 'bg-surface2 border-border text-muted hover:text-white'
                      }`}
                    >
                      <input type="checkbox" checked={themes.includes(theme)} onChange={() => toggleTheme(theme)} className="hidden" />
                      {theme}
                    </label>
                  ))}
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs text-muted mb-1">Articles/jour</label>
                  <input type="number" min={1} max={20} value={articlesPerDay} onChange={e => setArticlesPerDay(Number(e.target.value))} className={inputClass} />
                </div>
                <div>
                  <label className="block text-xs text-muted mb-1">Date de debut</label>
                  <input type="date" value={startDate} onChange={e => setStartDate(e.target.value)} className={inputClass} />
                </div>
              </div>
            </div>
          )}

          {/* Config: Thematic */}
          {campaignType === 'thematic' && (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
              <h4 className="text-sm font-semibold text-white">Configuration - Thematique</h4>
              <div>
                <label className="block text-xs text-muted mb-1">Sujets (Entree pour ajouter)</label>
                <div className="flex flex-wrap items-center gap-2 bg-bg border border-border rounded-lg px-3 py-2 min-h-[40px]">
                  {themeTopics.map(t => (
                    <span key={t} className="inline-flex items-center gap-1 bg-violet/20 text-violet-light text-xs px-2 py-1 rounded">
                      {t}
                      <button type="button" onClick={() => setThemeTopics(themeTopics.filter(x => x !== t))} className="text-violet hover:text-white">x</button>
                    </span>
                  ))}
                  <input
                    type="text"
                    value={themeTopicInput}
                    onChange={e => setThemeTopicInput(e.target.value)}
                    onKeyDown={handleThemeTopicAdd}
                    placeholder={themeTopics.length === 0 ? 'Ajoutez des sujets...' : ''}
                    className="flex-1 bg-transparent text-white text-sm outline-none min-w-[100px]"
                  />
                </div>
              </div>
              <div>
                <label className="block text-xs text-muted mb-1">Articles/jour</label>
                <input type="number" min={1} max={20} value={articlesPerDay} onChange={e => setArticlesPerDay(Number(e.target.value))} className={`${inputClass} max-w-[120px]`} />
              </div>
            </div>
          )}

          {/* Config: Pillar cluster */}
          {campaignType === 'pillar_cluster' && (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
              <h4 className="text-sm font-semibold text-white">Configuration - Pilier + clusters</h4>
              <div>
                <label className="block text-xs text-muted mb-1">Sujet pilier *</label>
                <input
                  type="text"
                  value={pillarTopic}
                  onChange={e => setPillarTopic(e.target.value)}
                  placeholder="Ex: Guide complet de l'expatriation"
                  className={inputClass}
                />
              </div>
              <div>
                <label className="block text-xs text-muted mb-1">Nombre d'articles clusters: {clusterCount}</label>
                <input
                  type="range"
                  min={3}
                  max={15}
                  value={clusterCount}
                  onChange={e => setClusterCount(Number(e.target.value))}
                  className="w-full accent-violet"
                />
              </div>
            </div>
          )}

          {/* Config: Comparative series */}
          {campaignType === 'comparative_series' && (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
              <h4 className="text-sm font-semibold text-white">Configuration - Serie de comparatifs</h4>
              <div className="space-y-2">
                {comparisonTopics.map((topic, i) => (
                  <div key={i} className="flex items-center gap-2">
                    <span className="text-xs text-muted w-6 text-right">{i + 1}.</span>
                    <input
                      type="text"
                      value={topic}
                      onChange={e => updateComparisonTopic(i, e.target.value)}
                      placeholder="Ex: France vs Allemagne pour s'expatrier"
                      className={`${inputClass} flex-1`}
                    />
                    {comparisonTopics.length > 1 && (
                      <button type="button" onClick={() => removeComparisonTopic(i)} className="text-danger hover:text-red-400 p-1">
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    )}
                  </div>
                ))}
              </div>
              <button type="button" onClick={addComparisonTopic} className="text-xs text-violet hover:text-violet-light transition-colors">
                + Ajouter un comparatif
              </button>
            </div>
          )}

          {/* Config: Custom */}
          {campaignType === 'custom' && (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
              <h4 className="text-sm font-semibold text-white">Configuration - Personnalise</h4>
              <div className="space-y-2">
                {customTitles.map((title, i) => (
                  <div key={i} className="flex items-center gap-2">
                    <span className="text-xs text-muted w-6 text-right">{i + 1}.</span>
                    <input
                      type="text"
                      value={title}
                      onChange={e => updateCustomTitle(i, e.target.value)}
                      placeholder="Titre ou sujet de l'article..."
                      className={`${inputClass} flex-1`}
                    />
                    {customTitles.length > 1 && (
                      <button type="button" onClick={() => removeCustomTitle(i)} className="text-danger hover:text-red-400 p-1">
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    )}
                  </div>
                ))}
              </div>
              <button type="button" onClick={addCustomTitle} className="text-xs text-violet hover:text-violet-light transition-colors">
                + Ajouter un titre
              </button>
            </div>
          )}

          {/* Submit */}
          <div className="flex items-center justify-between pt-2">
            <div className="text-sm text-muted">
              <span>{itemCount} article(s) prevu(s)</span>
              <span className="mx-2">|</span>
              <span>Budget estime: ~${(budgetEstimate / 100).toFixed(2)}</span>
            </div>
            <button
              type="submit"
              disabled={submitting || !name.trim() || itemCount === 0}
              className="px-8 py-3 bg-violet hover:bg-violet/90 text-white font-semibold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {submitting ? (
                <span className="inline-flex items-center gap-2">
                  <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                  Creation...
                </span>
              ) : (
                'Creer la campagne'
              )}
            </button>
          </div>
        </form>

        {/* RIGHT: Sidebar */}
        <div className="space-y-4">
          {/* Budget */}
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

          {/* Preview */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white text-sm mb-3">Apercu</h3>
            <div className="space-y-2 text-xs">
              <div className="flex justify-between">
                <span className="text-muted">Type</span>
                <span className="text-white">{CAMPAIGN_TYPES.find(t => t.value === campaignType)?.label}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Langues</span>
                <span className="text-white">{languages.join(', ').toUpperCase()}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Articles</span>
                <span className="text-white">{itemCount}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Budget estime</span>
                <span className="text-white">${(budgetEstimate / 100).toFixed(2)}</span>
              </div>
              {campaignType === 'country_coverage' && articlesPerDay > 0 && itemCount > 0 && (
                <div className="flex justify-between">
                  <span className="text-muted">Duree estimee</span>
                  <span className="text-white">{Math.ceil(itemCount / articlesPerDay)} jour(s)</span>
                </div>
              )}
            </div>
          </div>

          {/* Info */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white text-sm mb-3">Informations</h3>
            <ul className="space-y-2 text-xs text-muted">
              <li>- La campagne demarre en mode brouillon</li>
              <li>- Vous pourrez la demarrer/pause/annuler</li>
              <li>- Les articles sont generes selon le rythme defini</li>
              <li>- Chaque article peut etre modifie individuellement</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}
