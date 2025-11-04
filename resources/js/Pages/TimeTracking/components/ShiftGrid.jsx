import React from 'react';
import ShiftCard from './ShiftCard';
import ShiftTypeSelector from './ShiftTypeSelector';
import dayjs from 'dayjs';
import axios from 'axios';

export default function ShiftGrid({ employees, shifts, shift_types, reload }) {
  const days = Array.from({ length: 7 }, (_, i) => dayjs().startOf('week').add(i, 'day'));

  const assignShift = async (employeeId, date, typeId) => {
    await axios.post('/api/shifts', { employee_id: employeeId, shift_date: date, shift_type_id: typeId });
    reload();
  };

  const deleteShift = async (shiftId) => {
    await axios.delete(`/api/shifts/${shiftId}`);
    reload();
  };

  return (
    <div className="overflow-auto bg-white rounded shadow">
      <table className="min-w-full border">
        <thead className="bg-gray-100">
          <tr>
            <th className="p-2 border">Mitarbeiter</th>
            {days.map((d, i) => (
              <th key={i} className="p-2 border text-center">{d.format('dd DD.MM')}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {employees.map(emp => (
            <tr key={emp.id}>
              <td className="p-2 border font-semibold">{emp.first_name} {emp.last_name}</td>
              {days.map((d, i) => {
                const shift = shifts.find(s => s.employee_id === emp.id && s.shift_date === d.format('YYYY-MM-DD'));
                return (
                  <td key={i} className="p-1 border text-center align-top">
                    {shift ? (
                      <ShiftCard shift={shift} onDelete={() => deleteShift(shift.id)} />
                    ) : (
                      <ShiftTypeSelector onSelect={(typeId) => assignShift(emp.id, d.format('YYYY-MM-DD'), typeId)} types={shift_types} />
                    )}
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
