import React, { useState } from 'react';
import { Outlet, NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { useReminders } from '../hooks/useReminders';

export default function Layout() {
  const { user, logout } = useAuth();
  const { reminders } = useReminders();
  const navigate = useNavigate();
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const navClass = ({ isActive }: { isActive: boolean }) =>
    `flex items-center gap-3 px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
      isActive
        ? 'bg-violet/20 text-violet-light'
        : 'text-gray-400 hover:bg-surface2 hover:text-white'
    }`;

  const isAdmin = user?.role === 'admin';
  const isManager = user?.role === 'manager';
  const isResearcher = user?.role === 'researcher';
  const canAccessAI = isAdmin || isManager;

  const handleNavClick = () => setSidebarOpen(false);

  const SectionLabel = ({ children }: { children: React.ReactNode }) => (
    <p className="text-[10px] font-bold text-muted uppercase tracking-wider px-4 mt-4 mb-1">{children}</p>
  );

  return (
    <div className="flex min-h-screen bg-bg">
      {/* Mobile hamburger */}
      <button onClick={() => setSidebarOpen(true)}
        className="md:hidden fixed top-3 left-3 z-50 p-2 bg-surface border border-border rounded-lg text-white" aria-label="Menu">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
          <line x1="3" y1="5" x2="17" y2="5" /><line x1="3" y1="10" x2="17" y2="10" /><line x1="3" y1="15" x2="17" y2="15" />
        </svg>
      </button>

      {/* Backdrop */}
      {sidebarOpen && <div className="md:hidden fixed inset-0 z-40 bg-black/60" onClick={() => setSidebarOpen(false)} />}

      {/* Sidebar */}
      <aside className={`fixed inset-y-0 left-0 z-50 w-60 bg-surface border-r border-border flex flex-col transform transition-transform duration-200 ease-in-out ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'} md:translate-x-0 md:static md:flex-shrink-0`}>
        <div className="p-5 border-b border-border flex items-center justify-between">
          <div>
            <h1 className="font-title text-lg font-bold text-white">Mission Control</h1>
            <p className="text-[10px] text-muted mt-0.5">SOS-Expat</p>
          </div>
          <button onClick={() => setSidebarOpen(false)} className="md:hidden text-muted hover:text-white" aria-label="Fermer">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
              <line x1="4" y1="4" x2="16" y2="16" /><line x1="16" y1="4" x2="4" y2="16" />
            </svg>
          </button>
        </div>

        <nav className="flex-1 p-3 space-y-0.5 overflow-y-auto">
          {isResearcher ? (
            /* Researcher nav */
            <>
              <NavLink to="/" end className={navClass} onClick={handleNavClick}>
                <span>📊</span> Mon Tableau
              </NavLink>
              <NavLink to="/influenceurs" className={navClass} onClick={handleNavClick}>
                <span>👥</span> Contacts
              </NavLink>
              <NavLink to="/a-relancer" className={navClass} onClick={handleNavClick}>
                <span>🔔</span> A relancer
                {reminders.length > 0 && (
                  <span className="ml-auto bg-amber text-black text-xs font-bold px-1.5 py-0.5 rounded-full">{reminders.length}</span>
                )}
              </NavLink>
              <NavLink to="/journal" className={navClass} onClick={handleNavClick}>
                <span>📝</span> Journal
              </NavLink>
            </>
          ) : (
            /* Admin/Manager/Member nav */
            <>
              <SectionLabel>Principal</SectionLabel>
              <NavLink to="/" end className={navClass} onClick={handleNavClick}>
                <span>📊</span> Dashboard
              </NavLink>
              <NavLink to="/influenceurs" className={navClass} onClick={handleNavClick}>
                <span>👥</span> Contacts
              </NavLink>
              <NavLink to="/a-relancer" className={navClass} onClick={handleNavClick}>
                <span>🔔</span> Relances
                {reminders.length > 0 && (
                  <span className="ml-auto bg-amber text-black text-xs font-bold px-1.5 py-0.5 rounded-full">{reminders.length}</span>
                )}
              </NavLink>

              <SectionLabel>Outils</SectionLabel>
              {canAccessAI && (
                <NavLink to="/ai-research" className={navClass} onClick={handleNavClick}>
                  <span>🤖</span> Recherche IA
                </NavLink>
              )}
              <NavLink to="/outreach" className={navClass} onClick={handleNavClick}>
                <span>✉️</span> Templates
              </NavLink>
              <NavLink to="/content-engine" className={navClass} onClick={handleNavClick}>
                <span>📡</span> Content Engine
              </NavLink>
              <NavLink to="/journal" className={navClass} onClick={handleNavClick}>
                <span>📝</span> Journal
              </NavLink>

              <SectionLabel>Analyse</SectionLabel>
              <NavLink to="/statistiques" className={navClass} onClick={handleNavClick}>
                <span>📈</span> Statistiques
              </NavLink>

              {isAdmin && (
                <>
                  <SectionLabel>Administration</SectionLabel>
                  <NavLink to="/admin" className={navClass} onClick={handleNavClick}>
                    <span>⚡</span> Console Admin
                  </NavLink>
                  <NavLink to="/admin/types" className={navClass} onClick={handleNavClick}>
                    <span>🏷️</span> Types de Contacts
                  </NavLink>
                  <NavLink to="/admin/prompts" className={navClass} onClick={handleNavClick}>
                    <span>🤖</span> Prompts IA
                  </NavLink>
                  <NavLink to="/equipe" className={navClass} onClick={handleNavClick}>
                    <span>⚙️</span> Équipe
                  </NavLink>
                </>
              )}
            </>
          )}
        </nav>

        {/* User footer */}
        <div className="p-4 border-t border-border">
          <div className="flex items-center gap-3 mb-3">
            <div className="w-8 h-8 rounded-full bg-violet/30 flex items-center justify-center text-violet-light font-bold text-sm">
              {user?.name[0]}
            </div>
            <div className="min-w-0">
              <p className="text-sm font-medium text-white truncate">{user?.name}</p>
              <p className="text-xs text-muted capitalize">{user?.role}</p>
            </div>
          </div>
          <button onClick={handleLogout} className="w-full text-sm text-muted hover:text-white py-1.5 transition-colors text-left">
            Déconnexion &rarr;
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
