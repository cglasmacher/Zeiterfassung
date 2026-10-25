import { useEffect, useMemo, useState } from 'react';
import { AlertTriangle, CheckCircle2, ChevronLeft, ClipboardCheck, Thermometer } from 'lucide-react';
import api from './api';

function time(value){ if(!value) return ''; return new Intl.DateTimeFormat('de-DE',{hour:'2-digit',minute:'2-digit'}).format(new Date(value)); }
function overdue(task){ return task.status==='open' && task.due_at && new Date(task.due_at)<new Date(); }

export default function HaccpPage({ onBack, onChanged }) {
  const [data,setData]=useState({summary:{},tasks:[]});
  const [loading,setLoading]=useState(true), [error,setError]=useState(''), [saving,setSaving]=useState(null);

  async function load(){
    setLoading(true); setError('');
    try{ const res=await api.get('/haccp/today'); setData(res.data); }
    catch(err){ setError(err.response?.data?.message||'HACCP-Kontrollen konnten nicht geladen werden.'); }
    finally{ setLoading(false); }
  }
  useEffect(()=>{ load(); },[]);

  const tasks=useMemo(()=>[...(data.tasks||[])].sort((a,b)=>{
    const score=t=>(t.status==='open'?0:10)+(t.criticality==='critical'?-4:t.criticality==='elevated'?-2:0)+(overdue(t)?-3:0);
    return score(a)-score(b)||new Date(a.due_at)-new Date(b.due_at);
  }),[data]);

  async function submit(task,payload){
    setSaving(task.id); setError('');
    try{ await api.post(`/haccp/tasks/${task.id}/complete`,payload); await load(); onChanged?.(); }
    catch(err){ setError(err.response?.data?.message||Object.values(err.response?.data?.errors||{}).flat()[0]||'Kontrolle konnte nicht gespeichert werden.'); }
    finally{ setSaving(null); }
  }

  return <section className="haccp-page">
    <button className="haccp-back" onClick={onBack}><ChevronLeft size={20}/> Home</button>
    <div className="haccp-title"><div><small>HEUTE</small><h2>HACCP</h2></div><ClipboardCheck size={28}/></div>
    <div className="haccp-summary">
      <div><small>Offen</small><strong>{data.summary?.open||0}</strong></div>
      <div className={data.summary?.overdue?'warn':''}><small>Überfällig</small><strong>{data.summary?.overdue||0}</strong></div>
      <div><small>Erledigt</small><strong>{data.summary?.completed||0}</strong></div>
    </div>
    {!!data.summary?.critical_overdue&&<div className="haccp-critical"><AlertTriangle/><div><strong>CCP überfällig</strong><span>{data.summary.critical_overdue} kritische Kontrolle{data.summary.critical_overdue===1?'':'n'} sofort durchführen.</span></div></div>}
    {error&&<div className="error haccp-error">{error}</div>}
    {loading?<div className="empty">HACCP wird geladen …</div>:tasks.length?tasks.map(task=><HaccpTask key={task.id} task={task} saving={saving===task.id} onSubmit={payload=>submit(task,payload)}/>):<div className="empty">Für heute sind keine HACCP-Kontrollen vorhanden.</div>}
  </section>;
}

function HaccpTask({task,saving,onSubmit}){
  const [numeric,setNumeric]=useState(''), [text,setText]=useState(''), [corrective,setCorrective]=useState(''), [choice,setChoice]=useState(null);
  const isOpen=task.status==='open', isOverdue=overdue(task), type=task.result_type||'checkbox';
  const numericType=['temperature','number'].includes(type), booleanType=['checkbox','yes_no'].includes(type), textType=['text','checklist'].includes(type);
  const num=numeric===''?null:Number(numeric);
  const outside=numericType&&num!==null&&((task.min_value!==null&&task.min_value!==undefined&&num<Number(task.min_value))||(task.max_value!==null&&task.max_value!==undefined&&num>Number(task.max_value)));
  const badBoolean=booleanType&&choice===false;
  const needsCorrection=isOpen&&(outside||badBoolean)&&(task.requires_corrective_action||task.criticality==='critical');
  const valid=!isOpen?false:numericType?numeric!==''&&!needsCorrection||numeric!==''&&!!corrective.trim():booleanType?choice!==null&&(!needsCorrection||!!corrective.trim()):textType?!!text.trim():true;
  const limits=[task.min_value!==null&&task.min_value!==undefined?`min. ${task.min_value}`:null,task.max_value!==null&&task.max_value!==undefined?`max. ${task.max_value}`:null].filter(Boolean).join(' · ');

  function save(){
    const payload={};
    if(numericType) payload.numeric_value=Number(numeric);
    if(booleanType) payload.boolean_value=choice;
    if(textType) payload.text_value=text.trim();
    if(needsCorrection) payload.corrective_action=corrective.trim();
    onSubmit(payload);
  }

  return <article className={`haccp-task ${task.criticality||'normal'} ${isOverdue?'overdue':''} ${!isOpen?'done':''}`}>
    <div className="haccp-task-head"><div className="haccp-tags">{task.criticality==='critical'&&<span className="ccp">CCP</span>}{task.criticality==='elevated'&&<span className="elevated">ERHÖHT</span>}{isOverdue&&<span className="late">ÜBERFÄLLIG</span>}{!isOpen&&<span className="finished"><CheckCircle2 size={12}/> ERLEDIGT</span>}</div><small>{time(task.due_at)} Uhr</small></div>
    <h3>{task.name}</h3>
    {(task.device?.name||task.role_name)&&<p className="haccp-meta">{task.device?.name}{task.device?.location?` · ${task.device.location}`:''}{task.role_name?` · ${task.role_name}`:''}</p>}
    {limits&&<div className="haccp-limits"><Thermometer size={16}/><span>Sollbereich: {limits}{task.unit?` ${task.unit}`:''}</span></div>}
    {isOpen&&<div className="haccp-inputs">
      {numericType&&<label>Messwert<div className="haccp-number"><input inputMode="decimal" type="number" step="any" value={numeric} onChange={e=>setNumeric(e.target.value)}/>{task.unit&&<span>{task.unit}</span>}</div></label>}
      {booleanType&&<div className="haccp-choice"><button className={choice===true?'selected ok':''} onClick={()=>setChoice(true)}>OK / Ja</button><button className={choice===false?'selected no':''} onClick={()=>setChoice(false)}>Nicht OK / Nein</button></div>}
      {textType&&<label>Kontrollergebnis<textarea rows="3" value={text} onChange={e=>setText(e.target.value)} placeholder="Ergebnis dokumentieren"/></label>}
      {needsCorrection&&<label className="correction">Korrekturmaßnahme<textarea rows="3" value={corrective} onChange={e=>setCorrective(e.target.value)} placeholder="Was wurde unternommen?"/></label>}
      <button className="haccp-save" disabled={!valid||saving} onClick={save}>{saving?'Speichere …':'Kontrolle speichern'}</button>
    </div>}
  </article>;
}
