import React, { useEffect, useState } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import axios from 'axios';

export default function Dashboard() {
  const [summary, setSummary] = useState(null);

  useEffect(() => {
    axios.get('/api/summary/current?rfid_tag=ABCD1234').then(res => setSummary(res.data));
  }, []);

  return (
    <TimeTrackingLayout>
      <h1 className="text-2xl font-bold mb-4">Dashboard</h1>
      {summary ? (
        <div className="bg-white rounded-xl shadow p-6">
          <p><strong>Mitarbeiter:</strong> {summary.employee}</p>
          <p><strong>Datum:</strong> {summary.date}</p>
          {summary.open_entry ? (
            <>
              <p className="text-green-600">Eingestempelt seit: {summary.open_entry.clock_in}</p>
              <p>Aktuelle Arbeitszeit: {summary.open_entry.hours_since_in.toFixed(2)} Std</p>
            </>
          ) : (
            <p className="text-gray-500">Derzeit nicht eingestempelt</p>
          )}
          {summary.daily_summary && (
            <p className="mt-2">
              Heutige Gesamtstunden: {summary.daily_summary.total_hours.toFixed(2)}h
            </p>
          )}
        </div>
      ) : (
        <p>Lade Daten…</p>
      )}
    </TimeTrackingLayout>
  );
}
