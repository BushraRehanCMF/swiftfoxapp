import React from 'react';
import type { PropsWithChildren } from 'react';
import Sidebar from './components/Sidebar';
import TopBar from './components/TopBar';
import TrialBanner from './components/TrialBanner';

const Layout: React.FC<PropsWithChildren> = ({ children }) => {
  // TODO: Replace with real data from API/auth context
  const trialDaysLeft = 12;
  const conversationsUsed = 8;
  const conversationsLimit = 100;
  const accountName = 'Acme Inc.';

  return (
    <div className="flex h-screen bg-gray-50">
      <Sidebar />
      <div className="flex-1 flex flex-col min-w-0">
        <TopBar
          accountName={accountName}
          trialDaysLeft={trialDaysLeft}
          conversationsUsed={conversationsUsed}
          conversationsLimit={conversationsLimit}
        />
        <TrialBanner daysLeft={trialDaysLeft} />
        <main className="flex-1 overflow-y-auto p-8">{children}</main>
      </div>
    </div>
  );
};

export default Layout;
