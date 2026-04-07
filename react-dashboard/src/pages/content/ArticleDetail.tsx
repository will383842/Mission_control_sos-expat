import React, { useEffect, useState, useCallback, useRef } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchArticle,
  updateArticle,
  publishArticle,
  unpublishArticle,
  duplicateArticle,
  deleteArticle,
  fetchArticleVersions,
  restoreArticleVersion,
  evaluateSeoChecklist,
  fetchSeoChecklist,
} from '../../api/contentApi';
import ArticleQualityPanel from '../../components/content/ArticleQualityPanel';
import type {
  GeneratedArticle,
  ArticleFaq,
  ArticleVersion,
  ArticleImage,
  SeoChecklist,
  ContentStatus,
  GenerationPhase,
  GenerationLog,
} from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { errMsg } from './helpers';
import { useDirtyGuard } from '../../hooks/useDirtyGuard';

// ── Constants ───────────────────────────────────────────────
const STATUS_COLORS: Record<ContentStatus, string> = {
  draft: 'bg-muted/20 text-muted',
  generating: 'bg-amber/20 text-amber animate-pulse',
  review: 'bg-blue-500/20 text-blue-400',
  scheduled: 'bg-violet/20 text-violet',
  published: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted',
};

const STATUS_LABELS: Record<ContentStatus, string> = {
  draft: 'Brouillon',
  generating: 'Generation...',
  review: 'Revue',
  scheduled: 'Planifie',
  published: 'Publie',
  archived: 'Archive',
};

const GENERATION_PHASES: GenerationPhase[] = [
  'validate', 'research', 'title', 'excerpt', 'content',
  'faq', 'meta', 'jsonld', 'internal_links', 'external_links',
  'affiliate_links', 'images', 'slugs', 'quality', 'translations',
];

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

type TabKey = 'contenu' | 'seo' | 'faq' | 'medias' | 'publier';

const TABS: { key: TabKey; label: string }[] = [
  { key: 'contenu', label: 'Contenu' },
  { key: 'seo', label: 'SEO' },
  { key: 'faq', label: 'FAQ' },
  { key: 'medias', label: 'Medias' },
  { key: 'publier', label: 'Publier' },
];

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors w-full';

function seoColor(score: number): string {
  if (score >= 80) return 'text-success';
  if (score >= 50) return 'text-amber';
  return 'text-danger';
}

function seoBgColor(score: number): string {
  if (score >= 80) return 'bg-success';
  if (score >= 50) return 'bg-amber';
  return 'bg-danger';
}

function cents(n: number): string {
  return (n / 100).toFixed(2);
}

