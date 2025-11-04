import React, { useEffect, useState } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import axios from 'axios';

export default function MonthlyOverview() {
  const [year, setYear] = useState(2025);
  const [month, setMonth] = useState(10);
  const [summary, setSummary] = useState(null);

  useEffect(() => {
    axios.get(`/api/summary/monthly/1/${year}/${month}`).then(res => setSummary(res.data));
  }, [year, month]);

  return (
    <TimeTrackingLayout>
      <h1 className="text-2xl font-bold mb-4">Monatsübersicht</h1>
      <div className="flex gap-2 mb-4">
        <input type="number" value={month} min="1" max="12" onChange={e => setMonth(e.target.value)} className="border p-2 rounded" />
        <input type="number" value={year} onChange={e => setYear(e.target.value)} className="border p-2 rounded" />
      </div>
      {summary && (
        <div className="bg-white p-6 shadow rounded">
          <p><strong>Mitarbeiter:</strong> {summary.employee}</p>
          <p>Monat: {summary.month_label}</p>
          <p>Arbeitszeit: {summary.total_hours}h</p>
          <p>Pausen: {summary.total_break_minutes} Min</p>
          <p>Arbeitstage: {summary.working_days}</p>
        </div>
      )}
    </TimeTrackingLayout>
  );
}
