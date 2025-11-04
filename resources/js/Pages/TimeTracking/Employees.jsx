import React, { useEffect, useState } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import axios from 'axios';

export default function Employees() {
  const [employees, setEmployees] = useState([]);

  useEffect(() => {
    axios.get('/api/employees').then(res => setEmployees(res.data));
  }, []);

  return (
    <TimeTrackingLayout>
      <h1 className="text-2xl font-bold mb-4">Mitarbeiter</h1>
      <table className="bg-white rounded shadow w-full">
        <thead className="bg-gray-100">
          <tr>
            <th className="p-2 text-left">Name</th>
            <th className="p-2 text-left">Position</th>
            <th className="p-2 text-left">RFID</th>
            <th className="p-2 text-left">Status</th>
          </tr>
        </thead>
        <tbody>
          {employees.map(emp => (
            <tr key={emp.id} className="border-b">
              <td className="p-2">{emp.first_name} {emp.last_name}</td>
              <td className="p-2">{emp.position || '-'}</td>
              <td className="p-2">{emp.rfid_tag || '-'}</td>
              <td className="p-2">{emp.active ? 'Aktiv' : 'Inaktiv'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </TimeTrackingLayout>
  );
}
