import { useState, useCallback } from 'react';
import api from '../api/client';
import type { EmailTemplate, OutreachMessage, ContactType } from '../types/influenceur';

export function useTemplates() {
  const [templates, setTemplates] = useState<EmailTemplate[]>([]);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async (contactType?: ContactType, language?: string) => {
    setLoading(true);
    try {
      const params: Record<string, string> = {};
      if (contactType) params.contact_type = contactType;
      if (language) params.language = language;
      const { data } = await api.get<EmailTemplate[]>('/templates', { params });
      setTemplates(data);
    } catch { /* ignore */ }
    finally { setLoading(false); }
  }, []);

  const create = useCallback(async (payload: Partial<EmailTemplate>) => {
    const { data } = await api.post<EmailTemplate>('/templates', payload);
    setTemplates(prev => [...prev, data]);
    return data;
  }, []);

  const update = useCallback(async (id: number, payload: Partial<EmailTemplate>) => {
    const { data } = await api.put<EmailTemplate>(`/templates/${id}`, payload);
    setTemplates(prev => prev.map(t => t.id === id ? data : t));
    return data;
  }, []);

  const remove = useCallback(async (id: number) => {
    await api.delete(`/templates/${id}`);
    setTemplates(prev => prev.filter(t => t.id !== id));
  }, []);

  const generateForContact = useCallback(async (influenceurId: number, step = 1): Promise<OutreachMessage | null> => {
    try {
      const { data } = await api.get<OutreachMessage>(`/contacts/${influenceurId}/outreach`, { params: { step } });
      return data;
    } catch { return null; }
  }, []);

  const generateBatch = useCallback(async (ids: number[], step = 1): Promise<OutreachMessage[]> => {
    try {
      const { data } = await api.post<OutreachMessage[]>('/templates/generate-batch', { influenceur_ids: ids, step });
      return data;
    } catch { return []; }
  }, []);

  return { templates, loading, load, create, update, remove, generateForContact, generateBatch };
}
