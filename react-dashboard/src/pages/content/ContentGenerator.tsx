import React, { useEffect, useState, useCallback } from 'react';
import api from '../../api/client';
import { generateArticle } from '../../api/contentApi';
import type { GenerateArticleParams } from '../../types/content';
import { toast } from '../../components/Toast';

// ─── TYPES ───────────────────────────────────────────────

interface SourceItem {
  id: number;
  source_type: string;
  source_id: number | null;
  title: string;
  country: string | null;
  country_slug: string | null;
  theme: string | null;
  sub_category: string | null;
  language: string;
  word_count: number;
  quality_score: number;
  is_cleaned: boolean;
  processing_status: string;
  used_count: number;
  input_quality: string;
}

interface CategoryStats {
  slug: string;
  total: number;
  ready: number;
  processing: number;
  used: number;
  daily_quota: number;
  is_paused: boolean;
  weight_percent: number;
}

// ─── CONFIG PER TYPE ─────────────────────────────────────

const TYPE_CONFIG: Record<string, {
  emoji: string;
  title: string;
  slug: string;
  description: string;
  gradient: string;
  contentType: string;
  autoExpand: boolean;
  titleTemplate: string;
  instructions: string;
}> = {
  chatters: {
    emoji: '💬', title: 'Chatters', slug: 'chatters',
    description: 'Articles pour attirer des chatters sur la plateforme SOS-Expat',
    gradient: 'from-violet/20 to-violet/5',
    contentType: 'outreach',
    autoExpand: true,
    titleTemplate: 'Devenir Chatter SOS-Expat en {pays} : missions, revenus et avantages',
    instructions: 'Article de recrutement chatter. Avantages: revenus complementaires depuis chez soi, flexibilite horaire, communaute internationale. Missions: partager SOS-Expat sur les reseaux sociaux, repondre aux questions. CTA vers inscription chatter.',
  },
  influenceurs: {
    emoji: '📢', title: 'Influenceurs', slug: 'bloggeurs',
    description: 'Articles pour attirer des influenceurs et blogueurs affilies',
    gradient: 'from-cyan/20 to-cyan/5',
    contentType: 'outreach',
    autoExpand: true,
    titleTemplate: 'Devenir Influenceur SOS-Expat en {pays} : monetisez votre audience',
    instructions: 'Article pour attirer des influenceurs/blogueurs. Avantages: commissions par appel, widget integrable, audience expat. Programmes: $10/appel client, $5/appel recrute. CTA vers inscription blogueur.',
  },
  'admin-groupes': {
    emoji: '👥', title: 'Admin Groupes', slug: 'admin-groups',
    description: 'Articles pour attirer des admins de groupes WhatsApp/Telegram',
    gradient: 'from-amber/20 to-amber/5',
    contentType: 'outreach',
    autoExpand: true,
    titleTemplate: 'Devenir Admin Groupe SOS-Expat en {pays} : gerez et monetisez votre communaute',
    instructions: 'Article pour attirer des admins de groupes WhatsApp/Telegram/Facebook. Avantages: monetiser sa communaute, recruter des prestataires et clients. CTA vers inscription admin groupe.',
  },
  avocats: {
    emoji: '⚖️', title: 'Avocats', slug: 'avocats',
    description: 'Articles pour attirer des avocats prestataires sur la plateforme',
    gradient: 'from-success/20 to-success/5',
    contentType: 'outreach',
    autoExpand: true,
    titleTemplate: 'Avocat en {pays} : rejoignez SOS-Expat et developez votre clientele internationale',
    instructions: 'Article pour attirer des avocats prestataires. Avantages: clientele internationale, appels remuneres, flexibilite, aucun engagement. Expertise: droit immigration, fiscalite, travail, immobilier. CTA vers inscription prestataire avocat.',
  },
  'expats-aidants': {
    emoji: '🧳', title: 'Expats Aidants', slug: 'expats-aidants',
    description: 'Articles pour attirer des expatries aidants sur la plateforme',
    gradient: 'from-danger/20 to-danger/5',
    contentType: 'outreach',
    autoExpand: true,
    titleTemplate: 'Expatrie en {pays} ? Aidez d\'autres expatries et gagnez un revenu complementaire',
    instructions: 'Article pour attirer des expatries experimentes comme aidants. Avantages: partager son experience, revenu complementaire, flexibilite, aider la communaute. Missions: assistance telephonique, conseils pratiques. CTA vers inscription expat aidant.',
  },
  testimonial: {
    emoji: '💬', title: 'Temoignages', slug: 'temoignages',
    description: 'Temoignages d\'expatries par pays — recits personnels et conseils vecus',
    gradient: 'from-pink/20 to-pink/5',
    contentType: 'testimonial',
    autoExpand: true,
    titleTemplate: 'Temoignage expatrie en {pays} : mon experience et mes conseils',
    instructions: 'Temoignage simule d\'un expatrie installe dans le pays. Style narratif a la premiere personne. Inclure: contexte de depart, defis rencontres, bonnes surprises, conseils concrets. NE PAS inventer de donnees chiffrees precises. Ton personnel et authentique. CTA vers SOS-Expat.com pour une aide personnalisee.',
  },
};

