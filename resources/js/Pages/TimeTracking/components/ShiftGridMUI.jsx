import React from "react";
import {
  Box,
  Table,
  TableHead,
  TableBody,
  TableRow,
  TableCell,
  Typography,
} from "@mui/material";
import dayjs from "dayjs";
import axios from "axios";
import {
  DndContext,
  PointerSensor,
  useSensor,
  useSensors,
} from "@dnd-kit/core";
import DraggableShift from "./dnd/DraggableShift";
import DroppableCell from "./dnd/DroppableCell";
import ShiftTypeDialog from "./ShiftTypeDialog";

export default function ShiftGridMUI({ employees, shifts, shift_types, reload }) {
  const days = Array.from({ length: 7 }, (_, i) =>
    dayjs().startOf("week").add(i, "day")
  );

  const sensors = useSensors(useSensor(PointerSensor));

  const handleAssign = async (empId, date, typeId) => {
    await axios.post("/api/shifts", {
      employee_id: empId,
      shift_date: date,
      shift_type_id: typeId,
    });
    reload();
  };

  const handleDelete = async (id) => {
    await axios.delete(`/api/shifts/${id}`);
    reload();
  };

  const handleDragEnd = async (event) => {
    const { active, over } = event;
    if (!over) return;

    const draggedShift = active.data.current.shift;
    const targetEmp = over.data.current.employee;
    const targetDate = over.data.current.date;

    // nur ändern, wenn Ziel anders ist
    if (
      draggedShift.employee_id !== targetEmp.id ||
      draggedShift.shift_date !== targetDate
    ) {
      await axios.put(`/api/shifts/${draggedShift.id}`, {
        employee_id: targetEmp.id,
        shift_date: targetDate,
      });
      reload();
    }
  };

  return (
    <Box sx={{ overflowX: "auto" }}>
      <DndContext sensors={sensors} onDragEnd={handleDragEnd}>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell sx={{ fontWeight: 700 }}>Mitarbeiter</TableCell>
              {days.map((d, i) => (
                <TableCell key={i} align="center">
                  {d.format("dd")} <br /> {d.format("DD.MM")}
                </TableCell>
              ))}
            </TableRow>
          </TableHead>

          <TableBody>
            {employees.map((emp) => (
              <TableRow key={emp.id}>
                <TableCell sx={{ fontWeight: 600 }}>
                  {emp.first_name} {emp.last_name}
                </TableCell>

                {days.map((d, i) => {
                  const dateStr = d.format("YYYY-MM-DD");
                  const shift = shifts.find(
                    (s) =>
                      s.employee_id === emp.id && s.shift_date === dateStr
                  );

                  return (
                    <TableCell key={i} align="center" sx={{ verticalAlign: "top" }}>
                      <DroppableCell employee={emp} date={dateStr}>
                        {shift ? (
                          <DraggableShift
                            shift={shift}
                            onDelete={() => handleDelete(shift.id)}
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
                    </TableCell>
                  );
                })}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </DndContext>
    </Box>
  );
}
