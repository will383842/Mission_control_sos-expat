import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

function EyeIcon({ open }: { open: boolean }) {
  if (open) {
    return (
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
        <circle cx="12" cy="12" r="3"/>
      </svg>
    );
  }
  return (
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
      <line x1="1" y1="1" x2="23" y2="23"/>
    </svg>
  );
}

export default function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await login(email, password);
      navigate('/');
    } catch {
      setError('Email ou mot de passe invalide.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-bg flex items-center justify-center p-4 relative overflow-hidden">
      {/* Ambient background orbs */}
      <div className="pointer-events-none absolute -top-32 -left-32 w-96 h-96 rounded-full bg-violet/20 blur-3xl" aria-hidden="true" />
      <div className="pointer-events-none absolute -bottom-32 -right-32 w-96 h-96 rounded-full bg-cyan/10 blur-3xl" aria-hidden="true" />

      <div className="w-full max-w-sm relative z-10">
        {/* Logo + brand */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-violet shadow-glow-violet ring-2 ring-violet/30 mb-4">
            <span className="font-title text-2xl font-bold text-white">MC</span>
          </div>
          <h1 className="font-title text-2xl font-bold text-white flex items-center justify-center gap-2">
            Mission Control
            <span className="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-gradient-violet text-white tracking-wider uppercase shadow-sm">v2</span>
          </h1>
          <p className="text-violet-light/80 text-xs mt-1 tracking-widest uppercase font-semibold">SOS-Expat CRM</p>
        </div>

        <form onSubmit={handleSubmit} className="bg-surface/80 backdrop-blur-xl border border-border/80 rounded-2xl p-6 space-y-4 shadow-2xl ring-1 ring-white/5">
          {error && (
            <div className="bg-red-500/10 border border-red-500/30 text-red-300 text-sm px-4 py-3 rounded-xl ring-1 ring-inset ring-red-400/10" role="alert">
              {error}
            </div>
          )}

          <div>
            <label className="block text-sm text-gray-400 mb-1.5 font-medium">Email</label>
            <input
              type="email"
              value={email}
              onChange={e => setEmail(e.target.value)}
              required
              className="w-full bg-surface2/70 border border-border/80 rounded-xl px-4 py-2.5 text-white text-sm placeholder-muted shadow-inner-sm transition-all focus:outline-none focus:border-violet focus:bg-surface2 focus:shadow-glow-violet"
              placeholder="vous@sos-expat.com"
            />
          </div>

          <div>
            <label className="block text-sm text-gray-400 mb-1.5 font-medium">Mot de passe</label>
            <div className="relative">
              <input
                type={showPassword ? 'text' : 'password'}
                value={password}
                onChange={e => setPassword(e.target.value)}
                required
                className="w-full bg-surface2/70 border border-border/80 rounded-xl px-4 py-2.5 pr-10 text-white text-sm placeholder-muted shadow-inner-sm transition-all focus:outline-none focus:border-violet focus:bg-surface2 focus:shadow-glow-violet"
                placeholder="••••••••"
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white transition-colors"
                tabIndex={-1}
                aria-label={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
              >
                <EyeIcon open={showPassword} />
              </button>
            </div>
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full bg-gradient-violet hover:shadow-glow-violet active:scale-[0.98] disabled:opacity-50 disabled:active:scale-100 text-white font-semibold py-3 rounded-xl transition-all duration-200 ring-1 ring-violet/30 shadow-lg"
          >
            {loading ? 'Connexion...' : 'Se connecter'}
          </button>
        </form>

        <p className="text-center text-[11px] text-muted mt-6 tracking-wider uppercase">
          Nouvelle interface v2 · {new Date().toISOString().slice(0, 10)}
        </p>
      </div>
    </div>
  );
}
