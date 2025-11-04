import React, { useState } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import axios from 'axios';

export default function Exports() {
  const [month, setMonth] = useState(10);
  const [year, setYear] = useState(2025);
  const [exporting, setExporting] = useState(false);
  const [message, setMessage] = useState('');

  const handleExport = async () => {
    setExporting(true);
    try {
      const res = await axios.post('/api/exports/run', { month, year });
      setMessage(`Export erfolgreich: ${res.data.filename}`);
    } catch (e) {
      setMessage('Fehler beim Export');
    } finally {
      setExporting(false);
    }
  };

  return (
    <TimeTrackingLayout>
      <h1 className="text-2xl font-bold mb-4">Exporte</h1>
      <div className="flex gap-2 mb-4">
        <input type="number" min="1" max="12" value={month} onChange={e => setMonth(e.target.value)} className="border p-2 rounded" />
        <input type="number" value={year} onChange={e => setYear(e.target.value)} className="border p-2 rounded" />
        <button
          onClick={handleExport}
          disabled={exporting}
          className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
        >
          {exporting ? 'Export läuft…' : 'Lexware-Export starten'}
        </button>
      </div>
      {message && <p>{message}</p>}
    </TimeTrackingLayout>
  );
}
