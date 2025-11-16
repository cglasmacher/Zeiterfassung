import React from 'react';
import Badge from '@/Components/ui/Badge';
import { User, Plus, X, AlertTriangle } from 'lucide-react';

export default function ShiftCell({ 
  shifts, 
  onAddShift, 
  onEditShift, 
  onDeleteShift, 
  date,
  onDragStart,
  onDragOver,
  onDrop,
  shiftTypeId 
}) {
  const getShiftColor = (shiftType) => {
    const name = shiftType?.name?.toLowerCase() || '';
    if (name.includes('früh')) return 'bg-primary-100 border-primary-300 text-primary-900';
    if (name.includes('spät')) return 'bg-accent-100 border-accent-300 text-accent-900';
    if (name.includes('nacht')) return 'bg-secondary-100 border-secondary-300 text-secondary-900';
    return 'bg-success-100 border-success-300 text-success-900';
  };

  const getEmployeeWorkloadColor = (employee) => {
    if (!employee?.max_monthly_hours) return '';
    const planned = parseFloat(employee.monthly_planned_hours) || 0;
    const max = parseFloat(employee.max_monthly_hours) || 0;
    const percentage = max > 0 ? (planned / max) * 100 : 0;
    
    if (percentage >= 100) return 'border-error-500 bg-error-50';
    if (percentage >= 80) return 'border-warning-500 bg-warning-50';
    return 'border-success-500 bg-success-50';
  };

  // Check for overlapping shifts (same employee, same day)
  const hasOverlap = (shift) => {
    if (!shift.employee_id) return false;
    return shifts.filter(s => 
      s.employee_id === shift.employee_id && 
      s.id !== shift.id
    ).length > 0;
  };

  const handleDragStart = (e, shift) => {
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('application/json', JSON.stringify({
      shiftId: shift.id,
      employeeId: shift.employee_id,
      shiftTypeId: shift.shift_type_id,
      sourceDate: date
    }));
    if (onDragStart) onDragStart(shift);
  };

  const handleDragOver = (e) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    if (onDragOver) onDragOver(e);
  };

  const handleDrop = (e) => {
    e.preventDefault();
    if (onDrop) {
      const data = JSON.parse(e.dataTransfer.getData('application/json'));
      onDrop(data, date, shiftTypeId);
    }
  };

  return (
    <div 
      className="min-h-[100px] p-2 space-y-1"
      onDragOver={handleDragOver}
      onDrop={handleDrop}
    >
      {shifts.map((shift) => {
        const overlap = hasOverlap(shift);
        const workloadColor = shift.employee ? getEmployeeWorkloadColor(shift.employee) : '';
        
        return (
          <div
            key={shift.id}
            draggable={!!shift.employee_id}
            onDragStart={(e) => handleDragStart(e, shift)}
            className={`p-2 rounded-lg border-2 cursor-pointer hover:shadow-md transition-all ${
              shift.employee 
                ? `${getShiftColor(shift.shift_type)} ${workloadColor}`
                : 'bg-neutral-50 border-dashed border-neutral-300'
            } ${overlap ? 'ring-2 ring-error-500 ring-offset-1' : ''}`}
            onClick={() => onEditShift(shift)}
          >
            <div className="flex items-start justify-between gap-1">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-1">
                  <p className="text-xs font-semibold truncate">
                    {shift.shift_type?.name || 'Schicht'}
                  </p>
                  {overlap && (
                    <AlertTriangle className="w-3 h-3 text-error-600 flex-shrink-0" title="Überschneidung erkannt!" />
                  )}
                </div>
                {shift.employee ? (
                  <>
                    <p className="text-xs truncate flex items-center gap-1 mt-1">
                      <User className="w-3 h-3 flex-shrink-0" />
                      {shift.employee.first_name} {shift.employee.last_name[0]}.
                    </p>
                    {shift.employee.max_monthly_hours && (
                      <p className="text-xs text-neutral-600 mt-0.5">
                        {((parseFloat(shift.employee.monthly_planned_hours) || 0) / parseFloat(shift.employee.max_monthly_hours) * 100).toFixed(0)}% Auslastung
                      </p>
                    )}
                  </>
                ) : (
                  <p className="text-xs text-neutral-500 italic mt-1">Nicht besetzt</p>
                )}
                <p className="text-xs text-neutral-600 mt-0.5">
                  {shift.start_time?.substring(0, 5)} - {shift.end_time?.substring(0, 5)}
                </p>
              </div>
              <button
                onClick={(e) => {
                  e.stopPropagation();
                  onDeleteShift(shift);
                }}
                className="p-1 hover:bg-error-100 rounded transition-colors"
              >
                <X className="w-3 h-3 text-error-600" />
              </button>
            </div>
          </div>
        );
      })}
      
      <button
        onClick={() => onAddShift(date)}
        className="w-full p-2 border-2 border-dashed border-neutral-300 rounded-lg hover:border-primary-400 hover:bg-primary-50 transition-all flex items-center justify-center gap-1 text-neutral-600 hover:text-primary-600"
      >
        <Plus className="w-4 h-4" />
        <span className="text-xs font-medium">Schicht</span>
      </button>
    </div>
  );
}