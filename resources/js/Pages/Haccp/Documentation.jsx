import { Head, Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const monthNames = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

export default function Documentation() {
    const now = new Date();
    const [year, setYear] = useState(now.getFullYear());
    const [month, setMonth] = useState(now.getMonth() + 1);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [closing, setClosing] = useState(false);
    const [error, setError] = useState('');

    const document = data?.document;
    const stats = document?.statistics;
    const years = useMemo(() => Array.from({ length: 5 }, (_, i) => now.getFullYear() - i), []);

    async function load() {
        setLoading(true); setError('');
        try {
            const response = await fetch(`/api/haccp/documentation/${year}/${month}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Monatsdokumentation konnte nicht geladen werden.');
            setData(await response.json());
        } catch (e) { setError(e.message); }
        finally { setLoading(false); }
    }

    useEffect(() => { load(); }, [year, month]);

    async function closeMonth() {
        if (!window.confirm(`${monthNames[month - 1]} ${year} endgültig abschließen? Die Monatsdokumentation wird danach als unveränderliche PDF archiviert.`)) return;
        setClosing(true); setError('');
        try {
            const response = await fetch(`/api/haccp/documentation/${year}/${month}/close`, {
                method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() }, body: '{}'
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || 'Monat konnte nicht abgeschlossen werden.');
            await load();
        } catch (e) { setError(e.message); }
        finally { setClosing(false); }
    }

    const pdfUrl = `/api/haccp/documentation/${year}/${month}/pdf`;

    return <>
        <Head title="HACCP Dokumentation" />
        <div className="min-h-screen bg-slate-50 p-4 md:p-8">
            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div><div className="text-sm font-semibold uppercase tracking-wide text-slate-500">HACCP</div><h1 className="text-3xl font-bold text-slate-900">Monatsdokumentation</h1><p className="mt-1 text-slate-600">Kontrollen kompakt prüfen, abschließen und revisionsstabil archivieren.</p></div>
                    <div className="flex gap-2"><Link href="/haccp" className="rounded-lg border bg-white px-4 py-2 font-medium text-slate-700">HACCP heute</Link><Link href="/haccp/verwaltung" className="rounded-lg border bg-white px-4 py-2 font-medium text-slate-700">Verwaltung</Link></div>
                </div>

                <div className="rounded-xl border bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="text-sm font-medium text-slate-700">Monat<select value={month} onChange={e=>setMonth(Number(e.target.value))} className="mt-1 block rounded-lg border-slate-300">{monthNames.map((name,i)=><option key={name} value={i+1}>{name}</option>)}</select></label>
                        <label className="text-sm font-medium text-slate-700">Jahr<select value={year} onChange={e=>setYear(Number(e.target.value))} className="mt-1 block rounded-lg border-slate-300">{years.map(y=><option key={y}>{y}</option>)}</select></label>
                        {data?.closed && <span className="rounded-full bg-emerald-100 px-3 py-2 text-sm font-semibold text-emerald-800">Monat abgeschlossen</span>}
                    </div>
                </div>

                {error && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">{error}</div>}
                {loading ? <div className="rounded-xl border bg-white p-8 text-slate-500">Dokumentation wird geladen…</div> : document && <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        {[['Kontrollen',stats.total],['Erledigt',stats.completed],['Fehlend',stats.missing],['Abweichungen',stats.deviations],['Erfüllung',`${stats.completion_rate}%`]].map(([label,value])=><div key={label} className="rounded-xl border bg-white p-5 shadow-sm"><div className="text-sm text-slate-500">{label}</div><div className="mt-1 text-3xl font-bold text-slate-900">{value}</div></div>)}
                    </div>

                    {stats.open_deviations > 0 && <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900"><strong>{stats.open_deviations} offene Abweichung(en).</strong> Der Monat kann erst abgeschlossen werden, wenn diese bearbeitet wurden.</div>}

                    <div className="rounded-xl border bg-white p-5 shadow-sm">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"><div><h2 className="text-lg font-semibold">Temperaturkontrollen</h2><p className="text-sm text-slate-500">Kompakte Vorschau. Die PDF verwendet die vollständige Monatsmatrix im Querformat.</p></div><span className="text-sm text-slate-500">{Object.keys(document.temperature_matrix || {}).length} Kühlgeräte</span></div>
                        <div className="mt-4 overflow-x-auto"><table className="min-w-full text-sm"><thead><tr className="border-b text-left text-slate-500"><th className="py-2 pr-4">Gerät</th><th className="px-2">Standort</th><th className="px-2">Soll</th><th className="px-2">Messungen</th><th className="px-2">Auffällig</th></tr></thead><tbody>{Object.entries(document.temperature_matrix || {}).map(([name,row])=>{const entries=Object.values(row.days||{}).flat(); const measured=entries.filter(e=>!e.missing).length; const bad=entries.filter(e=>e.within_limits===false).length; return <tr key={name} className="border-b last:border-0"><td className="py-3 pr-4 font-medium">{name}</td><td className="px-2">{row.meta?.location||'–'}</td><td className="px-2">{row.meta?.min ?? '–'} – {row.meta?.max ?? '–'} {row.meta?.unit||'°C'}</td><td className="px-2">{measured}</td><td className={`px-2 font-semibold ${bad?'text-red-700':'text-emerald-700'}`}>{bad||'0'}</td></tr>})}</tbody></table></div>
                    </div>

                    {(document.missing_controls?.length > 0 || document.deviations?.length > 0) && <div className="grid gap-4 lg:grid-cols-2">
                        <div className="rounded-xl border bg-white p-5 shadow-sm"><h2 className="font-semibold">Fehlende Kontrollen</h2><div className="mt-3 space-y-2">{document.missing_controls?.length ? document.missing_controls.slice(0,8).map(item=><div key={item.id} className="rounded-lg bg-slate-50 p-3 text-sm"><strong>{item.title}</strong><div className="text-slate-500">{item.device||item.category} · {new Date(item.due_at).toLocaleString('de-DE')}</div></div>) : <p className="text-sm text-emerald-700">Keine fehlenden Kontrollen.</p>}</div></div>
                        <div className="rounded-xl border bg-white p-5 shadow-sm"><h2 className="font-semibold">Abweichungen</h2><div className="mt-3 space-y-2">{document.deviations?.length ? document.deviations.slice(0,8).map(item=><div key={item.id} className="rounded-lg bg-slate-50 p-3 text-sm"><strong>{item.control}</strong><div className="text-slate-500">{item.date} · {item.device||'ohne Gerät'}</div><div className="mt-1">{item.corrective_action||'Noch keine Maßnahme dokumentiert'}</div></div>) : <p className="text-sm text-emerald-700">Keine Abweichungen.</p>}</div></div>
                    </div>}

                    <div className="flex flex-wrap justify-end gap-3 rounded-xl border bg-white p-5 shadow-sm">
                        <a href={pdfUrl} target="_blank" rel="noreferrer" className="rounded-lg border px-4 py-2 font-semibold text-slate-700">{data.closed ? 'Archiv-PDF herunterladen' : 'PDF-Vorschau'}</a>
                        {!data.closed && <button onClick={closeMonth} disabled={closing || stats.open_deviations > 0} className="rounded-lg bg-slate-900 px-5 py-2 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">{closing?'Wird abgeschlossen…':'Monat abschließen'}</button>}
                    </div>
                </>}
            </div>
        </div>
    </>;
}
