import React, { lazy, Suspense } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthContext, useAuthProvider } from './hooks/useAuth';
import Layout from './components/Layout';
import { ToastContainer } from './components/Toast';

// ── Lazy routes ─────────────────────────────────────────────
// Every page is lazy-loaded so the initial bundle stays small.
// Layout, Login, and auth utilities stay eager for instant TTI.
const Login = lazy(() => import('./pages/Login'));
const Dashboard = lazy(() => import('./pages/Dashboard'));
const ResearcherDashboard = lazy(() => import('./pages/ResearcherDashboard'));
const Contacts = lazy(() => import('./pages/Contacts'));
const ContactDetail = lazy(() => import('./pages/ContactDetail'));
const ARelancer = lazy(() => import('./pages/ARelancer'));
const Equipe = lazy(() => import('./pages/Equipe'));
const AdminAiPrompts = lazy(() => import('./pages/AdminAiPrompts'));
const AdminScraper = lazy(() => import('./pages/AdminScraper'));
const AutoCampaign = lazy(() => import('./pages/AutoCampaign'));
const AdminContactTypes = lazy(() => import('./pages/AdminContactTypes'));
const AiResearch = lazy(() => import('./pages/AiResearch'));
const Outreach = lazy(() => import('./pages/Outreach'));
const ContentEngine = lazy(() => import('./pages/ContentEngine'));
const Journal = lazy(() => import('./pages/Journal'));
const Directories = lazy(() => import('./pages/Directories'));
const CoverageMatrix = lazy(() => import('./pages/CoverageMatrix'));
const QualityDashboard = lazy(() => import('./pages/QualityDashboard'));
const ContentHub = lazy(() => import('./pages/content/ContentHub'));
const ContentLinks = lazy(() => import('./pages/content/ContentLinks'));
const ContentSourcePage = lazy(() => import('./pages/content/ContentSource'));
const ContentCountryPage = lazy(() => import('./pages/content/ContentCountry'));
const ContentCities = lazy(() => import('./pages/content/ContentCities'));
const ContentArticlePage = lazy(() => import('./pages/content/ContentArticle'));
const BusinessDirectory = lazy(() => import('./pages/content/BusinessDirectory'));
const LawyerDirectory = lazy(() => import('./pages/content/LawyerDirectory'));
const ContentSites = lazy(() => import('./pages/content/ContentSites'));
const AffiliateLinks = lazy(() => import('./pages/content/AffiliateLinks'));
const AffiliateDashboard = lazy(() => import('./pages/affiliates/AffiliateDashboard'));
const CountryProfiles = lazy(() => import('./pages/content/CountryProfiles'));
const CountryProfileDetail = lazy(() => import('./pages/content/CountryProfileDetail'));
const CityProfiles = lazy(() => import('./pages/content/CityProfiles'));
const CityProfileDetail = lazy(() => import('./pages/content/CityProfileDetail'));
const ContentContacts = lazy(() => import('./pages/content/ContentContacts'));
const JournalistContacts = lazy(() => import('./pages/contacts/JournalistContacts'));
const ContactsBase = lazy(() => import('./pages/contacts/ContactsBase'));
const CategoryContactsPage = lazy(() => import('./pages/contacts/CategoryContactsPage'));
const ScrapingDashboard = lazy(() => import('./pages/scraping/ScrapingDashboard'));
const ContentQuestions = lazy(() => import('./pages/content/ContentQuestions'));
const ProspectionHub = lazy(() => import('./pages/prospection/ProspectionHub'));
const ProspectionOverview = lazy(() => import('./pages/prospection/ProspectionOverview'));
const ProspectionEmails = lazy(() => import('./pages/prospection/ProspectionEmails'));
const ProspectionSequences = lazy(() => import('./pages/prospection/ProspectionSequences'));
const ProspectionContacts = lazy(() => import('./pages/prospection/ProspectionContacts'));
const ProspectionConfig = lazy(() => import('./pages/prospection/ProspectionConfig'));
const ProspectionCampaignWizard = lazy(() => import('./pages/prospection/ProspectionCampaignWizard'));

