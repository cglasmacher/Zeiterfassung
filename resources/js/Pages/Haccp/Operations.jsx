import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import axios from 'axios';

export default function Operations() {
    const [data, setData] = useState({ maintenance: [], deviations: [] });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const load = async () => { setLoading(true); setError(''); try { const r = await axios.get('/api/haccp/operations'); setData(r.data); } catch (e) { setError(e.response?.data?.message || 'HACCP-Daten konnten nicht geladen werden.'); } finally { setLoading(false); } };
    useEffect(() => { load(); }, []);

    const completeMaintenance = async (plan) => {
        const provider = window.prompt('Ausgeführt durch / Dienstleister (optional):', plan.last_provider || '') ?? null;
        if (provider === null) return;
        const notes = window.prompt('Notiz zur Wartung (optional):', '') ?? null;
        if (notes === null) return;
        await axios.post(`/api/haccp/operations/maintenance/${plan.id}/complete`, { provider, notes });
        await load();
    };

    const resolve = async (deviation) => {
        const corrective_action = window.prompt('Durchgeführte Korrekturmaßnahme:', deviation.corrective_action || '');
        if (!corrective_action) return;
        const follow_up_value = window.prompt('Nachkontrollwert (optional):', '');
        const follow_up_note = window.prompt('Vermerk zur Nachkontrolle (optional):', '');
        await axios.post(`/api/haccp/operations/deviations/${deviation.id}/resolve`, { corrective_action, follow_up_value: follow_up_value === '' ? null : Number(follow_up_value), follow_up_note });
        await load();
    };

    const dueClass = (date) => date && new Date(date + 'T23:59:59') < new Date() ? 'text-red-700 font-semibold' : 'text-slate-700';

    return <AuthenticatedLayout header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">HACCP · Wartungen & Abweichungen</h2>}>
        <div className="py-8"><div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div className="flex gap-3"><a href="/haccp" className="rounded-lg border px-4 py-2 bg-white">HACCP heute</a><a href="/haccp/dokumentation" className="rounded-lg border px-4 py-2 bg-white">Monatsdokumentation</a><a href="/haccp/verwaltung" className="rounded-lg border px-4 py-2 bg-white">Verwaltung</a></div>
            {error && <div className="rounded-lg bg-red-50 p-4 text-red-700">{error}</div>}
            {loading ? <div className="bg-white rounded-xl shadow p-6">Lade…</div> : <>
                <section className="bg-white rounded-xl shadow p-6"><div className="flex items-center justify-between mb-4"><div><h3 className="text-lg font-semibold">Wartungen</h3><p className="text-sm text-slate-500">Fälligkeiten je Gerät. Eine erledigte Wartung berechnet den nächsten Termin automatisch.</p></div><span className="text-sm text-slate-500">{data.maintenance.length} Pläne</span></div>
                    <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead><tr className="border-b text-left text-slate-500"><th className="py-2">Gerät</th><th>Wartung</th><th>Intervall</th><th>Zuletzt</th><th>Nächster Termin</th><th></th></tr></thead><tbody>{data.maintenance.map(p => <tr key={p.id} className="border-b last:border-0"><td className="py-3"><b>{p.device_name}</b><div className="text-xs text-slate-500">{p.location || '—'}</div></td><td>{p.name}</td><td>{p.interval_value} {({days:'Tag(e)',weeks:'Woche(n)',months:'Monat(e)',years:'Jahr(e)'})[p.frequency] || p.frequency}</td><td>{p.last_completed_at || p.last_record_at || '—'}</td><td className={dueClass(p.next_due_at)}>{p.next_due_at || 'nicht gesetzt'}</td><td className="text-right"><button onClick={() => completeMaintenance(p)} className="rounded-lg bg-slate-900 text-white px-3 py-2">Wartung erledigt</button></td></tr>)}</tbody></table>{!data.maintenance.length && <p className="py-6 text-slate-500">Noch keine Wartungspläne vorhanden. Diese werden in der HACCP-Verwaltung den Geräten zugeordnet.</p>}</div>
                </section>
                <section className="bg-white rounded-xl shadow p-6"><div className="flex items-center justify-between mb-4"><div><h3 className="text-lg font-semibold">Abweichungen</h3><p className="text-sm text-slate-500">Abweichung → Korrekturmaßnahme → Nachkontrolle → Abschluss.</p></div><span className="text-sm text-slate-500">{data.deviations.filter(d => !d.resolved_at).length} offen</span></div>
                    <div className="space-y-3">{data.deviations.map(d => <div key={d.id} className={`rounded-xl border p-4 ${d.resolved_at ? 'bg-slate-50' : 'border-amber-300 bg-amber-50'}`}><div className="flex flex-col md:flex-row md:items-start md:justify-between gap-3"><div><div className="font-semibold">A-{String(d.id).padStart(4,'0')} · {d.device_name || d.title}</div><div className="text-sm text-slate-600">{d.completed_at} · {d.description}</div>{d.numeric_value !== null && <div className="text-sm mt-1">Messwert: <b>{d.numeric_value}</b></div>}{d.corrective_action && <div className="text-sm mt-2 whitespace-pre-line"><b>Maßnahme:</b> {d.corrective_action}</div>}{d.resolved_at && <div className="text-xs text-slate-500 mt-2">Abgeschlossen: {d.resolved_at} · {d.resolved_by_name || 'Benutzer'}</div>}</div>{!d.resolved_at && <button onClick={() => resolve(d)} className="rounded-lg bg-amber-700 text-white px-4 py-2 shrink-0">Nachkontrolle & abschließen</button>}</div></div>)}{!data.deviations.length && <p className="text-slate-500">Keine Abweichungen vorhanden.</p>}</div>
                </section>
            </>}
        </div></div>
    </AuthenticatedLayout>;
}
