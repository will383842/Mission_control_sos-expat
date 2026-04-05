import api from './client';
import type {
  GeneratedArticle,
  Comparative,
  LandingPage,
  PressRelease,
  PressDossier,
  ContentCampaign,
  ContentCampaignItem,
  GenerationPreset,
  PromptTemplate,
  GenerationStats,
  GenerationLog,
  ArticleVersion,
  SeoAnalysis,
  SeoDashboard,
  HreflangMatrixEntry,
  InternalLinksGraph,
  PublishingEndpoint,
  PublicationQueueItem,
  PublicationSchedule,
  CostOverview,
  CostBreakdownEntry,
  CostTrendEntry,
  UnsplashImage,
  PaginatedResponse,
  GenerateArticleParams,
  GenerateComparativeParams,
  TopicCluster,
  QaEntry,
  KeywordTracking,
  KeywordGap,
  KeywordCannibalization,
  ArticleKeyword,
  TranslationBatch,
  TranslationOverview,
  SeoChecklist,
  QuestionCluster,
  QuestionClusterStats,
  TaxonomyDistribution,
  PublicationStats,
  QualityMonitoringData,
} from '../types/content';

// ============================================================
// ARTICLES
// ============================================================

export const fetchArticles = (params?: {
  status?: string;
  language?: string;
  country?: string;
  search?: string;
  page?: number;
}) =>
  api.get<PaginatedResponse<GeneratedArticle>>('/content-gen/articles', { params });

export const fetchArticle = (id: number) =>
  api.get<GeneratedArticle>(`/content-gen/articles/${id}`);

export const generateArticle = (params: GenerateArticleParams) =>
  api.post<GeneratedArticle>('/content-gen/articles', params);

export const updateArticle = (id: number, data: Partial<GeneratedArticle>) =>
  api.put<GeneratedArticle>(`/content-gen/articles/${id}`, data);

export const deleteArticle = (id: number) =>
  api.delete(`/content-gen/articles/${id}`);

export const publishArticle = (id: number, data: { endpoint_id: number; scheduled_at?: string }) =>
  api.post(`/content-gen/articles/${id}/publish`, data);

export const unpublishArticle = (id: number) =>
  api.post(`/content-gen/articles/${id}/unpublish`);

export const duplicateArticle = (id: number) =>
  api.post<GeneratedArticle>(`/content-gen/articles/${id}/duplicate`);

export const bulkPublishArticles = (data: { article_ids: number[]; endpoint_id: number }) =>
  api.post('/content-gen/articles/bulk-publish', data);

export const bulkDeleteArticles = (ids: number[]) =>
  api.delete('/content-gen/articles/bulk-delete', { data: { article_ids: ids } });

export const fetchArticleVersions = (id: number) =>
  api.get<ArticleVersion[]>(`/content-gen/articles/${id}/versions`);

export const restoreArticleVersion = (articleId: number, versionId: number) =>
  api.post<GeneratedArticle>(`/content-gen/articles/${articleId}/versions/${versionId}/restore`);

// ============================================================
// COMPARATIVES
// ============================================================

export const fetchComparatives = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<Comparative>>('/content-gen/comparatives', { params });

export const fetchComparative = (id: number) =>
  api.get<Comparative>(`/content-gen/comparatives/${id}`);

export const generateComparative = (params: GenerateComparativeParams) =>
  api.post<Comparative>('/content-gen/comparatives', params);

export const updateComparative = (id: number, data: Partial<Comparative>) =>
  api.put<Comparative>(`/content-gen/comparatives/${id}`, data);

export const deleteComparative = (id: number) =>
  api.delete(`/content-gen/comparatives/${id}`);

export const publishComparative = (id: number, data: { endpoint_id: number; scheduled_at?: string }) =>
  api.post(`/content-gen/comparatives/${id}/publish`, data);

// ============================================================
// LANDING PAGES
// ============================================================

export const fetchLandings = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<LandingPage>>('/content-gen/landings', { params });

export const fetchLanding = (id: number) =>
  api.get<LandingPage>(`/content-gen/landings/${id}`);

export const createLanding = (data: Partial<LandingPage>) =>
  api.post<LandingPage>('/content-gen/landings', data);