const STATUS_STYLES: Record<string, { bg: string; text: string; label: string }> = {
  raw:        { bg: 'bg-muted/10', text: 'text-muted/60', label: 'Brut' },
  ready:      { bg: 'bg-blue-500/10', text: 'text-blue-400', label: 'Pret' },
  processing: { bg: 'bg-amber/10', text: 'text-amber', label: 'En cours' },
  used:       { bg: 'bg-success/10', text: 'text-success', label: 'Utilise' },
  skipped:    { bg: 'bg-muted/10', text: 'text-muted/40', label: 'Ignore' },
};

const TABS = ['sources', 'items', 'generer'] as const;
type Tab = typeof TABS[number];
const TAB_LABELS: Record<Tab, { emoji: string; label: string }> = {
  sources: { emoji: '📋', label: 'Sources' },
  items:   { emoji: '📦', label: 'Items' },
  generer: { emoji: '⚡', label: 'Generer' },
};

// ─── COMPONENT ───────────────────────────────────────────

interface Props {
  type: keyof typeof TYPE_CONFIG;
}

export default function ContentGenerator({ type }: Props) {
  const config = TYPE_CONFIG[type];
  const [tab, setTab] = useState<Tab>('items');
  const [items, setItems] = useState<SourceItem[]>([]);
  const [stats, setStats] = useState<CategoryStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState<number | null>(null);
  const [batchGenerating, setBatchGenerating] = useState(false);
  const [filterStatus, setFilterStatus] = useState('');
  const [newTitle, setNewTitle] = useState('');
  const [bulkInput, setBulkInput] = useState('');
  const [showBulk, setShowBulk] = useState(false);

  // Load data
  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [itemsRes, statsRes] = await Promise.all([
        api.get(`/generation-sources/${config.slug}/items`, {
          params: { per_page: 100, status: filterStatus || undefined },
        }),
        api.get('/generation-sources/categories'),
      ]);
      setItems((itemsRes.data as any).data ?? itemsRes.data ?? []);
      const cats = (statsRes.data as any) ?? [];
      const myCat = Array.isArray(cats) ? cats.find((c: any) => c.slug === config.slug) : null;
      setStats(myCat ?? null);
    } catch {
      toast.error('Erreur chargement');
    } finally {
      setLoading(false);
    }
  }, [config.slug, filterStatus]);

  useEffect(() => { loadData(); }, [loadData]);

  // Generate one item
  const handleGenerateOne = async (item: SourceItem) => {
    setGenerating(item.id);
    try {
      const params: GenerateArticleParams = {
        topic: item.title,
        language: item.language || 'fr',
        content_type: config.contentType as any,
        tone: 'professional',
        length: 'medium',
        generate_faq: true,
        research_sources: false,
        auto_internal_links: true,
        auto_affiliate_links: true,
        instructions: config.instructions,
      };
      if (item.country) params.country = item.country;
      await generateArticle(params);
      toast.success(`Generation lancee: ${item.title}`);
      loadData();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erreur');
    } finally {
      setGenerating(null);
    }
  };

  // Generate batch
  const handleGenerateBatch = async (limit = 10) => {
    const readyItems = items.filter(i => i.processing_status === 'ready');
    if (readyItems.length === 0) { toast.error('Aucun item pret'); return; }
    if (!confirm(`Generer ${Math.min(readyItems.length, limit)} articles ?`)) return;

    setBatchGenerating(true);
    let count = 0;
    for (const item of readyItems.slice(0, limit)) {
      try {
        const params: GenerateArticleParams = {
          topic: item.title,
          language: item.language || 'fr',
          content_type: config.contentType as any,
          tone: 'professional',
          length: 'medium',
          generate_faq: true,
          research_sources: false,
          auto_internal_links: true,
          auto_affiliate_links: true,
          instructions: config.instructions,
        };
        if (item.country) params.country = item.country;
        await generateArticle(params);
        count++;
        await new Promise(r => setTimeout(r, 2000));
      } catch { /* continue */ }
    }
    toast.success(`${count} articles lances`);
    setBatchGenerating(false);
    loadData();
  };

  // Add manual title
  const handleAddTitle = async () => {
    if (!newTitle.trim()) return;
    // For types without existing pipeline items, we generate directly
    try {
      const params: GenerateArticleParams = {
        topic: newTitle.trim(),
        language: 'fr',
        content_type: config.contentType as any,
        tone: 'professional',
        length: 'medium',
        generate_faq: true,
        research_sources: true,
        auto_internal_links: true,
        auto_affiliate_links: true,
        instructions: config.instructions,
      };
      await generateArticle(params);
      toast.success(`Generation lancee: ${newTitle.trim()}`);
      setNewTitle('');
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erreur');
    }
  };

  // Auto-expand {pays} × 197
  const handleAutoExpand = async () => {
    if (!confirm(`Generer automatiquement pour les 197 pays ? (cela creera ~197 articles)`)) return;
    toast.success('Expansion pays lancee — les items seront generes progressivement');
    // This would ideally call a backend endpoint that expands and queues
    try {
      await api.post(`/generation-sources/${config.slug}/trigger`);
      toast.success('Pipeline de generation declenche');
      loadData();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erreur trigger');
    }
  };

  const readyCount = items.filter(i => i.processing_status === 'ready').length;
  const usedCount = items.filter(i => i.processing_status === 'used').length;
  const totalCount = items.length;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-title font-bold text-white tracking-tight flex items-center gap-3">
            <span className="text-3xl">{config.emoji}</span>
            {config.title}
          </h1>
          <p className="text-sm text-muted mt-1">{config.description}</p>
        </div>
        <button onClick={loadData} className="text-xs text-muted hover:text-white px-3 py-1.5 bg-surface2/50 rounded-lg transition-colors">
          Rafraichir
        </button>
      </div>

      {/* Stats bar */}
      <div className="grid grid-cols-4 gap-3">
        {[
          { label: 'Total', value: totalCount, color: 'text-white' },
          { label: 'Prets', value: readyCount, color: 'text-blue-400' },
          { label: 'Generes', value: usedCount, color: 'text-success' },
          { label: 'Quota/jour', value: stats?.daily_quota ?? '-', color: 'text-amber' },
        ].map(s => (
          <div key={s.label} className="bg-surface/60 backdrop-blur border border-border/30 rounded-xl p-3 text-center">
            <p className={`text-2xl font-bold ${s.color}`}>{s.value}</p>
            <p className="text-[10px] text-muted uppercase tracking-wider">{s.label}</p>
          </div>
        ))}
      </div>

      {/* Tab bar */}
      <div className="flex gap-1 bg-surface/40 backdrop-blur rounded-xl p-1 border border-border/20">
        {TABS.map(t => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition-all ${
              tab === t
                ? 'bg-violet/20 text-violet-light border border-violet/30 shadow-lg shadow-violet/5'
                : 'text-muted hover:text-white'
            }`}
          >
            <span>{TAB_LABELS[t].emoji}</span>
            {TAB_LABELS[t].label}
          </button>
        ))}
      </div>

      {/* Tab content */}
      {tab === 'sources' && (
        <div className="space-y-4">
          <div className={`bg-gradient-to-br ${config.gradient} border border-border/30 rounded-2xl p-6`}>
            <h3 className="text-sm font-bold text-white mb-3">Source de donnees</h3>
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p className="text-muted text-xs uppercase tracking-wider">Pipeline source</p>
                <p className="text-white font-mono mt-1">{config.slug}</p>
              </div>
              <div>
                <p className="text-muted text-xs uppercase tracking-wider">Content type</p>
                <p className="text-white font-mono mt-1">{config.contentType}</p>
              </div>
              <div>
                <p className="text-muted text-xs uppercase tracking-wider">Input quality</p>
                <p className="text-white font-mono mt-1">title_only</p>
              </div>
              <div>
                <p className="text-muted text-xs uppercase tracking-wider">Auto-expand pays</p>
                <p className="text-white font-mono mt-1">{config.autoExpand ? 'Oui (197 pays)' : 'Non'}</p>
              </div>
            </div>
            {stats && (
              <div className="mt-4 pt-4 border-t border-border/20 flex gap-6 text-xs">
                <span className="text-muted">Poids: <span className="text-white">{stats.weight_percent}%</span></span>
                <span className="text-muted">Pause: <span className={stats.is_paused ? 'text-danger' : 'text-success'}>{stats.is_paused ? 'Oui' : 'Non'}</span></span>
                <span className="text-muted">Quota: <span className="text-white">{stats.daily_quota}/jour</span></span>
              </div>
            )}
          </div>

          <div className="bg-surface/40 border border-border/20 rounded-2xl p-6">
            <h3 className="text-sm font-bold text-white mb-3">Template titre</h3>
            <p className="text-sm font-mono text-violet-light bg-bg/40 rounded-xl px-4 py-3">{config.titleTemplate}</p>
            <p className="text-xs text-muted mt-2">Les titres sont generes automatiquement avec la variable {'{pays}'} remplacee par chaque pays.</p>
          </div>

          <div className="bg-surface/40 border border-border/20 rounded-2xl p-6">
            <h3 className="text-sm font-bold text-white mb-3">Instructions IA</h3>
            <p className="text-sm text-muted leading-relaxed">{config.instructions}</p>
          </div>
        </div>
      )}

      {tab === 'items' && (
        <div className="space-y-4">
          {/* Status filter */}
          <div className="flex gap-1.5">
            {[
              { key: '', label: 'Tous' },
              { key: 'ready', label: 'Prets' },
              { key: 'processing', label: 'En cours' },
              { key: 'used', label: 'Generes' },
              { key: 'raw', label: 'Bruts' },
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
            {loading ? (
              <div className="flex items-center justify-center h-40 text-muted">
                <div className="w-5 h-5 border-2 border-violet/30 border-t-violet rounded-full animate-spin mr-3" />
                Chargement...
              </div>
            ) : items.length > 0 ? (
              <div className="divide-y divide-border/10">
                {items.map(item => {
                  const st = STATUS_STYLES[item.processing_status] || STATUS_STYLES.raw;
                  return (
                    <div key={item.id} className="flex items-center gap-3 px-5 py-3 hover:bg-surface2/20 transition-colors group">
                      {item.country && (
                        <img
                          src={`/images/flags/${(item.country_slug || item.country || '').toLowerCase()}.webp`}
                          alt="" className="w-5 h-3.5 object-cover rounded-sm shrink-0"
                          onError={e => { (e.target as HTMLImageElement).style.display = 'none'; }}
                        />
                      )}
                      <div className="flex-1 min-w-0">
                        <p className="text-sm text-white truncate">{item.title}</p>
                        {item.theme && <p className="text-[10px] text-muted">{item.theme}</p>}
                      </div>
                      <span className="text-[10px] text-muted">{item.language}</span>
                      <span className={`shrink-0 px-2.5 py-1 rounded-lg text-[10px] font-semibold uppercase tracking-wider ${st.bg} ${st.text}`}>
                        {st.label}
                      </span>
                      {item.processing_status === 'ready' && (
                        <button
                          onClick={() => handleGenerateOne(item)}
                          disabled={generating === item.id}
                          className="opacity-0 group-hover:opacity-100 px-2.5 py-1 text-[10px] bg-violet/20 text-violet-light rounded-lg hover:bg-violet/30 transition-all disabled:opacity-50"
                        >
                          {generating === item.id ? '...' : 'Generer'}
                        </button>
                      )}
                    </div>
                  );
                })}
              </div>
            ) : (
              <div className="px-5 py-12 text-center">
                <p className="text-3xl mb-2">📭</p>
                <p className="text-sm text-muted">Aucun item. Cliquez sur l'onglet "Generer" pour lancer l'expansion pays.</p>
              </div>
            )}
          </div>
        </div>
      )}

      {tab === 'generer' && (
        <div className="space-y-4">
          {/* Auto-expand */}
          {config.autoExpand && (
            <div className={`bg-gradient-to-br ${config.gradient} border border-border/30 rounded-2xl p-6`}>
              <h3 className="text-sm font-bold text-white mb-2">{config.emoji} Generation automatique × 197 pays</h3>
              <p className="text-xs text-muted mb-4">Genere automatiquement un article pour chaque pays en utilisant le template titre.</p>
              <button
                onClick={handleAutoExpand}
                className="px-5 py-2.5 bg-gradient-to-r from-violet to-violet-light text-white text-sm font-semibold rounded-xl shadow-lg shadow-violet/20 hover:shadow-violet/40 transition-all hover:scale-[1.02] active:scale-[0.98]"
              >
                Lancer l'expansion 197 pays
              </button>
            </div>
          )}

          {/* Batch generation */}
          {readyCount > 0 && (
            <div className="bg-surface/60 backdrop-blur border border-border/30 rounded-2xl p-6">
              <h3 className="text-sm font-bold text-white mb-2">Generation par lot</h3>
              <p className="text-xs text-muted mb-4">{readyCount} items prets a generer.</p>
              <div className="flex gap-3">
                <button
                  onClick={() => handleGenerateBatch(5)}
                  disabled={batchGenerating}
                  className="px-4 py-2 bg-violet/20 text-violet-light text-sm font-medium rounded-xl border border-violet/20 hover:bg-violet/30 transition-all disabled:opacity-50"
                >
                  {batchGenerating ? 'En cours...' : 'Generer 5'}
                </button>
                <button
                  onClick={() => handleGenerateBatch(20)}
                  disabled={batchGenerating}
                  className="px-4 py-2 bg-violet/20 text-violet-light text-sm font-medium rounded-xl border border-violet/20 hover:bg-violet/30 transition-all disabled:opacity-50"
                >
                  Generer 20
                </button>
                <button
                  onClick={() => handleGenerateBatch(100)}
                  disabled={batchGenerating}
                  className="px-4 py-2 bg-amber/20 text-amber text-sm font-medium rounded-xl border border-amber/20 hover:bg-amber/30 transition-all disabled:opacity-50"
                >
                  Generer tout ({readyCount})
                </button>
              </div>
            </div>
          )}

          {/* Manual title */}
          <div className="bg-surface/40 border border-border/20 rounded-2xl p-6">
            <h3 className="text-sm font-bold text-white mb-3">Ajouter un titre manuellement</h3>
            <div className="flex gap-2">
              <input
                type="text"
                value={newTitle}
                onChange={e => setNewTitle(e.target.value)}
                onKeyDown={e => e.key === 'Enter' && (e.preventDefault(), handleAddTitle())}
                placeholder={config.titleTemplate.replace('{pays}', 'France')}
                className="flex-1 bg-bg/60 border border-border/40 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-violet/50 focus:ring-1 focus:ring-violet/20 backdrop-blur transition-all"
              />
              <button
                onClick={handleAddTitle}
                className="px-4 py-2.5 bg-violet/20 text-violet-light text-sm font-medium rounded-xl border border-violet/20 hover:bg-violet/30 transition-all"
              >
                Generer
              </button>
            </div>

            <button onClick={() => setShowBulk(!showBulk)} className="mt-3 text-xs text-muted hover:text-violet-light transition-colors">
              {showBulk ? 'Fermer bulk' : '+ Ajouter en bulk (copier-coller)'}
            </button>

            {showBulk && (
              <div className="mt-3 space-y-2">
                <textarea
                  value={bulkInput}
                  onChange={e => setBulkInput(e.target.value)}
                  rows={6}
                  placeholder="Un titre par ligne..."
                  className="w-full bg-bg/60 border border-border/40 rounded-xl px-4 py-3 text-white text-sm font-mono focus:outline-none focus:border-violet/50 transition-all resize-none"
                />
                <button
                  onClick={() => {
                    const lines = bulkInput.split('\n').map(l => l.trim()).filter(Boolean);
                    lines.forEach(async (title) => {
                      try {
                        await generateArticle({
                          topic: title, language: 'fr', content_type: config.contentType as any,
                          tone: 'professional', length: 'medium', generate_faq: true,
                          research_sources: true, auto_internal_links: true, auto_affiliate_links: true,
                          instructions: config.instructions,
                        });
                      } catch { /* continue */ }
                    });
                    toast.success(`${lines.length} articles lances`);
                    setBulkInput('');
                    setShowBulk(false);
                  }}
                  className="px-4 py-2 bg-violet/20 text-violet-light text-sm rounded-xl border border-violet/20 hover:bg-violet/30 transition-all"
                >
                  Generer {bulkInput.split('\n').filter(l => l.trim()).length} articles
                </button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
