import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthContext, useAuthProvider } from './hooks/useAuth';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import ResearcherDashboard from './pages/ResearcherDashboard';
import Contacts from './pages/Contacts';
import ContactDetail from './pages/ContactDetail';
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
import ContentCities from './pages/content/ContentCities';
import ContentArticlePage from './pages/content/ContentArticle';
import BusinessDirectory from './pages/content/BusinessDirectory';
import LawyerDirectory from './pages/content/LawyerDirectory';
import ContentSites from './pages/content/ContentSites';
import AffiliateLinks from './pages/content/AffiliateLinks';
import CountryProfiles from './pages/content/CountryProfiles';
import CountryProfileDetail from './pages/content/CountryProfileDetail';
import CityProfiles from './pages/content/CityProfiles';
import CityProfileDetail from './pages/content/CityProfileDetail';
import ContentContacts from './pages/content/ContentContacts';
import JournalistContacts from './pages/contacts/JournalistContacts';
import ContactsBase from './pages/contacts/ContactsBase';
import ScrapingDashboard from './pages/scraping/ScrapingDashboard';
import ContentQuestions from './pages/content/ContentQuestions';
import ProspectionHub from './pages/prospection/ProspectionHub';
import ProspectionOverview from './pages/prospection/ProspectionOverview';
import ProspectionEmails from './pages/prospection/ProspectionEmails';
import ProspectionSequences from './pages/prospection/ProspectionSequences';
import ProspectionContacts from './pages/prospection/ProspectionContacts';
import ProspectionConfig from './pages/prospection/ProspectionConfig';
import ProspectionCampaignWizard from './pages/prospection/ProspectionCampaignWizard';
import Layout from './components/Layout';
import { ToastContainer } from './components/Toast';

