import { useEffect, useState } from 'react';
import api from '../../api/client';

interface AffiliateDomain {
  domain: string;
  total_mentions: number;
  liens_uniques: number;
  exemple_url: string;
  exemple_anchor: string;
}

export default function AffiliateLinks() {
  const [domains, setDomains] = useState<AffiliateDomain[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api.get('/content/affiliate-domains')
      .then(res => setDomains(res.data))
      .catch(() => setError('Erreur de chargement'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64" role="status">
        <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  const totalMentions = domains.reduce((s, d) => s + d.total_mentions, 0);

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      <div>
        <h1 className="font-title text-2xl font-bold text-white">Liens Affilies</h1>
        <p className="text-muted text-sm mt-1">
          {domains.length} sites avec programme d'affiliation detecte &middot; {totalMentions.toLocaleString()} mentions totales
        </p>
        <p className="text-muted text-xs mt-0.5">
          Ce sont les sites pour lesquels tu peux demander ton propre lien affilie. Les URLs sont nettoyees (sans le code d'expat.com).
        </p>
      </div>

      {error && (
        <div className="bg-red-900/20 border border-red-500/30 text-red-400 p-3 rounded-xl text-sm">{error}</div>
      )}

      <div className="bg-surface border border-border rounded-xl overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border text-left text-muted">
              <th className="px-4 py-3 font-medium">Site</th>
              <th className="px-4 py-3 font-medium">Exemple d'URL (propre, sans code affilie)</th>
              <th className="px-4 py-3 font-medium">Contexte</th>
              <th className="px-4 py-3 font-medium text-right">Mentions</th>
              <th className="px-4 py-3 font-medium text-right">Liens uniques</th>
            </tr>
          </thead>
          <tbody>
            {domains.map((d) => (
              <tr key={d.domain} className="border-b border-border/50 hover:bg-surface2 transition-colors">
                <td className="px-4 py-3">
                  <a href={`https://${d.domain}`} target="_blank" rel="noopener noreferrer"
                    className="text-violet-light hover:underline font-medium">
                    {d.domain}
                  </a>
                </td>
                <td className="px-4 py-3 max-w-md">
                  <a href={d.exemple_url} target="_blank" rel="noopener noreferrer"
                    className="text-cyan text-xs hover:underline truncate block">
                    {d.exemple_url.length > 80 ? d.exemple_url.slice(0, 80) + '...' : d.exemple_url}
                  </a>
                </td>
                <td className="px-4 py-3 text-gray-400 text-xs max-w-xs truncate">
                  {d.exemple_anchor || '-'}
                </td>
                <td className="px-4 py-3 text-right text-white font-bold">{d.total_mentions}</td>
                <td className="px-4 py-3 text-right text-muted">{d.liens_uniques}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
