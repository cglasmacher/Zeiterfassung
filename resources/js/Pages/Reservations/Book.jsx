import React, { useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import { Plus, Users, Hourglass, RefreshCw, ChevronLeft, ChevronRight, Lock, X, Settings2, Armchair } from 'lucide-react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import Field from '@/Components/Reservations/Field';
import { api, statusLabels, kindLabels, activeStatuses, localParts, clock, shiftDay, durationFor, defaultForm, sameTables, tableState, tableLabel, errorText } from './reservationUi';
import { planColors, planTimeLayout } from './planReservations';
import './reservationBook.css';

const empty = {rooms:[],bookings:[],blocks:[],combinations:[],settings:{configuration:{}},can_manage:false};
export default function Book() {
    const [date,setDate]=useState(localParts().date), [data,setData]=useState(empty), [busy,setBusy]=useState(false), [error,setError]=useState(''), [notice,setNotice]=useState('');
    const [tab,setTab]=useState('all'), [search,setSearch]=useState(''), [modal,setModal]=useState(null), [blockOpen,setBlockOpen]=useState(false), [showPlan,setShowPlan]=useState(false);
    const sequence=useRef(0);
    const load=async()=>{const seq=++sequence.current;try {const {data:next}=await axios.get(`${api}/book`,{params:{date}});if(seq===sequence.current){setData(next);setError('');}}catch(e){if(seq===sequence.current)setError(errorText(e));}};
    useEffect(()=>{setBusy(true);load().finally(()=>setBusy(false));const timer=setInterval(()=>{if(!document.hidden)load();},30000);return()=>{sequence.current++;clearInterval(timer);};},[date]);
    const open=(kind,booking=null,promote=false)=>{setModal({kind,booking,promote});setNotice('');};
    const saveStatus=async(booking,status)=>{if(busy)return;setBusy(true);setError('');try {await axios.put(`${api}/bookings/${booking.id}`,{version:booking.version,status});await load();setNotice('Status gespeichert.');}catch(e){setError(errorText(e));}finally{setBusy(false);}};
    const waiting=data.bookings.filter(b=>b.status==='waiting');
    const active=data.bookings.filter(b=>activeStatuses.includes(b.status));
    const filtered=data.bookings.filter(b=>(tab==='all'||(tab==='waitlist'?b.status==='waiting':b.kind===tab))&&`${b.guest_name||''} ${b.phone||''} ${b.notes||''} ${b.resmio_notes||''} ${b.guest_comment||''} ${b.customer_notes||''} ${[...(b.resmio_tags||[]),...(b.customer_tags||[])].map(t=>t.name).join(' ')} ${b.tables.map(t=>t.name).join(' ')}`.toLowerCase().includes(search.toLowerCase()));
    return <TimeTrackingLayout><div className="rb-page">
        <header className="rb-header"><div><p className="rb-eyebrow">Hemingway · Service</p><h1>Reservierungsbuch</h1><p>Reservierungen, spontane Gäste und wartende Gruppen an einem Ort.</p></div><div className="rb-actions">{data.can_manage&&<button className="rb-btn" disabled={busy||!data.resmio?.enabled} onClick={async()=>{setBusy(true);setError('');try{const r=await axios.post(`${api}/resmio/sync`);await load();setNotice(`${r.data.count} Resmio-Buchungen abgeglichen.`);}catch(e){setError(errorText(e));}finally{setBusy(false);}}}><RefreshCw size={16}/>Resmio synchronisieren</button>}<button className="rb-btn" disabled={busy} onClick={()=>setBlockOpen(true)}><Lock size={16}/>Sperre</button><button className="rb-btn" disabled={busy} onClick={()=>open('waitlist')}><Hourglass size={16}/>Warteliste</button><button className="rb-btn" disabled={busy} onClick={()=>open('walkin')}><Users size={16}/>Walk-in</button><button className="rb-btn rb-primary" disabled={busy} onClick={()=>open('reservation')}><Plus size={17}/>Reservierung</button></div></header>
        {error&&<div className="rb-alert rb-error" role="alert">{error}<button onClick={load}>Neu laden</button></div>}{notice&&<div className="rb-alert" role="status">{notice}</div>}
        {data.resmio?.enabled&&<div className="rb-alert"><span>Resmio → Hemingway · {data.resmio.last_success_at?`Letzter Abgleich: ${localParts(data.resmio.last_success_at).date} ${clock(data.resmio.last_success_at)}`:'Noch kein erfolgreicher Abgleich.'} Eigene Buchungen und Serviceaktionen werden nicht an Resmio zurückgesendet.{data.resmio.last_error&&<strong className="rb-overdue"> {data.resmio.last_error}</strong>}</span></div>}
        {data.bookings.some(b=>b.resmio_unassigned||b.resmio_missing||(b.resmio_issues||[]).length>0)&&<div className="rb-alert rb-error">Resmio-Buchungen benötigen Prüfung. Bei fehlender Tischzuordnung bleiben neue lokale Buchungen für den betroffenen Zeitraum vorsorglich gesperrt. Die importierten Buchungen können direkt bearbeitet und zugeordnet werden.{data.can_manage&&<Link href={route('reservations.management')}>Tische zuordnen</Link>}</div>}
        <div className="rb-daybar"><div className="rb-actions"><button className="rb-icon" aria-label="Vorheriger Tag" onClick={()=>setDate(shiftDay(date,-1))}><ChevronLeft size={18}/></button><input aria-label="Reservierungstag" type="date" value={date} onChange={e=>{if(e.target.value)setDate(e.target.value);}}/><button className="rb-icon" aria-label="Nächster Tag" onClick={()=>setDate(shiftDay(date,1))}><ChevronRight size={18}/></button><button className="rb-btn" onClick={()=>setDate(localParts().date)}>Heute</button></div><div className="rb-actions"><button className={`rb-btn ${showPlan?'rb-selected':''}`} onClick={()=>setShowPlan(!showPlan)}><Armchair size={16}/>{showPlan?'Liste anzeigen':'Tischübersicht'}</button><button className="rb-icon" aria-label="Aktualisieren" onClick={load}><RefreshCw size={17}/></button>{data.can_manage&&<Link className="rb-btn" href={route('reservations.management')}><Settings2 size={16}/>Verwaltung</Link>}</div></div>
        <div className="rb-stats"><div><strong>{active.reduce((s,b)=>s+b.party_size,0)}</strong><span>Gäste eingeplant</span></div><div><strong>{data.bookings.filter(b=>b.status==='seated').reduce((s,b)=>s+b.party_size,0)}</strong><span>Gäste platziert</span></div><div><strong>{waiting.length}</strong><span>Gruppen auf Warteliste</span></div><div><strong>{data.blocks.length}</strong><span>Sperrungen am Tag</span></div></div>
        {data.blocks.length>0&&<details className="rb-card"><summary>Sperrungen anzeigen ({data.blocks.length})</summary>{data.blocks.map(b=><div key={b.id} className="rb-block"><div><strong>{b.reason}</strong><p>{localParts(b.starts_at).date} {clock(b.starts_at)} – {localParts(b.ends_at).date} {clock(b.ends_at)} · {b.table_ids?.length?`Tische: ${data.rooms.flatMap(r=>r.tables).filter(t=>b.table_ids.includes(t.id)).map(t=>t.name).join(', ')}`:data.rooms.find(r=>r.id===b.room_id)?.name||'Gesamtes Restaurant'}</p></div><button className="rb-btn" disabled={busy} onClick={async()=>{if(!window.confirm('Sperre aufheben?'))return;setBusy(true);try{await axios.delete(`${api}/blocks/${b.id}`);await load();}catch(e){setError(errorText(e));}finally{setBusy(false);}}}>Aufheben</button></div>)}</details>}
        {showPlan?<ServicePlan data={data} date={date} open={b=>open(b.kind,b)}/>:<>
        <div className="rb-filterbar"><nav aria-label="Buchungsfilter">{[['all','Alle'],['reservation','Reservierungen'],['walkin','Walk-ins'],['waitlist',`Warteliste (${waiting.length})`]].map(([id,label])=><button key={id} className={tab===id?'active':''} onClick={()=>setTab(id)}>{label}</button>)}</nav><input aria-label="Buchungen suchen" placeholder="Name, Telefon, Tisch …" value={search} onChange={e=>setSearch(e.target.value)}/></div>
        {!data.rooms.length&&<div className="rb-alert">Zuerst Räume und Tische im Builder anlegen. Wartelisteneinträge sind bereits möglich.</div>}
        <div className="rb-bookings">{filtered.map(b=>{const overdue=b.status==='seated'&&new Date(b.ends_at)<new Date();return <article key={b.id} className={`rb-booking ${['cancelled','finished','noshow'].includes(b.status)?'rb-inactive':''}`}>
            <button className="rb-booking-main" onClick={()=>open(b.kind,b)}><div className="rb-booking-time"><strong>{clock(b.starts_at)}</strong><small>bis {clock(b.ends_at)}</small>{localParts(b.starts_at).date!==date&&<small>{localParts(b.starts_at).date}</small>}</div><div><div className="rb-name">{b.guest_name||'Spontane Gäste'} <span className="rb-kind">{kindLabels[b.kind]}</span></div><p>{b.party_size} Personen · {b.tables.length?tableLabel(b.tables):b.status==='waiting'?'Wartet auf Platz':'Kein Tisch'}</p>{b.phone&&<p>{b.phone}</p>}{b.source==='resmio'&&<ImportedInfo booking={b} compact/>}{b.notes&&<p className="rb-notes">{b.notes}</p>}{overdue&&<p className="rb-overdue">Geplante Endzeit überschritten – Aufenthaltsdauer bei Bedarf anpassen.</p>}</div></button>
            <div className="rb-booking-side"><span className={`rb-status rb-status-${b.status}`}>{statusLabels[b.status]}</span><div className="rb-actions">{b.status==='waiting'?(b.source==='resmio'?<span className="rb-hint">In Resmio bestätigen</span>:<button className="rb-btn rb-primary" disabled={busy} onClick={()=>open('reservation',b,true)}>Übernehmen</button>):<>{['confirmed','unconfirmed'].includes(b.status)&&<button className="rb-btn" disabled={busy} onClick={()=>saveStatus(b,'arrived')}>Angekommen</button>}{['confirmed','unconfirmed','arrived'].includes(b.status)&&<button className="rb-btn" disabled={busy} onClick={()=>saveStatus(b,'seated')}>Platzieren</button>}{b.status==='seated'&&<button className="rb-btn" disabled={busy} onClick={()=>saveStatus(b,'finished')}>Tisch freigeben</button>}</>}<button className="rb-btn" onClick={()=>open(b.kind,b)}>Bearbeiten</button></div></div>
        </article>;})}{!filtered.length&&<div className="rb-empty">{busy?'Buchungen werden geladen …':'Keine Einträge für diese Auswahl.'}</div>}</div></>}
        <p className="rb-footnote">Automatische Aktualisierung alle 30 Sekunden · Resmio-Buchungen werden einseitig übernommen. Öffentliche Online-Buchungen und Gästemails folgen im nächsten Ausbau.</p>
        {modal?.booking?.source==='resmio'?<ImportedBookingDialog booking={modal.booking} data={data} close={()=>setModal(null)} done={async()=>{setModal(null);await load();setNotice('Lokale Ergänzungen gespeichert.');}}/>:modal&&<BookingDialog modal={modal} date={date} data={data} close={()=>setModal(null)} done={async(saved)=>{setModal(null);const next=localParts(saved.starts_at).date;if(next!==date)setDate(next);else await load();setNotice('Buchung gespeichert.');}}/>}
        {blockOpen&&<BlockDialog date={date} data={data} close={()=>setBlockOpen(false)} done={async()=>{setBlockOpen(false);await load();setNotice('Sperre angelegt.');}}/>}
    </div></TimeTrackingLayout>;
}

export function Dialog({title,close,children,busy=false}) {
    const ref=useRef(null);
    useEffect(()=>{const el=ref.current;const previous=document.activeElement;el.showModal();return()=>{el.close();previous?.focus?.();};},[]);
    return <dialog ref={ref} className="rb-dialog" onCancel={e=>{e.preventDefault();if(!busy)close();}}><header><h2>{title}</h2><button type="button" className="rb-icon" aria-label="Schließen" disabled={busy} onClick={close}><X size={20}/></button></header>{children}</dialog>;
}

function ImportedInfo({booking:b,compact=false}) {
    return <div className="rb-imported-info">
        {[[b.resmio_tags,'Reservierung'],[b.customer_tags,'Gast']].map(([tags,type])=><div className="rb-tags" key={type}>{(tags||[]).map(t=><span key={t.id} title={`${type}-Tag`}>{type==='Gast'?'Gast: ':''}{t.name}</span>)}</div>)}
        {[[b.resmio_notes,'Resmio-Notiz'],[b.guest_comment,'Gastkommentar'],[b.customer_notes,'Gästenotiz']].map(([text,label])=>text&&<div key={label} className="rb-import-note"><strong>{label}</strong><p>{text}</p></div>)}
        {b.resmio_missing&&<p className="rb-overdue">Im letzten Abgleich nicht enthalten. Die letzte bekannte Belegung bleibt bestehen; bitte in Resmio prüfen.</p>}
        {(b.resmio_issues||[]).map(issue=><p className="rb-overdue" key={issue}>{issue}</p>)}
        {b.resmio_local_status&&<p className="rb-hint">Lokaler Servicestatus: {statusLabels[b.resmio_local_status]} · Resmio: {b.resmio_status}</p>}
        {!compact&&<><p className="rb-hint">Resmio-Tische: {(b.resmio_tables||[]).map(t=>t.name).join(', ')||'Noch nicht zugeordnet'}</p>
        {Object.keys(b.resmio_data?.booking_request_parameters||{}).length>0&&<details><summary>Zusätzliche Reservierungsangaben</summary><pre>{JSON.stringify(b.resmio_data.booking_request_parameters,null,2)}</pre></details>}</>}
    </div>;
}

function ImportedBookingDialog({booking,data,close,done}) {
    const [notes,setNotes]=useState(booking.notes||''),[status,setStatus]=useState(''),[error,setError]=useState(''),[busy,setBusy]=useState(false);
    const [options,setOptions]=useState([]),[optionBusy,setOptionBusy]=useState(true),[optionError,setOptionError]=useState(''),[tableIds,setTableIds]=useState(booking.tables.map(t=>t.id));
    const tableChanged=!sameTables(tableIds,booking.tables.map(t=>t.id));
    const canAssign=activeStatuses.includes(booking.status)&&booking.kind!=='waitlist';
    useEffect(()=>{if(!canAssign){setOptionBusy(false);return;}const controller=new AbortController();axios.get(`${api}/bookings/${booking.id}/table-options`,{signal:controller.signal}).then(r=>setOptions(r.data.options)).catch(e=>{if(!axios.isCancel(e))setOptionError(errorText(e));}).finally(()=>setOptionBusy(false));return()=>controller.abort();},[booking.id]);
    const selected=options.find(o=>sameTables(o.table_ids,tableIds))?.id||'__unavailable';
    const dismiss=()=>{if((notes=== (booking.notes||'')&&!status&&!tableChanged)||window.confirm('Lokale Änderungen verwerfen?'))close();};
    const save=async(reset=false,resetTables=false)=>{setBusy(true);setError('');try{await axios.put(`${api}/bookings/${booking.id}`,{version:booking.version,notes,...(reset?{reset_resmio_status:true}:status?{status}:{}),...(resetTables?{reset_resmio_tables:true}:tableChanged?{table_ids:tableIds}:{})});await done();}catch(e){setError(errorText(e));}finally{setBusy(false);}};
    return <Dialog title={`Reservierung bearbeiten · ${booking.guest_name||'Gäste'}`} close={dismiss} busy={busy}><form className="rb-form" onSubmit={e=>{e.preventDefault();save();}}>
        {error&&<div className="rb-alert rb-error" role="alert">{error}</div>}
        <p className="rb-hint">Datum, Gästezahl, Kontakte und Resmio-Notizen werden in Resmio gepflegt. Tischwahl, eigene Notizen und Serviceaktionen können lokal geändert werden und bleiben beim Abgleich erhalten. Tischänderungen gelten nur in Hemingway.</p>
        <div className="rb-card"><strong>{localParts(booking.starts_at).date} · {clock(booking.starts_at)}–{clock(booking.ends_at)} · {booking.party_size} Personen</strong><p>{tableLabel(booking.tables)}</p><p>{booking.phone} {booking.email}</p><p>{statusLabels[booking.status]} · Quelle: {booking.resmio_data?.source||'Resmio'}{booking.resmio_data?.ref_num?` · Referenz ${booking.resmio_data.ref_num}`:''}</p><ImportedInfo booking={booking}/></div>
        <fieldset disabled={busy}>
        {canAssign&&<><Field label="Tisch / erlaubte Kombination"><select disabled={optionBusy||!!optionError} value={selected} onChange={e=>setTableIds(options.find(o=>o.id===e.target.value)?.table_ids||tableIds)}>
            <option value="__unavailable" disabled>{optionBusy?'Verfügbarkeit wird geprüft …':`Aktuell: ${tableLabel(booking.tables)} – nicht verfügbar`}</option>
            {options.map(o=><option key={o.id} value={o.id}>{tableLabel(data.rooms.flatMap(r=>r.tables).filter(t=>o.table_ids.includes(t.id)))} ({o.capacity} Plätze)</option>)}
        </select></Field><p className={optionError?'rb-overdue':'rb-hint'}>{optionError||`${options.length} passende Tischoptionen für ${clock(booking.starts_at)}–${clock(booking.ends_at)}. Beim Speichern wird erneut geprüft.`}</p>
        {booking.resmio_local_table_ids!==null&&booking.resmio_local_table_ids!==undefined&&<p className="rb-hint">Lokale Tischzuordnung aktiv. <button type="button" className="rb-btn" onClick={()=>save(false,true)}>Wieder Resmio-Tische übernehmen</button></p>}</>}
        <Field label="Eigene interne Notizen"><textarea maxLength={2000} rows={4} value={notes} onChange={e=>setNotes(e.target.value)}/></Field>
        {activeStatuses.includes(booking.status)&&booking.kind!=='waitlist'&&<Field label="Lokale Serviceaktion"><select value={status} onChange={e=>setStatus(e.target.value)}><option value="">Status beibehalten</option><option value="arrived">Angekommen</option><option value="seated">Platzieren</option><option value="finished">Tisch freigeben</option></select></Field>}
        {booking.resmio_local_status&&<button type="button" className="rb-btn" onClick={()=>save(true)}>Wieder Resmio-Status übernehmen</button>}
        </fieldset><footer><button type="button" className="rb-btn" disabled={busy} onClick={dismiss}>Schließen</button><button className="rb-btn rb-primary" disabled={busy}>Lokale Ergänzungen speichern</button></footer>
    </form></Dialog>;
}

function BookingDialog({modal,date,data,close,done}) {
    const [form,setForm]=useState(()=>{const f=defaultForm(modal.kind,date,data.settings.configuration,modal.booking);if(modal.promote){f.kind='reservation';f.status='confirmed';f.table_ids=[];}return f;});
    const [options,setOptions]=useState([]),[optionBusy,setOptionBusy]=useState(false),[optionError,setOptionError]=useState(''),[error,setError]=useState(''),[busy,setBusy]=useState(false),[events,setEvents]=useState([]);
    const [customDuration,setCustomDuration]=useState(!!modal.booking);
    const dirty=useRef(false);const set=(key,value)=>{dirty.current=true;setForm(f=>({...f,[key]:value,...(['date','time','party_size','duration_minutes','room_id','kind'].includes(key)?{table_ids:[]}:{}),...(key==='party_size'&&!customDuration?{duration_minutes:durationFor(value,data.settings.configuration)}:{})}));};
    const dismiss=()=>{if(!dirty.current||window.confirm('Änderungen an der Buchung verwerfen?'))close();};
    useEffect(()=>{if(!modal.booking)return;axios.get(`${api}/bookings/${modal.booking.id}/events`).then(r=>setEvents(r.data)).catch(()=>{});},[]);
    useEffect(()=>{
        if(form.kind==='waitlist')return;
        const controller=new AbortController();setOptionBusy(true);setOptionError('');
        const timer=setTimeout(()=>axios.get(`${api}/availability`,{signal:controller.signal,params:{date:form.date,time:form.time,party_size:form.party_size,duration_minutes:form.duration_minutes,room_id:form.room_id||undefined,except:modal.booking?.id,kind:form.kind}}).then(r=>{setOptions(r.data.options);setOptionBusy(false);}).catch(e=>{if(!axios.isCancel(e)){setOptions([]);setOptionError(errorText(e));setOptionBusy(false);}}),200);
        return()=>{clearTimeout(timer);controller.abort();};
    },[form.kind,form.date,form.time,form.party_size,form.duration_minutes,form.room_id]);
    const save=async e=>{e.preventDefault();setBusy(true);setError('');try {const payload={...form,room_id:form.room_id||null};if(!modal.booking)delete payload.version;
        let result; if(modal.promote)result=await axios.post(`${api}/bookings/${modal.booking.id}/promote`,payload);else if(modal.booking)result=await axios.put(`${api}/bookings/${modal.booking.id}`,payload);else result=await axios.post(`${api}/bookings`,payload);await done(result.data);
    }catch(e){setError(errorText(e));}finally{setBusy(false);}};
    const selected=options.find(o=>sameTables(o.table_ids,form.table_ids))?.id||(form.table_ids.length?'__unavailable':'');
    return <Dialog title={modal.promote?'Warteliste übernehmen':`${kindLabels[form.kind]} ${modal.booking?'bearbeiten':'anlegen'}`} close={dismiss} busy={busy}><form onSubmit={save} className="rb-form">
        {error&&<div className="rb-alert rb-error" role="alert">{error}</div>}
        <fieldset disabled={busy}><div className="rb-grid">
        {modal.promote&&<Field label="Übernahme"><select value={form.kind} onChange={e=>set('kind',e.target.value)}><option value="reservation">Reservierung für Wunschzeit</option><option value="walkin">Jetzt platzieren (Walk-in)</option></select></Field>}
        {form.kind!=='walkin'?<><Field label="Datum"><input required type="date" value={form.date} onChange={e=>set('date',e.target.value)}/></Field><Field label="Beginn"><input required type="time" value={form.time} onChange={e=>set('time',e.target.value)}/></Field></>:<p className="rb-span">{modal.booking&&!modal.promote?'Beginn: '+localParts(modal.booking.starts_at).date+' '+clock(modal.booking.starts_at):'Walk-ins werden mit der aktuellen Uhrzeit angelegt.'}</p>}
        <Field label="Personen"><input required type="number" min={1} max={100} value={form.party_size} onChange={e=>set('party_size',Number(e.target.value))}/></Field><Field label="Aufenthalt (Minuten)"><input required type="number" min={15} max={720} value={form.duration_minutes} onChange={e=>{setCustomDuration(true);set('duration_minutes',Number(e.target.value));}}/></Field>
        <Field label="Name / Gruppe"><input required={form.kind==='waitlist'||(form.kind==='reservation'&&data.settings.configuration.require_name)} maxLength={120} value={form.guest_name} onChange={e=>set('guest_name',e.target.value)}/></Field><Field label="Telefon"><input required={form.kind==='reservation'&&data.settings.configuration.require_phone} maxLength={60} type="tel" value={form.phone} onChange={e=>set('phone',e.target.value)}/></Field>
        <Field label="E-Mail (optional)"><input type="email" maxLength={200} value={form.email} onChange={e=>set('email',e.target.value)}/></Field><Field label="Quelle"><select value={form.source} onChange={e=>set('source',e.target.value)}>{[['phone','Telefon'],['email','E-Mail'],['manual','Vor Ort'],['walkin','Walk-in']].map(([v,l])=><option key={v} value={v}>{l}</option>)}</select></Field>
        {form.kind!=='waitlist'&&<><Field label="Raum"><select value={form.room_id} onChange={e=>set('room_id',e.target.value)}><option value="">Beliebiger Raum</option>{data.rooms.map(r=><option key={r.id} value={r.id}>{r.name}</option>)}</select></Field><Field label="Tisch / erlaubte Kombination"><select disabled={optionBusy} value={selected} onChange={e=>{dirty.current=true;setForm(f=>({...f,table_ids:options.find(o=>o.id===e.target.value)?.table_ids||[]}));}}><option value="">{optionBusy?'Verfügbarkeit wird geprüft …':'Automatisch passenden Tisch wählen'}</option>{selected==='__unavailable'&&<option value="__unavailable" disabled>Aktuelle Tischauswahl nicht verfügbar</option>}{options.map(o=><option key={o.id} value={o.id}>{data.rooms.find(r=>r.id===o.room_id)?.name} · {o.name} ({o.capacity} Plätze)</option>)}</select></Field>
        <p className={`rb-span ${optionError||!options.length?'rb-overdue':'rb-hint'}`}>{optionError||(!optionBusy&&!options.length?'Kein passender freier Tisch für diesen Zeitraum. Wunschzeit ändern oder zunächst auf der Warteliste erfassen.':`${options.length} passende Tischoptionen. Beim Speichern wird erneut verbindlich geprüft.`)}</p></>}
        {form.kind==='waitlist'&&<p className="rb-hint rb-span">Dieser Eintrag hält keine Plätze frei. Erst beim Übernehmen werden Zeit und Tisch verbindlich geprüft.</p>}
        {modal.booking&&!modal.promote&&<Field label="Status"><select value={form.status} onChange={e=>set('status',e.target.value)}>{Object.entries(statusLabels).filter(([v])=>form.kind==='waitlist'?['waiting','cancelled'].includes(v):v!=='waiting').map(([v,l])=><option key={v} value={v}>{l}</option>)}</select></Field>}
        <div className="rb-span"><Field label="Interne Notizen"><textarea rows={3} maxLength={2000} value={form.notes} onChange={e=>set('notes',e.target.value)}/></Field></div>
        </div></fieldset>
        {events.length>0&&<details className="rb-hint"><summary>Letzte Änderungen</summary>{events.map(e=><p key={e.id}>{new Date(e.created_at).toLocaleString('de-DE',{timeZone:'Europe/Berlin'})} · {e.user_name||'Team'} · {({created:'Angelegt',updated:'Bearbeitet',promoted:'Aus Warteliste übernommen',resmio_sync:'Resmio-Abgleich',resmio_deleted:'In Resmio gelöscht'})[e.action]}</p>)}</details>}
        <footer><button type="button" className="rb-btn" disabled={busy} onClick={dismiss}>Abbrechen</button><button className="rb-btn rb-primary" disabled={busy}>{busy?'Speichert …':'Speichern'}</button></footer>
    </form></Dialog>;
}

function BlockDialog({date,data,close,done}) {
    const [form,setForm]=useState({reason:'',start_date:date,start_time:'10:00',end_date:date,end_time:'23:59',room_id:'',table_ids:[]}),[busy,setBusy]=useState(false),[error,setError]=useState('');
    const set=(key,value)=>setForm(f=>({...f,[key]:value,...(key==='room_id'?{table_ids:[]}:{} )}));
    return <Dialog title="Tische oder Räume sperren" close={close} busy={busy}><form className="rb-form" onSubmit={async e=>{e.preventDefault();setBusy(true);setError('');try{await axios.post(`${api}/blocks`,{...form,room_id:form.room_id||null});await done();}catch(e){setError(errorText(e));}finally{setBusy(false);}}}>
        {error&&<div className="rb-alert rb-error" role="alert">{error}</div>}<fieldset disabled={busy}><Field label="Grund"><input required maxLength={200} value={form.reason} onChange={e=>set('reason',e.target.value)}/></Field><div className="rb-grid">{[['start_date','Von Datum','date'],['start_time','Von Uhrzeit','time'],['end_date','Bis Datum','date'],['end_time','Bis Uhrzeit','time']].map(([k,l,t])=><Field key={k} label={l}><input required type={t} value={form[k]} onChange={e=>set(k,e.target.value)}/></Field>)}</div>
        <Field label="Bereich"><select value={form.room_id} onChange={e=>set('room_id',e.target.value)}><option value="">Gesamtes Restaurant</option>{data.rooms.map(r=><option key={r.id} value={r.id}>{r.name}</option>)}</select></Field>
        {form.room_id&&<div className="rb-checks">{data.rooms.find(r=>r.id===form.room_id)?.tables.map(t=><label key={t.id}><input type="checkbox" checked={form.table_ids.includes(t.id)} onChange={e=>set('table_ids',e.target.checked?[...form.table_ids,t.id]:form.table_ids.filter(id=>id!==t.id))}/>Tisch {t.name}</label>)}</div>}
        <p className="rb-hint">Ohne einzelne Tischauswahl wird der gesamte Bereich gesperrt. Betroffene Buchungen müssen vorher umgesetzt oder storniert werden.</p></fieldset><footer><button type="button" className="rb-btn" disabled={busy} onClick={close}>Abbrechen</button><button className="rb-btn rb-primary" disabled={busy}>Sperre anlegen</button></footer>
    </form></Dialog>;
}

function ServicePlan({data,date,open}) {
    const [roomId,setRoomId]=useState(''),[time,setTime]=useState(localParts().time);const room=data.rooms.find(r=>r.id===roomId)||data.rooms[0];
    const layout=room?planTimeLayout(room,data.bookings,date):null;
    const activate=(e,booking)=>{if(['Enter',' '].includes(e.key)){e.preventDefault();open(booking);}};
    return <section className="rb-card"><div className="rb-actions"><Field label="Raum"><select value={room?.id||''} onChange={e=>setRoomId(e.target.value)}>{data.rooms.map(r=><option key={r.id} value={r.id}>{r.name}</option>)}</select></Field><Field label="Zeitpunkt"><input type="time" value={time} onChange={e=>setTime(e.target.value)}/></Field><button className="rb-btn" onClick={()=>setTime(localParts().time)}>Jetzt</button></div>
        <div className="rb-plan-legend">{[['free','Frei'],['unconfirmed','Unbestätigt'],['confirmed','Reserviert'],['arrived','Angekommen'],['seated','Platziert'],['finished','Abgeschlossen'],['blocked','Gesperrt']].map(([key,label])=><span key={key}><i style={{background:planColors[key].fill,borderColor:planColors[key].stroke}}/>{label}</span>)}</div>
        <p className="rb-hint">Tischfarben gelten für den gewählten Zeitpunkt. Daneben stehen alle Reservierungszeiten des Tages, farbig nach Status. Uhrzeit anklicken zum Bearbeiten.</p>
        {room?<div className="rb-plan-scroll"><svg role="group" aria-label="Tischbelegung" viewBox={`0 0 ${layout.width} ${layout.height}`} style={{minWidth:layout.width}} className="rb-plan">
            {(room.elements||[]).map(o=><g key={o.id} transform={`translate(${o.x} ${o.y}) rotate(${o.rotation})`}><rect x={-o.width/2} y={-o.height/2} width={o.width} height={o.height} rx={4} fill={o.type==='text'?'transparent':'#deded3'}/>{o.type!=='wall'&&<text textAnchor="middle" dominantBaseline="central" fontSize={14}>{o.label}</text>}</g>)}
            {layout.labels.map(l=><line key={l.table.id} x1={l.table.x} y1={l.table.y} x2={l.x>l.table.x?l.x:l.x+l.width} y2={Math.max(l.y+8,Math.min(l.table.y,l.y+l.height-8))} stroke="#aebaa2" strokeWidth={1} pointerEvents="none"/>)}
            {room.tables.map(t=>{const {booking,block}=tableState(t,data.bookings,data.blocks,date,time);const color=booking?planColors[booking.status]:block?planColors.blocked:planColors.free;return <g key={t.id} data-table-id={t.id} data-state={booking?.status||(block?'blocked':'free')} role={booking?'button':undefined} tabIndex={booking?0:undefined} aria-label={`Tisch ${t.name}: ${booking?statusLabels[booking.status]:block?'gesperrt':'frei'}`} onClick={()=>booking&&open(booking)} onKeyDown={e=>booking&&activate(e,booking)} style={{cursor:booking?'pointer':'default'}} transform={`translate(${t.x} ${t.y}) rotate(${t.rotation})`}>
                {t.shape==='rectangle'?<rect x={-t.width/2} y={-t.height/2} width={t.width} height={t.height} rx={6} fill={color.fill} stroke={color.stroke} strokeWidth={2}/>:<ellipse rx={t.shape==='round'?Math.min(t.width,t.height)/2:t.width/2} ry={t.shape==='round'?Math.min(t.width,t.height)/2:t.height/2} fill={color.fill} stroke={color.stroke} strokeWidth={2}/>}
                <g fill={color.text} transform={`rotate(${-t.rotation})`}><text textAnchor="middle" y={-3} fontSize={17} fontWeight={650}>{t.name}</text><text textAnchor="middle" y={16} fontSize={10}>{booking?`${booking.party_size} ${booking.tables.length>1?'ges.':'Gäste'}`:block?'Gesperrt':`${t.capacity} Plätze`}</text></g><title>{booking?`${statusLabels[booking.status]} · ${clock(booking.starts_at)}–${clock(booking.ends_at)}`:block?.reason||'Frei'}</title>
            </g>;})}
            {layout.labels.map(l=><g key={l.table.id} data-time-table={l.table.id} transform={`translate(${l.x} ${l.y})`}><rect width={l.width} height={l.height} rx={7} fill="#fff" stroke="#dce3d5"/>
                {l.reservations.map((b,i)=>{const color=planColors[b.status],current=tableState(l.table,[b],[],date,time).booking;return <g key={b.id} role="button" tabIndex={0} aria-label={`Tisch ${l.table.name}, ${clock(b.starts_at)} bis ${clock(b.ends_at)}, ${statusLabels[b.status]}`} onClick={()=>open(b)} onKeyDown={e=>activate(e,b)} className="rb-plan-time" transform={`translate(4 ${4+i*22})`}>
                    <rect width={l.width-8} height={20} rx={4} fill={color.fill} stroke={color.stroke} strokeWidth={current?2:0.8}/><text x={(l.width-8)/2} y={10} textAnchor="middle" dominantBaseline="central" fill={color.text} fontSize={12} fontWeight={current?700:500}>{clock(b.starts_at)}–{clock(b.ends_at)}</text><title>{statusLabels[b.status]}</title>
                </g>;})}
            </g>)}
        </svg></div>:<p>Noch keine Räume angelegt.</p>}
    </section>;
}
