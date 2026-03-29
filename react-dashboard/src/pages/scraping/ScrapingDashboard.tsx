import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';

// ─── Types ───────────────────────────────────────────────────────────────────

interface ScraperAction {
  id: string;
  label: string;
  color: 'violet' | 'green' | 'amber' | 'yellow' | 'blue' | 'cyan' | 'red';
}

interface Scraper {
  id: string;
  label: string;
  icon: string;
  category: string;
  description: string;
  stats: Record<string, any>;
  actions: ScraperAction[];
}

interface QueueState {
  pending: number;
  failed: number;
}

interface StatusData {
  queue: QueueState;
  scrapers: Scraper[];
}

// ─── Constants ───────────────────────────────────────────────────────────────

const ACTION_COLORS: Record<string, string> = {
  violet: 'bg-violet/20 text-violet-light hover:bg-violet/40 border border-violet/30',
  green:  'bg-green-900/30 text-green-300 hover:bg-green-900/50 border border-green-500/30',
  amber:  'bg-amber-900/30 text-amber-300 hover:bg-amber-900/50 border border-amber-500/30',
  yellow: 'bg-yellow-900/30 text-yellow-300 hover:bg-yellow-900/50 border border-yellow-500/30',
  blue:   'bg-blue-900/30 text-blue-300 hover:bg-blue-900/50 border border-blue-500/30',
  cyan:   'bg-cyan-900/30 text-cyan-300 hover:bg-cyan-900/50 border border-cyan-500/30',
  red:    'bg-red-900/30 text-red-300 hover:bg-red-900/50 border border-red-500/30',
};

// ─── Stat row helper ─────────────────────────────────────────────────────────

function StatRow({ label, value, highlight }: { label: string; value: any; highlight?: string }) {
  if (value === null || value === undefined || value === 0) return null;
  return (
    <div className="flex items-center justify-between py-0.5">
      <span className="text-muted text-xs">{label}</span>
      <span className={`text-xs font-medium ${highlight || 'text-white'}`}>
        {typeof value === 'number' ? value.toLocaleString() : value}
      </span>
    </div>
  );
}

// ─── Scraper card ─────────────────────────────────────────────────────────────

