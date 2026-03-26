import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useContentGeneration, useCosts } from '../../hooks/useContentEngine';
import type { GenerateComparativeParams } from '../../types/content';

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
export default function ComparativeCreate() {
  const navigate = useNavigate();
  const { generating, error: genError, generateComparative } = useContentGeneration();
  const { overview, load: loadCosts } = useCosts();

  const [title, setTitle] = useState('');
  const [entities, setEntities] = useState<string[]>(['', '']);
  const [language, setLanguage] = useState('fr');
  const [country, setCountry] = useState('');
  const [keywords, setKeywords] = useState<string[]>([]);
  const [keywordInput, setKeywordInput] = useState('');

  useEffect(() => {
    loadCosts();
  }, [loadCosts]);

  const addEntity = () => {
    if (entities.length < 5) {
      setEntities([...entities, '']);
    }
  };

  const removeEntity = (index: number) => {
    if (entities.length > 2) {
      setEntities(entities.filter((_, i) => i !== index));
    }
  };

  const updateEntity = (index: number, value: string) => {
    setEntities(entities.map((e, i) => i === index ? value : e));
  };

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

  const validEntities = entities.filter(e => e.trim());
  const costEstimate = 15 + (validEntities.length * 3); // ~$0.15 base + ~$0.03/entity

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim() || validEntities.length < 2) return;

    const params: GenerateComparativeParams = {
      title: title.trim(),
      entities: validEntities,
      language,
      country: country || undefined,
      keywords: keywords.length > 0 ? keywords : undefined,
    };

    const comp = await generateComparative(params);
    if (comp) {
      navigate(`/content/comparatives/${comp.id}`);
    }
  };

  return (
    <div className="p-4 md:p-6">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm text-muted mb-4">
        <button onClick={() => navigate('/content/overview')} className="hover:text-white transition-colors">Contenu</button>
        <span>/</span>
        <button onClick={() => navigate('/content/comparatives')} className="hover:text-white transition-colors">Comparatifs</button>
        <span>/</span>
        <span className="text-white">Nouveau</span>
      </div>

      <h2 className="font-title text-2xl font-bold text-white mb-6">Generer un comparatif</h2>

      <div className="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-6">
        {/* LEFT: Form */}
        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Error */}
          {genError && (
            <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-lg">{genError}</div>
          )}

          {/* Title */}
          <div>
            <label className="block text-xs text-muted mb-1">Titre du comparatif *</label>
            <input
              type="text"
              value={title}
              onChange={e => setTitle(e.target.value)}
              placeholder="Ex: Meilleurs pays pour s'expatrier en 2026"
              className={`${inputClass} text-base py-3`}
              required
            />
          </div>

          {/* Entities */}
          <div>
            <label className="block text-xs text-muted mb-2">Entites a comparer * (min 2, max 5)</label>
            <div className="space-y-2">
              {entities.map((entity, i) => (
                <div key={i} className="flex items-center gap-2">
                  <span className="text-xs text-muted w-6 text-right">{i + 1}.</span>
                  <input
                    type="text"
                    value={entity}
                    onChange={e => updateEntity(i, e.target.value)}
                    placeholder={`Entite ${i + 1}...`}
                    className={`${inputClass} flex-1`}
                  />
                  {entities.length > 2 && (
                    <button
                      type="button"
                      onClick={() => removeEntity(i)}
                      className="text-danger hover:text-red-400 transition-colors p-1"
                    >
                      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  )}
                </div>
              ))}
            </div>
            {entities.length < 5 && (
              <button
                type="button"
                onClick={addEntity}
                className="mt-2 text-xs text-violet hover:text-violet-light transition-colors"
              >
                + Ajouter une entite
              </button>
            )}
          </div>

          {/* Language, Country */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                placeholder="Ex: Europe"
                className={inputClass}
              />
            </div>
          </div>

          {/* Keywords */}
          <div>
            <label className="block text-xs text-muted mb-1">Mots-cles (Entree pour ajouter)</label>
            <div className="flex flex-wrap items-center gap-2 bg-bg border border-border rounded-lg px-3 py-2 min-h-[40px]">
              {keywords.map(kw => (
                <span key={kw} className="inline-flex items-center gap-1 bg-violet/20 text-violet-light text-xs px-2 py-1 rounded">
                  {kw}
                  <button type="button" onClick={() => removeKeyword(kw)} className="text-violet hover:text-white transition-colors">x</button>
                </span>
              ))}
              <input
                type="text"
                value={keywordInput}
                onChange={e => setKeywordInput(e.target.value)}
                onKeyDown={handleKeywordAdd}
                placeholder={keywords.length === 0 ? 'comparatif, vs, meilleur...' : ''}
                className="flex-1 bg-transparent text-white text-sm outline-none min-w-[100px]"
              />
            </div>
          </div>

          {/* Cost + Generate */}
          <div className="flex items-center justify-between pt-2">
            <span className="text-sm text-muted">
              Cout estime: ~${(costEstimate / 100).toFixed(2)}
            </span>
            <button
              type="submit"
              disabled={generating || !title.trim() || validEntities.length < 2}
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
                'Generer le comparatif'
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

          {/* Tips */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white text-sm mb-3">Conseils</h3>
            <ul className="space-y-2 text-xs text-muted">
              <li>- Comparez 2 a 5 entites similaires</li>
              <li>- Un titre clair aide l'IA a structurer le comparatif</li>
              <li>- Les mots-cles ameliorent le SEO</li>
              <li>- Le comparatif inclura tableau, pros/cons et verdict</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}
