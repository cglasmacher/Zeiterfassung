import React from 'react';

export default function ShiftCard({ shift, onDelete }) {
  return (
    <div className="bg-blue-100 rounded p-1 shadow-sm text-sm relative">
      <p className="font-medium">{shift.shift_type.name}</p>
      <p>{shift.start_time.slice(0,5)} – {shift.end_time.slice(0,5)}</p>
      <button
        onClick={onDelete}
        className="absolute top-0 right-0 text-xs text-red-500 px-1"
        title="Löschen"
      >
        ✕
      </button>
    </div>
  );
}
