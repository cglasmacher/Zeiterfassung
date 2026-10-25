import { useEffect, useState } from 'react';
import axios from 'axios';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';

export default function Documentation() {
    const now = new Date();
    const [year, setYear] = useState(now.getFullYear());
    const [month, setMonth] = useState(now.getMonth() + 1);
    const [data, setData] = useState(null);
    const [corrections, setCorrections] = useState([]);
    const [loading, setLoading] = useState(false);

    const load = async () => {
        setLoading(true);
        try { const [d,c]=await Promise.all([axios.get(`/api/haccp/documentation/${year}/${month}`),axios.get(`/api/haccp/corrections/month/${year}/${month}`).catch(()=>({data:{corrections:[]}}))]);setData(d.data);setCorrections(c.data.corrections||[]); }
        finally { setLoading(false); }
    };
    useEffect(() => { load(); }, [year, month]);

    const closeMonth = async () => {
        if (!confirm('Monat endgültig abschließen? Die Dokumentation wird danach revisionssicher eingefroren.')) return;
        try { await axios.post(`/api/haccp/documentation/${year}/${month}/close`); await load(); }
        catch (e) { alert(e.response?.data?.message || 'Monatsabschluss nicht möglich.'); }
    };

    const doc = data?.document;
    const stat = doc?.statistics || {};
    const cards = [
        ['Kontrollen', stat.total ?? 0], ['Erledigt', stat.completed ?? 0], ['Fehlend', stat.missing ?? 0],
        ['Abweichungen', stat.deviations ?? 0], ['Wareneingänge', stat.goods_receipts ?? 0], ['Reinigungen', `${stat.cleaning_completed ?? 0}/${stat.cleaning_due ?? 0}`],
        ['Speisen-Stichproben', `${stat.food_samples_completed ?? 0}/${stat.food_samples_due ?? 0}`], ['Rückstellproben', stat.retention_samples ?? 0],
    ];

    return <TimeTrackingLayout title="HACCP · Dokumentation">
        <div className="space-y-6">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div><h1 className="text-2xl font-semibold">HACCP Monatsdokumentation</h1><p className="text-sm text-gray-500">Hemingway Gastro & Events GmbH · Markt 16 · 40721 Hilden</p></div>
                <div className="flex flex-wrap gap-2">
                    <input className="rounded border px-3 py-2 w-24" type="number" value={year} onChange={e=>setYear(Number(e.target.value))}/>
                    <select className="rounded border px-3 py-2" value={month} onChange={e=>setMonth(Number(e.target.value))}>{Array.from({length:12},(_,i)=><option key={i+1} value={i+1}>{String(i+1).padStart(2,'0')}</option>)}</select>
                    <a href="/haccp/korrekturen" className="rounded border bg-white px-4 py-2 text-sm">Korrekturen & Nachträge</a>
                    {!data?.closed && <button onClick={closeMonth} className="rounded bg-gray-900 px-4 py-2 text-white">Monat abschließen</button>}
                </div>
            </div>
            {loading && <div className="rounded border bg-white p-6">Lade Dokumentation…</div>}
            {!loading && doc && <>
                <div className={`rounded border p-4 ${data?.closed?'bg-green-50':'bg-amber-50'}`}><b>{data?.closed?'Abgeschlossen':'Offener Monat'}</b>{data?.closed && <span className="ml-2 text-sm">Der gespeicherte Snapshot wird angezeigt. Spätere Korrekturen werden ausschließlich als Nachtrag protokolliert.</span>}</div>
                {corrections.length>0&&<div className="rounded-xl border border-blue-200 bg-blue-50 p-4"><b>{corrections.length} Korrektur{corrections.length===1?'':'en'} / Nachtrag</b><p className="mt-1 text-sm">{data?.closed?'Das archivierte Monats-PDF wurde dadurch nicht verändert.':'Die Originalnachweise bleiben unverändert; die Korrekturen sind im Audit-Protokoll dokumentiert.'}</p></div>}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{cards.map(([label,value])=><div key={label} className="rounded-xl border bg-white p-4"><div className="text-sm text-gray-500">{label}</div><div className="mt-1 text-2xl font-semibold">{value}</div></div>)}</div>
                {(stat.open_deviations>0 || stat.cleaning_missing>0 || stat.food_samples_missing>0) && <div className="rounded-xl border border-red-200 bg-red-50 p-4"><b>Monatsabschluss noch nicht möglich</b><div className="mt-2 text-sm">Offene Abweichungen: {stat.open_deviations||0} · fehlende Reinigungen: {stat.cleaning_missing||0} · offene Stichproben: {stat.food_samples_missing||0}</div></div>}
                <Section title="Abweichungen"><Table rows={doc.deviations} columns={['date','control','device','criticality','value','corrective_action','resolved_at']} /></Section>
                <Section title="Wareneingang"><Table rows={doc.goods_receipts} columns={['completed_at','supplier','item','temperature','visual_ok','olfactory_ok','supplier_docs_complete','completed_by']} /></Section>
                <Section title="Speisenzubereitung · Stichproben"><Table rows={doc.food_samples} columns={['scheduled_for','dish','core_temperature','sensory_ok','status','completed_by']} /></Section>
                <Section title="Rückstellproben"><Table rows={doc.retention_samples} columns={['sampled_at','product','batch','label','storage_location','dispose_on','recorded_by']} /></Section>
                <Section title="Reinigungsnachweise"><Table rows={doc.cleaning} columns={['due_date','plan','location','activity','cleaning_agent','disinfectant','completed_at','completed_by']} /></Section>
                <Section title="Fehlende Kontrollen"><Table rows={doc.missing_controls} columns={['due_at','title','device','category','criticality']} /></Section>
                {corrections.length>0&&<Section title="Korrektur- / Nachtragsprotokoll"><Table rows={corrections.map(r=>({...r,changes:Object.entries(r.field_changes||{}).map(([k,v])=>`${k}: ${format(v.before)} → ${format(v.after)}`).join(' · ')}))} columns={['corrected_at','source_type','source_id','reason','corrected_by_name','changes']} /></Section>}
            </>}
        </div>
    </TimeTrackingLayout>;
}

