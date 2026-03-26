import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchQaEntry,
  updateQaEntry,
  deleteQaEntry,
  publishQaEntry,
} from '../../api/contentApi';
import type { QaEntry, ContentStatus, QaSourceType } from '../../types/content';

// ── Constants ───────────────────────────────────────────────
type Tab = 'content' | 'seo';

const TABS: { key: Tab; label: string }[] = [
  { key: 'content', label: 'Contenu' },
  { key: 'seo', label: 'SEO / Publier' },
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
  generating: 'Generation',
  review: 'A relire',
  scheduled: 'Planifie',
  published: 'Publie',
  archived: 'Archive',
};

const SOURCE_TYPE_LABELS: Record<QaSourceType, string> = {
  article_faq: 'FAQ Article',
  paa: 'PAA',
  scraped: 'Scrape',
  manual: 'Manuel',
  ai_suggested: 'IA',
};

function seoBgColor(score: number) {
  if (score >= 80) return 'bg-success/20 text-success';
  if (score >= 60) return 'bg-amber/20 text-amber';
  return 'bg-danger/20 text-danger';
}

const inputClass = 'w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

// ── Component ───────────────────────────────────────────────
export default function QaDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [entry, setEntry] = useState<QaEntry | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [activeTab, setActiveTab] = useState<Tab>('content');
  const [editMode, setEditMode] = useState(false);

  // Editable fields
  const [question, setQuestion] = useState('');
  const [answerShort, setAnswerShort] = useState('');
  const [answerDetailedHtml, setAnswerDetailedHtml] = useState('');
  const [metaTitle, setMetaTitle] = useState('');
  const [metaDescription, setMetaDescription] = useState('');
  const [slug, setSlug] = useState('');
  const [keywordsPrimary, setKeywordsPrimary] = useState('');
  const [keywordsSecondary, setKeywordsSecondary] = useState('');

  const loadEntry = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await fetchQaEntry(Number(id));
      const data = res.data as unknown as QaEntry;
      setEntry(data);
      setQuestion(data.question);
      setAnswerShort(data.answer_short);
      setAnswerDetailedHtml(data.answer_detailed_html || '');
      setMetaTitle(data.meta_title || '');
      setMetaDescription(data.meta_description || '');
      setSlug(data.slug);
      setKeywordsPrimary(data.keywords_primary || '');
      setKeywordsSecondary((data.keywords_secondary || []).join(', '));
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur de chargement';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => { loadEntry(); }, [loadEntry]);

  const handleSave = async () => {
    if (!entry) return;
    setSaving(true);
    try {
      await updateQaEntry(entry.id, {
        question,
        answer_short: answerShort,
        answer_detailed_html: answerDetailedHtml || null,
        meta_title: metaTitle || null,
        meta_description: metaDescription || null,
        slug,
        keywords_primary: keywordsPrimary || null,
        keywords_secondary: keywordsSecondary ? keywordsSecondary.split(',').map(s => s.trim()).filter(Boolean) : null,
      });
      setEditMode(false);
      loadEntry();
    } catch {
      // silently handled
    } finally {
      setSaving(false);
    }
  };

  const handlePublish = async () => {
    if (!entry) return;
    try {
      await publishQaEntry(entry.id);
      loadEntry();
    } catch {
      // silently handled
    }
  };

  const handleDelete = async () => {
    if (!entry || !window.confirm('Supprimer cette Q&A ?')) return;
    try {
      await deleteQaEntry(entry.id);
      navigate('/content/qa');
    } catch {
      // silently handled
    }
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-64 mb-4" />
        <div className="animate-pulse bg-surface2 rounded-xl h-96" />
      </div>
    );
  }

  if (error || !entry) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-surface border border-border rounded-xl p-8 text-center">
          <p className="text-danger mb-4">{error || 'Q&A introuvable'}</p>
          <button onClick={() => navigate('/content/qa')} className="text-sm text-violet hover:text-violet-light transition-colors">
            Retour aux Q&A
          </button>
        </div>
      </div>
    );
  }

  const wordCount = answerShort.split(/\s+/).filter(Boolean).length;

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <button onClick={() => navigate('/content/qa')} className="text-xs text-muted hover:text-white transition-colors mb-2 inline-flex items-center gap-1">
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Retour aux Q&A
          </button>
          <h2 className="font-title text-xl font-bold text-white">{entry.question}</h2>
        </div>
        <div className="flex items-center gap-2">
          {!editMode ? (
            <button onClick={() => setEditMode(true)} className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
              Modifier
            </button>
          ) : (
            <>
              <button onClick={() => { setEditMode(false); loadEntry(); }} className="px-4 py-1.5 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors">
                Annuler
              </button>
              <button onClick={handleSave} disabled={saving} className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50">
                {saving ? 'Sauvegarde...' : 'Sauvegarder'}
              </button>
            </>
          )}
          {entry.status !== 'published' && (
            <button onClick={handlePublish} className="px-4 py-1.5 bg-success/80 hover:bg-success text-white text-sm rounded-lg transition-colors">
              Publier
            </button>
          )}
          <button onClick={handleDelete} className="px-4 py-1.5 bg-surface2 text-danger hover:bg-danger/20 text-sm rounded-lg border border-border transition-colors">
            Supprimer
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        {TABS.map(tab => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`px-4 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px ${
              activeTab === tab.key
                ? 'text-violet-light border-violet'
                : 'text-muted hover:text-white border-transparent'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main content (2/3) */}
        <div className="lg:col-span-2 space-y-4">
          {activeTab === 'content' && (
            <>
              {/* Question */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <label className="text-xs text-muted uppercase tracking-wide mb-2 block">Question</label>
                {editMode ? (
                  <input type="text" value={question} onChange={e => setQuestion(e.target.value)} className={inputClass} />
                ) : (
                  <h1 className="text-lg font-bold text-white">{entry.question}</h1>
                )}
              </div>

              {/* Short answer */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <div className="flex items-center justify-between mb-2">
                  <label className="text-xs text-muted uppercase tracking-wide">Reponse courte</label>
                  <span className={`text-xs ${wordCount >= 40 && wordCount <= 60 ? 'text-success' : 'text-amber'}`}>
                    {wordCount} mots
                  </span>
                </div>
                {editMode ? (
                  <textarea value={answerShort} onChange={e => setAnswerShort(e.target.value)} rows={4} className={inputClass} />
                ) : (
                  <p className="text-sm text-gray-300 leading-relaxed">{entry.answer_short}</p>
                )}
              </div>

              {/* Detailed answer */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <div className="flex items-center justify-between mb-2">
                  <label className="text-xs text-muted uppercase tracking-wide">Reponse detaillee</label>
                  {!editMode && entry.answer_detailed_html && (
                    <button onClick={() => setEditMode(true)} className="text-xs text-violet hover:text-violet-light transition-colors">
                      Modifier
                    </button>
                  )}
                </div>
                {editMode ? (
                  <textarea value={answerDetailedHtml} onChange={e => setAnswerDetailedHtml(e.target.value)} rows={12} className={inputClass + ' font-mono text-xs'} placeholder="<p>Reponse detaillee en HTML...</p>" />
                ) : entry.answer_detailed_html ? (
                  <div className="prose prose-invert prose-sm max-w-none text-gray-300" dangerouslySetInnerHTML={{ __html: entry.answer_detailed_html }} />
                ) : (
                  <p className="text-sm text-muted italic">Aucune reponse detaillee</p>
                )}
              </div>

              {/* Related Q&A */}
              {entry.related_qa_ids && entry.related_qa_ids.length > 0 && (
                <div className="bg-surface border border-border rounded-xl p-5">
                  <label className="text-xs text-muted uppercase tracking-wide mb-2 block">Q&A liees</label>
                  <div className="flex flex-wrap gap-2">
                    {entry.related_qa_ids.map(qaId => (
                      <button
                        key={qaId}
                        onClick={() => navigate(`/content/qa/${qaId}`)}
                        className="px-2 py-1 text-xs bg-violet/15 text-violet-light rounded hover:bg-violet/25 transition-colors"
                      >
                        Q&A #{qaId}
                      </button>
                    ))}
                  </div>
                </div>
              )}

              {/* Parent article */}
              {entry.parent_article && (
                <div className="bg-surface border border-border rounded-xl p-5">
                  <label className="text-xs text-muted uppercase tracking-wide mb-2 block">Article parent</label>
                  <button
                    onClick={() => navigate(`/content/articles/${entry.parent_article_id}`)}
                    className="text-sm text-violet hover:text-violet-light transition-colors"
                  >
                    {entry.parent_article.title}
                  </button>
                </div>
              )}
            </>
          )}

          {activeTab === 'seo' && (
            <>
              {/* Meta title */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <div className="flex items-center justify-between mb-2">
                  <label className="text-xs text-muted uppercase tracking-wide">Meta titre</label>
                  <span className={`text-xs ${(metaTitle.length >= 50 && metaTitle.length <= 60) ? 'text-success' : 'text-amber'}`}>
                    {metaTitle.length}/60
                  </span>
                </div>
                {editMode ? (
                  <input type="text" value={metaTitle} onChange={e => setMetaTitle(e.target.value)} className={inputClass} />
                ) : (
                  <p className="text-sm text-white">{entry.meta_title || <span className="text-muted italic">Non defini</span>}</p>
                )}
              </div>

              {/* Meta description */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <div className="flex items-center justify-between mb-2">
                  <label className="text-xs text-muted uppercase tracking-wide">Meta description</label>
                  <span className={`text-xs ${(metaDescription.length >= 140 && metaDescription.length <= 160) ? 'text-success' : 'text-amber'}`}>
                    {metaDescription.length}/160
                  </span>
                </div>
                {editMode ? (
                  <textarea value={metaDescription} onChange={e => setMetaDescription(e.target.value)} rows={3} className={inputClass} />
                ) : (
                  <p className="text-sm text-gray-300">{entry.meta_description || <span className="text-muted italic">Non defini</span>}</p>
                )}
              </div>

              {/* Slug */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <label className="text-xs text-muted uppercase tracking-wide mb-2 block">Slug</label>
                {editMode ? (
                  <input type="text" value={slug} onChange={e => setSlug(e.target.value)} className={inputClass} />
                ) : (
                  <p className="text-sm text-white font-mono">{entry.slug}</p>
                )}
              </div>

              {/* Keywords */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <label className="text-xs text-muted uppercase tracking-wide mb-2 block">Mot-cle principal</label>
                {editMode ? (
                  <input type="text" value={keywordsPrimary} onChange={e => setKeywordsPrimary(e.target.value)} className={inputClass} />
                ) : (
                  <p className="text-sm text-white">{entry.keywords_primary || <span className="text-muted italic">Non defini</span>}</p>
                )}
                <label className="text-xs text-muted uppercase tracking-wide mb-2 block mt-4">Mots-cles secondaires</label>
                {editMode ? (
                  <input type="text" value={keywordsSecondary} onChange={e => setKeywordsSecondary(e.target.value)} className={inputClass} placeholder="mot1, mot2, mot3" />
                ) : (
                  <div className="flex flex-wrap gap-1">
                    {(entry.keywords_secondary || []).map(kw => (
                      <span key={kw} className="inline-block px-1.5 py-0.5 rounded text-xs bg-violet/15 text-violet-light">{kw}</span>
                    ))}
                    {(!entry.keywords_secondary || entry.keywords_secondary.length === 0) && <span className="text-muted text-sm italic">Aucun</span>}
                  </div>
                )}
              </div>

              {/* JSON-LD preview */}
              {entry.json_ld && (
                <div className="bg-surface border border-border rounded-xl p-5">
                  <label className="text-xs text-muted uppercase tracking-wide mb-2 block">JSON-LD</label>
                  <pre className="text-xs text-gray-400 bg-bg rounded p-3 overflow-x-auto max-h-60">
                    {JSON.stringify(entry.json_ld, null, 2)}
                  </pre>
                </div>
              )}
            </>
          )}
        </div>

        {/* Sidebar (1/3) */}
        <div className="space-y-4">
          {/* SEO score */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white mb-3">Score SEO</h3>
            <div className="flex items-center gap-3">
              <span className={`text-3xl font-bold px-3 py-1 rounded-lg ${seoBgColor(entry.seo_score)}`}>
                {entry.seo_score}
              </span>
              <span className="text-muted text-sm">/ 100</span>
            </div>
          </div>

          {/* Details */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title font-semibold text-white mb-3">Details</h3>
            <div className="space-y-3">
              <div className="flex justify-between text-sm">
                <span className="text-muted">Statut</span>
                <span className={`px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[entry.status]}`}>
                  {STATUS_LABELS[entry.status]}
                </span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-muted">Source</span>
                <span className="text-white">{SOURCE_TYPE_LABELS[entry.source_type]}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-muted">Langue</span>
                <span className="text-white uppercase">{entry.language}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-muted">Pays</span>
                <span className="text-white capitalize">{entry.country || '-'}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-muted">Mots</span>
                <span className="text-white">{entry.word_count}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-muted">Cout IA</span>
                <span className="text-white">${(entry.generation_cost_cents / 100).toFixed(2)}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-muted">Cree le</span>
                <span className="text-white">{new Date(entry.created_at).toLocaleDateString('fr-FR')}</span>
              </div>
              {entry.published_at && (
                <div className="flex justify-between text-sm">
                  <span className="text-muted">Publie le</span>
                  <span className="text-white">{new Date(entry.published_at).toLocaleDateString('fr-FR')}</span>
                </div>
              )}
            </div>
          </div>

          {/* Translations */}
          {entry.translations && entry.translations.length > 0 && (
            <div className="bg-surface border border-border rounded-xl p-5">
              <h3 className="font-title font-semibold text-white mb-3">Traductions</h3>
              <div className="space-y-2">
                {entry.translations.map(t => (
                  <button
                    key={t.id}
                    onClick={() => navigate(`/content/qa/${t.id}`)}
                    className="w-full flex items-center justify-between px-3 py-2 rounded-lg bg-surface2/50 hover:bg-surface2 transition-colors text-sm"
                  >
                    <span className="text-white uppercase">{t.language}</span>
                    <span className={`px-1.5 py-0.5 rounded text-[10px] font-medium ${STATUS_COLORS[t.status]}`}>
                      {STATUS_LABELS[t.status]}
                    </span>
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Hreflang */}
          {entry.hreflang_map && Object.keys(entry.hreflang_map).length > 0 && (
            <div className="bg-surface border border-border rounded-xl p-5">
              <h3 className="font-title font-semibold text-white mb-3">Hreflang</h3>
              <div className="space-y-1">
                {Object.entries(entry.hreflang_map).map(([lang, url]) => (
                  <div key={lang} className="flex justify-between text-xs">
                    <span className="text-muted uppercase">{lang}</span>
                    <a href={url} target="_blank" rel="noopener noreferrer" className="text-violet hover:text-violet-light truncate max-w-[150px]">{url}</a>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
