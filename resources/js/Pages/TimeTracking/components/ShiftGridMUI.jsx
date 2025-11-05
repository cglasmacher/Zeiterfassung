import React, { useState } from "react";
import dayjs from "dayjs";
import axios from "axios";
import {
  DndContext,
  PointerSensor,
  useSensor,
  useSensors,
  DragOverlay,
} from "@dnd-kit/core";
import DraggableShift from "./dnd/DraggableShift";
import DroppableCell from "./dnd/DroppableCell";
import ShiftTypeDialog from "./ShiftTypeDialog";
import Badge from '@/Components/ui/Badge';
import { Clock, Plus, AlertCircle } from 'lucide-react';

export default function ShiftGridMUI({ employees, shifts, shift_types, reload, weekStart, toast }) {
  const [activeShift, setActiveShift] = useState(null);
  
  const days = Array.from({ length: 7 }, (_, i) =>
    (weekStart || dayjs().startOf("week")).add(i, "day")
  );

  const sensors = useSensors(useSensor(PointerSensor, {
    activationConstraint: {
      distance: 8,
    },
  }));

  const handleAssign = async (empId, date, typeId) => {
    try {
      await axios.post("/api/shifts", {
        employee_id: empId,
        shift_date: date,
        shift_type_id: typeId,
      });
      toast?.success('Schicht erfolgreich erstellt');
      reload();
    } catch (error) {
      console.error('Error assigning shift:', error);
      toast?.error('Fehler beim Erstellen der Schicht');
    }
  };

  const handleDelete = async (id) => {
    if (!confirm('Schicht wirklich löschen?')) return;
    
    try {
      await axios.delete(`/api/shifts/${id}`);
      toast?.success('Schicht erfolgreich gelöscht');
      reload();
    } catch (error) {
      console.error('Error deleting shift:', error);
      toast?.error('Fehler beim Löschen der Schicht');
    }
  };

  const handleDragStart = (event) => {
    setActiveShift(event.active.data.current.shift);
  };

  const handleDragEnd = async (event) => {
    const { active, over } = event;
    setActiveShift(null);
    
    if (!over) return;

    const draggedShift = active.data.current.shift;
    const targetEmp = over.data.current.employee;
    const targetDate = over.data.current.date;

    if (
      draggedShift.employee_id !== targetEmp.id ||
      draggedShift.shift_date !== targetDate
    ) {
      try {
        await axios.put(`/api/shifts/${draggedShift.id}`, {
          employee_id: targetEmp.id,
          shift_date: targetDate,
        });
        toast?.success('Schicht erfolgreich verschoben');
        reload();
      } catch (error) {
        console.error('Error moving shift:', error);
        toast?.error('Fehler beim Verschieben der Schicht');
      }
    }
  };

  const isWeekend = (date) => {
    return date.day() === 0 || date.day() === 6;
  };

  const isToday = (date) => {
    return date.isSame(dayjs(), 'day');
  };

  const getShiftColor = (shiftType) => {
    const colors = {
      'Frühschicht': 'bg-primary-500',
      'Spätschicht': 'bg-accent-500',
      'Nachtschicht': 'bg-secondary-500',
      'Sonderschicht': 'bg-success-500',
    };
    return colors[shiftType?.name] || 'bg-neutral-500';
  };

  return (
    <div className="overflow-x-auto custom-scrollbar">
      <DndContext 
        sensors={sensors} 
        onDragStart={handleDragStart}
        onDragEnd={handleDragEnd}
      >
        <table className="w-full border-collapse">
          <thead className="bg-neutral-50 sticky top-0 z-10">
            <tr>
              <th className="px-6 py-4 text-left font-semibold text-neutral-900 border-b-2 border-neutral-200 min-w-[200px]">
                Mitarbeiter
              </th>
              {days.map((d, i) => (
                <th 
                  key={i} 
                  className={`px-4 py-4 text-center font-semibold border-b-2 border-neutral-200 min-w-[140px] ${
                    isWeekend(d) ? 'bg-neutral-100' : ''
                  } ${isToday(d) ? 'bg-primary-50 border-primary-300' : ''}`}
                >
                  <div className="flex flex-col items-center gap-1">
                    <span className="text-xs text-neutral-500 uppercase">
                      {d.format("dd")}
                    </span>
                    <span className={`text-lg ${isToday(d) ? 'text-primary-600 font-bold' : 'text-neutral-900'}`}>
                      {d.format("DD")}
                    </span>
                    <span className="text-xs text-neutral-500">
                      {d.format("MMM")}
                    </span>
                  </div>
                </th>
              ))}
            </tr>
          </thead>

          <tbody>
            {employees?.map((emp) => (
              <tr 
                key={emp.id}
                className="border-b border-neutral-200 hover:bg-neutral-50 transition-colors"
              >
                <td className="px-6 py-4 border-r border-neutral-200">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-semibold flex-shrink-0">
                      {emp.first_name?.charAt(0)}{emp.last_name?.charAt(0)}
                    </div>
                    <div className="min-w-0">
                      <p className="font-semibold text-neutral-900 truncate">
                        {emp.first_name} {emp.last_name}
                      </p>
                      <p className="text-xs text-neutral-500 truncate">
                        {emp.position || 'Mitarbeiter'}
                      </p>
                    </div>
                  </div>
                </td>

                {days.map((d, i) => {
                  const dateStr = d.format("YYYY-MM-DD");
                  const shift = shifts?.find(
                    (s) =>
                      s.employee_id === emp.id && s.shift_date === dateStr
                  );

                  return (
                    <td 
                      key={i} 
                      className={`px-2 py-2 align-top ${
                        isWeekend(d) ? 'bg-neutral-50' : ''
                      } ${isToday(d) ? 'bg-primary-50/50' : ''}`}
                    >
                      <DroppableCell employee={emp} date={dateStr}>
                        {shift ? (
                          <DraggableShift
                            shift={shift}
                            onDelete={() => handleDelete(shift.id)}
                            color={getShiftColor(shift.shift_type)}
                          />
                        ) : (
                          <ShiftTypeDialog
                            types={shift_types}
                            onSelect={(typeId) =>
                              handleAssign(emp.id, dateStr, typeId)
                            }
                          />
                        )}
                      </DroppableCell>
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>

        <DragOverlay>
          {activeShift ? (
            <div className={`${getShiftColor(activeShift.shift_type)} text-white p-3 rounded-lg shadow-2xl opacity-90 cursor-grabbing`}>
              <div className="flex items-center gap-2">
                <Clock className="w-4 h-4" />
                <div>
                  <p className="font-semibold text-sm">{activeShift.shift_type?.name}</p>
                  <p className="text-xs opacity-90">
                    {activeShift.start_time?.slice(0, 5)} - {activeShift.end_time?.slice(0, 5)}
                  </p>
                </div>
              </div>
            </div>
          ) : null}
        </DragOverlay>
      </DndContext>
    </div>
  );
}