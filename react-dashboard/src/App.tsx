import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthContext, useAuthProvider } from './hooks/useAuth';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Influenceurs from './pages/Influenceurs';
import InfluenceurDetail from './pages/InfluenceurDetail';
import ARelancer from './pages/ARelancer';
import Statistiques from './pages/Statistiques';
import Equipe from './pages/Equipe';
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

export default function App() {
  const auth = useAuthProvider();

  return (
    <AuthContext.Provider value={auth}>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/" element={<PrivateRoute><Layout /></PrivateRoute>}>
            <Route index element={<Dashboard />} />
            <Route path="influenceurs" element={<Influenceurs />} />
            <Route path="influenceurs/:id" element={<InfluenceurDetail />} />
            <Route path="a-relancer" element={<ARelancer />} />
            <Route path="statistiques" element={<Statistiques />} />
            <Route path="equipe" element={<AdminRoute><Equipe /></AdminRoute>} />
          </Route>
        </Routes>
      </BrowserRouter>
    </AuthContext.Provider>
  );
}
