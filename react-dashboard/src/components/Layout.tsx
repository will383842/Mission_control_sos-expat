import React, { useState, useEffect } from 'react';
import { Outlet, NavLink, useNavigate, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../hooks/useAuth';
import { useContactTypes } from '../hooks/useContactTypes';
import { useReminders } from '../hooks/useReminders';

// ── Chevron icon ────────────────────────────────────────────
function ChevronIcon({ open }: { open: boolean }) {
  return (
    <svg
      className={`w-3.5 h-3.5 text-muted transition-transform duration-200 ${open ? 'rotate-90' : ''}`}
      fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}
    >
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
    </svg>
  );
}

// ── Section separator ──────────────────────────────────────
function NavSeparator({ label }: { label: string }) {
  return (
    <div className="px-4 pt-4 pb-1">
      <p className="text-[10px] font-semibold uppercase tracking-widest text-gray-600 select-none">{label}</p>
    </div>
  );
}

// ── Collapsible nav sub-group (niveau 2) ───────────────────
function NavSubGroup({
  label,
  children,
  isOpen,
  onToggle,
}: {
  label: string;
  children: React.ReactNode;
  isOpen: boolean;
  onToggle: () => void;
}) {
  return (
    <div>
      <button
        onClick={onToggle}
        className="w-full flex items-center gap-1.5 px-3 pt-3 pb-0.5 group/sub"
      >
        <span className="flex-1 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-600 group-hover/sub:text-gray-400 transition-colors">
          {label}
        </span>
        <svg
          className={`w-3 h-3 text-gray-700 transition-transform duration-200 ${isOpen ? 'rotate-90' : ''}`}
          fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
        </svg>
      </button>
      <div className={`overflow-hidden transition-all duration-200 ${isOpen ? 'max-h-[600px] opacity-100' : 'max-h-0 opacity-0'}`}>
        {children}
      </div>
    </div>
  );
}

// ── Collapsible nav group ───────────────────────────────────
function NavGroup({
  label,
  icon,
  children,
  isOpen,
  onToggle,
  badge,
}: {
  label: string;
  icon: React.ReactNode;
  children: React.ReactNode;
  isOpen: boolean;
  onToggle: () => void;
  badge?: number;
}) {
  return (
    <div>
      <button
        onClick={onToggle}
        className="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium text-gray-400 hover:bg-surface2 hover:text-white transition-colors group"
      >
        <span className="text-base flex-shrink-0">{icon}</span>
        <span className="flex-1 text-left">{label}</span>
        {badge != null && badge > 0 && (
          <span className="bg-amber text-black text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none mr-1">
            {badge}
          </span>
        )}
        <ChevronIcon open={isOpen} />
      </button>
      <div
        className={`overflow-hidden transition-all duration-200 ${
          isOpen ? 'max-h-[2000px] opacity-100' : 'max-h-0 opacity-0'
        }`}
      >
        <div className="ml-4 pl-3 border-l border-border/50 space-y-0.5 py-1">
          {children}
        </div>
      </div>
    </div>
  );
}

// ── localStorage keys ───────────────────────────────────────
const LS_GROUPS    = 'mc_nav_groups';
const LS_SUBGROUPS = 'mc_nav_subgroups';

const DEFAULT_SUBGROUPS: Record<string, boolean> = {
  sourcing_ia        : true,
  sourcing_contacts  : true,
  sourcing_content   : true,
  sourcing_config    : true,
  content_piloter    : true,
  content_contenu    : true,
  content_affiliation: true,
  content_publish    : true,
};

function loadLS<T extends Record<string, boolean>>(key: string, defaults: T): T {
  try {
    const raw = localStorage.getItem(key);
    if (raw) return { ...defaults, ...JSON.parse(raw) };
  } catch { /* ignore */ }
  return defaults;
}

