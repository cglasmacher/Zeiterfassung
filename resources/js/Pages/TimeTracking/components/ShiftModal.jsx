import React, { useState, useEffect } from 'react';
import Modal from '@/Components/ui/Modal';
import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import { AlertCircle, Users } from 'lucide-react';
import axios from 'axios';

export default function ShiftModal({ isOpen, onClose, onSave, onApproved, shift, date, shiftTypes, employees }) {
  const [formData, setFormData] = useState({ shift_type_id:'', employee_id:'', department_id:'', shift_date:date||'', start_time:'', end_time:'', is_open_for_applications:false });
  const [errors, setErrors] = useState({});
  const [approving, setApproving] = useState(null);

  useEffect(() => {
    if (shift) setFormData({
      shift_type_id: shift.shift_type_id || '', employee_id: shift.employee_id || '', department_id: shift.department_id || '',
      shift_date: shift.shift_date || date || '', start_time: shift.start_time?.substring(0,5)||'', end_time: shift.end_time?.substring(0,5)||'',
      is_open_for_applications: !!shift.is_open_for_applications,
    });
    else setFormData({ shift_type_id:'', employee_id:'', department_id:'', shift_date:date||'', start_time:'', end_time:'', is_open_for_applications:false });
    setErrors({});
  }, [shift,date,isOpen]);

  const handleShiftTypeChange = (id) => {
    const type=shiftTypes.find(s=>s.id===parseInt(id)); let dept=formData.department_id;
    if(type?.departments?.length && !type.departments.find(d=>d.id===parseInt(dept))) dept=type.departments[0].id;
    setFormData({...formData,shift_type_id:id,department_id:dept,start_time:type?.default_start?.substring(0,5)||'',end_time:type?.default_end?.substring(0,5)||''});
  };
  const handleSubmit=(e)=>{e.preventDefault();setErrors({});if(!formData.shift_type_id){setErrors({shift_type_id:['Bitte wählen Sie einen Schichttyp']});return;}const d={...formData};const type=shiftTypes.find(s=>s.id===parseInt(d.shift_type_id));if(!d.department_id&&type?.departments?.length)d.department_id=type.departments[0].id;if(d.employee_id)d.is_open_for_applications=false;onSave(d);};
  const type=shiftTypes.find(s=>s.id===parseInt(formData.shift_type_id));
  const filtered=formData.department_id?employees.filter(e=>e.departments?.some(d=>d.id===parseInt(formData.department_id))):employees;
  const pending=(shift?.applications||[]).filter(a=>a.status==='pending');
  const approve=async(app)=>{if(!shift?.id)return;setApproving(app.id);try{await axios.post(`/api/planner/open-shifts/${shift.id}/applications/${app.id}/approve`);onApproved?.();onClose();}finally{setApproving(null);}};
  const hourInfo=e=>{if(!e.max_monthly_hours)return null;const p=parseFloat(e.monthly_planned_hours)||0,m=parseFloat(e.max_monthly_hours)||0,r=m-p;return{planned:p,max:m,remaining:r,percentage:m?(p/m)*100:0,isOverloaded:r<0,isNearLimit:r<m*.2&&r>=0};};

  return <Modal isOpen={isOpen} onClose={onClose} title={shift?.id?'Schicht bearbeiten':'Neue Schicht'} size="md" footer={<div className="flex justify-end gap-3"><Button variant="ghost" onClick={onClose}>Abbrechen</Button><Button variant="primary" onClick={handleSubmit}>{shift?.id?'Speichern':'Erstellen'}</Button></div>}>
    <form onSubmit={handleSubmit} className="space-y-4">
      <Input type="date" label="Datum" value={formData.shift_date} onChange={e=>setFormData({...formData,shift_date:e.target.value})} required />
      <div><label className="block text-sm font-medium text-neutral-700 mb-1.5">Schichttyp *</label><select className="input" value={formData.shift_type_id} onChange={e=>handleShiftTypeChange(e.target.value)} required><option value="">Bitte wählen...</option>{shiftTypes.map(t=><option key={t.id} value={t.id}>{t.name} ({t.default_start?.substring(0,5)} - {t.default_end?.substring(0,5)})</option>)}</select>{errors.shift_type_id&&<p className="mt-1 text-sm text-error-500">{errors.shift_type_id[0]}</p>}</div>
      {type?.departments?.length>0&&<div><label className="block text-sm font-medium text-neutral-700 mb-1.5">Station *</label><select className="input" value={formData.department_id} onChange={e=>setFormData({...formData,department_id:e.target.value,employee_id:''})}>{type.departments.map(d=><option key={d.id} value={d.id}>{d.name}</option>)}</select></div>}
      <div className="grid grid-cols-2 gap-4"><Input type="time" label="Startzeit" value={formData.start_time} onChange={e=>setFormData({...formData,start_time:e.target.value})}/><Input type="time" label="Endzeit" value={formData.end_time} onChange={e=>setFormData({...formData,end_time:e.target.value})}/></div>
      <div><label className="block text-sm font-medium text-neutral-700 mb-1.5">Mitarbeiter zuweisen</label><select className="input" value={formData.employee_id} onChange={e=>setFormData({...formData,employee_id:e.target.value,is_open_for_applications:e.target.value?false:formData.is_open_for_applications})}><option value="">Nicht besetzt</option>{filtered.map(e=>{const h=hourInfo(e);return <option key={e.id} value={e.id}>{e.first_name} {e.last_name}{h&&` (${h.planned.toFixed(0)}/${h.max}h)`}</option>})}</select></div>
      {!formData.employee_id&&<label className="flex items-start gap-3 p-3 rounded-lg border border-warning-200 bg-warning-50 cursor-pointer"><input type="checkbox" className="mt-1" checked={formData.is_open_for_applications} onChange={e=>setFormData({...formData,is_open_for_applications:e.target.checked})}/><div><div className="font-semibold text-warning-900">Als freie Schicht veröffentlichen</div><div className="text-xs text-warning-700 mt-1">Mitarbeiter sehen diese Schicht in der App und können „Ich kann“ wählen.</div></div></label>}
      {shift?.id&&shift.is_open_for_applications&&<div className="border-t pt-4"><div className="flex items-center gap-2 mb-3"><Users className="w-4 h-4"/><span className="font-semibold">Bewerbungen ({pending.length})</span></div>{pending.length===0?<p className="text-sm text-neutral-500">Noch keine Bewerbungen.</p>:<div className="space-y-2">{pending.map(a=><div key={a.id} className="flex items-center justify-between gap-3 p-3 rounded-lg bg-neutral-50 border"><div><div className="font-medium">{a.employee?.first_name} {a.employee?.last_name}</div><div className="text-xs text-neutral-500">möchte diese Schicht übernehmen</div></div><Button type="button" variant="primary" size="sm" disabled={approving===a.id} onClick={()=>approve(a)}>{approving===a.id?'Vergabe…':'Zuweisen'}</Button></div>)}</div>}</div>}
    </form>
  </Modal>;
}
