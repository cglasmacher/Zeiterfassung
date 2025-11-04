import React from "react";
import { useDraggable } from "@dnd-kit/core";
import { Clock, Trash2, GripVertical } from 'lucide-react';

export default function DraggableShift({ shift, onDelete, color = 'bg-primary-500' }) {
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
    id: `shift-${shift.id}`,
    data: { shift },
  });

  return (
    <div
      ref={setNodeRef}
      className={`
        ${color} text-white rounded-lg p-3 shadow-md
        transition-all duration-200 cursor-grab active:cursor-grabbing
        hover:shadow-lg hover:scale-105 group relative
        ${isDragging ? 'opacity-50 scale-95' : ''}
      `}
      {...listeners}
      {...attributes}
    >
      <div className="flex items-start justify-between gap-2">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-1.5 mb-1">
            <GripVertical className="w-3 h-3 opacity-50" />
            <p className="font-semibold text-sm truncate">
              {shift.shift_type?.name || 'Schicht'}
            </p>
          </div>
          <div className="flex items-center gap-1.5 text-xs opacity-90">
            <Clock className="w-3 h-3" />
            <span>
              {shift.start_time?.slice(0, 5)} - {shift.end_time?.slice(0, 5)}
            </span>
          </div>
          {shift.planned_hours && (
            <p className="text-xs opacity-75 mt-1">
              {shift.planned_hours}h geplant
            </p>
          )}
        </div>
        
        <button
          onClick={(e) => {
            e.stopPropagation();
            onDelete();
          }}
          className="opacity-0 group-hover:opacity-100 transition-opacity p-1 hover:bg-white/20 rounded"
          title="Schicht löschen"
        >
          <Trash2 className="w-3.5 h-3.5" />
        </button>
      </div>
    </div>
  );
}