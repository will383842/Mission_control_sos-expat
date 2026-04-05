import React, { useState, useEffect } from 'react';
import { Outlet, NavLink, useNavigate, useLocation } from 'react-router-dom';
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
  sourcing_ia       : true,
  sourcing_contacts : true,
  sourcing_content  : true,
  sourcing_config   : true,
  content_piloter   : true,
  content_contenu   : true,
  content_publish   : true,
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
    contentEngine: (path.startsWith('/content') && !path.startsWith('/content/sources') && !path.startsWith('/content/countries') && !path.startsWith('/content/cities') && !path.startsWith('/content/questions') && !path.startsWith('/content/affiliates') && !path.startsWith('/content/sites') && !path.startsWith('/content/lawyers') && !path.startsWith('/content/businesses') && !path.startsWith('/content/country-directory')) || path.startsWith('/seo') || path === '/publishing' || path === '/media' || path === '/costs' || path === '/translations',
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

  const isAdmin = user?.role === 'admin';
  const isManager = user?.role === 'manager';
  const isResearcher = user?.role === 'researcher';
  const canAccessAI = isAdmin || isManager;

  const handleNavClick = () => setSidebarOpen(false);

  // Nav link styles
  const navClass = ({ isActive }: { isActive: boolean }) =>
    `flex items-center gap-3 px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
      isActive
        ? 'bg-violet/20 text-white'
        : 'text-gray-400 hover:bg-white/5 hover:text-white'
    }`;

  const subNavClass = ({ isActive }: { isActive: boolean }) =>
    `flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] transition-colors ${
      isActive
        ? 'text-white bg-violet/20 font-medium'
        : 'text-gray-500 hover:text-gray-200 hover:bg-white/5'
    }`;

  return (
    <div className="flex min-h-screen bg-bg">
      {/* Mobile hamburger */}
      <button
        onClick={() => setSidebarOpen(true)}
        className="md:hidden fixed top-3 left-3 z-50 p-2 bg-surface border border-border rounded-lg text-white"
        aria-label="Menu"
      >
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
          <line x1="3" y1="5" x2="17" y2="5" />
          <line x1="3" y1="10" x2="17" y2="10" />
          <line x1="3" y1="15" x2="17" y2="15" />
        </svg>
      </button>

      {/* Backdrop */}
      {sidebarOpen && (
        <div className="md:hidden fixed inset-0 z-40 bg-black/60" onClick={() => setSidebarOpen(false)} />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed inset-y-0 left-0 z-50 w-60 bg-surface border-r border-border flex flex-col transform transition-transform duration-200 ease-in-out ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        } md:translate-x-0 md:sticky md:top-0 md:h-screen md:flex-shrink-0`}
      >
        {/* Header */}
        <div className="p-5 border-b border-border flex items-center justify-between">
          <div>
            <h1 className="font-title text-lg font-bold text-white">Mission Control</h1>
            <p className="text-[10px] text-muted mt-0.5">SOS-Expat CRM</p>
          </div>
          <button
            onClick={() => setSidebarOpen(false)}
            className="md:hidden text-muted hover:text-white"
            aria-label="Fermer"
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

                  <NavSubGroup label="Contacts" isOpen={openSubGroups.sourcing_contacts} onToggle={() => toggleSubGroup('sourcing_contacts')}>
                    <NavLink to="/contacts/journalistes" className={subNavClass} onClick={handleNavClick}>
                      🗞️ Journalistes & Presse
                    </NavLink>
                    <NavLink to="/directories" className={subNavClass} onClick={handleNavClick}>
                      📚 Annuaires web
                    </NavLink>
                    <NavLink to="/content/businesses" className={subNavClass} onClick={handleNavClick}>
                      🏢 Entreprises
                    </NavLink>
                    <NavLink to="/content/contacts" className={subNavClass} onClick={handleNavClick}>
                      🌐 Contacts web
                    </NavLink>
                  </NavSubGroup>

                  <NavSubGroup label="Données contenu" isOpen={openSubGroups.sourcing_content} onToggle={() => toggleSubGroup('sourcing_content')}>
                    <NavLink to="/content/sources" className={subNavClass} onClick={handleNavClick}>
                      🗂️ Sources de génération
                    </NavLink>
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
                {/* Tous les contacts */}
                <NavLink to="/contacts" end className={subNavClass} onClick={handleNavClick}>
                  👥 Tous les contacts
                </NavLink>

                {/* ── Catégories — routes dédiées, remount garanti ── */}
                <NavLink to="/contacts/institutionnel" className={subNavClass} onClick={handleNavClick}>
                  🏛️ Institutionnel
                </NavLink>
                <NavLink to="/contacts/medias-influence" className={subNavClass} onClick={handleNavClick}>
                  📺 Médias & Influence
                </NavLink>
                <NavLink to="/contacts/youtubeurs" className={subNavClass} onClick={handleNavClick}>
                  ▶️ YouTubeurs
                </NavLink>
                <NavLink to="/contacts/instagrammeurs" className={subNavClass} onClick={handleNavClick}>
                  📸 Instagrammeurs
                </NavLink>
                <NavLink to="/contacts/services-b2b" className={subNavClass} onClick={handleNavClick}>
                  💼 Services B2B
                </NavLink>
                <NavLink to="/contacts/communautes" className={subNavClass} onClick={handleNavClick}>
                  🌍 Communautés
                </NavLink>
                <NavLink to="/contacts/digital" className={subNavClass} onClick={handleNavClick}>
                  🔗 Digital & SEO
                </NavLink>
                <NavLink to="/contacts/ecoles" className={subNavClass} onClick={handleNavClick}>
                  🏫 Écoles
                </NavLink>
                <NavLink to="/contacts/ufe" className={subNavClass} onClick={handleNavClick}>
                  🇫🇷 UFE Monde
                </NavLink>
                <NavLink to="/contacts/alliance-francaise" className={subNavClass} onClick={handleNavClick}>
                  🎭 Alliance Française
                </NavLink>
                <NavLink to="/content/lawyers" className={subNavClass} onClick={handleNavClick}>
                  ⚖️ Avocats
                </NavLink>
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
                    <NavLink to="/content/command-center" className={subNavClass} onClick={handleNavClick}>
                      ⚡ Command Center
                    </NavLink>
                    <NavLink to="/content/overview" className={subNavClass} onClick={handleNavClick}>
                      📊 Vue d'ensemble
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
                    <NavLink to="/content/articles" className={subNavClass} onClick={handleNavClick}>
                      📝 Articles
                    </NavLink>
                    <NavLink to="/content/templates" className={subNavClass} onClick={handleNavClick}>
                      🧩 Templates
                    </NavLink>
                    <NavLink to="/content/comparatives" className={subNavClass} onClick={handleNavClick}>
                      ⚖️ Comparatifs
                    </NavLink>
                    <NavLink to="/content/sondages" className={subNavClass} onClick={handleNavClick}>
                      📊 Sondages
                    </NavLink>
                    <NavLink to="/content/outils-visiteurs" className={subNavClass} onClick={handleNavClick}>
                      🌐 Outils Visiteurs
                    </NavLink>
                    <NavLink to="/content/clusters" className={subNavClass} onClick={handleNavClick}>
                      🔵 Clusters
                    </NavLink>
                    <NavLink to="/content/landings" className={subNavClass} onClick={handleNavClick}>
                      🛬 Landings
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
                  </NavSubGroup>
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

        {/* Footer raccourcis rapides */}
        {isAdmin && (
          <div className="px-3 py-2 border-t border-border">
            <p className="text-[10px] font-semibold uppercase tracking-widest text-gray-600 mb-2 px-1">Raccourcis</p>
            <div className="flex gap-1">
              <NavLink
                to="/directories"
                title="Annuaires"
                className={({ isActive }) =>
                  `flex-1 flex flex-col items-center gap-1 py-2 rounded-lg text-xs transition-colors ${
                    isActive ? 'bg-violet/20 text-violet-light' : 'text-muted hover:bg-white/5 hover:text-white'
                  }`
                }
                onClick={handleNavClick}
              >
                <span className="text-lg">📁</span>
                <span className="text-[10px] leading-none">Annuaires</span>
              </NavLink>
              <NavLink
                to="/content/sondages"
                title="Sondages"
                className={({ isActive }) =>
                  `flex-1 flex flex-col items-center gap-1 py-2 rounded-lg text-xs transition-colors ${
                    isActive ? 'bg-violet/20 text-violet-light' : 'text-muted hover:bg-white/5 hover:text-white'
                  }`
                }
                onClick={handleNavClick}
              >
                <span className="text-lg">📊</span>
                <span className="text-[10px] leading-none">Sondages</span>
              </NavLink>
              <NavLink
                to="/content/outils-visiteurs"
                title="Outils Visiteurs"
                className={({ isActive }) =>
                  `flex-1 flex flex-col items-center gap-1 py-2 rounded-lg text-xs transition-colors ${
                    isActive ? 'bg-violet/20 text-violet-light' : 'text-muted hover:bg-white/5 hover:text-white'
                  }`
                }
                onClick={handleNavClick}
              >
                <span className="text-lg">🌐</span>
                <span className="text-[10px] leading-none">Outils Visiteurs</span>
              </NavLink>
            </div>
          </div>
        )}

        {/* User footer */}
        <div className="p-3 border-t border-border">
          <div className="flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-white/5 transition-colors group">
            <div className="w-8 h-8 rounded-full bg-violet/30 flex items-center justify-center text-violet-light font-bold text-sm flex-shrink-0 ring-1 ring-violet/20">
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
      <main className="flex-1 min-h-screen">
        <Outlet />
      </main>
    </div>
  );
}
