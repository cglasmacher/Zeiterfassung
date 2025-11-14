import React, { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { 
  LayoutDashboard, 
  Calendar, 
  CalendarDays, 
  Users, 
  Download, 
  Clock,
  Menu,
  X,
  ChevronRight,
  LogOut,
  Settings,
  Bell,
  Edit3
} from 'lucide-react';

export default function TimeTrackingLayout({ children }) {
  const { url } = usePage();
  const [sidebarOpen, setSidebarOpen] = useState(true);

  const links = [
    { href: '/timetracking', label: 'Dashboard', icon: LayoutDashboard },
    { href: '/timetracking/daily', label: 'Tagesübersicht', icon: Calendar },
    { href: '/timetracking/monthly', label: 'Monatsübersicht', icon: CalendarDays },
    { href: '/timetracking/planner', label: 'Dienstplan', icon: Clock },
    { href: '/timetracking/manual-entry', label: 'Manuelle Erfassung', icon: Users },
    { href: '/timetracking/time-manipulation', label: 'Stundenmanipulation', icon: Edit3 },
    { href: '/timetracking/exports', label: 'Exporte', icon: Download },
    { href: '/timetracking/settings', label: 'Einstellungen', icon: Settings },
  ];

  return (
    <div className="min-h-screen flex bg-gradient-to-br from-neutral-50 via-primary-50/30 to-accent-50/20">
      {/* Sidebar */}
      <aside 
        className={`${
          sidebarOpen ? 'w-72' : 'w-20'
        } bg-white shadow-xl transition-all duration-300 ease-in-out flex flex-col border-r border-neutral-200`}
      >
        {/* Logo & Toggle */}
        <div className="p-6 border-b border-neutral-200 flex items-center justify-between">
          {sidebarOpen ? (
            <>
              <div>
                <h1 className="text-2xl font-bold text-gradient">Hemingway</h1>
                <p className="text-xs text-neutral-500 mt-0.5">Zeiterfassung</p>
              </div>
              <button
                onClick={() => setSidebarOpen(false)}
                className="p-2 hover:bg-neutral-100 rounded-lg transition-colors"
              >
                <X className="w-5 h-5 text-neutral-600" />
              </button>
            </>
          ) : (
            <button
              onClick={() => setSidebarOpen(true)}
              className="p-2 hover:bg-neutral-100 rounded-lg transition-colors mx-auto"
            >
              <Menu className="w-5 h-5 text-neutral-600" />
            </button>
          )}
        </div>

        {/* Navigation */}
        <nav className="flex-1 p-4 space-y-1 overflow-y-auto custom-scrollbar">
          {links.map(link => {
            const Icon = link.icon;
            const isActive = url.startsWith(link.href);
            
            return (
              <Link
                key={link.href}
                href={link.href}
                className={`
                  flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200
                  ${isActive 
                    ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' 
                    : 'text-neutral-700 hover:bg-neutral-100'
                  }
                  ${!sidebarOpen && 'justify-center'}
                `}
              >
                <Icon className={`w-5 h-5 flex-shrink-0 ${isActive ? 'text-white' : 'text-neutral-600'}`} />
                {sidebarOpen && (
                  <>
                    <span className="flex-1 font-medium">{link.label}</span>
                    {isActive && <ChevronRight className="w-4 h-4" />}
                  </>
                )}
              </Link>
            );
          })}
        </nav>

        {/* User Section */}
        <div className="p-4 border-t border-neutral-200">
          {sidebarOpen ? (
            <div className="space-y-2">
              <Link 
                href="/logout" 
                method="post" 
                as="button"
                className="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-error-50 transition-colors text-error-600"
              >
                <LogOut className="w-5 h-5" />
                <span className="text-sm font-medium">Abmelden</span>
              </Link>
            </div>
          ) : (
            <div className="space-y-2">
              <Link 
                href="/logout" 
                method="post" 
                as="button"
                className="w-full p-2.5 rounded-lg hover:bg-error-50 transition-colors flex justify-center"
              >
                <LogOut className="w-5 h-5 text-error-600" />
              </Link>
            </div>
          )}
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 flex flex-col overflow-hidden">
        {/* Top Bar */}
        <header className="bg-white/80 backdrop-blur-lg border-b border-neutral-200 px-8 py-4 shadow-sm">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-2xl font-bold text-neutral-900">
                {links.find(l => url.startsWith(l.href))?.label || 'Dashboard'}
              </h2>
              <p className="text-sm text-neutral-500 mt-0.5">
                {new Date().toLocaleDateString('de-DE', { 
                  weekday: 'long', 
                  year: 'numeric', 
                  month: 'long', 
                  day: 'numeric' 
                })}
              </p>
            </div>
            
            <div className="flex items-center gap-4">
              <button className="relative p-2 hover:bg-neutral-100 rounded-lg transition-colors">
                <Bell className="w-5 h-5 text-neutral-600" />
                <span className="absolute top-1 right-1 w-2 h-2 bg-error-500 rounded-full"></span>
              </button>
              
              <div className="flex items-center gap-3 pl-4 border-l border-neutral-200">
                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-semibold">
                  A
                </div>
                <div>
                  <p className="text-sm font-semibold text-neutral-900">Admin User</p>
                  <p className="text-xs text-neutral-500">Manager</p>
                </div>
              </div>
            </div>
          </div>
        </header>

        {/* Content Area */}
        <div className="flex-1 overflow-y-auto custom-scrollbar p-8">
          <div className="max-w-[1600px] mx-auto">
            {children}
          </div>
        </div>
      </main>
    </div>
  );
}