// ── Main Layout ─────────────────────────────────────────────
export default function Layout() {
  const { t } = useTranslation();
  const { user, logout } = useAuth();
  useContactTypes();
  const { reminders } = useReminders();
  const navigate = useNavigate();
  const location = useLocation();
  const [sidebarOpen, setSidebarOpen] = useState(false);

  // Helper: determine which groups should be open for a given path
  const getGroupsForPath = (path: string) => ({
    contacts: path.startsWith('/contacts') || path === '/a-relancer' || path.startsWith('/influenceurs'),
    acquisition: false,
    scraping: path === '/directories' || path === '/contacts/journalistes' || path === '/admin/scraper' || path.startsWith('/content/sites') || path.startsWith('/content/businesses') || path.startsWith('/content/lawyers') || path.startsWith('/content/country-directory') || path.startsWith('/scraping') || path.startsWith('/content/sources') || path.startsWith('/content/countries') || path.startsWith('/content/cities') || path.startsWith('/content/questions') || path.startsWith('/content/affiliates') || path.startsWith('/admin/campaigns') || path === '/ai-research' || path === '/admin/avancement' || path.startsWith('/content/contacts') || path.startsWith('/content/links'),
    contentEngine: (path.startsWith('/content') && !path.startsWith('/content/sources') && !path.startsWith('/content/countries') && !path.startsWith('/content/cities') && !path.startsWith('/content/questions') && !path.startsWith('/content/affiliates') && !path.startsWith('/content/sites') && !path.startsWith('/content/lawyers') && !path.startsWith('/content/businesses') && !path.startsWith('/content/country-directory') && !path.startsWith('/content/landing-generator') && !path.startsWith('/content/landings') && !path.startsWith('/content/republication-rs')) || path.startsWith('/seo') || path === '/publishing' || path === '/media' || path === '/costs' || path === '/translations',
    landingGenerator: path.startsWith('/content/landing-generator') || path.startsWith('/content/landings'),
    republication: path.startsWith('/content/republication-rs'),
    prospection: path.startsWith('/prospection') || path === '/outreach',
    monetiser: path.startsWith('/affiliates'),
    parametres: path.startsWith('/admin/types') || path.startsWith('/admin/prompts') || path.startsWith('/admin/prompt-templates') || path.startsWith('/admin/presets') || path === '/equipe' || path === '/journal',
  });

  // Track which nav groups are expanded — persist dans localStorage
  const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(() =>
    loadLS(LS_GROUPS, getGroupsForPath(window.location.pathname))
  );

  // Track which sub-groups are expanded — persist dans localStorage
  const [openSubGroups, setOpenSubGroups] = useState<Record<string, boolean>>(() =>
    loadLS(LS_SUBGROUPS, DEFAULT_SUBGROUPS)
  );

  // Auto-expand le groupe parent quand on navigue vers une route qu'il contient
  useEffect(() => {
    const needed = getGroupsForPath(location.pathname);
    setOpenGroups(prev => {
      const next = {
        contacts    : prev.contacts     || needed.contacts,
        acquisition : prev.acquisition  || needed.acquisition,
        scraping    : prev.scraping     || needed.scraping,
        contentEngine: prev.contentEngine || needed.contentEngine,
        prospection : prev.prospection  || needed.prospection,
        monetiser   : prev.monetiser    || needed.monetiser,
        parametres  : prev.parametres   || needed.parametres,
      };
      try { localStorage.setItem(LS_GROUPS, JSON.stringify(next)); } catch { /* ignore */ }
      return next;
    });
  }, [location.pathname]);

  const toggleGroup = (key: string) => {
    setOpenGroups(prev => {
      const next = { ...prev, [key]: !prev[key] };
      try { localStorage.setItem(LS_GROUPS, JSON.stringify(next)); } catch { /* ignore */ }
      return next;
    });
  };

  const toggleSubGroup = (key: string) => {
    setOpenSubGroups(prev => {
      const next = { ...prev, [key]: !prev[key] };
      try { localStorage.setItem(LS_SUBGROUPS, JSON.stringify(next)); } catch { /* ignore */ }
      return next;
    });
  };

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  // Close mobile sidebar on Escape
  useEffect(() => {
    if (!sidebarOpen) return;
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setSidebarOpen(false);
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [sidebarOpen]);

  // NOTE: route-change close is handled via handleNavClick on each NavLink.

  const isAdmin = user?.role === 'admin';
  const isManager = user?.role === 'manager';
  const isResearcher = user?.role === 'researcher';
  const canAccessAI = isAdmin || isManager;

  const handleNavClick = () => setSidebarOpen(false);

  // Nav link styles — v2: explicit left accent bar + gradient bg + glow on active
  const navClass = ({ isActive }: { isActive: boolean }) =>
    `relative flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 ${
      isActive
        ? 'bg-gradient-violet-subtle text-white shadow-glow-violet ring-1 ring-violet/40 before:content-[""] before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:h-6 before:w-1 before:rounded-r-full before:bg-gradient-violet before:shadow-glow-violet'
        : 'text-gray-400 hover:bg-white/[0.04] hover:text-white hover:translate-x-0.5'
    }`;

  const subNavClass = ({ isActive }: { isActive: boolean }) =>
    `relative flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] transition-all duration-200 ${
      isActive
        ? 'text-white bg-violet/15 font-medium ring-1 ring-violet/30 before:content-[""] before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:h-4 before:w-0.5 before:rounded-r-full before:bg-violet'
        : 'text-gray-500 hover:text-gray-200 hover:bg-white/[0.04] hover:translate-x-0.5'
    }`;

  return (
    <div className="flex min-h-screen bg-bg">
      {/* Skip link (WCAG) */}
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-1/2 focus:-translate-x-1/2 focus:z-[100] focus:bg-violet focus:text-white focus:px-4 focus:py-2 focus:rounded-lg focus:shadow-lg"
      >
        {t('nav.skipToContent')}
      </a>

      {/* Mobile hamburger */}
      <button
        onClick={() => setSidebarOpen(true)}
        className="md:hidden fixed top-3 left-3 z-50 min-h-touch min-w-touch p-2 bg-surface border border-border rounded-lg text-white focus-visible:outline-2 focus-visible:outline-violet"
        aria-label={t('nav.openMenu')}
        aria-expanded={sidebarOpen}
        aria-controls="main-sidebar"
      >
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
          <line x1="3" y1="5" x2="17" y2="5" />
          <line x1="3" y1="10" x2="17" y2="10" />
          <line x1="3" y1="15" x2="17" y2="15" />
        </svg>
      </button>

      {/* Backdrop */}
      {sidebarOpen && (
        <div
          className="md:hidden fixed inset-0 z-40 bg-black/60 backdrop-blur-sm"
          onClick={() => setSidebarOpen(false)}
          aria-hidden="true"
        />
      )}

      {/* Sidebar */}
      <aside
        id="main-sidebar"
        aria-label="Navigation principale"
        className={`fixed inset-y-0 left-0 z-50 w-64 bg-surface/95 backdrop-blur-xl border-r-2 border-violet/20 flex flex-col transform transition-transform duration-200 ease-in-out shadow-2xl ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        } md:translate-x-0 md:sticky md:top-0 md:h-screen md:flex-shrink-0`}
      >
        {/* Header with prominent branding */}
        <div className="p-5 border-b border-border/70 flex items-center justify-between bg-gradient-violet-subtle">
          <div className="flex items-center gap-3">
            <div className="w-11 h-11 rounded-2xl bg-gradient-violet flex items-center justify-center shadow-glow-violet text-white font-bold font-title text-base ring-2 ring-violet/30">
              MC
            </div>
            <div>
              <h1 className="font-title text-[16px] font-bold text-white leading-tight flex items-center gap-1.5">
                Mission Control
                <span className="text-[9px] font-semibold px-1.5 py-0.5 rounded-md bg-gradient-violet text-white tracking-wider uppercase shadow-sm">v2</span>
              </h1>
              <p className="text-[10px] text-violet-light/80 mt-0.5 tracking-widest uppercase font-semibold">SOS-Expat CRM</p>
            </div>
          </div>
          <button
            onClick={() => setSidebarOpen(false)}
            className="md:hidden min-h-touch min-w-touch inline-flex items-center justify-center text-muted hover:text-white focus-visible:outline-2 focus-visible:outline-violet rounded-lg"
            aria-label={t('nav.closeMenu')}
          >
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
              <line x1="4" y1="4" x2="16" y2="16" />
              <line x1="16" y1="4" x2="4" y2="16" />
            </svg>
          </button>
        </div>

        {/* Navigation */}
        <nav className="flex-1 p-3 space-y-0.5 overflow-y-auto">
          {isResearcher ? (
            /* ═══ Researcher nav ═══ */
            <>
              <NavLink to="/" end className={navClass} onClick={handleNavClick}>
                <span>📊</span> Mon Tableau
              </NavLink>
              <NavLink to="/contacts" className={navClass} onClick={handleNavClick}>
                <span>👥</span> Contacts
              </NavLink>
              <NavLink to="/a-relancer" className={navClass} onClick={handleNavClick}>
                <span>🔔</span> Relances
                {reminders.length > 0 && (
                  <span className="ml-auto bg-amber text-black text-xs font-bold px-1.5 py-0.5 rounded-full">
                    {reminders.length}
                  </span>
                )}
              </NavLink>
              <NavLink to="/journal" className={navClass} onClick={handleNavClick}>
                <span>📝</span> Journal
              </NavLink>
            </>
          ) : (
            /* ═══ Admin / Manager / Member nav ═══ */
            <>
              {/* Dashboard */}
              <NavLink to="/" end className={navClass} onClick={handleNavClick}>
                <span>📊</span> Dashboard
              </NavLink>

              {/* ════════════════════════════════════
                  1. ALIMENTER — sourcer les données
                  ════════════════════════════════════ */}
              {isAdmin && <NavSeparator label="Alimenter" />}

              {isAdmin && (
                <NavGroup
                  label="Sourcing"
                  icon="🔍"
                  isOpen={openGroups.scraping}
                  onToggle={() => toggleGroup('scraping')}
                >
                  <NavLink to="/scraping/dashboard" className={subNavClass} onClick={handleNavClick}>
                    📡 Vue d'ensemble
                  </NavLink>

                  <NavSubGroup label="IA & Automatisation" isOpen={openSubGroups.sourcing_ia} onToggle={() => toggleSubGroup('sourcing_ia')}>
                    {canAccessAI && (
                      <NavLink to="/ai-research" className={subNavClass} onClick={handleNavClick}>
                        🧠 Recherche IA
                      </NavLink>
                    )}
                    {isAdmin && (
                      <>
                        <NavLink to="/admin/campaigns" className={subNavClass} onClick={handleNavClick}>
                          🤖 Campagnes auto
                        </NavLink>
                        <NavLink to="/admin/avancement" className={subNavClass} onClick={handleNavClick}>
                          📈 Couverture
                        </NavLink>
                      </>
                    )}
                  </NavSubGroup>

                  <NavSubGroup label="Annuaires" isOpen={openSubGroups.sourcing_contacts} onToggle={() => toggleSubGroup('sourcing_contacts')}>
                    <NavLink to="/directories" className={subNavClass} onClick={handleNavClick}>
                      📚 Annuaires web
                    </NavLink>
                    {/* P4 Option 1 (2026-04-21) : Entreprises (/content/businesses) et
                        Contacts web (/content/contacts) sont retires de la sidebar.
                        Les routes sont conservees (accessibles via URL directe). Leurs
                        contacts sont visibles dans "Tous les contacts" via chips par
                        source_origin (content_businesses, content_contacts). */}
                  </NavSubGroup>

                  <NavSubGroup label="Données contenu" isOpen={openSubGroups.sourcing_content} onToggle={() => toggleSubGroup('sourcing_content')}>
                    <NavLink to="/content/sites" className={subNavClass} onClick={handleNavClick}>
                      🌐 Sites web
                    </NavLink>
                    <NavLink to="/content/links" className={subNavClass} onClick={handleNavClick}>
                      🔗 Liens extraits
                    </NavLink>
                    <NavLink to="/content/countries" className={subNavClass} onClick={handleNavClick}>
                      🌍 Fiches Pays
                    </NavLink>
                    <NavLink to="/content/cities" className={subNavClass} onClick={handleNavClick}>
                      🏙️ Fiches Villes
                    </NavLink>
                    <NavLink to="/content/questions" className={subNavClass} onClick={handleNavClick}>
                      💬 Q&A Forum
                    </NavLink>
                    <NavLink to="/content/affiliates" className={subNavClass} onClick={handleNavClick}>
                      🔗 Liens Affiliés
                    </NavLink>
                  </NavSubGroup>

                  <NavSubGroup label="Config" isOpen={openSubGroups.sourcing_config} onToggle={() => toggleSubGroup('sourcing_config')}>
                    <NavLink to="/admin/scraper" className={subNavClass} onClick={handleNavClick}>
                      ⚙️ Configuration scraper
                    </NavLink>
                  </NavSubGroup>
                </NavGroup>
              )}

              {/* ════════════════════════════════════
                  2. GÉRER — CRM contacts
                  ════════════════════════════════════ */}
              <NavSeparator label="Gérer" />

              <NavGroup
                label="Contacts"
                icon="👥"
                isOpen={openGroups.contacts}
                onToggle={() => toggleGroup('contacts')}
                badge={reminders.length}
              >
                {/* Tous les contacts (avec chips de catégories/sous-types dans la page) */}
                <NavLink to="/contacts" end className={subNavClass} onClick={handleNavClick}>
                  👥 Tous les contacts
                </NavLink>

                {/* P4 refactor (2026-04-21) : les 5 sous-catégories (Institutionnel,
                    Médias, Services B2B, Communautés, Digital) sont retirées de la
                    sidebar. Elles restent accessibles via les chips/cards sur la page
                    /contacts et via les URLs /contacts/<category> (routes conservées). */}

                {/* P4 Option 1 (2026-04-21) : Journalistes & Presse et Avocats
                    sont retires de la sidebar utilisateur. Leurs contacts sont
                    visibles dans "Tous les contacts" via chips de filtre par
                    contact_type (presse, avocat). Les outils de scraping specialises
                    restent accessibles aux admins via URL directe :
                    - /contacts/journalistes (scrape publications, infer emails)
                    - /content/lawyers (LawyerDirectory + scrape all/sources) */}
                <NavLink to="/content/country-directory" className={subNavClass} onClick={handleNavClick}>
                  🗺️ Annuaire Pays
                </NavLink>

                {/* ── Actions ── */}
                <NavLink to="/a-relancer" className={subNavClass} onClick={handleNavClick}>
                  <span className="flex items-center gap-2 w-full">
                    🔔 Relances
                    {reminders.length > 0 && (
                      <span className="ml-auto bg-amber text-black text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">
                        {reminders.length}
                      </span>
                    )}
                  </span>
                </NavLink>
                <NavLink to="/contacts/base" className={subNavClass} onClick={handleNavClick}>
                  🗂️ Triage & Doublons
                </NavLink>
              </NavGroup>

              {/* ════════════════════════════════════
                  3. CONTACTER — outreach & prospection
                  ════════════════════════════════════ */}
              {isAdmin && <NavSeparator label="Contacter" />}

              {isAdmin && (
                <NavGroup
                  label="Prospection"
                  icon="✉️"
                  isOpen={openGroups.prospection}
                  onToggle={() => toggleGroup('prospection')}
                >
                  <NavLink to="/prospection" end className={subNavClass} onClick={handleNavClick}>
                    🏠 Hub
                  </NavLink>
                  <NavLink to="/prospection/campaign" className={subNavClass} onClick={handleNavClick}>
                    🎯 Lancer campagne
                  </NavLink>
                  <NavLink to="/prospection/emails" className={subNavClass} onClick={handleNavClick}>
                    📧 Emails
                  </NavLink>
                  <NavLink to="/prospection/sequences" className={subNavClass} onClick={handleNavClick}>
                    🔄 Séquences
                  </NavLink>
                  <NavLink to="/outreach" className={subNavClass} onClick={handleNavClick}>
                    📝 Templates
                  </NavLink>
                  <NavLink to="/prospection/config" className={subNavClass} onClick={handleNavClick}>
                    ⚙️ Configuration
                  </NavLink>
                </NavGroup>
              )}

              {/* ════════════════════════════════════
                  4. CRÉER — produire du contenu
                  ════════════════════════════════════ */}
              {isAdmin && <NavSeparator label="Créer" />}

              {isAdmin && (
                <NavGroup
                  label="Content Generator"
                  icon="✍️"
                  isOpen={openGroups.contentEngine}
                  onToggle={() => toggleGroup('contentEngine')}
                >
                  <NavSubGroup label="Piloter" isOpen={openSubGroups.content_piloter} onToggle={() => toggleSubGroup('content_piloter')}>
                    <NavLink to="/content/orchestrator" className={subNavClass} onClick={handleNavClick}>
                      🎯 Orchestrator
                    </NavLink>
                    <NavLink to="/content/command-center" className={subNavClass} onClick={handleNavClick}>
                      ⚡ Command Center
                    </NavLink>
                    <NavLink to="/content/overview" className={subNavClass} onClick={handleNavClick}>
                      📊 Vue d'ensemble
                    </NavLink>
                    <NavLink to="/content/sources" className={subNavClass} onClick={handleNavClick}>
                      🗂️ Sources de génération
                    </NavLink>
                  </NavSubGroup>

                  <NavSubGroup label="Contenu" isOpen={openSubGroups.content_contenu} onToggle={() => toggleSubGroup('content_contenu')}>
                    <NavLink to="/content/generate-qr" className={subNavClass} onClick={handleNavClick}>
                      ❓ Q/R
                    </NavLink>
                    <NavLink to="/content/news" className={subNavClass} onClick={handleNavClick}>
                      📰 News RSS
                    </NavLink>
                    <NavLink to="/content/fiches-general" className={subNavClass} onClick={handleNavClick}>
                      🌍 Fiches Pays
                    </NavLink>
                    <NavLink to="/content/fiches-expatriation" className={subNavClass} onClick={handleNavClick}>
                      ✈️ Fiches Pays Expat
                    </NavLink>
                    <NavLink to="/content/fiches-vacances" className={subNavClass} onClick={handleNavClick}>
                      🏖️ Fiches Pays Vacances
                    </NavLink>
                    <NavLink to="/content/fiches-villes" className={subNavClass} onClick={handleNavClick}>
                      🏙️ Fiches Villes
                    </NavLink>
                    <NavLink to="/content/chatters" className={subNavClass} onClick={handleNavClick}>
                      💬 Chatters
                    </NavLink>
                    <NavLink to="/content/influenceurs" className={subNavClass} onClick={handleNavClick}>
                      📢 Influenceurs
                    </NavLink>
                    <NavLink to="/content/admin-groupes" className={subNavClass} onClick={handleNavClick}>
                      👥 Admin Groupes
                    </NavLink>
                    <NavLink to="/content/avocats" className={subNavClass} onClick={handleNavClick}>
                      ⚖️ Avocats
                    </NavLink>
                    <NavLink to="/content/expats-aidants" className={subNavClass} onClick={handleNavClick}>
                      🧳 Expats Aidants
                    </NavLink>
                    <NavLink to="/content/art-mots-cles" className={subNavClass} onClick={handleNavClick}>
                      🔑 Art Mots Cles
                    </NavLink>
                    <NavLink to="/content/longues-traines" className={subNavClass} onClick={handleNavClick}>
                      🎯 Art Longues Traines
                    </NavLink>
                    <NavLink to="/content/tutoriels" className={subNavClass} onClick={handleNavClick}>
                      📖 Tutoriels
                    </NavLink>
<NavLink to="/content/articles" className={subNavClass} onClick={handleNavClick}>
                      📝 Art Titre Manuel
                    </NavLink>
                    <NavLink to="/content/comparatives" className={subNavClass} onClick={handleNavClick}>
                      ⚖️ Comparatifs SEO
                    </NavLink>
                    <NavLink to="/content/temoignages" className={subNavClass} onClick={handleNavClick}>
                      💬 Temoignages
                    </NavLink>
                    <NavLink to="/content/souffrances" className={subNavClass} onClick={handleNavClick}>
                      😔 Souffrances
                    </NavLink>
                    <NavLink to="/content/sondages" className={subNavClass} onClick={handleNavClick}>
                      📊 Sondages
                    </NavLink>
                    <NavLink to="/content/statistiques" className={subNavClass} onClick={handleNavClick}>
                      📈 Statistiques
                    </NavLink>
                    <NavLink to="/content/outils-visiteurs" className={subNavClass} onClick={handleNavClick}>
                      🌐 Outils Visiteurs
                    </NavLink>
                    <NavLink to="/content/clusters" className={subNavClass} onClick={handleNavClick}>
                      🔵 Clusters
                    </NavLink>
                  </NavSubGroup>

                  <NavSubGroup label="Affiliation" isOpen={openSubGroups.content_affiliation} onToggle={() => toggleSubGroup('content_affiliation')}>
                    <NavLink to="/content/affiliate-comparatives" className={subNavClass} onClick={handleNavClick}>
                      💰 Comparatifs Affilies
                    </NavLink>
                    <NavLink to="/content/affiliate-programs" className={subNavClass} onClick={handleNavClick}>
                      🤝 Programmes
                    </NavLink>
                  </NavSubGroup>

                  <NavSubGroup label="Optimiser & Publier" isOpen={openSubGroups.content_publish} onToggle={() => toggleSubGroup('content_publish')}>
                    <NavLink to="/content/quality" className={subNavClass} onClick={handleNavClick}>
                      ✅ Qualité
                    </NavLink>
                    <NavLink to="/seo" end className={subNavClass} onClick={handleNavClick}>
                      🔍 SEO
                    </NavLink>
                    <NavLink to="/seo/keywords" className={subNavClass} onClick={handleNavClick}>
                      🔑 Mots-clés
                    </NavLink>
                    <NavLink to="/seo/internal-links" className={subNavClass} onClick={handleNavClick}>
                      🕸️ Maillage interne
                    </NavLink>
                    <NavLink to="/publishing" className={subNavClass} onClick={handleNavClick}>
                      📤 Publication
                    </NavLink>
                    <NavLink to="/translations" className={subNavClass} onClick={handleNavClick}>
                      🌐 Traductions
                    </NavLink>
                    <NavLink to="/media" className={subNavClass} onClick={handleNavClick}>
                      🖼️ Médias
                    </NavLink>
                    <NavLink to="/costs" className={subNavClass} onClick={handleNavClick}>
                      💰 Coûts IA
                    </NavLink>
                    <NavLink to="/content/templates" className={subNavClass} onClick={handleNavClick}>
                      🧩 Templates
                    </NavLink>
                  </NavSubGroup>
                </NavGroup>
              )}

              {isAdmin && (
                <NavGroup
                  label="Landing Generator"
                  icon="🎯"
                  isOpen={openGroups.landingGenerator}
                  onToggle={() => toggleGroup('landingGenerator')}
                >
                  <NavLink to="/content/landing-generator" end className={subNavClass} onClick={handleNavClick}>
                    🏠 Vue d'ensemble
                  </NavLink>
                  <NavLink to="/content/landing-generator/clients" className={subNavClass} onClick={handleNavClick}>
                    👤 Clients
                  </NavLink>
                  <NavLink to="/content/landing-generator/avocats" className={subNavClass} onClick={handleNavClick}>
                    ⚖️ Avocats
                  </NavLink>
                  <NavLink to="/content/landing-generator/helpers" className={subNavClass} onClick={handleNavClick}>
                    🧳 Helpers
                  </NavLink>
                  <NavLink to="/content/landing-generator/matching" className={subNavClass} onClick={handleNavClick}>
                    🎯 Matching
                  </NavLink>
                  <NavLink to="/content/landing-generator/piliers" className={subNavClass} onClick={handleNavClick}>
                    🏛️ Piliers catégories
                  </NavLink>
                  <NavLink to="/content/landing-generator/profils" className={subNavClass} onClick={handleNavClick}>
                    🧑‍💻 Profils expatriés
                  </NavLink>
                  <NavLink to="/content/landing-generator/urgences" className={subNavClass} onClick={handleNavClick}>
                    🚨 Urgences
                  </NavLink>
                  <NavLink to="/content/landing-generator/nationalites" className={subNavClass} onClick={handleNavClick}>
                    🌍 Nationalités
                  </NavLink>
                  <NavLink to="/content/landings" className={subNavClass} onClick={handleNavClick}>
                    📄 Toutes les LPs
                  </NavLink>
                </NavGroup>
              )}

              {isAdmin && (
                <NavGroup
                  label="Republication RS"
                  icon="📣"
                  isOpen={openGroups.republication}
                  onToggle={() => toggleGroup('republication')}
                >
                  <NavLink to="/content/republication-rs/linkedin" className={subNavClass} onClick={handleNavClick}>
                    💼 LinkedIn
                  </NavLink>
                  <NavLink to="/content/republication-rs/pinterest" className={subNavClass} onClick={handleNavClick}>
                    📌 Pinterest
                  </NavLink>
                  <NavLink to="/content/republication-rs/threads" className={subNavClass} onClick={handleNavClick}>
                    🧵 Threads
                  </NavLink>
                  <NavLink to="/content/republication-rs/facebook" className={subNavClass} onClick={handleNavClick}>
                    📘 Facebook
                  </NavLink>
                  <NavLink to="/content/republication-rs/instagram" className={subNavClass} onClick={handleNavClick}>
                    📸 Instagram
                  </NavLink>
                  <NavLink to="/content/republication-rs/reddit" className={subNavClass} onClick={handleNavClick}>
                    🤖 Reddit
                  </NavLink>
                </NavGroup>
              )}

              {/* ════════════════════════════════════
                  5. MONÉTISER — affiliation & revenus
                  ════════════════════════════════════ */}
              {isAdmin && <NavSeparator label="Monétiser" />}

              {isAdmin && (
                <NavGroup
                  label="Affiliés"
                  icon="💸"
                  isOpen={openGroups.monetiser}
                  onToggle={() => toggleGroup('monetiser')}
                >
                  <NavLink to="/affiliates" end className={subNavClass} onClick={handleNavClick}>
                    🗺️ Cartographie & revenus
                  </NavLink>
                  <NavLink to="/content/affiliates" className={subNavClass} onClick={handleNavClick}>
                    🔗 Liens détectés (scraping)
                  </NavLink>
                </NavGroup>
              )}

              {/* ════════════════════════════════════
                  6. CONFIGURER — paramètres & équipe
                  ════════════════════════════════════ */}
              {isAdmin && <NavSeparator label="Configurer" />}

              {isAdmin && (
                <NavGroup
                  label="Paramètres"
                  icon="⚙️"
                  isOpen={openGroups.parametres}
                  onToggle={() => toggleGroup('parametres')}
                >
                  <NavLink to="/admin/types" className={subNavClass} onClick={handleNavClick}>
                    Types de contacts
                  </NavLink>
                  <NavLink to="/admin/prompts" className={subNavClass} onClick={handleNavClick}>
                    Prompts IA
                  </NavLink>
                  <NavLink to="/admin/prompt-templates" className={subNavClass} onClick={handleNavClick}>
                    Prompts Content
                  </NavLink>
                  <NavLink to="/admin/presets" className={subNavClass} onClick={handleNavClick}>
                    Presets génération
                  </NavLink>
                  <NavLink to="/equipe" className={subNavClass} onClick={handleNavClick}>
                    Équipe & Objectifs
                  </NavLink>
                  <NavLink to="/journal" className={subNavClass} onClick={handleNavClick}>
                    Journal
                  </NavLink>
                  <NavLink to="/settings/api-balance" className={subNavClass} onClick={handleNavClick}>
                    💳 Soldes API IA
                  </NavLink>
                </NavGroup>
              )}

              {/* Items visible to all non-researcher roles */}
              {!isAdmin && (
                <>
                  <NavLink to="/outreach" className={navClass} onClick={handleNavClick}>
                    <span>✉️</span> Templates
                  </NavLink>
                  <NavLink to="/journal" className={navClass} onClick={handleNavClick}>
                    <span>📝</span> Journal
                  </NavLink>
                </>
              )}
            </>
          )}
        </nav>


        {/* Quick links — external */}
        <div className="px-4 pt-3 pb-1">
          <p className="text-[10px] font-semibold uppercase tracking-widest text-gray-600 select-none">Liens rapides</p>
        </div>
        <div className="px-2 space-y-0.5 pb-3">
          <a href="https://blog.life-expat.com/admin" target="_blank" rel="noopener"
             className="flex items-center gap-2.5 px-3 py-2 rounded-lg text-[13px] text-muted hover:bg-white/5 hover:text-white transition-colors">
            <span className="text-base">📝</span> Blog Admin
            <svg className="w-3 h-3 ml-auto text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
          </a>
        </div>

        {/* User footer */}
        <div className="p-3 border-t border-border/70 bg-gradient-to-t from-violet/[0.06] to-transparent">
          <div className="flex items-center gap-3 px-2 py-2 rounded-xl hover:bg-white/[0.04] transition-all duration-200 group">
            <div className="w-9 h-9 rounded-xl bg-gradient-violet flex items-center justify-center text-white font-bold text-sm flex-shrink-0 shadow-glow-violet ring-1 ring-violet/30">
              {user?.name?.[0]?.toUpperCase() ?? '?'}
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-sm font-medium text-white truncate leading-tight">{user?.name}</p>
              <p className="text-[11px] text-muted capitalize leading-tight">{user?.role}</p>
            </div>
            <button
              onClick={handleLogout}
              title="Déconnexion"
              className="flex-shrink-0 text-gray-600 hover:text-red-400 transition-colors opacity-0 group-hover:opacity-100"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
              </svg>
            </button>
          </div>
        </div>
      </aside>

      {/* Main content */}
      {/* NOTE: overflow-auto intentionally removed — it created a scroll container that
          prevented native <select> dropdowns from opening in Chrome 120+ (dropdown
          clipped by overflow context). Page-level scroll via body is used instead.
          Sidebar uses md:sticky md:h-screen to remain visible on scroll. */}
      <main id="main-content" tabIndex={-1} className="flex-1 min-h-screen focus:outline-none">
        <Outlet />
      </main>
    </div>
  );
}
