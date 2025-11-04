import React, { useEffect, useState } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import axios from 'axios';
import dayjs from 'dayjs';

export default function DailyOverview() {
  const [date, setDate] = useState(dayjs().format('YYYY-MM-DD'));
  const [data, setData] = useState([]);

  useEffect(() => {
    axios.get(`/api/summary/daily/1/${date}`).then(res => setData([res.data]));
  }, [date]);

  return (
    <TimeTrackingLayout>
      <h1 className="text-2xl font-bold mb-4">Tagesübersicht</h1>
      <input
        type="date"
        value={date}
        onChange={e => setDate(e.target.value)}
        className="border p-2 rounded mb-4"
      />
      {data.map((d, i) => (
        <div key={i} className="bg-white p-4 shadow rounded mb-2">
          <p><strong>{d.employee}</strong> – {d.work_date}</p>
          <p>Arbeitszeit: {d.total_hours} Std / Pausen: {d.total_break_minutes} Min</p>
        </div>
      ))}
    </TimeTrackingLayout>
  );
}
