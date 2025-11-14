import React from 'react';
import Badge from '@/Components/ui/Badge';
import { User, Plus, X } from 'lucide-react';

export default function ShiftCell({ shifts, onAddShift, onEditShift, onDeleteShift, date }) {
  const getShiftColor = (shiftType) => {
    const name = shiftType?.name?.toLowerCase() || '';
    if (name.includes('früh')) return 'bg-primary-100 border-primary-300 text-primary-900';
    if (name.includes('spät')) return 'bg-accent-100 border-accent-300 text-accent-900';
    if (name.includes('nacht')) return 'bg-secondary-100 border-secondary-300 text-secondary-900';
    return 'bg-success-100 border-success-300 text-success-900';
  };

  return (
    <div className="min-h-[100px] p-2 space-y-1">
      {shifts.map((shift) => (
        <div
          key={shift.id}
          className={`p-2 rounded-lg border-2 cursor-pointer hover:shadow-md transition-all ${
            shift.employee 
              ? getShiftColor(shift.shift_type)
              : 'bg-neutral-50 border-dashed border-neutral-300'
          }`}
          onClick={() => onEditShift(shift)}
        >
          <div className="flex items-start justify-between gap-1">
            <div className="flex-1 min-w-0">
              <p className="text-xs font-semibold truncate">
                {shift.shift_type?.name || 'Schicht'}
              </p>
              {shift.employee ? (
                <p className="text-xs truncate flex items-center gap-1 mt-1">
                  <User className="w-3 h-3 flex-shrink-0" />
                  {shift.employee.first_name} {shift.employee.last_name[0]}.
                </p>
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
      ))}
      
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