export const updateLanding = (id: number, data: Partial<LandingPage>) =>
  api.put<LandingPage>(`/content-gen/landings/${id}`, data);

export const deleteLanding = (id: number) =>
  api.delete(`/content-gen/landings/${id}`);

export const publishLanding = (id: number, data: { endpoint_id: number }) =>
  api.post(`/content-gen/landings/${id}/publish`, data);

export const manageLandingCtas = (id: number, ctas: { url: string; text: string; position: string; style: string; sort_order: number }[]) =>
  api.post(`/content-gen/landings/${id}/ctas`, { ctas });

// ============================================================
// PRESS RELEASES
// ============================================================

export const fetchPressReleases = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<PressRelease>>('/content-gen/press/releases', { params });

export const fetchPressRelease = (id: number) =>
  api.get<PressRelease>(`/content-gen/press/releases/${id}`);

export const createPressRelease = (data: Partial<PressRelease>) =>
  api.post<PressRelease>('/content-gen/press/releases', data);

export const updatePressRelease = (id: number, data: Partial<PressRelease>) =>
  api.put<PressRelease>(`/content-gen/press/releases/${id}`, data);

export const deletePressRelease = (id: number) =>
  api.delete(`/content-gen/press/releases/${id}`);

export const publishPressRelease = (id: number, data: { endpoint_id: number }) =>
  api.post(`/content-gen/press/releases/${id}/publish`, data);

export const exportPressReleasePdf = (id: number) =>
  api.get(`/content-gen/press/releases/${id}/export-pdf`, { responseType: 'blob' });

export const exportPressReleaseWord = (id: number) =>
  api.get(`/content-gen/press/releases/${id}/export-word`, { responseType: 'blob' });

// ============================================================
// PRESS DOSSIERS
// ============================================================

export const fetchPressDossiers = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<PressDossier>>('/content-gen/press/dossiers', { params });

export const fetchPressDossier = (id: number) =>
  api.get<PressDossier>(`/content-gen/press/dossiers/${id}`);

export const createPressDossier = (data: Partial<PressDossier>) =>
  api.post<PressDossier>('/content-gen/press/dossiers', data);

export const updatePressDossier = (id: number, data: Partial<PressDossier>) =>
  api.put<PressDossier>(`/content-gen/press/dossiers/${id}`, data);

export const deletePressDossier = (id: number) =>
  api.delete(`/content-gen/press/dossiers/${id}`);

export const addDossierItem = (dossierId: number, data: { itemable_type: string; itemable_id: number }) =>
  api.post(`/content-gen/press/dossiers/${dossierId}/items`, data);

export const removeDossierItem = (dossierId: number, itemId: number) =>
  api.delete(`/content-gen/press/dossiers/${dossierId}/items/${itemId}`);

export const reorderDossierItems = (dossierId: number, itemIds: number[]) =>
  api.put(`/content-gen/press/dossiers/${dossierId}/reorder`, { item_ids: itemIds });

export const exportDossierPdf = (id: number) =>
  api.get(`/content-gen/press/dossiers/${id}/export-pdf`, { responseType: 'blob' });

// ============================================================
// CAMPAIGNS
// ============================================================

export const fetchCampaigns = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<ContentCampaign>>('/content-gen/campaigns', { params });

export const fetchCampaign = (id: number) =>
  api.get<ContentCampaign>(`/content-gen/campaigns/${id}`);

export const createCampaign = (data: Partial<ContentCampaign>) =>
  api.post<ContentCampaign>('/content-gen/campaigns', data);

export const updateCampaign = (id: number, data: Partial<ContentCampaign>) =>
  api.put<ContentCampaign>(`/content-gen/campaigns/${id}`, data);

export const deleteCampaign = (id: number) =>
  api.delete(`/content-gen/campaigns/${id}`);

export const startCampaign = (id: number) =>
  api.post<ContentCampaign>(`/content-gen/campaigns/${id}/start`);

export const pauseCampaign = (id: number) =>
  api.post<ContentCampaign>(`/content-gen/campaigns/${id}/pause`);

export const resumeCampaign = (id: number) =>
  api.post<ContentCampaign>(`/content-gen/campaigns/${id}/resume`);

export const cancelCampaign = (id: number) =>
  api.post<ContentCampaign>(`/content-gen/campaigns/${id}/cancel`);

