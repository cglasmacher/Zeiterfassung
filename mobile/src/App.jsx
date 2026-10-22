import { useEffect, useState } from 'react';
import { CalendarDays, CheckSquare, Clock3, Home, LogOut, Megaphone, MoreHorizontal } from 'lucide-react';
import api, { hasToken, login, logout } from './api';

const tabs = [
  ['home', Home, 'Home'], ['plan', CalendarDays, 'Plan'], ['time', Clock3, 'Zeit'],
  ['tasks', CheckSquare, 'Aufgaben'], ['more', MoreHorizontal, 'Mehr'],
];

function fmtDate(v) { return v ? new Intl.DateTimeFormat('de-DE', { weekday:'short', day:'2-digit', month:'2-digit' }).format(new Date(v)) : ''; }
function fmtTime(v) { if (!v) return ''; const d = v.includes?.('T') ? new Date(v) : new Date(`2000-01-01T${v}`); return new Intl.DateTimeFormat('de-DE',{hour:'2-digit',minute:'2-digit'}).format(d); }

export default function App() {
  const [ready,setReady]=useState(false), [auth,setAuth]=useState(false), [tab,setTab]=useState('home');
  const [email,setEmail]=useState(''), [password,setPassword]=useState(''), [error,setError]=useState('');
  const [dashboard,setDashboard]=useState(null), [shifts,setShifts]=useState([]), [tasks,setTasks]=useState([]), [notices,setNotices]=useState([]), [events,setEvents]=useState([]), [time,setTime]=useState(null);
  const [busy,setBusy]=useState(false);

  useEffect(()=>{ hasToken().then(v=>{setAuth(v);setReady(true);}); },[]);
  useEffect(()=>{ if(auth) refresh(); },[auth]);

  async function refresh(){
    try {
      const [d,s,t,n,e,ts]=await Promise.all([
        api.get('/dashboard'), api.get('/shifts'), api.get('/tasks'), api.get('/notices'), api.get('/events'), api.get('/time/status')
      ]);
      setDashboard(d.data); setShifts(s.data.shifts||[]); setTasks(t.data.tasks||[]); setNotices(n.data.notices||[]); setEvents(e.data.events||[]); setTime(ts.data);
    } catch(err){ if(err.response?.status===401){await logout();setAuth(false);} }
  }

  async function submit(e){ e.preventDefault(); setBusy(true); setError(''); try{ await login(email,password);setAuth(true); }catch(err){setError(err.response?.data?.message||'Anmeldung fehlgeschlagen.');}finally{setBusy(false);} }
  async function clock(){ setBusy(true); try{ await api.post(time?.clocked_in?'/time/clock-out':'/time/clock-in'); await refresh(); }finally{setBusy(false);} }
  async function complete(id,completed){ await api.patch(`/tasks/${id}`,{completed}); await refresh(); }
  async function signout(){ await logout();setAuth(false);setDashboard(null); }

  if(!ready) return <div className="splash">HEMINGWAY</div>;
  if(!auth) return <main className="login"><div className="brand">HEMINGWAY<span>HILDEN</span></div><div><h1>Team App</h1><p>Dienstplan, Zeiterfassung und aktuelle Informationen.</p></div><form onSubmit={submit}><input type="email" placeholder="E-Mail" value={email} onChange={e=>setEmail(e.target.value)} required/><input type="password" placeholder="Passwort" value={password} onChange={e=>setPassword(e.target.value)} required/><button disabled={busy}>Anmelden</button>{error&&<div className="error">{error}</div>}</form></main>;

  const emp=dashboard?.employee;
  return <div className="app"><header><div><small>HEMINGWAY · HILDEN</small><h1>{tab==='home'?`Hallo ${emp?.first_name||''}`:tabs.find(x=>x[0]===tab)?.[2]}</h1></div>{tab==='more'&&<button className="icon" onClick={signout}><LogOut size={20}/></button>}</header>
    <main className="content">
      {tab==='home'&&<>
        <section className="hero"><span>HEUTE</span>{dashboard?.today_shift?<><h2>{dashboard.today_shift.shift_type?.name||dashboard.today_shift.department?.name||'Schicht'}</h2><strong>{fmtTime(dashboard.today_shift.start_time)} – {fmtTime(dashboard.today_shift.end_time)}</strong></>:<><h2>Keine Schicht geplant</h2><p>Für heute ist keine Schicht eingetragen.</p></>}</section>
        <button className={`clock ${time?.clocked_in?'out':''}`} onClick={clock} disabled={busy}><Clock3/>{time?.clocked_in?'AUSSTEMPELN':'EINSTEMPELN'}</button>
        <div className="grid"><Card title="Nächste Schicht">{dashboard?.next_shift?<><b>{fmtDate(dashboard.next_shift.shift_date)}</b><span>{fmtTime(dashboard.next_shift.start_time)} – {fmtTime(dashboard.next_shift.end_time)}</span></>:<span>Keine weitere Schicht</span>}</Card><Card title="Aufgaben"><b>{dashboard?.open_tasks||0} offen</b><span>Deine aktuellen Aufgaben</span></Card></div>
        {notices.length>0&&<section><h3>Mitteilungen</h3>{notices.slice(0,3).map(x=><Item key={x.id} item={x}/>)}</section>}
      </>}
      {tab==='plan'&&<section><h3>Kommende Schichten</h3>{shifts.length?shifts.map(s=><div className="row" key={s.id}><div className="date"><b>{fmtDate(s.shift_date)}</b></div><div><strong>{fmtTime(s.start_time)} – {fmtTime(s.end_time)}</strong><span>{s.shift_type?.name||s.department?.name}</span></div></div>):<Empty text="Keine kommenden Schichten."/>}</section>}
      {tab==='time'&&<section><div className="status"><Clock3/><div><small>AKTUELLER STATUS</small><h2>{time?.clocked_in?'Eingestempelt':'Nicht eingestempelt'}</h2>{time?.entry?.clock_in&&<span>Seit {fmtTime(time.entry.clock_in)} Uhr</span>}</div></div><button className={`clock ${time?.clocked_in?'out':''}`} onClick={clock} disabled={busy}>{time?.clocked_in?'AUSSTEMPELN':'EINSTEMPELN'}</button></section>}
      {tab==='tasks'&&<section><h3>Aufgaben</h3>{tasks.length?tasks.map(x=><label className="task" key={x.id}><input type="checkbox" checked={!!x.completed_at} onChange={e=>complete(x.id,e.target.checked)}/><div><strong>{x.title}</strong>{x.body&&<span>{x.body}</span>}</div></label>):<Empty text="Keine offenen Aufgaben."/>}</section>}
      {tab==='more'&&<><section><h3>Mitteilungen</h3>{notices.map(x=><Item key={x.id} item={x}/>)}</section><section><h3>Events</h3>{events.length?events.map(x=><Item key={x.id} item={x} meta={fmtDate(x.starts_at)}/>):<Empty text="Keine anstehenden Events."/>}</section><section className="profile"><b>{emp?.first_name} {emp?.last_name}</b><span>{emp?.position}</span></section></>}
    </main>
    <nav>{tabs.map(([id,Icon,label])=><button className={tab===id?'active':''} key={id} onClick={()=>setTab(id)}><Icon size={21}/><span>{label}</span></button>)}</nav>
  </div>;
}

function Card({title,children}){return <div className="card"><small>{title}</small>{children}</div>}
function Item({item,meta}){return <div className="item"><div><strong>{item.title}</strong>{item.body&&<span>{item.body}</span>}</div>{meta&&<small>{meta}</small>}</div>}
function Empty({text}){return <div className="empty">{text}</div>}
