import React, { useState, useEffect } from 'react';
import Modal from '@/Components/ui/Modal';
import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import { AlertCircle, Users } from 'lucide-react';
import axios from 'axios';

export default function ShiftModal({ isOpen, onClose, onSave, shift, date, shiftTypes, employees, departments }) {
  const emptyForm = {
    shift_type_id: '', employee_id: '', department_id: '', shift_date: date || '',
    start_time: '', end_time: '', is_open_for_applications: false,
  };
  const [formData, setFormData] = useState(emptyForm);
  const [errors, setErrors] = useState({});
  const [applications, setApplications] = useState([]);
  const [approving, setApproving] = useState(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (shift) {
      setFormData({
        shift_type_id: shift.shift_type_id || '', employee_id: shift.employee_id || '',
        department_id: shift.department_id || '', shift_date: shift.shift_date || date || '',
        start_time: shift.start_time?.substring(0, 5) || '', end_time: shift.end_time?.substring(0, 5) || '',
        is_open_for_applications: !!shift.is_open_for_applications,
      });
    } else {
      setFormData({ ...emptyForm, shift_date: date || '' });
    }
    setErrors({});
    setApplications([]);
  }, [shift, date, isOpen, departments]);

  useEffect(() => {
    if (!isOpen || !shift?.id || !shift.is_open_for_applications) return;
    axios.get('/api/planner/open-shifts', { params: { start: shift.shift_date, end: shift.shift_date } })
      .then(({ data }) => {
        const current = (data.shifts || []).find(item => item.id === shift.id);
        setApplications(current?.applications || []);
      })
      .catch(() => setApplications([]));
  }, [isOpen, shift]);

  const handleShiftTypeChange = (shiftTypeId) => {
    const shiftType = shiftTypes.find(st => st.id === parseInt(shiftTypeId));
    let departmentId = formData.department_id;
    if (shiftType?.departments?.length) {
      const valid = shiftType.departments.some(d => d.id === parseInt(departmentId));
      if (!valid) departmentId = shiftType.departments[0].id;
    }
    setFormData({ ...formData, shift_type_id: shiftTypeId, department_id: departmentId,
      start_time: shiftType?.default_start?.substring(0, 5) || '', end_time: shiftType?.default_end?.substring(0, 5) || '' });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrors({});
    if (!formData.shift_type_id) {
      setErrors({ shift_type_id: ['Bitte wählen Sie einen Schichttyp'] });
      return;
    }
    const dataToSave = { ...formData };
    const selectedType = shiftTypes.find(st => st.id === parseInt(dataToSave.shift_type_id));
    if (!dataToSave.department_id && selectedType?.departments?.length) dataToSave.department_id = selectedType.departments[0].id;
    if (dataToSave.employee_id) dataToSave.is_open_for_applications = false;

    if (!shift?.id && dataToSave.is_open_for_applications) {
      setSaving(true);
      try {
        await axios.post('/api/planner/open-shifts', {
          shift_type_id: dataToSave.shift_type_id, department_id: dataToSave.department_id || null,
          shift_date: dataToSave.shift_date, start_time: dataToSave.start_time, end_time: dataToSave.end_time,
        });
        onClose();
        window.location.reload();
      } catch (error) {
        setErrors(error.response?.data?.errors || { general: ['Freie Schicht konnte nicht erstellt werden.'] });
      } finally { setSaving(false); }
      return;
    }
    onSave(dataToSave);
  };

  const selectedShiftType = shiftTypes.find(st => st.id === parseInt(formData.shift_type_id));
  const filteredEmployees = formData.department_id
    ? employees.filter(emp => emp.departments?.some(d => d.id === parseInt(formData.department_id))) : employees;

  const getEmployeeHourInfo = (employee) => {
    if (!employee.max_monthly_hours) return null;
    const planned = parseFloat(employee.monthly_planned_hours) || 0;
    const max = parseFloat(employee.max_monthly_hours) || 0;
    const remaining = max - planned;
    const percentage = max > 0 ? (planned / max) * 100 : 0;
    return { planned, max, remaining, percentage, isOverloaded: remaining < 0, isNearLimit: remaining < max * 0.2 && remaining >= 0 };
  };

  const pending = applications.filter(a => a.status === 'pending');
  const approve = async (application) => {
    setApproving(application.id);
    try {
      await axios.post(`/api/planner/open-shifts/${shift.id}/applications/${application.id}/approve`);
      onClose();
      window.location.reload();
    } finally { setApproving(null); }
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={shift ? 'Schicht bearbeiten' : 'Neue Schicht'} size="md"
      footer={<div className="flex items-center justify-end gap-3"><Button variant="ghost" onClick={onClose}>Abbrechen</Button><Button variant="primary" disabled={saving} onClick={handleSubmit}>{saving ? 'Erstellen…' : shift ? 'Speichern' : 'Erstellen'}</Button></div>}>
      <form onSubmit={handleSubmit} className="space-y-4">
        {errors.general && <p className="text-sm text-error-600">{errors.general[0]}</p>}
        <Input type="date" label="Datum" value={formData.shift_date} onChange={e => setFormData({ ...formData, shift_date: e.target.value })} error={errors.shift_date?.[0]} required />
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1.5">Schichttyp *</label>
          <select className={`input ${errors.shift_type_id ? 'border-error-500' : ''}`} value={formData.shift_type_id} onChange={e => handleShiftTypeChange(e.target.value)} required>
            <option value="">Bitte wählen...</option>
            {shiftTypes.map(type => <option key={type.id} value={type.id}>{type.name} ({type.default_start?.substring(0, 5)} - {type.default_end?.substring(0, 5)})</option>)}
          </select>
          {errors.shift_type_id && <p className="mt-1.5 text-sm text-error-500">{errors.shift_type_id[0]}</p>}
        </div>
        {selectedShiftType?.departments?.length > 0 && <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1.5">Station *</label>
          <select className="input" value={formData.department_id} onChange={e => setFormData({ ...formData, department_id: e.target.value, employee_id: '' })} required>
            {selectedShiftType.departments.map(dept => <option key={dept.id} value={dept.id}>{dept.name}</option>)}
          </select>
        </div>}
        <div className="grid grid-cols-2 gap-4">
          <Input type="time" label="Startzeit" value={formData.start_time} onChange={e => setFormData({ ...formData, start_time: e.target.value })} error={errors.start_time?.[0]} />
          <Input type="time" label="Endzeit" value={formData.end_time} onChange={e => setFormData({ ...formData, end_time: e.target.value })} error={errors.end_time?.[0]} />
        </div>
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1.5">Mitarbeiter zuweisen</label>
          <select className="input" value={formData.employee_id} onChange={e => setFormData({ ...formData, employee_id: e.target.value, is_open_for_applications: e.target.value ? false : formData.is_open_for_applications })}>
            <option value="">Nicht besetzt</option>
            {filteredEmployees.map(emp => { const info = getEmployeeHourInfo(emp); return <option key={emp.id} value={emp.id}>{emp.first_name} {emp.last_name}{info && ` (${info.planned.toFixed(0)}/${info.max}h)`}</option>; })}
          </select>
          {formData.employee_id && (() => {
            const employee = employees.find(e => e.id === parseInt(formData.employee_id));
            const info = employee ? getEmployeeHourInfo(employee) : null;
            if (!info) return null;
            return <div className={`mt-2 p-3 rounded-lg border ${info.isOverloaded ? 'bg-error-50 border-error-200' : info.isNearLimit ? 'bg-warning-50 border-warning-200' : 'bg-success-50 border-success-200'}`}>
              <div className="flex items-start gap-2">{info.isOverloaded && <AlertCircle className="w-4 h-4 text-error-600 flex-shrink-0 mt-0.5" />}<div className="flex-1">
                <p className="text-sm font-medium">Monatliches Stundenkontingent</p>
                <div className="flex items-center gap-2 mt-1"><div className="flex-1 bg-neutral-200 rounded-full h-2"><div className={`h-2 rounded-full ${info.isOverloaded ? 'bg-error-500' : info.isNearLimit ? 'bg-warning-500' : 'bg-success-500'}`} style={{ width: `${Math.min(info.percentage, 100)}%` }} /></div><span className="text-xs font-medium text-neutral-700">{info.percentage.toFixed(0)}%</span></div>
                <p className="text-xs text-neutral-600 mt-1">Geplant: {info.planned.toFixed(1)}h / {info.max}h {info.remaining >= 0 ? `(${info.remaining.toFixed(1)}h verfügbar)` : `(${Math.abs(info.remaining).toFixed(1)}h überschritten!)`}</p>
              </div></div>
            </div>;
          })()}
        </div>
        {!formData.employee_id && <label className="flex items-start gap-3 p-3 rounded-lg border border-warning-200 bg-warning-50 cursor-pointer">
          <input type="checkbox" className="mt-1" checked={formData.is_open_for_applications} onChange={e => setFormData({ ...formData, is_open_for_applications: e.target.checked })} />
          <div><div className="font-semibold text-warning-900">Als freie Schicht veröffentlichen</div><div className="text-xs text-warning-700 mt-1">Mitarbeiter sehen diese Schicht in der App und können „Ich kann“ wählen.</div></div>
        </label>}
        {shift?.id && shift.is_open_for_applications && <div className="border-t pt-4">
          <div className="flex items-center gap-2 mb-3"><Users className="w-4 h-4" /><span className="font-semibold">Bewerbungen ({pending.length})</span></div>
          {pending.length === 0 ? <p className="text-sm text-neutral-500">Noch keine Bewerbungen.</p> : <div className="space-y-2">{pending.map(application => <div key={application.id} className="flex items-center justify-between gap-3 p-3 rounded-lg bg-neutral-50 border"><div><div className="font-medium">{application.employee?.first_name} {application.employee?.last_name}</div><div className="text-xs text-neutral-500">möchte diese Schicht übernehmen</div></div><Button type="button" variant="primary" size="sm" disabled={approving === application.id} onClick={() => approve(application)}>{approving === application.id ? 'Vergabe…' : 'Zuweisen'}</Button></div>)}</div>}
        </div>}
      </form>
    </Modal>
  );
}
