import React from 'react';
import { NavLink } from 'react-router-dom';
import {
  Inbox,
  Users,
  Tag,
  Zap,
  Clock,
  BarChart3,
  UserPlus,
  MessageCircle
} from 'lucide-react';
import logo from '../assets/logo-white.png';

interface NavItem {
  to: string;
  label: string;
  icon: React.ComponentType<{ size?: number; className?: string }>;
}

const Sidebar: React.FC = () => {
  const navItems: NavItem[] = [
    { to: '/inbox', label: 'Inbox', icon: Inbox },
    { to: '/contacts', label: 'Contacts', icon: Users },
    { to: '/labels', label: 'Labels', icon: Tag },
    { to: '/automations', label: 'Automations', icon: Zap },
    { to: '/business-hours', label: 'Business Hours', icon: Clock },
    { to: '/usage', label: 'Usage', icon: BarChart3 },
    { to: '/team', label: 'Team', icon: UserPlus },
    { to: '/whatsapp', label: 'WhatsApp', icon: MessageCircle },
  ];

  return (
    <aside className="w-64 h-full bg-gradient-to-b from-emerald-700 to-emerald-800 text-white flex flex-col shadow-xl">
      {/* Logo Section */}
      <div className="h-16 flex items-center justify-center border-b border-emerald-600/30 backdrop-blur-sm">
        <img src={logo} alt="SwiftFox logo" className="h-9 w-auto" />
      </div>

      {/* Navigation */}
      <nav className="flex-1 px-3 py-6 space-y-1">
        {navItems.map((item) => {
          const Icon = item.icon;
          return (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200 group relative ${
                  isActive
                    ? 'bg-white/10 text-white shadow-lg backdrop-blur-sm'
                    : 'text-emerald-50/90 hover:bg-white/5 hover:text-white'
                }`
              }
            >
              {({ isActive }) => (
                <>
                  {/* Active indicator */}
                  {isActive && (
                    <div className="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-white rounded-r-full" />
                  )}

                  {/* Icon */}
                  <Icon
                    size={20}
                    className={`transition-transform duration-200 ${
                      isActive ? 'scale-110' : 'group-hover:scale-105'
                    }`}
                  />

                  {/* Label */}
                  <span className="text-sm font-medium">
                    {item.label}
                  </span>
                </>
              )}
            </NavLink>
          );
        })}
      </nav>

      {/* Footer Section (optional) */}
      <div className="px-3 pb-4 border-t border-emerald-600/30">
        <div className="mt-4 px-4 py-3 bg-white/5 rounded-lg backdrop-blur-sm">
          <p className="text-xs text-emerald-100/80 font-medium">SwiftFox Cloud v1.2</p>
          <p className="text-xs text-emerald-200/60 mt-0.5">by CNP Group LLC</p>
        </div>
      </div>
    </aside>
  );
};

export default Sidebar;
