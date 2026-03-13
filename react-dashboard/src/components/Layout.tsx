import React from 'react';
import { Outlet, NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { useReminders } from '../hooks/useReminders';

export default function Layout() {
  const { user, logout } = useAuth();
  const { reminders } = useReminders();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const navClass = ({ isActive }: { isActive: boolean }) =>
    `flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors ${
      isActive
        ? 'bg-violet/20 text-violet-light'
        : 'text-gray-400 hover:bg-surface2 hover:text-white'
    }`;

  return (
    <div className="flex min-h-screen bg-bg">
      {/* Sidebar */}
      <aside className="w-60 flex-shrink-0 bg-surface border-r border-border flex flex-col">
        <div className="p-5 border-b border-border">
          <h1 className="font-title text-lg font-bold text-white">Influenceurs</h1>
          <p className="text-xs text-muted mt-0.5">SOS-Expat CRM</p>
        </div>

        <nav className="flex-1 p-3 space-y-1">
          <NavLink to="/" end className={navClass}>
            <span>📊</span> Dashboard
          </NavLink>
          <NavLink to="/influenceurs" className={navClass}>
            <span>👥</span> Influenceurs
          </NavLink>
          <NavLink to="/a-relancer" className={navClass}>
            <span>🔔</span> À relancer
            {reminders.length > 0 && (
              <span className="ml-auto bg-amber text-black text-xs font-bold px-1.5 py-0.5 rounded-full">
                {reminders.length}
              </span>
            )}
          </NavLink>
          <NavLink to="/statistiques" className={navClass}>
            <span>📈</span> Statistiques
          </NavLink>
          {user?.role === 'admin' && (
            <NavLink to="/equipe" className={navClass}>
              <span>⚙️</span> Équipe
            </NavLink>
          )}
        </nav>

        <div className="p-4 border-t border-border">
          <div className="flex items-center gap-3 mb-3">
            <div className="w-8 h-8 rounded-full bg-violet/30 flex items-center justify-center text-violet-light font-bold text-sm">
              {user?.name[0]}
            </div>
            <div>
              <p className="text-sm font-medium text-white truncate">{user?.name}</p>
              <p className="text-xs text-muted capitalize">{user?.role}</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="w-full text-sm text-muted hover:text-white py-1.5 transition-colors text-left"
          >
            Déconnexion →
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
