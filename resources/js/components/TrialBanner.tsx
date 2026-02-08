import React from 'react';

const TrialBanner: React.FC<{ daysLeft: number }> = ({ daysLeft }) => {
  if (daysLeft <= 0) return null;
  return (
    <div className="w-full bg-yellow-100 border-b border-yellow-300 text-yellow-900 text-center py-2 text-sm">
      <span className="font-semibold">Trial:</span> {daysLeft} days left. Upgrade to keep using WhatsApp features.
    </div>
  );
};

export default TrialBanner;
