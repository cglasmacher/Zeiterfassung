import React, {useEffect,useState} from 'react';
import axios from 'axios';
import {api,errorText} from '@/Pages/Reservations/reservationUi';
export default function TagManagement(){
    const [tags,setTags]=useState([]),[name,setName]=useState(''),[busy,setBusy]=useState(false),[error,setError]=useState('');
    const load=()=>axios.get(`${api}/tags`).then(r=>setTags(r.data));
    useEffect(()=>{load().catch(e=>setError(errorText(e)));},[]);
    const save=async(tag,values)=>{setBusy(true);setError('');try{if(tag)await axios.put(`${api}/tags/${tag.id}`,values);else {await axios.post(`${api}/tags`,values);setName('');}await load();}catch(e){setError(errorText(e));}finally{setBusy(false);}};
    return <section className="rb-card"><h2>Reservierungs-Tags</h2><p className="rb-hint">Standard-Tags und eigene Kennzeichnungen. Archivierte Tags bleiben an vorhandenen Reservierungen und im Zeitraumfilter erhalten. Änderungen hier betreffen keine importierten Resmio-Tags.</p>{error&&<div className="rb-alert rb-error" role="alert">{error}</div>}
    <form className="rb-actions" onSubmit={e=>{e.preventDefault();save(null,{name});}}><input aria-label="Neuer Tag" placeholder="Eigener Tag …" required maxLength={80} value={name} onChange={e=>setName(e.target.value)}/><button className="rb-btn" disabled={busy}>Tag hinzufügen</button></form>
    <div className="rb-tag-management">{tags.map(t=><div key={t.id}><span>{t.name}</span><button type="button" className="rb-btn" disabled={busy} onClick={()=>{const name=window.prompt('Tag umbenennen',t.name);if(name?.trim())save(t,{name,active:t.active});}}>Umbenennen</button><button type="button" className="rb-btn" disabled={busy} onClick={()=>save(t,{name:t.name,active:!t.active})}>{t.active?'Archivieren':'Aktivieren'}</button></div>)}</div></section>;
}