export const fetchCampaignItems = (id: number) =>
  api.get<ContentCampaignItem[]>(`/content-gen/campaigns/${id}/items`);

// ============================================================
// GENERATION
// ============================================================

export const fetchGenerationStats = () =>
  api.get<GenerationStats>('/content-gen/generation/stats');

export const fetchGenerationHistory = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<GenerationLog>>('/content-gen/generation/history', { params });

export const fetchPresets = () =>
  api.get<GenerationPreset[]>('/content-gen/generation/presets');

export const createPreset = (data: Partial<GenerationPreset>) =>
  api.post<GenerationPreset>('/content-gen/generation/presets', data);

export const updatePreset = (id: number, data: Partial<GenerationPreset>) =>
  api.put<GenerationPreset>(`/content-gen/generation/presets/${id}`, data);

export const deletePreset = (id: number) =>
  api.delete(`/content-gen/generation/presets/${id}`);

export const fetchPromptTemplates = () =>
  api.get<PromptTemplate[]>('/content-gen/generation/prompts');

export const createPromptTemplate = (data: Partial<PromptTemplate>) =>
  api.post<PromptTemplate>('/content-gen/generation/prompts', data);

export const updatePromptTemplate = (id: number, data: Partial<PromptTemplate>) =>
  api.put<PromptTemplate>(`/content-gen/generation/prompts/${id}`, data);

export const deletePromptTemplate = (id: number) =>
  api.delete(`/content-gen/generation/prompts/${id}`);

export const testPromptTemplate = (data: { prompt_id: number; variables: Record<string, string> }) =>
  api.post<{ output: string }>('/content-gen/generation/prompts/test', data);

// ============================================================
// SEO
// ============================================================

export const fetchSeoDashboard = () =>
  api.get<SeoDashboard>('/content-gen/seo/dashboard');

export const analyzeSeo = (data: { model_type: string; model_id: number }) =>
  api.post<SeoAnalysis>('/content-gen/seo/analyze', data);

export const fetchHreflangMatrix = () =>
  api.get<HreflangMatrixEntry[]>('/content-gen/seo/hreflang-matrix');

export const fetchInternalLinksGraph = () =>
  api.get<InternalLinksGraph>('/content-gen/seo/internal-links-graph');

export const fetchOrphanedArticles = () =>
  api.get<GeneratedArticle[]>('/content-gen/seo/orphaned');

export const fixOrphanedArticle = (articleId: number) =>
  api.post('/content-gen/seo/fix-orphaned', { article_id: articleId });

// ============================================================
// PUBLISHING
// ============================================================

export const fetchEndpoints = () =>
  api.get<PublishingEndpoint[]>('/content-gen/publishing/endpoints');

export const createEndpoint = (data: Partial<PublishingEndpoint>) =>
  api.post<PublishingEndpoint>('/content-gen/publishing/endpoints', data);

export const updateEndpoint = (id: number, data: Partial<PublishingEndpoint>) =>
  api.put<PublishingEndpoint>(`/content-gen/publishing/endpoints/${id}`, data);

export const deleteEndpoint = (id: number) =>
  api.delete(`/content-gen/publishing/endpoints/${id}`);

export const fetchPublicationQueue = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<PublicationQueueItem>>('/content-gen/publishing/queue', { params });

export const executeQueueItem = (id: number) =>
  api.post<PublicationQueueItem>(`/content-gen/publishing/queue/${id}/execute`);

export const cancelQueueItem = (id: number) =>
  api.post<PublicationQueueItem>(`/content-gen/publishing/queue/${id}/cancel`);

export const fetchSchedule = (endpointId: number) =>
  api.get<PublicationSchedule>(`/content-gen/publishing/endpoints/${endpointId}/schedule`);

export const updateSchedule = (endpointId: number, data: Partial<PublicationSchedule>) =>
  api.put<PublicationSchedule>(`/content-gen/publishing/endpoints/${endpointId}/schedule`, data);

// ============================================================
// COSTS
// ============================================================

export const fetchCostOverview = () =>
  api.get<CostOverview>('/content-gen/costs/overview');

export const fetchCostBreakdown = (params?: { period?: string }) =>
  api.get<CostBreakdownEntry[]>('/content-gen/costs/breakdown', { params });

