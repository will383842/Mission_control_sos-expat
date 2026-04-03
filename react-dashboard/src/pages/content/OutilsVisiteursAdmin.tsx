import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchVisitorTools,
  toggleVisitorTool,
  fetchToolLeads,
  type VisitorTool,
  type ToolsStats,
  type ToolLead,
  type LeadFilters,
} from '../../api/outilsVisiteursApi';
import { toast } from '../../components/Toast';

const CATEGORY_LABELS: Record<string, string> = {
  calculate: 'Calculer',
  compare:   'Comparer',
  generate:  'Générer',
  emergency: 'Urgence',
};

const CATEGORY_COLORS: Record<string, string> = {
  calculate: 'bg-blue-500/20 text-blue-300',
  compare:   'bg-purple-500/20 text-purple-300',
  generate:  'bg-emerald-500/20 text-emerald-300',
  emergency: 'bg-red-500/20 text-red-300',
};

type Tab = 'tools' | 'leads';

export default function OutilsVisiteursAdmin() {
  const [tab, setTab] = useState<Tab>('tools');

  // ── Tools tab ──────────────────────────────────────────────
  const [tools, setTools] = useState<VisitorTool[]>([]);
  const [stats, setStats] = useState<ToolsStats | null>(null);
  const [loadingTools, setLoadingTools] = useState(true);
  const [togglingId, setTogglingId] = useState<string | null>(null);

  const loadTools = useCallback(async () => {
    setLoadingTools(true);
    try {
      const res = await fetchVisitorTools();
      setTools(res.data);
      setStats(res.stats);
    } catch {
      toast.error('Impossible de contacter le Blog (vérifier BLOG_API_KEY)');
    } finally {
      setLoadingTools(false);
    }
  }, []);

  useEffect(() => { loadTools(); }, [loadTools]);

  const handleToggle = async (tool: VisitorTool) => {
    setTogglingId(tool.id);
    try {
      const res = await toggleVisitorTool(tool.id);
      setTools(prev => prev.map(t => t.id === res.id ? { ...t, is_active: res.is_active } : t));
    } catch {
      toast.error('Erreur lors de la mise à jour');
    } finally {
      setTogglingId(null);
    }
  };

  // ── Leads tab ──────────────────────────────────────────────
  const [leads, setLeads] = useState<ToolLead[]>([]);
  const [leadStats, setLeadStats] = useState({ total: 0, current_page: 1, last_page: 1 });
  const [loadingLeads, setLoadingLeads] = useState(false);
  const [filters, setFilters] = useState<LeadFilters>({ page: 1, per_page: 50 });

  const loadLeads = useCallback(async () => {
    setLoadingLeads(true);
    try {
      const res = await fetchToolLeads(filters);
      setLeads(res.data);
      setLeadStats({ total: res.total, current_page: res.current_page, last_page: res.last_page });
    } catch {
      toast.error('Erreur lors du chargement des leads');
    } finally {
      setLoadingLeads(false);
    }
  }, [filters]);

  useEffect(() => {
    if (tab === 'leads') loadLeads();
  }, [tab, loadLeads]);

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-text">Outils Visiteurs</h1>
          <p className="text-muted text-sm mt-1">
            Les {stats?.total ?? '—'} outils accessibles sur{' '}
            <a
              href="https://sos-expat.com/fr-fr/outils"
              target="_blank"
              rel="noopener noreferrer"
              className="text-violet hover:underline"
            >
              sos-expat.com/outils
            </a>
            {' '}— données en direct depuis le Blog
          </p>
        </div>
        <button
          onClick={loadTools}
          className="text-sm text-muted hover:text-text border border-border px-3 py-1.5 rounded-lg transition-colors"
        >
          ↻ Actualiser
        </button>
      </div>

      {/* Stats */}
      {stats && (
        <div className="grid grid-cols-5 gap-4">
          <div className="bg-surface1 rounded-xl p-4 border border-border">
            <p className="text-muted text-xs mb-1">Total outils</p>
            <p className="text-2xl font-bold text-text">{stats.total}</p>
          </div>
          <div className="bg-surface1 rounded-xl p-4 border border-border">
            <p className="text-muted text-xs mb-1">Actifs</p>
            <p className="text-2xl font-bold text-green-400">{stats.active}</p>
          </div>
          <div className="bg-surface1 rounded-xl p-4 border border-border">
            <p className="text-muted text-xs mb-1">Leads total</p>
            <p className="text-2xl font-bold text-violet">{stats.total_leads}</p>
          </div>
          <div className="bg-surface1 rounded-xl p-4 border border-border">
            <p className="text-muted text-xs mb-1">Leads aujourd'hui</p>
            <p className="text-2xl font-bold text-blue-400">{stats.today_leads}</p>
          </div>
          <div className="bg-surface1 rounded-xl p-4 border border-border">
            <p className="text-muted text-xs mb-1">Leads 7 jours</p>
            <p className="text-2xl font-bold text-purple-400">{stats.week_leads}</p>
          </div>
        </div>
      )}

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        {(['tools', 'leads'] as Tab[]).map(t => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${
              tab === t
                ? 'border-violet text-violet'
                : 'border-transparent text-muted hover:text-text'
            }`}
          >
            {t === 'tools' ? '🛠️ Outils' : '📧 Leads'}
          </button>
        ))}
      </div>

      {/* ── Tab: Outils ─────────────────────────────────────── */}
      {tab === 'tools' && (
        <>
          {loadingTools ? (
            <div className="text-center py-12 text-muted">Chargement depuis le Blog...</div>
          ) : tools.length === 0 ? (
            <div className="text-center py-12">
              <p className="text-muted text-lg mb-2">Aucun outil trouvé</p>
              <p className="text-muted/60 text-sm">Vérifiez que BLOG_API_KEY est configuré</p>
            </div>
          ) : (
            <div className="bg-surface1 rounded-xl border border-border overflow-hidden">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-muted text-xs uppercase">
                    <th className="text-left px-4 py-3">Outil</th>
                    <th className="text-left px-4 py-3">Catégorie</th>
                    <th className="text-center px-4 py-3">IA</th>
                    <th className="text-right px-4 py-3">Leads</th>
                    <th className="text-center px-4 py-3">Actif</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {tools.map(tool => (
                    <tr key={tool.id} className="hover:bg-surface2/50 transition-colors">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <span className="text-lg">{tool.icon}</span>
                          <div>
                            <p className="font-medium text-text">{tool.title_fr ?? tool.slug_key}</p>
                            <p className="text-xs text-muted">{tool.slug_key}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <span className={`px-2 py-0.5 rounded text-xs font-medium ${CATEGORY_COLORS[tool.category] ?? 'bg-surface2 text-muted'}`}>
                          {CATEGORY_LABELS[tool.category] ?? tool.category}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-center">
                        {tool.is_ai_powered && (
                          <span className="text-xs bg-amber-500/20 text-amber-300 px-1.5 py-0.5 rounded">IA</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-right font-mono text-text">
                        {tool.leads_count}
                      </td>
                      <td className="px-4 py-3 text-center">
                        <button
                          onClick={() => handleToggle(tool)}
                          disabled={togglingId === tool.id}
                          className={`w-9 h-5 rounded-full transition-colors relative disabled:opacity-50 ${tool.is_active ? 'bg-green-500' : 'bg-surface2'}`}
                        >
                          <span className={`absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform ${tool.is_active ? 'translate-x-4' : 'translate-x-0.5'}`} />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}

      {/* ── Tab: Leads ──────────────────────────────────────── */}
      {tab === 'leads' && (
        <div className="space-y-4">
          {/* Filters */}
          <div className="flex gap-3 flex-wrap">
            <select
              value={filters.tool_id ?? ''}
              onChange={e => setFilters(f => ({ ...f, tool_id: e.target.value || undefined, page: 1 }))}
              className="bg-surface1 border border-border text-text text-sm rounded-lg px-3 py-2"
            >
              <option value="">Tous les outils</option>
              {tools.map(t => (
                <option key={t.id} value={t.id}>{t.title_fr ?? t.slug_key}</option>
              ))}
            </select>
            <input
              type="text"
              placeholder="Rechercher email..."
              value={filters.search ?? ''}
              onChange={e => setFilters(f => ({ ...f, search: e.target.value || undefined, page: 1 }))}
              className="bg-surface1 border border-border text-text text-sm rounded-lg px-3 py-2 w-48"
            />
            <input
              type="date"
              value={filters.from ?? ''}
              onChange={e => setFilters(f => ({ ...f, from: e.target.value || undefined, page: 1 }))}
              className="bg-surface1 border border-border text-text text-sm rounded-lg px-3 py-2"
            />
            <input
              type="date"
              value={filters.to ?? ''}
              onChange={e => setFilters(f => ({ ...f, to: e.target.value || undefined, page: 1 }))}
              className="bg-surface1 border border-border text-text text-sm rounded-lg px-3 py-2"
            />
            <button
              onClick={loadLeads}
              className="bg-violet hover:bg-violet/90 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
            >
              Filtrer
            </button>
          </div>

          {loadingLeads ? (
            <div className="text-center py-12 text-muted">Chargement...</div>
          ) : leads.length === 0 ? (
            <div className="text-center py-12 text-muted">Aucun lead trouvé</div>
          ) : (
            <>
              <p className="text-xs text-muted">{leadStats.total} leads au total</p>
              <div className="bg-surface1 rounded-xl border border-border overflow-hidden">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-border text-muted text-xs uppercase">
                      <th className="text-left px-4 py-3">Email</th>
                      <th className="text-left px-4 py-3">Outil</th>
                      <th className="text-left px-4 py-3">Langue</th>
                      <th className="text-left px-4 py-3">Pays</th>
                      <th className="text-center px-4 py-3">CGU</th>
                      <th className="text-center px-4 py-3">Synced</th>
                      <th className="text-right px-4 py-3">Date</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {leads.map(lead => (
                      <tr key={lead.id} className="hover:bg-surface2/50 transition-colors">
                        <td className="px-4 py-3 font-medium text-text">{lead.email}</td>
                        <td className="px-4 py-3 text-muted text-xs">{lead.tool?.slug_key ?? '—'}</td>
                        <td className="px-4 py-3 text-muted uppercase text-xs">{lead.preferred_language ?? lead.language_code ?? '—'}</td>
                        <td className="px-4 py-3 text-muted uppercase text-xs">{lead.country_code ?? '—'}</td>
                        <td className="px-4 py-3 text-center">
                          <span className={`text-xs px-1.5 py-0.5 rounded ${lead.cgu_accepted ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'}`}>
                            {lead.cgu_accepted ? 'Oui' : 'Non'}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-center">
                          <span className={`text-xs px-1.5 py-0.5 rounded ${lead.synced_at ? 'bg-blue-500/20 text-blue-400' : 'bg-surface2 text-muted'}`}>
                            {lead.synced_at ? 'Oui' : 'Non'}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-right text-muted text-xs">
                          {new Date(lead.created_at).toLocaleDateString('fr-FR')}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Pagination */}
              {leadStats.last_page > 1 && (
                <div className="flex justify-center gap-2">
                  <button
                    onClick={() => setFilters(f => ({ ...f, page: (f.page ?? 1) - 1 }))}
                    disabled={(filters.page ?? 1) <= 1}
                    className="px-3 py-1 text-sm rounded border border-border text-muted hover:text-text disabled:opacity-30"
                  >
                    ← Préc.
                  </button>
                  <span className="px-3 py-1 text-sm text-muted">
                    {filters.page ?? 1} / {leadStats.last_page}
                  </span>
                  <button
                    onClick={() => setFilters(f => ({ ...f, page: (f.page ?? 1) + 1 }))}
                    disabled={(filters.page ?? 1) >= leadStats.last_page}
                    className="px-3 py-1 text-sm rounded border border-border text-muted hover:text-text disabled:opacity-30"
                  >
                    Suiv. →
                  </button>
                </div>
              )}
            </>
          )}
        </div>
      )}
    </div>
  );
}
