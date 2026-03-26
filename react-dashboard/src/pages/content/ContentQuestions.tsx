import { useEffect, useState, useCallback, useRef } from 'react';
import api from '../../api/client';

interface Question {
  id: number;
  title: string;
  url: string;
  country: string | null;
  country_slug: string | null;
  continent: string | null;
  views: number;
  replies: number;
  is_sticky: boolean;
  article_status: string;
  article_notes: string | null;
  source?: { id: number; name: string; slug: string };
}

interface Stats {
  total: number;
  by_country: { country: string; country_slug: string; count: number; total_views: number }[];
  by_status: { article_status: string; count: number }[];
  top_viewed: { id: number; title: string; country: string; views: number; replies: number; article_status: string }[];
}

const STATUS_LABELS: Record<string, { label: string; color: string }> = {
  new: { label: 'Nouveau', color: 'bg-gray-700 text-gray-300' },
  planned: { label: 'Planifie', color: 'bg-violet/20 text-violet-light' },
  writing: { label: 'En cours', color: 'bg-amber/20 text-amber' },
  published: { label: 'Publie', color: 'bg-green-900/30 text-green-400' },
  skipped: { label: 'Ignore', color: 'bg-red-900/20 text-red-400' },
};

export default function ContentQuestions() {
  const [questions, setQuestions] = useState<Question[]>([]);
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [filterCountry, setFilterCountry] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [minViews, setMinViews] = useState('');
  const [tab, setTab] = useState<'list' | 'top' | 'stats'>('list');

  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    const timer = setTimeout(() => { setSearch(searchInput); setPage(1); }, 400);
    return () => clearTimeout(timer);
  }, [searchInput]);

  const fetchQuestions = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    setLoading(true);
    try {
      const params: Record<string, string> = { page: String(page), per_page: '50' };
      if (search) params.search = search;
      if (filterCountry) params.country = filterCountry;
      if (filterStatus) params.status = filterStatus;
      if (minViews) params.min_views = minViews;
      const res = await api.get('/questions', { params, signal: controller.signal });
      if (!controller.signal.aborted) {
        setQuestions(res.data.data);
        setLastPage(res.data.last_page);
        setTotal(res.data.total);
      }
    } catch (err: unknown) {
      if (err instanceof Error && err.name === 'CanceledError') return;
    } finally {
      if (!controller.signal.aborted) setLoading(false);
    }
  }, [page, search, filterCountry, filterStatus, minViews]);

  useEffect(() => { fetchQuestions(); }, [fetchQuestions]);

  useEffect(() => {
    api.get('/questions/stats').then(res => setStats(res.data)).catch(() => {});
  }, []);

  const updateStatus = async (id: number, status: string) => {
    try {
      await api.put(`/questions/${id}/status`, { article_status: status });
      setQuestions(prev => prev.map(q => q.id === id ? { ...q, article_status: status } : q));
    } catch { /* */ }
  };

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      <div>
        <h1 className="font-title text-2xl font-bold text-white">Q&A Forum</h1>
        <p className="text-muted text-sm mt-1">
          Questions des expats scrapees depuis les forums — {total.toLocaleString()} questions
        </p>
        <p className="text-muted text-xs mt-0.5">
          Triees par vues pour identifier les sujets les plus demandes. Marque "Planifie" pour creer un article dessus.
        </p>
      </div>

      {/* KPIs */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
          <div className="bg-surface border border-border rounded-xl p-3 text-center">
            <div className="text-lg font-bold text-white">{stats.total.toLocaleString()}</div>
            <div className="text-xs text-muted">Questions</div>
          </div>
          {stats.by_status.map((s) => (
            <div key={s.article_status} className="bg-surface border border-border rounded-xl p-3 text-center">
              <div className="text-lg font-bold text-white">{s.count}</div>
              <div className="text-xs text-muted">{STATUS_LABELS[s.article_status]?.label || s.article_status}</div>
            </div>
          ))}
        </div>
      )}

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        {([
          { key: 'list' as const, label: 'Toutes les questions' },
          { key: 'top' as const, label: 'Top vues' },
          { key: 'stats' as const, label: 'Par pays' },
        ]).map((t) => (
          <button key={t.key} onClick={() => setTab(t.key)}
            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
              tab === t.key ? 'border-violet text-white' : 'border-transparent text-muted hover:text-white'
            }`}>
            {t.label}
          </button>
        ))}
      </div>

      {/* TAB: List */}
      {tab === 'list' && (
        <>
          <div className="flex flex-wrap gap-3 items-center">
            <input type="text" value={searchInput} onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Rechercher une question..."
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-64" />
            <select value={filterCountry} onChange={(e) => { setFilterCountry(e.target.value); setPage(1); }}
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="">Tous pays</option>
              {stats?.by_country.slice(0, 30).map((c) => (
                <option key={c.country_slug} value={c.country_slug}>{c.country} ({c.count})</option>
              ))}
            </select>
            <select value={filterStatus} onChange={(e) => { setFilterStatus(e.target.value); setPage(1); }}
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
              <option value="">Tous statuts</option>
              {Object.entries(STATUS_LABELS).map(([k, v]) => (
                <option key={k} value={k}>{v.label}</option>
              ))}
            </select>
            <input type="number" value={minViews} onChange={(e) => { setMinViews(e.target.value); setPage(1); }}
              placeholder="Vues min."
              className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-28" />
          </div>

          <div className="bg-surface border border-border rounded-xl overflow-x-auto">
            {loading ? (
              <div className="flex items-center justify-center h-32" role="status">
                <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
              </div>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left text-muted">
                    <th className="px-4 py-3 font-medium">Question</th>
                    <th className="px-4 py-3 font-medium">Pays</th>
                    <th className="px-4 py-3 font-medium text-right">Vues</th>
                    <th className="px-4 py-3 font-medium text-right">Rep.</th>
                    <th className="px-4 py-3 font-medium">Statut article</th>
                    <th className="px-4 py-3 font-medium">Source</th>
                  </tr>
                </thead>
                <tbody>
                  {questions.length === 0 ? (
                    <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">Aucune question</td></tr>
                  ) : questions.map((q) => (
                    <tr key={q.id} className="border-b border-border/50 hover:bg-surface2 transition-colors">
                      <td className="px-4 py-2 max-w-md">
                        <div className="text-white text-sm">{q.title}</div>
                      </td>
                      <td className="px-4 py-2 text-gray-400 text-xs">{q.country || '-'}</td>
                      <td className="px-4 py-2 text-right text-white font-bold">{q.views.toLocaleString()}</td>
                      <td className="px-4 py-2 text-right text-muted">{q.replies}</td>
                      <td className="px-4 py-2">
                        <select
                          value={q.article_status}
                          onChange={(e) => updateStatus(q.id, e.target.value)}
                          className={`px-2 py-0.5 rounded text-xs font-medium border-0 cursor-pointer ${STATUS_LABELS[q.article_status]?.color || 'bg-gray-700 text-gray-300'}`}
                        >
                          {Object.entries(STATUS_LABELS).map(([k, v]) => (
                            <option key={k} value={k}>{v.label}</option>
                          ))}
                        </select>
                      </td>
                      <td className="px-4 py-2 text-muted text-xs">{q.source?.name || '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          {lastPage > 1 && (
            <div className="flex items-center justify-center gap-2">
              <button onClick={() => setPage(Math.max(1, page - 1))} disabled={page === 1}
                className="px-3 py-1 bg-surface border border-border rounded text-sm text-white disabled:opacity-30">Prec.</button>
              <span className="text-sm text-muted">Page {page} / {lastPage}</span>
              <button onClick={() => setPage(Math.min(lastPage, page + 1))} disabled={page === lastPage}
                className="px-3 py-1 bg-surface border border-border rounded text-sm text-white disabled:opacity-30">Suiv.</button>
            </div>
          )}
        </>
      )}

      {/* TAB: Top viewed */}
      {tab === 'top' && stats && (
        <div className="bg-surface border border-border rounded-xl overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-muted">
                <th className="px-4 py-3 font-medium">#</th>
                <th className="px-4 py-3 font-medium">Question</th>
                <th className="px-4 py-3 font-medium">Pays</th>
                <th className="px-4 py-3 font-medium text-right">Vues</th>
                <th className="px-4 py-3 font-medium text-right">Reponses</th>
                <th className="px-4 py-3 font-medium">Statut</th>
              </tr>
            </thead>
            <tbody>
              {stats.top_viewed.map((q, i) => (
                <tr key={q.id} className="border-b border-border/50 hover:bg-surface2">
                  <td className="px-4 py-2 text-muted">{i + 1}</td>
                  <td className="px-4 py-2 text-white">{q.title}</td>
                  <td className="px-4 py-2 text-gray-400 text-xs">{q.country}</td>
                  <td className="px-4 py-2 text-right text-white font-bold">{q.views.toLocaleString()}</td>
                  <td className="px-4 py-2 text-right text-muted">{q.replies}</td>
                  <td className="px-4 py-2">
                    <span className={`px-2 py-0.5 rounded text-xs ${STATUS_LABELS[q.article_status]?.color || 'bg-gray-700 text-gray-300'}`}>
                      {STATUS_LABELS[q.article_status]?.label || q.article_status}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* TAB: By country */}
      {tab === 'stats' && stats && (
        <div className="bg-surface border border-border rounded-xl overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-muted">
                <th className="px-4 py-3 font-medium">Pays</th>
                <th className="px-4 py-3 font-medium text-right">Questions</th>
                <th className="px-4 py-3 font-medium text-right">Vues totales</th>
              </tr>
            </thead>
            <tbody>
              {stats.by_country.map((c) => (
                <tr key={c.country_slug} className="border-b border-border/50 hover:bg-surface2 cursor-pointer"
                  onClick={() => { setFilterCountry(c.country_slug); setTab('list'); setPage(1); }}>
                  <td className="px-4 py-2 text-white">{c.country}</td>
                  <td className="px-4 py-2 text-right text-white font-bold">{c.count.toLocaleString()}</td>
                  <td className="px-4 py-2 text-right text-muted">{c.total_views?.toLocaleString()}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