export const fetchCostTrends = (params?: { days?: number }) =>
  api.get<CostTrendEntry[]>('/content-gen/costs/trends', { params });

// ============================================================
// MEDIA
// ============================================================

export const searchUnsplash = (query: string, perPage?: number) =>
  api.get<UnsplashImage[]>('/content-gen/media/unsplash', { params: { query, per_page: perPage } });

export const generateDalleImage = (prompt: string, size?: string) =>
  api.post<{ url: string }>('/content-gen/media/generate-image', { prompt, size });

// ============================================================
// CLUSTERS
// ============================================================

export const fetchClusters = (params?: {
  country?: string;
  category?: string;
  status?: string;
  page?: number;
}) =>
  api.get<PaginatedResponse<TopicCluster>>('/content-gen/clusters', { params });

export const fetchCluster = (id: number) =>
  api.get<TopicCluster>(`/content-gen/clusters/${id}`);

export const autoCluster = (data: { country: string; category?: string }) =>
  api.post<{ clusters_created: number; message: string }>('/content-gen/clusters/auto-cluster', data);

export const generateClusterBrief = (id: number) =>
  api.post<TopicCluster>(`/content-gen/clusters/${id}/brief`);

export const generateFromCluster = (id: number) =>
  api.post<TopicCluster>(`/content-gen/clusters/${id}/generate`);

export const generateClusterQa = (id: number) =>
  api.post<{ qa_created: number }>(`/content-gen/clusters/${id}/generate-qa`);

export const deleteCluster = (id: number) =>
  api.delete(`/content-gen/clusters/${id}`);

// ============================================================
// Q&A
// ============================================================

export const fetchQaEntries = (params?: {
  language?: string;
  country?: string;
  category?: string;
  status?: string;
  source_type?: string;
  search?: string;
  page?: number;
}) =>
  api.get<PaginatedResponse<QaEntry>>('/content-gen/qa', { params });

export const fetchQaEntry = (id: number) =>
  api.get<QaEntry>(`/content-gen/qa/${id}`);

export const createQaEntry = (data: Partial<QaEntry>) =>
  api.post<QaEntry>('/content-gen/qa', data);

export const updateQaEntry = (id: number, data: Partial<QaEntry>) =>
  api.put<QaEntry>(`/content-gen/qa/${id}`, data);

export const deleteQaEntry = (id: number) =>
  api.delete(`/content-gen/qa/${id}`);

export const publishQaEntry = (id: number) =>
  api.post(`/content-gen/qa/${id}/publish`);

export const generateQaFromArticle = (articleId: number) =>
  api.post<{ qa_created: number }>('/content-gen/qa/generate-from-article', { article_id: articleId });

export const generateQaFromPaa = (data: { topic: string; country: string; language?: string }) =>
  api.post<{ qa_created: number }>('/content-gen/qa/generate-from-paa', data);

export const bulkPublishQa = (ids: number[]) =>
  api.post('/content-gen/qa/bulk-publish', { qa_ids: ids });

// ============================================================
// KEYWORDS
// ============================================================

export const fetchKeywords = (params?: {
  type?: string;
  language?: string;
  country?: string;
  search?: string;
  page?: number;
}) =>
  api.get<PaginatedResponse<KeywordTracking>>('/content-gen/keywords', { params });

export const fetchKeywordGaps = (params?: { language?: string; country?: string }) =>
  api.get<KeywordGap[]>('/content-gen/keywords/gaps', { params });

export const fetchKeywordCannibalization = () =>
  api.get<KeywordCannibalization[]>('/content-gen/keywords/cannibalization');

export const fetchArticleKeywords = (articleId: number) =>
  api.get<ArticleKeyword[]>(`/content-gen/keywords/article/${articleId}`);

// ============================================================
// TRANSLATIONS
// ============================================================

export const fetchTranslationBatches = (params?: { status?: string; page?: number }) =>
  api.get<PaginatedResponse<TranslationBatch>>('/content-gen/translations', { params });

export const fetchTranslationOverview = () =>
  api.get<TranslationOverview[]>('/content-gen/translations/overview');

export const startTranslationBatch = (data: { target_language: string; content_type: 'article' | 'qa' | 'all' }) =>
  api.post<TranslationBatch>('/content-gen/translations/start', data);