function Section({title,children}) { return <section className="rounded-xl border bg-white p-4"><h2 className="mb-3 text-lg font-semibold">{title}</h2>{children}</section>; }
function Table({rows=[],columns=[]}) { if(!rows?.length)return <p className="text-sm text-gray-500">Keine Einträge.</p>; return <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead><tr>{columns.map(c=><th key={c} className="border-b px-2 py-2 text-left">{label(c)}</th>)}</tr></thead><tbody>{rows.map((r,i)=><tr key={r.id??i}>{columns.map(c=><td key={c} className="border-b px-2 py-2 align-top">{format(r[c])}</td>)}</tr>)}</tbody></table></div>; }
function format(v){ if(v===true||v===1)return 'i.O.'; if(v===false||v===0)return 'nicht i.O.'; if(typeof v==='object'&&v!==null)return JSON.stringify(v);return v??'—'; }
function label(v){return ({date:'Datum',control:'Kontrolle',device:'Gerät',criticality:'Kritikalität',value:'Wert',corrective_action:'Maßnahme',resolved_at:'Erledigt',completed_at:'Zeitpunkt',supplier:'Lieferant',item:'Ware / Artikel',temperature:'Temperatur',visual_ok:'Sicht',olfactory_ok:'Geruch',supplier_docs_complete:'Dokumente',completed_by:'Mitarbeiter',scheduled_for:'Geplant',dish:'Speise / Artikel',core_temperature:'Kerntemperatur',sensory_ok:'Sensorik',status:'Status',sampled_at:'Probe',product:'Produkt',batch:'Charge',label:'Kennzeichnung',storage_location:'Lagerung',dispose_on:'Entsorgung',recorded_by:'Erfasst von',due_date:'Fällig',plan:'Reinigungsplan',location:'Bereich',activity:'Tätigkeit',cleaning_agent:'Reiniger',disinfectant:'Desinfektion',due_at:'Fällig',title:'Kontrolle',category:'Kategorie',corrected_at:'Korrigiert am',source_type:'Nachweisart',source_id:'ID',reason:'Begründung',corrected_by_name:'Korrigiert durch',changes:'Änderungen'})[v]||v; }
