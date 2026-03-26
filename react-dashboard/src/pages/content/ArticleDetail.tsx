import React, { useEffect, useState, useCallback, useRef } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchArticle,
  updateArticle,
  deleteArticle,
  duplicateArticle,
  publishArticle,
  fetchArticleVersions,
  restoreArticleVersion,
  fetchEndpoints,
  searchUnsplash,
  generateDalleImage,
} from '../../api/contentApi';
import type {
  GeneratedArticle,
  ArticleFaq,
  ArticleVersion,
  GenerationLog,
  GenerationPhase,
  PublishingEndpoint,
  ContentStatus,
  UnsplashImage,
  SeoAnalysis,
} from '../../types/content';

// ── Constants ───────────────────────────────────────────────
type Tab = 'content' | 'seo' | 'faq' | 'media' | 'publish';

const TABS: { key: Tab; label: string }[] = [
  { key: 'content', label: 'Contenu' },
  { key: 'seo', label: 'SEO' },
  { key: 'faq', label: 'FAQ' },
  { key: 'media', label: 'Medias' },
  { key: 'publish', label: 'Publier' },
];

const STATUS_COLORS: Record<ContentStatus, string> = {
  draft: 'bg-muted/20 text-muted',
  generating: 'bg-amber/20 text-amber',
  review: 'bg-orange-500/20 text-orange-400',
  scheduled: 'bg-cyan/20 text-cyan',
  published: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted',
};

const STATUS_LABELS: Record<ContentStatus, string> = {
  draft: 'Brouillon',
  generating: 'Generation...',
  review: 'A relire',
  scheduled: 'Planifie',
  published: 'Publie',
  archived: 'Archive',
};

const PHASE_LABELS: Record<GenerationPhase, string> = {
  validate: 'Validation',
  research: 'Recherche',
  title: 'Titre',
  excerpt: 'Extrait',
  content: 'Contenu',
  faq: 'FAQ',
  meta: 'Meta SEO',
  jsonld: 'JSON-LD',
  internal_links: 'Liens internes',
  external_links: 'Liens externes',
  affiliate_links: 'Liens affilies',
  images: 'Images',
  slugs: 'Slugs',
  quality: 'Qualite',
  translations: 'Traductions',
};

const ALL_PHASES: GenerationPhase[] = [
  'validate', 'research', 'title', 'excerpt', 'content', 'faq', 'meta', 'jsonld',
  'internal_links', 'external_links', 'affiliate_links', 'images', 'slugs', 'quality', 'translations',
];

function seoColor(score: number) {
  if (score >= 80) return 'text-success';
  if (score >= 60) return 'text-amber';
  return 'text-danger';
}

function seoBgColor(score: number) {
  if (score >= 80) return 'bg-success/20 text-success';
  if (score >= 60) return 'bg-amber/20 text-amber';
  return 'bg-danger/20 text-danger';
}

const inputClass = 'w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

