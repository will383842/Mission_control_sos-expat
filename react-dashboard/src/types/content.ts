// ============================================================
// TYPES — Content Engine (synchronized with Laravel backend)
// ============================================================

// Enums
export type ContentStatus = 'draft' | 'generating' | 'review' | 'scheduled' | 'published' | 'archived';
export type ContentType = 'article' | 'guide' | 'news' | 'tutorial';
export type ImageSource = 'unsplash' | 'dalle' | 'upload' | 'external';
export type GenerationPhase =
  | 'validate' | 'research' | 'title' | 'excerpt' | 'content'
  | 'faq' | 'meta' | 'jsonld' | 'internal_links' | 'external_links'
  | 'affiliate_links' | 'images' | 'slugs' | 'quality' | 'translations';
export type CampaignType = 'country_coverage' | 'thematic' | 'pillar_cluster' | 'comparative_series' | 'custom';
export type CampaignStatus = 'draft' | 'running' | 'paused' | 'completed' | 'cancelled';
export type PublicationStatus = 'pending' | 'publishing' | 'published' | 'failed' | 'cancelled';
export type EndpointType = 'firestore' | 'wordpress' | 'webhook' | 'export' | 'blog';

// ============================================================
// ARTICLES
// ============================================================

export interface GeneratedArticle {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  language: string;
  country: string | null;
  content_type: ContentType;
  excerpt: string | null;
  content_html: string | null;
  content_text: string | null;
  featured_image_url: string | null;
  featured_image_alt: string | null;
  featured_image_attribution: string | null;
  meta_title: string | null;
  meta_description: string | null;
  canonical_url: string | null;
  json_ld: Record<string, unknown> | null;
  hreflang_map: Record<string, string> | null;
  keywords_primary: string | null;
  keywords_secondary: string[] | null;
  keyword_density: Record<string, number> | null;
  word_count: number;
  reading_time_minutes: number;
  seo_score: number;
  quality_score: number;
  readability_score: number | null;
  status: ContentStatus;
  generation_model: string | null;
  generation_cost_cents: number;
  generation_tokens_input: number;
  generation_tokens_output: number;
  generation_duration_seconds: number;
  source_article_id: number | null;
  parent_article_id: number | null;
  pillar_article_id: number | null;
  published_at: string | null;
  scheduled_at: string | null;
  created_by: number | null;
  created_at: string;
  updated_at: string;
  // Relations (when loaded)
  faqs?: ArticleFaq[];
  sources?: ArticleSource[];
  images?: ArticleImage[];
  versions?: ArticleVersion[];
  translations?: GeneratedArticle[];
  seo_analysis?: SeoAnalysis | null;
  generation_logs?: GenerationLog[];
  generation_preset_id: number | null;
  creator?: { id: number; name: string } | null;
}

export interface ArticleFaq {
  id: number;
  article_id: number;
  question: string;
  answer: string;
  sort_order: number;
}

export interface ArticleSource {
  id: number;
  article_id: number;
  url: string;
  title: string | null;
  excerpt: string | null;
  domain: string | null;
  trust_score: number;
}

export interface ArticleVersion {
  id: number;
  article_id: number;
  version_number: number;
  content_html: string;
  meta_title: string | null;
  meta_description: string | null;
  changes_summary: string | null;
  created_by: number | null;
  created_at: string;
}

export interface ArticleImage {
  id: number;
  article_id: number;
  url: string;
  alt_text: string | null;
  source: ImageSource;
  attribution: string | null;
  width: number | null;
  height: number | null;
  sort_order: number;
}

// ============================================================
// COMPARATIVES
// ============================================================