// Content Engine pages
const ContentOverview = lazy(() => import('./pages/content/ContentOverview'));
const GenerateQr = lazy(() => import('./pages/content/GenerateQr'));
const ArticlesList = lazy(() => import('./pages/content/ArticlesList'));
const ArticleCreate = lazy(() => import('./pages/content/ArticleCreate'));
const ArticleDetail = lazy(() => import('./pages/content/ArticleDetail'));
const FichesPays = lazy(() => import('./pages/content/FichesPays'));
const ContentGenerator = lazy(() => import('./pages/content/ContentGenerator'));
const ArtMotsCles = lazy(() => import('./pages/content/ArtMotsCles'));
const ContentTemplates = lazy(() => import('./pages/content/ContentTemplates'));
const ContentTemplateDetail = lazy(() => import('./pages/content/ContentTemplateDetail'));
const ComparativesList = lazy(() => import('./pages/content/ComparativesList'));
const ComparativeCreate = lazy(() => import('./pages/content/ComparativeCreate'));
const ComparativeDetail = lazy(() => import('./pages/content/ComparativeDetail'));
const CampaignsList = lazy(() => import('./pages/content/CampaignsList'));
const CampaignCreate = lazy(() => import('./pages/content/CampaignCreate'));
const CampaignDetail = lazy(() => import('./pages/content/CampaignDetail'));
const SeoDashboard = lazy(() => import('./pages/content/SeoDashboard'));
const SeoInternalLinks = lazy(() => import('./pages/content/SeoInternalLinks'));
const PublishingDashboard = lazy(() => import('./pages/content/PublishingDashboard'));
const PublicationControl = lazy(() => import('./pages/content/PublicationControl'));
const QualityMonitoring = lazy(() => import('./pages/content/QualityMonitoring'));
const TaxonomyManager = lazy(() => import('./pages/content/TaxonomyManager'));
const CostsDashboard = lazy(() => import('./pages/content/CostsDashboard'));
const MediaLibrary = lazy(() => import('./pages/content/MediaLibrary'));
const PromptTemplates = lazy(() => import('./pages/content/PromptTemplates'));
const GenerationPresets = lazy(() => import('./pages/content/GenerationPresets'));
const ClustersList = lazy(() => import('./pages/content/ClustersList'));
const ClusterDetail = lazy(() => import('./pages/content/ClusterDetail'));
const KeywordTracker = lazy(() => import('./pages/content/KeywordTracker'));
const TranslationsDashboard = lazy(() => import('./pages/content/TranslationsDashboard'));
const DailyScheduler = lazy(() => import('./pages/content/DailyScheduler'));
const QuestionClustersList = lazy(() => import('./pages/content/QuestionClustersList'));
const QuestionClusterDetail = lazy(() => import('./pages/content/QuestionClusterDetail'));
const LandingsList = lazy(() => import('./pages/content/LandingsList'));
const LandingCreate = lazy(() => import('./pages/content/LandingCreate'));
const LandingDetail = lazy(() => import('./pages/content/LandingDetail'));
const PressList = lazy(() => import('./pages/content/PressList'));
const PressDetail = lazy(() => import('./pages/content/PressDetail'));
const DossierDetail = lazy(() => import('./pages/content/DossierDetail'));
const DataCleanupDashboard = lazy(() => import('./pages/content/DataCleanupDashboard'));
const GenerationSources = lazy(() => import('./pages/content/GenerationSources'));
const SourceDetail = lazy(() => import('./pages/content/SourceDetail'));
const ContentCommandCenter = lazy(() => import('./pages/content/ContentCommandCenter'));
const ContentOrchestrator = lazy(() => import('./pages/content/ContentOrchestrator'));
const ArtLonguesTraines = lazy(() => import('./pages/content/ArtLonguesTraines'));
const BrandContent = lazy(() => import('./pages/content/BrandContent'));
const ApiBalanceMonitor = lazy(() => import('./pages/settings/ApiBalanceMonitor'));
const CountryDirectoryPage = lazy(() => import('./pages/content/CountryDirectoryPage'));
const SondagesList = lazy(() => import('./pages/content/SondagesList'));
const SondagesResultats = lazy(() => import('./pages/content/SondagesResultats'));
const PromoToolsAdmin = lazy(() => import('./pages/content/PromoToolsAdmin'));
const OutilsVisiteursAdmin = lazy(() => import('./pages/content/OutilsVisiteursAdmin'));
const NewsHub = lazy(() => import('./pages/content/NewsHub'));
const ArtStatistiques = lazy(() => import('./pages/content/ArtStatistiques'));
const LandingGeneratorHub = lazy(() => import('./pages/content/LandingGeneratorHub'));
const LandingGeneratorClients = lazy(() => import('./pages/content/LandingGeneratorClients'));
const LandingGeneratorAvocats = lazy(() => import('./pages/content/LandingGeneratorAvocats'));
const LandingGeneratorHelpers = lazy(() => import('./pages/content/LandingGeneratorHelpers'));
const LandingGeneratorMatching = lazy(() => import('./pages/content/LandingGeneratorMatching'));

