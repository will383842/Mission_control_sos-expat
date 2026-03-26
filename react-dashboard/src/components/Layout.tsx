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

// ── Collapsible nav group ───────────────────────────────────
function NavGroup({
  label,
  icon,
  children,
  isOpen,
  onToggle,
}: {
  label: string;
  icon: React.ReactNode;
  children: React.ReactNode;
  isOpen: boolean;
  onToggle: () => void;
}) {
  return (
    <div>
      <button
        onClick={onToggle}
        className="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium text-gray-400 hover:bg-surface2 hover:text-white transition-colors"
      >
        <span className="text-base flex-shrink-0">{icon}</span>
        <span className="flex-1 text-left">{label}</span>
        <ChevronIcon open={isOpen} />
      </button>
      <div
        className={`overflow-hidden transition-all duration-200 ${
          isOpen ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0'
        }`}
      >
        <div className="ml-4 pl-3 border-l border-border/50 space-y-0.5 py-1">
          {children}
        </div>
      </div>
    </div>
  );
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
    contacts: path.startsWith('/influenceurs') || path === '/a-relancer',
    acquisition: path.startsWith('/admin/campaigns') || path === '/ai-research' || path === '/directories' || path === '/admin/avancement',
    content: path.startsWith('/content'),
    contentEngine: path === '/content/overview' || path === '/content/scheduler' || path.startsWith('/content/articles') || path.startsWith('/content/comparatives') || path.startsWith('/content/campaigns') || path.startsWith('/content/clusters') || path.startsWith('/content/qa') || path.startsWith('/content/question-clusters') || path.startsWith('/content/landings') || path.startsWith('/content/press'),
    seo: path.startsWith('/seo'),
    publication: path === '/publishing' || path === '/media' || path === '/costs',
    translations: path === '/translations',
    prospection: path.startsWith('/prospection') || path === '/outreach',
    parametres: path.startsWith('/admin/types') || path.startsWith('/admin/prompts') || path.startsWith('/admin/scraper') || path.startsWith('/admin/prompt-templates') || path.startsWith('/admin/presets') || path === '/equipe' || path === '/journal',
  });

  // Track which nav groups are expanded
  const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(() => getGroupsForPath(window.location.pathname));

  // Auto-expand the relevant group when route changes (but never auto-close user-opened groups)
  useEffect(() => {
    const needed = getGroupsForPath(location.pathname);
    setOpenGroups(prev => ({
      contacts: prev.contacts || needed.contacts,
      acquisition: prev.acquisition || needed.acquisition,
      content: prev.content || needed.content,
      contentEngine: prev.contentEngine || needed.contentEngine,
      seo: prev.seo || needed.seo,
      publication: prev.publication || needed.publication,
      translations: prev.translations || needed.translations,
      prospection: prev.prospection || needed.prospection,
      parametres: prev.parametres || needed.parametres,
    }));
  }, [location.pathname]);

  const toggleGroup = (key: string) => {
    setOpenGroups(prev => ({ ...prev, [key]: !prev[key] }));
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
        ? 'bg-violet/20 text-violet-light'
        : 'text-gray-400 hover:bg-surface2 hover:text-white'
    }`;

  const subNavClass = ({ isActive }: { isActive: boolean }) =>
    `flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] transition-colors ${
      isActive
        ? 'text-violet-light bg-violet/10'
        : 'text-gray-500 hover:text-gray-300 hover:bg-surface2/50'
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
        } md:translate-x-0 md:static md:flex-shrink-0`}
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
              <NavLink to="/influenceurs" className={navClass} onClick={handleNavClick}>
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
              {/* 1. Dashboard */}
              <NavLink to="/" end className={navClass} onClick={handleNavClick}>
                <span>📊</span> Dashboard
              </NavLink>

              {/* 2. Contacts (group) */}
              <NavGroup
                label="Contacts"
                icon="👥"
                isOpen={openGroups.contacts}
                onToggle={() => toggleGroup('contacts')}
              >
                <NavLink to="/influenceurs" className={subNavClass} onClick={handleNavClick}>
                  Liste des contacts
                </NavLink>
                <NavLink to="/a-relancer" className={subNavClass} onClick={handleNavClick}>
                  <span className="flex items-center gap-2">
                    Relances
                    {reminders.length > 0 && (
                      <span className="bg-amber text-black text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">
                        {reminders.length}
                      </span>
                    )}
                  </span>
                </NavLink>
              </NavGroup>

              {/* 3. Acquisition (group) - admin/manager */}
              {canAccessAI && (
                <NavGroup
                  label="Acquisition"
                  icon="🚀"
                  isOpen={openGroups.acquisition}
                  onToggle={() => toggleGroup('acquisition')}
                >
                  {isAdmin && (
                    <NavLink to="/admin/campaigns" className={subNavClass} onClick={handleNavClick}>
                      Campagnes auto
                    </NavLink>
                  )}
                  <NavLink to="/ai-research" className={subNavClass} onClick={handleNavClick}>
                    Recherche IA
                  </NavLink>
                  <NavLink to="/directories" className={subNavClass} onClick={handleNavClick}>
                    Annuaires
                  </NavLink>
                  {isAdmin && (
                    <NavLink to="/admin/avancement" className={subNavClass} onClick={handleNavClick}>
                      Couverture
                    </NavLink>
                  )}
                </NavGroup>
              )}

              {/* 4. Prospection (group) - admin only */}
              {isAdmin && (
                <NavGroup
                  label="Prospection"
                  icon="✉️"
                  isOpen={openGroups.prospection}
                  onToggle={() => toggleGroup('prospection')}
                >
                  <NavLink to="/prospection" end className={subNavClass} onClick={handleNavClick}>
                    Hub
                  </NavLink>
                  <NavLink to="/prospection/campaign" className={subNavClass} onClick={handleNavClick}>
                    Lancer campagne
                  </NavLink>
                  <NavLink to="/prospection/emails" className={subNavClass} onClick={handleNavClick}>
                    Emails
                  </NavLink>
                  <NavLink to="/prospection/sequences" className={subNavClass} onClick={handleNavClick}>
                    Sequences
                  </NavLink>
                  <NavLink to="/outreach" className={subNavClass} onClick={handleNavClick}>
                    Templates
                  </NavLink>
                  <NavLink to="/prospection/config" className={subNavClass} onClick={handleNavClick}>
                    Configuration
                  </NavLink>
                </NavGroup>
              )}

              {/* 5. Content (group) - admin only */}
              {isAdmin && (
                <NavGroup
                  label="Content"
                  icon="📄"
                  isOpen={openGroups.content}
                  onToggle={() => toggleGroup('content')}
                >
                  <NavLink to="/content/sources" className={subNavClass} onClick={handleNavClick}>
                    Sources Generation
                  </NavLink>
                  <NavLink to="/content" end className={subNavClass} onClick={handleNavClick}>
                    Dashboard
                  </NavLink>
                  <NavLink to="/content/sites" className={subNavClass} onClick={handleNavClick}>
                    Les Sites
                  </NavLink>
                  <NavLink to="/content/businesses" className={subNavClass} onClick={handleNavClick}>
                    Annuaire
                  </NavLink>
                  <NavLink to="/content/countries" className={subNavClass} onClick={handleNavClick}>
                    Fiches Pays
                  </NavLink>
                  <NavLink to="/content/contacts" className={subNavClass} onClick={handleNavClick}>
                    Contacts
                  </NavLink>
                  <NavLink to="/content/questions" className={subNavClass} onClick={handleNavClick}>
                    Q&A Forum
                  </NavLink>
                  <NavLink to="/content/affiliates" className={subNavClass} onClick={handleNavClick}>
                    Liens Affilies
                  </NavLink>
                  <NavLink to="/content/links" className={subNavClass} onClick={handleNavClick}>
                    Tous les liens
                  </NavLink>
                  <NavLink to="/content/data-cleanup" className={subNavClass} onClick={handleNavClick}>
                    Donnees brutes (traite)
                  </NavLink>
                </NavGroup>
              )}

              {/* Content Engine v2 - admin only */}
              {isAdmin && (
                <NavGroup
                  label="Contenu"
                  icon="✍️"
                  isOpen={openGroups.contentEngine}
                  onToggle={() => toggleGroup('contentEngine')}
                >
                  <NavLink to="/content/overview" className={subNavClass} onClick={handleNavClick}>
                    Vue d'ensemble
                  </NavLink>
                  <NavLink to="/content/scheduler" className={subNavClass} onClick={handleNavClick}>
                    Planification
                  </NavLink>
                  <NavLink to="/content/articles" className={subNavClass} onClick={handleNavClick}>
                    Articles
                  </NavLink>
                  <NavLink to="/content/comparatives" className={subNavClass} onClick={handleNavClick}>
                    Comparatifs
                  </NavLink>
                  <NavLink to="/content/campaigns" className={subNavClass} onClick={handleNavClick}>
                    Campagnes
                  </NavLink>
                  <NavLink to="/content/clusters" className={subNavClass} onClick={handleNavClick}>
                    Clusters
                  </NavLink>
                  <NavLink to="/content/qa" className={subNavClass} onClick={handleNavClick}>
                    Q&A
                  </NavLink>
                  <NavLink to="/content/question-clusters" className={subNavClass} onClick={handleNavClick}>
                    Questions Forum
                  </NavLink>
                  <NavLink to="/content/landings" className={subNavClass} onClick={handleNavClick}>
                    Landings
                  </NavLink>
                  <NavLink to="/content/press" className={subNavClass} onClick={handleNavClick}>
                    Presse
                  </NavLink>
                </NavGroup>
              )}

              {/* SEO - admin only */}
              {isAdmin && (
                <NavGroup
                  label="SEO"
                  icon="🔍"
                  isOpen={openGroups.seo}
                  onToggle={() => toggleGroup('seo')}
                >
                  <NavLink to="/seo" end className={subNavClass} onClick={handleNavClick}>
                    Dashboard SEO
                  </NavLink>
                  <NavLink to="/seo/internal-links" className={subNavClass} onClick={handleNavClick}>
                    Maillage interne
                  </NavLink>
                  <NavLink to="/seo/keywords" className={subNavClass} onClick={handleNavClick}>
                    Mots-cles
                  </NavLink>
                </NavGroup>
              )}

              {/* Publication - admin only */}
              {isAdmin && (
                <NavGroup
                  label="Publication"
                  icon="📤"
                  isOpen={openGroups.publication}
                  onToggle={() => toggleGroup('publication')}
                >
                  <NavLink to="/publishing" className={subNavClass} onClick={handleNavClick}>
                    Publication
                  </NavLink>
                  <NavLink to="/media" className={subNavClass} onClick={handleNavClick}>
                    Medias
                  </NavLink>
                  <NavLink to="/costs" className={subNavClass} onClick={handleNavClick}>
                    Couts IA
                  </NavLink>
                </NavGroup>
              )}

              {/* Traductions - admin only */}
              {isAdmin && (
                <NavGroup
                  label="Traductions"
                  icon="🌐"
                  isOpen={openGroups.translations}
                  onToggle={() => toggleGroup('translations')}
                >
                  <NavLink to="/translations" className={subNavClass} onClick={handleNavClick}>
                    Traductions
                  </NavLink>
                </NavGroup>
              )}

              {/* 6. Qualite - admin only */}
              {isAdmin && (
                <NavLink to="/admin/qualite" className={navClass} onClick={handleNavClick}>
                  <span>✅</span> Qualite
                </NavLink>
              )}

              {/* 6. Parametres (group) - admin only */}
              {isAdmin && (
                <NavGroup
                  label="Parametres"
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
                  <NavLink to="/admin/scraper" className={subNavClass} onClick={handleNavClick}>
                    Scraper
                  </NavLink>
                  <NavLink to="/admin/prompt-templates" className={subNavClass} onClick={handleNavClick}>
                    Prompts Content
                  </NavLink>
                  <NavLink to="/admin/presets" className={subNavClass} onClick={handleNavClick}>
                    Presets generation
                  </NavLink>
                  <NavLink to="/equipe" className={subNavClass} onClick={handleNavClick}>
                    Equipe & Objectifs
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
                  {!canAccessAI && (
                    <NavLink to="/directories" className={navClass} onClick={handleNavClick}>
                      <span>📚</span> Annuaires
                    </NavLink>
                  )}
                </>
              )}
            </>
          )}
        </nav>

        {/* User footer */}
        <div className="p-4 border-t border-border">
          <div className="flex items-center gap-3 mb-3">
            <div className="w-8 h-8 rounded-full bg-violet/30 flex items-center justify-center text-violet-light font-bold text-sm">
              {user?.name?.[0] ?? '?'}
            </div>
            <div className="min-w-0">
              <p className="text-sm font-medium text-white truncate">{user?.name}</p>
              <p className="text-xs text-muted capitalize">{user?.role}</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="w-full text-sm text-muted hover:text-white py-1.5 transition-colors text-left"
          >
            Deconnexion &rarr;
          </button>
        </div>
      </aside>

      {/* Main content */}
      <main className="flex-1 overflow-auto">
        <Outlet />
      </main>
    </div>
  );
}
