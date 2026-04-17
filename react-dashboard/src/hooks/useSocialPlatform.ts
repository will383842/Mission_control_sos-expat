import { useQuery } from '@tanstack/react-query';
import api from '../api/client';

/**
 * Supported social platforms (mirror of backend config/social.php).
 */
export type SocialPlatform = 'linkedin' | 'facebook' | 'threads' | 'instagram';

export interface SocialTokenStatus {
  connected: boolean;
  name: string | null;
  expires_in_days: number | null;
  has_refresh_token: boolean;
  refresh_expires_in_days: number | null;
}

export interface SocialStats {
  platform: SocialPlatform;
  posts_this_week: number;
  posts_scheduled: number;
  posts_published: number;
  posts_generating: number;
  total_reach: number;
  avg_engagement_rate: number;
  top_performing_day: string;
  available_articles: number;
  available_faqs: number;
  available_sondages: number;
  upcoming_posts: Array<{
    id: number;
    day_type: string;
    lang: string;
    account: string | null;
    hook_preview: string;
    scheduled_at: string | null;
    source_type: string;
    status: string;
    has_image: boolean;
  }>;
  connected: boolean;
  token_status: Record<string, SocialTokenStatus>;
}

export interface SocialQueueItem {
  id: number;
  platform: SocialPlatform;
  source_type: string;
  day_type: string;
  lang: string;
  account_type: string | null;
  hook: string;
  body: string;
  hashtags: string[] | null;
  status: string;
  scheduled_at: string | null;
  published_at: string | null;
  platform_post_id: string | null;
  reach: number;
  likes: number;
  comments: number;
  shares: number;
  engagement_rate: number;
}

export interface SocialQueuePage {
  data: SocialQueueItem[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

const base = (platform: SocialPlatform) => `/content-gen/social/${platform}`;

/**
 * Dashboard stats for a given platform (called every 30 s).
 */
export function useSocialStats(platform: SocialPlatform) {
  return useQuery<SocialStats>({
    queryKey: ['social-stats', platform],
    queryFn: () => api.get(base(platform) + '/stats').then(r => r.data),
    refetchInterval: 30_000,
  });
}

/**
 * Paginated queue of posts for a given platform (+ optional status filter).
 */
export function useSocialQueue(platform: SocialPlatform, status: string = 'all', perPage = 25) {
  return useQuery<SocialQueuePage>({
    queryKey: ['social-queue', platform, status, perPage],
    queryFn: () =>
      api
        .get(base(platform) + '/queue', { params: { status, per_page: perPage } })
        .then(r => r.data),
  });
}

/**
 * OAuth connection status for a given platform.
 */
export function useSocialOAuthStatus(platform: SocialPlatform) {
  return useQuery<{ platform: SocialPlatform; tokens: Record<string, SocialTokenStatus> }>({
    queryKey: ['social-oauth', platform],
    queryFn: () => api.get(base(platform) + '/oauth/status').then(r => r.data),
    refetchInterval: 120_000,
  });
}

/**
 * Absolute URL to trigger the OAuth authorize flow (redirect the browser here).
 * This hits the public callback endpoint, not the authenticated API prefix.
 */
export function socialOAuthAuthorizeUrl(platform: SocialPlatform, accountType?: string): string {
  const baseUrl = (api.defaults.baseURL ?? '').replace(/\/api\/?$/, '');
  const qs = accountType ? `?account_type=${encodeURIComponent(accountType)}` : '';
  return `${baseUrl}/api/social/${platform}/oauth/authorize${qs}`;
}