// ── Shared fallback ────────────────────────────────────────────────────────
function LoadingFallback() {
  return (
    <div className="flex items-center justify-center min-h-[60vh]" role="status" aria-live="polite">
      <div className="flex flex-col items-center gap-3">
        <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" aria-hidden="true" />
        <p className="text-sm text-text-muted">Chargement...</p>
      </div>
    </div>
  );
}

// ── Error boundary ─────────────────────────────────────────────────────────
interface ErrorBoundaryState { hasError: boolean; message: string }
class ErrorBoundary extends React.Component<{ children: React.ReactNode }, ErrorBoundaryState> {
  constructor(props: { children: React.ReactNode }) {
    super(props);
    this.state = { hasError: false, message: '' };
  }
  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { hasError: true, message: error.message };
  }
  render() {
    if (this.state.hasError) {
      return (
        <div className="flex flex-col items-center justify-center h-screen bg-bg gap-4 p-8">
          <p className="text-red-400 text-lg font-semibold">Une erreur inattendue s'est produite.</p>
          <p className="text-muted text-sm font-mono">{this.state.message}</p>
          <button
            onClick={() => { this.setState({ hasError: false, message: '' }); window.location.href = '/'; }}
            className="px-4 py-2 bg-violet text-white rounded-lg text-sm"
          >
            Retour à l'accueil
          </button>
        </div>
      );
    }
    return this.props.children;
  }
}

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
        <ErrorBoundary>
          <ToastContainer />
          <Suspense fallback={<LoadingFallback />}>
            <Routes>
              <Route path="/login" element={<Login />} />
              <Route path="/" element={<PrivateRoute><Layout /></PrivateRoute>}>
                <Route index element={<IndexRoute />} />
                <Route path="mon-tableau" element={<ResearcherDashboard />} />

                {/* Core CRM — Contacts */}
                <Route path="contacts" element={<Contacts />} />

                {/* ── Catégories (route dédiée = remount garanti = filtre toujours correct) ── */}
                <Route path="contacts/institutionnel"
                  element={<CategoryContactsPage key="institutionnel" category="institutionnel" />} />
                <Route path="contacts/medias-influence"
                  element={<CategoryContactsPage key="medias_influence" category="medias_influence" />} />
                <Route path="contacts/youtubeurs"
                  element={<CategoryContactsPage key="youtubeurs" category="medias_influence" contactType="youtubeur" />} />
                <Route path="contacts/instagrammeurs"
                  element={<CategoryContactsPage key="instagrammeurs" category="medias_influence" contactType="instagrammeur" />} />
                <Route path="contacts/services-b2b"
                  element={<CategoryContactsPage key="services_b2b" category="services_b2b" />} />
                <Route path="contacts/communautes"
                  element={<CategoryContactsPage key="communautes" category="communautes" />} />
                <Route path="contacts/digital"
                  element={<CategoryContactsPage key="digital" category="digital" />} />
                <Route path="contacts/ecoles"
                  element={<CategoryContactsPage key="ecoles" category="institutionnel" contactType="ecole" />} />
                <Route path="contacts/ufe"
                  element={<CategoryContactsPage key="ufe" category="institutionnel" contactType="ufe" />} />
                <Route path="contacts/alliance-francaise"
                  element={<CategoryContactsPage key="alliance_francaise" category="institutionnel" contactType="alliance_francaise" />} />

                {/* ── Outils de sourcing contacts ── */}
                <Route path="contacts/journalistes" element={<AdminRoute><JournalistContacts /></AdminRoute>} />
                <Route path="contacts/base" element={<AdminRoute><ContactsBase /></AdminRoute>} />

                {/* ── Fiche contact individuelle (après les routes statiques) ── */}
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
                <Route path="content/country-directory" element={<AdminRoute><CountryDirectoryPage /></AdminRoute>} />
                <Route path="content/lawyers" element={<AdminRoute><LawyerDirectory /></AdminRoute>} />
                <Route path="content/contacts" element={<AdminRoute><ContentContacts /></AdminRoute>} />
                <Route path="scraping/dashboard" element={<AdminRoute><ScrapingDashboard /></AdminRoute>} />
                <Route path="content/questions" element={<AdminRoute><ContentQuestions /></AdminRoute>} />
                <Route path="content/affiliates" element={<AdminRoute><AffiliateLinks /></AdminRoute>} />
                <Route path="affiliates" element={<AdminRoute><AffiliateDashboard /></AdminRoute>} />
                <Route path="content/data-cleanup" element={<AdminRoute><DataCleanupDashboard /></AdminRoute>} />
                <Route path="content/command-center" element={<AdminRoute><ContentCommandCenter /></AdminRoute>} />
                <Route path="content/orchestrator" element={<AdminRoute><ContentOrchestrator /></AdminRoute>} />
                <Route path="content/sources" element={<AdminRoute><GenerationSources /></AdminRoute>} />
                <Route path="content/sources/:sourceType" element={<AdminRoute><SourceDetail /></AdminRoute>} />
                <Route path="content/countries" element={<AdminRoute><CountryProfiles /></AdminRoute>} />
                <Route path="content/country/:countrySlug" element={<AdminRoute><CountryProfileDetail /></AdminRoute>} />
                <Route path="content/cities" element={<AdminRoute><CityProfiles /></AdminRoute>} />
                <Route path="content/cities/:citySlug" element={<AdminRoute><CityProfileDetail /></AdminRoute>} />

                {/* Content Engine v2 */}
                <Route path="content/overview" element={<AdminRoute><ContentOverview /></AdminRoute>} />
                <Route path="content/scheduler" element={<AdminRoute><DailyScheduler /></AdminRoute>} />
                <Route path="content/publication" element={<AdminRoute><PublicationControl /></AdminRoute>} />
                <Route path="content/quality" element={<AdminRoute><QualityMonitoring /></AdminRoute>} />
                <Route path="content/taxonomies" element={<AdminRoute><TaxonomyManager /></AdminRoute>} />
                <Route path="content/articles" element={<AdminRoute><ArticlesList /></AdminRoute>} />
                <Route path="content/articles/new" element={<AdminRoute><ArticleCreate /></AdminRoute>} />
                <Route path="content/articles/:id" element={<AdminRoute><ArticleDetail /></AdminRoute>} />
                <Route path="content/comparatives" element={<AdminRoute><ComparativesList /></AdminRoute>} />
                <Route path="content/comparatives/new" element={<AdminRoute><ComparativeCreate /></AdminRoute>} />
                <Route path="content/comparatives/:id" element={<AdminRoute><ComparativeDetail /></AdminRoute>} />
                <Route path="content/affiliate-comparatives" element={<AdminRoute><ComparativesList /></AdminRoute>} />
                <Route path="content/affiliate-programs" element={<AdminRoute><AffiliateDashboard /></AdminRoute>} />
                <Route path="content/campaigns" element={<AdminRoute><CampaignsList /></AdminRoute>} />
                <Route path="content/campaigns/new" element={<AdminRoute><CampaignCreate /></AdminRoute>} />
                <Route path="content/campaigns/:id" element={<AdminRoute><CampaignDetail /></AdminRoute>} />

                {/* Content Pipeline: Clusters, Q&A, Keywords, Translations */}
                <Route path="content/clusters" element={<AdminRoute><ClustersList /></AdminRoute>} />
                <Route path="content/clusters/:id" element={<AdminRoute><ClusterDetail /></AdminRoute>} />
                <Route path="content/question-clusters" element={<AdminRoute><QuestionClustersList /></AdminRoute>} />
                <Route path="content/generate-qr" element={<AdminRoute><GenerateQr /></AdminRoute>} />
                <Route path="content/question-clusters/:id" element={<AdminRoute><QuestionClusterDetail /></AdminRoute>} />
                <Route path="content/landing-generator" element={<AdminRoute><LandingGeneratorHub /></AdminRoute>} />
                <Route path="content/landing-generator/clients" element={<AdminRoute><LandingGeneratorClients /></AdminRoute>} />
                <Route path="content/landing-generator/avocats" element={<AdminRoute><LandingGeneratorAvocats /></AdminRoute>} />
                <Route path="content/landing-generator/helpers" element={<AdminRoute><LandingGeneratorHelpers /></AdminRoute>} />
                <Route path="content/landing-generator/matching" element={<AdminRoute><LandingGeneratorMatching /></AdminRoute>} />
                <Route path="content/landings" element={<AdminRoute><LandingsList /></AdminRoute>} />
                <Route path="content/landings/new" element={<AdminRoute><LandingCreate /></AdminRoute>} />
                <Route path="content/landings/:id" element={<AdminRoute><LandingDetail /></AdminRoute>} />
                <Route path="content/press" element={<AdminRoute><PressList /></AdminRoute>} />
                <Route path="content/sondages" element={<AdminRoute><SondagesList /></AdminRoute>} />
                <Route path="content/sondages/resultats" element={<AdminRoute><SondagesResultats /></AdminRoute>} />
                <Route path="content/outils" element={<AdminRoute><PromoToolsAdmin /></AdminRoute>} />
                <Route path="content/outils-visiteurs" element={<AdminRoute><OutilsVisiteursAdmin /></AdminRoute>} />
                <Route path="content/news" element={<AdminRoute><NewsHub /></AdminRoute>} />
                <Route path="content/fiches-general" element={<AdminRoute><FichesPays type="general" /></AdminRoute>} />
                <Route path="content/fiches-expatriation" element={<AdminRoute><FichesPays type="expatriation" /></AdminRoute>} />
                <Route path="content/fiches-vacances" element={<AdminRoute><FichesPays type="vacances" /></AdminRoute>} />
                <Route path="content/chatters" element={<AdminRoute><ContentGenerator type="chatters" /></AdminRoute>} />
                <Route path="content/influenceurs" element={<AdminRoute><ContentGenerator type="influenceurs" /></AdminRoute>} />
                <Route path="content/admin-groupes" element={<AdminRoute><ContentGenerator type="admin-groupes" /></AdminRoute>} />
                <Route path="content/avocats" element={<AdminRoute><ContentGenerator type="avocats" /></AdminRoute>} />
                <Route path="content/expats-aidants" element={<AdminRoute><ContentGenerator type="expats-aidants" /></AdminRoute>} />
                <Route path="content/temoignages" element={<AdminRoute><ContentGenerator type="testimonial" /></AdminRoute>} />
                <Route path="content/souffrances" element={<AdminRoute><ContentGenerator type="pain-point" /></AdminRoute>} />
                <Route path="content/fiches-villes" element={<AdminRoute><ContentGenerator type="guide-city" /></AdminRoute>} />
                <Route path="content/tutoriels" element={<AdminRoute><ContentGenerator type="tutorial" /></AdminRoute>} />
                <Route path="content/longues-traines" element={<AdminRoute><ArtLonguesTraines /></AdminRoute>} />
                <Route path="content/brand-content" element={<AdminRoute><BrandContent /></AdminRoute>} />
                <Route path="content/statistiques" element={<AdminRoute><ArtStatistiques /></AdminRoute>} />
                <Route path="content/art-mots-cles" element={<AdminRoute><ArtMotsCles /></AdminRoute>} />
                <Route path="content/templates" element={<AdminRoute><ContentTemplates /></AdminRoute>} />
                <Route path="content/templates/:id" element={<AdminRoute><ContentTemplateDetail /></AdminRoute>} />
                <Route path="content/press/releases/:id" element={<AdminRoute><PressDetail /></AdminRoute>} />
                <Route path="content/press/dossiers/:id" element={<AdminRoute><DossierDetail /></AdminRoute>} />
                <Route path="seo/keywords" element={<AdminRoute><KeywordTracker /></AdminRoute>} />
                <Route path="translations" element={<AdminRoute><TranslationsDashboard /></AdminRoute>} />

                {/* Villes scrapees */}
                <Route path="content/:sourceSlug/cities" element={<AdminRoute><ContentCities /></AdminRoute>} />

                {/* Content v1 catch-all routes (must come after specific content/* routes) */}
                <Route path="content/:sourceSlug" element={<AdminRoute><ContentSourcePage /></AdminRoute>} />
                <Route path="content/:sourceSlug/:countrySlug" element={<AdminRoute><ContentCountryPage /></AdminRoute>} />
                <Route path="content/:sourceSlug/:countrySlug/:articleSlug" element={<AdminRoute><ContentArticlePage /></AdminRoute>} />
                <Route path="seo" element={<AdminRoute><SeoDashboard /></AdminRoute>} />
                <Route path="seo/internal-links" element={<AdminRoute><SeoInternalLinks /></AdminRoute>} />
                <Route path="publishing" element={<AdminRoute><PublishingDashboard /></AdminRoute>} />
                <Route path="costs" element={<AdminRoute><CostsDashboard /></AdminRoute>} />
                <Route path="media" element={<AdminRoute><MediaLibrary /></AdminRoute>} />
                <Route path="admin/prompt-templates" element={<AdminRoute><PromptTemplates /></AdminRoute>} />
                <Route path="admin/presets" element={<AdminRoute><GenerationPresets /></AdminRoute>} />

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
                <Route path="settings/api-balance" element={<AdminRoute><ApiBalanceMonitor /></AdminRoute>} />

                {/* Legacy redirects — old routes redirect to new locations */}
                <Route path="statistiques" element={<Navigate to="/content/statistiques" replace />} />
                <Route path="admin" element={<Navigate to="/" replace />} />

                {/* 404 catch-all */}
                <Route path="*" element={
                  <div className="flex flex-col items-center justify-center h-96 gap-3">
                    <p className="text-2xl font-bold text-text">404</p>
                    <p className="text-muted">Page introuvable</p>
                    <Navigate to="/" replace />
                  </div>
                } />
              </Route>
            </Routes>
          </Suspense>
        </ErrorBoundary>
      </BrowserRouter>
    </AuthContext.Provider>
  );
}
