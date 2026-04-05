import React, { useEffect, useState, useCallback, useRef } from 'react';
import api from '../../api/client';
import { generateArticle, fetchArticles } from '../../api/contentApi';
import type { GenerateArticleParams, GeneratedArticle } from '../../types/content';
import { toast } from '../../components/Toast';

interface KeywordItem {
  id: number;
  keyword: string;
  cluster: string;
  sub_cluster: string;
  secondary: string;
  intent: string;
  status: 'pending' | 'generating' | 'published' | 'failed';
  article_id?: number;
}

const STATUS_STYLES: Record<string, { bg: string; text: string; label: string }> = {
  pending:    { bg: 'bg-muted/10', text: 'text-muted', label: 'En attente' },
  generating: { bg: 'bg-amber/10', text: 'text-amber', label: 'En cours' },
  published:  { bg: 'bg-success/10', text: 'text-success', label: 'Genere' },
  failed:     { bg: 'bg-danger/10', text: 'text-danger', label: 'Echec' },
};

const INTENT_STYLES: Record<string, string> = {
  transactionnel: 'bg-violet/10 text-violet-light',
  urgence: 'bg-danger/10 text-danger',
  informationnel: 'bg-cyan/10 text-cyan',
};

const TABS = ['items', 'import', 'generer'] as const;
type Tab = typeof TABS[number];