export const fetchTranslationBatch = (id: number) =>
  api.get<TranslationBatch>(`/content-gen/translations/${id}`);

export const pauseTranslationBatch = (id: number) =>
  api.post<TranslationBatch>(`/content-gen/translations/${id}/pause`);

export const resumeTranslationBatch = (id: number) =>
  api.post<TranslationBatch>(`/content-gen/translations/${id}/resume`);

export const cancelTranslationBatch = (id: number) =>
  api.post<TranslationBatch>(`/content-gen/translations/${id}/cancel`);

// ============================================================
// SEO CHECKLIST
// ============================================================

export const fetchSeoChecklist = (articleId: number) =>
  api.get<SeoChecklist>(`/content-gen/seo/checklist/${articleId}`);

export const evaluateSeoChecklist = (articleId: number) =>
  api.post<SeoChecklist>(`/content-gen/seo/checklist/${articleId}/evaluate`);

export const fetchFailedChecks = (articleId: number) =>
  api.get<{ failed_checks: unknown[]; total_failed: number; overall_score: number }>(`/content-gen/seo/checklist/${articleId}/failed`);

// ============================================================
// QUESTION CLUSTERS
// ============================================================

export const fetchQuestionClusters = (params?: Record<string, unknown>) =>
  api.get<PaginatedResponse<QuestionCluster>>('/content-gen/question-clusters', { params });

export const fetchQuestionClusterStats = () =>
  api.get<QuestionClusterStats>('/content-gen/question-clusters/stats');

export const autoClusterQuestions = (data?: { country_slug?: string; category?: string }) =>
  api.post('/content-gen/question-clusters/auto-cluster', data);

export const fetchQuestionCluster = (id: number) =>
  api.get<QuestionCluster>(`/content-gen/question-clusters/${id}`);

export const generateQaFromQuestionCluster = (id: number) =>
  api.post(`/content-gen/question-clusters/${id}/generate-qa`);

export const generateArticleFromQuestionCluster = (id: number) =>
  api.post(`/content-gen/question-clusters/${id}/generate-article`);

export const generateBothFromQuestionCluster = (id: number) =>
  api.post(`/content-gen/question-clusters/${id}/generate-both`);

export const skipQuestionCluster = (id: number) =>
  api.post(`/content-gen/question-clusters/${id}/skip`);

export const deleteQuestionCluster = (id: number) =>
  api.delete(`/content-gen/question-clusters/${id}`);

// ============================================================
// AUTO PIPELINE
// ============================================================

export const runAutoPipeline = (options?: { country?: string; category?: string; max_articles?: number; min_quality_score?: number; include_qa?: boolean; articles_from_questions?: boolean }) =>
  api.post('/content-gen/generation/auto-pipeline', options);

export const fetchPipelineStatus = () =>
  api.get('/content-gen/generation/pipeline-status');

// ============================================================
// DAILY SCHEDULE
// ============================================================

import type {
  DailyContentSchedule,
  DailyContentLog,
  ScheduleStatus,
} from '../types/content';

export const fetchDailySchedule = () =>
  api.get<ScheduleStatus>('/content-gen/schedule');

export const updateDailySchedule = (data: Partial<DailyContentSchedule>) =>
  api.put('/content-gen/schedule', data);

export const fetchScheduleHistory = () =>
  api.get<DailyContentLog[]>('/content-gen/schedule/history');

export const runScheduleNow = () =>
  api.post('/content-gen/schedule/run-now');

export const addCustomTitles = (titles: string[]) =>
  api.post('/content-gen/schedule/custom-titles', { titles });

// ============================================================
// QUALITY & PLAGIARISM
// ============================================================

import type { PlagiarismResult, QualityAuditResult } from '../types/content';

export const checkArticlePlagiarism = (articleId: number) =>
  api.post<PlagiarismResult>(`/content-gen/quality/${articleId}/plagiarism`);

export const fetchArticleReadability = (articleId: number) =>
  api.get(`/content-gen/quality/${articleId}/readability`);

export const fetchArticleTone = (articleId: number) =>
  api.get(`/content-gen/quality/${articleId}/tone`);

export const fetchArticleBrandCheck = (articleId: number) =>
  api.get(`/content-gen/quality/${articleId}/brand`);

