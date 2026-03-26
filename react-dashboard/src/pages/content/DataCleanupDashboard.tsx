import { useEffect, useState } from 'react';
import api from '../../api/client';

interface StatusCount { processing_status?: string; article_status?: string; count: number; avg_words?: number; total_views?: number; total_replies?: number }
interface SourceBreakdown { source_name: string; total: number; processed: number; duplicates: number; low_quality: number; with_country: number; without_country: number }
interface Opportunity { question_title: string; country: string; theme: string; views: number; replies: number; priority_score: number }
interface ThemeCount { theme: string; count: number; total_views: number }
interface CountryCount { country: string; count: number; total_views: number }
interface MonetizableTheme { country: string; theme: string; nb_existing_articles: number; qa_total_views: number; monetization_score: number }
interface AffiliateProgram { domain: string; nb_links: number; nb_articles: number }

interface CleanupData {
  articles_by_status: Record<string, StatusCount>;
  articles_by_source: SourceBreakdown[];
  question_stats: StatusCount[];
  top_opportunities: Opportunity[];
  opportunities_by_theme: ThemeCount[];
  opportunities_by_country: CountryCount[];
  monetizable_themes: MonetizableTheme[];
  affiliate_programs: AffiliateProgram[];
}

const THEME_LABELS: Record<string, string> = {
  visa: 'Visa & Immigration', emploi: 'Emploi & Travail', logement: 'Logement',
  sante: 'Sante', banque: 'Banque & Finances', education: 'Education',
  transport: 'Transport', telecom: 'Telecom & Internet', fiscalite: 'Fiscalite',
  retraite: 'Retraite', famille: 'Famille & Enfants', animaux: 'Animaux',
  cout_vie: 'Cout de la vie', autre: 'Autre',
};

const STATUS_COLORS: Record<string, string> = {
  processed: 'bg-emerald-500/20 text-emerald-400',
  duplicate: 'bg-amber-500/20 text-amber-400',
  low_quality: 'bg-red-500/20 text-red-400',
  new: 'bg-blue-500/20 text-blue-400',
  ready: 'bg-green-500/20 text-green-400',
};

function fmt(n: number): string {
  return n.toLocaleString('fr-FR');
}

type Tab = 'overview' | 'opportunities' | 'monetization' | 'affiliates';

