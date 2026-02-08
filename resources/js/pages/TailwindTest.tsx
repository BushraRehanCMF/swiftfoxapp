import React from 'react';

const TailwindTest: React.FC = () => (
  <div className="min-h-screen flex items-center justify-center bg-emerald-100">
    <div className="bg-white p-8 rounded shadow-md text-center">
      <h1 className="text-4xl font-bold text-emerald-700 mb-4">Tailwind Test</h1>
      <p className="text-lg text-gray-700 mb-2">If you see this box styled, Tailwind is working!</p>
      <button className="px-6 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700 transition">Test Button</button>
    </div>
  </div>
);

export default TailwindTest;
