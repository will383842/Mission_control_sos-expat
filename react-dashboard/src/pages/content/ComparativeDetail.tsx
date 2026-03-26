import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchComparative,
  updateComparative,
  deleteComparative,
  publishComparative,
  fetchEndpoints,
} from '../../api/contentApi';
import type {
  Comparative,
  ContentStatus,
  PublishingEndpoint,
} from '../../types/content';

// ── Constants ───────────────────────────────────────────────
type Tab = 'content' | 'seo' | 'publish';

const TABS: { key: Tab; label: string }[] = [
  { key: 'content', label: 'Contenu' },
  { key: 'seo', label: 'SEO' },
  { key: 'publish', label: 'Publier' },
];

const STATUS_COLORS: Record<ContentStatus, string> = {
  draft: 'bg-muted/20 text-muted',
  generating: 'bg-amber/20 text-amber',
  review: 'bg-orange-500/20 text-orange-400',
  scheduled: 'bg-cyan/20 text-cyan',
  published: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted',
};

const STATUS_LABELS: Record<ContentStatus, string> = {
  draft: 'Brouillon',
  generating: 'Generation...',
  review: 'A relire',
  scheduled: 'Planifie',
  published: 'Publie',
  archived: 'Archive',
};

function seoColor(score: number) {
  if (score >= 80) return 'text-success';
  if (score >= 60) return 'text-amber';
  return 'text-danger';
}

function seoBgColor(score: number) {
  if (score >= 80) return 'bg-success/20 text-success';
  if (score >= 60) return 'bg-amber/20 text-amber';
  return 'bg-danger/20 text-danger';
}

const inputClass = 'w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

