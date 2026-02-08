import React from 'react';
import { useAuth } from '../stores/auth';
import { useNavigate } from 'react-router-dom';

const LogoutButton: React.FC = () => {
  const { logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <button
      onClick={handleLogout}
      className="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 ml-4 text-sm"
    >
      Logout
    </button>
  );
};

export default LogoutButton;
