import api from './client';

export type ToolCategory = 'calculate' | 'compare' | 'generate' | 'emergency';

export interface VisitorTool {
  id: string;
  slug_key: string;
  category: ToolCategory;
  icon: string;
  sort_order: number;
  is_active: boolean;
  is_ai_powered: boolean;
  leads_count: number;
  title_fr: string | null;
  title_en: string | null;
  deleted_at: string | null;
}

export interface ToolsStats {
  total: number;
  active: number;
  total_leads: number;
  today_leads: number;
  week_leads: number;
}

export interface ToolsResponse {
  data: VisitorTool[];
  stats: ToolsStats;
}

export interface ToolLead {
  id: string;
  tool_id: string;
  tool: { id: string; slug_key: string } | null;
  email: string;
  language_code: string | null;
  preferred_language: string | null;
  country_code: string | null;
  cgu_accepted: boolean;
  synced_at: string | null;
  created_at: string;
}

export interface PaginatedLeads {
  data: ToolLead[];
  total: number;
  current_page: number;
  last_page: number;
  per_page: number;
}

export interface LeadFilters {
  tool_id?: string;
  language?: string;
  search?: string;
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

export async function fetchVisitorTools(): Promise<ToolsResponse> {
  const res = await api.get<ToolsResponse>('/blog/tools');
  return res.data;
}

export async function toggleVisitorTool(id: string): Promise<{ id: string; is_active: boolean }> {
  const res = await api.post(`/blog/tools/${id}/toggle`);
  return res.data;
}

export async function fetchToolLeads(filters: LeadFilters = {}): Promise<PaginatedLeads> {
  const res = await api.get<PaginatedLeads>('/blog/tools/leads', { params: filters });
  return res.data;
}
