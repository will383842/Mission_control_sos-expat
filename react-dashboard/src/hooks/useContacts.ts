import { useState, useCallback } from 'react';
import api from '../api/client';
import type { Influenceur, InfluenceurFilters, PaginatedInfluenceurs } from '../types/influenceur';

export function useContacts() {
  const [contacts, setContacts] = useState<Influenceur[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [nextCursor, setNextCursor] = useState<string | null>(null);
  const [filters, setFilters] = useState<InfluenceurFilters>({});

  const fetchPage = useCallback(async (cursor: string | null, currentFilters: InfluenceurFilters, reset: boolean) => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, unknown> = { per_page: 30, ...currentFilters };
      if (cursor) params.cursor = cursor;

      const { data } = await api.get<PaginatedInfluenceurs>('/contacts', { params });
      setContacts(prev => reset ? data.data : [...prev, ...data.data]);
      setNextCursor(data.next_cursor);
      setHasMore(data.has_more);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Erreur lors du chargement des contacts';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  const load = useCallback((newFilters?: InfluenceurFilters) => {
    const f = newFilters ?? filters;
    if (newFilters) setFilters(newFilters);
    setContacts([]);
    setNextCursor(null);
    fetchPage(null, f, true);
  }, [filters, fetchPage]);

  const loadMore = useCallback(() => {
    if (hasMore && !loading && nextCursor) {
      fetchPage(nextCursor, filters, false);
    }
  }, [hasMore, loading, nextCursor, filters, fetchPage]);

  const createContact = useCallback(async (payload: Partial<Influenceur>) => {
    const { data } = await api.post<Influenceur>('/contacts', payload);
    setContacts(prev => [data, ...prev]);
    return data;
  }, []);

  const updateContact = useCallback(async (id: number, payload: Partial<Influenceur>) => {
    const { data } = await api.put<Influenceur>(`/contacts/${id}`, payload);
    setContacts(prev => prev.map(c => c.id === id ? data : c));
    return data;
  }, []);

  const deleteContact = useCallback(async (id: number) => {
    await api.delete(`/contacts/${id}`);
    setContacts(prev => prev.filter(c => c.id !== id));
  }, []);

  return {
    contacts, loading, error, hasMore, filters,
    load, loadMore,
    createContact, updateContact, deleteContact,
  };
}

// Backward compat alias
export function useInfluenceurs() {
  const hook = useContacts();
  return {
    influenceurs: hook.contacts,
    loading: hook.loading,
    error: hook.error,
    hasMore: hook.hasMore,
    filters: hook.filters,
    load: hook.load,
    loadMore: hook.loadMore,
    createInfluenceur: hook.createContact,
    updateInfluenceur: hook.updateContact,
    deleteInfluenceur: hook.deleteContact,
  };
}