export const fetchArticleFactCheck = (articleId: number) =>
  api.get(`/content-gen/quality/${articleId}/fact-check`);

export const fetchArticleFullAudit = (articleId: number) =>
  api.get<QualityAuditResult>(`/content-gen/quality/${articleId}/full-audit`);

export const improveArticleQuality = (articleId: number) =>
  api.post(`/content-gen/quality/${articleId}/improve`);

export const fetchFlaggedArticles = (params?: { status?: string; min_similarity?: number }) =>
  api.get<PaginatedResponse<GeneratedArticle>>('/content-gen/articles', {
    params: { ...params, sort_by: 'quality_score', sort_dir: 'asc' },
  });

// ============================================================
// TAXONOMY DISTRIBUTION
// ============================================================

export const fetchTaxonomyDistribution = () =>
  api.get<TaxonomyDistribution[]>('/content-gen/taxonomy-distribution');

export const updateTaxonomyDistribution = (data: { total_articles_per_day: number; distribution: TaxonomyDistribution[] }) =>
  api.put('/content-gen/taxonomy-distribution', data);

// ============================================================
// PUBLICATION STATS
// ============================================================

export const fetchPublicationStats = () =>
  api.get<PublicationStats>('/content-gen/publication-stats');

export const updatePublicationRate = (data: { publish_per_day: number; start_hour: number; end_hour: number; irregular: boolean }) =>
  api.put('/content-gen/publication-rate', data);

// ============================================================
// QUALITY MONITORING
// ============================================================

export const fetchQualityMonitoring = (params?: { status?: string; language?: string; content_type?: string }) =>
  api.get<QualityMonitoringData>('/content-gen/quality-monitoring', { params });

export const rejectArticle = (id: number, reason?: string) =>
  api.post(`/content-gen/articles/${id}/reject`, { reason });

export const approveArticle = (id: number) =>
  api.post(`/content-gen/articles/${id}/approve`);


// ============================================================
// Q/R BLOG GENERATOR
// ============================================================

export interface QrBlogStats {
  available: number;
  writing: number;
  published: number;
  skipped: number;
  total: number;
  progress: QrBlogProgress | null;
}

export interface QrBlogProgress {
  status: 'idle' | 'running' | 'completed' | 'failed';
  total: number;
  completed: number;
  skipped: number;
  errors: number;
  current_title: string | null;
  started_at: string | null;
  finished_at: string | null;
  triggered_by?: string;
  log: Array<{ type: 'success' | 'skip' | 'error'; id: number; title: string; optimized_title?: string; reason?: string }>;
}

export interface QrSource {
  id: number;
  title: string;
  country: string | null;
  country_slug: string | null;
  language: string;
  views: number;
  replies: number;
  article_status: string;
  article_notes: string | null;
  url: string | null;
  created_at: string;
}

export interface QrSchedule {
  active: boolean;
  daily_limit: number;
  country: string;
  category: string;
  duration_type: 'unlimited' | 'days' | 'total';
  max_days: number | null;
  total_goal: number | null;
  start_date: string | null;
  total_generated: number;
  last_run_at: string | null;
  sources_available?: number;
}

export interface QrGeneratedArticle {
  id: number;
  title: string;
  slug: string;
  language_code: string;
  published_at: string | null;
  created_at: string;
  mc_uuid: string | null;
  category?: { name: string; slug: string };
}

// Stats & génération
export const fetchQrBlogStats = () =>
  api.get<QrBlogStats>('/content-gen/qr-blog/stats');

export const fetchQrBlogProgress = () =>
  api.get<QrBlogProgress>('/content-gen/qr-blog/progress');

export const launchQrBlogGeneration = (params: { limit?: number; country?: string; category?: string }) =>
  api.post('/content-gen/qr-blog/generate', params);

export const resetQrBlogWriting = () =>
  api.post('/content-gen/qr-blog/reset');

// Sources
export const fetchQrSources = (params: Record<string, unknown>) =>
  api.get('/content-gen/qr-blog/sources', { params });

export const addQrSource = (data: { title: string; country?: string; language?: string; notes?: string }) =>
  api.post('/content-gen/qr-blog/sources', data);

export const updateQrSource = (id: number, data: Partial<{ title: string; article_status: string; article_notes: string }>) =>
  api.put(`/content-gen/qr-blog/sources/${id}`, data);

