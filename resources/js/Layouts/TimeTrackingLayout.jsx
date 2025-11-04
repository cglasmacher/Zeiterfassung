import React from 'react';
import { Link, usePage } from '@inertiajs/react';

export default function TimeTrackingLayout({ children }) {
  const { url } = usePage();

  const links = [
    { href: '/timetracking', label: 'Dashboard' },
    { href: '/timetracking/daily', label: 'Tagesübersicht' },
    { href: '/timetracking/monthly', label: 'Monatsübersicht' },
    { href: '/timetracking/planner', label: 'Dienstplan' },
    { href: '/timetracking/employees', label: 'Mitarbeiter' },
    { href: '/timetracking/exports', label: 'Exporte' },
  ];

  return (
    <div className="min-h-screen flex bg-gray-100">
      <aside className="w-64 bg-white shadow-md">
        <div className="p-4 font-bold text-xl border-b">Zeiterfassung</div>
        <nav className="flex flex-col space-y-1 p-2">
          {links.map(link => (
            <Link
              key={link.href}
              href={link.href}
              className={`p-2 rounded-md ${url.startsWith(link.href) ? 'bg-blue-600 text-white' : 'hover:bg-gray-200'}`}
            >
              {link.label}
            </Link>
          ))}
        </nav>
      </aside>
      <main className="flex-1 p-6">{children}</main>
    </div>
  );
}
