import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  fetchTemplate, addTemplateItems, generateTemplateItems,
  expandTemplate, skipTemplateItem, resetTemplateItem,
  type ContentTemplate, type ContentTemplateItem,
} from '../../api/contentApi';
import { toast } from '../../components/Toast';

const STATUS_STYLES: Record<string, { bg: string; text: string; label: string }> = {
  pending:     { bg: 'bg-muted/10', text: 'text-muted', label: 'En attente' },
  optimizing:  { bg: 'bg-blue-500/10', text: 'text-blue-400', label: 'Optimisation...' },
  generating:  { bg: 'bg-amber/10', text: 'text-amber', label: 'Generation...' },
  published:   { bg: 'bg-success/10', text: 'text-success', label: 'Publie' },
  failed:      { bg: 'bg-danger/10', text: 'text-danger', label: 'Echec' },
  skipped:     { bg: 'bg-muted/10', text: 'text-muted/60', label: 'Ignore' },
};

export default function ContentTemplateDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [template, setTemplate] = useState<ContentTemplate | null>(null);
  const [loading, setLoading] = useState(true);
  const [newKeyword, setNewKeyword] = useState('');
  const [bulkInput, setBulkInput] = useState('');
  const [showBulk, setShowBulk] = useState(false);
  const [generating, setGenerating] = useState(false);
  const [expandLoading, setExpandLoading] = useState(false);
  const [filterStatus, setFilterStatus] = useState('');

  const loadTemplate = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      const res = await fetchTemplate(Number(id));
      setTemplate(res.data as unknown as ContentTemplate);
    } catch {
      toast.error('Template introuvable');
      navigate('/content/templates');
    } finally {
      setLoading(false);
    }
  }, [id, navigate]);

  useEffect(() => { loadTemplate(); }, [loadTemplate]);

  const handleAddKeyword = async () => {
    if (!template || !newKeyword.trim()) return;
    try {
      await addTemplateItems(template.id, [newKeyword.trim()]);
      setNewKeyword('');
      toast.success('Mot-cle ajoute');
      loadTemplate();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erreur');
    }
  };

  const handleBulkAdd = async () => {
    if (!template) return;
    const lines = bulkInput.split('\n').map(l => l.trim()).filter(Boolean);
    if (lines.length === 0) return;
    try {
      const res = await addTemplateItems(template.id, lines);
      setBulkInput('');
      setShowBulk(false);
      toast.success((res.data as any).message);
      loadTemplate();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erreur');
    }
  };

  const handleExpand = async () => {
    if (!template) return;
    setExpandLoading(true);
    try {
      const res = await expandTemplate(template.id);
      toast.success((res.data as any).message);
      loadTemplate();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erreur expansion');
    } finally {
      setExpandLoading(false);
    }
  };

  const handleGenerate = async (limit?: number, itemIds?: number[]) => {
    if (!template) return;
    setGenerating(true);
    try {
      const res = await generateTemplateItems(template.id, limit, itemIds);
      toast.success((res.data as any).message);
      loadTemplate();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erreur generation');
    } finally {
      setGenerating(false);
    }
  };

  const handleSkip = async (itemId: number) => {
    try {
      await skipTemplateItem(itemId);
      loadTemplate();
    } catch { toast.error('Erreur'); }
  };

  const handleReset = async (itemId: number) => {
    try {
      await resetTemplateItem(itemId);
      loadTemplate();
    } catch { toast.error('Erreur'); }
  };

  if (loading || !template) {
    return (
      <div className="flex items-center justify-center h-64 text-muted">
        <div className="w-5 h-5 border-2 border-violet/30 border-t-violet rounded-full animate-spin mr-3" />
        Chargement...
      </div>
    );
  }

  const items = (template.items ?? []).filter(i => !filterStatus || i.status === filterStatus);
  const pendingCount = (template.items ?? []).filter(i => i.status === 'pending').length;
  const generatingCount = (template.items ?? []).filter(i => i.status === 'generating').length;
  const publishedCount = (template.items ?? []).filter(i => i.status === 'published').length;
  const failedCount = (template.items ?? []).filter(i => i.status === 'failed').length;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <button onClick={() => navigate('/content/templates')} className="text-muted hover:text-white transition-colors">
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
        </button>
        <div className="flex-1">
          <h1 className="text-2xl font-title font-bold text-white">{template.name}</h1>
          <p className="text-sm font-mono text-muted/60 mt-0.5">{template.title_template}</p>
        </div>
        <button onClick={loadTemplate} className="text-xs text-muted hover:text-white px-3 py-1.5 bg-surface2/50 rounded-lg transition-colors">
          Rafraichir
        </button>
      </div>

      {/* Stats bar */}
      <div className="grid grid-cols-5 gap-3">
        {[
          { label: 'Total', value: template.items?.length ?? 0, color: 'text-white' },
          { label: 'En attente', value: pendingCount, color: 'text-muted' },
          { label: 'En cours', value: generatingCount, color: 'text-amber' },
          { label: 'Publies', value: publishedCount, color: 'text-success' },
          { label: 'Echecs', value: failedCount, color: 'text-danger' },
        ].map(s => (
          <div key={s.label} className="bg-surface/60 backdrop-blur border border-border/30 rounded-xl p-3 text-center">
            <p className={`text-2xl font-bold ${s.color}`}>{s.value}</p>
            <p className="text-[10px] text-muted uppercase tracking-wider">{s.label}</p>
          </div>
        ))}
      </div>

      {/* Actions bar */}
      <div className="flex flex-wrap items-center gap-3">
        {/* Add keywords (manual mode) */}
        {template.expansion_mode === 'manual' || template.expansion_mode === 'custom_list' ? (
          <div className="flex gap-2 flex-1 min-w-[300px]">
            <input
              type="text"
              value={newKeyword}
              onChange={e => setNewKeyword(e.target.value)}
              onKeyDown={e => e.key === 'Enter' && (e.preventDefault(), handleAddKeyword())}
              placeholder="Ajouter un mot-cle..."
              className="flex-1 bg-bg/60 border border-border/40 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-violet/50 focus:ring-1 focus:ring-violet/20 backdrop-blur transition-all"
            />
            <button onClick={handleAddKeyword} className="px-4 py-2.5 bg-violet/20 text-violet-light text-sm font-medium rounded-xl border border-violet/20 hover:bg-violet/30 transition-all">
              Ajouter
            </button>
            <button onClick={() => setShowBulk(!showBulk)} className="px-3 py-2.5 bg-surface2/50 text-muted text-sm rounded-xl border border-border/30 hover:text-white transition-all">
              Bulk
            </button>
          </div>
        ) : (
          <button
            onClick={handleExpand}
            disabled={expandLoading}
            className="px-4 py-2.5 bg-cyan/20 text-cyan text-sm font-medium rounded-xl border border-cyan/20 hover:bg-cyan/30 transition-all disabled:opacity-50"
          >
            {expandLoading ? 'Expansion...' : `Expander (${template.expansion_mode === 'all_countries' ? '197 pays' : 'selection'})`}
          </button>
        )}

        {/* Generate button */}
        {pendingCount > 0 && (
          <button
            onClick={() => handleGenerate(10)}
            disabled={generating}
            className="px-5 py-2.5 bg-gradient-to-r from-violet to-violet-light text-white text-sm font-semibold rounded-xl shadow-lg shadow-violet/20 hover:shadow-violet/40 transition-all disabled:opacity-50 hover:scale-[1.02] active:scale-[0.98]"
          >
            {generating ? (
              <span className="flex items-center gap-2">
                <div className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                Generation...
              </span>
            ) : (
              `Generer ${Math.min(pendingCount, 10)} articles`
            )}
          </button>
        )}
      </div>

      {/* Bulk input */}
      {showBulk && (
        <div className="bg-surface/60 backdrop-blur border border-border/30 rounded-2xl p-5 space-y-3">
          <p className="text-xs text-muted">Un mot-cle par ligne :</p>
          <textarea
            value={bulkInput}
            onChange={e => setBulkInput(e.target.value)}
            rows={8}
            className="w-full bg-bg/60 border border-border/40 rounded-xl px-4 py-3 text-white text-sm font-mono focus:outline-none focus:border-violet/50 transition-all resize-none"
            placeholder={"visa travail Allemagne\ncout de la vie Portugal\nassurance expatrie Espagne"}
          />
          <div className="flex justify-end gap-3">
            <button onClick={() => setShowBulk(false)} className="text-sm text-muted hover:text-white">Annuler</button>
            <button onClick={handleBulkAdd} className="px-5 py-2 bg-violet/20 text-violet-light text-sm font-medium rounded-xl border border-violet/20 hover:bg-violet/30 transition-all">
              Ajouter {bulkInput.split('\n').filter(l => l.trim()).length} mots-cles
            </button>
          </div>
        </div>
      )}

      {/* Status filter */}
      <div className="flex gap-1.5">
        {[
          { key: '', label: 'Tous' },
          { key: 'pending', label: 'En attente' },
          { key: 'generating', label: 'En cours' },
          { key: 'published', label: 'Publies' },
          { key: 'failed', label: 'Echecs' },
          { key: 'skipped', label: 'Ignores' },
        ].map(f => (
          <button
            key={f.key}
            onClick={() => setFilterStatus(f.key)}
            className={`px-3 py-1.5 rounded-lg text-[11px] font-medium transition-all ${
              filterStatus === f.key ? 'bg-violet/20 text-violet-light' : 'bg-surface2/30 text-muted hover:text-white'
            }`}
          >
            {f.label}
          </button>
        ))}
      </div>

      {/* Items list */}
      <div className="bg-surface/40 backdrop-blur border border-border/20 rounded-2xl overflow-hidden">
        {items.length > 0 ? (
          <div className="divide-y divide-border/10">
            {items.map(item => {
              const st = STATUS_STYLES[item.status] || STATUS_STYLES.pending;
              return (
                <div key={item.id} className="flex items-center gap-3 px-5 py-3 hover:bg-surface2/20 transition-colors group">
                  {/* Country flag */}
                  {item.variable_values?.pays_code && (
                    <img
                      src={`/images/flags/${(item.variable_values.pays_code || '').toLowerCase()}.webp`}
                      alt=""
                      className="w-5 h-3.5 object-cover rounded-sm shrink-0"
                      onError={e => { (e.target as HTMLImageElement).style.display = 'none'; }}
                    />
                  )}

                  {/* Title */}
                  <div className="flex-1 min-w-0">
                    <p className="text-sm text-white truncate">
                      {item.optimized_title || item.expanded_title}
                    </p>
                    {item.error_message && (
                      <p className="text-[10px] text-danger/70 truncate mt-0.5">{item.error_message}</p>
                    )}
                  </div>

                  {/* Status badge */}
                  <span className={`shrink-0 px-2.5 py-1 rounded-lg text-[10px] font-semibold uppercase tracking-wider ${st.bg} ${st.text} ${item.status === 'generating' ? 'animate-pulse' : ''}`}>
                    {st.label}
                  </span>

                  {/* Actions */}
                  <div className="shrink-0 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    {item.status === 'pending' && (
                      <>
                        <button
                          onClick={() => handleGenerate(1, [item.id])}
                          className="px-2.5 py-1 text-[10px] bg-violet/20 text-violet-light rounded-lg hover:bg-violet/30 transition-all"
                        >
                          Generer
                        </button>
                        <button
                          onClick={() => handleSkip(item.id)}
                          className="px-2 py-1 text-[10px] text-muted hover:text-danger transition-colors"
                        >
                          Skip
                        </button>
                      </>
                    )}
                    {item.status === 'failed' && (
                      <button
                        onClick={() => handleReset(item.id)}
                        className="px-2.5 py-1 text-[10px] bg-amber/20 text-amber rounded-lg hover:bg-amber/30 transition-all"
                      >
                        Retry
                      </button>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        ) : (
          <div className="px-5 py-12 text-center">
            <p className="text-3xl mb-2">🔍</p>
            <p className="text-sm text-muted">
              {template.items?.length === 0
                ? template.expansion_mode === 'manual'
                  ? 'Ajoutez des mots-cles pour commencer'
                  : 'Cliquez "Expander" pour generer les items'
                : 'Aucun item pour ce filtre'
              }
            </p>
          </div>
        )}
      </div>
    </div>
  );
}
