import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import TailwindTest from './pages/TailwindTest';
import Layout from './Layout';
import Login from './pages/Login';
import ResetPassword from './pages/ResetPassword';
import WhatsApp from './pages/WhatsApp';
import Labels from './pages/Labels';
import Contacts from './pages/Contacts';
import { useAuth } from './stores/auth';

// Placeholder pages
const Inbox = () => <div className="text-xl">Inbox</div>;
const Automations = () => <div className="text-xl">Automations</div>;
const BusinessHours = () => <div className="text-xl">Business Hours</div>;
const Usage = () => <div className="text-xl">Usage & Trial Status</div>;
const Team = () => <div className="text-xl">Team Management</div>;

const Protected: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { user, loading } = useAuth();
  if (loading) return <div className="flex h-screen items-center justify-center">Loading...</div>;
  if (!user) return <Navigate to="/login" replace />;
  return <>{children}</>;
};

const AppRouter: React.FC = () => (
  <Router>
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/reset-password" element={<ResetPassword />} />
      <Route path="/tailwind-test" element={<TailwindTest />} />
      <Route
        path="/*"
        element={
          <Protected>
            <Layout>
              <Routes>
                <Route path="/" element={<Navigate to="/inbox" replace />} />
                <Route path="/inbox" element={<Inbox />} />
                <Route path="/contacts" element={<Contacts />} />
                <Route path="/labels" element={<Labels />} />
                <Route path="/automations" element={<Automations />} />
                <Route path="/business-hours" element={<BusinessHours />} />
                <Route path="/usage" element={<Usage />} />
                <Route path="/team" element={<Team />} />
                <Route path="/whatsapp" element={<WhatsApp />} />
              </Routes>
            </Layout>
          </Protected>
        }
      />
    </Routes>
  </Router>
);

export default AppRouter;
