import React, { createContext, useContext, useState, useEffect } from 'react';
import api from '../services/api';

interface User {
  id: string;
  name: string;
  email: string;
  role: 'owner' | 'member' | 'super_admin';
  account_id?: string;
}

interface AuthContextType {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // On mount, check if user is authenticated
    const fetchUser = async () => {
      const token = localStorage.getItem('auth_token');
      if (!token) {
        setLoading(false);
        return;
      }

      api.defaults.headers.common.Authorization = `Bearer ${token}`;
      try {
        const { data } = await api.get('/auth/user');
        setUser(data.data);
      } catch {
        setUser(null);
        localStorage.removeItem('auth_token');
        delete api.defaults.headers.common.Authorization;
      } finally {
        setLoading(false);
      }
    };
    fetchUser();
  }, []);

  const login = async (email: string, password: string) => {
    setLoading(true);
    try {
      const { data: loginResponse } = await api.post('/auth/login', { email, password });
      const token = loginResponse?.data?.token;
      if (token) {
        localStorage.setItem('auth_token', token);
        api.defaults.headers.common.Authorization = `Bearer ${token}`;
      }

      const { data } = await api.get('/auth/user');
      setUser(data.data);
    } catch (err: any) {
      setUser(null);
      localStorage.removeItem('auth_token');
      delete api.defaults.headers.common.Authorization;
      throw new Error(err.response?.data?.error?.message || 'Invalid credentials');
    } finally {
      setLoading(false);
    }
  };

  const logout = async () => {
    await api.post('/auth/logout');
    setUser(null);
    localStorage.removeItem('auth_token');
    delete api.defaults.headers.common.Authorization;
  };

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
};