// ── Component ───────────────────────────────────────────────
export default function ComparativeDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const comparativeId = Number(id);

  const [comparative, setComparative] = useState<Comparative | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [tab, setTab] = useState<Tab>('content');

  // Edit state
  const [editing, setEditing] = useState(false);
  const [editTitle, setEditTitle] = useState('');
  const [editExcerpt, setEditExcerpt] = useState('');
  const [editContentHtml, setEditContentHtml] = useState('');
  const [saving, setSaving] = useState(false);

  // Publish state
  const [endpoints, setEndpoints] = useState<PublishingEndpoint[]>([]);
  const [selectedEndpointId, setSelectedEndpointId] = useState<number | null>(null);
  const [publishing, setPublishing] = useState(false);
  const [publishError, setPublishError] = useState<string | null>(null);

  const loadComparative = useCallback(async () => {
    try {
      const { data } = await fetchComparative(comparativeId);
      setComparative(data);
      setEditTitle(data.title);
      setEditExcerpt(data.excerpt ?? '');
      setEditContentHtml(data.content_html ?? '');
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  }, [comparativeId]);

  useEffect(() => {
    loadComparative();
  }, [loadComparative]);

  useEffect(() => {
    fetchEndpoints().then(res => setEndpoints(res.data)).catch(() => {});
  }, []);

  const handleSave = async () => {
    if (!comparative) return;
    setSaving(true);
    try {
      const { data } = await updateComparative(comparative.id, {
        title: editTitle,
        excerpt: editExcerpt || null,
        content_html: editContentHtml || null,
      });
      setComparative(data);
      setEditing(false);
    } catch { /* ignore */ }
    finally { setSaving(false); }
  };

  const handleDelete = async () => {
    if (!comparative || !confirm('Supprimer ce comparatif ?')) return;
    try {
      await deleteComparative(comparative.id);
      navigate('/content/comparatives');
    } catch { /* ignore */ }
  };

  const handlePublish = async () => {
    if (!comparative || !selectedEndpointId) return;
    setPublishing(true);
    setPublishError(null);
    try {
      await publishComparative(comparative.id, { endpoint_id: selectedEndpointId });
      await loadComparative();
    } catch (err: unknown) {
      setPublishError(err instanceof Error ? err.message : 'Erreur lors de la publication');
    } finally {
      setPublishing(false);
    }
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6">
        <div className="text-muted text-sm">Chargement...</div>
      </div>
    );
  }

  if (error || !comparative) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-lg">
          {error || 'Comparatif introuvable'}
        </div>
      </div>
    );
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm text-muted">
        <button onClick={() => navigate('/content/overview')} className="hover:text-white transition-colors">Contenu</button>
        <span>/</span>
        <button onClick={() => navigate('/content/comparatives')} className="hover:text-white transition-colors">Comparatifs</button>
        <span>/</span>
        <span className="text-white truncate max-w-[200px]">{comparative.title}</span>
      </div>

      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">{comparative.title}</h2>
          <div className="flex items-center gap-3 mt-2">
            <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[comparative.status]}`}>
              {STATUS_LABELS[comparative.status]}
            </span>
            <span className="text-xs text-muted uppercase">{comparative.language}</span>
            {comparative.country && <span className="text-xs text-muted">{comparative.country}</span>}
            <span className={`text-xs font-medium ${seoColor(comparative.seo_score)}`}>
              SEO: {comparative.seo_score}/100
            </span>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {!editing && (
            <button
              onClick={() => setEditing(true)}
              className="px-4 py-1.5 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors"
            >
              Modifier
            </button>
          )}
          <button
            onClick={handleDelete}
            className="px-4 py-1.5 bg-surface2 text-danger hover:bg-danger/20 text-sm rounded-lg border border-border transition-colors"
          >
            Supprimer
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        {TABS.map(t => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
              tab === t.key
                ? 'border-violet text-white'
                : 'border-transparent text-muted hover:text-white'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {/* ── TAB: Content ── */}
      {tab === 'content' && (
        <div className="space-y-6">
          {/* Entities list */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white text-sm mb-3">
              Entites comparees ({comparative.entities.length})
            </h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              {comparative.entities.map((entity, i) => (
                <div key={i} className="bg-surface2 border border-border rounded-lg p-3">
                  <p className="text-white font-medium text-sm mb-1">{entity.name}</p>
                  {entity.description && (
                    <p className="text-muted text-xs mb-2">{entity.description}</p>
                  )}
                  {entity.rating !== undefined && (
                    <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${seoBgColor(entity.rating * 10)}`}>
                      {entity.rating}/10
                    </span>
                  )}
                  {entity.pros && entity.pros.length > 0 && (
                    <div className="mt-2">
                      <p className="text-xs text-success mb-1">Avantages:</p>
                      <ul className="text-xs text-muted space-y-0.5">
                        {entity.pros.map((p, j) => <li key={j}>+ {p}</li>)}
                      </ul>
                    </div>
                  )}
                  {entity.cons && entity.cons.length > 0 && (
                    <div className="mt-2">
                      <p className="text-xs text-danger mb-1">Inconvenients:</p>
                      <ul className="text-xs text-muted space-y-0.5">
                        {entity.cons.map((c, j) => <li key={j}>- {c}</li>)}
                      </ul>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>

          {/* Comparison data table */}
          {comparative.comparison_data && Object.keys(comparative.comparison_data).length > 0 && (
            <div className="bg-surface border border-border rounded-xl p-5">
              <h3 className="font-title font-semibold text-white text-sm mb-3">Tableau comparatif</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                      <th className="pb-3 pr-4">Critere</th>
                      {comparative.entities.map((e, i) => (
                        <th key={i} className="pb-3 pr-4">{e.name}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {Object.entries(comparative.comparison_data).map(([key, value]) => (
                      <tr key={key} className="border-b border-border/50">
                        <td className="py-2 pr-4 text-muted font-medium">{key}</td>
                        {comparative.entities.map((_, i) => {
                          const row = value as Record<string, unknown>;
                          const cellValue = row ? String(Object.values(row)[i] ?? '-') : '-';
                          return <td key={i} className="py-2 pr-4 text-white">{cellValue}</td>;
                        })}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* Content HTML */}
          {editing ? (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
              <h3 className="font-title font-semibold text-white text-sm">Modifier le contenu</h3>
              <div>
                <label className="block text-xs text-muted mb-1">Titre</label>
                <input
                  type="text"
                  value={editTitle}
                  onChange={e => setEditTitle(e.target.value)}
                  className={inputClass}
                />
              </div>
              <div>
                <label className="block text-xs text-muted mb-1">Extrait</label>
                <textarea
                  value={editExcerpt}
                  onChange={e => setEditExcerpt(e.target.value)}
                  rows={3}
                  className={inputClass}
                />
              </div>
              <div>
                <label className="block text-xs text-muted mb-1">Contenu HTML</label>
                <textarea
                  value={editContentHtml}
                  onChange={e => setEditContentHtml(e.target.value)}
                  rows={15}
                  className={`${inputClass} font-mono text-xs`}
                />
              </div>
              <div className="flex gap-3">
                <button
                  onClick={handleSave}
                  disabled={saving}
                  className="px-6 py-2 bg-violet hover:bg-violet/90 text-white font-semibold rounded-lg transition-colors disabled:opacity-50"
                >
                  {saving ? 'Sauvegarde...' : 'Sauvegarder'}
                </button>
                <button
                  onClick={() => {
                    setEditing(false);
                    setEditTitle(comparative.title);
                    setEditExcerpt(comparative.excerpt ?? '');
                    setEditContentHtml(comparative.content_html ?? '');
                  }}
                  className="px-4 py-2 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors"
                >
                  Annuler
                </button>
              </div>
            </div>
          ) : (
            comparative.content_html && (
              <div className="bg-surface border border-border rounded-xl p-5">
                <h3 className="font-title font-semibold text-white text-sm mb-3">Contenu</h3>
                {comparative.excerpt && (
                  <p className="text-muted text-sm mb-4 italic">{comparative.excerpt}</p>
                )}
                <div
                  className="prose prose-invert prose-sm max-w-none"
                  dangerouslySetInnerHTML={{ __html: comparative.content_html }}
                />
              </div>
            )
          )}
        </div>
      )}

      {/* ── TAB: SEO ── */}
      {tab === 'seo' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="bg-surface border border-border rounded-xl p-5">
              <p className="text-xs text-muted uppercase tracking-wide mb-1">Score SEO</p>
              <p className={`text-2xl font-bold ${seoColor(comparative.seo_score)}`}>
                {comparative.seo_score}/100
              </p>
            </div>
            <div className="bg-surface border border-border rounded-xl p-5">
              <p className="text-xs text-muted uppercase tracking-wide mb-1">Score qualite</p>
              <p className={`text-2xl font-bold ${seoColor(comparative.quality_score)}`}>
                {comparative.quality_score}/100
              </p>
            </div>
            <div className="bg-surface border border-border rounded-xl p-5">
              <p className="text-xs text-muted uppercase tracking-wide mb-1">Langue</p>
              <p className="text-2xl font-bold text-white uppercase">{comparative.language}</p>
            </div>
            <div className="bg-surface border border-border rounded-xl p-5">
              <p className="text-xs text-muted uppercase tracking-wide mb-1">Cout generation</p>
              <p className="text-2xl font-bold text-white">
                ${(comparative.generation_cost_cents / 100).toFixed(2)}
              </p>
            </div>
          </div>

          {/* Meta info */}
          <div className="bg-surface border border-border rounded-xl p-5 space-y-3">
            <h3 className="font-title font-semibold text-white text-sm">Meta SEO</h3>
            {comparative.meta_title && (
              <div>
                <p className="text-xs text-muted mb-0.5">Meta title</p>
                <p className="text-sm text-white">{comparative.meta_title}</p>
                <p className="text-xs text-muted mt-0.5">{comparative.meta_title.length}/60 caracteres</p>
              </div>
            )}
            {comparative.meta_description && (
              <div>
                <p className="text-xs text-muted mb-0.5">Meta description</p>
                <p className="text-sm text-white">{comparative.meta_description}</p>
                <p className="text-xs text-muted mt-0.5">{comparative.meta_description.length}/160 caracteres</p>
              </div>
            )}
            {comparative.slug && (
              <div>
                <p className="text-xs text-muted mb-0.5">Slug</p>
                <p className="text-sm text-violet font-mono">{comparative.slug}</p>
              </div>
            )}
          </div>

          {/* Hreflang */}
          {comparative.hreflang_map && Object.keys(comparative.hreflang_map).length > 0 && (
            <div className="bg-surface border border-border rounded-xl p-5">
              <h3 className="font-title font-semibold text-white text-sm mb-3">Hreflang</h3>
              <div className="space-y-1">
                {Object.entries(comparative.hreflang_map).map(([lang, url]) => (
                  <div key={lang} className="flex items-center gap-3 text-sm">
                    <span className="text-muted uppercase w-8">{lang}</span>
                    <span className="text-white truncate">{url}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* JSON-LD */}
          {comparative.json_ld && (
            <div className="bg-surface border border-border rounded-xl p-5">
              <h3 className="font-title font-semibold text-white text-sm mb-3">JSON-LD</h3>
              <pre className="text-xs text-muted bg-bg rounded-lg p-4 overflow-x-auto max-h-64">
                {JSON.stringify(comparative.json_ld, null, 2)}
              </pre>
            </div>
          )}
        </div>
      )}

      {/* ── TAB: Publish ── */}
      {tab === 'publish' && (
        <div className="space-y-6">
          <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
            <h3 className="font-title font-semibold text-white text-sm">Publier le comparatif</h3>

            {comparative.status === 'published' && comparative.published_at && (
              <div className="bg-success/10 border border-success/30 text-success text-sm px-4 py-3 rounded-lg">
                Publie le {new Date(comparative.published_at).toLocaleString('fr-FR')}
              </div>
            )}

            <div>
              <label className="block text-xs text-muted mb-1">Endpoint de publication</label>
              <select
                value={selectedEndpointId ?? ''}
                onChange={e => setSelectedEndpointId(e.target.value ? Number(e.target.value) : null)}
                className={inputClass}
              >
                <option value="">Selectionner un endpoint...</option>
                {endpoints.filter(ep => ep.is_active).map(ep => (
                  <option key={ep.id} value={ep.id}>{ep.name} ({ep.type})</option>
                ))}
              </select>
            </div>

            {publishError && (
              <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-lg">
                {publishError}
              </div>
            )}

            <button
              onClick={handlePublish}
              disabled={publishing || !selectedEndpointId}
              className="px-6 py-2 bg-violet hover:bg-violet/90 text-white font-semibold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {publishing ? 'Publication...' : 'Publier'}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
