import { useEffect, useState } from 'react';
import { CalendarPlus, CalendarDays, ExternalLink, LogOut, Megaphone, UserRound } from 'lucide-react';
import api, { logout } from './api';

function fmtDateTime(value){ if(!value) return ''; return new Intl.DateTimeFormat('de-DE',{weekday:'short',day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}).format(new Date(value)); }
function icsEscape(value=''){ return String(value).replace(/\\/g,'\\\\').replace(/\n/g,'\\n').replace(/,/g,'\\,').replace(/;/g,'\\;'); }
function icsDate(value){ return new Date(value).toISOString().replace(/[-:]/g,'').replace(/\.\d{3}/,''); }
function buildIcs(event){ const start=event.starts_at?new Date(event.starts_at):new Date(),end=event.ends_at?new Date(event.ends_at):new Date(start.getTime()+2*60*60*1000); return ['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Hemingway Hilden//Team App//DE','CALSCALE:GREGORIAN','METHOD:PUBLISH','BEGIN:VEVENT',`UID:hemingway-event-${event.id}@hemingway-hilden.de`,`DTSTAMP:${icsDate(new Date())}`,`DTSTART:${icsDate(start)}`,`DTEND:${icsDate(end)}`,`SUMMARY:${icsEscape(event.title)}`,event.body?`DESCRIPTION:${icsEscape(event.body)}`:null,'LOCATION:Hemingway Hilden, Markt 16, 40721 Hilden','END:VEVENT','END:VCALENDAR'].filter(Boolean).join('\r\n'); }
function exportEvent(event){ const blob=new Blob([buildIcs(event)],{type:'text/calendar;charset=utf-8'}),url=URL.createObjectURL(blob),link=document.createElement('a'); link.href=url; link.download=`Hemingway-${String(event.title||'Termin').replace(/[^a-z0-9äöüß_-]+/gi,'-')}.ics`; document.body.appendChild(link); link.click(); link.remove(); setTimeout(()=>URL.revokeObjectURL(url),1500); }

export default function MorePage(){
  const [active,setActive]=useState(false),[notices,setNotices]=useState([]),[events,setEvents]=useState([]),[employee,setEmployee]=useState(null);
  useEffect(()=>{ const detect=()=>setActive(!!document.querySelector('.content .profile')); detect(); const observer=new MutationObserver(detect); observer.observe(document.body,{childList:true,subtree:true}); return()=>observer.disconnect(); },[]);
  useEffect(()=>{ if(!active)return; Promise.allSettled([api.get('/announcements'),api.get('/events'),api.get('/dashboard')]).then(([n,e,d])=>{ if(n.status==='fulfilled')setNotices(n.value.data.announcements||[]); if(e.status==='fulfilled')setEvents(e.value.data.events||[]); if(d.status==='fulfilled')setEmployee(d.value.data.employee||null); }); },[active]);
  async function signout(){ await logout(); window.location.reload(); }
  if(!active)return null;
  return <div className="more-overlay"><div className="more-page">
    <section className="more-profile"><div className="more-avatar"><UserRound size={24}/></div><div><small>DEIN PROFIL</small><strong>{employee?.first_name} {employee?.last_name}</strong><span>{employee?.position||'Hemingway Team'}</span></div></section>
    <section className="more-section"><div className="more-heading"><div><Megaphone size={18}/><h3>Mitteilungen</h3></div><span>{notices.length}</span></div>{notices.length?notices.map(item=><article className="info-card" key={item.id}><div className="info-card-head"><small>{item.employee_id?'FÜR DICH':'TEAM'}</small>{item.priority>0&&<b>WICHTIG</b>}</div><h4>{item.title}</h4>{item.body&&<p>{item.body}</p>}{item.url&&<a href={item.url} target="_blank" rel="noreferrer">Link öffnen <ExternalLink size={14}/></a>}</article>):<div className="empty">Keine aktuellen Mitteilungen.</div>}</section>
    <section className="more-section"><div className="more-heading"><div><CalendarDays size={18}/><h3>Events</h3></div><span>{events.length}</span></div>{events.length?events.map(event=><article className="event-card" key={event.id}><div className="event-date"><b>{new Intl.DateTimeFormat('de-DE',{day:'2-digit'}).format(new Date(event.starts_at))}</b><span>{new Intl.DateTimeFormat('de-DE',{month:'short'}).format(new Date(event.starts_at))}</span></div><div className="event-copy"><small>{fmtDateTime(event.starts_at)}{event.ends_at?` – ${new Intl.DateTimeFormat('de-DE',{hour:'2-digit',minute:'2-digit'}).format(new Date(event.ends_at))} Uhr`:' Uhr'}</small><h4>{event.title}</h4>{event.body&&<p>{event.body}</p>}<button onClick={()=>exportEvent(event)}><CalendarPlus size={16}/> Zum Kalender hinzufügen</button></div></article>):<div className="empty">Keine anstehenden Events.</div>}</section>
    <button className="logout-button" onClick={signout}><LogOut size={18}/> Abmelden</button>
  </div></div>;
}