export default function DataCleanupDashboard() {
  const [data, setData] = useState<CleanupData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [tab, setTab] = useState<Tab>('overview');

  useEffect(() => {
    api.get('/content/data-cleanup')
      .then(res => setData(res.data))
      .catch(() => setError('Erreur de chargement'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="p-8 text-gray-400 animate-pulse">Chargement du dashboard...</div>;
  if (error || !data) return <div className="p-8 text-red-400">{error}</div>;

  const totalArticles = data.articles_by_source.reduce((s, r) => s + r.total, 0);
  const totalProcessed = data.articles_by_source.reduce((s, r) => s + r.processed, 0);
  const totalDuplicates = data.articles_by_source.reduce((s, r) => s + r.duplicates, 0);
  const totalLowQuality = data.articles_by_source.reduce((s, r) => s + r.low_quality, 0);
  const totalWithCountry = data.articles_by_source.reduce((s, r) => s + r.with_country, 0);

  const questionCovered = data.question_stats.find(q => q.article_status === 'covered');
  const questionOpportunity = data.question_stats.find(q => q.article_status === 'opportunity');

  const tabs: { key: Tab; label: string }[] = [
    { key: 'overview', label: 'Vue d\'ensemble' },
    { key: 'opportunities', label: `Opportunites (${fmt(data.top_opportunities.length)})` },
    { key: 'monetization', label: `Monetisation (${data.monetizable_themes.length})` },
    { key: 'affiliates', label: `Programmes Affilies (${data.affiliate_programs.length})` },
  ];

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-white">Donnees Brutes</h1>
        <p className="text-emerald-400 text-sm mt-1">✓ Traitement effectue — les donnees nettoyees sont dans Sources Generation</p>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-gray-700 pb-0">
        {tabs.map(t => (
          <button key={t.key} onClick={() => setTab(t.key)}
            className={`px-4 py-2 text-sm font-medium rounded-t transition-colors ${tab === t.key ? 'bg-gray-700 text-white border-b-2 border-blue-500' : 'text-gray-400 hover:text-gray-200'}`}
          >{t.label}</button>
        ))}
      </div>

      {tab === 'overview' && (
        <div className="space-y-6">
          {/* KPIs */}
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <KPI label="Articles total" value={fmt(totalArticles)} />
            <KPI label="Traites" value={fmt(totalProcessed)} color="text-emerald-400" />
            <KPI label="Doublons" value={fmt(totalDuplicates)} color="text-amber-400" />
            <KPI label="Basse qualite" value={fmt(totalLowQuality)} color="text-red-400" />
            <KPI label="Avec pays" value={`${Math.round(totalWithCountry / totalArticles * 100)}%`} color="text-blue-400" />
            <KPI label="Liens affilies" value={fmt(data.affiliate_programs.reduce((s, p) => s + p.nb_links, 0))} color="text-purple-400" />
          </div>

          {/* Questions KPIs */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <KPI label="Questions forum" value={fmt((questionCovered?.count || 0) + (questionOpportunity?.count || 0))} />
            <KPI label="Couvertes" value={fmt(questionCovered?.count || 0)} color="text-emerald-400" />
            <KPI label="Opportunites" value={fmt(questionOpportunity?.count || 0)} color="text-amber-400" />
            <KPI label="Vues totales Q&A" value={fmt((questionCovered?.total_views || 0) + (questionOpportunity?.total_views || 0))} color="text-blue-400" />
          </div>

          {/* Articles by Source */}
          <div className="bg-gray-800 rounded-lg p-4">
            <h2 className="text-lg font-semibold text-white mb-3">Articles par source</h2>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-gray-400 border-b border-gray-700">
                    <th className="text-left py-2 px-3">Source</th>
                    <th className="text-right py-2 px-3">Total</th>
                    <th className="text-right py-2 px-3">Traites</th>
                    <th className="text-right py-2 px-3">Doublons</th>
                    <th className="text-right py-2 px-3">Low Q</th>
                    <th className="text-right py-2 px-3">Avec pays</th>
                    <th className="text-right py-2 px-3">Sans pays</th>
                  </tr>
                </thead>
                <tbody>
                  {data.articles_by_source.map(s => (
                    <tr key={s.source_name} className="border-b border-gray-700/50 hover:bg-gray-700/30">
                      <td className="py-2 px-3 text-white font-medium">{s.source_name}</td>
                      <td className="py-2 px-3 text-right">{fmt(s.total)}</td>
                      <td className="py-2 px-3 text-right text-emerald-400">{fmt(s.processed)}</td>
                      <td className="py-2 px-3 text-right text-amber-400">{s.duplicates > 0 ? fmt(s.duplicates) : '-'}</td>
                      <td className="py-2 px-3 text-right text-red-400">{s.low_quality > 0 ? fmt(s.low_quality) : '-'}</td>
                      <td className="py-2 px-3 text-right text-blue-400">{fmt(s.with_country)}</td>
                      <td className="py-2 px-3 text-right text-gray-500">{s.without_country > 0 ? fmt(s.without_country) : '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Articles by Status */}
          <div className="bg-gray-800 rounded-lg p-4">
            <h2 className="text-lg font-semibold text-white mb-3">Repartition par statut</h2>
            <div className="flex gap-3 flex-wrap">
              {Object.entries(data.articles_by_status).map(([status, info]) => (
                <span key={status} className={`px-3 py-1.5 rounded-full text-sm font-medium ${STATUS_COLORS[status] || 'bg-gray-600 text-gray-300'}`}>
                  {status}: {fmt(info.count)} {info.avg_words ? `(~${info.avg_words} mots)` : ''}
                </span>
              ))}
            </div>
          </div>
        </div>
      )}

      {tab === 'opportunities' && (
        <div className="space-y-6">
          {/* By Theme */}
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
            {data.opportunities_by_theme.map(t => (
              <div key={t.theme} className="bg-gray-800 rounded-lg p-3 text-center">
                <div className="text-xs text-gray-400 mb-1">{THEME_LABELS[t.theme] || t.theme}</div>
                <div className="text-lg font-bold text-white">{t.count}</div>
                <div className="text-xs text-gray-500">{fmt(t.total_views)} vues</div>
              </div>
            ))}
          </div>

          {/* By Country */}
          <div className="bg-gray-800 rounded-lg p-4">
            <h2 className="text-lg font-semibold text-white mb-3">Top pays (opportunites)</h2>
            <div className="flex gap-2 flex-wrap">
              {data.opportunities_by_country.slice(0, 20).map(c => (
                <span key={c.country} className="px-3 py-1 bg-gray-700 rounded-full text-sm text-gray-200">
                  {c.country} <span className="text-blue-400 font-medium">{c.count}</span>
                </span>
              ))}
            </div>
          </div>

          {/* Top Opportunities Table */}
          <div className="bg-gray-800 rounded-lg p-4">
            <h2 className="text-lg font-semibold text-white mb-3">Top 100 opportunites de contenu</h2>
            <div className="overflow-x-auto max-h-[600px] overflow-y-auto">
              <table className="w-full text-sm">
                <thead className="sticky top-0 bg-gray-800">
                  <tr className="text-gray-400 border-b border-gray-700">
                    <th className="text-left py-2 px-3">#</th>
                    <th className="text-left py-2 px-3">Question</th>
                    <th className="text-left py-2 px-3">Pays</th>
                    <th className="text-left py-2 px-3">Theme</th>
                    <th className="text-right py-2 px-3">Vues</th>
                    <th className="text-right py-2 px-3">Reponses</th>
                    <th className="text-right py-2 px-3">Score</th>
                  </tr>
                </thead>
                <tbody>
                  {data.top_opportunities.map((o, i) => (
                    <tr key={i} className="border-b border-gray-700/50 hover:bg-gray-700/30">
                      <td className="py-2 px-3 text-gray-500">{i + 1}</td>
                      <td className="py-2 px-3 text-white max-w-md truncate">{o.question_title}</td>
                      <td className="py-2 px-3 text-gray-300">{o.country}</td>
                      <td className="py-2 px-3"><span className="px-2 py-0.5 bg-blue-500/20 text-blue-400 rounded text-xs">{THEME_LABELS[o.theme] || o.theme}</span></td>
                      <td className="py-2 px-3 text-right">{fmt(o.views)}</td>
                      <td className="py-2 px-3 text-right">{fmt(o.replies)}</td>
                      <td className="py-2 px-3 text-right font-bold text-amber-400">{fmt(o.priority_score)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {tab === 'monetization' && (
        <div className="space-y-6">
          <div className="bg-gray-800 rounded-lg p-4">
            <h2 className="text-lg font-semibold text-white mb-3">Top 50 pays monetisables (assurance sante expat)</h2>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-gray-400 border-b border-gray-700">
                    <th className="text-left py-2 px-3">#</th>
                    <th className="text-left py-2 px-3">Pays</th>
                    <th className="text-right py-2 px-3">Articles sante</th>
                    <th className="text-right py-2 px-3">Vues Q&A</th>
                    <th className="text-right py-2 px-3">Score</th>
                  </tr>
                </thead>
                <tbody>
                  {data.monetizable_themes.map((t, i) => (
                    <tr key={i} className="border-b border-gray-700/50 hover:bg-gray-700/30">
                      <td className="py-2 px-3 text-gray-500">{i + 1}</td>
                      <td className="py-2 px-3 text-white font-medium">{t.country}</td>
                      <td className="py-2 px-3 text-right">{t.nb_existing_articles}</td>
                      <td className="py-2 px-3 text-right text-blue-400">{fmt(t.qa_total_views)}</td>
                      <td className="py-2 px-3 text-right font-bold text-purple-400">{fmt(Math.round(t.monetization_score))}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {tab === 'affiliates' && (
        <div className="space-y-6">
          <div className="bg-gray-800 rounded-lg p-4">
            <h2 className="text-lg font-semibold text-white mb-3">Vrais programmes d'affiliation detectes</h2>
            <p className="text-gray-400 text-sm mb-4">Apres nettoyage des faux positifs (UTM/analytics). Seuls les vrais liens affilies restent.</p>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-gray-400 border-b border-gray-700">
                    <th className="text-left py-2 px-3">Domaine</th>
                    <th className="text-right py-2 px-3">Liens</th>
                    <th className="text-right py-2 px-3">Articles</th>
                  </tr>
                </thead>
                <tbody>
                  {data.affiliate_programs.map(p => (
                    <tr key={p.domain} className="border-b border-gray-700/50 hover:bg-gray-700/30">
                      <td className="py-2 px-3 text-white font-medium">{p.domain}</td>
                      <td className="py-2 px-3 text-right text-purple-400 font-bold">{p.nb_links}</td>
                      <td className="py-2 px-3 text-right">{p.nb_articles}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function KPI({ label, value, color = 'text-white' }: { label: string; value: string; color?: string }) {
  return (
    <div className="bg-gray-800 rounded-lg p-4">
      <div className="text-xs text-gray-400 mb-1">{label}</div>
      <div className={`text-xl font-bold ${color}`}>{value}</div>
    </div>
  );
}