export const deleteQrSource = (id: number) =>
  api.delete(`/content-gen/qr-blog/sources/${id}`);

// Programmation
export const fetchQrSchedule = () =>
  api.get<QrSchedule>('/content-gen/qr-blog/schedule');

export const saveQrSchedule = (data: QrSchedule) =>
  api.put('/content-gen/qr-blog/schedule', data);

// Contenus générés
export const fetchQrGenerated = (params: Record<string, unknown>) =>
  api.get('/content-gen/qr-blog/generated', { params });

// ─── FICHES PAYS ─────────────────────────────────────────────
export interface FichesStats {
  covered: number;
  total: number;
  progress: number;
}
export interface FicheArticle {
  id: number;
  status: string;
  title: string;
  country_code: string;
  country_name: string;
  lang_count: number;
  published_at: string | null;
  updated_at: string | null;
}
export interface FichesMissingCountry {
  code: string;
  name: string;
  flag: string;
}

export const fetchFichesStats = (type: string) =>
  api.get<FichesStats>(`/content-gen/fiches/${type}/stats`);

export const fetchFichesArticles = (type: string, page = 1) =>
  api.get<{ data: FicheArticle[]; current_page: number; last_page: number; total: number }>(
    `/content-gen/fiches/${type}/articles`, { params: { page } }
  );

export const fetchFichesMissing = (type: string) =>
  api.get<{ countries: FichesMissingCountry[] }>(`/content-gen/fiches/${type}/missing`);

export const launchFicheGeneration = (type: string, country: string, draft = false) =>
  api.post(`/content-gen/fiches/${type}/generate`, { country, draft });

export const fetchFichesProgress = (type: string) =>
  api.get(`/content-gen/fiches/${type}/progress`);

// ─── CONTENT TEMPLATES ENGINE ────────────────────────────────
export interface ContentTemplate {
  id: number;
  uuid: string;
  name: string;
  description: string | null;
  preset_type: string;
  content_type: string;
  title_template: string;
  variables: Array<{ name: string; type: string; required: boolean }>;
  expansion_mode: string;
  expansion_values: string[];
  language: string;
  tone: string;
  article_length: string;
  generation_instructions: string | null;
  generate_faq: boolean;
  faq_count: number;
  research_sources: boolean;
  auto_internal_links: boolean;
  auto_affiliate_links: boolean;
  auto_translate: boolean;
  image_source: string;
  total_items: number;
  generated_items: number;
  published_items: number;
  failed_items: number;
  is_active: boolean;
  items_count?: number;
  pending_count?: number;
  generated_count?: number;
  items?: ContentTemplateItem[];
  created_at: string;
  updated_at: string;
}

export interface ContentTemplateItem {
  id: number;
  template_id: number;
  expanded_title: string;
  variable_values: Record<string, string>;
  status: string;
  optimized_title: string | null;
  generated_article_id: number | null;
  error_message: string | null;
  generation_cost_cents: number;
  generated_at: string | null;
}

export const fetchTemplates = (params?: Record<string, unknown>) =>
  api.get('/content-gen/templates', { params });

export const fetchTemplate = (id: number) =>
  api.get<ContentTemplate>(`/content-gen/templates/${id}`);

export const createTemplate = (data: Partial<ContentTemplate>) =>
  api.post<ContentTemplate>('/content-gen/templates', data);

export const updateTemplate = (id: number, data: Partial<ContentTemplate>) =>
  api.put<ContentTemplate>(`/content-gen/templates/${id}`, data);

export const deleteTemplate = (id: number) =>
  api.delete(`/content-gen/templates/${id}`);

export const expandTemplate = (id: number) =>
  api.post(`/content-gen/templates/${id}/expand`);

export const addTemplateItems = (id: number, items: string[]) =>
  api.post(`/content-gen/templates/${id}/add-items`, { items });

export const generateTemplateItems = (id: number, limit?: number, itemIds?: number[]) =>
  api.post(`/content-gen/templates/${id}/generate`, { limit, item_ids: itemIds });

export const skipTemplateItem = (itemId: number) =>
  api.post(`/content-gen/templates/items/${itemId}/skip`);

export const resetTemplateItem = (itemId: number) =>
  api.post(`/content-gen/templates/items/${itemId}/reset`);
