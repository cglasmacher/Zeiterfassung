import React, {useEffect,useState} from 'react';
import axios from 'axios';
import {api,errorText,statusLabels,reservationStatuses} from '@/Pages/Reservations/reservationUi';
import ReservationList from './ReservationList';
import Field from './Field';
export default function ReservationSearch({date,tags,refresh,open,onStatus,onPromote,busy}) {
    const [draft,setDraft]=useState({from:date,to:date,tag:'',status:''}),[query,setQuery]=useState(null),[page,setPage]=useState(1),[result,setResult]=useState(null),[error,setError]=useState(''),[loading,setLoading]=useState(false);
    useEffect(()=>{if(!query)return;const controller=new AbortController();setLoading(true);setError('');axios.get(`${api}/search`,{params:{...query,page},signal:controller.signal}).then(r=>setResult(r.data)).catch(e=>{if(!axios.isCancel(e)){setError(errorText(e));setResult(null);}}).finally(()=>{if(!controller.signal.aborted)setLoading(false);});return()=>controller.abort();},[query,page,refresh]);
    const set=(key,value)=>setDraft(f=>({...f,[key]:value}));
    return <section className="rb-range-search"><form className="rb-grid" onSubmit={e=>{e.preventDefault();setPage(1);setResult(null);setQuery({...draft});}}>
    <Field label="Von"><input required type="date" value={draft.from} onChange={e=>set('from',e.target.value)}/></Field><Field label="Bis (einschließlich)"><input required type="date" min={draft.from} value={draft.to} onChange={e=>set('to',e.target.value)}/></Field>
    <Field label="Tag"><input list="reservation-search-tags" placeholder="Alle Tags" maxLength={80} value={draft.tag} onChange={e=>set('tag',e.target.value)}/><datalist id="reservation-search-tags">{tags.map(t=><option key={t.id} value={t.name}/>)}</datalist></Field>
    <Field label="Status"><select value={draft.status} onChange={e=>set('status',e.target.value)}><option value="">Alle Status</option>{[...reservationStatuses,'waiting'].map(s=><option key={s} value={s}>{statusLabels[s]}</option>)}</select></Field><button className="rb-btn rb-primary" disabled={loading}>Zeitraum durchsuchen</button></form>
    <p className="rb-hint">Zeitraum bezieht sich auf den Reservierungstag. Tags werden auch in importierten Reservierungs- und Gast-Tags gesucht. Ohne Statusfilter sind Stornos und No-Shows enthalten.</p>
    {error&&<div className="rb-alert rb-error" role="alert">{error}</div>}{loading&&<p role="status">Suche läuft …</p>}
    {result&&<><p role="status"><strong>{result.total} Reservierungen</strong> · {query.from} bis {query.to}{query.tag?` · ${query.tag}`:''}</p><ReservationList sortable={false} bookings={result.data} date="Zeitraum" busy={busy||loading} open={open} onStatus={onStatus} onPromote={onPromote}/><div className="rb-actions"><button className="rb-btn" disabled={loading||page<=1} onClick={()=>setPage(p=>p-1)}>Zurück</button><span>Seite {result.current_page} von {result.last_page}</span><button className="rb-btn" disabled={loading||page>=result.last_page} onClick={()=>setPage(p=>p+1)}>Weiter</button></div></>}
    </section>;
}
