import React, { useEffect, useState, useCallback } from 'react';
import api from '../../api/client';
import { generateArticle } from '../../api/contentApi';
import type { GenerateArticleParams } from '../../types/content';
import { toast } from '../../components/Toast';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip,
  ResponsiveContainer, Cell,
} from 'recharts';

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
    description: 'Articles SEO pour attirer des chatters (missions reseaux sociaux)',
    gradient: 'from-violet/20 to-violet/5',
    contentType: 'outreach',
    autoExpand: true,
    titleTemplate: 'Gagner de l\'argent en ligne {pays} : missions reseaux sociaux pour expatries',
    instructions: 'Article SEO ciblant les gens qui cherchent a gagner un revenu en ligne. Presenter les missions reseaux sociaux comme opportunite. Avantages: revenus complementaires, flexibilite, communaute. Mentionner SOS-Expat comme plateforme qui propose ces missions. CTA vers inscription.',
  },
  influenceurs: {
    emoji: '📢', title: 'Influenceurs', slug: 'bloggeurs',
    description: 'Articles SEO pour attirer des blogueurs/influenceurs affilies',
    gradient: 'from-cyan/20 to-cyan/5',
    contentType: 'outreach',
    autoExpand: true,
    titleTemplate: 'Monetiser son blog expatriation {pays} : programmes d\'affiliation',
    instructions: 'Article SEO ciblant les blogueurs expatries qui veulent monetiser. Presenter les programmes d\'affiliation expatriation. Avantages: commissions par appel ($10/client, $5/recrute), widget integrable. CTA vers inscription blogueur SOS-Expat.',
  },
  'admin-groupes': {
    emoji: '👥', title: 'Admin Groupes', slug: 'admin-groups',
    description: 'Articles SEO pour attirer des admins de groupes Facebook expatries',
    gradient: 'from-amber/20 to-amber/5',
    contentType: 'outreach',
    autoExpand: true,
    titleTemplate: 'Groupes Facebook expatries {pays} : creer et gerer une communaute',
    instructions: 'Article SEO ciblant les admins de groupes Facebook/Telegram expatries. Comment creer, gerer et monetiser un groupe. Avantages: communaute engagee, revenus via partenariats. Mentionner SOS-Expat comme partenaire pour monetiser. CTA vers inscription admin groupe.',
  },
  avocats: {
    emoji: '⚖️', title: 'Avocats', slug: 'avocats',
    description: 'Articles SEO pour attirer des avocats francophones a l\'international',
    gradient: 'from-success/20 to-success/5',
    contentType: 'outreach',
    autoExpand: true,
    titleTemplate: 'Avocat francophone {pays} : trouver des clients expatries en ligne',
    instructions: 'Article SEO ciblant les avocats francophones a l\'etranger. Comment developper sa clientele expatriee en ligne. Avantages: visibilite internationale, appels remuneres, flexibilite. Mentionner SOS-Expat comme plateforme de mise en relation. CTA vers inscription prestataire.',
  },
  'expats-aidants': {
    emoji: '🧳', title: 'Expats Aidants', slug: 'expats-aidants',
    description: 'Articles SEO pour attirer des expatries experimentes comme aidants',
    gradient: 'from-danger/20 to-danger/5',
    contentType: 'outreach',
    autoExpand: true,
    titleTemplate: 'Revenu complementaire {pays} : aider les expatries a distance',
    instructions: 'Article SEO ciblant les expatries experimentes qui veulent un revenu complementaire. Comment aider d\'autres expatries a distance (assistance telephonique, conseils). Avantages: flexibilite, partage d\'experience. Mentionner SOS-Expat. CTA vers inscription expat aidant.',
  },
  'guide-city': {
    emoji: '🏙️', title: 'Fiches Villes', slug: 'villes',
    description: 'Guides par ville — quartiers, cout de vie, logement, transport, communaute expatriee',
    gradient: 'from-sky/20 to-sky/5',
    contentType: 'guide_city',
    autoExpand: true,
    titleTemplate: 'Vivre a {ville} en tant qu\'expatrie : guide complet',
    instructions: 'Guide specifique a la VILLE (pas au pays). Quartiers par nom, prix locaux en devise locale + EUR/USD, transports locaux, adresses precises. S\'adresser a TOUTE nationalite. Lier vers la fiche pays pour le contexte national. CTA vers SOS-Expat.com.',
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
  tutorial: {
    emoji: '📖', title: 'Tutoriels', slug: 'tutoriels',
    description: 'Guides pratiques pas-a-pas pour les demarches des expatries',
    gradient: 'from-success/20 to-success/5',
    contentType: 'tutorial',
    autoExpand: true,
    titleTemplate: 'Comment {demarche} en {pays} : guide complet etape par etape',
    instructions: 'Guide pratique pas-a-pas sur une demarche administrative ou pratique pour expatrie. Structure: introduction contexte, pre-requis, etapes numerotees avec details concrets, delais, couts, erreurs a eviter, FAQ. Inclure captures/schemas si pertinent. CTA vers SOS-Expat.com pour aide personnalisee.',
  },
  'pain-point': {
    emoji: '😔', title: 'Souffrances', slug: 'pain-point',
    description: 'Articles ciblant les problemes urgents des expatries (passeport perdu, arnaque, urgence medicale)',
    gradient: 'from-red-500/20 to-red-500/5',
    contentType: 'pain_point',
    autoExpand: true,
    titleTemplate: '{sujet} : que faire quand on est expatrie en {pays}',
    instructions: 'Article URGENCE/SOUFFRANCE pour expatrie en detresse. Encadre urgence en haut avec premiers reflexes (3-5 actions immediates). Etapes numerotees actions concretes. Numeros et contacts utiles (ambassade, police, urgences du pays). Section erreurs a ne PAS commettre. CTA fort vers SOS-Expat.com (mise en relation expert en 5 min, 24h/24, 197 pays). Ton empathique et directif. S\'adresser a TOUTE nationalite.',
  },
};

