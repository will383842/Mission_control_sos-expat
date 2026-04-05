import React, { useEffect, useState, useCallback } from 'react';
import { generateArticle, fetchArticles } from '../../api/contentApi';
import type { GeneratedArticle, GenerateArticleParams } from '../../types/content';
import { toast } from '../../components/Toast';

const STATUS_BADGE: Record<string, string> = {
  draft: 'bg-muted/20 text-muted',
  generating: 'bg-amber/20 text-amber animate-pulse',
  review: 'bg-blue-500/20 text-blue-400',
  published: 'bg-success/20 text-success',
  failed: 'bg-danger/20 text-danger',
};

interface KeywordEntry {
  keyword: string;
  status: 'pending' | 'generating' | 'done';
  articleId?: number;
}

const PRESETS: Record<string, {
  title: string;
  emoji: string;
  description: string;
  contentType: string;
  tone: string;
  length: string;
  defaultInstructions: string;
}> = {
  'mots-cles': {
    title: 'Art Mots cles',
    emoji: '🔑',
    description: 'Articles optimises SEO autour de mots-cles principaux',
    contentType: 'article',
    tone: 'professional',
    length: 'medium',
    defaultInstructions: 'Article SEO optimise pour le mot-cle principal. Inclure des donnees chiffrees, des exemples concrets, et un angle pratique pour les expatries/voyageurs.',
  },
  'longues-traines': {
    title: 'Art Longues traines',
    emoji: '🎯',
    description: 'Articles ciblant des requetes longue traine a faible concurrence',
    contentType: 'article',
    tone: 'friendly',
    length: 'medium',
    defaultInstructions: 'Article longue traine : repondre precisement a la requete specifique. Ton conversationnel, reponse directe des le premier paragraphe (featured snippet), puis developpement detaille. Inclure FAQ PAA.',
  },
  'rec-avocats': {
    title: 'Art Rec Avocats',
    emoji: '⚖️',
    description: 'Articles de recrutement pour attirer des avocats partenaires',
    contentType: 'article',
    tone: 'professional',
    length: 'long',
    defaultInstructions: 'Article de recrutement destine aux avocats et juristes. Mettre en avant les avantages de rejoindre SOS-Expat comme prestataire : revenus complementaires, flexibilite, clientele internationale, assistance 24/7. Inclure temoignages types et chiffres concrets. CTA vers inscription prestataire.',
  },
  'rec-expats': {
    title: 'Art Rec Expats',
    emoji: '🧳',
    description: 'Articles de recrutement pour attirer des expats aidants',
    contentType: 'article',
    tone: 'friendly',
    length: 'long',
    defaultInstructions: 'Article de recrutement destine aux expatries experimentes. Mettre en avant le programme SOS-Expat : aider d\'autres expatries, gagner un revenu, partager son experience. Inclure exemples concrets de missions, revenus moyens, flexibilite. CTA vers inscription comme expat aidant.',
  },
};

interface Props {
  preset: 'mots-cles' | 'longues-traines' | 'rec-avocats' | 'rec-expats';
}

