import React from "react";
import { Clock, UserPlus, X, AlertCircle } from 'lucide-react';

export default function PositionShiftCell({ 
  shifts = [], 
  onAssignEmployee, 
  onRemoveEmployee,
  onAddShift 
}) {
  
  const getShiftColor = (shiftType) => {
    const colors = {
      'Frühschicht': { bg: 'bg-primary-500', border: 'border-primary-300', light: 'bg-primary-50' },
      'Spätschicht': { bg: 'bg-accent-500', border: 'border-accent-300', light: 'bg-accent-50' },
      'Nachtschicht': { bg: 'bg-secondary-500', border: 'border-secondary-300', light: 'bg-secondary-50' },
      'Sonderschicht': { bg: 'bg-success-500', border: 'border-success-300', light: 'bg-success-50' },
    };
    return colors[shiftType?.name] || { bg: 'bg-neutral-500', border: 'border-neutral-300', light: 'bg-neutral-50' };
  };

  return (
    <div className="space-y-2 min-h-[80px]">
      {shifts.map((shift) => {
        const isVacant = !shift.employee_id;
        const colors = getShiftColor(shift.shift_type);

        if (isVacant) {
          // Vakante Schicht
          return (
            <button
              key={shift.id}
              onClick={() => onAssignEmployee(shift)}
              className={`w-full text-left p-2.5 rounded-lg border-2 border-dashed ${colors.border} ${colors.light} hover:shadow-md transition-all group`}
            >
              <div className="flex items-start justify-between gap-2">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-1.5 mb-1">
                    <AlertCircle className="w-3 h-3 text-warning-600 flex-shrink-0" />
                    <p className="text-xs font-semibold text-neutral-700 truncate">
                      {shift.shift_type?.name}
                    </p>
                  </div>
                  <div className="flex items-center gap-1 text-xs text-neutral-600">
                    <Clock className="w-3 h-3" />
                    <span>{shift.start_time?.slice(0, 5)} - {shift.end_time?.slice(0, 5)}</span>
                  </div>
                  <p className="text-xs font-semibold text-warning-600 mt-1">VAKANT</p>
                </div>
                <UserPlus className="w-4 h-4 text-neutral-400 group-hover:text-primary-500 transition-colors flex-shrink-0" />
              </div>
            </button>
          );
        } else {
          // Besetzte Schicht
          return (
            <div
              key={shift.id}
              className={`${colors.bg} text-white p-2.5 rounded-lg shadow-md hover:shadow-lg transition-all group relative`}
            >
              <div className="flex items-start justify-between gap-2">
                <div className="flex-1 min-w-0">
                  <p className="text-xs font-semibold mb-1 truncate">
                    {shift.shift_type?.name}
                  </p>
                  <div className="flex items-center gap-1 text-xs opacity-90 mb-1">
                    <Clock className="w-3 h-3" />
                    <span>{shift.start_time?.slice(0, 5)} - {shift.end_time?.slice(0, 5)}</span>
                  </div>
                  <p className="text-xs font-bold truncate">
                    {shift.employee?.first_name} {shift.employee?.last_name}
                  </p>
                </div>
                <button
                  onClick={() => onRemoveEmployee(shift)}
                  className="opacity-0 group-hover:opacity-100 transition-opacity p-1 hover:bg-white/20 rounded flex-shrink-0"
                  title="Zuweisung entfernen"
                >
                  <X className="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          );
        }
      })}

      {/* Add new shift button */}
      <button
        onClick={onAddShift}
        className="w-full p-2.5 border-2 border-dashed border-neutral-300 rounded-lg hover:border-primary-500 hover:bg-primary-50 transition-all flex items-center justify-center gap-2 text-neutral-500 hover:text-primary-600 group"
      >
        <UserPlus className="w-4 h-4" />
        <span className="text-xs font-medium">Schicht hinzufügen</span>
      </button>
    </div>
  );
}