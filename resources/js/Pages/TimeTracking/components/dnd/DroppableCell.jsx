import React from "react";
import { useDroppable } from "@dnd-kit/core";

export default function DroppableCell({ employee, date, children }) {
  const { setNodeRef, isOver } = useDroppable({
    id: `cell-${employee.id}-${date}`,
    data: { employee, date },
  });

  return (
    <div
      ref={setNodeRef}
      className={`
        min-h-[80px] rounded-lg transition-all duration-200 p-1
        ${isOver 
          ? 'bg-primary-100 border-2 border-primary-400 border-dashed scale-105' 
          : 'border-2 border-transparent'
        }
      `}
    >
      {children}
    </div>
  );
}
