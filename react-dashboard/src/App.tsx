import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthContext, useAuthProvider } from './hooks/useAuth';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import ResearcherDashboard from './pages/ResearcherDashboard';
import Influenceurs from './pages/Influenceurs';
import InfluenceurDetail from './pages/InfluenceurDetail';
import ARelancer from './pages/ARelancer';
import Equipe from './pages/Equipe';
import AdminAiPrompts from './pages/AdminAiPrompts';
import AdminScraper from './pages/AdminScraper';
import AutoCampaign from './pages/AutoCampaign';
import AdminContactTypes from './pages/AdminContactTypes';
import AiResearch from './pages/AiResearch';
import Outreach from './pages/Outreach';
import ContentEngine from './pages/ContentEngine';
import Journal from './pages/Journal';
import Directories from './pages/Directories';
import CoverageMatrix from './pages/CoverageMatrix';
import QualityDashboard from './pages/QualityDashboard';
import ContentHub from './pages/content/ContentHub';
import ContentLinks from './pages/content/ContentLinks';
import ContentSourcePage from './pages/content/ContentSource';
import ContentCountryPage from './pages/content/ContentCountry';
import ContentArticlePage from './pages/content/ContentArticle';
import BusinessDirectory from './pages/content/BusinessDirectory';
import ContentSites from './pages/content/ContentSites';
import ProspectionHub from './pages/prospection/ProspectionHub';
import ProspectionOverview from './pages/prospection/ProspectionOverview';
import ProspectionEmails from './pages/prospection/ProspectionEmails';
import ProspectionSequences from './pages/prospection/ProspectionSequences';
import ProspectionContacts from './pages/prospection/ProspectionContacts';
import ProspectionConfig from './pages/prospection/ProspectionConfig';
import ProspectionCampaignWizard from './pages/prospection/ProspectionCampaignWizard';
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

            {/* Acquisition */}
            <Route path="ai-research" element={<AdminOrManagerRoute><AiResearch /></AdminOrManagerRoute>} />
            <Route path="directories" element={<Directories />} />

            {/* Prospection */}
            <Route path="outreach" element={<Outreach />} />
            <Route path="prospection" element={<AdminRoute><ProspectionHub /></AdminRoute>} />
            <Route path="prospection/overview" element={<AdminRoute><ProspectionOverview /></AdminRoute>} />
            <Route path="prospection/emails" element={<AdminRoute><ProspectionEmails /></AdminRoute>} />
            <Route path="prospection/sequences" element={<AdminRoute><ProspectionSequences /></AdminRoute>} />
            <Route path="prospection/contacts" element={<AdminRoute><ProspectionContacts /></AdminRoute>} />
            <Route path="prospection/config" element={<AdminRoute><ProspectionConfig /></AdminRoute>} />
            <Route path="prospection/campaign" element={<AdminRoute><ProspectionCampaignWizard /></AdminRoute>} />

            {/* Content Engine */}
            <Route path="content" element={<AdminRoute><ContentHub /></AdminRoute>} />
            <Route path="content/sites" element={<AdminRoute><ContentSites /></AdminRoute>} />
            <Route path="content/links" element={<AdminRoute><ContentLinks /></AdminRoute>} />
            <Route path="content/businesses" element={<AdminRoute><BusinessDirectory /></AdminRoute>} />
            <Route path="content/articles/:id" element={<AdminRoute><ContentArticlePage /></AdminRoute>} />
            <Route path="content/:sourceSlug" element={<AdminRoute><ContentSourcePage /></AdminRoute>} />
            <Route path="content/:sourceSlug/:countrySlug" element={<AdminRoute><ContentCountryPage /></AdminRoute>} />

            {/* Tools */}
            <Route path="content-engine" element={<ContentEngine />} />
            <Route path="journal" element={<Journal />} />

            {/* Admin: Quality */}
            <Route path="admin/qualite" element={<AdminRoute><QualityDashboard /></AdminRoute>} />

            {/* Admin: Settings */}
            <Route path="admin/types" element={<AdminRoute><AdminContactTypes /></AdminRoute>} />
            <Route path="admin/prompts" element={<AdminRoute><AdminAiPrompts /></AdminRoute>} />
            <Route path="admin/scraper" element={<AdminRoute><AdminScraper /></AdminRoute>} />
            <Route path="admin/campaigns" element={<AdminRoute><AutoCampaign /></AdminRoute>} />
            <Route path="admin/avancement" element={<AdminRoute><CoverageMatrix /></AdminRoute>} />
            <Route path="equipe" element={<AdminRoute><Equipe /></AdminRoute>} />

            {/* Legacy redirects — old routes redirect to new locations */}
            <Route path="statistiques" element={<Navigate to="/" replace />} />
            <Route path="admin" element={<Navigate to="/" replace />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </AuthContext.Provider>
  );
}