export default function ArtMotsCles() {
  const [tab, setTab] = useState<Tab>('items');
  const [keywords, setKeywords] = useState<KeywordItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [filterCluster, setFilterCluster] = useState('');
  const [filterIntent, setFilterIntent] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [searchQ, setSearchQ] = useState('');
  const [generating, setGenerating] = useState<number | null>(null);
  const [batchGenerating, setBatchGenerating] = useState(false);
  const [csvInput, setCsvInput] = useState('');
  const [bulkInput, setBulkInput] = useState('');
  const fileRef = useRef<HTMLInputElement>(null);

  // Load keywords from localStorage (until backend API is ready)
  const loadKeywords = useCallback(() => {
    const stored = localStorage.getItem('sos_art_mots_cles');
    if (stored) {
      try { setKeywords(JSON.parse(stored)); } catch { setKeywords([]); }
    }
  }, []);

  useEffect(() => { loadKeywords(); }, [loadKeywords]);

  const saveKeywords = (kws: KeywordItem[]) => {
    setKeywords(kws);
    localStorage.setItem('sos_art_mots_cles', JSON.stringify(kws));
  };

  // Import CSV
  const handleCsvImport = (text: string) => {
    const lines = text.split('\n').filter(l => l.trim());
    if (lines.length < 2) { toast.error('CSV vide ou invalide'); return; }

    const existing = new Set(keywords.map(k => k.keyword));
    let added = 0;
    const newKws = [...keywords];

    for (let i = 1; i < lines.length; i++) {
      const cols = lines[i].split(',');
      if (cols.length < 4) continue;

      const keyword = (cols[3] || '').trim().replace(/^"|"$/g, '');
      if (!keyword || existing.has(keyword)) continue;

      newKws.push({
        id: Date.now() + i,
        keyword,
        cluster: (cols[1] || '').trim().replace(/^"|"$/g, ''),
        sub_cluster: (cols[2] || '').trim().replace(/^"|"$/g, ''),
        secondary: (cols[4] || '').trim().replace(/^"|"$/g, ''),
        intent: (cols[6] || 'transactionnel').trim().replace(/^"|"$/g, ''),
        status: 'pending',
      });
      existing.add(keyword);
      added++;
    }

    saveKeywords(newKws);
    toast.success(`${added} mots-cles importes (${newKws.length} total)`);
    setCsvInput('');
  };

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => {
      handleCsvImport(ev.target?.result as string);
    };
    reader.readAsText(file, 'utf-8');
  };

  // Bulk add (one keyword per line)
  const handleBulkAdd = () => {
    const lines = bulkInput.split('\n').map(l => l.trim()).filter(Boolean);
    if (lines.length === 0) return;

    const existing = new Set(keywords.map(k => k.keyword));
    let added = 0;
    const newKws = [...keywords];

    for (const line of lines) {
      if (existing.has(line)) continue;
      newKws.push({
        id: Date.now() + added,
        keyword: line,
        cluster: '',
        sub_cluster: '',
        secondary: '',
        intent: 'transactionnel',
        status: 'pending',
      });
      existing.add(line);
      added++;
    }

    saveKeywords(newKws);
    toast.success(`${added} mots-cles ajoutes`);
    setBulkInput('');
  };

  // Generate one
  const handleGenerateOne = async (kw: KeywordItem) => {
    setGenerating(kw.id);
    const updated = keywords.map(k => k.id === kw.id ? { ...k, status: 'generating' as const } : k);
    saveKeywords(updated);

    try {
      const params: GenerateArticleParams = {
        topic: kw.keyword,
        language: 'fr',
        content_type: 'article',
        tone: 'professional',
        length: 'medium',
        generate_faq: true,
        research_sources: true,
        auto_internal_links: true,
        auto_affiliate_links: true,
        keywords: kw.secondary ? kw.secondary.split('|').filter(Boolean) : [kw.keyword],
      };
      await generateArticle(params);
      const done = keywords.map(k => k.id === kw.id ? { ...k, status: 'published' as const } : k);
      saveKeywords(done);
      toast.success(`Generation lancee: ${kw.keyword}`);
    } catch (e: any) {
      const failed = keywords.map(k => k.id === kw.id ? { ...k, status: 'failed' as const } : k);
      saveKeywords(failed);
      toast.error(e?.response?.data?.message || 'Erreur generation');
    } finally {
      setGenerating(null);
    }
  };

  // Generate batch
  const handleBatchGenerate = async (limit: number) => {
    const pending = filtered.filter(k => k.status === 'pending');
    if (pending.length === 0) { toast.error('Aucun mot-cle en attente'); return; }
    if (!confirm(`Generer ${Math.min(pending.length, limit)} articles ?`)) return;

    setBatchGenerating(true);
    let count = 0;
    for (const kw of pending.slice(0, limit)) {
      await handleGenerateOne(kw);
      count++;
      await new Promise(r => setTimeout(r, 2000));
    }
    setBatchGenerating(false);
    toast.success(`${count} articles lances`);
  };

  // Delete
  const handleDelete = (id: number) => {
    saveKeywords(keywords.filter(k => k.id !== id));
  };

  // Clear all
  const handleClearAll = () => {
    if (!confirm('Supprimer TOUS les mots-cles ?')) return;
    saveKeywords([]);
  };

  // Filters
  const clusters = [...new Set(keywords.map(k => k.cluster).filter(Boolean))].sort();
  const filtered = keywords.filter(k => {
    if (filterCluster && k.cluster !== filterCluster) return false;
    if (filterIntent && k.intent !== filterIntent) return false;
    if (filterStatus && k.status !== filterStatus) return false;
    if (searchQ && !k.keyword.toLowerCase().includes(searchQ.toLowerCase())) return false;
    return true;
  });

  const pendingCount = keywords.filter(k => k.status === 'pending').length;
  const generatedCount = keywords.filter(k => k.status === 'published').length;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-title font-bold text-white tracking-tight flex items-center gap-3">
            <span className="text-3xl">🔑</span>
            Art Mots Cles
          </h1>
          <p className="text-sm text-muted mt-1">Generation d'articles a partir de mots-cles SEO</p>
        </div>
        <div className="flex gap-2">
          <button onClick={loadKeywords} className="text-xs text-muted hover:text-white px-3 py-1.5 bg-surface2/50 rounded-lg transition-colors">
            Rafraichir
          </button>
          {keywords.length > 0 && (
            <button onClick={handleClearAll} className="text-xs text-danger/60 hover:text-danger px-3 py-1.5 bg-surface2/50 rounded-lg transition-colors">
              Tout supprimer
            </button>
          )}
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-4 gap-3">
        {[
          { label: 'Total', value: keywords.length, color: 'text-white' },
          { label: 'En attente', value: pendingCount, color: 'text-muted' },
          { label: 'Generes', value: generatedCount, color: 'text-success' },
          { label: 'Clusters', value: clusters.length, color: 'text-violet-light' },
        ].map(s => (
          <div key={s.label} className="bg-surface/60 backdrop-blur border border-border/30 rounded-xl p-3 text-center">
            <p className={`text-2xl font-bold ${s.color}`}>{s.value}</p>
            <p className="text-[10px] text-muted uppercase tracking-wider">{s.label}</p>
          </div>
        ))}
      </div>

      {/* Tabs */}
      <div className="flex gap-1 bg-surface/40 backdrop-blur rounded-xl p-1 border border-border/20">
        {([['items', '📦', 'Mots-cles'], ['import', '📥', 'Importer'], ['generer', '⚡', 'Generer']] as [Tab, string, string][]).map(([t, emoji, label]) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition-all ${
              tab === t ? 'bg-violet/20 text-violet-light border border-violet/30' : 'text-muted hover:text-white'
            }`}
          >
            <span>{emoji}</span> {label}
          </button>
        ))}
      </div>

      {/* Tab: Items */}
      {tab === 'items' && (
        <div className="space-y-4">
          {/* Filters */}
          <div className="flex gap-2 flex-wrap">
            <input
              type="text" value={searchQ} onChange={e => setSearchQ(e.target.value)}
              placeholder="Rechercher..."
              className="bg-bg/60 border border-border/40 rounded-xl px-3 py-2 text-white text-sm w-48 focus:outline-none focus:border-violet/50"
            />
            <select value={filterCluster} onChange={e => setFilterCluster(e.target.value)}
              className="bg-bg/60 border border-border/40 rounded-xl px-3 py-2 text-white text-sm">
              <option value="">Tous clusters</option>
              {clusters.map(c => <option key={c} value={c}>{c.replace(/^\d+\.\s*/, '')}</option>)}
            </select>
            <select value={filterIntent} onChange={e => setFilterIntent(e.target.value)}
              className="bg-bg/60 border border-border/40 rounded-xl px-3 py-2 text-white text-sm">
              <option value="">Toutes intentions</option>
              <option value="transactionnel">Transactionnel</option>
              <option value="urgence">Urgence</option>
              <option value="informationnel">Informationnel</option>
            </select>
            <select value={filterStatus} onChange={e => setFilterStatus(e.target.value)}
              className="bg-bg/60 border border-border/40 rounded-xl px-3 py-2 text-white text-sm">
              <option value="">Tous status</option>
              <option value="pending">En attente</option>
              <option value="published">Genere</option>
              <option value="failed">Echec</option>
            </select>
          </div>

          <p className="text-xs text-muted">{filtered.length} mots-cles affiches</p>

          {/* List */}
          <div className="bg-surface/40 backdrop-blur border border-border/20 rounded-2xl overflow-hidden">
            {filtered.length > 0 ? (
              <div className="divide-y divide-border/10 max-h-[600px] overflow-y-auto">
                {filtered.map(kw => {
                  const st = STATUS_STYLES[kw.status] || STATUS_STYLES.pending;
                  return (
                    <div key={kw.id} className="flex items-center gap-3 px-5 py-2.5 hover:bg-surface2/20 transition-colors group">
                      <div className="flex-1 min-w-0">
                        <p className="text-sm text-white truncate">{kw.keyword}</p>
                        {kw.cluster && <p className="text-[10px] text-muted truncate">{kw.cluster.replace(/^\d+\.\s*/, '')} &gt; {kw.sub_cluster}</p>}
                      </div>
                      {kw.intent && (
                        <span className={`shrink-0 px-2 py-0.5 rounded text-[9px] font-medium uppercase ${INTENT_STYLES[kw.intent] || ''}`}>
                          {kw.intent.slice(0, 5)}
                        </span>
                      )}
                      <span className={`shrink-0 px-2.5 py-1 rounded-lg text-[10px] font-semibold ${st.bg} ${st.text} ${kw.status === 'generating' ? 'animate-pulse' : ''}`}>
                        {st.label}
                      </span>
                      <div className="shrink-0 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        {kw.status === 'pending' && (
                          <button onClick={() => handleGenerateOne(kw)} disabled={generating === kw.id}
                            className="px-2 py-1 text-[10px] bg-violet/20 text-violet-light rounded-lg hover:bg-violet/30">
                            Generer
                          </button>
                        )}
                        <button onClick={() => handleDelete(kw.id)}
                          className="px-2 py-1 text-[10px] text-danger/60 hover:text-danger">x</button>
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : (
              <div className="px-5 py-12 text-center">
                <p className="text-3xl mb-2">🔑</p>
                <p className="text-sm text-muted">Aucun mot-cle. Allez dans l'onglet "Importer" pour charger vos mots-cles.</p>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Tab: Import */}
      {tab === 'import' && (
        <div className="space-y-4">
          {/* CSV file upload */}
          <div className="bg-gradient-to-br from-violet/20 to-violet/5 border border-border/30 rounded-2xl p-6">
            <h3 className="text-sm font-bold text-white mb-2">📥 Importer un fichier CSV</h3>
            <p className="text-xs text-muted mb-4">Format attendu : #, Cluster, Sous-cluster, Mot-cle, Secondaires, Nb KW, Intention</p>
            <input ref={fileRef} type="file" accept=".csv" onChange={handleFileUpload} className="hidden" />
            <button onClick={() => fileRef.current?.click()}
              className="px-5 py-2.5 bg-gradient-to-r from-violet to-violet-light text-white text-sm font-semibold rounded-xl shadow-lg shadow-violet/20 hover:shadow-violet/40 transition-all">
              Choisir un fichier CSV
            </button>
          </div>

          {/* Paste CSV */}
          <div className="bg-surface/40 border border-border/20 rounded-2xl p-6">
            <h3 className="text-sm font-bold text-white mb-2">📋 Coller un CSV</h3>
            <textarea value={csvInput} onChange={e => setCsvInput(e.target.value)} rows={8}
              placeholder="#,Cluster,Sous-cluster,Mot cle,Secondaires,Nb,Intention&#10;1,Expatries,Visa,visa travail allemagne,,1,informationnel"
              className="w-full bg-bg/60 border border-border/40 rounded-xl px-4 py-3 text-white text-sm font-mono focus:outline-none focus:border-violet/50 transition-all resize-none" />
            {csvInput && (
              <button onClick={() => handleCsvImport(csvInput)}
                className="mt-3 px-4 py-2 bg-violet/20 text-violet-light text-sm rounded-xl border border-violet/20 hover:bg-violet/30">
                Importer {csvInput.split('\n').filter(l => l.trim()).length - 1} lignes
              </button>
            )}
          </div>

          {/* Bulk text */}
          <div className="bg-surface/40 border border-border/20 rounded-2xl p-6">
            <h3 className="text-sm font-bold text-white mb-2">✏️ Ajouter en vrac (un mot-cle par ligne)</h3>
            <textarea value={bulkInput} onChange={e => setBulkInput(e.target.value)} rows={6}
              placeholder="visa travail allemagne&#10;cout de la vie portugal&#10;avocat francophone barcelone"
              className="w-full bg-bg/60 border border-border/40 rounded-xl px-4 py-3 text-white text-sm font-mono focus:outline-none focus:border-violet/50 transition-all resize-none" />
            {bulkInput && (
              <button onClick={handleBulkAdd}
                className="mt-3 px-4 py-2 bg-violet/20 text-violet-light text-sm rounded-xl border border-violet/20 hover:bg-violet/30">
                Ajouter {bulkInput.split('\n').filter(l => l.trim()).length} mots-cles
              </button>
            )}
          </div>
        </div>
      )}

      {/* Tab: Generer */}
      {tab === 'generer' && (
        <div className="space-y-4">
          {pendingCount > 0 ? (
            <div className="bg-gradient-to-br from-violet/20 to-violet/5 border border-border/30 rounded-2xl p-6">
              <h3 className="text-sm font-bold text-white mb-2">⚡ Generation par lot</h3>
              <p className="text-xs text-muted mb-4">{pendingCount} mots-cles en attente de generation.</p>
              <div className="flex gap-3">
                <button onClick={() => handleBatchGenerate(5)} disabled={batchGenerating}
                  className="px-4 py-2 bg-violet/20 text-violet-light text-sm font-medium rounded-xl border border-violet/20 hover:bg-violet/30 disabled:opacity-50">
                  {batchGenerating ? 'En cours...' : 'Generer 5'}
                </button>
                <button onClick={() => handleBatchGenerate(20)} disabled={batchGenerating}
                  className="px-4 py-2 bg-violet/20 text-violet-light text-sm font-medium rounded-xl border border-violet/20 hover:bg-violet/30 disabled:opacity-50">
                  Generer 20
                </button>
                <button onClick={() => handleBatchGenerate(pendingCount)} disabled={batchGenerating}
                  className="px-4 py-2 bg-amber/20 text-amber text-sm font-medium rounded-xl border border-amber/20 hover:bg-amber/30 disabled:opacity-50">
                  Generer tout ({pendingCount})
                </button>
              </div>
            </div>
          ) : (
            <div className="bg-surface/40 border border-border/20 rounded-2xl p-6 text-center">
              <p className="text-3xl mb-2">✅</p>
              <p className="text-sm text-muted">
                {keywords.length === 0
                  ? 'Importez des mots-cles dans l\'onglet "Importer" pour commencer.'
                  : 'Tous les mots-cles ont ete generes !'}
              </p>
            </div>
          )}

          <div className="bg-surface/40 border border-border/20 rounded-2xl p-6">
            <h3 className="text-sm font-bold text-white mb-3">ℹ️ Comment ca marche</h3>
            <ul className="text-xs text-muted space-y-2">
              <li>1. Importez vos mots-cles (CSV ou copier-coller)</li>
              <li>2. Chaque mot-cle devient le sujet d'un article</li>
              <li>3. L'IA genere un article complet (15 phases : recherche, contenu, SEO, FAQ, images)</li>
              <li>4. L'article est publie sur sos-expat.com et traduit en 9 langues</li>
              <li>5. Les mots-cles secondaires sont utilises pour le SEO on-page</li>
            </ul>
          </div>
        </div>
      )}
    </div>
  );
}
