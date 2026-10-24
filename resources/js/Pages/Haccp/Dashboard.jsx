import React from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';

const cards = [
    ['Heute fällig', '0', 'Kontrollen und Checklisten für heute'],
    ['Überfällig', '0', 'Nicht fristgerecht erledigte Kontrollen'],
    ['Abweichungen', '0', 'Offene Abweichungen und Maßnahmen'],
    ['Wartungen', '0', 'Demnächst fällige Gerätewartungen'],
];

export default function HaccpDashboard() {
    return (
        <TimeTrackingLayout>
            <main id="main-content" className="p-4 md:p-6 lg:p-8 space-y-6">
                <div>
                    <p className="text-xs uppercase tracking-[0.16em] text-neutral-500">HACCP</p>
                    <h1 className="text-2xl font-semibold text-neutral-900">Hygiene & Kontrollen</h1>
                    <p className="mt-1 text-sm text-neutral-600">Kontrollen, Geräte, Wartungen, Abweichungen und Monatsdokumentation.</p>
                </div>
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map(([title, value, text]) => (
                        <article key={title} className="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                            <p className="text-sm font-medium text-neutral-600">{title}</p>
                            <p className="mt-2 text-3xl font-semibold text-neutral-900">{value}</p>
                            <p className="mt-2 text-xs leading-5 text-neutral-500">{text}</p>
                        </article>
                    ))}
                </section>
                <section className="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-semibold text-neutral-900">Grundmodul eingerichtet</h2>
                    <p className="mt-2 text-sm text-neutral-600">Als Nächstes werden Stammdaten für HACCP-Rollen und Geräte sowie frei konfigurierbare Kontrollvorlagen auf dieses Datenmodell gesetzt.</p>
                </section>
            </main>
        </TimeTrackingLayout>
    );
}
