import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchCluster,
  generateClusterBrief,
  generateFromCluster,
  generateClusterQa,
  deleteCluster,
} from '../../api/contentApi';
import type { TopicCluster, ClusterStatus } from '../../types/content';

// ── Constants ───────────────────────────────────────────────
const CLUSTER_STATUS_COLORS: Record<ClusterStatus, string> = {
  pending: 'bg-muted/20 text-muted',
  ready: 'bg-blue-500/20 text-blue-400',
  generating: 'bg-amber/20 text-amber animate-pulse',
  generated: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted',
};

const CLUSTER_STATUS_LABELS: Record<ClusterStatus, string> = {
  pending: 'En attente',
  ready: 'Pret',
  generating: 'Generation...',
  generated: 'Genere',
  archived: 'Archive',
};

const PROCESSING_STATUS_COLORS: Record<string, string> = {
  pending: 'bg-muted/20 text-muted',
  extracted: 'bg-blue-500/20 text-blue-400',
  used: 'bg-success/20 text-success',
};

function seoBgColor(score: number) {
  if (score >= 80) return 'bg-success/20 text-success';
  if (score >= 60) return 'bg-amber/20 text-amber';
  return 'bg-danger/20 text-danger';
}

// ── Component ───────────────────────────────────────────────
export default function ClusterDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [cluster, setCluster] = useState<TopicCluster | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [expandedArticle, setExpandedArticle] = useState<number | null>(null);

  const loadCluster = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await fetchCluster(Number(id));
      setCluster(res.data as unknown as TopicCluster);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur de chargement';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => { loadCluster(); }, [loadCluster]);

  const handleAction = async (action: string) => {
    if (!cluster) return;
    setActionLoading(action);
    try {
      if (action === 'brief') {
        await generateClusterBrief(cluster.id);
      } else if (action === 'generate') {
        await generateFromCluster(cluster.id);
      } else if (action === 'qa') {
        await generateClusterQa(cluster.id);
      } else if (action === 'delete') {
        if (!window.confirm('Supprimer ce cluster ?')) { setActionLoading(null); return; }
        await deleteCluster(cluster.id);
        navigate('/content/clusters');
        return;
      }
      loadCluster();
    } catch {
      // silently handled
    } finally {
      setActionLoading(null);
    }
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-64 mb-4" />
        <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">
          <div className="lg:col-span-3 space-y-4">
            {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-24" />)}
          </div>
          <div className="lg:col-span-2 animate-pulse bg-surface2 rounded-xl h-96" />
        </div>
      </div>
    );
  }

  if (error || !cluster) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-surface border border-border rounded-xl p-8 text-center">
          <p className="text-danger mb-4">{error || 'Cluster introuvable'}</p>
          <button onClick={() => navigate('/content/clusters')} className="text-sm text-violet hover:text-violet-light transition-colors">
            Retour aux clusters
          </button>
        </div>
      </div>
    );
  }

  const brief = cluster.research_brief;
  const sourceArticles = cluster.source_articles ?? [];

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <button onClick={() => navigate('/content/clusters')} className="text-xs text-muted hover:text-white transition-colors mb-2 inline-flex items-center gap-1">
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Retour aux clusters
          </button>
          <h2 className="font-title text-2xl font-bold text-white">{cluster.name}</h2>
          <div className="flex items-center gap-3 mt-2">
            <span className="text-sm text-muted capitalize">{cluster.country}</span>
            <span className="text-muted">|</span>
            <span className="text-sm text-muted capitalize">{cluster.category}</span>
            <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${CLUSTER_STATUS_COLORS[cluster.status]}`}>
              {CLUSTER_STATUS_LABELS[cluster.status]}
            </span>
          </div>
          {cluster.description && <p className="text-sm text-muted mt-2">{cluster.description}</p>}
        </div>
        <div className="flex items-center gap-2 flex-wrap">
          {(cluster.status === 'pending' || cluster.status === 'ready') && (
            <button
              onClick={() => handleAction('brief')}
              disabled={!!actionLoading}
              className="px-4 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
            >
              {actionLoading === 'brief' ? 'Generation...' : 'Generer Brief'}
            </button>
          )}
          {(cluster.status === 'ready' || brief) && !cluster.generated_article_id && (
            <button
              onClick={() => handleAction('generate')}
              disabled={!!actionLoading}
              className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
            >
              {actionLoading === 'generate' ? 'Generation...' : 'Generer Article'}
            </button>
          )}
          {cluster.status === 'generated' && (
            <button
              onClick={() => handleAction('qa')}
              disabled={!!actionLoading}
              className="px-4 py-1.5 bg-success/80 hover:bg-success text-white text-sm rounded-lg transition-colors disabled:opacity-50"
            >
              {actionLoading === 'qa' ? 'Generation...' : 'Generer Q&A'}
            </button>
          )}
          <button
            onClick={() => handleAction('delete')}
            disabled={!!actionLoading}
            className="px-4 py-1.5 bg-surface2 text-danger hover:bg-danger/20 text-sm rounded-lg border border-border transition-colors disabled:opacity-50"
          >
            Supprimer
          </button>
        </div>
      </div>

      {/* Keywords tags */}
      {cluster.keywords_detected && cluster.keywords_detected.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {cluster.keywords_detected.map(kw => (
            <span key={kw} className="inline-block px-2 py-1 rounded-lg text-xs bg-violet/20 text-violet-light">
              {kw}
            </span>
          ))}
        </div>
      )}

      {/* Two columns */}
      <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">
        {/* Left: Source articles (60%) */}
        <div className="lg:col-span-3 space-y-3">
          <h3 className="font-title font-semibold text-white">Articles sources ({sourceArticles.length})</h3>
          {sourceArticles.length === 0 ? (
            <div className="bg-surface border border-border rounded-xl p-6 text-center text-muted text-sm">
              Aucun article source associe
            </div>
          ) : (
            sourceArticles.map(sa => (
              <div
                key={sa.id}
                className={`bg-surface border rounded-xl p-4 transition-colors cursor-pointer ${
                  sa.is_primary ? 'border-violet/50' : 'border-border'
                }`}
                onClick={() => setExpandedArticle(expandedArticle === sa.id ? null : sa.id)}
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 mb-1">
                      {sa.is_primary && (
                        <span className="px-1.5 py-0.5 rounded text-[10px] bg-violet/20 text-violet-light font-medium">Principal</span>
                      )}
                      <span className={`px-1.5 py-0.5 rounded text-[10px] font-medium ${PROCESSING_STATUS_COLORS[sa.processing_status] || 'text-muted'}`}>
                        {sa.processing_status}
                      </span>
                    </div>
                    <p className="text-white font-medium text-sm">{sa.source_article?.title ?? `Article #${sa.source_article_id}`}</p>
                    {sa.source_article?.url && (
                      <a href={sa.source_article.url} target="_blank" rel="noopener noreferrer" className="text-xs text-violet hover:text-violet-light truncate block mt-0.5" onClick={e => e.stopPropagation()}>
                        {sa.source_article.url}
                      </a>
                    )}
                  </div>
                  <div className="text-right flex-shrink-0">
                    <div className={`text-xs px-2 py-0.5 rounded ${seoBgColor(sa.relevance_score * 100)}`}>
                      {Math.round(sa.relevance_score * 100)}%
                    </div>
                    {sa.source_article?.word_count != null && (
                      <p className="text-[10px] text-muted mt-1">{sa.source_article.word_count} mots</p>
                    )}
                  </div>
                </div>
                {expandedArticle === sa.id && sa.extracted_facts && (
                  <div className="mt-3 pt-3 border-t border-border">
                    <p className="text-xs text-muted mb-1">Faits extraits:</p>
                    <pre className="text-xs text-gray-400 bg-bg rounded p-2 overflow-x-auto max-h-40">
                      {JSON.stringify(sa.extracted_facts, null, 2)}
                    </pre>
                  </div>
                )}
              </div>
            ))
          )}
        </div>

        {/* Right: Research brief (40%) */}
        <div className="lg:col-span-2 space-y-4">
          {brief ? (
            <>
              <div className="bg-surface border border-border rounded-xl p-5">
                <div className="flex items-center justify-between mb-3">
                  <h3 className="font-title font-semibold text-white">Research Brief</h3>
                  <span className="text-[10px] text-muted">{brief.tokens_used} tokens | ${(brief.cost_cents / 100).toFixed(2)}</span>
                </div>

                {/* Key facts */}
                {brief.extracted_facts && brief.extracted_facts.length > 0 && (
                  <div className="mb-4">
                    <p className="text-xs text-muted uppercase tracking-wide mb-2">Faits cles</p>
                    <ul className="space-y-1">
                      {brief.extracted_facts.map((fact, i) => (
                        <li key={i} className="text-xs text-gray-300 flex items-start gap-2">
                          <span className="text-violet mt-0.5">-</span>
                          <span>{typeof fact === 'string' ? fact : JSON.stringify(fact)}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                {/* Recent data */}
                {brief.recent_data && brief.recent_data.length > 0 && (
                  <div className="mb-4">
                    <p className="text-xs text-muted uppercase tracking-wide mb-2">Donnees recentes</p>
                    <ul className="space-y-1">
                      {brief.recent_data.map((d, i) => (
                        <li key={i} className="text-xs text-gray-300 flex items-start gap-2">
                          <span className="text-blue-400 mt-0.5">-</span>
                          <span>{typeof d === 'string' ? d : JSON.stringify(d)}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                {/* PAA questions */}
                {brief.paa_questions && brief.paa_questions.length > 0 && (
                  <div className="mb-4">
                    <p className="text-xs text-muted uppercase tracking-wide mb-2">Questions PAA</p>
                    <ul className="space-y-1">
                      {brief.paa_questions.map((q, i) => (
                        <li key={i} className="text-xs text-gray-300 flex items-start gap-2">
                          <span className="text-amber mt-0.5">?</span>
                          <span>{q}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                {/* Identified gaps */}
                {brief.identified_gaps && brief.identified_gaps.length > 0 && (
                  <div className="mb-4">
                    <p className="text-xs text-muted uppercase tracking-wide mb-2">Lacunes identifiees</p>
                    <ul className="space-y-1">
                      {brief.identified_gaps.map((g, i) => (
                        <li key={i} className="text-xs text-gray-300 flex items-start gap-2">
                          <span className="text-danger mt-0.5">!</span>
                          <span>{g}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>

              {/* Suggested keywords */}
              {brief.suggested_keywords && (
                <div className="bg-surface border border-border rounded-xl p-5">
                  <h4 className="font-title font-semibold text-white mb-3">Mots-cles suggeres</h4>
                  {Object.entries(brief.suggested_keywords).map(([type, keywords]) => (
                    keywords && keywords.length > 0 ? (
                      <div key={type} className="mb-3">
                        <p className="text-[10px] text-muted uppercase tracking-wide mb-1">{type.replace('_', ' ')}</p>
                        <div className="flex flex-wrap gap-1">
                          {keywords.map(kw => (
                            <span key={kw} className="inline-block px-1.5 py-0.5 rounded text-[10px] bg-violet/15 text-violet-light">
                              {kw}
                            </span>
                          ))}
                        </div>
                      </div>
                    ) : null
                  ))}
                </div>
              )}

              {/* Suggested structure */}
              {brief.suggested_structure && brief.suggested_structure.length > 0 && (
                <div className="bg-surface border border-border rounded-xl p-5">
                  <h4 className="font-title font-semibold text-white mb-3">Structure suggeree</h4>
                  <ul className="space-y-2">
                    {brief.suggested_structure.map((section, i) => (
                      <li key={i} className="text-sm text-gray-300">
                        <span className="text-muted mr-2">{i + 1}.</span>
                        {typeof section === 'string' ? section : (section as Record<string, unknown>).title as string || JSON.stringify(section)}
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </>
          ) : (
            <div className="bg-surface border border-border rounded-xl p-8 text-center">
              <p className="text-muted text-sm mb-4">Aucun brief de recherche genere</p>
              <button
                onClick={() => handleAction('brief')}
                disabled={!!actionLoading}
                className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
              >
                {actionLoading === 'brief' ? 'Generation...' : 'Generer Brief'}
              </button>
            </div>
          )}

          {/* Link to generated article */}
          {cluster.generated_article_id && (
            <div className="bg-surface border border-success/30 rounded-xl p-5">
              <h4 className="font-title font-semibold text-success mb-2">Article genere</h4>
              {cluster.generated_article ? (
                <div>
                  <p className="text-sm text-white mb-2">{cluster.generated_article.title}</p>
                  <button
                    onClick={() => navigate(`/content/articles/${cluster.generated_article_id}`)}
                    className="text-xs text-violet hover:text-violet-light transition-colors"
                  >
                    Voir l'article
                  </button>
                </div>
              ) : (
                <button
                  onClick={() => navigate(`/content/articles/${cluster.generated_article_id}`)}
                  className="text-sm text-violet hover:text-violet-light transition-colors"
                >
                  Voir l'article #{cluster.generated_article_id}
                </button>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
