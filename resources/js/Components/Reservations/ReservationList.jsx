import React, { useState } from 'react';
import { Globe, Phone, Mail, Users, Hourglass, CalendarDays, Pencil, ChevronDown, ChevronUp, AlertCircle } from 'lucide-react';
import { clock, localParts, statusLabels, tableLabel, canonicalStatus, editableStatuses } from '@/Pages/Reservations/reservationUi';

const sourceInfo=b=>b.kind==='waitlist'?[Hourglass,'Warteliste']:b.kind==='walkin'?[Users,'Walk-in']:b.source==='widget'?[Globe,'Online-Reservierung']:b.source==='resmio'?[Globe,'Resmio']:b.source==='phone'?[Phone,'Telefon']:b.source==='email'?[Mail,'E-Mail']:[CalendarDays,'Vor Ort'];
const notesFor=b=>[[b.notes,'Interne Notiz'],[b.resmio_notes,'Resmio-Notiz'],[b.guest_comment,'Gastkommentar'],[b.customer_notes,'Gästenotiz']].filter(([value])=>value);

export default function ReservationList({bookings,date,busy,open,onStatus,onPromote}) {
    const [expanded,setExpanded]=useState(null),[descending,setDescending]=useState(false);
    const sorted=[...bookings].sort((a,b)=>(new Date(a.starts_at)-new Date(b.starts_at)||a.id.localeCompare(b.id))*(descending?-1:1));
    return <div className="rb-list-wrap"><table className="rb-reservation-list"><caption className="sr-only">Reservierungen am {date}</caption><thead><tr>
        <th scope="col">Typ</th><th scope="col" aria-sort={descending?'descending':'ascending'}><button type="button" onClick={()=>setDescending(!descending)}>Zeit {descending?<ChevronUp size={14}/>:<ChevronDown size={14}/>}</button></th><th scope="col">Gast</th><th scope="col" className="rb-col-count">Pers.</th><th scope="col">Tische</th><th scope="col">Notizen</th><th scope="col">Status</th><th scope="col"><span className="sr-only">Bearbeiten</span></th>
    </tr></thead><tbody>{sorted.map(b=>{
        const [Icon,source]=sourceInfo(b),notes=notesFor(b),tags=[...(b.resmio_tags||[]).map(t=>({...t,kind:'Reservierung'})),...(b.customer_tags||[]).map(t=>({...t,kind:'Gast'}))];
        const overdue=b.status==='seated'&&new Date(b.ends_at)<new Date();
        const warnings=[...(b.resmio_issues||[]),...(b.resmio_unassigned&&!(b.resmio_issues||[]).length?['Tischzuordnung unvollständig; bitte prüfen.']:[]),...(b.resmio_missing?['Im letzten Resmio-Abgleich nicht enthalten; bitte prüfen.']:[]),...(overdue?['Geplante Endzeit überschritten – Aufenthaltsdauer bei Bedarf anpassen.']:[])];
        const details=expanded===b.id,id=`booking-details-${b.id}`,name=b.guest_name||'Spontane Gäste';
        const actions=editableStatuses(b);
        return <React.Fragment key={b.id}><tr className={`rb-reservation-row ${['cancelled','finished','noshow'].includes(b.status)?'is-inactive':''} ${warnings.length?'has-warning':''}`}>
            <td className="rb-row-type"><span title={source} aria-label={source}><Icon size={17}/></span></td>
            <td className="rb-row-time"><button type="button" onClick={()=>open(b)} aria-label={`${name} um ${clock(b.starts_at)} bearbeiten`}><strong>{clock(b.starts_at)}</strong><small>bis {clock(b.ends_at)}</small>{localParts(b.starts_at).date!==date&&<small>{localParts(b.starts_at).date}</small>}</button></td>
            <td className="rb-row-name"><button type="button" className="rb-row-guest" onClick={()=>open(b)}>{name}</button>{tags.length>0&&<div className="rb-row-tags">{tags.slice(0,3).map((t,i)=><span key={`${t.kind}-${t.id}-${i}`} title={`${t.kind}-Tag`}>{t.name}</span>)}{tags.length>3&&<button type="button" onClick={()=>setExpanded(details?null:b.id)} aria-expanded={details} aria-controls={id}>+{tags.length-3}</button>}</div>}</td>
            <td className="rb-row-count"><span className="rb-mobile-label">Personen</span><strong>{b.party_size}</strong></td>
            <td className="rb-row-tables"><button type="button" onClick={()=>open(b)}>{b.tables.length?tableLabel(b.tables):b.status==='waiting'?(b.public_meta?.requested_room_name?`Wartet · ${b.public_meta.requested_room_name}`:'Wartet auf Platz'):'Kein Tisch'}</button></td>
            <td className="rb-row-notes">{notes.length||warnings.length?<button type="button" className="rb-note-toggle" onClick={()=>setExpanded(details?null:b.id)} aria-expanded={details} aria-controls={id}><span>{warnings.length>0&&<span className="rb-row-warning"><AlertCircle size={13}/>Prüfen</span>}{notes.length>0&&<span className="rb-note-preview">{notes.map(([value])=>value).join(' · ')}</span>}{!notes.length&&warnings.length>0&&<span className="rb-note-preview">{warnings[0]}</span>}</span>{details?<ChevronUp size={14}/>:<ChevronDown size={14}/>}</button>:<span className="rb-note-empty">—</span>}</td>
            <td className="rb-row-status">{actions.length?<span className="rb-status-select-wrap"><select className={`rb-status-control rb-status-${b.status}`} aria-label={`Status für ${name}`} value={canonicalStatus(b.status)} disabled={busy} onChange={e=>onStatus(b,e.target.value)}>{actions.map(s=><option key={s} value={s}>{statusLabels[s]}</option>)}</select><ChevronDown size={14} aria-hidden="true"/></span>:<span className={`rb-status rb-status-${b.status}`}>{statusLabels[b.status]}</span>}{b.status==='waiting'&&(b.source==='resmio'?<small>In Resmio bestätigen</small>:<button type="button" className="rb-btn rb-row-promote" disabled={busy} onClick={()=>onPromote(b)}>Übernehmen</button>)}</td>
            <td className="rb-row-edit"><button type="button" className="rb-icon" aria-label={`${name} bearbeiten`} onClick={()=>open(b)}><Pencil size={15}/></button></td>
        </tr>{details&&<tr className="rb-row-details" id={id}><td colSpan={8}><div className="rb-note-details">
            {tags.length>0&&<div className="rb-tags">{tags.map((t,i)=><span key={`${t.kind}-${t.id}-${i}`}>{t.kind}: {t.name}</span>)}</div>}
            {notes.map(([value,label])=><div key={label}><strong>{label}</strong><p>{value}</p></div>)}
            {warnings.map((warning,i)=><p className="rb-overdue" key={i}>{warning}</p>)}
            {b.resmio_local_status&&<p className="rb-hint">Lokaler Status: {statusLabels[b.resmio_local_status]} · Resmio: {statusLabels[b.resmio_status]||b.resmio_status}</p>}
            <button type="button" className="rb-btn" onClick={()=>open(b)}>Buchung bearbeiten</button>
        </div></td></tr>}</React.Fragment>;
    })}{!sorted.length&&<tr><td colSpan={8}><div className="rb-empty">{busy?'Buchungen werden geladen …':'Keine Einträge für diese Auswahl.'}</div></td></tr>}</tbody></table></div>;
}
