import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthContext, useAuthProvider } from './hooks/useAuth';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import ResearcherDashboard from './pages/ResearcherDashboard';
import AdminConsole from './pages/AdminConsole';
import Influenceurs from './pages/Influenceurs';
import InfluenceurDetail from './pages/InfluenceurDetail';
import ARelancer from './pages/ARelancer';
import Statistiques from './pages/Statistiques';
import Equipe from './pages/Equipe';
import AdminAiPrompts from './pages/AdminAiPrompts';
import AdminContactTypes from './pages/AdminContactTypes';
import AiResearch from './pages/AiResearch';
import Outreach from './pages/Outreach';
import ContentEngine from './pages/ContentEngine';
import Journal from './pages/Journal';
import Layout from './components/Layout';

function PrivateRoute({ children }: { children: React.ReactNode }) {
  const { user, loading } = React.useContext(AuthContext);
  if (loading) return (
    <div className="flex items-center justify-center h-screen bg-bg">
      <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );
  return user ? <>{children}</> : <Navigate to="/login" replace />;
}

function AdminRoute({ children }: { children: React.ReactNode }) {
  const { user } = React.useContext(AuthContext);
  return user?.role === 'admin' ? <>{children}</> : <Navigate to="/" replace />;
}

function AdminOrManagerRoute({ children }: { children: React.ReactNode }) {
  const { user } = React.useContext(AuthContext);
  return (user?.role === 'admin' || user?.role === 'manager') ? <>{children}</> : <Navigate to="/" replace />;
}

function NonResearcherRoute({ children }: { children: React.ReactNode }) {
  const { user } = React.useContext(AuthContext);
  return user?.role === 'researcher' ? <Navigate to="/mon-tableau" replace /> : <>{children}</>;
}

function IndexRoute() {
  const { user } = React.useContext(AuthContext);
  if (user?.role === 'researcher') {
    return <ResearcherDashboard />;
  }
  return <Dashboard />;
}

export default function App() {
  const auth = useAuthProvider();

  return (
    <AuthContext.Provider value={auth}>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/" element={<PrivateRoute><Layout /></PrivateRoute>}>
            <Route index element={<IndexRoute />} />
            <Route path="mon-tableau" element={<ResearcherDashboard />} />

            {/* Core CRM */}
            <Route path="influenceurs" element={<Influenceurs />} />
            <Route path="influenceurs/:id" element={<InfluenceurDetail />} />
            <Route path="a-relancer" element={<ARelancer />} />

            {/* New modules (fusion) */}
            <Route path="ai-research" element={<AdminOrManagerRoute><AiResearch /></AdminOrManagerRoute>} />
            <Route path="outreach" element={<Outreach />} />
            <Route path="content-engine" element={<ContentEngine />} />
            <Route path="journal" element={<Journal />} />

            {/* Analytics */}
            <Route path="statistiques" element={<NonResearcherRoute><Statistiques /></NonResearcherRoute>} />

            {/* Admin */}
            <Route path="admin" element={<AdminRoute><AdminConsole /></AdminRoute>} />
            <Route path="admin/types" element={<AdminRoute><AdminContactTypes /></AdminRoute>} />
            <Route path="admin/prompts" element={<AdminRoute><AdminAiPrompts /></AdminRoute>} />
            <Route path="equipe" element={<AdminRoute><Equipe /></AdminRoute>} />
          </Route>
        </Routes>
      </BrowserRouter>
    </AuthContext.Provider>
  );
}
