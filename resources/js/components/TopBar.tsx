import React from 'react';
import { useNavigate } from 'react-router-dom';
import LogoutButton from './LogoutButton';

interface TopBarProps {
  accountName?: string;
  trialDaysLeft?: number;
  conversationsUsed?: number;
  conversationsLimit?: number;
}

const TopBar: React.FC<TopBarProps> = ({ accountName = 'Account', trialDaysLeft = 14, conversationsUsed = 0, conversationsLimit = 100 }) => {
    const navigate = useNavigate();

  return (
    <header className="h-16 flex items-center justify-between px-6 bg-white border-b border-gray-200 shadow-sm">
      <div className="font-semibold text-lg text-emerald-700">{accountName}</div>
      <div className="flex items-center gap-6">
        <div className="text-sm text-gray-700">
          Trial: <span className="font-medium text-emerald-700">{trialDaysLeft} days left</span>
        </div>
        <div className="text-sm text-gray-700">
          Conversations: <span className="font-medium text-emerald-700">{conversationsUsed}/{conversationsLimit}</span>
        </div>
        <button
          onClick={() => navigate('/pricing')}
          className="ml-4 px-4 py-1 bg-emerald-600 text-white rounded hover:bg-emerald-700 transition"
        >
          Upgrade
        </button>
        <LogoutButton />
      </div>
    </header>
  );
};

export default TopBar;
