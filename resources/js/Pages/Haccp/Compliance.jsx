import { useEffect, useState } from 'react';
import axios from 'axios';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import PhotoEvidenceInput,{uploadHaccpPhoto} from './PhotoEvidenceInput';

const Card=({children,className=''})=><div className={`rounded-xl border border-gray-200 bg-white p-5 shadow-sm ${className}`}>{children}</div>;
const Badge=({children,tone='gray'})=>{const c={red:'bg-red-100 text-red-800',amber:'bg-amber-100 text-amber-800',green:'bg-green-100 text-green-800',gray:'bg-gray-100 text-gray-700'};return <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${c[tone]}`}>{children}</span>};
const fmt=v=>v?new Date(v).toLocaleDateString('de-DE'): '—';

export default function Compliance(){
 const [data,setData]=useState({deviations:[],maintenance_plans:[],maintenance_records:[],devices:[]});
 const [loading,setLoading]=useState(true); const [error,setError]=useState('');
 const [action,setAction]=useState({}); const [maintenance,setMaintenance]=useState({}); const[photos,setPhotos]=useState({});
 const load=async()=>{setLoading(true);try{const {data}=await axios.get('/api/haccp/compliance');setData(data);setError('')}catch(e){setError(e.response?.data?.message||'Daten konnten nicht geladen werden.')}finally{setLoading(false)}};
 useEffect(()=>{load()},[]);
 const photo=(key)=>photos[key]||null,setPhoto=(key,file)=>setPhotos(p=>({...p,[key]:file}));
 const resolve=async d=>{const corrective_action=action[d.id]?.trim();if(!corrective_action)return;try{await axios.post(`/api/haccp/compliance/deviations/${d.id}/resolve`,{corrective_action});if(photo(`d-${d.id}`))await uploadHaccpPhoto('deviation',d.id,photo(`d-${d.id}`));setAction(a=>({...a,[d.id]:''}));setPhoto(`d-${d.id}`,null);await load()}catch(e){setError(e.response?.data?.message||'Abweichung konnte nicht abgeschlossen werden.')}};
 const complete=async p=>{const f=maintenance[p.id]||{};try{await axios.post(`/api/haccp/compliance/maintenance-plans/${p.id}/complete`,{completed_at:f.completed_at||new Date().toISOString().slice(0,10),provider:f.provider||null,notes:f.notes||null});if(photo(`m-${p.id}`))await uploadHaccpPhoto('maintenance_plan',p.id,photo(`m-${p.id}`));setMaintenance(m=>({...m,[p.id]:{}}));setPhoto(`m-${p.id}`,null);await load()}catch(e){setError(e.response?.data?.message||'Wartung konnte nicht dokumentiert werden.')}};
 const overdue=p=>p.next_due_at&&new Date(p.next_due_at+'T23:59:59')<new Date();
 return <TimeTrackingLayout title="HACCP – Abweichungen & Wartungen">
  <div className="space-y-6">
   <div><h1 className="text-2xl font-bold text-gray-900">Abweichungen & Wartungen</h1><p className="mt-1 text-sm text-gray-500">Korrekturmaßnahmen nachvollziehbar abschließen und Wartungen der HACCP-Geräte dokumentieren.</p></div>
   {error&&<div className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</div>}
   {loading?<Card>Lade HACCP-Daten …</Card>:<>
    <section className="space-y-3"><div className="flex items-center justify-between"><h2 className="text-lg font-semibold">Offene Abweichungen</h2><Badge tone={data.deviations.length?'red':'green'}>{data.deviations.length} offen</Badge></div>
     {data.deviations.length===0?<Card><span className="text-sm text-gray-500">Keine offenen Abweichungen.</span></Card>:data.deviations.map(d=><Card key={d.id} className={d.criticality==='critical'?'border-red-300':''}>
      <div className="flex flex-wrap items-start justify-between gap-3"><div><div className="flex items-center gap-2"><h3 className="font-semibold">{d.title||d.task_title||`Abweichung #${d.id}`}</h3><Badge tone={d.criticality==='critical'?'red':d.criticality==='elevated'?'amber':'gray'}>{d.criticality==='critical'?'CCP':d.criticality==='elevated'?'Erhöht':'Normal'}</Badge></div><div className="mt-1 text-sm text-gray-500">{d.device_name||d.source_label||'Allgemeine Kontrolle'} · {fmt(d.completed_at)} · {d.detected_by||'—'}</div><p className="mt-3 text-sm">{d.description}</p>{d.corrective_action&&<p className="mt-2 text-sm"><b>Bisherige Maßnahme:</b> {d.corrective_action}</p>}</div></div>
      <div className="mt-4 space-y-3"><textarea className="min-h-20 w-full rounded-lg border-gray-300 text-sm" placeholder="Durchgeführte Korrekturmaßnahme / Ergebnis der Nachkontrolle" value={action[d.id]||''} onChange={e=>setAction(a=>({...a,[d.id]:e.target.value}))}/><PhotoEvidenceInput compact file={photo(`d-${d.id}`)} onChange={f=>setPhoto(`d-${d.id}`,f)} label="Foto zur Korrektur / Nachkontrolle"/><div className="text-right"><button className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40" disabled={!action[d.id]?.trim()} onClick={()=>resolve(d)}>Abweichung abschließen</button></div></div>
     </Card>)}</section>

    <section className="space-y-3"><h2 className="text-lg font-semibold">Wartungspläne</h2>
     {data.maintenance_plans.length===0?<Card><span className="text-sm text-gray-500">Noch keine Wartungspläne angelegt.</span></Card>:data.maintenance_plans.map(p=><Card key={p.id} className={overdue(p)?'border-red-300':''}>
      <div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="font-semibold">{p.name}</h3><div className="mt-1 text-sm text-gray-500">{p.device_name} {p.location?`· ${p.location}`:''}</div></div><div className="flex gap-2"><Badge tone={overdue(p)?'red':'green'}>{overdue(p)?'Überfällig':`Fällig ${fmt(p.next_due_at)}`}</Badge></div></div>
      <div className="mt-3 grid gap-2 md:grid-cols-3"><input type="date" className="rounded-lg border-gray-300 text-sm" value={maintenance[p.id]?.completed_at||new Date().toISOString().slice(0,10)} onChange={e=>setMaintenance(m=>({...m,[p.id]:{...m[p.id],completed_at:e.target.value}}))}/><input className="rounded-lg border-gray-300 text-sm" placeholder="Dienstleister (optional)" value={maintenance[p.id]?.provider||''} onChange={e=>setMaintenance(m=>({...m,[p.id]:{...m[p.id],provider:e.target.value}}))}/><input className="rounded-lg border-gray-300 text-sm" placeholder="Notiz / Arbeiten" value={maintenance[p.id]?.notes||''} onChange={e=>setMaintenance(m=>({...m,[p.id]:{...m[p.id],notes:e.target.value}}))}/></div><div className="mt-3"><PhotoEvidenceInput compact file={photo(`m-${p.id}`)} onChange={f=>setPhoto(`m-${p.id}`,f)} label="Foto / Wartungsnachweis"/></div><div className="mt-3 text-right"><button className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white" onClick={()=>complete(p)}>Wartung dokumentieren</button></div>
     </Card>)}</section>

    <section className="space-y-3"><h2 className="text-lg font-semibold">Letzte Wartungsnachweise</h2><Card><div className="overflow-x-auto"><table className="min-w-full text-sm"><thead><tr className="border-b text-left text-gray-500"><th className="py-2 pr-4">Datum</th><th className="py-2 pr-4">Gerät</th><th className="py-2 pr-4">Wartung</th><th className="py-2 pr-4">Durchgeführt von</th><th className="py-2">Notiz</th></tr></thead><tbody>{data.maintenance_records.map(r=><tr key={r.id} className="border-b last:border-0"><td className="py-2 pr-4">{fmt(r.completed_at)}</td><td className="py-2 pr-4">{r.device_name}</td><td className="py-2 pr-4">{r.plan_name}</td><td className="py-2 pr-4">{r.provider||r.completed_by_name||'—'}</td><td className="py-2">{r.notes||'—'}</td></tr>)}</tbody></table>{!data.maintenance_records.length&&<div className="py-3 text-gray-500">Noch keine Wartungen dokumentiert.</div>}</div></Card></section>
   </>}
  </div>
 </TimeTrackingLayout>
}