// Content Engine pages (lazy loaded)
const ContentOverview = React.lazy(() => import('./pages/content/ContentOverview'));
const ArticlesList = React.lazy(() => import('./pages/content/ArticlesList'));
const ArticleCreate = React.lazy(() => import('./pages/content/ArticleCreate'));
const ArticleDetail = React.lazy(() => import('./pages/content/ArticleDetail'));
const ComparativesList = React.lazy(() => import('./pages/content/ComparativesList'));
const ComparativeCreate = React.lazy(() => import('./pages/content/ComparativeCreate'));
const ComparativeDetail = React.lazy(() => import('./pages/content/ComparativeDetail'));
const CampaignsList = React.lazy(() => import('./pages/content/CampaignsList'));
const CampaignCreate = React.lazy(() => import('./pages/content/CampaignCreate'));
const CampaignDetail = React.lazy(() => import('./pages/content/CampaignDetail'));
const SeoDashboard = React.lazy(() => import('./pages/content/SeoDashboard'));
const SeoInternalLinks = React.lazy(() => import('./pages/content/SeoInternalLinks'));
const PublishingDashboard = React.lazy(() => import('./pages/content/PublishingDashboard'));
const PublicationControl = React.lazy(() => import('./pages/content/PublicationControl'));
const QualityMonitoring = React.lazy(() => import('./pages/content/QualityMonitoring'));
const TaxonomyManager = React.lazy(() => import('./pages/content/TaxonomyManager'));
const CostsDashboard = React.lazy(() => import('./pages/content/CostsDashboard'));
const MediaLibrary = React.lazy(() => import('./pages/content/MediaLibrary'));
const PromptTemplates = React.lazy(() => import('./pages/content/PromptTemplates'));
const GenerationPresets = React.lazy(() => import('./pages/content/GenerationPresets'));
const ClustersList = React.lazy(() => import('./pages/content/ClustersList'));
const ClusterDetail = React.lazy(() => import('./pages/content/ClusterDetail'));
const QaList = React.lazy(() => import('./pages/content/QaList'));
const QaDetail = React.lazy(() => import('./pages/content/QaDetail'));
const KeywordTracker = React.lazy(() => import('./pages/content/KeywordTracker'));
const TranslationsDashboard = React.lazy(() => import('./pages/content/TranslationsDashboard'));
const DailyScheduler = React.lazy(() => import('./pages/content/DailyScheduler'));
const QuestionClustersList = React.lazy(() => import('./pages/content/QuestionClustersList'));
const QuestionClusterDetail = React.lazy(() => import('./pages/content/QuestionClusterDetail'));
const LandingsList = React.lazy(() => import('./pages/content/LandingsList'));
const LandingCreate = React.lazy(() => import('./pages/content/LandingCreate'));
const LandingDetail = React.lazy(() => import('./pages/content/LandingDetail'));
const PressList = React.lazy(() => import('./pages/content/PressList'));
const PressDetail = React.lazy(() => import('./pages/content/PressDetail'));
const DossierDetail = React.lazy(() => import('./pages/content/DossierDetail'));
const DataCleanupDashboard = React.lazy(() => import('./pages/content/DataCleanupDashboard'));
const GenerationSources = React.lazy(() => import('./pages/content/GenerationSources'));
const SourceDetail = React.lazy(() => import('./pages/content/SourceDetail'));
const ContentCommandCenter = React.lazy(() => import('./pages/content/ContentCommandCenter'));
const CountryDirectoryPage = React.lazy(() => import('./pages/content/CountryDirectoryPage'));
const SondagesList = React.lazy(() => import('./pages/content/SondagesList'));
const SondagesResultats = React.lazy(() => import('./pages/content/SondagesResultats'));

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
        <ToastContainer />
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/" element={<PrivateRoute><Layout /></PrivateRoute>}>
            <Route index element={<IndexRoute />} />
            <Route path="mon-tableau" element={<ResearcherDashboard />} />

            {/* Core CRM — Contacts */}
            <Route path="contacts" element={<Contacts />} />
            <Route path="contacts/:id" element={<ContactDetail />} />
            {/* Redirections legacy */}
            <Route path="influenceurs" element={<Navigate to="/contacts" replace />} />
            <Route path="influenceurs/:id" element={<Navigate to="/contacts" replace />} />
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
            <Route path="content/country-directory" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><CountryDirectoryPage /></React.Suspense></AdminRoute>} />
            <Route path="content/lawyers" element={<AdminRoute><LawyerDirectory /></AdminRoute>} />
            <Route path="content/contacts" element={<AdminRoute><ContentContacts /></AdminRoute>} />
            <Route path="contacts/journalistes" element={<AdminRoute><JournalistContacts /></AdminRoute>} />
            <Route path="contacts/base" element={<AdminRoute><ContactsBase /></AdminRoute>} />
            <Route path="scraping/dashboard" element={<AdminRoute><ScrapingDashboard /></AdminRoute>} />
            <Route path="content/questions" element={<AdminRoute><ContentQuestions /></AdminRoute>} />
            <Route path="content/affiliates" element={<AdminRoute><AffiliateLinks /></AdminRoute>} />
            <Route path="content/data-cleanup" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><DataCleanupDashboard /></React.Suspense></AdminRoute>} />
            <Route path="content/command-center" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><ContentCommandCenter /></React.Suspense></AdminRoute>} />
            <Route path="content/sources" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><GenerationSources /></React.Suspense></AdminRoute>} />
            <Route path="content/sources/:sourceType" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><SourceDetail /></React.Suspense></AdminRoute>} />
            <Route path="content/countries" element={<AdminRoute><CountryProfiles /></AdminRoute>} />
            <Route path="content/country/:countrySlug" element={<AdminRoute><CountryProfileDetail /></AdminRoute>} />
            <Route path="content/cities" element={<AdminRoute><CityProfiles /></AdminRoute>} />
            <Route path="content/cities/:citySlug" element={<AdminRoute><CityProfileDetail /></AdminRoute>} />

            {/* Content Engine v2 (lazy loaded) */}
            <Route path="content/overview" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><ContentOverview /></React.Suspense></AdminRoute>} />
            <Route path="content/scheduler" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><DailyScheduler /></React.Suspense></AdminRoute>} />
            <Route path="content/publication" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><PublicationControl /></React.Suspense></AdminRoute>} />
            <Route path="content/quality" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><QualityMonitoring /></React.Suspense></AdminRoute>} />
            <Route path="content/taxonomies" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><TaxonomyManager /></React.Suspense></AdminRoute>} />
            <Route path="content/articles" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><ArticlesList /></React.Suspense></AdminRoute>} />
            <Route path="content/articles/new" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><ArticleCreate /></React.Suspense></AdminRoute>} />
            <Route path="content/articles/:id" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><ArticleDetail /></React.Suspense></AdminRoute>} />
            <Route path="content/comparatives" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><ComparativesList /></React.Suspense></AdminRoute>} />
            <Route path="content/comparatives/new" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><ComparativeCreate /></React.Suspense></AdminRoute>} />
            <Route path="content/comparatives/:id" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><ComparativeDetail /></React.Suspense></AdminRoute>} />
            <Route path="content/campaigns" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><CampaignsList /></React.Suspense></AdminRoute>} />
            <Route path="content/campaigns/new" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><CampaignCreate /></React.Suspense></AdminRoute>} />
            <Route path="content/campaigns/:id" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><CampaignDetail /></React.Suspense></AdminRoute>} />

            {/* Content Pipeline: Clusters, Q&A, Keywords, Translations */}
            <Route path="content/clusters" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><ClustersList /></React.Suspense></AdminRoute>} />
            <Route path="content/clusters/:id" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><ClusterDetail /></React.Suspense></AdminRoute>} />
            <Route path="content/qa" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><QaList /></React.Suspense></AdminRoute>} />
            <Route path="content/qa/:id" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><QaDetail /></React.Suspense></AdminRoute>} />
            <Route path="content/question-clusters" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><QuestionClustersList /></React.Suspense></AdminRoute>} />
            <Route path="content/question-clusters/:id" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><QuestionClusterDetail /></React.Suspense></AdminRoute>} />
            <Route path="content/landings" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><LandingsList /></React.Suspense></AdminRoute>} />
            <Route path="content/landings/new" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><LandingCreate /></React.Suspense></AdminRoute>} />
            <Route path="content/landings/:id" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><LandingDetail /></React.Suspense></AdminRoute>} />
            <Route path="content/press" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><PressList /></React.Suspense></AdminRoute>} />
            <Route path="content/sondages" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><SondagesList /></React.Suspense></AdminRoute>} />
            <Route path="content/sondages/resultats" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><SondagesResultats /></React.Suspense></AdminRoute>} />
            <Route path="content/press/releases/:id" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><PressDetail /></React.Suspense></AdminRoute>} />
            <Route path="content/press/dossiers/:id" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><DossierDetail /></React.Suspense></AdminRoute>} />
            <Route path="seo/keywords" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><KeywordTracker /></React.Suspense></AdminRoute>} />
            <Route path="translations" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><TranslationsDashboard /></React.Suspense></AdminRoute>} />

            {/* Villes scrapees */}
            <Route path="content/:sourceSlug/cities" element={<AdminRoute><ContentCities /></AdminRoute>} />

            {/* Content v1 catch-all routes (must come after specific content/* routes) */}
            <Route path="content/:sourceSlug" element={<AdminRoute><ContentSourcePage /></AdminRoute>} />
            <Route path="content/:sourceSlug/:countrySlug" element={<AdminRoute><ContentCountryPage /></AdminRoute>} />
            <Route path="seo" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><SeoDashboard /></React.Suspense></AdminRoute>} />
            <Route path="seo/internal-links" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><SeoInternalLinks /></React.Suspense></AdminRoute>} />
            <Route path="publishing" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><PublishingDashboard /></React.Suspense></AdminRoute>} />
            <Route path="costs" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><CostsDashboard /></React.Suspense></AdminRoute>} />
            <Route path="media" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><MediaLibrary /></React.Suspense></AdminRoute>} />
            <Route path="admin/prompt-templates" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><PromptTemplates /></React.Suspense></AdminRoute>} />
            <Route path="admin/presets" element={<AdminRoute><React.Suspense fallback={<div className="p-8 text-gray-400">Chargement...</div>}><GenerationPresets /></React.Suspense></AdminRoute>} />

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