export default function ArticleKeywords({ preset }: Props) {
  const config = PRESETS[preset];
  const [keywords, setKeywords] = useState<KeywordEntry[]>([]);
  const [newKeyword, setNewKeyword] = useState('');
  const [bulkInput, setBulkInput] = useState('');
  const [showBulk, setShowBulk] = useState(false);
  const [articles, setArticles] = useState<GeneratedArticle[]>([]);
  const [loading, setLoading] = useState(true);
  const [language, setLanguage] = useState('fr');
  const [country, setCountry] = useState('');

  // Load existing articles of this type
  const loadArticles = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetchArticles({
        content_type: config.contentType,
        per_page: 50,
        sort_by: 'created_at',
        sort_dir: 'desc',
      });
      const data = (res.data as any);
      setArticles(data.data ?? data ?? []);
    } catch {
      // non-blocking
    } finally {
      setLoading(false);
    }
  }, [config.contentType]);

  useEffect(() => { loadArticles(); }, [loadArticles]);

  const addKeyword = () => {
    const kw = newKeyword.trim();
    if (kw && !keywords.find(k => k.keyword === kw)) {
      setKeywords(prev => [...prev, { keyword: kw, status: 'pending' }]);
    }
    setNewKeyword('');
  };

  const addBulkKeywords = () => {
    const lines = bulkInput.split('\n').map(l => l.trim()).filter(Boolean);
    const existing = new Set(keywords.map(k => k.keyword));
    const newEntries = lines
      .filter(l => !existing.has(l))
      .map(keyword => ({ keyword, status: 'pending' as const }));
    setKeywords(prev => [...prev, ...newEntries]);
    setBulkInput('');
    setShowBulk(false);
    if (newEntries.length > 0) toast.success(`${newEntries.length} mots-cles ajoutes`);
  };

  const removeKeyword = (kw: string) => {
    setKeywords(prev => prev.filter(k => k.keyword !== kw));
  };

  const generateOne = async (kw: string) => {
    setKeywords(prev => prev.map(k => k.keyword === kw ? { ...k, status: 'generating' } : k));
    try {
      const params: GenerateArticleParams = {
        topic: kw,
        language,
        content_type: config.contentType as any,
        tone: config.tone as any,
        length: config.length as any,
        generate_faq: true,
        research_sources: true,
        auto_internal_links: true,
        auto_affiliate_links: true,
        instructions: config.defaultInstructions,
      };
      if (country.trim()) params.country = country.trim();
      params.keywords = [kw];

      const res = await generateArticle(params);
      const article = res.data as unknown as GeneratedArticle;
      setKeywords(prev => prev.map(k => k.keyword === kw ? { ...k, status: 'done', articleId: article.id } : k));
      toast.success(`Article lance pour "${kw}"`);
    } catch (e: any) {
      setKeywords(prev => prev.map(k => k.keyword === kw ? { ...k, status: 'pending' } : k));
      toast.error(e?.response?.data?.message || 'Erreur generation');
    }
  };

  const generateAll = async () => {
    const pending = keywords.filter(k => k.status === 'pending');
    if (pending.length === 0) return;
    if (!confirm(`Generer ${pending.length} articles ? (pipeline 15 phases chacun)`)) return;
    for (const kw of pending) {
      await generateOne(kw.keyword);
      // 2s delay between each to avoid rate limiting
      await new Promise(r => setTimeout(r, 2000));
    }
  };

  const pendingCount = keywords.filter(k => k.status === 'pending').length;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-foreground flex items-center gap-2">
            {config.emoji} {config.title}
          </h1>
          <p className="text-sm text-muted mt-1">{config.description}</p>
        </div>
        <button onClick={loadArticles} className="btn-ghost text-sm" disabled={loading}>
          Rafraichir
        </button>
      </div>

      {/* Add keywords */}
      <div className="card p-4 space-y-3">
        <div className="flex items-center gap-3">
          <select
            value={language}
            onChange={e => setLanguage(e.target.value)}
            className="bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm w-28"
          >
            <option value="fr">FR</option>
            <option value="en">EN</option>
            <option value="es">ES</option>
            <option value="de">DE</option>
            <option value="pt">PT</option>
            <option value="ru">RU</option>
            <option value="zh">ZH</option>
            <option value="ar">AR</option>
            <option value="hi">HI</option>
          </select>
          <input
            type="text"
            value={country}
            onChange={e => setCountry(e.target.value)}
            placeholder="Pays (optionnel, ex: France)"
            className="bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm w-48"
          />
        </div>
        <div className="flex gap-2">
          <input
            type="text"
            value={newKeyword}
            onChange={e => setNewKeyword(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && (e.preventDefault(), addKeyword())}
            placeholder={preset === 'rec-avocats' ? 'Ex: avocat expatrie Espagne' : preset === 'rec-expats' ? 'Ex: devenir expat aidant Portugal' : 'Ex: visa travail Allemagne 2026'}
            className="flex-1 bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm"
          />
          <button onClick={addKeyword} className="btn-primary text-sm px-4">
            Ajouter
          </button>
          <button onClick={() => setShowBulk(!showBulk)} className="btn-ghost text-sm">
            {showBulk ? 'Fermer' : 'Bulk'}
          </button>
        </div>

        {showBulk && (
          <div className="space-y-2">
            <textarea
              value={bulkInput}
              onChange={e => setBulkInput(e.target.value)}
              placeholder="Un mot-cle par ligne..."
              rows={6}
              className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm font-mono"
            />
            <button onClick={addBulkKeywords} className="btn-primary text-sm">
              Ajouter {bulkInput.split('\n').filter(l => l.trim()).length} mots-cles
            </button>
          </div>
        )}
      </div>

      {/* Keywords queue */}
      {keywords.length > 0 && (
        <div className="card overflow-hidden">
          <div className="px-4 py-3 border-b border-border flex items-center justify-between">
            <h2 className="text-sm font-bold text-foreground">
              File de generation ({pendingCount} en attente / {keywords.length} total)
            </h2>
            {pendingCount > 0 && (
              <button onClick={generateAll} className="btn-primary text-xs px-3">
                Generer tout ({pendingCount})
              </button>
            )}
          </div>
          <div className="divide-y divide-border">
            {keywords.map(kw => (
              <div key={kw.keyword} className="flex items-center gap-3 px-4 py-2.5">
                <span className="flex-1 text-sm text-foreground font-mono">{kw.keyword}</span>
                <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                  kw.status === 'generating' ? 'bg-amber/20 text-amber animate-pulse' :
                  kw.status === 'done' ? 'bg-success/20 text-success' :
                  'bg-muted/20 text-muted'
                }`}>
                  {kw.status === 'generating' ? 'En cours...' : kw.status === 'done' ? 'Lance' : 'En attente'}
                </span>
                {kw.status === 'pending' && (
                  <>
                    <button onClick={() => generateOne(kw.keyword)} className="btn-ghost text-xs">
                      Generer
                    </button>
                    <button onClick={() => removeKeyword(kw.keyword)} className="text-danger text-xs hover:underline">
                      x
                    </button>
                  </>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Existing articles */}
      <div className="card overflow-hidden">
        <div className="px-4 py-3 border-b border-border">
          <h2 className="text-sm font-bold text-foreground">Articles generes ({articles.length})</h2>
        </div>
        {loading ? (
          <div className="px-4 py-8 text-center text-muted text-sm">Chargement...</div>
        ) : articles.length > 0 ? (
          <table className="w-full text-sm">
            <thead className="bg-surface">
              <tr>
                <th className="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Titre</th>
                <th className="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Status</th>
                <th className="px-4 py-2 text-left text-xs font-medium text-muted uppercase">SEO</th>
                <th className="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Mots</th>
                <th className="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Date</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {articles.map(a => (
                <tr key={a.id} className="hover:bg-surface/50">
                  <td className="px-4 py-2.5 text-foreground max-w-sm truncate">{a.title}</td>
                  <td className="px-4 py-2.5">
                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_BADGE[a.status] || 'bg-muted/20 text-muted'}`}>
                      {a.status}
                    </span>
                  </td>
                  <td className="px-4 py-2.5 text-muted">{a.seo_score ?? '-'}</td>
                  <td className="px-4 py-2.5 text-muted">{a.word_count ?? '-'}</td>
                  <td className="px-4 py-2.5 text-muted text-xs">
                    {a.created_at ? new Date(a.created_at).toLocaleDateString('fr') : '-'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <div className="px-4 py-8 text-center text-muted text-sm">Aucun article genere.</div>
        )}
      </div>
    </div>
  );
}