function ScraperCard({
  scraper,
  onLaunch,
  launching,
}: {
  scraper: Scraper;
  onLaunch: (action: string) => void;
  launching: string | null;
}) {
  const s = scraper.stats;
  const contacts = s.contacts_found ?? s.crm_total ?? 0;
  const withEmail = s.contacts_with_email ?? s.crm_with_email ?? 0;
  const lastRun = s.last_run ? new Date(s.last_run).toLocaleDateString('fr-FR') : null;

  return (
    <div className="bg-surface border border-border rounded-xl p-4 space-y-3">
      {/* Header */}
      <div className="flex items-start gap-3">
        <span className="text-2xl flex-shrink-0">{scraper.icon}</span>
        <div className="flex-1 min-w-0">
          <div className="text-white font-medium text-sm">{scraper.label}</div>
          <div className="text-muted text-xs mt-0.5">{scraper.description}</div>
        </div>
        <span className="px-2 py-0.5 bg-surface2 text-muted rounded text-[10px] flex-shrink-0">{scraper.category}</span>
      </div>

      {/* Stats */}
      <div className="bg-surface2/50 rounded-lg p-3 space-y-0.5">
        {/* Journalist-specific */}
        <StatRow label="Publications configurées" value={s.publications} />
        <StatRow label="Publications scrapées" value={s.publications_scraped} highlight="text-green-400" />
        <StatRow label="Publications en attente" value={s.publications_pending} highlight="text-amber-400" />
        <StatRow label="Bylines configurées" value={s.bylines_configured} highlight="text-blue-300" />
        <StatRow label="Patterns email configurés" value={s.email_pattern_configured} highlight="text-cyan-300" />
        <StatRow label="Emails inférés" value={s.emails_inferred} highlight="text-amber-300" />

        {/* Lawyer-specific */}
        <StatRow label="Annuaires sources" value={s.sources_total} />
        <StatRow label="Annuaires complétés" value={s.sources_completed} highlight="text-green-400" />
        <StatRow label="Emails vérifiés" value={s.contacts_verified} highlight="text-green-400" />

        {/* Directory-specific */}
        <StatRow label="Annuaires total" value={s.directories_total} />
        <StatRow label="Annuaires complétés" value={s.directories_completed} highlight="text-green-400" />
        <StatRow label="Annuaires en attente" value={s.directories_pending} highlight="text-amber-400" />

        {/* Business-specific */}
        <StatRow label="Fiches détaillées" value={s.detailed_scraped} highlight="text-blue-300" />
        <StatRow label="Sites en attente" value={s.sites_pending} highlight="text-amber-400" />
        <StatRow label="Sites traités" value={s.sites_done} highlight="text-green-400" />

        {/* Common */}
        {contacts > 0 && (
          <div className="flex items-center justify-between py-0.5 border-t border-border/50 mt-1 pt-1.5">
            <span className="text-white text-xs font-medium">Contacts trouvés</span>
            <span className="text-green-400 text-sm font-bold">{contacts.toLocaleString()}</span>
          </div>
        )}
        {withEmail > 0 && (
          <div className="flex items-center justify-between py-0.5">
            <span className="text-muted text-xs">Dont avec email</span>
            <span className="text-blue-300 text-xs font-medium">{withEmail.toLocaleString()}</span>
          </div>
        )}
        {lastRun && (
          <div className="flex items-center justify-between py-0.5">
            <span className="text-muted text-xs">Dernier scraping</span>
            <span className="text-muted text-xs">{lastRun}</span>
          </div>
        )}
      </div>

      {/* Actions */}
      <div className="flex flex-wrap gap-2">
        {scraper.actions.map(action => (
          <button
            key={action.id}
            onClick={() => onLaunch(action.id)}
            disabled={launching === action.id}
            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors disabled:opacity-50 ${ACTION_COLORS[action.color]}`}
          >
            {launching === action.id ? '⏳ Lancement...' : action.label}
          </button>
        ))}
      </div>
    </div>
  );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function ScrapingDashboard() {
  const [tab, setTab] = useState<'etat' | 'resultats'>('etat');
  const [data, setData] = useState<StatusData | null>(null);
  const [loading, setLoading] = useState(true);
  const [launching, setLaunching] = useState<string | null>(null);
  const [notif, setNotif] = useState<{ msg: string; type: 'success' | 'error' } | null>(null);

  const notify = (msg: string, type: 'success' | 'error' = 'success') => {
    setNotif({ msg, type });
    setTimeout(() => setNotif(null), 7000);
  };

  const fetchStatus = useCallback(async () => {
    try {
      const res = await api.get('/content-gen/scraping/status');
      setData(res.data);
    } catch {
      notify('Erreur de chargement du statut', 'error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchStatus(); }, [fetchStatus]);
  // Auto-refresh every 30s
  useEffect(() => {
    const t = setInterval(fetchStatus, 30000);
    return () => clearInterval(t);
  }, [fetchStatus]);

  const handleLaunch = async (action: string) => {
    setLaunching(action);
    try {
      const res = await api.post('/content-gen/scraping/launch', { action });
      notify(`✅ ${res.data.message}`);
      setTimeout(fetchStatus, 3000);
    } catch (e: any) {
      notify(e?.response?.data?.error || 'Erreur de lancement', 'error');
    } finally {
      setLaunching(null);
    }
  };

  const totalContacts = data?.scrapers.reduce((sum, s) => {
    return sum + (s.stats.contacts_found ?? s.stats.crm_total ?? 0);
  }, 0) ?? 0;

  const totalWithEmail = data?.scrapers.reduce((sum, s) => {
    return sum + (s.stats.contacts_with_email ?? s.stats.crm_with_email ?? 0);
  }, 0) ?? 0;

  const categories = data
    ? [...new Set(data.scrapers.map(s => s.category))]
    : [];

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-title text-2xl font-bold text-white">🔧 Outils de Scraping</h1>
          <p className="text-muted text-sm mt-1">Pilotage centralisé de tous les scrapers</p>
        </div>
        <button onClick={fetchStatus} className="px-3 py-2 bg-surface2 text-muted rounded-lg text-xs hover:text-white transition-colors">
          ↻ Actualiser
        </button>
      </div>

      {/* Notification */}
      {notif && (
        <div className={`p-3 rounded-xl text-sm flex justify-between border ${
          notif.type === 'success'
            ? 'bg-green-900/20 border-green-500/30 text-green-300'
            : 'bg-red-900/20 border-red-500/30 text-red-300'
        }`}>
          <span>{notif.msg}</span>
          <button onClick={() => setNotif(null)}>×</button>
        </div>
      )}

      {/* Queue + global KPIs */}
      {data && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div className="bg-surface border border-border rounded-xl p-3 text-center">
            <div className="text-white font-bold text-xl">{totalContacts.toLocaleString()}</div>
            <div className="text-xs text-muted">Contacts scrapés total</div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-3 text-center">
            <div className="text-green-400 font-bold text-xl">{totalWithEmail.toLocaleString()}</div>
            <div className="text-xs text-muted">Avec email</div>
          </div>
          <div className={`border rounded-xl p-3 text-center ${data.queue.pending > 0 ? 'bg-amber-900/20 border-amber-500/30' : 'bg-surface border-border'}`}>
            <div className={`font-bold text-xl ${data.queue.pending > 0 ? 'text-amber-400' : 'text-muted'}`}>
              {data.queue.pending}
            </div>
            <div className="text-xs text-muted">Jobs en queue</div>
          </div>
          <div className={`border rounded-xl p-3 text-center ${data.queue.failed > 0 ? 'bg-red-900/20 border-red-500/30' : 'bg-surface border-border'}`}>
            <div className={`font-bold text-xl ${data.queue.failed > 0 ? 'text-red-400' : 'text-muted'}`}>
              {data.queue.failed}
            </div>
            <div className="text-xs text-muted">Jobs échoués</div>
          </div>
        </div>
      )}

      {/* Tabs */}
      <div className="flex gap-1 bg-surface p-1 rounded-lg w-fit">
        {[
          { id: 'etat',      label: 'État & Pilotage' },
          { id: 'resultats', label: '📊 Voir les résultats' },
        ].map(t => (
          <button key={t.id} onClick={() => setTab(t.id as any)}
            className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${tab === t.id ? 'bg-violet text-white' : 'text-muted hover:text-white'}`}>
            {t.label}
          </button>
        ))}
      </div>

      {/* ─── TAB: ÉTAT ─────────────────────────────────────────────────────── */}
      {tab === 'etat' && (
        <>
          {loading ? (
            <div className="flex justify-center py-16">
              <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
            </div>
          ) : (
            categories.map(category => (
              <div key={category} className="space-y-3">
                <h2 className="text-white font-title font-bold text-base border-b border-border pb-2">
                  {category}
                </h2>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {data!.scrapers
                    .filter(s => s.category === category)
                    .map(scraper => (
                      <ScraperCard
                        key={scraper.id}
                        scraper={scraper}
                        onLaunch={handleLaunch}
                        launching={launching}
                      />
                    ))}
                </div>
              </div>
            ))
          )}

          {/* Links to individual tools */}
          <div className="bg-surface border border-border rounded-xl p-4">
            <div className="text-white font-medium text-sm mb-3">Accès aux outils détaillés</div>
            <div className="flex flex-wrap gap-2">
              {[
                { to: '/contacts/journalistes', label: '🗞️ Journalistes & Publications', desc: 'Gérer les publications, voir les contacts' },
                { to: '/content/lawyers', label: '⚖️ Annuaire Avocats', desc: 'Voir tous les avocats scrapés' },
                { to: '/directories', label: '📚 Annuaires web', desc: 'Configurer les annuaires à scraper' },
                { to: '/content/businesses', label: '🏢 Entreprises', desc: 'Annuaire expat.com' },
                { to: '/content/sites', label: '🌐 Sites web', desc: 'Contacts extraits de sites' },
                { to: '/content/country-directory', label: '🗺️ Annuaire Pays', desc: 'Données par pays' },
                { to: '/admin/scraper', label: '⚙️ Config scraper', desc: 'Paramètres avancés' },
              ].map(link => (
                <Link key={link.to} to={link.to}
                  className="px-3 py-2 bg-surface2 border border-border rounded-lg hover:border-violet/40 hover:bg-violet/10 transition-colors group">
                  <div className="text-white text-xs font-medium group-hover:text-violet-light">{link.label}</div>
                  <div className="text-muted text-[10px]">{link.desc}</div>
                </Link>
              ))}
            </div>
          </div>
        </>
      )}

      {/* ─── TAB: RÉSULTATS ────────────────────────────────────────────────── */}
      {tab === 'resultats' && (
        <div className="space-y-4">
          <p className="text-muted text-sm">Vue unifiée de tous les contacts collectés par les scrapers, avec triage par qualité et déduplication.</p>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {[
              {
                to: '/contacts/base',
                icon: '🗂️',
                label: 'Base Contacts Unifiée',
                desc: 'Tous les contacts de toutes les sources · Triage email vérifié / email présent / site web / rien · Déduplication',
                color: 'border-violet/30 hover:bg-violet/10',
              },
              {
                to: '/contacts',
                icon: '👥',
                label: 'CRM Principal',
                desc: 'Écoles, associations, influenceurs, consulats, blogs, communautés expat… (3 663 contacts)',
                color: 'border-blue-500/30 hover:bg-blue-900/10',
              },
              {
                to: '/contacts/journalistes',
                icon: '🗞️',
                label: 'Journalistes & Presse',
                desc: '135 publications · Journalistes avec email, bylines, inférence',
                color: 'border-green-500/30 hover:bg-green-900/10',
              },
              {
                to: '/content/lawyers',
                icon: '⚖️',
                label: 'Avocats',
                desc: '572 avocats · 100% avec email · Spécialisés immigration & expat',
                color: 'border-yellow-500/30 hover:bg-yellow-900/10',
              },
              {
                to: '/content/businesses',
                icon: '🏢',
                label: 'Entreprises',
                desc: '72 entreprises expat · 71 avec email',
                color: 'border-amber-500/30 hover:bg-amber-900/10',
              },
              {
                to: '/content/contacts',
                icon: '🌐',
                label: 'Contacts web',
                desc: '103 contacts extraits d\'articles · 25 avec email',
                color: 'border-cyan-500/30 hover:bg-cyan-900/10',
              },
            ].map(item => (
              <Link key={item.to} to={item.to}
                className={`bg-surface border ${item.color} rounded-xl p-4 flex gap-3 transition-colors group`}>
                <span className="text-2xl flex-shrink-0">{item.icon}</span>
                <div>
                  <div className="text-white font-medium text-sm group-hover:text-violet-light transition-colors">{item.label}</div>
                  <div className="text-muted text-xs mt-0.5">{item.desc}</div>
                </div>
                <span className="ml-auto text-muted self-center">→</span>
              </Link>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