export interface Comparative {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  language: string;
  country: string | null;
  entities: ComparativeEntity[];
  comparison_data: Record<string, unknown> | null;
  content_html: string | null;
  excerpt: string | null;
  meta_title: string | null;
  meta_description: string | null;
  json_ld: Record<string, unknown> | null;
  hreflang_map: Record<string, string> | null;
  seo_score: number;
  quality_score: number;
  status: ContentStatus;
  generation_cost_cents: number;
  published_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface ComparativeEntity {
  name: string;
  description?: string;
  pros?: string[];
  cons?: string[];
  rating?: number;
}

// ============================================================
// LANDING PAGES
// ============================================================

export type AudienceType =
  // Audiences originales
  | 'clients' | 'lawyers' | 'helpers' | 'matching'
  // Nouveaux types 2026
  | 'category_pillar' | 'profile' | 'emergency' | 'nationality';

export type UserProfile =
  | 'digital_nomade' | 'retraite' | 'famille'
  | 'entrepreneur' | 'etudiant' | 'investisseur' | 'expatrie';

export type ProblemCategory =
  | 'sante' | 'immigration' | 'securite' | 'documents' | 'banque_argent'
  | 'travail' | 'logement' | 'famille' | 'voyage' | 'police_justice'
  | 'fiscalite' | 'assurance' | 'etudes' | 'transport' | 'geopolitique_crise'
  | 'entreprise_investissement' | 'langue_culture_orientation' | 'consommation_litiges'
  | 'ambassade_consulat' | 'profils_vulnerables' | 'douane_animaux_rarete' | 'humain_orientation';

export type LandingTemplateId =
  // clients
  | 'urgent' | 'seo' | 'trust'
  // lawyers
  | 'general' | 'freedom' | 'income' | 'premium'
  // helpers
  | 'recruitment' | 'opportunity' | 'reassurance'
  // matching
  | 'expert' | 'lawyer' | 'helper'
  // category_pillar
  | 'overview' | 'guide'
  // profile
  | 'profile_general' | 'profile_guide'
  // emergency
  | 'emergency'
  // nationality
  | 'nationality_general';

export interface LandingPage {
  id: number;
  uuid: string;
  parent_id: number | null;
  title: string;
  slug: string;
  language: string;
  country: string | null;
  // Landing Generator fields
  audience_type?: AudienceType | null;
  template_id?: LandingTemplateId | string | null;
  problem_id?: string | null;
  country_code?: string | null;
  generation_source?: 'ai_generated' | 'manual' | null;
  generation_params?: Record<string, unknown> | null;
  // Nouveaux types 2026
  category_slug?: ProblemCategory | string | null;  // pour category_pillar
  user_profile?: UserProfile | null;                // pour profile
  origin_nationality?: string | null;               // pour nationality (ISO 3166-1 alpha-2)
  // Content
  sections: LandingSection[];
  meta_title: string | null;
  meta_description: string | null;
  json_ld?: Record<string, unknown> | null;
  hreflang_map?: Record<string, string> | null;
  // Image Unsplash
  featured_image_url?: string | null;
  featured_image_alt?: string | null;
  featured_image_attribution?: string | null;
  photographer_name?: string | null;
  photographer_url?: string | null;
  // Keywords SEO (migration 000004)
  keywords_primary?: string | null;
  keywords_secondary?: string[] | null;
  // SEO complet
  seo_score: number;
  canonical_url?: string | null;
  og_locale?: string | null;
  og_type?: string | null;
  og_url?: string | null;
  og_site_name?: string | null;
  og_title?: string | null;
  og_description?: string | null;
  og_image?: string | null;
  twitter_card?: string | null;
  twitter_title?: string | null;
  twitter_description?: string | null;
  twitter_image?: string | null;
  robots?: string | null;
  content_language?: string | null;
  geo_region?: string | null;
  geo_placename?: string | null;
  geo_position?: string | null;
  icbm?: string | null;
  // Design & freshness (migration 000004)
  design_template?: string | null;
  date_published_at?: string | null;
  date_modified_at?: string | null;
  // Status & dates
  status: ContentStatus;
  external_url?: string | null;
  external_id?: string | null;
  published_at: string | null;
  last_reviewed_at?: string | null;
  created_at: string;
  updated_at: string;
  cta_links?: LandingCtaLink[];
}

export interface LandingSection {
  type: 'hero' | 'features' | 'testimonials' | 'cta' | 'faq' | 'content'
      | 'trust_signals' | 'guide_steps' | 'local_info' | 'why_us'
      | 'testimonial_proof' | 'earnings' | 'freedom' | 'process'
      | 'client_quality' | 'what_you_do' | 'community_proof' | 'no_pressure'
      | 'trust_signals' | 'lawyer_advantages' | 'helper_advantages';
  content: Record<string, unknown>;
}

export interface LandingCtaLink {
  id: number;
  landing_page_id: number;
  url: string;
  text: string;
  position: string;
  style: string;
  sort_order: number;
}

export interface LandingCampaign {
  id: number;
  audience_type: AudienceType;
  country_queue: string[];
  current_country: string | null;
  pages_per_country: number;
  selected_templates: string[];
  problem_filters: {
    categories?: string[];
    business_value?: string[];
    min_urgency?: number;
  } | null;
  daily_limit: number;
  status: 'idle' | 'running' | 'paused' | 'completed';
  total_generated: number;
  total_cost_cents: number;
  started_at: string | null;
  completed_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface LandingProblem {
  id: number;
  slug: string;
  title: string;
  category: string;
  subcategory: string | null;
  intent: string;
  urgency_score: number;
  business_value: 'high' | 'medium' | 'low';
  product_route: string;
  needs_lawyer: boolean;
  needs_helper: boolean;
  lp_angle: string | null;
  faq_seed: Record<string, unknown> | null;
  search_queries_seed: string[] | null;
  status: 'active' | 'inactive';
}

// ============================================================
// PRESS
// ============================================================

export interface PressRelease {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  language: string;
  content_html: string | null;
  excerpt: string | null;
  meta_title: string | null;
  meta_description: string | null;
  seo_score: number;
  status: ContentStatus;
  published_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface PressDossier {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  language: string;
  description: string | null;
  cover_image_url: string | null;
  status: ContentStatus;
  published_at: string | null;
  created_at: string;
  updated_at: string;
  items?: PressDossierItem[];
}

export interface PressDossierItem {
  id: number;
  dossier_id: number;
  itemable_type: string;
  itemable_id: number;
  sort_order: number;
  itemable?: GeneratedArticle | PressRelease;
}

// ============================================================
// SEO
// ============================================================

export interface SeoAnalysis {
  id: number;
  overall_score: number;
  title_score: number;
  meta_description_score: number;
  headings_score: number;
  content_score: number;
  images_score: number;
  internal_links_score: number;
  external_links_score: number;
  structured_data_score: number;
  hreflang_score: number;
  technical_score: number;
  issues: SeoIssue[];
  analyzed_at: string;
}

export interface SeoIssue {
  type: string;
  severity: 'error' | 'warning' | 'info';
  message: string;
  suggestion?: string;
}

export interface HreflangMatrixEntry {
  article_id: number;
  title: string;
  language: string;
  translations: Record<string, boolean>;
}

export interface InternalLinksGraph {
  nodes: { id: number; title: string; seo_score: number; language: string }[];
  edges: { source: number; target: number; anchor_text: string }[];
}

// ============================================================
// GENERATION
// ============================================================

export interface GenerationLog {
  id: number;
  loggable_type: string;
  loggable_id: number;
  phase: GenerationPhase;
  status: 'pending' | 'running' | 'completed' | 'failed' | 'skipped';
  message: string | null;
  tokens_used: number;
  cost_cents: number;
  duration_ms: number;
  metadata: Record<string, unknown> | null;
  created_at: string;
}

export interface GenerationPreset {
  id: number;
  name: string;
  description: string | null;
  config: Record<string, unknown>;
  content_type: string;
  is_default: boolean;
  created_at: string;
}

export interface PromptTemplate {
  id: number;
  name: string;
  description: string | null;
  content_type: string;
  phase: string;
  system_message: string;
  user_message_template: string;
  model: string;
  temperature: number;
  max_tokens: number;
  is_active: boolean;
  version: number;
  created_at: string;
}

// ============================================================
// CAMPAIGNS
// ============================================================

export interface ContentCampaign {
  id: number;
  name: string;
  description: string | null;
  campaign_type: CampaignType;
  config: CampaignConfig;
  status: CampaignStatus;
  total_items: number;
  completed_items: number;
  failed_items: number;
  total_cost_cents: number;
  started_at: string | null;
  completed_at: string | null;
  created_at: string;
  updated_at: string;
  items?: ContentCampaignItem[];
  progress_percent?: number;
}

export interface CampaignConfig {
  country?: string;
  themes?: string[];
  languages?: string[];
  articles_per_day?: number;
  preset_id?: number;
  [key: string]: unknown;
}

export interface ContentCampaignItem {
  id: number;
  campaign_id: number;
  title_hint: string;
  status: 'pending' | 'generating' | 'completed' | 'failed' | 'skipped';
  error_message: string | null;
  sort_order: number;
  scheduled_at: string | null;
  completed_at: string | null;
  itemable?: GeneratedArticle | Comparative;
}

// ============================================================
// PUBLISHING
// ============================================================

export interface PublishingEndpoint {
  id: number;
  name: string;
  type: EndpointType;
  config: Record<string, unknown>;
  is_active: boolean;
  is_default: boolean;
  created_at: string;
  schedule?: PublicationSchedule;
}

export interface PublicationSchedule {
  id: number;
  endpoint_id: number;
  max_per_day: number;
  max_per_hour: number;
  min_interval_minutes: number;
  active_hours_start: string;
  active_hours_end: string;
  active_days: string[];
  auto_pause_on_errors: number;
  is_active: boolean;
}

export interface PublicationQueueItem {
  id: number;
  publishable_type: string;
  publishable_id: number;
  endpoint_id: number;
  status: PublicationStatus;
  priority: 'high' | 'default' | 'low';
  scheduled_at: string | null;
  published_at: string | null;
  attempts: number;
  max_attempts: number;
  last_error: string | null;
  external_id: string | null;
  external_url: string | null;
  publishable?: GeneratedArticle | Comparative | LandingPage | PressRelease;
  endpoint?: PublishingEndpoint;
}

// ============================================================
// COSTS
// ============================================================

export interface CostOverview {
  today_cents: number;
  this_week_cents: number;
  this_month_cents: number;
  daily_budget_cents: number;
  monthly_budget_cents: number;
  is_over_daily: boolean;
  is_over_monthly: boolean;
}

export interface CostBreakdownEntry {
  service: string;
  model: string;
  operation: string;
  count: number;
  total_tokens: number;
  total_cost_cents: number;
}

export interface CostTrendEntry {
  date: string;
  total_cost_cents: number;
  by_service: Record<string, number>;
}

// ============================================================
// MEDIA
// ============================================================

export interface UnsplashImage {
  url: string;
  thumb_url: string;
  alt_text: string;
  attribution: string;
  width: number;
  height: number;
  download_url: string;
}

// ============================================================
// PARAMS (generation requests)
// ============================================================

export interface GenerateArticleParams {
  topic: string;
  language: string;
  country?: string;
  content_type?: ContentType;
  keywords?: string[];
  instructions?: string;
  tone?: 'professional' | 'casual' | 'expert' | 'friendly';
  length?: 'short' | 'medium' | 'long';
  generate_faq?: boolean;
  faq_count?: number;
  research_sources?: boolean;
  image_source?: 'unsplash' | 'dalle' | 'none';
  auto_internal_links?: boolean;
  auto_affiliate_links?: boolean;
  translation_languages?: string[];
  preset_id?: number;
}

export interface GenerateComparativeParams {
  title: string;
  entities: string[];
  language: string;
  country?: string;
  keywords?: string[];
}

// ============================================================
// PAGINATED RESPONSE
// ============================================================

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  next_cursor?: string | null;
  prev_cursor?: string | null;
}

// ============================================================
// DASHBOARD AGGREGATES
// ============================================================

export interface SeoDashboard {
  scores_by_language: { language: string; avg_score: number; count: number }[];
  score_ranges: { range: string; count: number }[];
  top_issues: { type: string; count: number; severity: string }[];
  orphaned_count: number;
}

export interface GenerationStats {
  total_all_time: number;
  total_this_month: number;
  total_this_week: number;
  avg_generation_seconds: number;
  avg_quality_score: number;
  avg_seo_score: number;
  by_status: Record<string, number>;
  by_language: Record<string, number>;
}

// ============================================================
// TOPIC CLUSTERS
// ============================================================

export type ClusterStatus = 'pending' | 'ready' | 'generating' | 'generated' | 'archived';

export interface TopicCluster {
  id: number;
  name: string;
  slug: string;
  country: string;
  category: string;
  language: string;
  description: string | null;
  source_articles_count: number;
  status: ClusterStatus;
  keywords_detected: string[] | null;
  generated_article_id: number | null;
  created_at: string;
  updated_at: string;
  source_articles?: TopicClusterArticle[];
  research_brief?: ResearchBrief | null;
  generated_article?: GeneratedArticle | null;
}

export interface TopicClusterArticle {
  id: number;
  cluster_id: number;
  source_article_id: number;
  relevance_score: number;
  is_primary: boolean;
  processing_status: 'pending' | 'extracted' | 'used';
  extracted_facts: Record<string, unknown> | null;
  source_article?: { id: number; title: string; url: string; word_count: number; category: string | null; source_id: number };
}

export interface ResearchBrief {
  id: number;
  cluster_id: number;
  perplexity_response: string | null;
  extracted_facts: Record<string, unknown>[] | null;
  recent_data: Record<string, unknown>[] | null;
  identified_gaps: string[] | null;
  paa_questions: string[] | null;
  suggested_keywords: { primary: string[]; secondary: string[]; long_tail: string[]; lsi: string[] } | null;
  suggested_structure: Record<string, unknown>[] | null;
  tokens_used: number;
  cost_cents: number;
  created_at: string;
}

// ============================================================
// Q&A
// ============================================================

export type QaSourceType = 'article_faq' | 'paa' | 'scraped' | 'manual' | 'ai_suggested';

export interface QaEntry {
  id: number;
  uuid: string;
  parent_article_id: number | null;
  cluster_id: number | null;
  question: string;
  answer_short: string;
  answer_detailed_html: string | null;
  language: string;
  country: string | null;
  category: string | null;
  slug: string;
  meta_title: string | null;
  meta_description: string | null;
  canonical_url: string | null;
  json_ld: Record<string, unknown> | null;
  hreflang_map: Record<string, string> | null;
  keywords_primary: string | null;
  keywords_secondary: string[] | null;
  seo_score: number;
  word_count: number;
  source_type: QaSourceType;
  status: ContentStatus;
  generation_cost_cents: number;
  parent_qa_id: number | null;
  related_qa_ids: number[] | null;
  published_at: string | null;
  created_at: string;
  updated_at: string;
  parent_article?: GeneratedArticle | null;
  translations?: QaEntry[];
}

// ============================================================
// KEYWORDS
// ============================================================

export type KeywordType = 'primary' | 'secondary' | 'long_tail' | 'lsi' | 'paa' | 'semantic';

export interface KeywordTracking {
  id: number;
  keyword: string;
  type: KeywordType;
  language: string;
  country: string | null;
  category: string | null;
  search_volume_estimate: number | null;
  difficulty_estimate: number | null;
  trend: 'rising' | 'stable' | 'declining' | null;
  articles_using_count: number;
  first_used_at: string | null;
}

export interface ArticleKeyword {
  id: number;
  article_id: number;
  keyword_id: number;
  usage_type: string;
  density_percent: number | null;
  occurrences: number;
  position_context: string | null;
  keyword?: KeywordTracking;
}

export interface KeywordGap {
  keyword: string;
  type: string;
  covered: boolean;
  suggested_priority: 'high' | 'medium' | 'low';
}

export interface KeywordCannibalization {
  keyword: string;
  articles: { id: number; title: string }[];
  severity: 'high' | 'medium' | 'low';
}

// ============================================================
// TRANSLATION BATCHES
// ============================================================

export type TranslationBatchStatus = 'pending' | 'running' | 'paused' | 'completed' | 'cancelled' | 'failed';

export interface TranslationBatch {
  id: number;
  target_language: string;
  content_type: 'article' | 'qa' | 'all';
  status: TranslationBatchStatus;
  total_items: number;
  completed_items: number;
  failed_items: number;
  skipped_items: number;
  total_cost_cents: number;
  current_item_id: number | null;
  started_at: string | null;
  paused_at: string | null;
  completed_at: string | null;
  created_at: string;
}

export interface TranslationOverview {
  language: string;
  total_fr: number;
  translated: number;
  percent: number;
}

// ============================================================
// SEO CHECKLIST
// ============================================================

export interface SeoChecklist {
  id: number;
  article_id: number;
  has_single_h1: boolean;
  h1_contains_keyword: boolean;
  title_tag_length: number | null;
  title_tag_contains_keyword: boolean;
  meta_desc_length: number | null;
  meta_desc_contains_cta: boolean;
  keyword_in_first_paragraph: boolean;
  keyword_density_ok: boolean;
  heading_hierarchy_valid: boolean;
  has_table_or_list: boolean;
  has_article_schema: boolean;
  has_faq_schema: boolean;
  has_breadcrumb_schema: boolean;
  has_speakable_schema: boolean;
  has_howto_schema: boolean;
  json_ld_valid: boolean;
  has_author_box: boolean;
  has_sources_cited: boolean;
  has_date_published: boolean;
  has_date_modified: boolean;
  has_official_links: boolean;
  internal_links_count: number;
  external_links_count: number;
  official_links_count: number;
  broken_links_count: number;
  has_definition_paragraph: boolean;
  has_numbered_steps: boolean;
  has_comparison_table: boolean;
  has_speakable_content: boolean;
  has_direct_answers: boolean;
  paa_questions_covered: number;
  all_images_have_alt: boolean;
  featured_image_has_keyword: boolean;
  images_count: number;
  hreflang_complete: boolean;
  translations_count: number;
  overall_checklist_score: number;
}

// ============================================================
// QUESTION CLUSTERS
// ============================================================

export type QuestionClusterStatus = 'pending' | 'ready' | 'generating_qa' | 'generating_article' | 'completed' | 'skipped';

export interface QuestionCluster {
  id: number;
  name: string;
  slug: string;
  country: string;
  country_slug: string | null;
  continent: string | null;
  category: string | null;
  language: string;
  total_questions: number;
  total_views: number;
  total_replies: number;
  popularity_score: number;
  status: QuestionClusterStatus;
  generated_article_id: number | null;
  generated_qa_count: number;
  created_at: string;
  updated_at: string;
  questions?: QuestionClusterItemWithQuestion[];
  generated_article?: GeneratedArticle | null;
}

export interface QuestionClusterItem {
  id: number;
  cluster_id: number;
  question_id: number;
  is_primary: boolean;
  similarity_score: number;
}

export interface QuestionClusterItemWithQuestion extends QuestionClusterItem {
  question: ContentQuestionSummary;
}

export interface ContentQuestionSummary {
  id: number;
  title: string;
  url: string;
  country: string;
  country_slug: string | null;
  replies: number;
  views: number;
  is_closed: boolean;
  language: string;
  article_status: string;
  qa_entry_id: number | null;
  generated_article_id: number | null;
}

export interface QuestionClusterStats {
  total_clusters: number;
  by_country: { country: string; country_slug: string; count: number; total_views: number }[];
  by_status: { status: string; count: number }[];
  by_category: { category: string; count: number }[];
  top_popular: QuestionCluster[];
}

// ============================================================
// PIPELINE STATUS
// ============================================================

export interface PipelineStatus {
  unprocessed_articles: number;
  unprocessed_questions: number;
  pending_clusters: number;
  pending_question_clusters: number;
  currently_generating: number;
  generated_today: number;
  total_generated: number;
  avg_quality_score: number;
  avg_seo_score: number;
  pipeline_ready: boolean;
}

// ============================================================
// DAILY CONTENT SCHEDULER
// ============================================================

export interface DailyContentSchedule {
  id: number;
  name: string;
  is_active: boolean;
  pillar_articles_per_day: number;
  normal_articles_per_day: number;
  qa_per_day: number;
  comparatives_per_day: number;
  custom_titles: string[] | null;
  publish_per_day: number;
  publish_start_hour: number;
  publish_end_hour: number;
  publish_irregular: boolean;
  target_country: string | null;
  target_category: string | null;
  min_quality_score: number;
  created_at: string;
}

export interface DailyContentLog {
  id: number;
  schedule_id: number;
  date: string;
  pillar_generated: number;
  normal_generated: number;
  qa_generated: number;
  comparatives_generated: number;
  custom_generated: number;
  published: number;
  total_cost_cents: number;
  errors: string[] | null;
  started_at: string | null;
  completed_at: string | null;
}

export interface ScheduleStatus {
  schedule: DailyContentSchedule;
  today: DailyContentLog | null;
  is_running: boolean;
}

// ============================================================
// QUALITY & PLAGIARISM
// ============================================================

export interface PlagiarismMatch {
  article_id: number;
  article_title: string;
  similarity: number;
  matching_phrases: string[];
}

export interface PlagiarismResult {
  is_original: boolean;
  similarity_percent: number;
  status: 'original' | 'similar' | 'plagiarized';
  matches: PlagiarismMatch[];
  total_shingles: number;
  unique_shingles: number;
}

export interface QualityAuditResult {
  overall_score: number;
  plagiarism: PlagiarismResult;
  readability: {
    flesch_score: number;
    grade_level: string;
    avg_sentence_length: number;
    complex_word_percentage: number;
  };
  tone: {
    formality: number;
    objectivity: number;
    detected_tone: string;
  };
  brand: {
    compliant: boolean;
    score: number;
    issues: string[];
  };
  seo: {
    score: number;
    issues: string[];
  };
}

// ============================================================
// TAXONOMY DISTRIBUTION & PUBLICATION STATS
// ============================================================

export interface TaxonomyDistribution {
  content_type: string;
  label: string;
  percentage: number;
  calculated_count?: number;
  is_active: boolean;
  is_visible_on_blog?: boolean;
}

export interface PublicationStats {
  unpublished_stock: number;
  by_status: Record<string, number>;
  by_content_type: Record<string, number>;
  publish_per_day: number;
  days_of_stock: number;
  published_today: number;
  published_this_week: number;
  published_this_month: number;
  generation_today: number;
  total_published: number;
}

export interface QualityMonitoringData {
  flagged_articles: GeneratedArticle[];
  stats: {
    total_checked: number;
    original: number;
    similar: number;
    plagiarized: number;
    avg_quality_score: number;
    avg_seo_score: number;
  };
}