// ── Generation Progress ─────────────────────────────────────
function GenerationProgress({ logs, startedAt }: { logs: GenerationLog[]; startedAt: string }) {
  const logMap = new Map(logs.map(l => [l.phase, l]));
  const elapsed = Math.round((Date.now() - new Date(startedAt).getTime()) / 1000);
  const completedCount = logs.filter(l => l.status === 'completed').length;
  const totalTokens = logs.reduce((s, l) => s + l.tokens_used, 0);
  const pct = Math.round((completedCount / ALL_PHASES.length) * 100);

  return (
    <div className="space-y-4">
      {/* Progress bar */}
      <div>
        <div className="flex justify-between text-xs text-muted mb-1">
          <span>Progression: {pct}%</span>
          <span>{elapsed}s | {totalTokens.toLocaleString()} tokens</span>
        </div>
        <div className="h-2 bg-surface2 rounded-full overflow-hidden">
          <div className="h-full bg-violet rounded-full transition-all" style={{ width: `${pct}%` }} />
        </div>
      </div>

      {/* Phase list */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-1">
        {ALL_PHASES.map(phase => {
          const log = logMap.get(phase);
          const status = log?.status || 'pending';
          const icon = status === 'completed' ? '\u2705' : status === 'running' ? '\uD83D\uDD04' : status === 'failed' ? '\u274C' : '\u23F3';
          return (
            <div key={phase} className="flex items-center gap-2 text-sm py-1">
              <span className={status === 'running' ? 'animate-spin' : ''}>{icon}</span>
              <span className={status === 'completed' ? 'text-success' : status === 'running' ? 'text-amber' : status === 'failed' ? 'text-danger' : 'text-muted'}>
                {PHASE_LABELS[phase]}
              </span>
              {log && log.duration_ms > 0 && (
                <span className="text-xs text-muted ml-auto">{(log.duration_ms / 1000).toFixed(1)}s</span>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ── SEO Score Breakdown ─────────────────────────────────────
function SeoScoreBreakdown({ analysis }: { analysis: SeoAnalysis }) {
  const criteria = [
    { label: 'Titre', score: analysis.title_score },
    { label: 'Meta description', score: analysis.meta_description_score },
    { label: 'Titres (H1-H6)', score: analysis.headings_score },
    { label: 'Contenu', score: analysis.content_score },
    { label: 'Images', score: analysis.images_score },
    { label: 'Liens internes', score: analysis.internal_links_score },
    { label: 'Liens externes', score: analysis.external_links_score },
    { label: 'Donnees structurees', score: analysis.structured_data_score },
    { label: 'Hreflang', score: analysis.hreflang_score },
    { label: 'Technique', score: analysis.technical_score },
  ];

  return (
    <div className="space-y-2">
      {criteria.map(c => (
        <div key={c.label} className="flex items-center gap-3">
          <span className="text-xs text-muted w-36">{c.label}</span>
          <div className="flex-1 h-1.5 bg-surface2 rounded-full overflow-hidden">
            <div
              className={`h-full rounded-full ${c.score >= 80 ? 'bg-success' : c.score >= 60 ? 'bg-amber' : 'bg-danger'}`}
              style={{ width: `${c.score}%` }}
            />
          </div>
          <span className={`text-xs font-medium w-8 text-right ${seoColor(c.score)}`}>{c.score}</span>
        </div>
      ))}
    </div>
  );
}

// ── Main Component ──────────────────────────────────────────
export default function ArticleDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const articleId = Number(id);

  const [article, setArticle] = useState<GeneratedArticle | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [activeTab, setActiveTab] = useState<Tab>('content');

  // Content tab
  const [editMode, setEditMode] = useState(false);
  const [editTitle, setEditTitle] = useState('');
  const [editContent, setEditContent] = useState('');

  // SEO tab
  const [metaTitle, setMetaTitle] = useState('');
  const [metaDescription, setMetaDescription] = useState('');

  // FAQ tab
  const [faqs, setFaqs] = useState<ArticleFaq[]>([]);

  // Media tab
  const [unsplashQuery, setUnsplashQuery] = useState('');
  const [unsplashResults, setUnsplashResults] = useState<UnsplashImage[]>([]);
  const [searchingImages, setSearchingImages] = useState(false);
  const [dallePrompt, setDallePrompt] = useState('');
  const [generatingImage, setGeneratingImage] = useState(false);

  // Publish tab
  const [endpoints, setEndpoints] = useState<PublishingEndpoint[]>([]);
  const [selectedEndpoint, setSelectedEndpoint] = useState<number | null>(null);
  const [publishMode, setPublishMode] = useState<'now' | 'schedule'>('now');
  const [scheduleDate, setScheduleDate] = useState('');
  const [publishing, setPublishing] = useState(false);

  // Sidebar
  const [versions, setVersions] = useState<ArticleVersion[]>([]);
  const [showJsonLd, setShowJsonLd] = useState(false);

  // Polling for generation
  const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const loadArticle = useCallback(async () => {
    try {
      const { data } = await fetchArticle(articleId);
      setArticle(data);
      setEditTitle(data.title);
      setEditContent(data.content_html || '');
      setMetaTitle(data.meta_title || '');
      setMetaDescription(data.meta_description || '');
      setFaqs(data.faqs || []);
      return data;
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur lors du chargement';
      setError(msg);
      return null;
    } finally {
      setLoading(false);
    }
  }, [articleId]);

  useEffect(() => {
    loadArticle().then(data => {
      if (data?.status === 'generating') {
        pollingRef.current = setInterval(async () => {
          const updated = await loadArticle();
          if (updated && updated.status !== 'generating') {
            if (pollingRef.current) clearInterval(pollingRef.current);
          }
        }, 3000);
      }
    });
    fetchEndpoints().then(res => setEndpoints(res.data)).catch(() => {});
    fetchArticleVersions(articleId).then(res => setVersions(res.data)).catch(() => {});

    return () => {
      if (pollingRef.current) clearInterval(pollingRef.current);
    };
  }, [articleId, loadArticle]);

  const handleSave = async () => {
    if (!article) return;
    setSaving(true);
    try {
      const { data } = await updateArticle(article.id, {
        title: editTitle,
        content_html: editContent,
        meta_title: metaTitle,
        meta_description: metaDescription,
      });
      setArticle(data);
    } catch { /* ignore */ }
    finally { setSaving(false); }
  };

  const handleSaveFaqs = async () => {
    if (!article) return;
    setSaving(true);
    try {
      const { data } = await updateArticle(article.id, { faqs } as Partial<GeneratedArticle>);
      setArticle(data);
      if (data.faqs) setFaqs(data.faqs);
    } catch { /* ignore */ }
    finally { setSaving(false); }
  };

  const handlePublish = async () => {
    if (!article || !selectedEndpoint) return;
    setPublishing(true);
    try {
      await publishArticle(article.id, {
        endpoint_id: selectedEndpoint,
        scheduled_at: publishMode === 'schedule' ? scheduleDate : undefined,
      });
      await loadArticle();
    } catch { /* ignore */ }
    finally { setPublishing(false); }
  };

  const handleDuplicate = async () => {
    if (!article) return;
    try {
      const { data } = await duplicateArticle(article.id);
      navigate(`/content/articles/${data.id}`);
    } catch { /* ignore */ }
  };

  const handleDelete = async () => {
    if (!article || !confirm('Supprimer cet article ?')) return;
    try {
      await deleteArticle(article.id);
      navigate('/content/articles');
    } catch { /* ignore */ }
  };

  const handleRestore = async (versionId: number) => {
    if (!article || !confirm('Restaurer cette version ?')) return;
    try {
      const { data } = await restoreArticleVersion(article.id, versionId);
      setArticle(data);
      setEditTitle(data.title);
      setEditContent(data.content_html || '');
    } catch { /* ignore */ }
  };

  const handleSearchUnsplash = async () => {
    if (!unsplashQuery.trim()) return;
    setSearchingImages(true);
    try {
      const { data } = await searchUnsplash(unsplashQuery, 12);
      setUnsplashResults(data);
    } catch { /* ignore */ }
    finally { setSearchingImages(false); }
  };

  const handleGenerateDalle = async () => {
    if (!dallePrompt.trim()) return;
    setGeneratingImage(true);
    try {
      await generateDalleImage(dallePrompt);
      await loadArticle();
    } catch { /* ignore */ }
    finally { setGeneratingImage(false); }
  };

  const addFaq = () => {
    setFaqs([...faqs, { id: 0, article_id: articleId, question: '', answer: '', sort_order: faqs.length }]);
  };

  const updateFaq = (index: number, field: 'question' | 'answer', value: string) => {
    setFaqs(faqs.map((f, i) => i === index ? { ...f, [field]: value } : f));
  };

  const removeFaq = (index: number) => {
    setFaqs(faqs.filter((_, i) => i !== index));
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6">
        <div className="text-muted text-sm">Chargement...</div>
      </div>
    );
  }

  if (error || !article) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-lg">
          {error || 'Article introuvable'}
        </div>
      </div>
    );
  }

  // Pre-publication checklist
  const checklist = [
    { label: 'Score SEO > 80', ok: article.seo_score >= 80 },
    { label: 'Images presentes', ok: (article.images?.length ?? 0) > 0 || !!article.featured_image_url },
    { label: 'FAQ completes', ok: (article.faqs?.length ?? 0) > 0 },
    { label: 'Meta SEO renseignes', ok: !!article.meta_title && !!article.meta_description },
  ];

  return (
    <div className="p-4 md:p-6">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm text-muted mb-4">
        <button onClick={() => navigate('/content/overview')} className="hover:text-white transition-colors">Contenu</button>
        <span>/</span>
        <button onClick={() => navigate('/content/articles')} className="hover:text-white transition-colors">Articles</button>
        <span>/</span>
        <span className="text-white truncate max-w-[200px]">{article.title}</span>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-[1fr_300px] gap-6">
        {/* MAIN */}
        <div className="space-y-4">
          {/* Tab nav */}
          <div className="flex items-center gap-1 border-b border-border">
            {TABS.map(tab => (
              <button
                key={tab.key}
                onClick={() => setActiveTab(tab.key)}
                className={`px-4 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px ${
                  activeTab === tab.key
                    ? 'text-violet border-violet'
                    : 'text-muted hover:text-white border-transparent'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>

          {/* TAB: Content */}
          {activeTab === 'content' && (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
              {article.status === 'generating' ? (
                <GenerationProgress
                  logs={article.generation_logs || []}
                  startedAt={article.created_at}
                />
              ) : (
                <>
                  {/* Title */}
                  <div>
                    <label className="block text-xs text-muted mb-1">Titre</label>
                    <input
                      type="text"
                      value={editTitle}
                      onChange={e => setEditTitle(e.target.value)}
                      className={`${inputClass} text-base font-semibold`}
                    />
                  </div>

                  {/* Toggle */}
                  <div className="flex items-center gap-3">
                    <button
                      onClick={() => setEditMode(false)}
                      className={`text-xs px-3 py-1 rounded-lg transition-colors ${!editMode ? 'bg-violet text-white' : 'bg-surface2 text-muted'}`}
                    >
                      Apercu
                    </button>
                    <button
                      onClick={() => setEditMode(true)}
                      className={`text-xs px-3 py-1 rounded-lg transition-colors ${editMode ? 'bg-violet text-white' : 'bg-surface2 text-muted'}`}
                    >
                      Mode edition
                    </button>
                  </div>

                  {/* Content */}
                  {editMode ? (
                    <textarea
                      value={editContent}
                      onChange={e => setEditContent(e.target.value)}
                      rows={25}
                      className={`${inputClass} font-mono text-xs leading-relaxed`}
                    />
                  ) : (
                    <div
                      className="prose prose-invert max-w-none bg-bg border border-border rounded-lg p-6 text-sm leading-relaxed"
                      dangerouslySetInnerHTML={{ __html: editContent || '<p class="text-muted">Aucun contenu</p>' }}
                    />
                  )}

                  {/* Save */}
                  <div className="flex justify-end">
                    <button
                      onClick={handleSave}
                      disabled={saving}
                      className="px-6 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                    >
                      {saving ? 'Sauvegarde...' : 'Sauvegarder'}
                    </button>
                  </div>
                </>
              )}
            </div>
          )}

          {/* TAB: SEO */}
          {activeTab === 'seo' && (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-5">
              {/* Meta title */}
              <div>
                <div className="flex justify-between mb-1">
                  <label className="text-xs text-muted">Meta titre</label>
                  <span className={`text-xs ${metaTitle.length > 60 ? 'text-danger' : 'text-muted'}`}>{metaTitle.length}/60</span>
                </div>
                <input
                  type="text"
                  value={metaTitle}
                  onChange={e => setMetaTitle(e.target.value)}
                  className={inputClass}
                />
              </div>

              {/* Meta description */}
              <div>
                <div className="flex justify-between mb-1">
                  <label className="text-xs text-muted">Meta description</label>
                  <span className={`text-xs ${metaDescription.length > 160 ? 'text-danger' : 'text-muted'}`}>{metaDescription.length}/160</span>
                </div>
                <textarea
                  value={metaDescription}
                  onChange={e => setMetaDescription(e.target.value)}
                  rows={3}
                  className={inputClass}
                />
              </div>

              {/* Slug / hreflang */}
              <div>
                <label className="text-xs text-muted mb-1 block">Slug</label>
                <p className="text-sm text-white font-mono bg-bg border border-border rounded-lg px-3 py-2">{article.slug}</p>
              </div>

              {article.hreflang_map && Object.keys(article.hreflang_map).length > 0 && (
                <div>
                  <label className="text-xs text-muted mb-1 block">Slugs hreflang</label>
                  <div className="space-y-1">
                    {Object.entries(article.hreflang_map).map(([lang, url]) => (
                      <div key={lang} className="flex items-center gap-2 text-sm">
                        <span className="text-muted uppercase w-8">{lang}</span>
                        <span className="text-white font-mono text-xs truncate">{url}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Canonical */}
              {article.canonical_url && (
                <div>
                  <label className="text-xs text-muted mb-1 block">URL canonique</label>
                  <p className="text-sm text-white font-mono truncate">{article.canonical_url}</p>
                </div>
              )}

              {/* Keywords */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="text-xs text-muted mb-1 block">Mot-cle principal</label>
                  <p className="text-sm text-white">{article.keywords_primary || '-'}</p>
                  {article.keyword_density && article.keywords_primary && (
                    <p className="text-xs text-muted mt-0.5">
                      Densite: {(article.keyword_density[article.keywords_primary] || 0).toFixed(2)}%
                    </p>
                  )}
                </div>
                <div>
                  <label className="text-xs text-muted mb-1 block">Mots-cles secondaires</label>
                  {article.keywords_secondary?.map(kw => (
                    <span key={kw} className="inline-block mr-1 mb-1 px-2 py-0.5 bg-surface2 text-xs text-muted rounded">{kw}</span>
                  )) || <p className="text-sm text-muted">-</p>}
                </div>
              </div>

              {/* JSON-LD */}
              {article.json_ld && (
                <div>
                  <button
                    onClick={() => setShowJsonLd(!showJsonLd)}
                    className="text-xs text-violet hover:text-violet-light transition-colors"
                  >
                    {showJsonLd ? 'Masquer' : 'Voir'} JSON-LD
                  </button>
                  {showJsonLd && (
                    <pre className="mt-2 bg-bg border border-border rounded-lg p-4 text-xs text-muted overflow-x-auto max-h-64">
                      {JSON.stringify(article.json_ld, null, 2)}
                    </pre>
                  )}
                </div>
              )}

              {/* SEO Score breakdown */}
              {article.seo_analysis && (
                <div>
                  <h4 className="text-sm font-semibold text-white mb-3">Analyse SEO detaillee</h4>
                  <SeoScoreBreakdown analysis={article.seo_analysis} />
                  {article.seo_analysis.issues.length > 0 && (
                    <div className="mt-4 space-y-1">
                      <h5 className="text-xs text-muted uppercase tracking-wide mb-2">Problemes</h5>
                      {article.seo_analysis.issues.map((issue, i) => (
                        <div key={i} className={`text-xs px-3 py-2 rounded-lg ${
                          issue.severity === 'error' ? 'bg-danger/10 text-danger' :
                          issue.severity === 'warning' ? 'bg-amber/10 text-amber' :
                          'bg-cyan/10 text-cyan'
                        }`}>
                          <span className="font-medium">{issue.message}</span>
                          {issue.suggestion && <p className="text-muted mt-0.5">{issue.suggestion}</p>}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}

              {/* Save */}
              <div className="flex justify-end">
                <button
                  onClick={handleSave}
                  disabled={saving}
                  className="px-6 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                >
                  {saving ? 'Sauvegarde...' : 'Sauvegarder SEO'}
                </button>
              </div>
            </div>
          )}

          {/* TAB: FAQ */}
          {activeTab === 'faq' && (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="font-title font-semibold text-white text-sm">{faqs.length} question(s)</h3>
                <button
                  onClick={addFaq}
                  className="px-3 py-1.5 bg-violet hover:bg-violet/90 text-white text-xs rounded-lg transition-colors"
                >
                  + Ajouter une question
                </button>
              </div>

              {faqs.length === 0 ? (
                <p className="text-sm text-muted text-center py-6">Aucune FAQ. Ajoutez-en une ou regenerez.</p>
              ) : (
                <div className="space-y-3">
                  {faqs.map((faq, i) => (
                    <div key={faq.id || i} className="bg-bg border border-border rounded-lg p-4 space-y-2">
                      <div className="flex items-start justify-between gap-2">
                        <span className="text-xs text-muted mt-1">Q{i + 1}</span>
                        <button
                          onClick={() => removeFaq(i)}
                          className="text-xs text-danger hover:text-red-400 transition-colors"
                        >
                          Supprimer
                        </button>
                      </div>
                      <textarea
                        value={faq.question}
                        onChange={e => updateFaq(i, 'question', e.target.value)}
                        rows={2}
                        placeholder="Question..."
                        className={`${inputClass} text-sm font-medium`}
                      />
                      <textarea
                        value={faq.answer}
                        onChange={e => updateFaq(i, 'answer', e.target.value)}
                        rows={3}
                        placeholder="Reponse..."
                        className={inputClass}
                      />
                    </div>
                  ))}
                </div>
              )}

              <div className="flex justify-end">
                <button
                  onClick={handleSaveFaqs}
                  disabled={saving}
                  className="px-6 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                >
                  {saving ? 'Sauvegarde...' : 'Sauvegarder FAQ'}
                </button>
              </div>
            </div>
          )}

          {/* TAB: Media */}
          {activeTab === 'media' && (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-5">
              {/* Featured image */}
              {article.featured_image_url && (
                <div>
                  <h4 className="text-sm font-semibold text-white mb-2">Image principale</h4>
                  <img
                    src={article.featured_image_url}
                    alt={article.featured_image_alt || article.title}
                    className="rounded-lg max-h-64 object-cover border border-border"
                  />
                  {article.featured_image_attribution && (
                    <p className="text-xs text-muted mt-1">{article.featured_image_attribution}</p>
                  )}
                </div>
              )}

              {/* Image gallery */}
              {article.images && article.images.length > 0 && (
                <div>
                  <h4 className="text-sm font-semibold text-white mb-2">Galerie ({article.images.length})</h4>
                  <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    {article.images.map(img => (
                      <div key={img.id} className="relative group">
                        <img
                          src={img.url}
                          alt={img.alt_text || ''}
                          className="w-full h-28 object-cover rounded-lg border border-border"
                        />
                        {img.attribution && (
                          <p className="text-[10px] text-muted mt-0.5 truncate">{img.attribution}</p>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Unsplash search */}
              <div>
                <h4 className="text-sm font-semibold text-white mb-2">Rechercher sur Unsplash</h4>
                <div className="flex gap-2">
                  <input
                    type="text"
                    value={unsplashQuery}
                    onChange={e => setUnsplashQuery(e.target.value)}
                    onKeyDown={e => e.key === 'Enter' && handleSearchUnsplash()}
                    placeholder="Ex: expatriation, travel..."
                    className={`${inputClass} flex-1`}
                  />
                  <button
                    onClick={handleSearchUnsplash}
                    disabled={searchingImages}
                    className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                  >
                    {searchingImages ? '...' : 'Chercher'}
                  </button>
                </div>

                {unsplashResults.length > 0 && (
                  <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mt-3">
                    {unsplashResults.map((img, i) => (
                      <button
                        key={i}
                        className="relative group hover:opacity-80 transition-opacity"
                        onClick={() => {/* Would add to article images */}}
                      >
                        <img
                          src={img.thumb_url}
                          alt={img.alt_text}
                          className="w-full h-28 object-cover rounded-lg border border-border"
                        />
                        <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 rounded-lg flex items-center justify-center transition-opacity">
                          <span className="text-xs text-white">Ajouter</span>
                        </div>
                      </button>
                    ))}
                  </div>
                )}
              </div>

              {/* DALL-E */}
              <div>
                <h4 className="text-sm font-semibold text-white mb-2">Generer avec DALL-E</h4>
                <div className="flex gap-2">
                  <input
                    type="text"
                    value={dallePrompt}
                    onChange={e => setDallePrompt(e.target.value)}
                    placeholder="Decrivez l'image souhaitee..."
                    className={`${inputClass} flex-1`}
                  />
                  <button
                    onClick={handleGenerateDalle}
                    disabled={generatingImage}
                    className="px-4 py-2 bg-amber hover:bg-amber/90 text-black text-sm rounded-lg transition-colors disabled:opacity-50"
                  >
                    {generatingImage ? '...' : 'Generer'}
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* TAB: Publish */}
          {activeTab === 'publish' && (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-5">
              {/* Current status */}
              <div className="flex items-center gap-3">
                <span className="text-xs text-muted">Statut actuel:</span>
                <span className={`px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[article.status]}`}>
                  {STATUS_LABELS[article.status]}
                </span>
              </div>

              {/* Endpoint */}
              <div>
                <label className="block text-xs text-muted mb-1">Destination de publication</label>
                <select
                  value={selectedEndpoint || ''}
                  onChange={e => setSelectedEndpoint(Number(e.target.value) || null)}
                  className={inputClass}
                >
                  <option value="">Selectionnez...</option>
                  {endpoints.filter(ep => ep.is_active).map(ep => (
                    <option key={ep.id} value={ep.id}>
                      {ep.name} ({ep.type})
                      {ep.is_default ? ' [defaut]' : ''}
                    </option>
                  ))}
                </select>
              </div>

              {/* Schedule */}
              <div>
                <label className="block text-xs text-muted mb-2">Planification</label>
                <div className="flex items-center gap-4">
                  <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                    <input
                      type="radio"
                      name="publishMode"
                      checked={publishMode === 'now'}
                      onChange={() => setPublishMode('now')}
                      className="accent-violet"
                    />
                    Publier maintenant
                  </label>
                  <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                    <input
                      type="radio"
                      name="publishMode"
                      checked={publishMode === 'schedule'}
                      onChange={() => setPublishMode('schedule')}
                      className="accent-violet"
                    />
                    Planifier
                  </label>
                </div>
                {publishMode === 'schedule' && (
                  <input
                    type="datetime-local"
                    value={scheduleDate}
                    onChange={e => setScheduleDate(e.target.value)}
                    className={`${inputClass} mt-2 max-w-xs`}
                  />
                )}
              </div>

              {/* Translations */}
              {article.translations && article.translations.length > 0 && (
                <div>
                  <label className="block text-xs text-muted mb-2">Langues a publier</label>
                  <div className="flex flex-wrap gap-2">
                    <span className="px-2 py-1 bg-violet/20 text-violet-light text-xs rounded">
                      {article.language.toUpperCase()} (original)
                    </span>
                    {article.translations.map(t => (
                      <span key={t.id} className="px-2 py-1 bg-surface2 text-muted text-xs rounded">
                        {t.language.toUpperCase()}
                      </span>
                    ))}
                  </div>
                </div>
              )}

              {/* Pre-publication checklist */}
              <div>
                <h4 className="text-sm font-semibold text-white mb-2">Check-list</h4>
                <div className="space-y-1">
                  {checklist.map((item, i) => (
                    <div key={i} className="flex items-center gap-2 text-sm">
                      <span className={item.ok ? 'text-success' : 'text-amber'}>{item.ok ? '\u2705' : '\u26A0\uFE0F'}</span>
                      <span className={item.ok ? 'text-white' : 'text-muted'}>{item.label}</span>
                    </div>
                  ))}
                </div>
              </div>

              {/* Publish button */}
              <button
                onClick={handlePublish}
                disabled={publishing || !selectedEndpoint}
                className="w-full px-6 py-3 bg-success hover:bg-success/90 text-white font-semibold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {publishing ? 'Publication...' : publishMode === 'schedule' ? 'Planifier la publication' : 'Publier maintenant'}
              </button>
            </div>
          )}
        </div>

        {/* SIDEBAR */}
        <div className="space-y-4">
          {/* SEO Score */}
          <div className="bg-surface border border-border rounded-xl p-5 text-center">
            <p className="text-xs text-muted uppercase tracking-wide mb-2">Score SEO</p>
            <p className={`text-4xl font-bold ${seoColor(article.seo_score)}`}>
              {article.seo_score}
            </p>
            <p className="text-xs text-muted mt-1">/ 100</p>
          </div>

          {/* Quick info */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h4 className="font-title font-semibold text-white text-sm mb-3">Informations</h4>
            <div className="space-y-2 text-xs">
              <div className="flex justify-between"><span className="text-muted">Mots</span><span className="text-white">{article.word_count.toLocaleString()}</span></div>
              <div className="flex justify-between"><span className="text-muted">Lecture</span><span className="text-white">{article.reading_time_minutes} min</span></div>
              <div className="flex justify-between"><span className="text-muted">FAQ</span><span className="text-white">{article.faqs?.length || 0}</span></div>
              <div className="flex justify-between"><span className="text-muted">Images</span><span className="text-white">{article.images?.length || 0}</span></div>
              <div className="flex justify-between"><span className="text-muted">Qualite</span><span className={seoColor(article.quality_score)}>{article.quality_score}/100</span></div>
              <div className="flex justify-between"><span className="text-muted">Cout</span><span className="text-white">${(article.generation_cost_cents / 100).toFixed(2)}</span></div>
              <div className="flex justify-between"><span className="text-muted">Tokens</span><span className="text-white">{(article.generation_tokens_input + article.generation_tokens_output).toLocaleString()}</span></div>
              <div className="flex justify-between"><span className="text-muted">Duree</span><span className="text-white">{article.generation_duration_seconds}s</span></div>
            </div>
          </div>

          {/* Translations */}
          {article.translations && article.translations.length > 0 && (
            <div className="bg-surface border border-border rounded-xl p-5">
              <h4 className="font-title font-semibold text-white text-sm mb-3">Traductions</h4>
              <div className="space-y-1">
                <div className="flex items-center gap-2 text-xs">
                  <span className="w-8 text-muted uppercase">{article.language}</span>
                  <span className="px-1.5 py-0.5 bg-violet/20 text-violet-light rounded text-[10px]">original</span>
                </div>
                {article.translations.map(t => (
                  <button
                    key={t.id}
                    onClick={() => navigate(`/content/articles/${t.id}`)}
                    className="flex items-center gap-2 text-xs w-full hover:bg-surface2 rounded px-1 py-0.5 transition-colors"
                  >
                    <span className="w-8 text-muted uppercase">{t.language}</span>
                    <span className={`px-1.5 py-0.5 rounded text-[10px] ${STATUS_COLORS[t.status]}`}>
                      {STATUS_LABELS[t.status]}
                    </span>
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Versions */}
          {versions.length > 0 && (
            <div className="bg-surface border border-border rounded-xl p-5">
              <h4 className="font-title font-semibold text-white text-sm mb-3">Versions</h4>
              <div className="space-y-2">
                {versions.slice(0, 5).map(v => (
                  <div key={v.id} className="flex items-center justify-between text-xs">
                    <div>
                      <span className="text-white">v{v.version_number}</span>
                      <span className="text-muted ml-2">{new Date(v.created_at).toLocaleDateString('fr-FR')}</span>
                    </div>
                    <button
                      onClick={() => handleRestore(v.id)}
                      className="text-violet hover:text-violet-light transition-colors"
                    >
                      Restaurer
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Actions */}
          <div className="bg-surface border border-border rounded-xl p-5 space-y-2">
            <button
              onClick={handleDuplicate}
              className="w-full text-left px-3 py-2 text-sm text-white hover:bg-surface2 rounded-lg transition-colors"
            >
              Dupliquer
            </button>
            <button
              onClick={() => {
                if (article) updateArticle(article.id, { status: 'archived' }).then(() => loadArticle());
              }}
              className="w-full text-left px-3 py-2 text-sm text-muted hover:bg-surface2 rounded-lg transition-colors"
            >
              Archiver
            </button>
            <button
              onClick={handleDelete}
              className="w-full text-left px-3 py-2 text-sm text-danger hover:bg-surface2 rounded-lg transition-colors"
            >
              Supprimer
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
