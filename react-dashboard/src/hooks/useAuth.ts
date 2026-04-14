import { useState, useEffect, createContext, useContext } from 'react';
import api, { saveToken, clearToken, getStoredToken } from '../api/client';
import type { TeamMember } from '../types/influenceur';

interface AuthContextValue {
  user: Pick<TeamMember, 'id' | 'name' | 'email' | 'role'> | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextValue>({
  user: null,
  loading: true,
  login: async () => {},
  logout: async () => {},
});

export function useAuth() {
  return useContext(AuthContext);
}

export function useAuthProvider(): AuthContextValue {
  const [user, setUser] = useState<AuthContextValue['user']>(null);
  const [loading, setLoading] = useState(true);

  // On mount: restore session from stored token
  useEffect(() => {
    if (!getStoredToken()) {
      setLoading(false);
      return;
    }
    api.get('/me')
      .then(({ data }) => setUser(data))
      .catch(() => { clearToken(); setUser(null); })
      .finally(() => setLoading(false));
  }, []);

  const login = async (email: string, password: string) => {
    const { data } = await api.post('/login', { email, password });
    saveToken(data.token);
    setUser(data.user);
  };

  const logout = async () => {
    try { await api.post('/logout'); } catch {}
    clearToken();
    setUser(null);
  };

  return { user, loading, login, logout };
}
