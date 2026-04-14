import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';
import { Modal } from '../../ui/Modal';
import { Button } from '../../ui/Button';
import { Badge } from '../../ui/Badge';
import { Select } from '../../ui/Select';

// ── Types ─────────────────────────────────────────────────────────────────────

interface LiStats {
  posts_this_week: number;
  posts_scheduled: number;
  posts_published: number;
  posts_generating: number;
  total_reach: number;
  avg_engagement_rate: number;
  top_performing_day: string;
  available_articles: number;
  available_faqs: number;
  available_sondages: number;
  linkedin_connected: boolean;
  upcoming_posts: UpcomingPost[];
}

interface OAuthStatus {
  personal: { connected: boolean; name: string | null; expires_in_days: number };
  page:     { connected: boolean; name: string | null; expires_in_days: number };
}

interface LinkedInOrg {
  id: string;
  name: string;
}

interface UpcomingPost {
  id: number;
  day_type: string;
  lang: string;
  account: string;
  hook_preview: string;
  scheduled_at: string;
  source_type: string;
}

interface LiPost {
  id: number;
  source_type: string;
  source_id: number | null;
  source_title: string | null;
  day_type: string;
  lang: string;
  account: string;
  hook: string;
  body: string;
  hashtags: string[];
  first_comment: string | null;
  first_comment_status: 'pending' | 'posted' | 'failed' | null;
  first_comment_posted_at: string | null;
  reply_variants: string[] | null;
  featured_image_url: string | null;
  auto_scheduled: boolean;
  status: 'generating' | 'draft' | 'scheduled' | 'published' | 'failed';
  scheduled_at: string | null;
  published_at: string | null;
  reach: number;
  likes: number;
  comments: number;
  shares: number;
  engagement_rate: number;
  phase: number;
  error_message: string | null;
  created_at: string;
}

