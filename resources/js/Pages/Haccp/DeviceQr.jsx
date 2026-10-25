import React, { useEffect, useState } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import axios from 'axios';

const time = value => new Date(value).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });

export default function DeviceQr({ qrToken }) {
    const [data, setData] = useState(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    const load = async () => {
        setBusy(true); setError('');
        try { setData((await axios.get(`/api/haccp/qr/${qrToken}`)).data); }
        catch (e) { setError(e.response?.data?.message || 'Gerät konnte nicht geladen werden.'); }
        finally { setBusy(false); }
    };
    useEffect(() => { load(); }, [qrToken]);

    const open = data?.open_tasks || [];
    return <TimeTrackingLayout><main id="main-content" className="mx-auto max-w-2xl space-y-4 p-4 md:p-6">
        <a href="/haccp" className="inline-flex min-h-11 items-center rounded-xl border bg-white px-4 text-sm font-medium">← HACCP heute</a>
        {busy && !data && <div className="rounded-2xl border bg-white p-6 text-sm text-neutral-500">Gerät wird geladen …</div>}
        {error && <div className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">{error}</div>}
        {data && <>
            <section className="rounded-2xl border bg-white p-5 shadow-sm">
                <p className="text-xs font-semibold uppercase tracking-[.16em] text-neutral-500">HACCP · QR-Kontrollpunkt</p>
                <h1 className="mt-1 text-2xl font-semibold">{data.device.name}</h1>
                <p className="mt-1 text-sm text-neutral-600">{[data.device.device_type_name, data.device.location].filter(Boolean).join(' · ')}</p>
                {(data.device.min_value !== null || data.device.max_value !== null) && <p className="mt-3 rounded-xl bg-neutral-50 px-3 py-2 text-sm">Geräte-Sollbereich: <strong>{data.device.min_value ?? '–'} bis {data.device.max_value ?? '–'} {data.device.unit || ''}</strong></p>}
            </section>
            {open.length === 0 ? <section className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"><strong className="text-emerald-900">✓ Für dieses Gerät ist heute nichts mehr offen.</strong><p className="mt-1 text-sm text-emerald-800">Bereits erledigte Kontrollen findest du unter HACCP heute.</p></section> : <section className="space-y-3">
                <div><h2 className="text-lg font-semibold">Jetzt zu erledigen</h2><p className="text-sm text-neutral-500">{open.length} offene {open.length === 1 ? 'Kontrolle' : 'Kontrollen'} für dieses Gerät.</p></div>
                {open.map(task => <a key={task.id} href={`/haccp?task=${task.id}`} className={`block rounded-2xl border bg-white p-5 shadow-sm active:scale-[.99] ${task.snapshot?.criticality === 'critical' ? 'border-red-300' : ''}`}>
                    <div className="flex items-start justify-between gap-3"><div><div className="flex flex-wrap items-center gap-2"><strong className="text-base">{task.title}</strong>{task.snapshot?.criticality === 'critical' && <span className="rounded-full bg-red-100 px-2 py-1 text-xs font-bold text-red-800">CCP</span>}</div><p className="mt-1 text-sm text-neutral-500">Fällig {time(task.due_at)}{task.role_name ? ` · ${task.role_name}` : ''}</p></div><span className="text-xl">›</span></div>
                    {(task.min_value !== null || task.max_value !== null) && <p className="mt-3 text-sm">Soll: <strong>{task.min_value ?? '–'} bis {task.max_value ?? '–'} {task.unit || ''}</strong></p>}
                </a>)}
            </section>}
        </>}
    </main></TimeTrackingLayout>;
}
