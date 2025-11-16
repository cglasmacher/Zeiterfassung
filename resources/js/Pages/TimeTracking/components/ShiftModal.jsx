import React, { useState, useEffect } from 'react';
import Modal from '@/Components/ui/Modal';
import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import Badge from '@/Components/ui/Badge';
import { AlertCircle, Clock, User } from 'lucide-react';

export default function ShiftModal({ 
  isOpen, 
  onClose, 
  onSave, 
  shift, 
  date,
  shiftTypes, 
  employees,
  departments 
}) {
  const [formData, setFormData] = useState({
    shift_type_id: '',
    employee_id: '',
    department_id: '',
    shift_date: date || '',
    start_time: '',
    end_time: '',
  });
  const [errors, setErrors] = useState({});

  useEffect(() => {
    if (shift) {
      setFormData({
        shift_type_id: shift.shift_type_id || '',
        employee_id: shift.employee_id || '',
        department_id: shift.department_id || (departments?.[0]?.id || ''),
        shift_date: shift.shift_date || date || '',
        start_time: shift.start_time?.substring(0, 5) || '',
        end_time: shift.end_time?.substring(0, 5) || '',
      });
    } else {
      setFormData({
        shift_type_id: '',
        employee_id: '',
        department_id: departments?.[0]?.id || '', // Setze ersten Bereich als Standard
        shift_date: date || '',
        start_time: '',
        end_time: '',
      });
    }
    setErrors({});
  }, [shift, date, isOpen, departments]);

  const handleShiftTypeChange = (shiftTypeId) => {
    const shiftType = shiftTypes.find(st => st.id === parseInt(shiftTypeId));
    setFormData({
      ...formData,
      shift_type_id: shiftTypeId,
      start_time: shiftType?.default_start?.substring(0, 5) || '',
      end_time: shiftType?.default_end?.substring(0, 5) || '',
    });
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    setErrors({});

    if (!formData.shift_type_id) {
      setErrors({ shift_type_id: ['Bitte wählen Sie einen Schichttyp'] });
      return;
    }

    onSave(formData);
  };

  const selectedShiftType = shiftTypes.find(st => st.id === parseInt(formData.shift_type_id));
  const filteredEmployees = formData.department_id 
    ? employees.filter(emp => emp.departments?.some(d => d.id === parseInt(formData.department_id)))
    : employees;

  const getEmployeeHourInfo = (employee) => {
    if (!employee.max_monthly_hours) return null;
    const planned = employee.monthly_planned_hours || 0;
    const max = employee.max_monthly_hours;
    const remaining = max - planned;
    const percentage = (planned / max) * 100;
    
    return {
      planned,
      max,
      remaining,
      percentage,
      isOverloaded: remaining < 0,
      isNearLimit: remaining < max * 0.2 && remaining >= 0,
    };
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={shift ? 'Schicht bearbeiten' : 'Neue Schicht'}
      size="md"
      footer={
        <div className="flex items-center justify-end gap-3">
          <Button variant="ghost" onClick={onClose}>
            Abbrechen
          </Button>
          <Button variant="primary" onClick={handleSubmit}>
            {shift ? 'Speichern' : 'Erstellen'}
          </Button>
        </div>
      }
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        <Input
          type="date"
          label="Datum"
          value={formData.shift_date}
          onChange={(e) => setFormData({ ...formData, shift_date: e.target.value })}
          error={errors.shift_date?.[0]}
          required
        />

        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1.5">
            Schichttyp *
          </label>
          <select
            className={`input ${errors.shift_type_id ? 'border-error-500' : ''}`}
            value={formData.shift_type_id}
            onChange={(e) => handleShiftTypeChange(e.target.value)}
            required
          >
            <option value="">Bitte wählen...</option>
            {shiftTypes.map((type) => (
              <option key={type.id} value={type.id}>
                {type.name} ({type.default_start?.substring(0, 5)} - {type.default_end?.substring(0, 5)})
              </option>
            ))}
          </select>
          {errors.shift_type_id && (
            <p className="mt-1.5 text-sm text-error-500">{errors.shift_type_id[0]}</p>
          )}
        </div>

        {selectedShiftType && selectedShiftType.departments?.length > 0 && (
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1.5">
              Station
            </label>
            <select
              className="input"
              value={formData.department_id}
              onChange={(e) => setFormData({ ...formData, department_id: e.target.value, employee_id: '' })}
            >
              <option value="">Alle Stationen</option>
              {selectedShiftType.departments.map((dept) => (
                <option key={dept.id} value={dept.id}>
                  {dept.name}
                </option>
              ))}
            </select>
          </div>
        )}

        <div className="grid grid-cols-2 gap-4">
          <Input
            type="time"
            label="Startzeit"
            value={formData.start_time}
            onChange={(e) => setFormData({ ...formData, start_time: e.target.value })}
            error={errors.start_time?.[0]}
          />
          <Input
            type="time"
            label="Endzeit"
            value={formData.end_time}
            onChange={(e) => setFormData({ ...formData, end_time: e.target.value })}
            error={errors.end_time?.[0]}
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1.5">
            Mitarbeiter zuweisen
          </label>
          <select
            className="input"
            value={formData.employee_id}
            onChange={(e) => setFormData({ ...formData, employee_id: e.target.value })}
          >
            <option value="">Nicht besetzt</option>
            {filteredEmployees.map((emp) => {
              const hourInfo = getEmployeeHourInfo(emp);
              return (
                <option key={emp.id} value={emp.id}>
                  {emp.first_name} {emp.last_name}
                  {hourInfo && ` (${hourInfo.planned.toFixed(0)}/${hourInfo.max}h)`}
                </option>
              );
            })}
          </select>
          
          {formData.employee_id && (() => {
            const selectedEmp = employees.find(e => e.id === parseInt(formData.employee_id));
            const hourInfo = selectedEmp ? getEmployeeHourInfo(selectedEmp) : null;
            
            if (!hourInfo) return null;
            
            return (
              <div className={`mt-2 p-3 rounded-lg border ${
                hourInfo.isOverloaded 
                  ? 'bg-error-50 border-error-200' 
                  : hourInfo.isNearLimit 
                  ? 'bg-warning-50 border-warning-200'
                  : 'bg-success-50 border-success-200'
              }`}>
                <div className="flex items-start gap-2">
                  {hourInfo.isOverloaded && <AlertCircle className="w-4 h-4 text-error-600 flex-shrink-0 mt-0.5" />}
                  <div className="flex-1">
                    <p className={`text-sm font-medium ${
                      hourInfo.isOverloaded ? 'text-error-900' : hourInfo.isNearLimit ? 'text-warning-900' : 'text-success-900'
                    }`}>
                      Monatliches Stundenkontingent
                    </p>
                    <div className="flex items-center gap-2 mt-1">
                      <div className="flex-1 bg-neutral-200 rounded-full h-2">
                        <div 
                          className={`h-2 rounded-full ${
                            hourInfo.isOverloaded 
                              ? 'bg-error-500' 
                              : hourInfo.isNearLimit 
                              ? 'bg-warning-500'
                              : 'bg-success-500'
                          }`}
                          style={{ width: `${Math.min(hourInfo.percentage, 100)}%` }}
                        />
                      </div>
                      <span className="text-xs font-medium text-neutral-700">
                        {hourInfo.percentage.toFixed(0)}%
                      </span>
                    </div>
                    <p className="text-xs text-neutral-600 mt-1">
                      Geplant: {hourInfo.planned.toFixed(1)}h / {hourInfo.max}h
                      {hourInfo.remaining >= 0 ? (
                        <span className="text-success-600"> ({hourInfo.remaining.toFixed(1)}h verfügbar)</span>
                      ) : (
                        <span className="text-error-600"> ({Math.abs(hourInfo.remaining).toFixed(1)}h überschritten!)</span>
                      )}
                    </p>
                  </div>
                </div>
              </div>
            );
          })()}
        </div>
      </form>
    </Modal>
  );
}