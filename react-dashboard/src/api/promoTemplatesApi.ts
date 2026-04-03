import api from './client';

export type PromoTemplateType = 'utm_campaign' | 'promo_text';
export type PromoTemplateRole = 'all' | 'influencer' | 'blogger';

export interface PromoTemplate {
  id: number;
  slug: string;
  name: string;
  type: PromoTemplateType;
  role: PromoTemplateRole;
  content: string;
  language: string;
  is_active: boolean;
  sort_order: number;
  created_at: string;
  updated_at: string;
}

export interface PaginatedPromoTemplates {
  data: PromoTemplate[];
  total: number;
  current_page: number;
  last_page: number;
  per_page: number;
}

export interface PromoTemplateFormData {
  name: string;
  type: PromoTemplateType;
  role: PromoTemplateRole;
  content: string;
  language: string;
  is_active: boolean;
  sort_order: number;
}

export const fetchPromoTemplates = (params?: {
  type?: PromoTemplateType;
  role?: PromoTemplateRole;
  language?: string;
  active?: boolean;
}) => api.get<PaginatedPromoTemplates>('/promo-templates', { params }).then(r => r.data);

export const createPromoTemplate = (data: PromoTemplateFormData) =>
  api.post<PromoTemplate>('/promo-templates', data).then(r => r.data);

export const updatePromoTemplate = (id: number, data: Partial<PromoTemplateFormData>) =>
  api.put<PromoTemplate>(`/promo-templates/${id}`, data).then(r => r.data);

export const deletePromoTemplate = (id: number) =>
  api.delete(`/promo-templates/${id}`);

export const reorderPromoTemplates = (order: number[]) =>
  api.post('/promo-templates/reorder', { order }).then(r => r.data);
