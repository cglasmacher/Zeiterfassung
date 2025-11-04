import React from 'react';

export default function ShiftTypeSelector({ types, onSelect }) {
  return (
    <select
      onChange={(e) => { if (e.target.value) onSelect(e.target.value); }}
      className="border rounded p-1 text-sm"
      defaultValue=""
    >
      <option value="">–</option>
      {types.map(t => (
        <option key={t.id} value={t.id}>
          {t.name} ({t.default_start.slice(0,5)}–{t.default_end.slice(0,5)})
        </option>
      ))}
    </select>
  );
}
