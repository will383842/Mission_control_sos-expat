import { useState, useEffect } from 'react';
import api from '../api/client';
import type { StatsData } from '../types/influenceur';

export function useStats() {
  const [stats, setStats] = useState<StatsData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get<StatsData>('/stats')
      .then(({ data }) => setStats(data))
      .finally(() => setLoading(false));
  }, []);

  return { stats, loading };
}
