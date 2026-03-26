import { useEffect, useState } from 'react';
import api from '../api/client';

interface ContentSourceNav {
  id: number;
  name: string;
  slug: string;
  status: string;
  total_countries: number;
  total_articles: number;
}

export function useContentSources() {
  const [sources, setSources] = useState<ContentSourceNav[]>([]);

  useEffect(() => {
    api.get('/content/sources')
      .then(res => setSources(res.data))
      .catch(() => {});
  }, []);

  return sources;
}