// ── Component ───────────────────────────────────────────────
export default function ArticleDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { markDirty, markClean } = useDirtyGuard();
  const [article, setArticle] = useState<GeneratedArticle | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<TabKey>('contenu');
  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; variant?: 'danger' | 'warning' | 'default'; action: () => void } | null>(null);

  // Versions
  const [versions, setVersions] = useState<ArticleVersion[]>([]);

  // SEO checklist
  const [seoChecklist, setSeoChecklist] = useState<SeoChecklist | null>(null);

  // Content editing
  const [editMode, setEditMode] = useState(false);
  const [editContent, setEditContent] = useState('');

  // SEO editing
  const [metaTitle, setMetaTitle] = useState('');
  const [metaDescription, setMetaDescription] = useState('');

  // FAQ editing
  const [faqs, setFaqs] = useState<ArticleFaq[]>([]);

  // Publish
  const [scheduleMode, setScheduleMode] = useState<'now' | 'schedule'>('now');
  const [scheduleDate, setScheduleDate] = useState('');
  const [scheduleTime, setScheduleTime] = useState('');

  // Polling for generation
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const [generationStart] = useState(Date.now());

  const loadArticle = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await fetchArticle(Number(id));
      const data = res.data as unknown as GeneratedArticle;
      setArticle(data);
      setEditContent(data.content_html ?? '');
      setMetaTitle(data.meta_title ?? '');
      setMetaDescription(data.meta_description ?? '');
      setFaqs(data.faqs ?? []);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur de chargement';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [id]);

  const loadVersions = useCallback(async () => {
    if (!id) return;
    try {
      const res = await fetchArticleVersions(Number(id));
      setVersions((res.data as unknown as ArticleVersion[]) ?? []);
    } catch {
      // non-blocking
    }
  }, [id]);

  const loadChecklist = useCallback(async () => {
    if (!id) return;
    try {
      const res = await fetchSeoChecklist(Number(id));
      setSeoChecklist(res.data as unknown as SeoChecklist);
    } catch {
      // non-blocking
    }
  }, [id]);

  useEffect(() => {
    loadArticle();
    loadVersions();
    loadChecklist();
  }, [loadArticle, loadVersions, loadChecklist]);

  // Poll during generation
  useEffect(() => {
    if (article?.status === 'generating') {
      pollRef.current = setInterval(() => {
        loadArticle();
      }, 3000);
    } else if (pollRef.current) {
      clearInterval(pollRef.current);
      pollRef.current = null;
    }
    return () => {
      if (pollRef.current) clearInterval(pollRef.current);
    };
  }, [article?.status, loadArticle]);

  const handleSaveContent = async () => {
    if (!article) return;
    setActionLoading('save-content');
    try {
      await updateArticle(article.id, { content_html: editContent });
      toast('success', 'Contenu sauvegarde.');
      setEditMode(false);
      markClean();
      loadArticle();
      loadVersions();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleSaveSeo = async () => {
    if (!article) return;
    setActionLoading('save-seo');
    try {
      await updateArticle(article.id, { meta_title: metaTitle, meta_description: metaDescription });
      toast('success', 'Meta SEO sauvegardees.');
      markClean();
      loadArticle();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleEvaluateSeo = async () => {
    if (!article) return;
    setActionLoading('evaluate-seo');
    try {
      const res = await evaluateSeoChecklist(article.id);
      setSeoChecklist(res.data as unknown as SeoChecklist);
      toast('success', 'Checklist SEO evaluee.');
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleSaveFaqs = async () => {
    if (!article) return;
    setActionLoading('save-faq');
    try {
      await updateArticle(article.id, { faqs } as Partial<GeneratedArticle>);
      toast('success', 'FAQ sauvegardees.');
      markClean();
      loadArticle();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleDuplicate = async () => {
    if (!article) return;
    setActionLoading('duplicate');
    try {
      const res = await duplicateArticle(article.id);
      const newArt = res.data as unknown as GeneratedArticle;
      toast('success', 'Article duplique.');
      navigate(`/content/articles/${newArt.id}`);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = () => {
    if (!article) return;
    setConfirmAction({
      title: 'Supprimer cet article',
      message: 'Cette action est irreversible. Confirmer la suppression ?',
      variant: 'danger',
      action: async () => {
        setActionLoading('delete');
        try {
          await deleteArticle(article.id);
          toast('success', 'Article supprime.');
          navigate('/content/articles');
        } catch (err) {
          toast('error', errMsg(err));
        } finally {
          setActionLoading(null);
        }
      },
    });
  };

  const handlePublish = async () => {
    if (!article) return;
    setActionLoading('publish');
    try {
      const data: { endpoint_id: number; scheduled_at?: string } = { endpoint_id: 1 };
      if (scheduleMode === 'schedule' && scheduleDate) {
        data.scheduled_at = `${scheduleDate}T${scheduleTime || '00:00'}:00`;
      }
      await publishArticle(article.id, data);
      toast('success', scheduleMode === 'schedule' ? 'Publication planifiee.' : 'Article publie.');
      loadArticle();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleRestoreVersion = (versionId: number) => {
    if (!article) return;
    setConfirmAction({
      title: 'Restaurer cette version',
      message: 'La version actuelle sera sauvegardee avant la restauration.',
      variant: 'warning',
      action: async () => {
        setActionLoading('restore');
        try {
          await restoreArticleVersion(article.id, versionId);
          toast('success', 'Version restauree.');
          loadArticle();
          loadVersions();
        } catch (err) {
          toast('error', errMsg(err));
        } finally {
          setActionLoading(null);
        }
      },
    });
  };

  const addFaq = () => {
    markDirty();
    setFaqs(prev => [...prev, { id: 0, article_id: Number(id), question: '', answer: '', sort_order: prev.length }]);
  };

  const updateFaq = (index: number, field: 'question' | 'answer', value: string) => {
    markDirty();
    setFaqs(prev => prev.map((f, i) => i === index ? { ...f, [field]: value } : f));
  };

  const removeFaq = (index: number) => {
    markDirty();
    setFaqs(prev => prev.filter((_, i) => i !== index));
  };

  // ── Loading state ──
  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-80" />
        <div className="animate-pulse bg-surface2 rounded-lg h-6 w-48" />
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
          <div className="lg:col-span-3 space-y-4">
            <div className="animate-pulse bg-surface2 rounded-xl h-10" />
            {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-48" />)}
          </div>
          <div className="space-y-4">
            {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-32" />)}
          </div>
        </div>
      </div>
    );
  }

  // ── Error state ──
  if (error || !article) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-surface border border-border rounded-xl p-8 text-center">
          <p className="text-danger mb-4">{error || 'Article introuvable'}</p>
          <button onClick={() => navigate('/content/articles')} className="text-sm text-violet hover:text-violet-light transition-colors">
            Retour aux articles
          </button>
        </div>
      </div>
    );
  }

  // ── Generation progress ──
  const isGenerating = article.status === 'generating';
  const generationLogs: GenerationLog[] = article.generation_logs ?? [];
  const completedPhases = new Set(generationLogs.filter(l => l.status === 'completed').map(l => l.phase));
  const runningPhases = new Set(generationLogs.filter(l => l.status === 'running').map(l => l.phase));
  const progressPct = GENERATION_PHASES.length > 0
    ? Math.round((completedPhases.size / GENERATION_PHASES.length) * 100)
    : 0;
  const elapsedSec = Math.round((Date.now() - generationStart) / 1000);

  const images: ArticleImage[] = article.images ?? [];
  const translations: GeneratedArticle[] = article.translations ?? [];

  // SEO checklist items for rendering
  const checklistItems: { label: string; passed: boolean }[] = seoChecklist ? [
    { label: 'H1 unique', passed: seoChecklist.has_single_h1 },
    { label: 'H1 contient le mot-cle', passed: seoChecklist.h1_contains_keyword },
    { label: 'Title tag contient le mot-cle', passed: seoChecklist.title_tag_contains_keyword },
    { label: 'Meta description contient un CTA', passed: seoChecklist.meta_desc_contains_cta },
    { label: 'Mot-cle dans le 1er paragraphe', passed: seoChecklist.keyword_in_first_paragraph },
    { label: 'Densite de mot-cle OK', passed: seoChecklist.keyword_density_ok },
    { label: 'Hierarchie des titres valide', passed: seoChecklist.heading_hierarchy_valid },
    { label: 'Contient table ou liste', passed: seoChecklist.has_table_or_list },
    { label: 'Schema Article', passed: seoChecklist.has_article_schema },
    { label: 'Schema FAQ', passed: seoChecklist.has_faq_schema },
    { label: 'Schema Breadcrumb', passed: seoChecklist.has_breadcrumb_schema },
    { label: 'Schema Speakable', passed: seoChecklist.has_speakable_schema },
    { label: 'Schema HowTo', passed: seoChecklist.has_howto_schema },
    { label: 'JSON-LD valide', passed: seoChecklist.json_ld_valid },
    { label: 'Boite auteur', passed: seoChecklist.has_author_box },
    { label: 'Sources citees', passed: seoChecklist.has_sources_cited },
    { label: 'Date de publication', passed: seoChecklist.has_date_published },
    { label: 'Date de modification', passed: seoChecklist.has_date_modified },
    { label: 'Liens officiels', passed: seoChecklist.has_official_links },
    { label: 'Paragraphe de definition', passed: seoChecklist.has_definition_paragraph },
    { label: 'Etapes numerotees', passed: seoChecklist.has_numbered_steps },
    { label: 'Table de comparaison', passed: seoChecklist.has_comparison_table },
    { label: 'Contenu speakable', passed: seoChecklist.has_speakable_content },
    { label: 'Reponses directes', passed: seoChecklist.has_direct_answers },
    { label: 'Images avec alt', passed: seoChecklist.all_images_have_alt },
    { label: 'Image principale avec mot-cle', passed: seoChecklist.featured_image_has_keyword },
    { label: 'Hreflang complet', passed: seoChecklist.hreflang_complete },
  ] : [];

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div>
        <div className="flex items-center gap-2 text-xs text-muted mb-2">
          <button onClick={() => navigate('/content/overview')} className="hover:text-white transition-colors">Contenu</button>
          <span>/</span>
          <button onClick={() => navigate('/content/articles')} className="hover:text-white transition-colors">Articles</button>
          <span>/</span>
          <span className="text-white truncate max-w-[200px]">{article.title}</span>
        </div>
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <h2 className="font-title text-2xl font-bold text-white">{article.title}</h2>
            <div className="flex items-center gap-3 mt-2 flex-wrap">
              <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[article.status]}`}>
                {STATUS_LABELS[article.status]}
              </span>
              <span className="text-xs text-muted">{article.language.toUpperCase()}</span>
              {article.country && <span className="text-xs text-muted capitalize">{article.country}</span>}
              <span className="text-xs text-muted">{article.word_count.toLocaleString('fr-FR')} mots</span>
            </div>
          </div>
          <div className="flex items-center gap-2 flex-wrap">
            <button onClick={handleDuplicate} disabled={!!actionLoading} className="px-3 py-1.5 bg-surface2 text-muted hover:text-white text-xs rounded-lg border border-border transition-colors disabled:opacity-50">
              Dupliquer
            </button>
            <button onClick={handleDelete} disabled={!!actionLoading} className="px-3 py-1.5 bg-surface2 text-danger hover:bg-danger/20 text-xs rounded-lg border border-border transition-colors disabled:opacity-50">
              Supprimer
            </button>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Main content area */}
        <div className="lg:col-span-3 space-y-4">
          {/* Tabs */}
          <div className="flex items-center gap-1 bg-surface border border-border rounded-xl p-1">
            {TABS.map(tab => (
              <button
                key={tab.key}
                onClick={() => setActiveTab(tab.key)}
                className={`px-4 py-2 text-sm rounded-lg transition-colors ${
                  activeTab === tab.key ? 'bg-violet text-white' : 'text-muted hover:text-white'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>

          {/* Tab: Contenu */}
          {activeTab === 'contenu' && (
            <div className="bg-surface border border-border rounded-xl p-5">
              {isGenerating ? (
                <div className="space-y-4">
                  <h3 className="font-title font-semibold text-white">Generation en cours...</h3>
                  <div className="w-full h-3 bg-surface2 rounded-full overflow-hidden">
                    <div className="h-full bg-violet rounded-full transition-all animate-pulse" style={{ width: `${progressPct}%` }} />
                  </div>
                  <p className="text-xs text-muted">{progressPct}% — {elapsedSec}s ecoule</p>
                  <div className="space-y-2">
                    {GENERATION_PHASES.map(phase => {
                      const isComplete = completedPhases.has(phase);
                      const isRunning = runningPhases.has(phase);
                      return (
                        <div key={phase} className="flex items-center gap-2 text-sm">
                          <span className={`w-5 text-center ${isComplete ? 'text-success' : isRunning ? 'text-amber animate-pulse' : 'text-muted'}`}>
                            {isComplete ? '\u2713' : isRunning ? '\u25CB' : '\u2022'}
                          </span>
                          <span className={isComplete ? 'text-white' : isRunning ? 'text-amber' : 'text-muted'}>
                            {PHASE_LABELS[phase]}
                          </span>
                        </div>
                      );
                    })}
                  </div>
                </div>
              ) : (
                <div>
                  <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => setEditMode(false)}
                        className={`px-3 py-1 text-xs rounded-lg transition-colors ${!editMode ? 'bg-violet text-white' : 'text-muted hover:text-white'}`}
                      >
                        Apercu
                      </button>
                      <button
                        onClick={() => setEditMode(true)}
                        className={`px-3 py-1 text-xs rounded-lg transition-colors ${editMode ? 'bg-violet text-white' : 'text-muted hover:text-white'}`}
                      >
                        Editer
                      </button>
                    </div>
                    {editMode && (
                      <button
                        onClick={handleSaveContent}
                        disabled={actionLoading === 'save-content'}
                        className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-xs rounded-lg transition-colors disabled:opacity-50"
                      >
                        {actionLoading === 'save-content' ? 'Sauvegarde...' : 'Sauvegarder'}
                      </button>
                    )}
                  </div>
                  {editMode ? (
                    <textarea
                      value={editContent}
                      onChange={e => { setEditContent(e.target.value); markDirty(); }}
                      className={inputClass + ' min-h-[500px] font-mono text-xs resize-y'}
                    />
                  ) : (
                    <div
                      className="prose prose-invert max-w-none text-sm"
                      dangerouslySetInnerHTML={{ __html: article.content_html ?? '<p class="text-muted">Pas de contenu</p>' }}
                    />
                  )}
                </div>
              )}
            </div>
          )}

          {/* Tab: SEO */}
          {activeTab === 'seo' && (
            <div className="space-y-4">
              <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
                <h3 className="font-title font-semibold text-white">Meta SEO</h3>
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <label className="text-xs text-muted uppercase tracking-wide">Meta title</label>
                    <span className={`text-xs ${(metaTitle.length) <= 60 ? 'text-success' : 'text-danger'}`}>{metaTitle.length}/60</span>
                  </div>
                  <input type="text" value={metaTitle} onChange={e => { setMetaTitle(e.target.value); markDirty(); }} className={inputClass} />
                </div>
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <label className="text-xs text-muted uppercase tracking-wide">Meta description</label>
                    <span className={`text-xs ${(metaDescription.length) <= 160 ? 'text-success' : 'text-danger'}`}>{metaDescription.length}/160</span>
                  </div>
                  <textarea value={metaDescription} onChange={e => { setMetaDescription(e.target.value); markDirty(); }} rows={3} className={inputClass + ' resize-y'} />
                </div>
                <div>
                  <label className="text-xs text-muted uppercase tracking-wide">Slug</label>
                  <p className="text-sm text-white mt-1 bg-bg border border-border rounded-lg px-3 py-2">{article.slug}</p>
                </div>
                <div>
                  <label className="text-xs text-muted uppercase tracking-wide">Mot-cle principal</label>
                  <p className="text-sm text-white mt-1">{article.keywords_primary ?? '-'}</p>
                  {article.keyword_density && article.keywords_primary && (
                    <p className="text-xs text-muted mt-0.5">Densite: {(article.keyword_density[article.keywords_primary] ?? 0).toFixed(2)}%</p>
                  )}
                </div>
                <button
                  onClick={handleSaveSeo}
                  disabled={actionLoading === 'save-seo'}
                  className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                >
                  {actionLoading === 'save-seo' ? 'Sauvegarde...' : 'Sauvegarder les meta'}
                </button>
              </div>

              {/* SEO Checklist */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="font-title font-semibold text-white">Checklist SEO</h3>
                  <button
                    onClick={handleEvaluateSeo}
                    disabled={actionLoading === 'evaluate-seo'}
                    className="px-3 py-1 text-xs bg-violet hover:bg-violet/90 text-white rounded-lg transition-colors disabled:opacity-50"
                  >
                    {actionLoading === 'evaluate-seo' ? 'Evaluation...' : 'Evaluer'}
                  </button>
                </div>
                {seoChecklist ? (
                  <div>
                    <div className="flex items-center gap-3 mb-4">
                      <span className={`text-2xl font-bold ${seoColor(seoChecklist.overall_checklist_score)}`}>
                        {seoChecklist.overall_checklist_score}/100
                      </span>
                      <div className="flex-1 h-2 bg-surface2 rounded-full overflow-hidden">
                        <div className={`h-full rounded-full ${seoBgColor(seoChecklist.overall_checklist_score)}`} style={{ width: `${seoChecklist.overall_checklist_score}%` }} />
                      </div>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                      {checklistItems.map((item, i) => (
                        <div key={i} className="flex items-center gap-2 text-sm">
                          <span className={item.passed ? 'text-success' : 'text-danger'}>{item.passed ? '\u2713' : '\u2717'}</span>
                          <span className={item.passed ? 'text-white' : 'text-muted'}>{item.label}</span>
                        </div>
                      ))}
                    </div>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 pt-4 border-t border-border">
                      <div><p className="text-xs text-muted">Liens internes</p><p className="text-sm text-white font-medium">{seoChecklist.internal_links_count}</p></div>
                      <div><p className="text-xs text-muted">Liens externes</p><p className="text-sm text-white font-medium">{seoChecklist.external_links_count}</p></div>
                      <div><p className="text-xs text-muted">Images</p><p className="text-sm text-white font-medium">{seoChecklist.images_count}</p></div>
                      <div><p className="text-xs text-muted">Traductions</p><p className="text-sm text-white font-medium">{seoChecklist.translations_count}</p></div>
                    </div>
                  </div>
                ) : (
                  <p className="text-muted text-sm">Cliquez sur "Evaluer" pour lancer la checklist SEO.</p>
                )}
              </div>

              {/* JSON-LD preview */}
              {article.json_ld && (
                <details className="bg-surface border border-border rounded-xl">
                  <summary className="px-5 py-3 text-sm text-muted hover:text-white transition-colors cursor-pointer">JSON-LD Preview</summary>
                  <pre className="px-5 pb-5 text-xs text-muted overflow-x-auto">{JSON.stringify(article.json_ld, null, 2)}</pre>
                </details>
              )}
            </div>
          )}

          {/* Tab: FAQ */}
          {activeTab === 'faq' && (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="font-title font-semibold text-white">FAQ ({faqs.length})</h3>
                <div className="flex items-center gap-2">
                  <button onClick={addFaq} className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white border border-border rounded-lg transition-colors">
                    + Ajouter une question
                  </button>
                  <button
                    onClick={handleSaveFaqs}
                    disabled={actionLoading === 'save-faq'}
                    className="px-3 py-1 text-xs bg-violet hover:bg-violet/90 text-white rounded-lg transition-colors disabled:opacity-50"
                  >
                    {actionLoading === 'save-faq' ? 'Sauvegarde...' : 'Sauvegarder'}
                  </button>
                </div>
              </div>
              {faqs.length === 0 ? (
                <div className="text-center py-8">
                  <p className="text-muted text-sm mb-3">Aucune FAQ. Ajoutez des questions pour ameliorer le schema FAQ.</p>
                  <button onClick={addFaq} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
                    Ajouter une question
                  </button>
                </div>
              ) : (
                <div className="space-y-3">
                  {faqs.map((faq, index) => (
                    <div key={index} className="bg-surface2/50 border border-border rounded-lg p-4 space-y-2">
                      <div className="flex items-start justify-between gap-2">
                        <span className="text-xs text-muted font-medium mt-2">Q{index + 1}</span>
                        <button onClick={() => removeFaq(index)} className="text-xs text-danger hover:text-red-300 transition-colors">Supprimer</button>
                      </div>
                      <textarea
                        value={faq.question}
                        onChange={e => updateFaq(index, 'question', e.target.value)}
                        placeholder="Question..."
                        rows={2}
                        className={inputClass + ' resize-y'}
                      />
                      <textarea
                        value={faq.answer}
                        onChange={e => updateFaq(index, 'answer', e.target.value)}
                        placeholder="Reponse..."
                        rows={3}
                        className={inputClass + ' resize-y'}
                      />
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Tab: Medias */}
          {activeTab === 'medias' && (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
              <h3 className="font-title font-semibold text-white">Medias</h3>
              {/* Featured image */}
              {article.featured_image_url && (
                <div>
                  <p className="text-xs text-muted uppercase tracking-wide mb-2">Image principale</p>
                  <div className="relative rounded-xl overflow-hidden border border-border">
                    <img src={article.featured_image_url} alt={article.featured_image_alt ?? ''} className="w-full max-h-72 object-cover" />
                    {article.featured_image_attribution && (
                      <p className="text-[10px] text-muted mt-1 px-2 py-1 bg-surface2">{article.featured_image_attribution}</p>
                    )}
                  </div>
                </div>
              )}
              {/* Image gallery */}
              {images.length > 0 ? (
                <div>
                  <p className="text-xs text-muted uppercase tracking-wide mb-2">Galerie ({images.length})</p>
                  <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    {images.map(img => (
                      <div key={img.id} className="rounded-lg overflow-hidden border border-border">
                        <img src={img.url} alt={img.alt_text ?? ''} className="w-full h-32 object-cover" />
                        <div className="p-2 bg-surface2">
                          <p className="text-[10px] text-muted truncate">{img.alt_text ?? 'Sans description'}</p>
                          <span className="text-[10px] px-1 py-0.5 rounded bg-muted/20 text-muted">{img.source}</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ) : !article.featured_image_url ? (
                <div className="text-center py-8">
                  <p className="text-muted text-sm">Aucune image associee.</p>
                </div>
              ) : null}
            </div>
          )}

          {/* Tab: Publier */}
          {activeTab === 'publier' && (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
              <h3 className="font-title font-semibold text-white">Publication</h3>
              <div className="flex items-center gap-3 mb-4">
                <span className="text-sm text-muted">Statut actuel:</span>
                <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[article.status]}`}>
                  {STATUS_LABELS[article.status]}
                </span>
              </div>

              {/* Pre-pub checklist */}
              <div className="bg-surface2/50 border border-border rounded-lg p-4 space-y-2">
                <p className="text-xs text-muted uppercase tracking-wide mb-2">Pre-publication</p>
                <div className="flex items-center gap-2 text-sm">
                  <span className={article.seo_score >= 80 ? 'text-success' : 'text-danger'}>{article.seo_score >= 80 ? '\u2713' : '\u2717'}</span>
                  <span className="text-white">Score SEO {'>'} 80 (actuel: {article.seo_score})</span>
                </div>
                <div className="flex items-center gap-2 text-sm">
                  <span className={(faqs.length > 0) ? 'text-success' : 'text-danger'}>{(faqs.length > 0) ? '\u2713' : '\u2717'}</span>
                  <span className="text-white">FAQ presentes ({faqs.length})</span>
                </div>
                <div className="flex items-center gap-2 text-sm">
                  <span className={article.meta_title ? 'text-success' : 'text-danger'}>{article.meta_title ? '\u2713' : '\u2717'}</span>
                  <span className="text-white">Meta title defini</span>
                </div>
                <div className="flex items-center gap-2 text-sm">
                  <span className={article.meta_description ? 'text-success' : 'text-danger'}>{article.meta_description ? '\u2713' : '\u2717'}</span>
                  <span className="text-white">Meta description definie</span>
                </div>
              </div>

              {/* Schedule */}
              <div className="space-y-3">
                <div className="flex items-center gap-4">
                  <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                    <input type="radio" name="schedule" checked={scheduleMode === 'now'} onChange={() => setScheduleMode('now')} className="accent-violet" />
                    Publier maintenant
                  </label>
                  <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
                    <input type="radio" name="schedule" checked={scheduleMode === 'schedule'} onChange={() => setScheduleMode('schedule')} className="accent-violet" />
                    Planifier
                  </label>
                </div>
                {scheduleMode === 'schedule' && (
                  <div className="flex gap-3">
                    <input type="date" value={scheduleDate} onChange={e => setScheduleDate(e.target.value)} className={inputClass + ' max-w-[200px]'} />
                    <input type="time" value={scheduleTime} onChange={e => setScheduleTime(e.target.value)} className={inputClass + ' max-w-[150px]'} />
                  </div>
                )}
              </div>

              <button
                onClick={handlePublish}
                disabled={!!actionLoading || article.status === 'published'}
                className="w-full py-3 bg-success hover:bg-success/90 text-white font-bold rounded-lg transition-colors disabled:opacity-50 text-sm"
              >
                {actionLoading === 'publish'
                  ? 'Publication...'
                  : article.status === 'published'
                    ? 'Deja publie'
                    : scheduleMode === 'schedule'
                      ? 'Planifier la publication'
                      : 'Publier maintenant'}
              </button>

              {/* View on blog + Unpublish */}
              {article.status === 'published' && (
                <div className="flex gap-2 mt-2">
                  {(article as any).external_url && (
                    <a
                      href={(article as any).external_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="flex-1 py-2 text-center bg-violet/20 text-violet-light font-medium rounded-lg hover:bg-violet/30 transition-colors text-sm"
                    >
                      Voir sur le blog
                    </a>
                  )}
                  <button
                    onClick={async () => {
                      setActionLoading('unpublish');
                      try {
                        await unpublishArticle(article.id);
                        toast.success('Article depublie');
                        loadArticle();
                      } catch { toast.error('Erreur depublication'); }
                      finally { setActionLoading(null); }
                    }}
                    disabled={!!actionLoading}
                    className="flex-1 py-2 bg-danger/20 text-danger font-medium rounded-lg hover:bg-danger/30 transition-colors disabled:opacity-50 text-sm"
                  >
                    {actionLoading === 'unpublish' ? '...' : 'Depublier'}
                  </button>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Right sidebar */}
        <div className="space-y-4">
          {/* SEO Score gauge */}
          <div className="bg-surface border border-border rounded-xl p-5 text-center">
            <p className="text-xs text-muted uppercase tracking-wide mb-2">Score SEO</p>
            <div className="relative w-20 h-20 mx-auto">
              <svg viewBox="0 0 36 36" className="w-20 h-20">
                <path
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="3"
                  className="text-surface2"
                />
                <path
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="3"
                  strokeDasharray={`${article.seo_score}, 100`}
                  className={seoColor(article.seo_score)}
                />
              </svg>
              <span className={`absolute inset-0 flex items-center justify-center text-xl font-bold ${seoColor(article.seo_score)}`}>
                {article.seo_score}
              </span>
            </div>
          </div>

          {/* Quick info */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h4 className="font-title font-semibold text-white mb-3">Informations</h4>
            <div className="space-y-2 text-sm">
              <div className="flex justify-between"><span className="text-muted">Mots</span><span className="text-white">{article.word_count.toLocaleString('fr-FR')}</span></div>
              <div className="flex justify-between"><span className="text-muted">Lecture</span><span className="text-white">{article.reading_time_minutes} min</span></div>
              <div className="flex justify-between"><span className="text-muted">FAQ</span><span className="text-white">{faqs.length}</span></div>
              <div className="flex justify-between"><span className="text-muted">Qualite</span><span className="text-white">{article.quality_score}/100</span></div>
              <div className="flex justify-between"><span className="text-muted">Cout</span><span className="text-white">${cents(article.generation_cost_cents)}</span></div>
              <div className="flex justify-between"><span className="text-muted">Cree le</span><span className="text-white">{new Date(article.created_at).toLocaleDateString('fr-FR')}</span></div>
              {article.published_at && (
                <div className="flex justify-between"><span className="text-muted">Publie le</span><span className="text-white">{new Date(article.published_at).toLocaleDateString('fr-FR')}</span></div>
              )}
            </div>
          </div>

          {/* Translations */}
          {translations.length > 0 && (
            <div className="bg-surface border border-border rounded-xl p-5">
              <h4 className="font-title font-semibold text-white mb-3">Traductions</h4>
              <div className="space-y-2">
                {translations.map(t => (
                  <div key={t.id} className="flex items-center justify-between">
                    <span className="text-xs text-muted">{t.language.toUpperCase()}</span>
                    <div className="flex items-center gap-2">
                      <span className={`inline-block px-1.5 py-0.5 rounded text-[10px] ${STATUS_COLORS[t.status]}`}>
                        {STATUS_LABELS[t.status]}
                      </span>
                      <button onClick={() => navigate(`/content/articles/${t.id}`)} className="text-xs text-violet hover:text-violet-light transition-colors">
                        Voir
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Quality & Plagiarism */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h4 className="font-title font-semibold text-white mb-3">Qualite & Plagiat</h4>
            <ArticleQualityPanel
              articleId={article.id}
              qualityScore={article.quality_score}
              seoScore={article.seo_score}
              readabilityScore={article.readability_score}
            />
          </div>

          {/* Versions */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h4 className="font-title font-semibold text-white mb-3">Versions ({versions.length})</h4>
            {versions.length === 0 ? (
              <p className="text-xs text-muted">Aucune version precedente.</p>
            ) : (
              <div className="space-y-2">
                {versions.slice(0, 10).map(v => (
                  <div key={v.id} className="flex items-center justify-between">
                    <div>
                      <p className="text-xs text-white">v{v.version_number}</p>
                      <p className="text-[10px] text-muted">{new Date(v.created_at).toLocaleDateString('fr-FR')}</p>
                    </div>
                    <button
                      onClick={() => handleRestoreVersion(v.id)}
                      disabled={!!actionLoading}
                      className="text-xs text-violet hover:text-violet-light transition-colors disabled:opacity-50"
                    >
                      Restaurer
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      <ConfirmModal
        open={!!confirmAction}
        title={confirmAction?.title ?? ''}
        message={confirmAction?.message ?? ''}
        variant={confirmAction?.variant ?? 'danger'}
        confirmLabel="Confirmer"
        onConfirm={() => { confirmAction?.action(); setConfirmAction(null); }}
        onCancel={() => setConfirmAction(null)}
      />
    </div>
  );
}
