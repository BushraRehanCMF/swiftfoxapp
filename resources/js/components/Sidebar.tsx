import React from 'react';
import logo from '../assets/logo-white.png';

const Sidebar: React.FC = () => {
  return (
    <aside className="w-64 h-full bg-emerald-700 text-white flex flex-col">
      <div className="h-16 flex items-center justify-center border-b border-emerald-800">
        <img src={logo} alt="SwiftFox logo" className="h-8 w-auto" />
      </div>
      <nav className="flex-1 px-4 py-6 space-y-2">
        <a href="/inbox" className="block py-2 px-3 rounded hover:bg-emerald-800">Inbox</a>
        <a href="/contacts" className="block py-2 px-3 rounded hover:bg-emerald-800">Contacts</a>
        <a href="/labels" className="block py-2 px-3 rounded hover:bg-emerald-800">Labels</a>
        <a href="/automations" className="block py-2 px-3 rounded hover:bg-emerald-800">Automations</a>
        <a href="/business-hours" className="block py-2 px-3 rounded hover:bg-emerald-800">Business Hours</a>
        <a href="/usage" className="block py-2 px-3 rounded hover:bg-emerald-800">Usage</a>
        <a href="/team" className="block py-2 px-3 rounded hover:bg-emerald-800">Team</a>
        <a href="/whatsapp" className="block py-2 px-3 rounded hover:bg-emerald-800">WhatsApp</a>
      </nav>
    </aside>
  );
};

export default Sidebar;