interface PaginatedPosts {
  data: LiPost[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

interface AutoSelectResult {
  found: boolean;
  source_type: string;
  source_id: number | null;
  title: string | null;
  country: string | null;
  editorial_score?: number;
  seo_score?: number;
  available_count: number;
}

interface GenerateParams {
  source_type: string;
  source_id: number | null;
  day_type: string;
  lang: string;
  account: string;
}

// ── Constants ─────────────────────────────────────────────────────────────────

const BASE = '/content-gen/linkedin';

const DAYS = [
  { value: 'monday',    label: '📋 Lundi — Carrousel conseils' },
  { value: 'tuesday',   label: '💬 Mardi — Story fictive' },
  { value: 'wednesday', label: '🚨 Mercredi — Actu légale' },
  { value: 'thursday',  label: '❓ Jeudi — Q&A' },
  { value: 'friday',    label: '✨ Vendredi — Témoignage/tip' },
];

// Source types grouped: DB sources (need auto-select) vs free generation
const SOURCE_TYPES_DB = [
  { value: 'article', label: '📄 Article de blog (meilleur score)' },
  { value: 'faq',     label: '❓ FAQ / Q&A (meilleur SEO)' },
  { value: 'sondage', label: '📊 Stats sondage SOS-Expat' },
];

const SOURCE_TYPES_FREE = [
  { value: 'hot_take',          label: '🔥 Hot take — opinion tranchée' },
  { value: 'myth',              label: '💥 Mythe à démolir' },
  { value: 'poll',              label: '📊 Sondage LinkedIn natif' },
  { value: 'serie',             label: '📚 Série éducative numérotée' },
  { value: 'reactive',          label: '⚡ Réactif — actualité' },
  { value: 'milestone',         label: '🏆 Milestone — preuve sociale' },
  { value: 'partner_story',     label: '🤝 Story partenaire avocat/helper' },
  { value: 'counter_intuition', label: '🔄 Contre-intuition' },
  { value: 'tip',               label: '💡 Tip rapide actionnable' },
  { value: 'news',              label: '📰 Actualité libre' },
];

const ALL_SOURCE_TYPES = [...SOURCE_TYPES_DB, ...SOURCE_TYPES_FREE];

const SOURCE_LABEL: Record<string, string> = Object.fromEntries(ALL_SOURCE_TYPES.map(t => [t.value, t.label]));

const STATUS_META: Record<string, { label: string; variant: 'neutral' | 'info' | 'warning' | 'success' | 'danger' }> = {
  generating: { label: 'Génération...', variant: 'info' },
  draft:      { label: 'Brouillon',     variant: 'neutral' },
  scheduled:  { label: 'Planifié',      variant: 'warning' },
  published:  { label: 'Publié',        variant: 'success' },
  failed:     { label: 'Échec',         variant: 'danger' },
};

const DAY_SHORT: Record<string, string> = {
  monday: 'Lun', tuesday: 'Mar', wednesday: 'Mer', thursday: 'Jeu', friday: 'Ven',
};

// ── Helpers ───────────────────────────────────────────────────────────────────

function getCurrentWeekday(): string {
  const map = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
  const d = map[new Date().getDay()];
  return ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'].includes(d) ? d : 'monday';
}

// ── Main Component ────────────────────────────────────────────────────────────

export default function RepublicationLinkedIn() {
  const qc = useQueryClient();
  const [tab, setTab] = useState<'dashboard' | 'queue' | 'strategy'>('dashboard');
  const [queueStatus, setQueueStatus] = useState('all');
  const [queuePage, setQueuePage] = useState(1);

  const [showGenModal, setShowGenModal] = useState(false);
  const [genParams, setGenParams] = useState<GenerateParams>({
    source_type: 'article',
    source_id: null,
    day_type: getCurrentWeekday(),
    lang: 'fr',
    account: 'page',
  });

  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [scheduleModal, setScheduleModal] = useState<{ postId: number; date: string } | null>(null);
  const [weekGenProgress, setWeekGenProgress] = useState<string | null>(null);
  const [replyModal, setReplyModal] = useState<{ post: LiPost; commentText: string; variants: string[] | null } | null>(null);
  const [oauthModal, setOauthModal] = useState(false);

  // ── Queries ───────────────────────────────────────────────────────────

  const { data: oauthStatus, refetch: refetchOauth } = useQuery<OAuthStatus>({
    queryKey: ['li-oauth'],
    queryFn: () => api.get(BASE + '/oauth/status').then(r => r.data),
    staleTime: 60_000,
    refetchInterval: 120_000,
  });

  const { data: orgs } = useQuery<{ orgs: LinkedInOrg[] }>({
    queryKey: ['li-orgs'],
    queryFn: () => api.get(BASE + '/oauth/orgs').then(r => r.data),
    enabled: oauthModal && (oauthStatus?.personal.connected ?? false) && !(oauthStatus?.page.connected ?? false),
    staleTime: 300_000,
  });

  const mutateSetPage = useMutation({
    mutationFn: (org: LinkedInOrg) => api.post(BASE + '/oauth/set-page', { org_id: org.id, org_name: org.name }).then(r => r.data),
    onSuccess: () => { refetchOauth(); qc.invalidateQueries({ queryKey: ['li-stats'] }); },
  });

  const mutateDisconnect = useMutation({
    mutationFn: (accountType: string) => api.delete(BASE + '/oauth/disconnect', { params: { account_type: accountType } }),
    onSuccess: () => { refetchOauth(); qc.invalidateQueries({ queryKey: ['li-stats'] }); },
  });

  const { data: stats } = useQuery<LiStats>({
    queryKey: ['li-stats'],
    queryFn: () => api.get(BASE + '/stats').then(r => r.data),
    staleTime: 30_000,
    refetchInterval: 60_000,
  });

  const { data: queue, isLoading: queueLoading } = useQuery<PaginatedPosts>({
    queryKey: ['li-queue', queueStatus, queuePage],
    queryFn: () =>
      api.get(BASE + '/queue', { params: { status: queueStatus, page: queuePage, per_page: 25 } }).then(r => r.data),
    staleTime: 15_000,
    refetchInterval: (query) => {
      const posts = (query.state.data as PaginatedPosts | undefined)?.data ?? [];
      return posts.some(p => p.status === 'generating') ? 5_000 : false;
    },
  });

  const { data: autoSelect, isLoading: autoSelLoading } = useQuery<AutoSelectResult>({
    queryKey: ['li-auto-select', genParams.source_type, genParams.lang],
    queryFn: () =>
      api.get(BASE + '/auto-select', { params: { source_type: genParams.source_type, lang: genParams.lang } }).then(r => r.data),
    enabled: showGenModal && genParams.source_type !== 'tip' && genParams.source_type !== 'news',
    staleTime: 60_000,
  });

  // Sync auto-selected source_id into params (only if user didn't pick manually)
  useEffect(() => {
    if (autoSelect?.found && !genParams.source_id) {
      setGenParams(p => ({ ...p, source_id: autoSelect.source_id }));
    }
  }, [autoSelect]);

  // Reset source_id when source_type or lang changes
  useEffect(() => {
    setGenParams(p => ({ ...p, source_id: null }));
  }, [genParams.source_type, genParams.lang]);

  // ── Mutations ─────────────────────────────────────────────────────────

  const mutateGenerate = useMutation({
    mutationFn: (params: GenerateParams) => api.post(BASE + '/generate', params).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['li-queue'] });
      qc.invalidateQueries({ queryKey: ['li-stats'] });
    },
  });

  const mutatePublish = useMutation({
    mutationFn: (id: number) => api.post(`${BASE}/posts/${id}/publish`).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['li-queue'] });
      qc.invalidateQueries({ queryKey: ['li-stats'] });
    },
  });

  const mutateSchedule = useMutation({
    mutationFn: ({ id, date }: { id: number; date: string }) =>
      api.post(`${BASE}/posts/${id}/schedule`, { scheduled_at: date }).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['li-queue'] });
      setScheduleModal(null);
    },
  });

  const mutateDelete = useMutation({
    mutationFn: (id: number) => api.delete(`${BASE}/posts/${id}`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['li-queue'] });
      qc.invalidateQueries({ queryKey: ['li-stats'] });
    },
  });

  const mutateGenerateReplies = useMutation({
    mutationFn: ({ postId, comment }: { postId: number; comment: string }) =>
      api.post(`${BASE}/posts/${postId}/generate-replies`, { comment_text: comment }).then(r => r.data),
    onSuccess: (data) => {
      setReplyModal(m => m ? { ...m, variants: data.variants ?? [] } : null);
      qc.invalidateQueries({ queryKey: ['li-queue'] });
    },
  });

  // ── Actions ───────────────────────────────────────────────────────────

  function handleGenerate() {
    mutateGenerate.mutate(genParams, {
      onSuccess: () => {
        setShowGenModal(false);
        setTab('queue');
      },
    });
  }

  async function handleGenerateWeek() {
    const daySourceMap: [string, string][] = [
      ['monday',    'article'],
      ['tuesday',   'faq'],
      ['wednesday', 'article'],
      ['thursday',  'faq'],
      ['friday',    'tip'],
    ];

    setWeekGenProgress('Démarrage...');
    for (const [day, sourceType] of daySourceMap) {
      setWeekGenProgress(`Génération ${DAY_SHORT[day]}...`);
      await mutateGenerate.mutateAsync({
        source_type: sourceType,
        source_id: null,
        day_type: day,
        lang: genParams.lang,
        account: genParams.account,
      });
      await new Promise(res => setTimeout(res, 300));
    }
    setWeekGenProgress(null);
    setTab('queue');
  }

  // ── Render ────────────────────────────────────────────────────────────

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div>
          <h1 className="text-2xl font-bold text-text font-title">💼 LinkedIn Republication</h1>
          <p className="text-text-muted text-sm mt-1">
            Publication automatique — page SOS-Expat + profil personnel
          </p>
        </div>
        <div className="flex gap-2">
          <Button
            variant="secondary"
            size="sm"
            onClick={handleGenerateWeek}
            loading={weekGenProgress !== null}
            disabled={weekGenProgress !== null}
          >
            {weekGenProgress ?? '📅 Générer la semaine'}
          </Button>
          <Button
            size="sm"
            onClick={() => {
              setGenParams(p => ({ ...p, source_id: null, day_type: getCurrentWeekday() }));
              setShowGenModal(true);
            }}
          >
            ✨ Nouveau post
          </Button>
        </div>
      </div>

      {/* Phase banner */}
      <div className="rounded-xl border border-blue-500/30 bg-blue-500/8 p-4 flex items-start gap-3">
        <span className="text-xl shrink-0">🎯</span>
        <div>
          <p className="text-blue-300 font-semibold text-sm">Phase 1 — Clients francophones (Now → Août 2026)</p>
          <p className="text-text-muted text-xs mt-0.5">
            Posts dominants en FR · Expatriés francophones worldwide ·{' '}
            <span className="text-text-muted">Phase 2 (Sept 2026+) : expansion EN+FR, avocats et helpers partenaires, API LinkedIn v2</span>
          </p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        {(['dashboard', 'queue', 'strategy'] as const).map(t => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`px-4 py-2.5 text-sm font-medium rounded-t-lg transition-colors ${
              tab === t
                ? 'text-violet-light border-b-2 border-violet bg-surface2'
                : 'text-text-muted hover:text-text'
            }`}
          >
            {t === 'dashboard' ? '📊 Dashboard' : t === 'queue' ? "📋 File d'attente" : '🧭 Stratégie'}
            {t === 'queue' && queue && queue.total > 0 && (
              <span className="ml-1.5 bg-surface2 text-text-muted text-xs rounded-full px-1.5 py-0.5 border border-border">
                {queue.total}
              </span>
            )}
          </button>
        ))}
      </div>

      {/* ── DASHBOARD ────────────────────────────────────────────────── */}
      {tab === 'dashboard' && (
        <div className="space-y-6">
          {/* LinkedIn OAuth status — real connect buttons */}
          <LinkedInOAuthWidget
            status={oauthStatus}
            onConnect={() => setOauthModal(true)}
            onDisconnect={(type) => mutateDisconnect.mutate(type)}
          />

          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Cette semaine" value={stats?.posts_this_week ?? 0} icon="📅" />
            <StatCard label="Planifiés" value={stats?.posts_scheduled ?? 0} icon="⏰" color="text-amber-300" />
            <StatCard label="Publiés" value={stats?.posts_published ?? 0} icon="✅" color="text-green-300" />
            <StatCard label="Portée totale" value={stats?.total_reach ?? 0} icon="👁" color="text-blue-300" />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div className="bg-surface2 rounded-xl p-4 border border-border">
              <p className="text-text-muted text-xs mb-1">Engagement moyen</p>
              <p className="text-2xl font-bold text-text">{stats?.avg_engagement_rate ?? 0}%</p>
              <p className="text-text-muted text-xs mt-1">
                Meilleur jour : <span className="text-text capitalize">{DAY_SHORT[stats?.top_performing_day ?? ''] ?? stats?.top_performing_day ?? '—'}</span>
              </p>
            </div>
            <div className="bg-surface2 rounded-xl p-4 border border-border">
              <p className="text-text-muted text-xs mb-1">Articles disponibles</p>
              <p className="text-2xl font-bold text-green-300">{stats?.available_articles ?? 0}</p>
              <p className="text-text-muted text-xs mt-1">Non encore republié</p>
            </div>
            <div className="bg-surface2 rounded-xl p-4 border border-border">
              <p className="text-text-muted text-xs mb-1">FAQs disponibles</p>
              <p className="text-2xl font-bold text-violet-light">{stats?.available_faqs ?? 0}</p>
              <p className="text-text-muted text-xs mt-1">Non encore republié</p>
            </div>
            <div className="bg-surface2 rounded-xl p-4 border border-border">
              <p className="text-text-muted text-xs mb-1">Sondages disponibles</p>
              <p className="text-2xl font-bold text-blue-300">{stats?.available_sondages ?? 0}</p>
              <p className="text-text-muted text-xs mt-1">Non encore republié</p>
            </div>
          </div>

          {/* Upcoming posts calendar */}
          {stats?.upcoming_posts && stats.upcoming_posts.length > 0 && (
            <div className="bg-surface2 rounded-xl border border-border p-5">
              <h3 className="font-semibold text-text mb-4">📅 Prochaines publications (7 jours)</h3>
              <div className="space-y-2">
                {stats.upcoming_posts.map(p => (
                  <div key={p.id} className="flex items-center gap-3 py-2 border-b border-border/50 last:border-0">
                    <div className="w-24 shrink-0 text-center">
                      <p className="text-amber-300 font-mono text-xs">
                        {new Date(p.scheduled_at).toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: '2-digit' })}
                      </p>
                      <p className="text-text-muted text-[10px]">
                        {new Date(p.scheduled_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
                      </p>
                    </div>
                    <div className="flex items-center gap-1.5 shrink-0">
                      <span className="text-text-muted text-[10px] uppercase font-mono">{p.lang}</span>
                      <span className="text-text-muted text-[10px]">
                        {p.account === 'page' ? '🏢' : p.account === 'personal' ? '👤' : '🔀'}
                      </span>
                    </div>
                    <p className="text-text text-sm truncate flex-1">{p.hook_preview || SOURCE_LABEL[p.source_type]?.split('—')[0]?.trim()}</p>
                    <span className="text-text-muted text-[10px] shrink-0">{DAY_SHORT[p.day_type] ?? p.day_type}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Weekly rhythm */}
          <div>
            <h2 className="text-lg font-semibold text-text mb-3">Rythme hebdomadaire</h2>
            <div className="grid grid-cols-5 gap-3">
              {DAYS.map(day => (
                <div key={day.value} className="bg-surface2 rounded-xl p-4 border border-border text-center">
                  <p className="text-text font-bold">{DAY_SHORT[day.value]}</p>
                  <p className="text-text-muted text-xs mt-1 leading-tight">{day.label.split('—')[1]?.trim()}</p>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="mt-3 w-full text-xs"
                    onClick={() => {
                      setGenParams(p => ({ ...p, day_type: day.value, source_id: null }));
                      setShowGenModal(true);
                    }}
                  >
                    Générer
                  </Button>
                </div>
              ))}
            </div>
          </div>

          {/* Rules reminder */}
          <div className="rounded-xl border border-border bg-surface2 p-5">
            <h3 className="text-sm font-semibold text-text mb-3">📖 Règles LinkedIn 2026 (Top 1%)</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-text-muted">
              {[
                '✅ Hook : 2-3 lignes avant "Voir plus" (max 140 chars)',
                '✅ Corps total : 1 200–1 800 caractères',
                '✅ JAMAIS de lien dans le post (→ 1er commentaire)',
                '✅ 3-5 hashtags de niche pertinents',
                '✅ Ligne vide entre chaque paragraphe (mobile)',
                '✅ CTA : question ouverte pour les commentaires',
                '✅ Style : humain, empathique, conversationnel',
                '✅ Horaires optimaux : 07h30 et 12h15',
              ].map(r => <p key={r}>{r}</p>)}
            </div>
          </div>
        </div>
      )}

      {/* ── QUEUE ────────────────────────────────────────────────────── */}
      {tab === 'queue' && (
        <div className="space-y-4">
          <div className="flex gap-2 flex-wrap">
            {['all', 'generating', 'draft', 'scheduled', 'published', 'failed'].map(s => (
              <button
                key={s}
                onClick={() => { setQueueStatus(s); setQueuePage(1); }}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                  queueStatus === s
                    ? 'bg-violet/20 text-violet-light border border-violet/40'
                    : 'bg-surface2 text-text-muted border border-border hover:text-text'
                }`}
              >
                {s === 'all' ? 'Tous' : STATUS_META[s]?.label ?? s}
              </button>
            ))}
          </div>

          {queueLoading && <div className="text-center py-12 text-text-muted">Chargement...</div>}

          {!queueLoading && (!queue || queue.data.length === 0) && (
            <div className="text-center py-12 text-text-muted">
              <p className="text-4xl mb-3">📭</p>
              <p>Aucun post dans cette file</p>
              <Button variant="secondary" size="sm" className="mt-4" onClick={() => setShowGenModal(true)}>
                Générer un premier post
              </Button>
            </div>
          )}

          {queue?.data.map(post => (
            <PostCard
              key={post.id}
              post={post}
              expanded={expandedId === post.id}
              onToggleExpand={() => setExpandedId(expandedId === post.id ? null : post.id)}
              onPublish={() => mutatePublish.mutate(post.id)}
              onSchedule={() => {
                const d = new Date();
                d.setDate(d.getDate() + 1);
                d.setHours(7, 30, 0, 0);
                setScheduleModal({ postId: post.id, date: d.toISOString().slice(0, 16) });
              }}
              onDelete={() => {
                if (window.confirm('Supprimer ce post définitivement ?')) {
                  mutateDelete.mutate(post.id);
                }
              }}
              onGenerateReplies={() => setReplyModal({ post, commentText: '', variants: post.reply_variants ?? null })}
            />
          ))}

          {queue && queue.last_page > 1 && (
            <div className="flex items-center justify-between pt-2">
              <p className="text-text-muted text-sm">
                {queue.total} posts · page {queue.current_page}/{queue.last_page}
              </p>
              <div className="flex gap-2">
                <Button variant="secondary" size="sm" disabled={queue.current_page <= 1} onClick={() => setQueuePage(p => p - 1)}>
                  ← Précédent
                </Button>
                <Button variant="secondary" size="sm" disabled={queue.current_page >= queue.last_page} onClick={() => setQueuePage(p => p + 1)}>
                  Suivant →
                </Button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* ── STRATEGY ─────────────────────────────────────────────────── */}
      {tab === 'strategy' && <StrategyTab />}

      {/* ── GENERATE MODAL ───────────────────────────────────────────── */}
      <Modal
        open={showGenModal}
        onClose={() => setShowGenModal(false)}
        title="✨ Générer un post LinkedIn"
        size="md"
        footer={
          <>
            <Button variant="secondary" onClick={() => setShowGenModal(false)}>Annuler</Button>
            <Button onClick={handleGenerate} loading={mutateGenerate.isPending} disabled={mutateGenerate.isPending}>
              Générer (async)
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          <Select
            label="Jour de publication"
            options={DAYS}
            value={genParams.day_type}
            onChange={e => setGenParams(p => ({ ...p, day_type: e.target.value }))}
          />

          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-text">Type de contenu</label>
            <select
              className="h-11 px-3.5 rounded-lg border border-border bg-surface2 text-sm text-text focus:outline-none focus:border-violet"
              value={genParams.source_type}
              onChange={e => setGenParams(p => ({ ...p, source_type: e.target.value }))}
            >
              <optgroup label="── Sources DB (sélection intelligente)">
                {SOURCE_TYPES_DB.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
              </optgroup>
              <optgroup label="── Génération libre (10 angles)">
                {SOURCE_TYPES_FREE.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
              </optgroup>
            </select>
          </div>

          {/* Auto-select preview — only for DB source types */}
          {['article', 'faq', 'sondage'].includes(genParams.source_type) && (
            <div className="rounded-lg bg-surface border border-border/60 p-3 text-sm">
              {autoSelLoading && (
                <p className="text-text-muted animate-pulse">Recherche de la meilleure source...</p>
              )}
              {!autoSelLoading && autoSelect && (
                autoSelect.found ? (
                  <div>
                    <p className="text-green-300 font-medium text-xs mb-1">✅ Meilleure source sélectionnée automatiquement</p>
                    <p className="text-text truncate">{autoSelect.title}</p>
                    <p className="text-text-muted text-xs mt-1">
                      {autoSelect.editorial_score !== undefined && `Score éditorial : ${autoSelect.editorial_score}/100 · `}
                      {autoSelect.seo_score !== undefined && `Score SEO : ${autoSelect.seo_score}/100 · `}
                      {autoSelect.available_count} sources disponibles
                    </p>
                  </div>
                ) : (
                  <p className="text-amber-300 text-xs">⚠️ Aucune source disponible — génération libre sans source</p>
                )
              )}
            </div>
          )}

          <div className="grid grid-cols-2 gap-4">
            <Select
              label="Langue"
              options={[
                { value: 'fr',   label: '🇫🇷 Français' },
                { value: 'en',   label: '🇬🇧 Anglais' },
                { value: 'both', label: '🌍 FR + EN (deux posts)' },
              ]}
              value={genParams.lang}
              onChange={e => setGenParams(p => ({ ...p, lang: e.target.value }))}
            />
            <Select
              label="Compte de publication"
              options={[
                { value: 'page',     label: '🏢 Page SOS-Expat' },
                { value: 'personal', label: '👤 Profil personnel' },
                { value: 'both',     label: '🔀 Les deux' },
              ]}
              value={genParams.account}
              onChange={e => setGenParams(p => ({ ...p, account: e.target.value }))}
            />
          </div>

          {genParams.lang === 'both' && (
            <div className="rounded-lg bg-blue-500/10 border border-blue-500/30 p-3 text-xs text-blue-300">
              🌍 <strong>2 posts seront créés</strong> : un en français + un en anglais, chacun planifié sur le prochain créneau libre de sa langue.
            </div>
          )}

          <p className="text-text-muted text-xs">
            💡 La génération est asynchrone — le post apparaîtra dans la file d'attente sous 10-30 secondes.
          </p>
        </div>
      </Modal>

      {/* ── REPLY MODAL ──────────────────────────────────────────────── */}
      <Modal
        open={replyModal !== null}
        onClose={() => setReplyModal(null)}
        title="💬 Générer des réponses au commentaire"
        size="md"
        footer={
          <>
            <Button variant="secondary" onClick={() => setReplyModal(null)}>Fermer</Button>
            <Button
              onClick={() => replyModal && mutateGenerateReplies.mutate({ postId: replyModal.post.id, comment: replyModal.commentText })}
              loading={mutateGenerateReplies.isPending}
              disabled={mutateGenerateReplies.isPending || !replyModal?.commentText?.trim()}
            >
              Générer 10 variantes
            </Button>
          </>
        }
      >
        {replyModal && (
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-text mb-1.5">Commentaire reçu</label>
              <textarea
                className="w-full rounded-lg border border-border bg-surface2 px-3.5 py-2.5 text-sm text-text focus:outline-none focus:border-violet resize-none"
                rows={3}
                placeholder="Collez ici le commentaire LinkedIn reçu..."
                value={replyModal.commentText}
                onChange={e => setReplyModal(m => m ? { ...m, commentText: e.target.value } : null)}
              />
              <p className="text-text-muted text-xs mt-1">
                Post : <span className="text-text">{replyModal.post.hook?.slice(0, 80) || '(sans hook)'}</span>
              </p>
            </div>

            {replyModal.variants && replyModal.variants.length > 0 && (
              <div>
                <p className="text-sm font-medium text-text mb-2">10 variantes générées — copiez celle qui convient</p>
                <div className="space-y-2 max-h-80 overflow-y-auto pr-1">
                  {replyModal.variants.map((v, i) => (
                    <div key={i} className="flex items-start gap-2 group">
                      <span className="text-text-muted text-xs w-5 shrink-0 mt-2">{i + 1}.</span>
                      <div className="flex-1 bg-surface rounded-lg border border-border/60 px-3 py-2 text-sm text-text">
                        {v}
                      </div>
                      <button
                        className="shrink-0 mt-2 text-text-muted hover:text-violet-light transition-colors opacity-0 group-hover:opacity-100"
                        title="Copier"
                        onClick={() => navigator.clipboard.writeText(v)}
                      >
                        📋
                      </button>
                    </div>
                  ))}
                </div>
                <p className="text-text-muted text-xs mt-2">
                  💡 Phase 2 : validation 1-tap via Telegram — la réponse sélectionnée sera postée automatiquement via LinkedIn API
                </p>
              </div>
            )}

            {mutateGenerateReplies.isError && (
              <p className="text-red-300 text-sm">Erreur lors de la génération — réessayez.</p>
            )}
          </div>
        )}
      </Modal>

      {/* ── LINKEDIN OAUTH MODAL ─────────────────────────────────────── */}
      <Modal
        open={oauthModal}
        onClose={() => setOauthModal(false)}
        title="🔗 Connecter LinkedIn API v2"
        size="md"
        footer={<Button variant="secondary" onClick={() => setOauthModal(false)}>Fermer</Button>}
      >
        <div className="space-y-5">
          {/* Step 1: Personal */}
          <div className={`rounded-xl border p-4 ${oauthStatus?.personal.connected ? 'border-green-500/30 bg-green-500/8' : 'border-border bg-surface'}`}>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-semibold text-text flex items-center gap-2">
                  👤 Profil personnel
                  {oauthStatus?.personal.connected && (
                    <span className="text-green-300 text-xs">✅ Connecté — {oauthStatus.personal.name}</span>
                  )}
                </p>
                <p className="text-text-muted text-xs mt-0.5">
                  {oauthStatus?.personal.connected
                    ? `Token expire dans ${oauthStatus.personal.expires_in_days} jours`
                    : 'Publie sur ton profil LinkedIn (portée ×3-5 vs page)'}
                </p>
              </div>
              {oauthStatus?.personal.connected ? (
                <Button variant="danger" size="sm" onClick={() => mutateDisconnect.mutate('personal')}>
                  Déconnecter
                </Button>
              ) : (
                <Button size="sm" onClick={() => {
                  window.location.href = '/api/linkedin/oauth/authorize?account_type=personal';
                }}>
                  Connecter
                </Button>
              )}
            </div>
          </div>

          {/* Step 2: Company page */}
          <div className={`rounded-xl border p-4 ${oauthStatus?.page.connected ? 'border-blue-500/30 bg-blue-500/8' : 'border-border bg-surface'}`}>
            <div className="flex items-center justify-between mb-3">
              <div>
                <p className="text-sm font-semibold text-text flex items-center gap-2">
                  🏢 Page SOS-Expat
                  {oauthStatus?.page.connected && (
                    <span className="text-blue-300 text-xs">✅ Connectée — {oauthStatus.page.name}</span>
                  )}
                </p>
                <p className="text-text-muted text-xs mt-0.5">
                  {oauthStatus?.page.connected
                    ? `Token expire dans ${oauthStatus.page.expires_in_days} jours`
                    : 'Nécessite d\'abord de connecter le profil personnel'}
                </p>
              </div>
              {oauthStatus?.page.connected && (
                <Button variant="danger" size="sm" onClick={() => mutateDisconnect.mutate('page')}>
                  Déconnecter
                </Button>
              )}
            </div>

            {/* Org picker — shown when personal connected but page not yet configured */}
            {oauthStatus?.personal.connected && !oauthStatus?.page.connected && orgs?.orgs && orgs.orgs.length > 0 && (
              <div>
                <p className="text-xs text-text-muted mb-2">Choisir la page à connecter :</p>
                <div className="space-y-1.5">
                  {orgs.orgs.map(org => (
                    <button
                      key={org.id}
                      className="w-full text-left px-3 py-2 rounded-lg bg-surface2 border border-border hover:border-violet/40 text-sm text-text transition-colors"
                      onClick={() => mutateSetPage.mutate(org)}
                    >
                      🏢 {org.name} <span className="text-text-muted text-xs ml-1">#{org.id}</span>
                    </button>
                  ))}
                </div>
              </div>
            )}
            {oauthStatus?.personal.connected && !oauthStatus?.page.connected && !orgs?.orgs?.length && (
              <p className="text-xs text-amber-300">Aucune page LinkedIn gérée trouvée avec ce compte.</p>
            )}
            {!oauthStatus?.personal.connected && (
              <p className="text-xs text-amber-300">Connecte d'abord le profil personnel ci-dessus.</p>
            )}
          </div>

          {/* Publishing strategy reminder */}
          <div className="rounded-lg bg-violet/8 border border-violet/20 p-3 text-xs text-text-muted space-y-1">
            <p className="font-medium text-violet-light">⚡ Stratégie auto-publication 2026</p>
            <p>• <strong className="text-text">07h30</strong> → profil personnel (reach ×3-5)</p>
            <p>• <strong className="text-text">12h15</strong> → page SOS-Expat (même jour, 4h30 plus tard)</p>
            <p>• Même contenu, audience différente, algorithme ne pénalise pas</p>
          </div>

          {/* What to add in .env */}
          <div className="rounded-lg bg-surface border border-border p-3 font-mono text-xs text-text-muted">
            <p className="text-text-muted mb-1 font-sans font-medium text-xs">Variables à ajouter dans .env.production :</p>
            <p>LINKEDIN_CLIENT_ID=<span className="text-amber-300">ton_client_id</span></p>
            <p>LINKEDIN_CLIENT_SECRET=<span className="text-amber-300">ton_client_secret</span></p>
            <p>LINKEDIN_REDIRECT_URI=<span className="text-amber-300">https://ton-api.com/api/linkedin/oauth/callback</span></p>
            <p>LINKEDIN_DASHBOARD_URL=<span className="text-amber-300">https://ton-dashboard.com/content/republication-rs/linkedin</span></p>
          </div>
        </div>
      </Modal>

      {/* ── SCHEDULE MODAL ───────────────────────────────────────────── */}
      <Modal
        open={scheduleModal !== null}
        onClose={() => setScheduleModal(null)}
        title="⏰ Planifier la publication"
        size="sm"
        footer={
          <>
            <Button variant="secondary" onClick={() => setScheduleModal(null)}>Annuler</Button>
            <Button
              onClick={() => scheduleModal && mutateSchedule.mutate({ id: scheduleModal.postId, date: scheduleModal.date })}
              loading={mutateSchedule.isPending}
              disabled={mutateSchedule.isPending}
            >
              Planifier
            </Button>
          </>
        }
      >
        {scheduleModal && (
          <div className="space-y-3">
            <label className="block text-sm font-medium text-text">Date et heure de publication</label>
            <input
              type="datetime-local"
              className="w-full h-11 rounded-lg border border-border bg-surface2 px-3.5 text-sm text-text focus:outline-none focus:border-violet"
              value={scheduleModal.date}
              min={new Date().toISOString().slice(0, 16)}
              onChange={e => setScheduleModal(s => s ? { ...s, date: e.target.value } : null)}
            />
            <p className="text-text-muted text-xs">
              💡 Horaires optimaux LinkedIn : <strong className="text-text">07h30</strong> (matin) ou <strong className="text-text">12h15</strong> (déjeuner)
            </p>
          </div>
        )}
      </Modal>
    </div>
  );
}

// ── Sub-components ────────────────────────────────────────────────────────────

function LinkedInOAuthWidget({
  status,
  onConnect,
  onDisconnect,
}: {
  status: OAuthStatus | undefined;
  onConnect: () => void;
  onDisconnect: (type: string) => void;
}) {
  const personalOk = status?.personal.connected ?? false;
  const pageOk     = status?.page.connected ?? false;
  const bothOk     = personalOk && pageOk;

  return (
    <div className={`rounded-xl border p-4 flex items-center justify-between gap-4 flex-wrap ${
      bothOk ? 'border-green-500/30 bg-green-500/8' : personalOk ? 'border-blue-500/30 bg-blue-500/8' : 'border-amber-500/30 bg-amber-500/8'
    }`}>
      <div className="flex items-center gap-3 min-w-0">
        <span className="text-xl shrink-0">{bothOk ? '🔗' : personalOk ? '⚡' : '🔓'}</span>
        <div className="min-w-0">
          <p className={`font-semibold text-sm ${bothOk ? 'text-green-300' : personalOk ? 'text-blue-300' : 'text-amber-300'}`}>
            {bothOk
              ? `LinkedIn connecté — ${status!.personal.name} + ${status!.page.name}`
              : personalOk
              ? `Profil ${status!.personal.name} connecté · Page non configurée`
              : 'LinkedIn non connecté — publication manuelle uniquement'}
          </p>
          <p className="text-text-muted text-xs mt-0.5">
            {bothOk
              ? `Auto-publication activée · Perso expire dans ${status!.personal.expires_in_days}j · Page dans ${status!.page.expires_in_days}j`
              : personalOk
              ? 'Configure la page SOS-Expat pour activer la double publication automatique'
              : 'Connecte LinkedIn pour activer la publication automatique des posts générés'}
          </p>
        </div>
      </div>
      <Button size="sm" variant={bothOk ? 'secondary' : 'primary'} onClick={onConnect} className="shrink-0">
        {bothOk ? '⚙️ Gérer la connexion' : '🔗 Connecter LinkedIn'}
      </Button>
    </div>
  );
}

function StatCard({
  label, value, icon, color = 'text-text',
}: {
  label: string; value: number; icon: string; color?: string;
}) {
  return (
    <div className="bg-surface2 rounded-xl p-4 border border-border">
      <p className="text-text-muted text-xs mb-1">{icon} {label}</p>
      <p className={`text-2xl font-bold ${color}`}>{value.toLocaleString('fr-FR')}</p>
    </div>
  );
}

const FC_STATUS: Record<string, { label: string; color: string }> = {
  pending: { label: '⏳ 1er commentaire en attente',    color: 'text-amber-300' },
  posted:  { label: '✅ 1er commentaire publié',         color: 'text-green-300' },
  failed:  { label: '❌ 1er commentaire échoué',         color: 'text-red-300'   },
};

function PostCard({
  post,
  expanded,
  onToggleExpand,
  onPublish,
  onSchedule,
  onDelete,
  onGenerateReplies,
}: {
  post: LiPost;
  expanded: boolean;
  onToggleExpand: () => void;
  onPublish: () => void;
  onSchedule: () => void;
  onDelete: () => void;
  onGenerateReplies: () => void;
}) {
  const s = STATUS_META[post.status] ?? { label: post.status, variant: 'neutral' as const };

  return (
    <div className={`bg-surface2 rounded-xl border overflow-hidden transition-all ${
      post.status === 'generating' ? 'border-blue-500/40' : 'border-border'
    }`}>
      {/* Card header — always visible, click to expand */}
      <div
        className="flex items-start gap-3 p-4 cursor-pointer hover:bg-white/[0.02]"
        onClick={onToggleExpand}
      >
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap mb-1.5">
            <Badge variant={s.variant} dot={post.status === 'generating' || post.status === 'scheduled'} size="sm">
              {s.label}
            </Badge>
            {post.auto_scheduled && (
              <span className="text-violet-light text-[10px] bg-violet/10 border border-violet/20 rounded-full px-2 py-0.5 font-medium">
                ⚡ Auto-planifié
              </span>
            )}
            <span className="text-text-muted text-xs">{DAY_SHORT[post.day_type] ?? post.day_type}</span>
            <span className="text-text-muted text-xs uppercase font-mono">{post.lang}</span>
            <span className="text-text-muted text-xs">
              {post.account === 'page' ? '🏢 Page' : post.account === 'personal' ? '👤 Perso' : '🔀 Les deux'}
            </span>
            <span className="text-text-muted text-xs">
              {SOURCE_LABEL[post.source_type]?.split('—')[0]?.trim() ?? post.source_type}
            </span>
            {post.source_title && (
              <span className="text-text-muted text-xs truncate max-w-[160px]" title={post.source_title}>
                · {post.source_title}
              </span>
            )}
          </div>

          {post.status === 'generating' ? (
            <p className="text-blue-300 text-sm animate-pulse">Génération IA en cours (GPT-4o)...</p>
          ) : post.status === 'failed' ? (
            <p className="text-red-300 text-sm line-clamp-1">{post.error_message ?? 'Échec de génération'}</p>
          ) : (
            <p className="text-text text-sm font-medium line-clamp-2">{post.hook || '(Hook vide)'}</p>
          )}

          {post.scheduled_at && post.status === 'scheduled' && (
            <p className="text-amber-300 text-xs mt-1">
              📅 {new Date(post.scheduled_at).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
            </p>
          )}
          {post.published_at && (
            <p className="text-green-300 text-xs mt-1">
              ✅ Publié le {new Date(post.published_at).toLocaleDateString('fr-FR')}
            </p>
          )}
          {post.first_comment_status && FC_STATUS[post.first_comment_status] && (
            <p className={`text-[11px] mt-1 ${FC_STATUS[post.first_comment_status].color}`}>
              {FC_STATUS[post.first_comment_status].label}
              {post.first_comment_posted_at && (
                <span className="text-text-muted ml-1">
                  · {new Date(post.first_comment_posted_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
                </span>
              )}
            </p>
          )}
        </div>

        <span className="text-text-muted text-xs shrink-0 pt-1">{expanded ? '▲' : '▼'}</span>
      </div>

      {/* Expanded content */}
      {expanded && post.status !== 'generating' && (
        <div className="border-t border-border p-4 space-y-4">
          {post.hook && (
            <div>
              <p className="text-xs font-semibold text-text-muted mb-1.5 uppercase tracking-wide">Hook</p>
              <div className="bg-surface rounded-lg p-3 text-sm text-text font-medium border border-border/50">
                {post.hook}
              </div>
            </div>
          )}

          {post.body && (
            <div>
              <p className="text-xs font-semibold text-text-muted mb-1.5 uppercase tracking-wide">Corps du post</p>
              <div className="bg-surface rounded-lg p-3 text-sm text-text whitespace-pre-line border border-border/50 max-h-48 overflow-y-auto">
                {post.body}
              </div>
            </div>
          )}

          {post.hashtags.length > 0 && (
            <div className="flex gap-2 flex-wrap">
              {post.hashtags.map(h => (
                <span key={h} className="text-blue-300 text-xs bg-blue-500/10 rounded-full px-2.5 py-1 border border-blue-500/20">
                  #{h}
                </span>
              ))}
            </div>
          )}

          {/* First comment */}
          {post.first_comment && (
            <div>
              <p className="text-xs font-semibold text-text-muted mb-1.5 uppercase tracking-wide flex items-center gap-1.5 flex-wrap">
                💬 Premier commentaire
                <span className="text-violet-light text-[10px] font-normal normal-case tracking-normal">auto-posté 3 min après publication</span>
                {post.first_comment_status && FC_STATUS[post.first_comment_status] && (
                  <span className={`text-[10px] font-medium ${FC_STATUS[post.first_comment_status].color}`}>
                    · {FC_STATUS[post.first_comment_status].label}
                  </span>
                )}
              </p>
              <div className="bg-violet/8 rounded-lg p-3 text-sm text-text-muted border border-violet/20 whitespace-pre-line">
                {post.first_comment}
              </div>
            </div>
          )}

          {/* Featured image */}
          {post.featured_image_url && (
            <div>
              <p className="text-xs font-semibold text-text-muted mb-1.5 uppercase tracking-wide">Image</p>
              <img src={post.featured_image_url} alt="" className="rounded-lg max-h-32 object-cover border border-border" />
            </div>
          )}

          {post.status === 'published' && (post.reach > 0 || post.likes > 0) && (
            <div className="flex gap-4 text-xs text-text-muted border-t border-border pt-3">
              {post.reach > 0 && <span>👁 {post.reach.toLocaleString()} vues</span>}
              {post.likes > 0 && <span>👍 {post.likes}</span>}
              {post.comments > 0 && <span>💬 {post.comments}</span>}
              {post.shares > 0 && <span>🔁 {post.shares}</span>}
              {post.engagement_rate > 0 && <span>📈 {post.engagement_rate}%</span>}
            </div>
          )}

          <div className="flex gap-2 flex-wrap pt-1">
            {(post.status === 'draft' || post.status === 'scheduled') && (
              <>
                <Button variant="secondary" size="sm" onClick={onSchedule}>⏰ Planifier</Button>
                <Button size="sm" onClick={onPublish}>🚀 Publier maintenant</Button>
              </>
            )}
            {post.status === 'published' && (
              <Button variant="secondary" size="sm" onClick={onGenerateReplies}>
                💬 Répondre à un commentaire
              </Button>
            )}
            <Button variant="danger" size="sm" onClick={onDelete}>🗑</Button>
          </div>
        </div>
      )}
    </div>
  );
}

function StrategyTab() {
  return (
    <div className="space-y-6">
      {/* Rythme */}
      <div className="bg-surface2 rounded-xl border border-border p-5">
        <h3 className="font-semibold text-text mb-4">📅 Rythme 5 jours/semaine</h3>
        <div className="grid grid-cols-1 sm:grid-cols-5 gap-3">
          {[
            { day: 'Lundi',    type: 'article / hot_take', format: 'Carrousel "X erreurs/conseils"', emoji: '📋', note: 'editorial_score DESC' },
            { day: 'Mardi',    type: 'faq / story fictive', format: 'Hook émotionnel + récit', emoji: '💬', note: 'seo_score DESC' },
            { day: 'Mercredi', type: 'reactive / myth', format: 'Actu légale ou mythe à démolir', emoji: '🚨', note: 'libre / actu' },
            { day: 'Jeudi',    type: 'faq / sondage', format: 'Q&A ou stat choc', emoji: '❓', note: 'stats sondage SOS-Expat' },
            { day: 'Vendredi', type: 'tip / milestone', format: 'Tip rapide ou story partenaire', emoji: '✨', note: 'libre / inspirant' },
          ].map(row => (
            <div key={row.day} className="text-center p-3 rounded-lg bg-surface border border-border">
              <p className="text-xl mb-1">{row.emoji}</p>
              <p className="text-text text-sm font-bold">{row.day}</p>
              <p className="text-text-muted text-xs mt-1">{row.format}</p>
              <p className="text-violet-light text-[10px] font-mono mt-1">{row.type}</p>
            </div>
          ))}
        </div>
      </div>

      {/* 14 Angles */}
      <div className="bg-surface2 rounded-xl border border-border p-5">
        <h3 className="font-semibold text-text mb-4">🎯 14 angles de contenu</h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {[
            { label: '📄 Article', desc: 'Adapte un article blog en conseils actionnables', badge: 'DB source', color: 'text-green-300' },
            { label: '❓ FAQ', desc: 'Transforme une FAQ en post engageant (hook = la question)', badge: 'DB source', color: 'text-green-300' },
            { label: '📊 Sondage', desc: 'Crée un post statistique choc avec les données SOS-Expat', badge: 'DB source', color: 'text-green-300' },
            { label: '🔥 Hot take', desc: 'Opinion tranchée qui génère le débat (50% en désaccord)', badge: 'Libre', color: 'text-amber-300' },
            { label: '💥 Mythe', desc: '"Non, [mythe]. La vérité : [réalité]" + exemples concrets', badge: 'Libre', color: 'text-amber-300' },
            { label: '📊 Poll LinkedIn', desc: 'Sondage natif LinkedIn (pousse l\'algo ×3)', badge: 'Libre', color: 'text-amber-300' },
            { label: '📚 Série', desc: '"Expat tip #N" — fidélise et crée l\'habitude de revenir', badge: 'Libre', color: 'text-amber-300' },
            { label: '⚡ Réactif', desc: 'Surfe sur l\'actualité → reach ×5-10 si dans les premiers', badge: 'Libre', color: 'text-amber-300' },
            { label: '🏆 Milestone', desc: 'Preuve sociale chiffrée ("1000 expats aidés")', badge: 'Libre', color: 'text-amber-300' },
            { label: '🤝 Story partenaire', desc: 'Avocat ou helper : humanise la plateforme, recrute', badge: 'Libre', color: 'text-amber-300' },
            { label: '🔄 Contre-intuition', desc: 'Affirmation surprenante → curiosité → clics "voir plus"', badge: 'Libre', color: 'text-amber-300' },
            { label: '💡 Tip rapide', desc: 'Conseil pratique immédiatement actionnable', badge: 'Libre', color: 'text-amber-300' },
            { label: '📰 News', desc: 'Actualité légale/visa récente liée à l\'expat', badge: 'Libre', color: 'text-amber-300' },
            { label: '📋 Case study', desc: 'Cas client (anonymisé) : problème → solution → résultat', badge: 'Libre', color: 'text-amber-300' },
          ].map(a => (
            <div key={a.label} className="flex items-start gap-3 p-3 rounded-lg bg-surface border border-border">
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <p className="text-text text-sm font-medium">{a.label}</p>
                  <span className={`text-[10px] font-mono ${a.color}`}>{a.badge}</span>
                </div>
                <p className="text-text-muted text-xs mt-0.5">{a.desc}</p>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Premier commentaire + Algo */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div className="bg-surface2 rounded-xl border border-border p-5">
          <h3 className="font-semibold text-text mb-3">💬 Premier commentaire automatique</h3>
          <div className="space-y-2 text-sm text-text-muted">
            <p>Posté <strong className="text-text">3 minutes après publication</strong> via LinkedIn API v2</p>
            <p>Contient :</p>
            <ul className="ml-3 space-y-1">
              <li>• Une <strong className="text-text">question ouverte</strong> à la communauté</li>
              <li>• Le <strong className="text-text">lien vers l'article source</strong> (si disponible)</li>
              <li>• La "suite" non dite dans le post principal</li>
            </ul>
            <p className="mt-2 text-xs bg-surface rounded p-2 border border-border text-text-muted italic">
              "Vous avez vécu une situation similaire ? Partagez en commentaire 👇<br/>
              → Guide complet : sos-expat.com/..."
            </p>
            <p className="text-xs text-amber-300 mt-2">⏳ Disponible dès que LinkedIn API v2 OAuth est configurée</p>
          </div>
        </div>

        <div className="bg-surface2 rounded-xl border border-border p-5">
          <h3 className="font-semibold text-text mb-3">🤖 Intelligence système</h3>
          <div className="space-y-2 text-sm text-text-muted">
            <p>• Dedup : source non-republié (filtre par IDs)</p>
            <p>• Score : meilleur <code className="text-xs">editorial_score</code> ou <code className="text-xs">seo_score</code></p>
            <p>• KB injection : <strong className="text-text">KnowledgeBase SOS-Expat v2.0</strong> (20 blocs)</p>
            <p>• Audience : <strong className="text-text">AudienceContextService</strong> (9 langues × nationalités)</p>
            <p>• Pays en contexte corps, <strong className="text-text">jamais sujet principal</strong></p>
            <p>• Hashtags dérivés des <code className="text-xs">keywords_primary</code></p>
            <p>• Posts : <strong className="text-text">GPT-4o</strong> (hooks créatifs, storytelling) · Réponses : <strong className="text-text">GPT-4o-mini</strong></p>
            <p>• Image : <strong className="text-text">Unsplash</strong> (searchUnique, anti-doublon, attribution auto en 1er commentaire)</p>
            <p>• Async : résultat en 10-30s, polling toutes les 5s</p>
          </div>
        </div>
      </div>

      {/* Phase roadmap */}
      <div className="bg-surface2 rounded-xl border border-border p-5">
        <h3 className="font-semibold text-text mb-4">🚀 Roadmap Phase 1 → Phase 2</h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div className="rounded-lg bg-blue-500/8 border border-blue-500/20 p-4">
            <p className="text-blue-300 font-semibold text-sm mb-2">Phase 1 — Now → Août 2026</p>
            <ul className="space-y-1 text-xs text-text-muted">
              <li>✅ 5 posts/semaine · Page SOS-Expat</li>
              <li>✅ FR dominant · clients expats francophones</li>
              <li>✅ Horaires : 07h30 et 12h15</li>
              <li>✅ 14 angles de contenu automatiques</li>
              <li>✅ Premier commentaire généré (stocké)</li>
              <li>⏳ Premier commentaire auto-publié (OAuth)</li>
            </ul>
          </div>
          <div className="rounded-lg bg-violet/8 border border-violet/20 p-4">
            <p className="text-violet-light font-semibold text-sm mb-2">Phase 2 — Sept 2026+</p>
            <ul className="space-y-1 text-xs text-text-muted">
              <li>🔲 FR + EN en alternance</li>
              <li>🔲 Avocats partenaires + helpers ciblés</li>
              <li>🔲 Page + profil personnel (double reach)</li>
              <li>🔲 API LinkedIn v2 OAuth connectée</li>
              <li>🔲 Réponses commentaires AI + validation Telegram</li>
              <li>🔲 LinkedIn Newsletter</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}
