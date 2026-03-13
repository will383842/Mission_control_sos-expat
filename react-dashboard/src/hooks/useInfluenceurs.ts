import { useState, useCallback } from 'react';
import api from '../api/client';
import type { Influenceur, InfluenceurFilters, PaginatedInfluenceurs } from '../types/influenceur';

export function useInfluenceurs() {
  const [influenceurs, setInfluenceurs] = useState<Influenceur[]>([]);
  const [loading, setLoading] = useState(false);
  const [hasMore, setHasMore] = useState(false);
  const [nextCursor, setNextCursor] = useState<string | null>(null);
  const [filters, setFilters] = useState<InfluenceurFilters>({});

  const fetchPage = useCallback(async (cursor: string | null, currentFilters: InfluenceurFilters, reset: boolean) => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = { per_page: 30, ...currentFilters };
      if (cursor) params.cursor = cursor;

      const { data } = await api.get<PaginatedInfluenceurs>('/influenceurs', { params });
      setInfluenceurs(prev => reset ? data.data : [...prev, ...data.data]);
      setNextCursor(data.next_cursor);
      setHasMore(data.has_more);
    } finally {
      setLoading(false);
    }
  }, []);

  const load = useCallback((newFilters?: InfluenceurFilters) => {
    const f = newFilters ?? filters;
    if (newFilters) setFilters(newFilters);
    setInfluenceurs([]);
    setNextCursor(null);
    fetchPage(null, f, true);
  }, [filters, fetchPage]);

  const loadMore = useCallback(() => {
    if (hasMore && !loading && nextCursor) {
      fetchPage(nextCursor, filters, false);
    }
  }, [hasMore, loading, nextCursor, filters, fetchPage]);

  const createInfluenceur = useCallback(async (payload: Partial<Influenceur>) => {
    const { data } = await api.post<Influenceur>('/influenceurs', payload);
    setInfluenceurs(prev => [data, ...prev]);
    return data;
  }, []);

  const updateInfluenceur = useCallback(async (id: number, payload: Partial<Influenceur>) => {
    const { data } = await api.put<Influenceur>(`/influenceurs/${id}`, payload);
    setInfluenceurs(prev => prev.map(i => i.id === id ? data : i));
    return data;
  }, []);

  const deleteInfluenceur = useCallback(async (id: number) => {
    await api.delete(`/influenceurs/${id}`);
    setInfluenceurs(prev => prev.filter(i => i.id !== id));
  }, []);

  return {
    influenceurs, loading, hasMore, filters,
    load, loadMore, setFilters: load,
    createInfluenceur, updateInfluenceur, deleteInfluenceur,
  };
}
