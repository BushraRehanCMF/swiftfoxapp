import React, { useEffect, useState } from 'react';
import type { PropsWithChildren } from 'react';
import Sidebar from './components/Sidebar';
import TopBar from './components/TopBar';
import TrialBanner from './components/TrialBanner';
import api from './services/api';

type AccountData = {
  id: string;
  name: string;
  subscription_status: string;
  trial: {
    is_on_trial: boolean;
    is_expired: boolean;
    days_remaining: number;
  };
  usage: {
    conversations_used: number;
    conversations_limit: number;
  };
};

const Layout: React.FC<PropsWithChildren> = ({ children }) => {
  const [account, setAccount] = useState<AccountData | null>(null);

  useEffect(() => {
    const fetchAccount = async () => {
      try {
        const { data } = await api.get('/auth/user');
        setAccount(data.data.account || null);
      } catch (err) {
        console.error('Failed to load account data:', err);
      }
    };

    fetchAccount();
  }, []);

  // Default values while loading or if data unavailable
  const accountName = account?.name || 'Account';
  const trialDaysLeft = account?.trial?.days_remaining || 0;
  const conversationsUsed = account?.usage?.conversations_used || 0;
  const conversationsLimit = account?.usage?.conversations_limit || 100;
  const isOnTrial = account?.trial?.is_on_trial || false;

  return (
    <div className="flex h-screen bg-gray-50">
      <Sidebar />
      <div className="flex-1 flex flex-col min-w-0">
        <TopBar
          accountName={accountName}
          trialDaysLeft={trialDaysLeft}
          conversationsUsed={conversationsUsed}
          conversationsLimit={conversationsLimit}
          isOnTrial={isOnTrial}
        />
        {isOnTrial && <TrialBanner daysLeft={trialDaysLeft} />}
        <main className="flex-1 overflow-y-auto p-8">{children}</main>
      </div>
    </div>
  );
};

export default Layout;