const STATUS_STYLES: Record<string, { bg: string; text: string; label: string }> = {
  raw:        { bg: 'bg-muted/10', text: 'text-muted/60', label: 'Brut' },
  ready:      { bg: 'bg-blue-500/10', text: 'text-blue-400', label: 'Pret' },
  processing: { bg: 'bg-amber/10', text: 'text-amber', label: 'En cours' },
  used:       { bg: 'bg-success/10', text: 'text-success', label: 'Utilise' },
  skipped:    { bg: 'bg-muted/10', text: 'text-muted/40', label: 'Ignore' },
};

const TABS = ['sources', 'generation', 'generated'] as const;
type Tab = typeof TABS[number];
const TAB_LABELS: Record<Tab, { emoji: string; label: string }> = {
  sources:   { emoji: '📋', label: 'Sources' },
  generation:{ emoji: '⚡', label: 'Génération' },
  generated: { emoji: '✅', label: 'Contenus générés' },
};

// ─── COMPONENT ───────────────────────────────────────────

interface Props {
  type: keyof typeof TYPE_CONFIG;
}

export default function ContentGenerator({ type }: Props) {
  const config = TYPE_CONFIG[type];
  const [tab, setTab] = useState<Tab>('sources');
  const [items, setItems] = useState<SourceItem[]>([]);
  const [stats, setStats] = useState<CategoryStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState<number | null>(null);
  const [batchGenerating, setBatchGenerating] = useState(false);
  const [filterStatus, setFilterStatus] = useState('');
  const [newTitle, setNewTitle] = useState('');
  const [bulkInput, setBulkInput] = useState('');
  const [showBulk, setShowBulk] = useState(false);
  const [logs, setLogs] = useState<Array<{ date: string; generated: number; published: number; errors: number }>>([]);
  const [logsLoading, setLogsLoading] = useState(false);

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
      const raw = itemsRes.data as { items?: { data?: unknown[] }; data?: unknown[] } | unknown[];
      const extractedItems = (raw as { items?: { data?: unknown[] } })?.items?.data
        ?? (raw as { data?: unknown[] })?.data
        ?? (Array.isArray(raw) ? raw : []);
      setItems(extractedItems as typeof items);
      const cats = (statsRes.data as Array<{ slug: string }>) ?? [];
      const myCat = Array.isArray(cats) ? cats.find((c) => c.slug === config.slug) : null;
      setStats((myCat ?? null) as typeof stats);
      // Load logs for stats tab
      if (tab === 'generated') {
        try {
          const logsRes = await api.get('/content/orchestrator/logs', { params: { days: 30 } });
          const allLogs = (Array.isArray(logsRes.data) ? logsRes.data : []) as Array<{ type?: string; content_type?: string }>;
          const filtered = allLogs
            .filter((l) => l.type === config.contentType || l.content_type === config.contentType)
            .slice(0, 30)
            .reverse();
          setLogs(filtered as typeof logs);
        } catch {
          setLogs([]);
        }
      }
    } catch {
      toast.error('Erreur chargement');
    } finally {
      setLoading(false);
    }
  }, [config.slug, config.contentType, filterStatus, tab]);

  useEffect(() => { loadData(); }, [loadData]);

  // Load logs when switching to generated tab
  useEffect(() => {
    if (tab !== 'generated') return;
    setLogsLoading(true);
    api.get('/content/orchestrator/logs', { params: { days: 30 } })
      .then(res => {
        const allLogs = (Array.isArray(res.data) ? res.data : []) as Array<{ type?: string; content_type?: string }>;
        const filtered = allLogs
          .filter((l) => l.type === config.contentType || l.content_type === config.contentType)
          .slice(0, 30)
          .reverse();
        setLogs(filtered as typeof logs);
      })
      .catch(() => setLogs([]))
      .finally(() => setLogsLoading(false));
  }, [tab, config.contentType]);

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
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(msg || 'Erreur');
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
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(msg || 'Erreur');
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
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(msg || 'Erreur trigger');
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

          {/* Items list */}
          <div className="flex items-center gap-3">
            <span className="text-xs text-muted">Filtrer :</span>
            <select
              value={filterStatus}
              onChange={e => setFilterStatus(e.target.value)}
              className="bg-bg/60 border border-border/40 rounded-lg px-3 py-1.5 text-white text-xs focus:outline-none focus:border-violet/50 transition-all"
            >
              <option value="">Tous les statuts</option>
              <option value="ready">Prets</option>
              <option value="processing">En cours</option>
              <option value="used">Generes</option>
              <option value="raw">Bruts</option>
            </select>
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
                <p className="text-sm text-muted">Aucun item. Cliquez sur l'onglet "Génération" pour lancer l'expansion pays.</p>
              </div>
            )}
          </div>
        </div>
      )}

      {tab === 'generation' && (
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

      {tab === 'generated' && (
        <div className="space-y-6">
          {/* KPIs */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {[
              { label: 'Total items', value: stats?.total ?? 0, color: 'text-white' },
              { label: 'Prets', value: stats?.ready ?? 0, color: 'text-emerald-400' },
              { label: 'Utilises', value: stats?.used ?? 0, color: 'text-violet-light' },
              { label: 'Quota/jour', value: stats?.daily_quota ?? 0, color: 'text-amber-400' },
            ].map(kpi => (
              <div key={kpi.label} className="bg-surface/60 border border-border/30 rounded-2xl p-4 text-center">
                <div className={`text-2xl font-bold ${kpi.color}`}>{kpi.value}</div>
                <div className="text-xs text-muted mt-1">{kpi.label}</div>
              </div>
            ))}
          </div>

          {/* Distribution donut-style */}
          {stats && (
            <div className="bg-surface/60 border border-border/30 rounded-2xl p-5">
              <h3 className="text-sm font-bold text-white mb-4">Distribution du stock</h3>
              <div className="flex flex-col gap-2">
                {[
                  { label: 'Prets', value: stats.ready, color: '#10b981' },
                  { label: 'En cours', value: stats.processing, color: '#7c3aed' },
                  { label: 'Utilises', value: stats.used, color: '#6b7280' },
                ].map(row => (
                  <div key={row.label} className="flex items-center gap-3">
                    <span className="text-xs text-muted w-16">{row.label}</span>
                    <div className="flex-1 h-2 bg-border/20 rounded-full overflow-hidden">
                      <div
                        className="h-2 rounded-full transition-all"
                        style={{
                          width: stats.total > 0 ? `${Math.round(row.value / stats.total * 100)}%` : '0%',
                          backgroundColor: row.color,
                        }}
                      />
                    </div>
                    <span className="text-xs text-white w-8 text-right">{row.value}</span>
                  </div>
                ))}
              </div>
              <div className="mt-3 text-xs text-muted">
                Poids orchestrator : <span className="text-white font-medium">{stats.weight_percent}%</span>
                {' · '}Statut : <span className={stats.is_paused ? 'text-danger' : 'text-emerald-400'}>
                  {stats.is_paused ? 'En pause' : 'Actif'}
                </span>
              </div>
            </div>
          )}

          {/* Historique 30 jours */}
          <div className="bg-surface/60 border border-border/30 rounded-2xl p-5">
            <h3 className="text-sm font-bold text-white mb-4">Generations (30 derniers jours)</h3>
            {logsLoading ? (
              <div className="text-xs text-muted text-center py-4">Chargement...</div>
            ) : logs.length > 0 ? (
              <ResponsiveContainer width="100%" height={180}>
                <BarChart data={logs} margin={{ top: 0, right: 0, left: -20, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.06)" />
                  <XAxis
                    dataKey="date"
                    tick={{ fill: '#6b7280', fontSize: 10 }}
                    tickFormatter={(v: string) => v.slice(5)}
                  />
                  <YAxis tick={{ fill: '#6b7280', fontSize: 10 }} />
                  <Tooltip
                    contentStyle={{ background: '#101419', border: '1px solid rgba(255,255,255,0.1)', borderRadius: 8, fontSize: 12 }}
                    labelStyle={{ color: '#fff' }}
                  />
                  <Bar dataKey="generated" name="Generes" radius={[3, 3, 0, 0]}>
                    {logs.map((_, i: number) => (
                      <Cell key={i} fill={i === logs.length - 1 ? '#7c3aed' : '#a78bfa'} />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="text-xs text-muted text-center py-8 opacity-60">
                Aucune donnee d'historique disponible pour ce type de contenu.
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
