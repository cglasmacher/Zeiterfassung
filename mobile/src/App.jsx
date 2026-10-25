import { useEffect, useState } from 'react';
import { CalendarDays, CheckSquare, ChevronLeft, ChevronRight, Clock3, Coffee, Home, LogOut, MoreHorizontal } from 'lucide-react';
import api, { hasToken, login, logout } from './api';
import HaccpPage from './HaccpPage';

const tabs = [
  ['home', Home, 'Home'], ['plan', CalendarDays, 'Plan'], ['time', Clock3, 'Zeit'],
  ['tasks', CheckSquare, 'Aufgaben'], ['more', MoreHorizontal, 'Mehr'],
];
const dayNames=['Mo','Di','Mi','Do','Fr','Sa','So'];

function localDate(v){ return /^\d{4}-\d{2}-\d{2}$/.test(v) ? new Date(`${v}T12:00:00`) : new Date(v); }
function dateKey(d){ const y=d.getFullYear(),m=String(d.getMonth()+1).padStart(2,'0'),day=String(d.getDate()).padStart(2,'0'); return `${y}-${m}-${day}`; }
function addDays(d,n){ const x=new Date(d); x.setDate(x.getDate()+n); return x; }
function mondayOf(d){ const x=new Date(d); x.setHours(12,0,0,0); const day=x.getDay()||7; x.setDate(x.getDate()-day+1); return x; }
function monthKey(d){ return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`; }
function addMonths(d,n){ const x=new Date(d.getFullYear(),d.getMonth()+n,1,12); return x; }
function fmtDate(v) { if (!v) return ''; return new Intl.DateTimeFormat('de-DE', { weekday:'short', day:'2-digit', month:'2-digit' }).format(localDate(v)); }
function fmtLongDate(v){ if(!v) return ''; return new Intl.DateTimeFormat('de-DE',{weekday:'short',day:'2-digit',month:'2-digit'}).format(new Date(v)); }
function fmtTime(v) { if (!v) return ''; const d = v.includes?.('T') ? new Date(v) : new Date(`2000-01-01T${v}`); return new Intl.DateTimeFormat('de-DE',{hour:'2-digit',minute:'2-digit'}).format(d); }
function fmtWeek(start){ const end=addDays(start,6); const a=new Intl.DateTimeFormat('de-DE',{day:'2-digit',month:'2-digit'}).format(start); const b=new Intl.DateTimeFormat('de-DE',{day:'2-digit',month:'2-digit',year:'numeric'}).format(end); return `${a} – ${b}`; }
function fmtMonth(d){ return new Intl.DateTimeFormat('de-DE',{month:'long',year:'numeric'}).format(d); }
function fmtHours(v){ return `${Number(v||0).toLocaleString('de-DE',{minimumFractionDigits:1,maximumFractionDigits:2})} h`; }
function fmtMinutes(v){ const mins=Number(v||0); if(mins<60) return `${mins} min`; const h=Math.floor(mins/60),m=mins%60; return m?`${h} h ${m} min`:`${h} h`; }
function taskDue(item){ if(!item.due_at) return null; const due=new Date(item.due_at),today=new Date(); today.setHours(0,0,0,0); const dueDay=new Date(due); dueDay.setHours(0,0,0,0); const diff=Math.round((dueDay-today)/86400000); if(diff<0&&!item.completed_at) return {text:`Überfällig · ${fmtDate(item.due_at)}`,className:'overdue'}; if(diff===0) return {text:`Heute · ${fmtTime(item.due_at)} Uhr`,className:'today'}; if(diff===1) return {text:`Morgen · ${fmtTime(item.due_at)} Uhr`,className:''}; return {text:`Fällig ${fmtDate(item.due_at)} · ${fmtTime(item.due_at)} Uhr`,className:''}; }

export default function App() {
  const [ready,setReady]=useState(false), [auth,setAuth]=useState(false), [tab,setTab]=useState('home');
  const [email,setEmail]=useState(''), [password,setPassword]=useState(''), [error,setError]=useState('');
  const [dashboard,setDashboard]=useState(null), [shifts,setShifts]=useState([]), [tasks,setTasks]=useState([]), [notices,setNotices]=useState([]), [events,setEvents]=useState([]), [time,setTime]=useState(null), [haccpSummary,setHaccpSummary]=useState(null);
  const [busy,setBusy]=useState(false), [actionError,setActionError]=useState('');
  const [weekStart,setWeekStart]=useState(()=>mondayOf(new Date())), [selectedDay,setSelectedDay]=useState(()=>dateKey(new Date())), [planBusy,setPlanBusy]=useState(false), [planError,setPlanError]=useState('');
  const [openShifts,setOpenShifts]=useState([]), [applicationBusy,setApplicationBusy]=useState(null);
  const [timeMonth,setTimeMonth]=useState(()=>new Date(new Date().getFullYear(),new Date().getMonth(),1,12)), [timeHistory,setTimeHistory]=useState(null), [timeBusy,setTimeBusy]=useState(false), [timeError,setTimeError]=useState('');

  useEffect(()=>{ hasToken().then(v=>{setAuth(v);setReady(true);}); },[]);
  useEffect(()=>{ if(auth) refresh(); },[auth]);
  useEffect(()=>{ if(auth&&tab==='plan') loadWeek(weekStart); },[auth,tab,weekStart]);
  useEffect(()=>{ if(auth&&tab==='time') loadTimeHistory(timeMonth); },[auth,tab,timeMonth]);
  useEffect(()=>{ if(auth&&tab==='home') refresh(); },[auth,tab]);
  useEffect(()=>{
    if(!auth) return;
    const handleVisibility=()=>{ if(document.visibilityState==='visible'){ refresh(); if(tab==='time') loadTimeHistory(timeMonth); } };
    document.addEventListener('visibilitychange',handleVisibility);
    return ()=>document.removeEventListener('visibilitychange',handleVisibility);
  },[auth,tab,timeMonth]);

  async function refresh(){
    const requests = await Promise.allSettled([
      api.get('/dashboard'), api.get('/shifts'), api.get('/tasks'), api.get('/announcements'), api.get('/events'), api.get('/time/status'), api.get('/haccp/today')
    ]);
    const unauthorized = requests.some(r=>r.status==='rejected' && r.reason?.response?.status===401);
    if(unauthorized){ await logout(); setAuth(false); return; }
    const [d,s,t,n,e,ts,h] = requests;
    if(d.status==='fulfilled') setDashboard(d.value.data);
    if(s.status==='fulfilled') setShifts(s.value.data.shifts||[]);
    if(t.status==='fulfilled') setTasks(t.value.data.tasks||[]);
    if(n.status==='fulfilled') setNotices(n.value.data.announcements||n.value.data.notices||[]);
    if(e.status==='fulfilled') setEvents(e.value.data.events||[]);
    if(ts.status==='fulfilled') setTime(ts.value.data);
    if(h.status==='fulfilled') setHaccpSummary(h.value.data.summary||{});
    const failed = requests.find(r=>r.status==='rejected');
    if(failed) console.error('Mobile API refresh failed:', failed.reason?.response?.data || failed.reason);
  }

  async function loadWeek(start){
    setPlanBusy(true); setPlanError('');
    try{
      const from=dateKey(start), to=dateKey(addDays(start,6));
      const [assigned,open]=await Promise.all([api.get('/shifts',{params:{from,to}}),api.get('/open-shifts',{params:{from,to}})]);
      setShifts(assigned.data.shifts||[]); setOpenShifts(open.data.shifts||[]);
    }catch(err){ if(err.response?.status===401){ await logout(); setAuth(false); return; } setPlanError(err.response?.data?.message||'Dienstplan konnte nicht geladen werden.'); }
    finally{setPlanBusy(false);}
  }
  async function loadTimeHistory(month){
    setTimeBusy(true); setTimeError('');
    try{ const res=await api.get('/time/history',{params:{month:monthKey(month)}}); setTimeHistory(res.data); }
    catch(err){ if(err.response?.status===401){ await logout(); setAuth(false); return; } setTimeError(err.response?.data?.message||'Arbeitszeiten konnten nicht geladen werden.'); }
    finally{setTimeBusy(false);}
  }
  function changeWeek(delta){ const next=addDays(weekStart,delta*7); setWeekStart(next); setSelectedDay(dateKey(next)); }
  function currentWeek(){ const start=mondayOf(new Date()); setWeekStart(start); setSelectedDay(dateKey(new Date())); }
  async function applyForShift(shift){
    setApplicationBusy(shift.id); setPlanError('');
    try{ if(shift.application?.status==='pending') await api.delete(`/open-shifts/${shift.id}/apply`); else await api.post(`/open-shifts/${shift.id}/apply`); await loadWeek(weekStart); }
    catch(err){ setPlanError(err.response?.data?.message||'Bewerbung konnte nicht aktualisiert werden.'); if(err.response?.status===409) await loadWeek(weekStart); }
    finally{setApplicationBusy(null);}
  }
  async function submit(e){ e.preventDefault(); setBusy(true); setError(''); try{ await login(email,password);setAuth(true); }catch(err){setError(err.response?.data?.message||'Anmeldung fehlgeschlagen.');}finally{setBusy(false);} }
  async function clock(){
    setBusy(true); setActionError('');
    try{ await api.post(time?.clocked_in?'/time/clock-out':'/time/clock-in'); const status=await api.get('/time/status'); setTime(status.data); await refresh(); if(tab==='time') await loadTimeHistory(timeMonth); }
    catch(err){ setActionError(err.response?.data?.message||'Zeiterfassung konnte nicht aktualisiert werden.'); if(err.response?.status===409){ try{ const status=await api.get('/time/status'); setTime(status.data); }catch{} } }
    finally{setBusy(false);}
  }
  async function complete(id,completed){ await api.patch(`/tasks/${id}/complete`,{completed}); await refresh(); }
  async function signout(){ await logout();setAuth(false);setDashboard(null); }

  if(!ready) return <div className="splash">HEMINGWAY</div>;
  if(!auth) return <main className="login"><div className="brand">HEMINGWAY<span>HILDEN</span></div><div><h1>Team App</h1><p>Dienstplan, Zeiterfassung und aktuelle Informationen.</p></div><form onSubmit={submit}><input type="email" placeholder="E-Mail" value={email} onChange={e=>setEmail(e.target.value)} required/><input type="password" placeholder="Passwort" value={password} onChange={e=>setPassword(e.target.value)} required/><button disabled={busy}>Anmelden</button>{error&&<div className="error">{error}</div>}</form></main>;

  const emp=dashboard?.employee;
  const weekDays=Array.from({length:7},(_,i)=>addDays(weekStart,i));
  const selectedShifts=shifts.filter(s=>s.shift_date===selectedDay), selectedOpenShifts=openShifts.filter(s=>s.shift_date===selectedDay);
  const todayKey=dateKey(new Date()), currentStart=dateKey(mondayOf(new Date()));
  const timeSummary=timeHistory?.summary||{}, timeEntries=timeHistory?.entries||[];
  const openTasks=tasks.filter(x=>!x.completed_at), completedTasks=tasks.filter(x=>x.completed_at);
  const haccpAlert=(haccpSummary?.critical_overdue||0)>0||(haccpSummary?.overdue||0)>0;

  return <div className="app"><header><div><small>HEMINGWAY · HILDEN</small><h1>{tab==='home'?`Hallo${emp?.first_name?` ${emp.first_name}`:''}`:tab==='haccp'?'HACCP heute':tabs.find(x=>x[0]===tab)?.[2]}</h1></div>{tab==='more'&&<button className="icon" onClick={signout}><LogOut size={20}/></button>}</header>
    <main className="content">
      {tab==='home'&&<>
        <section className="hero"><span>HEUTE</span>{dashboard?.today_shift?<><h2>{dashboard.today_shift.shift_type?.name||dashboard.today_shift.department?.name||'Schicht'}</h2><strong>{fmtTime(dashboard.today_shift.start_time)} – {fmtTime(dashboard.today_shift.end_time)}</strong></>:<><h2>Keine Schicht geplant</h2><p>Für heute ist keine Schicht eingetragen.</p></>}</section>
        <button className={`clock ${time?.clocked_in?'out':''}`} onClick={clock} disabled={busy}><Clock3/>{time?.clocked_in?'AUSSTEMPELN':'EINSTEMPELN'}</button>
        {actionError&&<div className="error">{actionError}</div>}
        <div className="grid"><Card title="Nächste Schicht">{dashboard?.next_shift?<><b>{fmtDate(dashboard.next_shift.shift_date)}</b><span>{fmtTime(dashboard.next_shift.start_time)} – {fmtTime(dashboard.next_shift.end_time)}</span></>:<span>Keine weitere Schicht</span>}</Card><Card title="Aufgaben"><b>{dashboard?.open_tasks||0} offen</b><span>Deine aktuellen Aufgaben</span></Card><button className={`haccp-home-card ${haccpAlert?'alert':''}`} onClick={()=>setTab('haccp')}><div><small>HACCP HEUTE</small><strong>{haccpSummary?.open||0} offen{haccpSummary?.overdue?` · ${haccpSummary.overdue} überfällig`:''}</strong><span>{haccpAlert?'Kontrollen benötigen Aufmerksamkeit':'Heutige Kontrollen öffnen'}</span></div><span className="badge">{haccpSummary?.open||0}</span></button></div>
        {notices.length>0&&<section><h3>Mitteilungen</h3>{notices.slice(0,3).map(x=><Item key={x.id} item={x}/>)}</section>}
      </>}
      {tab==='haccp'&&<HaccpPage onBack={()=>setTab('home')} onChanged={refresh}/>} 
      {tab==='plan'&&<section className="plan"><div className="week-head"><button onClick={()=>changeWeek(-1)} aria-label="Vorherige Woche"><ChevronLeft/></button><div><small>DEINE WOCHE</small><strong>{fmtWeek(weekStart)}</strong></div><button onClick={()=>changeWeek(1)} aria-label="Nächste Woche"><ChevronRight/></button></div>{dateKey(weekStart)!==currentStart&&<button className="today-button" onClick={currentWeek}>Diese Woche</button>}<div className="day-strip">{weekDays.map((d,i)=>{const key=dateKey(d),dayShifts=shifts.filter(s=>s.shift_date===key),has=dayShifts.length>0,hasApproved=dayShifts.some(s=>s.from_application),hasOpen=openShifts.some(s=>s.shift_date===key);return <button key={key} className={`${selectedDay===key?'selected ':''}${key===todayKey?'today':''}`} onClick={()=>setSelectedDay(key)}><span>{dayNames[i]}</span><b>{d.getDate()}</b><i className={hasApproved?'has-approved':has?'has-shift':hasOpen?'has-open':''}/></button>})}</div>{planError&&<div className="error plan-error">{planError}</div>}<div className={`selected-shift ${selectedShifts.some(s=>s.from_application)?'approved-shift':''}`}><small>{selectedDay===todayKey?'HEUTE':fmtDate(selectedDay).toUpperCase()}</small>{planBusy?<p>Lade Dienstplan …</p>:selectedShifts.length?selectedShifts.map(s=><div className="shift-main" key={s.id}><div><h2>{s.shift_type?.name||s.department?.name||'Schicht'}</h2><strong>{fmtTime(s.start_time)} – {fmtTime(s.end_time)}</strong>{s.from_application&&<span className="approved-label">Bestätigte Bewerbung</span>}</div><CalendarDays size={25}/>{s.shift_type?.name&&s.department?.name&&<span>{s.department.name}</span>}</div>):<div className="free-day"><h2>Frei</h2><p>Für diesen Tag ist keine feste Schicht eingetragen.</p></div>}</div><div className="open-shift-list"><h3>Verfügbare Schichten</h3>{planBusy?<div className="empty">Lade verfügbare Schichten …</div>:selectedOpenShifts.length?selectedOpenShifts.map(s=><div className={`open-shift ${s.application?.status==='pending'?'applied':''}`} key={s.id}><div className="open-shift-copy"><small>{s.shift_type?.name||s.department?.name||'Freie Schicht'}</small><strong>{fmtTime(s.start_time)} – {fmtTime(s.end_time)}</strong>{s.department?.name&&<span>{s.department.name}</span>}</div><button disabled={applicationBusy===s.id} onClick={()=>applyForShift(s)}>{applicationBusy===s.id?'…':s.application?.status==='pending'?'Angefragt':'Ich kann'}</button>{s.application?.status==='pending'&&<p>Deine Bewerbung wartet auf Bestätigung. Tippe auf „Angefragt“, um sie zurückzuziehen.</p>}</div>):<Empty text="Für diesen Tag sind keine freien Schichten ausgeschrieben."/>}</div></section>}
      {tab==='time'&&<section className="time-page"><div className="status"><Clock3/><div><small>AKTUELLER STATUS</small><h2>{time?.clocked_in?'Eingestempelt':'Nicht eingestempelt'}</h2>{time?.entry?.clock_in&&<span>Seit {fmtTime(time.entry.clock_in)} Uhr</span>}</div></div><button className={`clock ${time?.clocked_in?'out':''}`} onClick={clock} disabled={busy}>{time?.clocked_in?'AUSSTEMPELN':'EINSTEMPELN'}</button>{actionError&&<div className="error">{actionError}</div>}<div className="time-month-head"><button onClick={()=>setTimeMonth(addMonths(timeMonth,-1))}><ChevronLeft/></button><div><small>ARBEITSZEITEN</small><strong>{fmtMonth(timeMonth)}</strong></div><button onClick={()=>setTimeMonth(addMonths(timeMonth,1))} disabled={monthKey(timeMonth)>=monthKey(new Date())}><ChevronRight/></button></div>{timeError&&<div className="error plan-error">{timeError}</div>}<div className="time-summary"><div><small>Stunden</small><strong>{timeBusy?'–':fmtHours(timeSummary.hours)}</strong></div><div><small>Arbeitstage</small><strong>{timeBusy?'–':timeSummary.days||0}</strong></div><div><small>Pausen</small><strong>{timeBusy?'–':fmtMinutes(timeSummary.break_minutes)}</strong></div></div><div className="time-history"><h3>Verlauf</h3>{timeBusy?<Empty text="Lade Arbeitszeiten …"/>:timeEntries.length?timeEntries.map(entry=><div className="time-entry" key={entry.id}><div className="time-entry-date"><strong>{fmtLongDate(entry.clock_in)}</strong>{entry.is_manual&&<small>Manuell</small>}</div><div className="time-entry-times"><strong>{fmtTime(entry.clock_in)} – {entry.clock_out?fmtTime(entry.clock_out):'läuft'}</strong><span>{entry.clock_out?`${fmtHours(entry.hours_worked)} netto`:'Aktive Arbeitszeit'}</span></div>{entry.clock_out&&<div className="time-entry-break"><Coffee size={15}/><span>{entry.break_minutes?`${entry.break_minutes} min Pause`:'Keine Pause'}</span></div>}</div>):<Empty text="Für diesen Monat sind noch keine Arbeitszeiten vorhanden."/>}</div></section>}
      {tab==='tasks'&&<section className="tasks-page"><div className="task-summary"><div><small>OFFEN</small><strong>{openTasks.length}</strong></div><div><small>ERLEDIGT</small><strong>{completedTasks.length}</strong></div></div><h3>Offene Aufgaben</h3>{openTasks.length?openTasks.map(x=><TaskCard key={x.id} item={x} onComplete={complete}/>):<Empty text="Keine offenen Aufgaben."/>}{completedTasks.length>0&&<div className="completed-tasks"><h3>Erledigt</h3>{completedTasks.map(x=><TaskCard key={x.id} item={x} onComplete={complete}/>)}</div>}</section>}
      {tab==='more'&&<><section><h3>Mitteilungen</h3>{notices.map(x=><Item key={x.id} item={x}/>)}</section><section><h3>Events</h3>{events.length?events.map(x=><Item key={x.id} item={x} meta={fmtDate(x.starts_at)}/>):<Empty text="Keine anstehenden Events."/>}</section><section className="profile"><b>{emp?.first_name} {emp?.last_name}</b><span>{emp?.position}</span></section></>}
    </main>
    <nav>{tabs.map(([id,Icon,label])=><button className={tab===id?'active':''} key={id} onClick={()=>setTab(id)}><Icon size={21}/><span>{label}</span></button>)}</nav>
  </div>;
}

function TaskCard({item,onComplete}){ const due=taskDue(item); return <div className={`task-card ${item.completed_at?'done':''} ${due?.className||''}`}><label className="task-check"><input type="checkbox" checked={!!item.completed_at} onChange={e=>onComplete(item.id,e.target.checked)}/><span/></label><div className="task-copy"><div className="task-tags">{item.priority>0&&<small className={`priority p${item.priority}`}>{item.priority>=2?'Wichtig':'Priorität'}</small>}<small className="audience">{item.employee_id?'Für dich':'Für alle'}</small></div><strong>{item.title}</strong>{item.body&&<p>{item.body}</p>}<div className="task-meta">{due&&<span className={due.className}>{due.text}</span>}{item.completed_at&&<span>Erledigt {fmtDate(item.completed_at)}</span>}</div></div></div> }
function Card({title,children}){return <div className="card"><small>{title}</small>{children}</div>}
function Item({item,meta}){return <div className="item"><div><strong>{item.title}</strong>{item.body&&<span>{item.body}</span>}</div>{meta&&<small>{meta}</small>}</div>}
function Empty({text}){return <div className="empty">{text}</div>}
